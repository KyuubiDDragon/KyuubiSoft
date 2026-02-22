import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/core/api/axios'

export const useFinanceStore = defineStore('finance', () => {
  const expenses = ref([])
  const categories = ref([])
  const summary = ref(null)
  const pagination = ref(null)
  const isLoading = ref(false)

  async function fetchExpenses(params = {}) {
    isLoading.value = true
    try {
      const response = await api.get('/api/v1/expenses', { params })
      expenses.value = response.data.data.items
      pagination.value = response.data.data.pagination
    } finally {
      isLoading.value = false
    }
  }

  async function fetchSummary(params = {}) {
    const response = await api.get('/api/v1/expenses/summary', { params })
    summary.value = response.data.data
  }

  async function fetchCategories() {
    const response = await api.get('/api/v1/expense-categories')
    categories.value = response.data.data
  }

  async function createExpense(data) {
    const response = await api.post('/api/v1/expenses', data)
    expenses.value.unshift(response.data.data)
    return response.data.data
  }

  async function updateExpense(id, data) {
    const response = await api.put(`/api/v1/expenses/${id}`, data)
    const index = expenses.value.findIndex(e => e.id === id)
    if (index !== -1) expenses.value[index] = response.data.data
    return response.data.data
  }

  async function deleteExpense(id) {
    await api.delete(`/api/v1/expenses/${id}`)
    expenses.value = expenses.value.filter(e => e.id !== id)
  }

  async function createCategory(data) {
    const response = await api.post('/api/v1/expense-categories', data)
    categories.value.push(response.data.data)
    return response.data.data
  }

  async function deleteCategory(id) {
    await api.delete(`/api/v1/expense-categories/${id}`)
    categories.value = categories.value.filter(c => c.id !== id)
  }

  return {
    expenses,
    categories,
    summary,
    pagination,
    isLoading,
    fetchExpenses,
    fetchSummary,
    fetchCategories,
    createExpense,
    updateExpense,
    deleteExpense,
    createCategory,
    deleteCategory,
  }
})
