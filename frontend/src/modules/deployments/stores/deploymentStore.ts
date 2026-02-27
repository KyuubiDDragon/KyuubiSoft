import { ref, computed } from 'vue'
import { defineStore } from 'pinia'
import api from '@/core/api/axios'
import { useUiStore } from '@/stores/ui'

export interface PipelineStep {
  name: string
  command: string
  timeout: number
}

export interface Pipeline {
  id: string
  user_id: string
  connection_id: string | null
  name: string
  description: string | null
  repository: string | null
  branch: string
  steps: PipelineStep[]
  environment: string
  auto_deploy: boolean
  notify_on_success: boolean
  notify_on_failure: boolean
  last_deployed_at: string | null
  total_deployments?: number
  last_deployment_status?: string | null
  last_deployment_at?: string | null
  recent_deployments?: Deployment[]
  created_at: string
  updated_at: string
}

export interface DeploymentStepLog {
  name: string
  command: string
  status: string
  duration_ms: number
  output: string
  started_at: string
  finished_at: string
}

export interface Deployment {
  id: string
  pipeline_id: string
  user_id: string
  status: 'pending' | 'running' | 'success' | 'failed' | 'cancelled' | 'rolled_back'
  commit_hash: string | null
  commit_message: string | null
  steps_log: DeploymentStepLog[]
  started_at: string | null
  finished_at: string | null
  duration_ms: number | null
  error_message: string | null
  rollback_of: string | null
  pipeline_name?: string
  repository?: string
  branch?: string
  environment?: string
  created_at: string
}

export interface DeploymentStats {
  total: number
  successful: number
  success_rate: number
  avg_duration_ms: number
  recent_failures: Array<{
    id: string
    status: string
    error_message: string | null
    created_at: string
    pipeline_name: string
  }>
}

