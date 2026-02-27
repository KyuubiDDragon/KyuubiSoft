import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/core/api/axios'

export interface Expense {
  id: string
  amount?: number
  description?: string
  date?: string
  category_id?: string
  category?: ExpenseCategory
  receipt_url?: string
  created_at?: string
  updated_at?: string
  [key: string]: unknown
}

export interface ExpenseCategory {
  id: string
  name: string
  color?: string
  icon?: string
  [key: string]: unknown
}

export interface ExpenseSummary {
  total?: number
  by_category?: Record<string, number>
  monthly?: Record<string, number>
  [key: string]: unknown
}

export interface IncomeEntry {
  id: string
  amount?: number
  description?: string
  date?: string
  category_id?: string
  category?: IncomeCategory
  created_at?: string
  updated_at?: string
  [key: string]: unknown
}

export interface IncomeCategory {
  id: string
  name: string
  color?: string
  icon?: string
  [key: string]: unknown
}

export interface IncomeSummary {
  total?: number
  by_category?: Record<string, number>
  monthly?: Record<string, number>
  [key: string]: unknown
}

export interface EuerReport {
  year?: number
  income_total?: number
  expense_total?: number
  profit?: number
  entries?: Record<string, unknown>[]
  [key: string]: unknown
}

export interface Pagination {
  page?: number
  per_page?: number
  total?: number
  total_pages?: number
  [key: string]: unknown
}

