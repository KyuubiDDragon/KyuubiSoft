/**
 * Push Notifications Service
 * Handles browser push notification subscription and management
 */

import api from '@/core/api/axios';

class PushNotificationService {
  constructor() {
    this.swRegistration = null;
    this.isSupported = 'serviceWorker' in navigator && 'PushManager' in window;
  }

  /**
   * Initialize the service worker
   */
  async init() {
    if (!this.isSupported) {
      console.warn('Push notifications are not supported in this browser');
      return false;
    }

    try {
      this.swRegistration = await navigator.serviceWorker.register('/sw.js', {
        scope: '/'
      });
      console.log('Service Worker registered:', this.swRegistration);
      return true;
    } catch (error) {
      console.error('Service Worker registration failed:', error);
      return false;
    }
  }

  /**
   * Get the current permission status
   */
  getPermissionStatus() {
    if (!this.isSupported) return 'unsupported';
    return Notification.permission;
  }

  /**
   * Request notification permission
   */
  async requestPermission() {
    if (!this.isSupported) return 'unsupported';

    try {
      const permission = await Notification.requestPermission();
      return permission;
    } catch (error) {
      console.error('Failed to request permission:', error);
      return 'denied';
    }
  }

  /**
   * Check if user is currently subscribed
   */
  async isSubscribed() {
    if (!this.swRegistration) return false;

    try {
      const subscription = await this.swRegistration.pushManager.getSubscription();
      return subscription !== null;
    } catch (error) {
      console.error('Failed to check subscription:', error);
      return false;
    }
  }

  /**
   * Subscribe to push notifications
   */
  async subscribe(deviceName = null) {
    if (!this.isSupported) {
      throw new Error('Push notifications are not supported');
    }

    // Ensure service worker is registered
    if (!this.swRegistration) {
      await this.init();
    }

    // Request permission if not granted
    const permission = await this.requestPermission();
    if (permission !== 'granted') {
      throw new Error('Notification permission denied');
    }

    try {
      // Get VAPID public key from server
      const { data: vapidResponse } = await api.get('/api/v1/push/vapid-key');
      const vapidPublicKey = vapidResponse.data.publicKey;

      // Convert VAPID key to Uint8Array
      const applicationServerKey = this.urlBase64ToUint8Array(vapidPublicKey);

      // Subscribe to push service
      const subscription = await this.swRegistration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey
      });

      // Send subscription to server
      await api.post('/api/v1/push/subscribe', {
        subscription: subscription.toJSON(),
        device_name: deviceName || this.getDeviceName()
      });

      return subscription;
    } catch (error) {
      console.error('Failed to subscribe:', error);
      throw error;
    }
  }

  /**
   * Unsubscribe from push notifications
   */
  async unsubscribe() {
    if (!this.swRegistration) return;

    try {
      const subscription = await this.swRegistration.pushManager.getSubscription();

      if (subscription) {
        // Unsubscribe from push service
        await subscription.unsubscribe();

        // Remove from server
        await api.post('/api/v1/push/unsubscribe', {
          endpoint: subscription.endpoint
        });
      }
    } catch (error) {
      console.error('Failed to unsubscribe:', error);
      throw error;
    }
  }

  /**
   * Get user's subscriptions from server
   */
  async getSubscriptions() {
    try {
      const { data } = await api.get('/api/v1/push/subscriptions');
      return data.data;
    } catch (error) {
      console.error('Failed to get subscriptions:', error);
      return [];
    }
  }

  /**
   * Get notification preferences
   */
  async getPreferences() {
    try {
      const { data } = await api.get('/api/v1/push/preferences');
      return data.data;
    } catch (error) {
      console.error('Failed to get preferences:', error);
      return null;
    }
  }

  /**
   * Update notification preferences
   */
  async updatePreferences(preferences) {
    try {
      await api.put('/api/v1/push/preferences', preferences);
      return true;
    } catch (error) {
      console.error('Failed to update preferences:', error);
      throw error;
    }
  }

  /**
   * Get notification history
   */
  async getHistory(limit = 50, offset = 0) {
    try {
      const { data } = await api.get('/api/v1/push/history', {
        params: { limit, offset }
      });
      return data.data;
    } catch (error) {
      console.error('Failed to get history:', error);
      return [];
    }
  }

  /**
   * Send test notification
   */
  async sendTest() {
    try {
      const { data } = await api.post('/api/v1/push/test');
      return data;
    } catch (error) {
      console.error('Failed to send test notification:', error);
      throw error;
    }
  }

  /**
   * Convert URL-safe base64 to Uint8Array
   */
  urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding)
      .replace(/-/g, '+')
      .replace(/_/g, '/');

    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);

    for (let i = 0; i < rawData.length; ++i) {
      outputArray[i] = rawData.charCodeAt(i);
    }

    return outputArray;
  }

  /**
   * Get device name based on user agent
   */
  getDeviceName() {
    const ua = navigator.userAgent;

    if (/Android/i.test(ua)) return 'Android Device';
    if (/iPhone|iPad|iPod/i.test(ua)) return 'iOS Device';
    if (/Windows/i.test(ua)) return 'Windows PC';
    if (/Mac/i.test(ua)) return 'Mac';
    if (/Linux/i.test(ua)) return 'Linux PC';

    return 'Unknown Device';
  }
}

// Export singleton instance
export const pushNotifications = new PushNotificationService();
export default pushNotifications;
