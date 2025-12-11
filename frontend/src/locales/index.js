import { createI18n } from 'vue-i18n'
import de from './de.json'
import en from './en.json'

// Available languages
export const availableLocales = [
  { code: 'de', name: 'Deutsch', flag: '🇩🇪' },
  { code: 'en', name: 'English', flag: '🇬🇧' },
]

// Get stored locale or browser language
function getDefaultLocale() {
  // Check localStorage first
  const stored = localStorage.getItem('locale')
  if (stored && availableLocales.some(l => l.code === stored)) {
    return stored
  }

  // Check browser language
  const browserLang = navigator.language?.split('-')[0]
  if (availableLocales.some(l => l.code === browserLang)) {
    return browserLang
  }

  // Default to German
  return 'de'
}

// Create i18n instance
const i18n = createI18n({
  legacy: false, // Use Composition API mode
  globalInjection: true, // Enable $t in templates
  locale: getDefaultLocale(),
  fallbackLocale: 'de',
  messages: {
    de,
    en,
  },
  // Date/time formatting
  datetimeFormats: {
    de: {
      short: {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
      },
      long: {
        year: 'numeric',
        month: 'long',
        day: '2-digit',
        weekday: 'long',
      },
      time: {
        hour: '2-digit',
        minute: '2-digit',
      },
      datetime: {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
      },
    },
    en: {
      short: {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
      },
      long: {
        year: 'numeric',
        month: 'long',
        day: '2-digit',
        weekday: 'long',
      },
      time: {
        hour: '2-digit',
        minute: '2-digit',
      },
      datetime: {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
      },
    },
  },
  // Number formatting
  numberFormats: {
    de: {
      currency: {
        style: 'currency',
        currency: 'EUR',
      },
      decimal: {
        style: 'decimal',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      },
      percent: {
        style: 'percent',
        minimumFractionDigits: 0,
      },
    },
    en: {
      currency: {
        style: 'currency',
        currency: 'USD',
      },
      decimal: {
        style: 'decimal',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      },
      percent: {
        style: 'percent',
        minimumFractionDigits: 0,
      },
    },
  },
})

// Helper to change locale
export function setLocale(locale) {
  if (availableLocales.some(l => l.code === locale)) {
    i18n.global.locale.value = locale
    localStorage.setItem('locale', locale)
    document.documentElement.lang = locale
  }
}

// Get current locale
export function getLocale() {
  return i18n.global.locale.value
}

export default i18n
