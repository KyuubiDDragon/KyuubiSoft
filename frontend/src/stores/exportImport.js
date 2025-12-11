import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/core/api/axios'

export const useExportImportStore = defineStore('exportImport', () => {
  const stats = ref({})
  const isLoading = ref(false)
  const isExporting = ref(false)
  const isImporting = ref(false)
  const importValidation = ref(null)
  const importResult = ref(null)
  const error = ref(null)

  async function loadStats() {
    isLoading.value = true
    error.value = null
    try {
      const response = await api.get('/api/v1/export/stats')
      stats.value = response.data.data
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to load export stats'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  async function exportData(types, format = 'json') {
    isExporting.value = true
    error.value = null
    try {
      const response = await api.post('/api/v1/export', {
        types,
        format
      }, {
        responseType: 'blob'
      })

      // Create download link
      const blob = new Blob([response.data], {
        type: format === 'json' ? 'application/json' : 'application/zip'
      })
      const url = window.URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url

      // Get filename from content-disposition header or use default
      const contentDisposition = response.headers['content-disposition']
      let filename = `kyuubisoft-export-${new Date().toISOString().split('T')[0]}.${format === 'json' ? 'json' : 'zip'}`
      if (contentDisposition) {
        const filenameMatch = contentDisposition.match(/filename="(.+)"/)
        if (filenameMatch) {
          filename = filenameMatch[1]
        }
      }

      link.download = filename
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
      window.URL.revokeObjectURL(url)

      return true
    } catch (err) {
      error.value = err.response?.data?.error || 'Export failed'
      throw err
    } finally {
      isExporting.value = false
    }
  }

  async function validateImport(data) {
    isLoading.value = true
    error.value = null
    importValidation.value = null
    try {
      const response = await api.post('/api/v1/import/validate', { data })
      importValidation.value = response.data.data
      return importValidation.value
    } catch (err) {
      error.value = err.response?.data?.error || 'Validation failed'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  async function importData(data, options = {}) {
    isImporting.value = true
    error.value = null
    importResult.value = null
    try {
      const response = await api.post('/api/v1/import', {
        data,
        conflict_resolution: options.conflictResolution || 'skip',
        types: options.types
      })
      importResult.value = response.data.data
      return importResult.value
    } catch (err) {
      error.value = err.response?.data?.error || 'Import failed'
      throw err
    } finally {
      isImporting.value = false
    }
  }

  function reset() {
    importValidation.value = null
    importResult.value = null
    error.value = null
  }

  return {
    stats,
    isLoading,
    isExporting,
    isImporting,
    importValidation,
    importResult,
    error,
    loadStats,
    exportData,
    validateImport,
    importData,
    reset
  }
})