export const useFinanceStore = defineStore('finance', () => {
  // Expenses
  const expenses = ref<Expense[]>([])
  const categories = ref<ExpenseCategory[]>([])
  const summary = ref<ExpenseSummary | null>(null)
  const pagination = ref<Pagination | null>(null)
  const isLoading = ref<boolean>(false)

  // Income
  const incomeEntries = ref<IncomeEntry[]>([])
  const incomeCategories = ref<IncomeCategory[]>([])
  const incomeSummary = ref<IncomeSummary | null>(null)
  const incomeIsLoading = ref<boolean>(false)

  // EUeR
  const euer = ref<EuerReport | null>(null)
  const euerIsLoading = ref<boolean>(false)

  // --- Expenses ---

  async function fetchExpenses(params: Record<string, unknown> = {}): Promise<void> {
    isLoading.value = true
    try {
      const response = await api.get('/api/v1/expenses', { params })
      expenses.value = response.data.data.items
      pagination.value = response.data.data.pagination
    } finally {
      isLoading.value = false
    }
  }

  async function fetchSummary(params: Record<string, unknown> = {}): Promise<void> {
    try {
      const response = await api.get('/api/v1/expenses/summary', { params })
      summary.value = response.data.data
    } catch (e) {
      console.error('fetchSummary failed:', e)
      throw e
    }
  }

  async function fetchCategories(): Promise<void> {
    try {
      const response = await api.get('/api/v1/expense-categories')
      categories.value = response.data.data
    } catch (e) {
      console.error('fetchCategories failed:', e)
      throw e
    }
  }

  async function createExpense(data: Partial<Expense>): Promise<Expense> {
    try {
      const response = await api.post('/api/v1/expenses', data)
      expenses.value.unshift(response.data.data)
      return response.data.data
    } catch (e) {
      console.error('createExpense failed:', e)
      throw e
    }
  }

  async function updateExpense(id: string, data: Partial<Expense>): Promise<Expense> {
    try {
      const response = await api.put(`/api/v1/expenses/${id}`, data)
      const index = expenses.value.findIndex((e: Expense) => e.id === id)
      if (index !== -1) expenses.value[index] = response.data.data
      return response.data.data
    } catch (e) {
      console.error('updateExpense failed:', e)
      throw e
    }
  }

  async function deleteExpense(id: string): Promise<void> {
    try {
      await api.delete(`/api/v1/expenses/${id}`)
      expenses.value = expenses.value.filter((e: Expense) => e.id !== id)
    } catch (e) {
      console.error('deleteExpense failed:', e)
      throw e
    }
  }

  async function createCategory(data: Partial<ExpenseCategory>): Promise<ExpenseCategory> {
    try {
      const response = await api.post('/api/v1/expense-categories', data)
      categories.value.push(response.data.data)
      return response.data.data
    } catch (e) {
      console.error('createCategory failed:', e)
      throw e
    }
  }

  async function deleteCategory(id: string): Promise<void> {
    try {
      await api.delete(`/api/v1/expense-categories/${id}`)
      categories.value = categories.value.filter((c: ExpenseCategory) => c.id !== id)
    } catch (e) {
      console.error('deleteCategory failed:', e)
      throw e
    }
  }

  // --- Income ---

  async function fetchIncomeEntries(params: Record<string, unknown> = {}): Promise<void> {
    incomeIsLoading.value = true
    try {
      const response = await api.get('/api/v1/income', { params })
      incomeEntries.value = response.data.data.items
    } finally {
      incomeIsLoading.value = false
    }
  }

  async function fetchIncomeSummary(params: Record<string, unknown> = {}): Promise<void> {
    try {
      const response = await api.get('/api/v1/income/summary', { params })
      incomeSummary.value = response.data.data
    } catch (e) {
      console.error('fetchIncomeSummary failed:', e)
      throw e
    }
  }

  async function fetchIncomeCategories(): Promise<void> {
    try {
      const response = await api.get('/api/v1/income-categories')
      incomeCategories.value = response.data.data
    } catch (e) {
      console.error('fetchIncomeCategories failed:', e)
      throw e
    }
  }

  async function createIncomeEntry(data: Partial<IncomeEntry>): Promise<IncomeEntry> {
    try {
      const response = await api.post('/api/v1/income', data)
      incomeEntries.value.unshift(response.data.data)
      return response.data.data
    } catch (e) {
      console.error('createIncomeEntry failed:', e)
      throw e
    }
  }

  async function updateIncomeEntry(id: string, data: Partial<IncomeEntry>): Promise<IncomeEntry> {
    try {
      const response = await api.put(`/api/v1/income/${id}`, data)
      const index = incomeEntries.value.findIndex((e: IncomeEntry) => e.id === id)
      if (index !== -1) incomeEntries.value[index] = response.data.data
      return response.data.data
    } catch (e) {
      console.error('updateIncomeEntry failed:', e)
      throw e
    }
  }

  async function deleteIncomeEntry(id: string): Promise<void> {
    try {
      await api.delete(`/api/v1/income/${id}`)
      incomeEntries.value = incomeEntries.value.filter((e: IncomeEntry) => e.id !== id)
    } catch (e) {
      console.error('deleteIncomeEntry failed:', e)
      throw e
    }
  }

  async function createIncomeCategory(data: Partial<IncomeCategory>): Promise<IncomeCategory> {
    try {
      const response = await api.post('/api/v1/income-categories', data)
      incomeCategories.value.push(response.data.data)
      return response.data.data
    } catch (e) {
      console.error('createIncomeCategory failed:', e)
      throw e
    }
  }

  async function deleteIncomeCategory(id: string): Promise<void> {
    try {
      await api.delete(`/api/v1/income-categories/${id}`)
      incomeCategories.value = incomeCategories.value.filter((c: IncomeCategory) => c.id !== id)
    } catch (e) {
      console.error('deleteIncomeCategory failed:', e)
      throw e
    }
  }

  // --- EUeR ---

  async function fetchEuer(year: number): Promise<void> {
    euerIsLoading.value = true
    try {
      const response = await api.get('/api/v1/finance/euer', { params: { year } })
      euer.value = response.data.data
    } finally {
      euerIsLoading.value = false
    }
  }

  function getEuerCsvUrl(year: number): string {
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

    // EUeR
    euer, euerIsLoading,
    fetchEuer, getEuerCsvUrl,
  }
})
