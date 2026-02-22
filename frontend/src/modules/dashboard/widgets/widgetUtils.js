import {
  SunIcon,
  CloudIcon,
} from '@heroicons/vue/24/outline'

export function formatDate(dateString) {
  if (!dateString) return '-'
  return new Date(dateString).toLocaleDateString('de-DE', {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  })
}

export function getStatusColor(status) {
  return status === 'up' ? 'text-green-400' : 'text-red-400'
}

export function getStatusBg(status) {
  return status === 'up' ? 'bg-green-500' : 'bg-red-500'
}

export function getWeatherIcon(icon) {
  const icons = {
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
