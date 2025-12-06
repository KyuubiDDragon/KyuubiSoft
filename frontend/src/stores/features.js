import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/core/api/axios'

export const useFeatureStore = defineStore('features', () => {
  // State
  const features = ref({})
  const details = ref({})
  const isLoaded = ref(false)
  const isLoading = ref(false)

  // Getters
  const isEnabled = computed(() => (feature) => {
    return !!features.value[feature]
  })

  const getMode = computed(() => (feature) => {
    return features.value[feature] || 'disabled'
  })

  const hasMode = computed(() => (feature, mode) => {
    return features.value[feature] === mode
  })

  const hasFull = computed(() => (feature) => {
    return features.value[feature] === 'full'
  })

  // Check sub-feature access (mirrors backend logic)
  function checkAccess(feature, subFeature) {
    const mode = features.value[feature]

    if (!mode || mode === 'disabled') {
      return false
    }

    if (mode === 'full') {
      return true
    }

    // Feature-specific sub-feature checks
    if (feature === 'docker') {
      switch (subFeature) {
        case 'system_socket': return mode === 'full'
        case 'own_hosts': return ['own', 'full'].includes(mode)
        case 'view': return ['own', 'full'].includes(mode)
        default: return false
      }
    }

    if (feature === 'tools') {
      switch (subFeature) {
        case 'ssh': return mode === 'full'
        case 'ping': return ['limited', 'full'].includes(mode)
        case 'dns': return ['limited', 'full'].includes(mode)
        case 'whois': return ['limited', 'full'].includes(mode)
        case 'port_check': return mode === 'full'
        default: return false
      }
    }

    return mode === 'full'
  }

  // Actions
  async function loadFeatures() {
    if (isLoading.value) return

    isLoading.value = true
    try {
      const response = await api.get('/api/v1/features')
      features.value = response.data.data?.features || {}
      details.value = response.data.data?.details || {}
      isLoaded.value = true
    } catch (error) {
      console.error('Failed to load features:', error)
      // Default to all features enabled if API fails
      features.value = {
        docker: 'full',
        server: 'full',
        tools: 'full',
        uptime: 'full',
        invoices: 'full',
        tickets: 'full',
        api_tester: 'full',
        youtube: 'full',
      }
      isLoaded.value = true
    } finally {
      isLoading.value = false
    }
  }

  // Convenience methods for common checks
  function canAccessDocker() {
    return isEnabled.value('docker')
  }

  function canAccessServer() {
    return isEnabled.value('server')
  }

  function canAccessTools() {
    return isEnabled.value('tools')
  }

  function canAccessUptime() {
    return isEnabled.value('uptime')
  }

  function canAccessInvoices() {
    return isEnabled.value('invoices')
  }

  function canAccessTickets() {
    return isEnabled.value('tickets')
  }

  function canAccessApiTester() {
    return isEnabled.value('api_tester')
  }

  function canAccessYoutube() {
    return isEnabled.value('youtube')
  }

  return {
    // State
    features,
    details,
    isLoaded,
    isLoading,

    // Getters
    isEnabled,
    getMode,
    hasMode,
    hasFull,

    // Actions
    loadFeatures,
    checkAccess,

    // Convenience methods
    canAccessDocker,
    canAccessServer,
    canAccessTools,
    canAccessUptime,
    canAccessInvoices,
    canAccessTickets,
    canAccessApiTester,
    canAccessYoutube,
  }
})
