import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/core/api/axios'

export const useFinanceStore = defineStore('finance', () => {
  // Expenses
  const expenses = ref([])
  const categories = ref([])
  const summary = ref(null)
  const pagination = ref(null)
  const isLoading = ref(false)

  // Income
  const incomeEntries = ref([])
  const incomeCategories = ref([])
  const incomeSummary = ref(null)
  const incomeIsLoading = ref(false)

  // EÜR
  const euer = ref(null)
  const euerIsLoading = ref(false)

  // ─── Expenses ─────────────────────────────────────────────────────────────

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
    try {
      const response = await api.get('/api/v1/expenses/summary', { params })
      summary.value = response.data.data
    } catch (e) {
      console.error('fetchSummary failed:', e)
      throw e
    }
  }

  async function fetchCategories() {
    try {
      const response = await api.get('/api/v1/expense-categories')
      categories.value = response.data.data
    } catch (e) {
      console.error('fetchCategories failed:', e)
      throw e
    }
  }

  async function createExpense(data) {
    try {
      const response = await api.post('/api/v1/expenses', data)
      expenses.value.unshift(response.data.data)
      return response.data.data
    } catch (e) {
      console.error('createExpense failed:', e)
      throw e
    }
  }

  async function updateExpense(id, data) {
    try {
      const response = await api.put(`/api/v1/expenses/${id}`, data)
      const index = expenses.value.findIndex(e => e.id === id)
      if (index !== -1) expenses.value[index] = response.data.data
      return response.data.data
    } catch (e) {
      console.error('updateExpense failed:', e)
      throw e
    }
  }

  async function deleteExpense(id) {
    try {
      await api.delete(`/api/v1/expenses/${id}`)
      expenses.value = expenses.value.filter(e => e.id !== id)
    } catch (e) {
      console.error('deleteExpense failed:', e)
      throw e
    }
  }

  async function createCategory(data) {
    try {
      const response = await api.post('/api/v1/expense-categories', data)
      categories.value.push(response.data.data)
      return response.data.data
    } catch (e) {
      console.error('createCategory failed:', e)
      throw e
    }
  }

  async function deleteCategory(id) {
    try {
      await api.delete(`/api/v1/expense-categories/${id}`)
      categories.value = categories.value.filter(c => c.id !== id)
    } catch (e) {
      console.error('deleteCategory failed:', e)
      throw e
    }
  }

  // ─── Income ───────────────────────────────────────────────────────────────

  async function fetchIncomeEntries(params = {}) {
    incomeIsLoading.value = true
    try {
      const response = await api.get('/api/v1/income', { params })
      incomeEntries.value = response.data.data.items
    } finally {
      incomeIsLoading.value = false
    }
  }

  async function fetchIncomeSummary(params = {}) {
    try {
      const response = await api.get('/api/v1/income/summary', { params })
      incomeSummary.value = response.data.data
    } catch (e) {
      console.error('fetchIncomeSummary failed:', e)
      throw e
    }
  }

  async function fetchIncomeCategories() {
    try {
      const response = await api.get('/api/v1/income-categories')
      incomeCategories.value = response.data.data
    } catch (e) {
      console.error('fetchIncomeCategories failed:', e)
      throw e
    }
  }

  async function createIncomeEntry(data) {
    try {
      const response = await api.post('/api/v1/income', data)
      incomeEntries.value.unshift(response.data.data)
      return response.data.data
    } catch (e) {
      console.error('createIncomeEntry failed:', e)
      throw e
    }
  }

  async function updateIncomeEntry(id, data) {
    try {
      const response = await api.put(`/api/v1/income/${id}`, data)
      const index = incomeEntries.value.findIndex(e => e.id === id)
      if (index !== -1) incomeEntries.value[index] = response.data.data
      return response.data.data
    } catch (e) {
      console.error('updateIncomeEntry failed:', e)
      throw e
    }
  }

  async function deleteIncomeEntry(id) {
    try {
      await api.delete(`/api/v1/income/${id}`)
      incomeEntries.value = incomeEntries.value.filter(e => e.id !== id)
    } catch (e) {
      console.error('deleteIncomeEntry failed:', e)
      throw e
    }
  }

  async function createIncomeCategory(data) {
    try {
      const response = await api.post('/api/v1/income-categories', data)
      incomeCategories.value.push(response.data.data)
      return response.data.data
    } catch (e) {
      console.error('createIncomeCategory failed:', e)
      throw e
    }
  }

  async function deleteIncomeCategory(id) {
    try {
      await api.delete(`/api/v1/income-categories/${id}`)
      incomeCategories.value = incomeCategories.value.filter(c => c.id !== id)
    } catch (e) {
      console.error('deleteIncomeCategory failed:', e)
      throw e
    }
  }

  // ─── EÜR ──────────────────────────────────────────────────────────────────

  async function fetchEuer(year) {
    euerIsLoading.value = true
    try {
      const response = await api.get('/api/v1/finance/euer', { params: { year } })
      euer.value = response.data.data
    } finally {
      euerIsLoading.value = false
    }
  }

  function getEuerCsvUrl(year) {
    return `/api/v1/finance/euer/export?year=${year}`
  }

  return {
    // Expenses
    expenses, categories, summary, pagination, isLoading,
    fetchExpenses, fetchSummary, fetchCategories,
    createExpense, updateExpense, deleteExpense,
    createCategory, deleteCategory,

    // Income
    incomeEntries, incomeCategories, incomeSummary, incomeIsLoading,
    fetchIncomeEntries, fetchIncomeSummary, fetchIncomeCategories,
    createIncomeEntry, updateIncomeEntry, deleteIncomeEntry,
    createIncomeCategory, deleteIncomeCategory,

    // EÜR
    euer, euerIsLoading,
    fetchEuer, getEuerCsvUrl,
  }
})
