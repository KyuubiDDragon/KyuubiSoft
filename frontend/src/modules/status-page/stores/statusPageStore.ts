import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/core/api/axios'

export interface StatusPageConfig {
  id: string
  user_id: string
  title: string
  description: string | null
  is_public: boolean
  created_at: string
  updated_at: string
}

export interface StatusPageMonitor {
  id: string
  config_id: string
  monitor_id: string
  display_name: string | null
  display_order: number
  group_name: string | null
  monitor_name: string
  monitor_type: string
  current_status: string
}

export interface IncidentUpdate {
  id: string
  incident_id: string
  status: string
  message: string
  created_at: string
}

export interface Incident {
  id: string
  config_id: string
  title: string
  status: string
  message: string | null
  impact: string
  started_at: string
  resolved_at: string | null
  created_at: string
  updated_at: string
  updates: IncidentUpdate[]
}

export interface PublicMonitor {
  id: string
  monitor_id: string
  display_name: string
  display_order: number
  group_name: string | null
  name: string
  type: string
  current_status: string
  uptime_percentage: number
  last_check_at: string
  daily_history: { date: string; status: string; uptime: number | null }[]
}

export interface PublicData {
  title: string
  description: string | null
  monitors: PublicMonitor[]
  active_incidents: Incident[]
  resolved_incidents: Incident[]
}

export const useStatusPageStore = defineStore('statusPage', () => {
  const config = ref<StatusPageConfig | null>(null)
  const monitors = ref<{ assigned: StatusPageMonitor[]; available: any[] }>({ assigned: [], available: [] })
  const incidents = ref<{ items: Incident[]; total: number; page: number; limit: number }>({ items: [], total: 0, page: 1, limit: 20 })
  const publicData = ref<PublicData | null>(null)
  const loading = ref(false)

  async function fetchConfig() {
    loading.value = true
    try {
      const response = await api.get('/api/v1/status-page/config')
      config.value = response.data.data
    } finally {
      loading.value = false
    }
  }

  async function updateConfig(data: Partial<StatusPageConfig>) {
    const response = await api.put('/api/v1/status-page/config', data)
    config.value = response.data.data
    return response.data.data
  }

  async function fetchMonitors() {
    const response = await api.get('/api/v1/status-page/monitors')
    monitors.value = response.data.data
  }

  async function addMonitor(data: { monitor_id: string; display_name?: string; group_name?: string }) {
    const response = await api.post('/api/v1/status-page/monitors', data)
    await fetchMonitors()
    return response.data.data
  }

  async function removeMonitor(id: string) {
    await api.delete(`/api/v1/status-page/monitors/${id}`)
    await fetchMonitors()
  }

  async function fetchIncidents(page = 1) {
    const response = await api.get('/api/v1/status-page/incidents', { params: { page, limit: 20 } })
    incidents.value = response.data.data
  }

  async function createIncident(data: { title: string; status: string; message?: string; impact: string }) {
    const response = await api.post('/api/v1/status-page/incidents', data)
    await fetchIncidents()
    return response.data.data
  }

  async function updateIncident(id: string, data: Partial<Incident>) {
    const response = await api.put(`/api/v1/status-page/incidents/${id}`, data)
    await fetchIncidents()
    return response.data.data
  }

  async function addIncidentUpdate(incidentId: string, data: { status: string; message: string }) {
    const response = await api.post(`/api/v1/status-page/incidents/${incidentId}/updates`, data)
    await fetchIncidents()
    return response.data.data
  }

  async function fetchPublicData() {
    loading.value = true
    try {
      const response = await api.get('/api/v1/status-page/public')
      publicData.value = response.data.data
    } finally {
      loading.value = false
    }
  }

  return {
    config,
    monitors,
    incidents,
    publicData,
    loading,
    fetchConfig,
    updateConfig,
    fetchMonitors,
    addMonitor,
    removeMonitor,
    fetchIncidents,
    createIncident,
    updateIncident,
    addIncidentUpdate,
    fetchPublicData,
  }
})
