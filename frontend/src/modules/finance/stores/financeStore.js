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
    const response = await api.get('/api/v1/income/summary', { params })
    incomeSummary.value = response.data.data
  }

  async function fetchIncomeCategories() {
    const response = await api.get('/api/v1/income-categories')
    incomeCategories.value = response.data.data
  }

  async function createIncomeEntry(data) {
    const response = await api.post('/api/v1/income', data)
    incomeEntries.value.unshift(response.data.data)
    return response.data.data
  }

  async function updateIncomeEntry(id, data) {
    const response = await api.put(`/api/v1/income/${id}`, data)
    const index = incomeEntries.value.findIndex(e => e.id === id)
    if (index !== -1) incomeEntries.value[index] = response.data.data
    return response.data.data
  }

  async function deleteIncomeEntry(id) {
    await api.delete(`/api/v1/income/${id}`)
    incomeEntries.value = incomeEntries.value.filter(e => e.id !== id)
  }

  async function createIncomeCategory(data) {
    const response = await api.post('/api/v1/income-categories', data)
    incomeCategories.value.push(response.data.data)
    return response.data.data
  }

  async function deleteIncomeCategory(id) {
    await api.delete(`/api/v1/income-categories/${id}`)
    incomeCategories.value = incomeCategories.value.filter(c => c.id !== id)
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
