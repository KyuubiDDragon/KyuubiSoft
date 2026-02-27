import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/core/api/axios'

interface FileVersion {
  id: string
  file_id: string
  version_number?: number
  size?: number
  created_at?: string
  created_by?: string
  comment?: string
  [key: string]: unknown
}

interface VersionSettings {
  auto_version: boolean
  max_versions_per_file: number
  keep_days: number
  [key: string]: unknown
}

interface VersionStats {
  total_versions: number
  total_size: number
  versioned_files: number
  [key: string]: unknown
}

interface VersionComparison {
  version1: FileVersion
  version2: FileVersion
  differences?: unknown
  [key: string]: unknown
}

interface CleanupResult {
  deleted_count?: number
  freed_size?: number
  [key: string]: unknown
}

interface UpdateSettingsData {
  auto_version?: boolean
  max_versions_per_file?: number
  keep_days?: number
  [key: string]: unknown
}

export const useFileVersionsStore = defineStore('fileVersions', () => {
  const versions = ref<FileVersion[]>([])
  const settings = ref<VersionSettings>({
    auto_version: true,
    max_versions_per_file: 10,
    keep_days: 90,
  })
  const stats = ref<VersionStats>({
    total_versions: 0,
    total_size: 0,
    versioned_files: 0,
  })
  const isLoading = ref<boolean>(false)
  const error = ref<string | null>(null)

  async function loadVersions(fileId: string): Promise<FileVersion[]> {
    isLoading.value = true
    error.value = null
    try {
      const response = await api.get(`/api/v1/storage/${fileId}/versions`)
      versions.value = response.data.data
      return response.data.data
    } catch (err: unknown) {
      error.value = (err as { response?: { data?: { error?: string } } }).response?.data?.error || 'Failed to load versions'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  async function getVersion(versionId: string): Promise<FileVersion> {
    try {
      const response = await api.get(`/api/v1/storage/versions/${versionId}`)
      return response.data.data
    } catch (err: unknown) {
      throw err
    }
  }

  async function restoreVersion(versionId: string): Promise<FileVersion> {
    try {
      const response = await api.post(`/api/v1/storage/versions/${versionId}/restore`)
      return response.data.data
    } catch (err: unknown) {
      throw err
    }
  }

  async function deleteVersion(versionId: string): Promise<void> {
    try {
      await api.delete(`/api/v1/storage/versions/${versionId}`)
      versions.value = versions.value.filter(v => v.id !== versionId)
    } catch (err: unknown) {
      throw err
    }
  }

  async function compareVersions(versionId1: string, versionId2: string): Promise<VersionComparison> {
    try {
      const response = await api.get('/api/v1/storage/versions/compare', {
        params: { version1: versionId1, version2: versionId2 },
      })
      return response.data.data
    } catch (err: unknown) {
      throw err
    }
  }

  function getDownloadUrl(versionId: string): string {
    return `/api/v1/storage/versions/${versionId}/download`
  }

  async function loadSettings(): Promise<VersionSettings> {
    try {
      const response = await api.get('/api/v1/storage/versions/settings')
      settings.value = response.data.data
      return response.data.data
    } catch (err: unknown) {
      console.error('Failed to load version settings', err)
      return settings.value
    }
  }

  async function updateSettings(data: UpdateSettingsData): Promise<VersionSettings> {
    try {
      const response = await api.put('/api/v1/storage/versions/settings', data)
      settings.value = response.data.data
      return response.data.data
    } catch (err: unknown) {
      throw err
    }
  }

  async function loadStats(): Promise<VersionStats> {
    try {
      const response = await api.get('/api/v1/storage/versions/stats')
      stats.value = response.data.data
      return response.data.data
    } catch (err: unknown) {
      console.error('Failed to load version stats', err)
      return stats.value
    }
  }

  async function cleanup(): Promise<CleanupResult> {
    try {
      const response = await api.post('/api/v1/storage/versions/cleanup')
      return response.data.data
    } catch (err: unknown) {
      throw err
    }
  }

  function formatSize(bytes: number): string {
    if (bytes >= 1073741824) {
      return (bytes / 1073741824).toFixed(2) + ' GB'
    } else if (bytes >= 1048576) {
      return (bytes / 1048576).toFixed(2) + ' MB'
    } else if (bytes >= 1024) {
      return (bytes / 1024).toFixed(2) + ' KB'
    }
    return bytes + ' B'
  }

  function formatDate(dateStr: string | null | undefined): string {
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