export const useDeploymentStore = defineStore('deployments', () => {
  const uiStore = useUiStore()

  // State
  const pipelines = ref<Pipeline[]>([])
  const currentPipeline = ref<Pipeline | null>(null)
  const deployments = ref<Deployment[]>([])
  const currentDeployment = ref<Deployment | null>(null)
  const stats = ref<DeploymentStats | null>(null)
  const loading = ref<boolean>(false)
  const deploying = ref<boolean>(false)

  // Actions
  async function fetchPipelines(): Promise<void> {
    loading.value = true
    try {
      const response = await api.get('/api/v1/deployments/pipelines')
      pipelines.value = response.data.data || []
    } catch (error) {
      uiStore.showError('Fehler beim Laden der Pipelines')
    } finally {
      loading.value = false
    }
  }

  async function createPipeline(data: {
    name: string
    description?: string
    repository?: string
    branch?: string
    steps: PipelineStep[]
    environment?: string
    connection_id?: string | null
    auto_deploy?: boolean
    notify_on_success?: boolean
    notify_on_failure?: boolean
  }): Promise<Pipeline | null> {
    try {
      const response = await api.post('/api/v1/deployments/pipelines', data)
      const pipeline = response.data.data
      pipelines.value.unshift(pipeline)
      uiStore.showSuccess('Pipeline erstellt')
      return pipeline
    } catch (error: any) {
      const msg = error.response?.data?.message || 'Fehler beim Erstellen der Pipeline'
      uiStore.showError(msg)
      return null
    }
  }

  async function fetchPipeline(id: string): Promise<Pipeline | null> {
    try {
      const response = await api.get(`/api/v1/deployments/pipelines/${id}`)
      currentPipeline.value = response.data.data
      return response.data.data
    } catch (error) {
      uiStore.showError('Fehler beim Laden der Pipeline')
      return null
    }
  }

  async function updatePipeline(
    id: string,
    data: Partial<{
      name: string
      description: string | null
      repository: string | null
      branch: string
      steps: PipelineStep[]
      environment: string
      connection_id: string | null
      auto_deploy: boolean
      notify_on_success: boolean
      notify_on_failure: boolean
    }>
  ): Promise<Pipeline | null> {
    try {
      const response = await api.put(`/api/v1/deployments/pipelines/${id}`, data)
      const updated = response.data.data
      const idx = pipelines.value.findIndex((p) => p.id === id)
      if (idx !== -1) {
        pipelines.value[idx] = { ...pipelines.value[idx], ...updated }
      }
      uiStore.showSuccess('Pipeline aktualisiert')
      return updated
    } catch (error: any) {
      const msg = error.response?.data?.message || 'Fehler beim Aktualisieren der Pipeline'
      uiStore.showError(msg)
      return null
    }
  }

  async function deletePipeline(id: string): Promise<boolean> {
    try {
      await api.delete(`/api/v1/deployments/pipelines/${id}`)
      pipelines.value = pipelines.value.filter((p) => p.id !== id)
      uiStore.showSuccess('Pipeline geloescht')
      return true
    } catch (error) {
      uiStore.showError('Fehler beim Loeschen der Pipeline')
      return false
    }
  }

  async function deploy(pipelineId: string, data?: {
    commit_hash?: string
    commit_message?: string
  }): Promise<Deployment | null> {
    deploying.value = true
    try {
      const response = await api.post(`/api/v1/deployments/pipelines/${pipelineId}/deploy`, data || {})
      const deployment = response.data.data
      // Update pipeline last deployment info locally
      const idx = pipelines.value.findIndex((p) => p.id === pipelineId)
      if (idx !== -1) {
        pipelines.value[idx].last_deployment_status = deployment.status
        pipelines.value[idx].last_deployment_at = deployment.created_at
        pipelines.value[idx].last_deployed_at = deployment.finished_at || deployment.created_at
        pipelines.value[idx].total_deployments = (pipelines.value[idx].total_deployments || 0) + 1
      }
      uiStore.showSuccess('Deployment gestartet')
      return deployment
    } catch (error: any) {
      const msg = error.response?.data?.message || 'Fehler beim Starten des Deployments'
      uiStore.showError(msg)
      return null
    } finally {
      deploying.value = false
    }
  }

  async function fetchDeployments(pipelineId: string, page: number = 1): Promise<void> {
    try {
      const response = await api.get(`/api/v1/deployments/pipelines/${pipelineId}/deployments`, {
        params: { page, per_page: 25 },
      })
      deployments.value = response.data.data?.items || response.data.data || []
    } catch (error) {
      uiStore.showError('Fehler beim Laden der Deployments')
      deployments.value = []
    }
  }

  async function fetchDeployment(id: string): Promise<Deployment | null> {
    try {
      const response = await api.get(`/api/v1/deployments/${id}`)
      currentDeployment.value = response.data.data
      return response.data.data
    } catch (error) {
      uiStore.showError('Fehler beim Laden des Deployments')
      return null
    }
  }

  async function cancelDeployment(id: string): Promise<boolean> {
    try {
      const response = await api.post(`/api/v1/deployments/${id}/cancel`)
      const updated = response.data.data
      const idx = deployments.value.findIndex((d) => d.id === id)
      if (idx !== -1) {
        deployments.value[idx] = updated
      }
      uiStore.showSuccess('Deployment abgebrochen')
      return true
    } catch (error: any) {
      const msg = error.response?.data?.message || 'Fehler beim Abbrechen des Deployments'
      uiStore.showError(msg)
      return false
    }
  }

  async function rollback(id: string): Promise<Deployment | null> {
    deploying.value = true
    try {
      const response = await api.post(`/api/v1/deployments/${id}/rollback`)
      const deployment = response.data.data
      uiStore.showSuccess('Rollback erfolgreich')
      return deployment
    } catch (error: any) {
      const msg = error.response?.data?.message || 'Fehler beim Rollback'
      uiStore.showError(msg)
      return null
    } finally {
      deploying.value = false
    }
  }

  async function fetchStats(): Promise<void> {
    try {
      const response = await api.get('/api/v1/deployments/stats')
      stats.value = response.data.data
    } catch (error) {
      uiStore.showError('Fehler beim Laden der Statistiken')
    }
  }

  return {
    // State
    pipelines,
    currentPipeline,
    deployments,
    currentDeployment,
    stats,
    loading,
    deploying,

    // Actions
    fetchPipelines,
    createPipeline,
    fetchPipeline,
    updatePipeline,
    deletePipeline,
    deploy,
    fetchDeployments,
    fetchDeployment,
    cancelDeployment,
    rollback,
    fetchStats,
  }
})
