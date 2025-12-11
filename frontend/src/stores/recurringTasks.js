import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/core/api/axios'

export const useRecurringTasksStore = defineStore('recurringTasks', () => {
  const tasks = ref([])
  const upcomingTasks = ref([])
  const isLoading = ref(false)
  const error = ref(null)

  const frequencyLabels = {
    daily: 'Täglich',
    weekly: 'Wöchentlich',
    biweekly: 'Zweiwöchentlich',
    monthly: 'Monatlich',
    yearly: 'Jährlich',
    custom: 'Benutzerdefiniert'
  }

  const targetTypeLabels = {
    list: 'Liste',
    checklist: 'Checkliste',
    kanban: 'Kanban-Board',
    project: 'Projekt'
  }

  const dayOfWeekLabels = {
    1: 'Montag',
    2: 'Dienstag',
    3: 'Mittwoch',
    4: 'Donnerstag',
    5: 'Freitag',
    6: 'Samstag',
    0: 'Sonntag'
  }

  const activeTasks = computed(() => tasks.value.filter(t => t.is_active))
  const inactiveTasks = computed(() => tasks.value.filter(t => !t.is_active))

  async function loadTasks(filters = {}) {
    isLoading.value = true
    error.value = null
    try {
      const response = await api.get('/api/v1/recurring-tasks', { params: filters })
      tasks.value = response.data.data
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to load recurring tasks'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  async function loadUpcoming(days = 7) {
    try {
      const response = await api.get('/api/v1/recurring-tasks/upcoming', { params: { days } })
      upcomingTasks.value = response.data.data
      return response.data.data
    } catch (err) {
      console.error('Failed to load upcoming tasks', err)
      return []
    }
  }

  async function getTask(id) {
    try {
      const response = await api.get(`/api/v1/recurring-tasks/${id}`)
      return response.data.data
    } catch (err) {
      throw err
    }
  }

  async function createTask(data) {
    try {
      const response = await api.post('/api/v1/recurring-tasks', data)
      const newTask = response.data.data
      tasks.value.push(newTask)
      return newTask
    } catch (err) {
      throw err
    }
  }

  async function updateTask(id, data) {
    try {
      const response = await api.put(`/api/v1/recurring-tasks/${id}`, data)
      const updatedTask = response.data.data
      const index = tasks.value.findIndex(t => t.id === id)
      if (index !== -1) {
        tasks.value[index] = updatedTask
      }
      return updatedTask
    } catch (err) {
      throw err
    }
  }

  async function deleteTask(id) {
    try {
      await api.delete(`/api/v1/recurring-tasks/${id}`)
      tasks.value = tasks.value.filter(t => t.id !== id)
    } catch (err) {
      throw err
    }
  }

  async function toggleTask(id) {
    try {
      const response = await api.post(`/api/v1/recurring-tasks/${id}/toggle`)
      const updatedTask = response.data.data
      const index = tasks.value.findIndex(t => t.id === id)
      if (index !== -1) {
        tasks.value[index] = updatedTask
      }
      return updatedTask
    } catch (err) {
      throw err
    }
  }

  async function skipOccurrence(id) {
    try {
      const response = await api.post(`/api/v1/recurring-tasks/${id}/skip`)
      const updatedTask = response.data.data
      const index = tasks.value.findIndex(t => t.id === id)
      if (index !== -1) {
        tasks.value[index] = updatedTask
      }
      return updatedTask
    } catch (err) {
      throw err
    }
  }

  async function processTask(id) {
    try {
      const response = await api.post(`/api/v1/recurring-tasks/${id}/process`)
      const result = response.data.data
      // Update the task with new next_occurrence
      const index = tasks.value.findIndex(t => t.id === id)
      if (index !== -1 && result.recurring_task) {
        tasks.value[index] = result.recurring_task
      }
      return result
    } catch (err) {
      throw err
    }
  }

  async function processDueTasks() {
    try {
      const response = await api.post('/api/v1/recurring-tasks/process-due')
      // Reload tasks to get updated data
      await loadTasks()
      return response.data.data
    } catch (err) {
      throw err
    }
  }

  async function getInstances(id) {
    try {
      const response = await api.get(`/api/v1/recurring-tasks/${id}/instances`)
      return response.data.data
    } catch (err) {
      throw err
    }
  }

  function formatFrequency(task) {
    if (task.frequency === 'custom') {
      return `Alle ${task.interval_value} Tage`
    }
    return frequencyLabels[task.frequency] || task.frequency
  }

  function formatDaysOfWeek(daysString) {
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
