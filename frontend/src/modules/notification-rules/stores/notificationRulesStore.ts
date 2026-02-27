import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/core/api/axios'
import { useUiStore } from '@/stores/ui'

export interface RuleCondition {
  field: string
  operator: string
  value: string
}

export interface RuleActionConfig {
  title?: string
  message?: string
  url?: string
}

export interface RuleAction {
  type: 'push' | 'webhook'
  config: RuleActionConfig
}

export interface NotificationRule {
  id: string
  name: string
  trigger_event: string
  conditions: RuleCondition[]
  actions: RuleAction[]
  is_active: boolean
  trigger_count: number
  last_triggered_at: string | null
  created_at: string
  updated_at: string
  [key: string]: unknown
}

export interface TriggerEvent {
  key: string
  label: string
  module: string
}

export interface RuleHistoryEntry {
  id: string
  rule_id: string
  trigger_event: string
  triggered_at: string
  status: string
  details?: Record<string, unknown>
  [key: string]: unknown
}

export interface RulePayload {
  name: string
  trigger_event: string
  conditions: RuleCondition[]
  actions: RuleAction[]
}

export const useNotificationRulesStore = defineStore('notification-rules', () => {
  const uiStore = useUiStore()

  // State
  const rules = ref<NotificationRule[]>([])
  const availableEvents = ref<TriggerEvent[]>([])
  const currentRule = ref<NotificationRule | null>(null)
  const ruleHistory = ref<RuleHistoryEntry[]>([])
  const loading = ref<boolean>(false)

  // Actions
  async function fetchRules(): Promise<void> {
    loading.value = true
    try {
      const response = await api.get('/api/v1/notification-rules')
      rules.value = response.data.data?.items || response.data.data || []
    } catch (error) {
      uiStore.showError('Fehler beim Laden der Benachrichtigungsregeln')
    } finally {
      loading.value = false
    }
  }

  async function fetchAvailableEvents(): Promise<void> {
    try {
      const response = await api.get('/api/v1/notification-rules/events')
      availableEvents.value = response.data.data || []
    } catch (error) {
      console.error('Failed to load available events:', error)
    }
  }

  async function createRule(payload: RulePayload): Promise<boolean> {
    try {
      const response = await api.post('/api/v1/notification-rules', payload)
      const newRule = response.data.data
      if (newRule) {
        rules.value.unshift(newRule)
      }
      uiStore.showSuccess('Regel erfolgreich erstellt')
      return true
    } catch (error) {
      uiStore.showError('Fehler beim Erstellen der Regel')
      return false
    }
  }

  async function updateRule(id: string, payload: RulePayload): Promise<boolean> {
    try {
      const response = await api.put(`/api/v1/notification-rules/${id}`, payload)
      const updated = response.data.data
      if (updated) {
        const idx = rules.value.findIndex(r => r.id === id)
        if (idx !== -1) {
          rules.value[idx] = updated
        }
      }
      uiStore.showSuccess('Regel erfolgreich aktualisiert')
      return true
    } catch (error) {
      uiStore.showError('Fehler beim Aktualisieren der Regel')
      return false
    }
  }

  async function deleteRule(id: string): Promise<boolean> {
    try {
      await api.delete(`/api/v1/notification-rules/${id}`)
      rules.value = rules.value.filter(r => r.id !== id)
      uiStore.showSuccess('Regel erfolgreich gelöscht')
      return true
    } catch (error) {
      uiStore.showError('Fehler beim Löschen der Regel')
      return false
    }
  }

  async function toggleRule(id: string): Promise<boolean> {
    try {
      const response = await api.post(`/api/v1/notification-rules/${id}/toggle`)
      const updated = response.data.data
      if (updated) {
        const idx = rules.value.findIndex(r => r.id === id)
        if (idx !== -1) {
          rules.value[idx] = updated
        }
      }
      const rule = rules.value.find(r => r.id === id)
      const status = rule?.is_active ? 'aktiviert' : 'deaktiviert'
      uiStore.showSuccess(`Regel ${status}`)
      return true
    } catch (error) {
      uiStore.showError('Fehler beim Umschalten der Regel')
      return false
    }
  }

  async function testRule(id: string): Promise<boolean> {
    try {
      await api.post(`/api/v1/notification-rules/${id}/test`)
      uiStore.showSuccess('Testregel wurde ausgelöst')
      return true
    } catch (error) {
      uiStore.showError('Fehler beim Testen der Regel')
      return false
    }
  }

  async function fetchHistory(id: string): Promise<void> {
    try {
      const response = await api.get(`/api/v1/notification-rules/${id}/history`)
      ruleHistory.value = response.data.data?.items || response.data.data || []
    } catch (error) {
      uiStore.showError('Fehler beim Laden der Ausführungshistorie')
      ruleHistory.value = []
    }
  }

  return {
    // State
    rules,
    availableEvents,
    currentRule,
    ruleHistory,
    loading,

    // Actions
    fetchRules,
    fetchAvailableEvents,
    createRule,
    updateRule,
    deleteRule,
    toggleRule,
    testRule,
    fetchHistory,
  }
})
