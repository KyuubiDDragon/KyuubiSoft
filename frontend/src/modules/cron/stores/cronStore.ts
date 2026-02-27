import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/core/api/axios'
import { useUiStore } from '@/stores/ui'

export interface CronJob {
  id: string
  user_id: string
  connection_id: string | null
  connection_name?: string
  expression: string
  command: string
  description: string | null
  is_active: boolean
  last_run_at: string | null
  next_run_at: string | null
  readable_expression?: string
  created_at: string
}

export interface CronJobHistory {
  id: string
  cron_job_id: string
  started_at: string
  finished_at: string | null
  exit_code: number | null
  stdout: string | null
  stderr: string | null
  duration_ms: number | null
}

export interface ParsedExpression {
  description: string
  next_runs: string[]
}

export const useCronStore = defineStore('cron', () => {
  const uiStore = useUiStore()

  // State
  const jobs = ref<CronJob[]>([])
  const currentJob = ref<CronJob | null>(null)
  const history = ref<CronJobHistory[]>([])
  const loading = ref<boolean>(false)

  // Actions
  async function fetchJobs(): Promise<void> {
    loading.value = true
    try {
      const response = await api.get('/api/v1/cron')
      jobs.value = response.data.data || []
    } catch (error) {
      uiStore.showError('Fehler beim Laden der Cron-Jobs')
    } finally {
      loading.value = false
    }
  }

  async function createJob(data: {
    expression: string
    command: string
    description?: string
    connection_id?: string | null
  }): Promise<CronJob | null> {
    try {
      const response = await api.post('/api/v1/cron', data)
      const job = response.data.data
      jobs.value.push(job)
      uiStore.showSuccess('Cron-Job erstellt')
      return job
    } catch (error: any) {
      const msg = error.response?.data?.message || 'Fehler beim Erstellen des Cron-Jobs'
      uiStore.showError(msg)
      return null
    }
  }

  async function updateJob(
    id: string,
    data: Partial<{
      expression: string
      command: string
      description: string | null
      connection_id: string | null
      is_active: boolean
    }>
  ): Promise<CronJob | null> {
    try {
      const response = await api.put(`/api/v1/cron/${id}`, data)
      const updated = response.data.data
      const idx = jobs.value.findIndex((j) => j.id === id)
      if (idx !== -1) {
        jobs.value[idx] = updated
      }
      uiStore.showSuccess('Cron-Job aktualisiert')
      return updated
    } catch (error: any) {
      const msg = error.response?.data?.message || 'Fehler beim Aktualisieren des Cron-Jobs'
      uiStore.showError(msg)
      return null
    }
  }

  async function deleteJob(id: string): Promise<boolean> {
    try {
      await api.delete(`/api/v1/cron/${id}`)
      jobs.value = jobs.value.filter((j) => j.id !== id)
      uiStore.showSuccess('Cron-Job geloescht')
      return true
    } catch (error) {
      uiStore.showError('Fehler beim Loeschen des Cron-Jobs')
      return false
    }
  }

  async function toggleJob(id: string): Promise<CronJob | null> {
    try {
      const response = await api.post(`/api/v1/cron/${id}/toggle`)
      const updated = response.data.data
      const idx = jobs.value.findIndex((j) => j.id === id)
      if (idx !== -1) {
        jobs.value[idx] = updated
      }
      return updated
    } catch (error) {
      uiStore.showError('Fehler beim Umschalten des Cron-Jobs')
      return null
    }
  }

  async function fetchHistory(jobId: string): Promise<void> {
    try {
      const response = await api.get(`/api/v1/cron/${jobId}/history`)
      history.value = response.data.data?.items || response.data.data || []
    } catch (error) {
      uiStore.showError('Fehler beim Laden der Ausfuehrungshistorie')
      history.value = []
    }
  }

  async function parseExpression(expression: string): Promise<ParsedExpression | null> {
    try {
      const response = await api.post('/api/v1/cron/parse', { expression })
      return response.data.data || null
    } catch (error) {
      return null
    }
  }

  return {
    // State
    jobs,
    currentJob,
    history,
    loading,

    // Actions
    fetchJobs,
    createJob,
    updateJob,
    deleteJob,
    toggleJob,
    fetchHistory,
    parseExpression,
  }
})
