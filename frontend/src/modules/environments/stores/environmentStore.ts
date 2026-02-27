import { ref } from 'vue'
import { defineStore } from 'pinia'
import api from '@/core/api/axios'
import { useUiStore } from '@/stores/ui'

export interface Environment {
  id: string
  user_id: string
  project_id: string | null
  project_name?: string | null
  name: string
  slug: string
  description: string | null
  variable_count: number
  created_at: string
  updated_at: string
  variables?: EnvironmentVariable[]
}

export interface EnvironmentVariable {
  id: string
  environment_id: string
  var_key: string
  var_value: string
  is_secret: boolean
  sort_order: number
  created_at: string
  updated_at: string
}

export interface EnvironmentHistoryEntry {
  id: string
  environment_id: string
  user_id: string
  user_name?: string
  action: string
  changes: any
  created_at: string
}

export const useEnvironmentStore = defineStore('environments', () => {
  const uiStore = useUiStore()

  // State
  const environments = ref<Environment[]>([])
  const currentEnvironment = ref<Environment | null>(null)
  const variables = ref<EnvironmentVariable[]>([])
  const history = ref<EnvironmentHistoryEntry[]>([])
  const loading = ref<boolean>(false)

  // Actions
  async function fetchEnvironments(): Promise<void> {
    loading.value = true
    try {
      const response = await api.get('/api/v1/environments')
      environments.value = response.data.data || []
    } catch (error) {
      uiStore.showError('Fehler beim Laden der Umgebungen')
    } finally {
      loading.value = false
    }
  }

  async function createEnvironment(data: {
    name: string
    slug?: string
    description?: string
    project_id?: string | null
  }): Promise<Environment | null> {
    try {
      const response = await api.post('/api/v1/environments', data)
      const env = response.data.data
      environments.value.push(env)
      uiStore.showSuccess('Umgebung erstellt')
      return env
    } catch (error: any) {
      const msg = error.response?.data?.message || 'Fehler beim Erstellen der Umgebung'
      uiStore.showError(msg)
      return null
    }
  }

  async function updateEnvironment(
    id: string,
    data: Partial<{
      name: string
      slug: string
      description: string | null
      project_id: string | null
    }>
  ): Promise<Environment | null> {
    try {
      const response = await api.put(`/api/v1/environments/${id}`, data)
      const updated = response.data.data
      const idx = environments.value.findIndex((e) => e.id === id)
      if (idx !== -1) {
        environments.value[idx] = updated
      }
      if (currentEnvironment.value?.id === id) {
        currentEnvironment.value = { ...currentEnvironment.value, ...updated }
      }
      uiStore.showSuccess('Umgebung aktualisiert')
      return updated
    } catch (error: any) {
      const msg = error.response?.data?.message || 'Fehler beim Aktualisieren der Umgebung'
      uiStore.showError(msg)
      return null
    }
  }

  async function deleteEnvironment(id: string): Promise<boolean> {
    try {
      await api.delete(`/api/v1/environments/${id}`)
      environments.value = environments.value.filter((e) => e.id !== id)
      if (currentEnvironment.value?.id === id) {
        currentEnvironment.value = null
        variables.value = []
      }
      uiStore.showSuccess('Umgebung geloescht')
      return true
    } catch (error) {
      uiStore.showError('Fehler beim Loeschen der Umgebung')
      return false
    }
  }

  async function fetchVariables(environmentId: string): Promise<void> {
    try {
      const response = await api.get(`/api/v1/environments/${environmentId}/variables`)
      variables.value = response.data.data || []
    } catch (error) {
      uiStore.showError('Fehler beim Laden der Variablen')
      variables.value = []
    }
  }

  async function setVariables(
    environmentId: string,
    vars: Array<{ key: string; value: string; is_secret: boolean }>
  ): Promise<EnvironmentVariable[] | null> {
    try {
      const response = await api.put(`/api/v1/environments/${environmentId}/variables`, {
        variables: vars,
      })
      variables.value = response.data.data || []
      uiStore.showSuccess('Variablen gespeichert')
      // Update variable count in the environments list
      const idx = environments.value.findIndex((e) => e.id === environmentId)
      if (idx !== -1) {
        environments.value[idx].variable_count = variables.value.length
      }
      return variables.value
    } catch (error: any) {
      const msg = error.response?.data?.message || 'Fehler beim Speichern der Variablen'
      uiStore.showError(msg)
      return null
    }
  }

  async function deleteVariable(environmentId: string, varId: string): Promise<boolean> {
    try {
      await api.delete(`/api/v1/environments/${environmentId}/variables/${varId}`)
      variables.value = variables.value.filter((v) => v.id !== varId)
      // Update variable count
      const idx = environments.value.findIndex((e) => e.id === environmentId)
      if (idx !== -1) {
        environments.value[idx].variable_count = variables.value.length
      }
      uiStore.showSuccess('Variable geloescht')
      return true
    } catch (error) {
      uiStore.showError('Fehler beim Loeschen der Variable')
      return false
    }
  }

  async function fetchHistory(environmentId: string): Promise<void> {
    try {
      const response = await api.get(`/api/v1/environments/${environmentId}/history`)
      history.value = response.data.data || []
    } catch (error) {
      uiStore.showError('Fehler beim Laden der Historie')
      history.value = []
    }
  }

  async function duplicateEnvironment(
    id: string,
    data?: { name?: string; slug?: string }
  ): Promise<Environment | null> {
    try {
      const response = await api.post(`/api/v1/environments/${id}/duplicate`, data || {})
      const env = response.data.data
      environments.value.push(env)
      uiStore.showSuccess('Umgebung dupliziert')
      return env
    } catch (error: any) {
      const msg = error.response?.data?.message || 'Fehler beim Duplizieren der Umgebung'
      uiStore.showError(msg)
      return null
    }
  }

  async function exportEnv(
    environmentId: string
  ): Promise<{ content: string; filename: string } | null> {
    try {
      const response = await api.get(`/api/v1/environments/${environmentId}/export`)
      return response.data.data || null
    } catch (error) {
      uiStore.showError('Fehler beim Exportieren der Umgebung')
      return null
    }
  }

  async function importEnv(
    environmentId: string,
    content: string,
    overwrite: boolean = false
  ): Promise<boolean> {
    try {
      const response = await api.post(`/api/v1/environments/${environmentId}/import`, {
        content,
        overwrite,
      })
      const data = response.data.data
      if (data?.variables) {
        variables.value = data.variables
      }
      // Update variable count
      const idx = environments.value.findIndex((e) => e.id === environmentId)
      if (idx !== -1) {
        environments.value[idx].variable_count = variables.value.length
      }
      uiStore.showSuccess(`${data?.imported || 0} Variablen importiert`)
      return true
    } catch (error: any) {
      const msg = error.response?.data?.message || 'Fehler beim Importieren'
      uiStore.showError(msg)
      return false
    }
  }

  return {
    // State
    environments,
    currentEnvironment,
    variables,
    history,
    loading,

    // Actions
    fetchEnvironments,
    createEnvironment,
    updateEnvironment,
    deleteEnvironment,
    fetchVariables,
    setVariables,
    deleteVariable,
    fetchHistory,
    duplicateEnvironment,
    exportEnv,
    importEnv,
  }
})
