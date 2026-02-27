import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/core/api/axios'

export interface Habit {
  id: string
  name?: string
  description?: string
  color?: string
  icon?: string
  frequency?: string
  target?: number
  streak: number
  completed_today: boolean
  created_at?: string
  updated_at?: string
  [key: string]: unknown
}

export interface HabitStats {
  total_habits?: number
  completed_today?: number
  longest_streak?: number
  completion_rate?: number
  [key: string]: unknown
}

export interface HabitCompletion {
  id?: string
  habit_id: string
  date: string
  completed: boolean
  [key: string]: unknown
}

export interface ToggleCompleteResult {
  completed: boolean
  [key: string]: unknown
}

export interface CreateHabitData {
  name: string
  description?: string
  color?: string
  icon?: string
  frequency?: string
  target?: number
  [key: string]: unknown
}

export const useHabitStore = defineStore('habits', () => {
  const habits = ref<Habit[]>([])
  const stats = ref<HabitStats | null>(null)
  const isLoading = ref<boolean>(false)

  async function fetchHabits(): Promise<void> {
    isLoading.value = true
    try {
      const response = await api.get('/api/v1/habits')
      habits.value = response.data.data
    } finally {
      isLoading.value = false
    }
  }

  async function fetchStats(): Promise<void> {
    const response = await api.get('/api/v1/habits/stats')
    stats.value = response.data.data
  }

  async function createHabit(data: CreateHabitData): Promise<Habit> {
    const response = await api.post('/api/v1/habits', data)
    habits.value.push(response.data.data)
    return response.data.data
  }

  async function updateHabit(id: string, data: Partial<Habit>): Promise<Habit> {
    const response = await api.put(`/api/v1/habits/${id}`, data)
    const index = habits.value.findIndex((h: Habit) => h.id === id)
    if (index !== -1) habits.value[index] = response.data.data
    return response.data.data
  }

  async function deleteHabit(id: string): Promise<void> {
    await api.delete(`/api/v1/habits/${id}`)
    habits.value = habits.value.filter((h: Habit) => h.id !== id)
  }

  async function toggleComplete(habitId: string, date: string | null = null): Promise<ToggleCompleteResult> {
    const payload: Record<string, unknown> = date ? { date } : {}
    const response = await api.post(`/api/v1/habits/${habitId}/complete`, payload)
    const result: ToggleCompleteResult = response.data.data

    // Update the habit's completed_today if it's today
    const today: string = new Date().toISOString().split('T')[0]
    if (!date || date === today) {
      const habit = habits.value.find((h: Habit) => h.id === habitId)
      if (habit) {
        habit.completed_today = result.completed
        // Recalculate streak locally
        habit.streak = result.completed ? habit.streak + 1 : Math.max(0, habit.streak - 1)
      }
    }

    return result
  }

  async function getCompletions(habitId: string, from: string, to: string): Promise<HabitCompletion[]> {
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
