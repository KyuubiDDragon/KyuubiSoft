import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/core/api/axios'

export const useHabitStore = defineStore('habits', () => {
  const habits = ref([])
  const stats = ref(null)
  const isLoading = ref(false)

  async function fetchHabits() {
    isLoading.value = true
    try {
      const response = await api.get('/api/v1/habits')
      habits.value = response.data.data
    } finally {
      isLoading.value = false
    }
  }

  async function fetchStats() {
    const response = await api.get('/api/v1/habits/stats')
    stats.value = response.data.data
  }

  async function createHabit(data) {
    const response = await api.post('/api/v1/habits', data)
    habits.value.push(response.data.data)
    return response.data.data
  }

  async function updateHabit(id, data) {
    const response = await api.put(`/api/v1/habits/${id}`, data)
    const index = habits.value.findIndex(h => h.id === id)
    if (index !== -1) habits.value[index] = response.data.data
    return response.data.data
  }

  async function deleteHabit(id) {
    await api.delete(`/api/v1/habits/${id}`)
    habits.value = habits.value.filter(h => h.id !== id)
  }

  async function toggleComplete(habitId, date = null) {
    const payload = date ? { date } : {}
    const response = await api.post(`/api/v1/habits/${habitId}/complete`, payload)
    const result = response.data.data

    // Update the habit's completed_today if it's today
    const today = new Date().toISOString().split('T')[0]
    if (!date || date === today) {
      const habit = habits.value.find(h => h.id === habitId)
      if (habit) {
        habit.completed_today = result.completed
        // Recalculate streak locally
        habit.streak = result.completed ? habit.streak + 1 : Math.max(0, habit.streak - 1)
      }
    }

    return result
  }

  async function getCompletions(habitId, from, to) {
    const response = await api.get(`/api/v1/habits/${habitId}/completions`, {
      params: { from, to }
    })
    return response.data.data
  }

  return {
    habits,
    stats,
    isLoading,
    fetchHabits,
    fetchStats,
    createHabit,
    updateHabit,
    deleteHabit,
    toggleComplete,
    getCompletions,
  }
})
