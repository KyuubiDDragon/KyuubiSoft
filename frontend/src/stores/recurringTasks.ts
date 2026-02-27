import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/core/api/axios'

interface RecurringTask {
  id: string
  is_active: boolean
  frequency: string
  interval_value?: number
  [key: string]: unknown
}

interface UpcomingTask {
  id: string
  [key: string]: unknown
}

interface TaskInstance {
  id: string
  [key: string]: unknown
}

interface ProcessResult {
  recurring_task?: RecurringTask
  [key: string]: unknown
}

interface ProcessDueResult {
  [key: string]: unknown
}

interface FrequencyLabels {
  [key: string]: string
}

interface TargetTypeLabels {
  [key: string]: string
}

interface DayOfWeekLabels {
  [key: number]: string
}

export const useRecurringTasksStore = defineStore('recurringTasks', () => {
  const tasks = ref<RecurringTask[]>([])
  const upcomingTasks = ref<UpcomingTask[]>([])
  const isLoading = ref<boolean>(false)
  const error = ref<string | null>(null)

  const frequencyLabels: FrequencyLabels = {
    daily: 'Täglich',
    weekly: 'Wöchentlich',
    biweekly: 'Zweiwöchentlich',
    monthly: 'Monatlich',
    yearly: 'Jährlich',
    custom: 'Benutzerdefiniert'
  }

  const targetTypeLabels: TargetTypeLabels = {
    list: 'Liste',
    checklist: 'Checkliste',
    kanban: 'Kanban-Board',
    project: 'Projekt'
  }

  const dayOfWeekLabels: DayOfWeekLabels = {
    1: 'Montag',
    2: 'Dienstag',
    3: 'Mittwoch',
    4: 'Donnerstag',
    5: 'Freitag',
    6: 'Samstag',
    0: 'Sonntag'
  }

  const activeTasks = computed<RecurringTask[]>(() => tasks.value.filter(t => t.is_active))
  const inactiveTasks = computed<RecurringTask[]>(() => tasks.value.filter(t => !t.is_active))

  async function loadTasks(filters: Record<string, unknown> = {}): Promise<void> {
    isLoading.value = true
    error.value = null
    try {
      const response = await api.get('/api/v1/recurring-tasks', { params: filters })
      tasks.value = response.data.data
    } catch (err: unknown) {
      error.value = (err as { response?: { data?: { error?: string } } }).response?.data?.error || 'Failed to load recurring tasks'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  async function loadUpcoming(days: number = 7): Promise<UpcomingTask[]> {
    try {
      const response = await api.get('/api/v1/recurring-tasks/upcoming', { params: { days } })
      upcomingTasks.value = response.data.data
      return response.data.data
    } catch (err: unknown) {
      console.error('Failed to load upcoming tasks', err)
      return []
    }
  }

  async function getTask(id: string): Promise<RecurringTask> {
    try {
      const response = await api.get(`/api/v1/recurring-tasks/${id}`)
      return response.data.data
    } catch (err: unknown) {
      throw err
    }
  }

  async function createTask(data: Record<string, unknown>): Promise<RecurringTask> {
    try {
      const response = await api.post('/api/v1/recurring-tasks', data)
      const newTask: RecurringTask = response.data.data
      tasks.value.push(newTask)
      return newTask
    } catch (err: unknown) {
      throw err
    }
  }

  async function updateTask(id: string, data: Record<string, unknown>): Promise<RecurringTask> {
    try {
      const response = await api.put(`/api/v1/recurring-tasks/${id}`, data)
      const updatedTask: RecurringTask = response.data.data
      const index = tasks.value.findIndex(t => t.id === id)
      if (index !== -1) {
        tasks.value[index] = updatedTask
      }
      return updatedTask
    } catch (err: unknown) {
      throw err
    }
  }

  async function deleteTask(id: string): Promise<void> {
    try {
      await api.delete(`/api/v1/recurring-tasks/${id}`)
      tasks.value = tasks.value.filter(t => t.id !== id)
    } catch (err: unknown) {
      throw err
    }
  }

  async function toggleTask(id: string): Promise<RecurringTask> {
    try {
      const response = await api.post(`/api/v1/recurring-tasks/${id}/toggle`)
      const updatedTask: RecurringTask = response.data.data
      const index = tasks.value.findIndex(t => t.id === id)
      if (index !== -1) {
        tasks.value[index] = updatedTask
      }
      return updatedTask
    } catch (err: unknown) {
      throw err
    }
  }

  async function skipOccurrence(id: string): Promise<RecurringTask> {
    try {
      const response = await api.post(`/api/v1/recurring-tasks/${id}/skip`)
      const updatedTask: RecurringTask = response.data.data
      const index = tasks.value.findIndex(t => t.id === id)
      if (index !== -1) {
        tasks.value[index] = updatedTask
      }
      return updatedTask
    } catch (err: unknown) {
      throw err
    }
  }

  async function processTask(id: string): Promise<ProcessResult> {
    try {
      const response = await api.post(`/api/v1/recurring-tasks/${id}/process`)
      const result: ProcessResult = response.data.data
      // Update the task with new next_occurrence
      const index = tasks.value.findIndex(t => t.id === id)
      if (index !== -1 && result.recurring_task) {
        tasks.value[index] = result.recurring_task
      }
      return result
    } catch (err: unknown) {
      throw err
    }
  }

  async function processDueTasks(): Promise<ProcessDueResult> {
    try {
      const response = await api.post('/api/v1/recurring-tasks/process-due')
      // Reload tasks to get updated data
      await loadTasks()
      return response.data.data
    } catch (err: unknown) {
      throw err
    }
  }

  async function getInstances(id: string): Promise<TaskInstance[]> {
    try {
      const response = await api.get(`/api/v1/recurring-tasks/${id}/instances`)
      return response.data.data
    } catch (err: unknown) {
      throw err
    }
  }

  function formatFrequency(task: RecurringTask): string {
    if (task.frequency === 'custom') {
      return `Alle ${task.interval_value} Tage`
    }
    return frequencyLabels[task.frequency] || task.frequency
  }

  function formatDaysOfWeek(daysString: string | null): string {
    if (!daysString) return ''
    const days = daysString.split(',').map(d => parseInt(d.trim()))
    return days.map(d => dayOfWeekLabels[d]).filter(Boolean).join(', ')
  }

  return {
    tasks,
    upcomingTasks,
    isLoading,
    error,
    frequencyLabels,
    targetTypeLabels,
    dayOfWeekLabels,
    activeTasks,
    inactiveTasks,
    loadTasks,
    loadUpcoming,
    getTask,
    createTask,
    updateTask,
    deleteTask,
    toggleTask,
    skipOccurrence,
    processTask,
    processDueTasks,
    getInstances,
    formatFrequency,
    formatDaysOfWeek
  }
})
