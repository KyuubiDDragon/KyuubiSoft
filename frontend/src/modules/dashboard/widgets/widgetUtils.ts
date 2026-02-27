import type { Component } from 'vue'
import {
  SunIcon,
  CloudIcon,
} from '@heroicons/vue/24/outline'

/**
 * Status values for uptime/health widgets
 */
export type ServiceStatus = 'up' | 'down' | string

/**
 * Weather icon identifiers
 */
export type WeatherIconType =
  | 'sunny'
  | 'partly_cloudy'
  | 'cloudy'
  | 'rain'
  | 'snow'
  | 'thunderstorm'
  | 'fog'
  | 'drizzle'
  | 'showers'

export function formatDate(dateString: string | undefined | null): string {
  if (!dateString) return '-'
  return new Date(dateString).toLocaleDateString('de-DE', {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  })
}

export function getStatusColor(status: ServiceStatus): string {
  return status === 'up' ? 'text-green-400' : 'text-red-400'
}

export function getStatusBg(status: ServiceStatus): string {
  return status === 'up' ? 'bg-green-500' : 'bg-red-500'
}

export function getWeatherIcon(icon: WeatherIconType | string): Component {
  const icons: Record<string, Component> = {
    sunny: SunIcon,
    partly_cloudy: CloudIcon,
    cloudy: CloudIcon,
    rain: CloudIcon,
    snow: CloudIcon,
    thunderstorm: CloudIcon,
    fog: CloudIcon,
    drizzle: CloudIcon,
    showers: CloudIcon,
  }
  return icons[icon] || CloudIcon
}
