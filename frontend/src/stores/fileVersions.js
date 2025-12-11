import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/core/api/axios'

export const useFileVersionsStore = defineStore('fileVersions', () => {
  const versions = ref([])
  const settings = ref({
    auto_version: true,
    max_versions_per_file: 10,
    keep_days: 90,
  })
  const stats = ref({
    total_versions: 0,
    total_size: 0,
    versioned_files: 0,
  })
  const isLoading = ref(false)
  const error = ref(null)

  async function loadVersions(fileId) {
    isLoading.value = true
    error.value = null
    try {
      const response = await api.get(`/api/v1/storage/${fileId}/versions`)
      versions.value = response.data.data
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to load versions'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  async function getVersion(versionId) {
    try {
      const response = await api.get(`/api/v1/storage/versions/${versionId}`)
      return response.data.data
    } catch (err) {
      throw err
    }
  }

  async function restoreVersion(versionId) {
    try {
      const response = await api.post(`/api/v1/storage/versions/${versionId}/restore`)
      return response.data.data
    } catch (err) {
      throw err
    }
  }

  async function deleteVersion(versionId) {
    try {
      await api.delete(`/api/v1/storage/versions/${versionId}`)
      versions.value = versions.value.filter(v => v.id !== versionId)
    } catch (err) {
      throw err
    }
  }

  async function compareVersions(versionId1, versionId2) {
    try {
      const response = await api.get('/api/v1/storage/versions/compare', {
        params: { version1: versionId1, version2: versionId2 },
      })
      return response.data.data
    } catch (err) {
      throw err
    }
  }

  function getDownloadUrl(versionId) {
    return `/api/v1/storage/versions/${versionId}/download`
  }

  async function loadSettings() {
    try {
      const response = await api.get('/api/v1/storage/versions/settings')
      settings.value = response.data.data
      return response.data.data
    } catch (err) {
      console.error('Failed to load version settings', err)
      return settings.value
    }
  }

  async function updateSettings(data) {
    try {
      const response = await api.put('/api/v1/storage/versions/settings', data)
      settings.value = response.data.data
      return response.data.data
    } catch (err) {
      throw err
    }
  }

  async function loadStats() {
    try {
      const response = await api.get('/api/v1/storage/versions/stats')
      stats.value = response.data.data
      return response.data.data
    } catch (err) {
      console.error('Failed to load version stats', err)
      return stats.value
    }
  }

  async function cleanup() {
    try {
      const response = await api.post('/api/v1/storage/versions/cleanup')
      return response.data.data
    } catch (err) {
      throw err
    }
  }

  function formatSize(bytes) {
    if (bytes >= 1073741824) {
      return (bytes / 1073741824).toFixed(2) + ' GB'
    } else if (bytes >= 1048576) {
      return (bytes / 1048576).toFixed(2) + ' MB'
    } else if (bytes >= 1024) {
      return (bytes / 1024).toFixed(2) + ' KB'
    }
    return bytes + ' B'
  }

  function formatDate(dateStr) {
    if (!dateStr) return '-'
    return new Date(dateStr).toLocaleString('de-DE', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })
  }

  return {
    versions,
    settings,
    stats,
    isLoading,
    error,
    loadVersions,
    getVersion,
    restoreVersion,
    deleteVersion,
    compareVersions,
    getDownloadUrl,
    loadSettings,
    updateSettings,
    loadStats,
    cleanup,
    formatSize,
    formatDate,
  }
})
