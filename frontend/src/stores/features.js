import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/core/api/axios'
import { useAuthStore } from '@/stores/auth'

/**
 * Feature Store - Manages feature flags and permissions
 *
 * Two-layer access control:
 * 1. Instance-Level (ENV): What features exist on this instance?
 * 2. User-Level (Permissions): What can this user access within instance limits?
 */
export const useFeatureStore = defineStore('features', () => {
  // State
  const features = ref({})           // Feature modes (from /api/v1/features)
  const details = ref({})            // Feature details with restricted sub-features
  const isLoaded = ref(false)
  const isLoading = ref(false)

  // Sub-feature definitions (mirrors backend FeatureService)
  const subFeatures = {
    docker: {
      view: ['portainer_only', 'own', 'full'],
      hosts_manage: ['own', 'full'],
      containers: ['own', 'full'],
      images: ['own', 'full'],
      volumes: ['own', 'full'],
      networks: ['own', 'full'],
      system_socket: ['full'],
      portainer: ['portainer_only', 'own', 'full'],
    },
    server: {
      view: ['own', 'full'],
      manage: ['own', 'full'],
      terminal: ['own', 'full'],
      localhost: ['full'],
    },
    tools: {
      ping: ['limited', 'full'],
      dns: ['limited', 'full'],
      whois: ['limited', 'full'],
      traceroute: ['limited', 'full'],
      ssl_check: ['limited', 'full'],
      http_headers: ['limited', 'full'],
      ip_lookup: ['limited', 'full'],
      security_headers: ['limited', 'full'],
      open_graph: ['limited', 'full'],
      port_check: ['full'],
      ssh: ['full'],
    },
    uptime: {
      view: ['full'],
      manage: ['full'],
    },
    invoices: {
      view: ['full'],
      create: ['full'],
      edit: ['full'],
      delete: ['full'],
    },
    tickets: {
      view: ['full'],
      create: ['full'],
      manage: ['full'],
    },
    api_tester: {
      view: ['limited', 'full'],
      execute: ['limited', 'full'],
      auth_headers: ['full'],
    },
    youtube: {
      use: ['full'],
    },
    passwords: {
      view: ['full'],
      manage: ['full'],
    },
  }

  // Getters

  /**
   * Check if a feature is enabled on this instance (any mode except disabled)
   */
  const isEnabled = computed(() => (feature) => {
    const mode = features.value[feature]
    return !!mode && mode !== 'disabled'
  })

  /**
   * Get the current mode of a feature
   */
  const getMode = computed(() => (feature) => {
    return features.value[feature] || 'disabled'
  })

  /**
   * Check if a feature has a specific mode
   */
  const hasMode = computed(() => (feature, mode) => {
    return features.value[feature] === mode
  })

  /**
   * Check if feature has full access
   */
  const hasFull = computed(() => (feature) => {
    return features.value[feature] === 'full'
  })

  /**
   * Check if a sub-feature is allowed by the current instance mode
   */
  function isSubFeatureAllowed(feature, subFeature) {
    const mode = features.value[feature]

    if (!mode || mode === 'disabled') {
      return false
    }

    const featureDef = subFeatures[feature]
    if (!featureDef || !featureDef[subFeature]) {
      // Unknown sub-feature, default to requiring full mode
      return mode === 'full'
    }

    return featureDef[subFeature].includes(mode)
  }

  /**
   * Check sub-feature access (Instance-Level only)
   * Use canAccess() for combined Instance + Permission check
   */
  function checkAccess(feature, subFeature) {
    return isSubFeatureAllowed(feature, subFeature)
  }

  /**
   * Combined access check: Instance-Level AND User Permission
   *
   * @param {string} feature - The feature name (e.g., 'docker')
   * @param {string|null} subFeature - Optional sub-feature (e.g., 'system_socket')
   * @returns {boolean}
   */
  function canAccess(feature, subFeature = null) {
    // 1. Instance-Level Check
    if (!isEnabled.value(feature)) {
      return false
    }

    // 2. Sub-Feature Mode Check (if specified)
    if (subFeature !== null && !isSubFeatureAllowed(feature, subFeature)) {
      return false
    }

    // 3. Permission Check
    const authStore = useAuthStore()
    const permission = subFeature
      ? `${feature}.${subFeature}`
      : `${feature}.view`

    return authStore.hasPermission(permission)
  }

  /**
   * Check if user can view a feature (Instance + Permission)
   */
  function canView(feature) {
    return canAccess(feature, 'view')
  }

  /**
   * Check if user can manage a feature (Instance + Permission)
   */
  function canManage(feature) {
    return canAccess(feature, 'manage')
  }

  /**
   * Get restricted sub-features for a feature based on current mode
   */
  function getRestrictedSubFeatures(feature) {
    return details.value[feature]?.restricted || []
  }

  // Actions

  /**
   * Load features from API
   */
  async function loadFeatures() {
    if (isLoading.value) return

    isLoading.value = true
    try {
      const response = await api.get('/api/v1/features')
      const data = response.data.data || {}

      // Extract modes
      features.value = data.features || {}

      // Store details with restricted info
      details.value = data.details || {}

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
        passwords: 'full',
      }
      isLoaded.value = true
    } finally {
      isLoading.value = false
    }
  }

  // Convenience methods for common feature checks

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

  function canAccessPasswords() {
    return isEnabled.value('passwords')
  }

  // Docker-specific checks
  function canUseDockerSystemSocket() {
    return canAccess('docker', 'system_socket')
  }

  function canManageDockerHosts() {
    return canAccess('docker', 'hosts_manage')
  }

  function canUsePortainer() {
    return canAccess('docker', 'portainer')
  }

  // Server-specific checks
  function canAccessLocalhost() {
    return canAccess('server', 'localhost')
  }

  // Tools-specific checks
  function canUseSSHTerminal() {
    return canAccess('tools', 'ssh')
  }

  function canUsePortScanner() {
    return canAccess('tools', 'port_check')
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
    canAccess,
    canView,
    canManage,
    isSubFeatureAllowed,
    getRestrictedSubFeatures,

    // Convenience methods - Feature access
    canAccessDocker,
    canAccessServer,
    canAccessTools,
    canAccessUptime,
    canAccessInvoices,
    canAccessTickets,
    canAccessApiTester,
    canAccessYoutube,
    canAccessPasswords,

    // Convenience methods - Sub-feature access
    canUseDockerSystemSocket,
    canManageDockerHosts,
    canUsePortainer,
    canAccessLocalhost,
    canUseSSHTerminal,
    canUsePortScanner,
  }
})
