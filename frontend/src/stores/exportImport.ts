import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/core/api/axios'

interface ExportStats {
  [key: string]: unknown
}

interface ImportValidation {
  [key: string]: unknown
}

interface ImportResult {
  [key: string]: unknown
}

interface ImportOptions {
  conflictResolution?: string
  types?: string[]
  [key: string]: unknown
}

export const useExportImportStore = defineStore('exportImport', () => {
  const stats = ref<ExportStats>({})
  const isLoading = ref<boolean>(false)
  const isExporting = ref<boolean>(false)
  const isImporting = ref<boolean>(false)
  const importValidation = ref<ImportValidation | null>(null)
  const importResult = ref<ImportResult | null>(null)
  const error = ref<string | null>(null)

  async function loadStats(): Promise<void> {
    isLoading.value = true
    error.value = null
    try {
      const response = await api.get('/api/v1/export/stats')
      stats.value = response.data.data
    } catch (err: unknown) {
      error.value = (err as { response?: { data?: { error?: string } } }).response?.data?.error || 'Failed to load export stats'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  async function exportData(types: string[], format: string = 'json'): Promise<boolean> {
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
    } catch (err: unknown) {
      error.value = (err as { response?: { data?: { error?: string } } }).response?.data?.error || 'Export failed'
      throw err
    } finally {
      isExporting.value = false
    }
  }

  async function validateImport(data: unknown): Promise<ImportValidation> {
    isLoading.value = true
    error.value = null
    importValidation.value = null
    try {
      const response = await api.post('/api/v1/import/validate', { data })
      importValidation.value = response.data.data
      return importValidation.value as ImportValidation
    } catch (err: unknown) {
      error.value = (err as { response?: { data?: { error?: string } } }).response?.data?.error || 'Validation failed'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  async function importData(data: unknown, options: ImportOptions = {}): Promise<ImportResult> {
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
      return importResult.value as ImportResult
    } catch (err: unknown) {
      error.value = (err as { response?: { data?: { error?: string } } }).response?.data?.error || 'Import failed'
      throw err
    } finally {
      isImporting.value = false
    }
  }

  function reset(): void {
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
