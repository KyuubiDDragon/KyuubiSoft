/**
 * Push Notifications Service
 * Handles browser push notification subscription and management
 */

import api from '@/core/api/axios'
import type { AxiosResponse } from 'axios'

/** Permission status including custom 'unsupported' state */
export type PushPermissionStatus = NotificationPermission | 'unsupported'

/** A push subscription record from the server */
export interface PushSubscriptionRecord {
  id: string
  endpoint: string
  device_name: string
  created_at: string
  [key: string]: unknown
}

/** Notification preferences configuration */
export interface NotificationPreferences {
  enabled: boolean
  [key: string]: unknown
}

/** Notification history entry */
export interface NotificationHistoryEntry {
  id: string
  title: string
  body: string
  sent_at: string
  [key: string]: unknown
}

/** VAPID key response from the server */
interface VapidKeyResponse {
  data: {
    publicKey: string
  }
}

class PushNotificationService {
  private swRegistration: ServiceWorkerRegistration | null
  public readonly isSupported: boolean

  constructor() {
    this.swRegistration = null
    this.isSupported = 'serviceWorker' in navigator && 'PushManager' in window
  }

  /**
   * Initialize the service worker
   */
  async init(): Promise<boolean> {
    if (!this.isSupported) {
      console.warn('Push notifications are not supported in this browser')
      return false
    }

    try {
      this.swRegistration = await navigator.serviceWorker.register('/sw.js', {
        scope: '/'
      })
      console.log('Service Worker registered:', this.swRegistration)
      return true
    } catch (error: unknown) {
      console.error('Service Worker registration failed:', error)
      return false
    }
  }

  /**
   * Get the current permission status
   */
  getPermissionStatus(): PushPermissionStatus {
    if (!this.isSupported) return 'unsupported'
    return Notification.permission
  }

  /**
   * Request notification permission
   */
  async requestPermission(): Promise<PushPermissionStatus> {
    if (!this.isSupported) return 'unsupported'

    try {
      const permission: NotificationPermission = await Notification.requestPermission()
      return permission
    } catch (error: unknown) {
      console.error('Failed to request permission:', error)
      return 'denied'
    }
  }

  /**
   * Check if user is currently subscribed
   */
  async isSubscribed(): Promise<boolean> {
    if (!this.swRegistration) return false

    try {
      const subscription: PushSubscription | null = await this.swRegistration.pushManager.getSubscription()
      return subscription !== null
    } catch (error: unknown) {
      console.error('Failed to check subscription:', error)
      return false
    }
  }

  /**
   * Subscribe to push notifications
   */
  async subscribe(deviceName: string | null = null): Promise<PushSubscription> {
    if (!this.isSupported) {
      throw new Error('Push notifications are not supported')
    }

    // Ensure service worker is registered
    if (!this.swRegistration) {
      await this.init()
    }

    // Request permission if not granted
    const permission: PushPermissionStatus = await this.requestPermission()
    if (permission !== 'granted') {
      throw new Error('Notification permission denied')
    }

    try {
      // Get VAPID public key from server
      const { data: vapidResponse }: AxiosResponse<VapidKeyResponse> = await api.get('/api/v1/push/vapid-key')
      const vapidPublicKey: string = vapidResponse.data.publicKey

      // Convert VAPID key to Uint8Array
      const applicationServerKey = this.urlBase64ToUint8Array(vapidPublicKey)

      // Subscribe to push service
      const subscription: PushSubscription = await this.swRegistration!.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: applicationServerKey.buffer as ArrayBuffer
      })

      // Send subscription to server
      await api.post('/api/v1/push/subscribe', {
        subscription: subscription.toJSON(),
        device_name: deviceName || this.getDeviceName()
      })

      return subscription
    } catch (error: unknown) {
      console.error('Failed to subscribe:', error)
      throw error
    }
  }

  /**
   * Unsubscribe from push notifications
   */
  async unsubscribe(): Promise<void> {
    if (!this.swRegistration) return

    try {
      const subscription: PushSubscription | null = await this.swRegistration.pushManager.getSubscription()

      if (subscription) {
        // Unsubscribe from push service
        await subscription.unsubscribe()

        // Remove from server
        await api.post('/api/v1/push/unsubscribe', {
          endpoint: subscription.endpoint
        })
      }
    } catch (error: unknown) {
      console.error('Failed to unsubscribe:', error)
      throw error
    }
  }

  /**
   * Get user's subscriptions from server
   */
  async getSubscriptions(): Promise<PushSubscriptionRecord[]> {
    try {
      const { data }: AxiosResponse = await api.get('/api/v1/push/subscriptions')
      return data.data as PushSubscriptionRecord[]
    } catch (error: unknown) {
      console.error('Failed to get subscriptions:', error)
      return []
    }
  }

  /**
   * Get notification preferences
   */
  async getPreferences(): Promise<NotificationPreferences | null> {
    try {
      const { data }: AxiosResponse = await api.get('/api/v1/push/preferences')
      return data.data as NotificationPreferences
    } catch (error: unknown) {
      console.error('Failed to get preferences:', error)
      return null
    }
  }

  /**
   * Update notification preferences
   */
  async updatePreferences(preferences: NotificationPreferences): Promise<boolean> {
    try {
      await api.put('/api/v1/push/preferences', preferences)
      return true
    } catch (error: unknown) {
      console.error('Failed to update preferences:', error)
      throw error
    }
  }

  /**
   * Get notification history
   */
  async getHistory(limit: number = 50, offset: number = 0): Promise<NotificationHistoryEntry[]> {
    try {
      const { data }: AxiosResponse = await api.get('/api/v1/push/history', {
        params: { limit, offset }
      })
      return data.data as NotificationHistoryEntry[]
    } catch (error: unknown) {
      console.error('Failed to get history:', error)
      return []
    }
  }

  /**
   * Send test notification
   */
  async sendTest(): Promise<unknown> {
    try {
      const { data }: AxiosResponse = await api.post('/api/v1/push/test')
      return data
    } catch (error: unknown) {
      console.error('Failed to send test notification:', error)
      throw error
    }
  }

  /**
   * Convert URL-safe base64 to Uint8Array
   */
  private urlBase64ToUint8Array(base64String: string): Uint8Array {
    const padding: string = '='.repeat((4 - base64String.length % 4) % 4)
    const base64: string = (base64String + padding)
      .replace(/-/g, '+')
      .replace(/_/g, '/')

    const rawData: string = window.atob(base64)
    const outputArray: Uint8Array = new Uint8Array(rawData.length)

    for (let i = 0; i < rawData.length; ++i) {
      outputArray[i] = rawData.charCodeAt(i)
    }

    return outputArray
  }

  /**
   * Get device name based on user agent
   */
  private getDeviceName(): string {
    const ua: string = navigator.userAgent

    if (/Android/i.test(ua)) return 'Android Device'
    if (/iPhone|iPad|iPod/i.test(ua)) return 'iOS Device'
    if (/Windows/i.test(ua)) return 'Windows PC'
    if (/Mac/i.test(ua)) return 'Mac'
    if (/Linux/i.test(ua)) return 'Linux PC'

    return 'Unknown Device'
  }
}

// Export singleton instance
export const pushNotifications: PushNotificationService = new PushNotificationService()
export default pushNotifications
