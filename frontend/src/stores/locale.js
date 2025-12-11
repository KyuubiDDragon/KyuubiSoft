import { defineStore } from 'pinia'
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '@/core/api/axios'
import { availableLocales, setLocale as setI18nLocale, getLocale } from '@/locales'

export const useLocaleStore = defineStore('locale', () => {
  const { locale } = useI18n({ useScope: 'global' })
  const currentLocale = ref(getLocale())
  const isLoading = ref(false)

  // Watch for locale changes
  watch(currentLocale, (newLocale) => {
    setI18nLocale(newLocale)
    // Optionally sync with backend
    syncLocaleToBackend(newLocale)
  })

  async function setLocale(localeCode) {
    if (!availableLocales.some(l => l.code === localeCode)) {
      console.warn(`Locale ${localeCode} is not available`)
      return
    }
    currentLocale.value = localeCode
    setI18nLocale(localeCode)
  }

  async function syncLocaleToBackend(localeCode) {
    try {
      await api.put('/api/v1/users/me/preferences', {
        locale: localeCode,
      })
    } catch (err) {
      // Silently fail - locale is stored locally anyway
      console.debug('Could not sync locale to backend', err)
    }
  }

  async function loadLocaleFromBackend() {
    try {
      const response = await api.get('/api/v1/users/me/preferences')
      const serverLocale = response.data.data?.locale
      if (serverLocale && availableLocales.some(l => l.code === serverLocale)) {
        currentLocale.value = serverLocale
        setI18nLocale(serverLocale)
      }
    } catch (err) {
      // Use local storage locale
      console.debug('Could not load locale from backend', err)
    }
  }

  function getAvailableLocales() {
    return availableLocales
  }

  function getCurrentLocaleInfo() {
    return availableLocales.find(l => l.code === currentLocale.value) || availableLocales[0]
  }

  return {
    currentLocale,
    isLoading,
    setLocale,
    loadLocaleFromBackend,
    getAvailableLocales,
    getCurrentLocaleInfo,
  }
})
