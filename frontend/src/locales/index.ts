import { createI18n, type I18nOptions } from 'vue-i18n'
import de from './de.json'
import en from './en.json'

/** Represents an available locale option */
export interface LocaleOption {
  code: string
  name: string
  flag: string
}

// Available languages
export const availableLocales: LocaleOption[] = [
  { code: 'de', name: 'Deutsch', flag: '\u{1F1E9}\u{1F1EA}' },
  { code: 'en', name: 'English', flag: '\u{1F1EC}\u{1F1E7}' },
]

// Get stored locale or browser language
function getDefaultLocale(): string {
  // Check localStorage first
  const stored: string | null = localStorage.getItem('locale')
  if (stored && availableLocales.some((l: LocaleOption) => l.code === stored)) {
    return stored
  }

  // Check browser language
  const browserLang: string | undefined = navigator.language?.split('-')[0]
  if (browserLang && availableLocales.some((l: LocaleOption) => l.code === browserLang)) {
    return browserLang
  }

  // Default to German
  return 'de'
}

// i18n configuration
const i18nOptions: I18nOptions = {
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
}

// Create i18n instance
const i18n = createI18n(i18nOptions)

// Helper to change locale
export function setLocale(locale: string): void {
  if (availableLocales.some((l: LocaleOption) => l.code === locale)) {
    ;(i18n.global.locale as unknown as { value: string }).value = locale
    localStorage.setItem('locale', locale)
    document.documentElement.lang = locale
  }
}

// Get current locale
export function getLocale(): string {
  return (i18n.global.locale as unknown as { value: string }).value
}

export default i18n
