<script setup>
import { ref, computed, onMounted } from 'vue'
import {
  PlusIcon,
  XMarkIcon,
  TrashIcon,
  PencilIcon,
  BanknotesIcon,
  ChartPieIcon,
  ArrowTrendingDownIcon,
  ArrowTrendingUpIcon,
  DocumentChartBarIcon,
  ArrowDownTrayIcon,
  PaperClipIcon,
} from '@heroicons/vue/24/outline'
import { useFinanceStore } from '../stores/financeStore.js'
import { useToast } from '@/composables/useToast'
import api from '@/core/api/axios'

const financeStore = useFinanceStore()
const toast = useToast()

// Active tab
const activeTab = ref('expenses')

// Date range
const selectedMonth = ref(new Date().toISOString().slice(0, 7)) // YYYY-MM
const selectedYear = ref(new Date().getFullYear())

const fromDate = computed(() => `${selectedMonth.value}-01`)
const toDate = computed(() => {
  const [year, month] = selectedMonth.value.split('-')
  const lastDay = new Date(parseInt(year), parseInt(month), 0).getDate()
  return `${selectedMonth.value}-${String(lastDay).padStart(2, '0')}`
})

// ─── Expense state ────────────────────────────────────────────────────────

const showExpenseForm = ref(false)
const showCategoryForm = ref(false)
const editingExpense = ref(null)
const receiptFile = ref(null)
const receiptUploading = ref(false)

const expenseForm = ref({
  description: '',
  amount: '',
  expense_date: new Date().toISOString().split('T')[0],
  category_id: '',
  currency: 'EUR',
  notes: '',
  is_recurring: false,
  receipt_file_id: null,
})

const categoryForm = ref({ name: '', color: '#3B82F6' })

const colorOptions = [
  '#3B82F6', '#10B981', '#F59E0B', '#EF4444',
  '#8B5CF6', '#EC4899', '#06B6D4', '#84CC16',
]

const totalThisMonth = computed(() => financeStore.summary?.total || 0)

const topCategories = computed(() => {
  if (!financeStore.summary) return []
  return (financeStore.summary.by_category || [])
    .filter(c => c.total > 0)
    .slice(0, 5)
})

// ─── Income state ─────────────────────────────────────────────────────────

const showIncomeForm = ref(false)
const showIncomeCategoryForm = ref(false)
const editingIncome = ref(null)
const incomeReceiptFile = ref(null)
const incomeReceiptUploading = ref(false)

const incomeForm = ref({
  description: '',
  amount: '',
  income_date: new Date().toISOString().split('T')[0],
  category_id: '',
  currency: 'EUR',
  source: '',
  notes: '',
  receipt_file_id: null,
})

const incomeCategoryForm = ref({ name: '', color: '#10B981' })

const incomeColorOptions = [
  '#10B981', '#3B82F6', '#F59E0B', '#EF4444',
  '#8B5CF6', '#EC4899', '#06B6D4', '#84CC16',
]

const totalIncomeThisMonth = computed(() => financeStore.incomeSummary?.total || 0)

const topIncomeCategories = computed(() => {
  if (!financeStore.incomeSummary) return []
  return (financeStore.incomeSummary.by_category || [])
    .filter(c => c.total > 0)
    .slice(0, 5)
})

// ─── Helpers ──────────────────────────────────────────────────────────────

function formatCurrency(amount) {
  return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(amount || 0)
}

function getCategoryName(categoryId, list) {
  const cat = list.find(c => c.id === categoryId)
  return cat?.name || 'Ohne Kategorie'
}

function getCategoryColor(categoryId, list) {
  const cat = list.find(c => c.id === categoryId)
  return cat?.color || '#6B7280'
}

// ─── Receipt upload ───────────────────────────────────────────────────────

async function uploadReceipt(file) {
  if (!file) return null
  const formData = new FormData()
  formData.append('file', file)
  const resp = await api.post('/api/v1/storage', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return resp.data.data?.id || null
}

// ─── Expense actions ──────────────────────────────────────────────────────

function openCreateExpense() {
  editingExpense.value = null
  receiptFile.value = null
  expenseForm.value = {
    description: '',
    amount: '',
    expense_date: new Date().toISOString().split('T')[0],
    category_id: '',
    currency: 'EUR',
    notes: '',
    is_recurring: false,
    receipt_file_id: null,
  }
  showExpenseForm.value = true
}

function openEditExpense(expense) {
  editingExpense.value = expense
  receiptFile.value = null
  expenseForm.value = {
    description: expense.description,
    amount: expense.amount,
    expense_date: expense.expense_date,
    category_id: expense.category_id || '',
    currency: expense.currency,
    notes: expense.notes || '',
    is_recurring: expense.is_recurring,
    receipt_file_id: expense.receipt_file_id || null,
  }
  showExpenseForm.value = true
}

async function saveExpense() {
  if (!expenseForm.value.description || !expenseForm.value.amount) return

  receiptUploading.value = true
  try {
    if (receiptFile.value) {
      const fileId = await uploadReceipt(receiptFile.value)
      if (fileId) expenseForm.value.receipt_file_id = fileId
    }
  } finally {
    receiptUploading.value = false
  }

  const data = {
    ...expenseForm.value,
    amount: parseFloat(expenseForm.value.amount),
    category_id: expenseForm.value.category_id || null,
  }

  try {
    if (editingExpense.value) {
      await financeStore.updateExpense(editingExpense.value.id, data)
    } else {
      await financeStore.createExpense(data)
    }
    showExpenseForm.value = false
    await loadExpenseData()
  } catch (e) {
    toast.error('Speichern fehlgeschlagen: ' + (e?.response?.data?.message ?? e?.message ?? 'Unbekannter Fehler'))
  }
}

async function deleteExpense(expense) {
  if (!confirm(`"${expense.description}" wirklich löschen?`)) return
  await financeStore.deleteExpense(expense.id)
  await financeStore.fetchSummary({ from: fromDate.value, to: toDate.value })
}

async function saveCategory() {
  if (!categoryForm.value.name.trim()) return
  await financeStore.createCategory(categoryForm.value)
  categoryForm.value = { name: '', color: '#3B82F6' }
  showCategoryForm.value = false
}

async function deleteCategory(cat) {
  if (!confirm(`Kategorie "${cat.name}" löschen?`)) return
  await financeStore.deleteCategory(cat.id)
}

// ─── Income actions ───────────────────────────────────────────────────────

function openCreateIncome() {
  editingIncome.value = null
  incomeReceiptFile.value = null
  incomeForm.value = {
    description: '',
    amount: '',
    income_date: new Date().toISOString().split('T')[0],
    category_id: '',
    currency: 'EUR',
    source: '',
    notes: '',
    receipt_file_id: null,
  }
  showIncomeForm.value = true
}

function openEditIncome(entry) {
  editingIncome.value = entry
  incomeReceiptFile.value = null
  incomeForm.value = {
    description: entry.description,
    amount: entry.amount,
    income_date: entry.income_date,
    category_id: entry.category_id || '',
    currency: entry.currency,
    source: entry.source || '',
    notes: entry.notes || '',
    receipt_file_id: entry.receipt_file_id || null,
  }
  showIncomeForm.value = true
}

async function saveIncome() {
  if (!incomeForm.value.description || !incomeForm.value.amount) return

  incomeReceiptUploading.value = true
  try {
    if (incomeReceiptFile.value) {
      const fileId = await uploadReceipt(incomeReceiptFile.value)
      if (fileId) incomeForm.value.receipt_file_id = fileId
    }
  } finally {
    incomeReceiptUploading.value = false
  }

  const data = {
    ...incomeForm.value,
    amount: parseFloat(incomeForm.value.amount),
    category_id: incomeForm.value.category_id || null,
  }

  try {
    if (editingIncome.value) {
      await financeStore.updateIncomeEntry(editingIncome.value.id, data)
    } else {
      await financeStore.createIncomeEntry(data)
    }
    showIncomeForm.value = false
    await loadIncomeData()
  } catch (e) {
    toast.error('Speichern fehlgeschlagen: ' + (e?.response?.data?.message ?? e?.message ?? 'Unbekannter Fehler'))
  }
}

async function deleteIncome(entry) {
  if (!confirm(`"${entry.description}" wirklich löschen?`)) return
  await financeStore.deleteIncomeEntry(entry.id)
  await financeStore.fetchIncomeSummary({ from: fromDate.value, to: toDate.value })
}

async function saveIncomeCategory() {
  if (!incomeCategoryForm.value.name.trim()) return
  await financeStore.createIncomeCategory(incomeCategoryForm.value)
  incomeCategoryForm.value = { name: '', color: '#10B981' }
  showIncomeCategoryForm.value = false
}

async function deleteIncomeCategory(cat) {
  if (!confirm(`Kategorie "${cat.name}" löschen?`)) return
  await financeStore.deleteIncomeCategory(cat.id)
}

// ─── Data loading ─────────────────────────────────────────────────────────

async function loadExpenseData() {
  await Promise.all([
    financeStore.fetchExpenses({ from: fromDate.value, to: toDate.value }),
    financeStore.fetchSummary({ from: fromDate.value, to: toDate.value }),
    financeStore.fetchCategories(),
  ])
}

async function loadIncomeData() {
  await Promise.all([
    financeStore.fetchIncomeEntries({ from: fromDate.value, to: toDate.value }),
    financeStore.fetchIncomeSummary({ from: fromDate.value, to: toDate.value }),
    financeStore.fetchIncomeCategories(),
  ])
}

async function loadEuerData() {
  await financeStore.fetchEuer(selectedYear.value)
}

async function switchTab(tab) {
  activeTab.value = tab
  if (tab === 'expenses') await loadExpenseData()
  else if (tab === 'income') await loadIncomeData()
  else if (tab === 'euer') await loadEuerData()
}

function downloadEuerCsv() {
  const url = financeStore.getEuerCsvUrl(selectedYear.value)
  const link = document.createElement('a')
  link.href = url
  link.download = `euer-${selectedYear.value}.csv`
  link.click()
}

// EÜR bar chart width helper
function barWidth(value, max) {
  if (!max || max === 0) return '0%'
  return `${Math.min(100, (value / max) * 100)}%`
}

onMounted(loadExpenseData)
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between flex-wrap gap-3">
      <div>
        <h1 class="text-2xl font-bold text-white">Finanzen</h1>
        <p class="text-gray-400 mt-1">Ausgaben, Einnahmen & EÜR-Auswertung</p>
      </div>
      <div class="flex items-center gap-2">
        <input
          v-if="activeTab !== 'euer'"
          v-model="selectedMonth"
          type="month"
          class="input text-sm"
          @change="activeTab === 'expenses' ? loadExpenseData() : loadIncomeData()"
        />
        <select
          v-if="activeTab === 'euer'"
          v-model.number="selectedYear"
          class="input text-sm w-28"
          @change="loadEuerData"
        >
          <option v-for="y in [2026, 2025, 2024, 2023]" :key="y" :value="y">{{ y }}</option>
        </select>

        <template v-if="activeTab === 'expenses'">
          <button @click="showCategoryForm = true" class="btn-secondary text-sm">Kategorien</button>
          <button @click="openCreateExpense" class="btn-primary text-sm">
            <PlusIcon class="w-4 h-4 mr-1" />
            Ausgabe
          </button>
        </template>
        <template v-if="activeTab === 'income'">
          <button @click="showIncomeCategoryForm = true" class="btn-secondary text-sm">Kategorien</button>
          <button @click="openCreateIncome" class="btn-primary text-sm">
            <PlusIcon class="w-4 h-4 mr-1" />
            Einnahme
          </button>
        </template>
        <template v-if="activeTab === 'euer'">
          <button @click="downloadEuerCsv" class="btn-secondary text-sm">
            <ArrowDownTrayIcon class="w-4 h-4 mr-1" />
            CSV Export
          </button>
        </template>
      </div>
    </div>

    <!-- Tabs -->
    <div class="flex gap-1 border-b border-dark-700">
      <button
        v-for="tab in [{ id: 'expenses', label: 'Ausgaben' }, { id: 'income', label: 'Einnahmen' }, { id: 'euer', label: 'EÜR' }]"
        :key="tab.id"
        @click="switchTab(tab.id)"
        class="px-4 py-2 text-sm font-medium transition-colors border-b-2 -mb-px"
        :class="activeTab === tab.id ? 'text-white border-primary-500' : 'text-gray-400 border-transparent hover:text-white'"
      >
        {{ tab.label }}
      </button>
    </div>

    <!-- ═══ EXPENSES TAB ═══ -->
    <template v-if="activeTab === 'expenses'">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="card p-4">
          <div class="flex items-center gap-3 mb-2">
            <div class="w-10 h-10 rounded-lg bg-red-500/20 flex items-center justify-center">
              <ArrowTrendingDownIcon class="w-5 h-5 text-red-400" />
            </div>
            <span class="text-gray-400 text-sm">Ausgaben gesamt</span>
          </div>
          <p class="text-2xl font-bold text-red-400">{{ formatCurrency(totalThisMonth) }}</p>
        </div>
        <div class="card p-4">
          <div class="flex items-center gap-3 mb-2">
            <div class="w-10 h-10 rounded-lg bg-primary-500/20 flex items-center justify-center">
              <ChartPieIcon class="w-5 h-5 text-primary-400" />
            </div>
            <span class="text-gray-400 text-sm">Kategorien</span>
          </div>
          <p class="text-2xl font-bold text-white">{{ financeStore.categories.length }}</p>
        </div>
        <div class="card p-4">
          <div class="flex items-center gap-3 mb-2">
            <div class="w-10 h-10 rounded-lg bg-yellow-500/20 flex items-center justify-center">
              <BanknotesIcon class="w-5 h-5 text-yellow-400" />
            </div>
            <span class="text-gray-400 text-sm">Transaktionen</span>
          </div>
          <p class="text-2xl font-bold text-white">{{ financeStore.expenses.length }}</p>
        </div>
      </div>

      <!-- Category breakdown -->
      <div v-if="topCategories.length > 0" class="card p-6">
        <h3 class="text-white font-semibold mb-4">Ausgaben nach Kategorie</h3>
        <div class="space-y-3">
          <div v-for="cat in topCategories" :key="cat.id" class="flex items-center gap-3">
            <span class="w-3 h-3 rounded-full flex-shrink-0" :style="{ backgroundColor: cat.color }"></span>
            <span class="text-sm text-gray-300 flex-1">{{ cat.name || 'Ohne Kategorie' }}</span>
            <div class="flex-1 h-2 bg-dark-600 rounded-full overflow-hidden">
              <div
                class="h-full rounded-full transition-all duration-500"
                :style="{ width: `${totalThisMonth > 0 ? (cat.total / totalThisMonth) * 100 : 0}%`, backgroundColor: cat.color }"
              ></div>
            </div>
            <span class="text-sm font-medium text-white w-20 text-right">{{ formatCurrency(cat.total) }}</span>
          </div>
        </div>
      </div>

      <!-- Expense list -->
      <div class="card">
        <div class="px-6 py-4 border-b border-dark-700">
          <h3 class="font-semibold text-white">Transaktionen</h3>
        </div>
        <div v-if="financeStore.isLoading" class="p-6">
          <div v-for="i in 3" :key="i" class="h-12 bg-dark-700 rounded mb-3 animate-pulse"></div>
        </div>
        <div v-else-if="financeStore.expenses.length === 0" class="p-12 text-center">
          <BanknotesIcon class="w-12 h-12 text-gray-600 mx-auto mb-3" />
          <p class="text-gray-500">Keine Ausgaben in diesem Zeitraum</p>
        </div>
        <div v-else>
          <div
            v-for="expense in financeStore.expenses"
            :key="expense.id"
            class="flex items-center gap-4 px-6 py-3 hover:bg-dark-700/50 transition-colors border-b border-dark-700/50 last:border-0"
          >
            <span
              class="w-2.5 h-2.5 rounded-full flex-shrink-0"
              :style="{ backgroundColor: getCategoryColor(expense.category_id, financeStore.categories) }"
            ></span>
            <div class="flex-1 min-w-0">
              <p class="text-sm text-white truncate">{{ expense.description }}</p>
              <p class="text-xs text-gray-500">{{ getCategoryName(expense.category_id, financeStore.categories) }} · {{ expense.expense_date }}</p>
            </div>
            <span v-if="expense.receipt_file_id" class="text-gray-500" title="Beleg vorhanden">
              <PaperClipIcon class="w-3.5 h-3.5" />
            </span>
            <span class="text-sm font-medium text-red-400 flex-shrink-0">
              -{{ formatCurrency(expense.amount) }}
            </span>
            <div class="flex items-center gap-1">
              <button @click="openEditExpense(expense)" class="p-1 text-gray-500 hover:text-gray-300">
                <PencilIcon class="w-3.5 h-3.5" />
              </button>
              <button @click="deleteExpense(expense)" class="p-1 text-gray-500 hover:text-red-400">
                <TrashIcon class="w-3.5 h-3.5" />
              </button>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- ═══ INCOME TAB ═══ -->
    <template v-else-if="activeTab === 'income'">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="card p-4">
          <div class="flex items-center gap-3 mb-2">
            <div class="w-10 h-10 rounded-lg bg-green-500/20 flex items-center justify-center">
              <ArrowTrendingUpIcon class="w-5 h-5 text-green-400" />
            </div>
            <span class="text-gray-400 text-sm">Einnahmen gesamt</span>
          </div>
          <p class="text-2xl font-bold text-green-400">{{ formatCurrency(totalIncomeThisMonth) }}</p>
        </div>
        <div class="card p-4">
          <div class="flex items-center gap-3 mb-2">
            <div class="w-10 h-10 rounded-lg bg-primary-500/20 flex items-center justify-center">
              <ChartPieIcon class="w-5 h-5 text-primary-400" />
            </div>
            <span class="text-gray-400 text-sm">Kategorien</span>
          </div>
          <p class="text-2xl font-bold text-white">{{ financeStore.incomeCategories.length }}</p>
        </div>
        <div class="card p-4">
          <div class="flex items-center gap-3 mb-2">
            <div class="w-10 h-10 rounded-lg bg-yellow-500/20 flex items-center justify-center">
              <BanknotesIcon class="w-5 h-5 text-yellow-400" />
            </div>
            <span class="text-gray-400 text-sm">Transaktionen</span>
          </div>
          <p class="text-2xl font-bold text-white">{{ financeStore.incomeEntries.length }}</p>
        </div>
      </div>

      <!-- Income category breakdown -->
      <div v-if="topIncomeCategories.length > 0" class="card p-6">
        <h3 class="text-white font-semibold mb-4">Einnahmen nach Kategorie</h3>
        <div class="space-y-3">
          <div v-for="cat in topIncomeCategories" :key="cat.id" class="flex items-center gap-3">
            <span class="w-3 h-3 rounded-full flex-shrink-0" :style="{ backgroundColor: cat.color }"></span>
            <span class="text-sm text-gray-300 flex-1">{{ cat.name || 'Ohne Kategorie' }}</span>
            <div class="flex-1 h-2 bg-dark-600 rounded-full overflow-hidden">
              <div
                class="h-full rounded-full transition-all duration-500"
                :style="{ width: `${totalIncomeThisMonth > 0 ? (cat.total / totalIncomeThisMonth) * 100 : 0}%`, backgroundColor: cat.color }"
              ></div>
            </div>
            <span class="text-sm font-medium text-white w-20 text-right">{{ formatCurrency(cat.total) }}</span>
          </div>
        </div>
      </div>

      <!-- Income list -->
      <div class="card">
        <div class="px-6 py-4 border-b border-dark-700">
          <h3 class="font-semibold text-white">Einnahmen</h3>
        </div>
        <div v-if="financeStore.incomeIsLoading" class="p-6">
          <div v-for="i in 3" :key="i" class="h-12 bg-dark-700 rounded mb-3 animate-pulse"></div>
        </div>
        <div v-else-if="financeStore.incomeEntries.length === 0" class="p-12 text-center">
          <ArrowTrendingUpIcon class="w-12 h-12 text-gray-600 mx-auto mb-3" />
          <p class="text-gray-500">Keine Einnahmen in diesem Zeitraum</p>
        </div>
        <div v-else>
          <div
            v-for="entry in financeStore.incomeEntries"
            :key="entry.id"
            class="flex items-center gap-4 px-6 py-3 hover:bg-dark-700/50 transition-colors border-b border-dark-700/50 last:border-0"
          >
            <span
              class="w-2.5 h-2.5 rounded-full flex-shrink-0"
              :style="{ backgroundColor: getCategoryColor(entry.category_id, financeStore.incomeCategories) }"
            ></span>
            <div class="flex-1 min-w-0">
              <p class="text-sm text-white truncate">{{ entry.description }}</p>
              <p class="text-xs text-gray-500">
                {{ getCategoryName(entry.category_id, financeStore.incomeCategories) }}
                <span v-if="entry.source"> · {{ entry.source }}</span>
                · {{ entry.income_date }}
              </p>
            </div>
            <span v-if="entry.receipt_file_id" class="text-gray-500" title="Beleg vorhanden">
              <PaperClipIcon class="w-3.5 h-3.5" />
            </span>
            <span class="text-sm font-medium text-green-400 flex-shrink-0">
              +{{ formatCurrency(entry.amount) }}
            </span>
            <div class="flex items-center gap-1">
              <button @click="openEditIncome(entry)" class="p-1 text-gray-500 hover:text-gray-300">
                <PencilIcon class="w-3.5 h-3.5" />
              </button>
              <button @click="deleteIncome(entry)" class="p-1 text-gray-500 hover:text-red-400">
                <TrashIcon class="w-3.5 h-3.5" />
              </button>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- ═══ EÜR TAB ═══ -->
    <template v-else-if="activeTab === 'euer'">
      <div v-if="financeStore.euerIsLoading" class="p-12 text-center">
        <div class="w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full animate-spin mx-auto"></div>
      </div>

      <template v-else-if="financeStore.euer">
        <!-- Summary cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="card p-4">
            <div class="flex items-center gap-3 mb-2">
              <div class="w-10 h-10 rounded-lg bg-green-500/20 flex items-center justify-center">
                <ArrowTrendingUpIcon class="w-5 h-5 text-green-400" />
              </div>
              <span class="text-gray-400 text-sm">Einnahmen {{ selectedYear }}</span>
            </div>
            <p class="text-2xl font-bold text-green-400">{{ formatCurrency(financeStore.euer.total_income) }}</p>
            <p class="text-xs text-gray-500 mt-1">
              Manuell: {{ formatCurrency(financeStore.euer.income_manual) }} ·
              Rechnungen: {{ formatCurrency(financeStore.euer.income_invoices) }}
            </p>
          </div>
          <div class="card p-4">
            <div class="flex items-center gap-3 mb-2">
              <div class="w-10 h-10 rounded-lg bg-red-500/20 flex items-center justify-center">
                <ArrowTrendingDownIcon class="w-5 h-5 text-red-400" />
              </div>
              <span class="text-gray-400 text-sm">Ausgaben {{ selectedYear }}</span>
            </div>
            <p class="text-2xl font-bold text-red-400">{{ formatCurrency(financeStore.euer.total_expenses) }}</p>
          </div>
          <div class="card p-4">
            <div class="flex items-center gap-3 mb-2">
              <div
                class="w-10 h-10 rounded-lg flex items-center justify-center"
                :class="financeStore.euer.profit >= 0 ? 'bg-blue-500/20' : 'bg-orange-500/20'"
              >
                <DocumentChartBarIcon
                  class="w-5 h-5"
                  :class="financeStore.euer.profit >= 0 ? 'text-blue-400' : 'text-orange-400'"
                />
              </div>
              <span class="text-gray-400 text-sm">Gewinn / Verlust</span>
            </div>
            <p
              class="text-2xl font-bold"
              :class="financeStore.euer.profit >= 0 ? 'text-blue-400' : 'text-orange-400'"
            >
              {{ formatCurrency(financeStore.euer.profit) }}
            </p>
          </div>
        </div>

        <!-- Monthly breakdown table -->
        <div class="card overflow-hidden">
          <div class="px-6 py-4 border-b border-dark-700">
            <h3 class="font-semibold text-white">Monatsübersicht {{ selectedYear }}</h3>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead class="bg-dark-700">
                <tr>
                  <th class="px-4 py-3 text-left text-sm font-medium text-gray-400">Monat</th>
                  <th class="px-4 py-3 text-right text-sm font-medium text-green-400">Einnahmen</th>
                  <th class="px-4 py-3 text-right text-sm font-medium text-red-400">Ausgaben</th>
                  <th class="px-4 py-3 text-right text-sm font-medium text-gray-400">Gewinn/Verlust</th>
                  <th class="px-4 py-3 text-left text-sm font-medium text-gray-400 w-48">Verlauf</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-dark-700">
                <tr
                  v-for="month in financeStore.euer.months"
                  :key="month.month"
                  class="hover:bg-dark-700/30 transition-colors"
                  :class="{ 'opacity-40': month.income === 0 && month.expenses === 0 }"
                >
                  <td class="px-4 py-3 text-sm text-white">{{ month.month_name }}</td>
                  <td class="px-4 py-3 text-sm text-right text-green-400">
                    {{ month.income > 0 ? formatCurrency(month.income) : '-' }}
                  </td>
                  <td class="px-4 py-3 text-sm text-right text-red-400">
                    {{ month.expenses > 0 ? formatCurrency(month.expenses) : '-' }}
                  </td>
                  <td
                    class="px-4 py-3 text-sm text-right font-medium"
                    :class="month.profit >= 0 ? 'text-blue-400' : 'text-orange-400'"
                  >
                    {{ month.income > 0 || month.expenses > 0 ? formatCurrency(month.profit) : '-' }}
                  </td>
                  <td class="px-4 py-3">
                    <div class="flex gap-1 h-4 items-center">
                      <div
                        class="h-3 rounded-sm bg-green-500/60"
                        :style="{ width: barWidth(month.income, Math.max(...financeStore.euer.months.map(m => Math.max(m.income, m.expenses)))) }"
                      ></div>
                      <div
                        class="h-3 rounded-sm bg-red-500/60"
                        :style="{ width: barWidth(month.expenses, Math.max(...financeStore.euer.months.map(m => Math.max(m.income, m.expenses)))) }"
                      ></div>
                    </div>
                  </td>
                </tr>
              </tbody>
              <tfoot class="bg-dark-700/50">
                <tr>
                  <td class="px-4 py-3 text-sm font-bold text-white">Gesamt</td>
                  <td class="px-4 py-3 text-sm font-bold text-right text-green-400">{{ formatCurrency(financeStore.euer.total_income) }}</td>
                  <td class="px-4 py-3 text-sm font-bold text-right text-red-400">{{ formatCurrency(financeStore.euer.total_expenses) }}</td>
                  <td
                    class="px-4 py-3 text-sm font-bold text-right"
                    :class="financeStore.euer.profit >= 0 ? 'text-blue-400' : 'text-orange-400'"
                  >
                    {{ formatCurrency(financeStore.euer.profit) }}
                  </td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

        <!-- Expenses by category -->
        <div v-if="financeStore.euer.expenses_by_category.length > 0" class="card p-6">
          <h3 class="text-white font-semibold mb-4">Ausgaben nach Kategorie {{ selectedYear }}</h3>
          <div class="space-y-3">
            <div
              v-for="cat in financeStore.euer.expenses_by_category"
              :key="cat.name"
              class="flex items-center gap-3"
            >
              <span class="w-3 h-3 rounded-full flex-shrink-0" :style="{ backgroundColor: cat.color || '#6B7280' }"></span>
              <span class="text-sm text-gray-300 w-40 truncate">{{ cat.name || 'Ohne Kategorie' }}</span>
              <div class="flex-1 h-2 bg-dark-600 rounded-full overflow-hidden">
                <div
                  class="h-full rounded-sm transition-all duration-500 bg-red-500/70"
                  :style="{ width: `${financeStore.euer.total_expenses > 0 ? (cat.total / financeStore.euer.total_expenses) * 100 : 0}%` }"
                ></div>
              </div>
              <span class="text-sm font-medium text-white w-24 text-right">{{ formatCurrency(cat.total) }}</span>
            </div>
          </div>
        </div>
      </template>

      <div v-else class="card p-12 text-center">
        <DocumentChartBarIcon class="w-12 h-12 text-gray-600 mx-auto mb-3" />
        <p class="text-gray-500">Noch keine Daten für {{ selectedYear }}</p>
      </div>
    </template>

    <!-- ═══ MODALS ═══ -->

    <!-- Expense Form Modal -->
    <Teleport to="body">
      <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100"
                  leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
        <div v-if="showExpenseForm" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4" @click.self="showExpenseForm = false">
          <div class="bg-dark-800 border border-dark-700 rounded-xl shadow-2xl w-full max-w-md">
            <div class="flex items-center justify-between px-6 py-4 border-b border-dark-700">
              <h3 class="text-lg font-semibold text-white">{{ editingExpense ? 'Ausgabe bearbeiten' : 'Neue Ausgabe' }}</h3>
              <button @click="showExpenseForm = false" class="text-gray-400 hover:text-white"><XMarkIcon class="w-5 h-5" /></button>
            </div>
            <div class="p-6 space-y-4">
              <div>
                <label class="label">Beschreibung *</label>
                <input v-model="expenseForm.description" type="text" class="input" placeholder="z.B. Bürobedarf, Fahrtkosten..." />
              </div>
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="label">Betrag *</label>
                  <input v-model="expenseForm.amount" type="number" step="0.01" min="0" class="input" placeholder="0.00" />
                </div>
                <div>
                  <label class="label">Datum</label>
                  <input v-model="expenseForm.expense_date" type="date" class="input" />
                </div>
              </div>
              <div>
                <label class="label">Kategorie</label>
                <select v-model="expenseForm.category_id" class="input">
                  <option value="">Ohne Kategorie</option>
                  <option v-for="cat in financeStore.categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                </select>
              </div>
              <div>
                <label class="label">Beleg / Quittung</label>
                <div class="flex items-center gap-2">
                  <label class="flex-1 flex items-center gap-2 cursor-pointer border border-dashed border-dark-600 hover:border-primary-500 rounded-lg p-3 transition-colors">
                    <PaperClipIcon class="w-4 h-4 text-gray-400" />
                    <span class="text-sm text-gray-400">
                      {{ receiptFile ? receiptFile.name : (expenseForm.receipt_file_id ? 'Beleg vorhanden (ersetzen...)' : 'Datei auswählen...') }}
                    </span>
                    <input type="file" class="hidden" accept="image/*,.pdf" @change="e => receiptFile = e.target.files[0]" />
                  </label>
                  <button v-if="receiptFile" @click="receiptFile = null; expenseForm.receipt_file_id = null" class="p-2 text-gray-500 hover:text-red-400">
                    <XMarkIcon class="w-4 h-4" />
                  </button>
                </div>
              </div>
              <div>
                <label class="label">Notizen</label>
                <textarea v-model="expenseForm.notes" class="input" rows="2" placeholder="Optional"></textarea>
              </div>
              <div class="flex gap-3 pt-2">
                <button @click="showExpenseForm = false" class="btn-secondary flex-1">Abbrechen</button>
                <button
                  @click="saveExpense"
                  class="btn-primary flex-1"
                  :disabled="!expenseForm.description || !expenseForm.amount || receiptUploading"
                >
                  <span v-if="receiptUploading">Hochladen...</span>
                  <span v-else>{{ editingExpense ? 'Speichern' : 'Hinzufügen' }}</span>
                </button>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Expense Category Modal -->
    <Teleport to="body">
      <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100"
                  leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
        <div v-if="showCategoryForm" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4" @click.self="showCategoryForm = false">
          <div class="bg-dark-800 border border-dark-700 rounded-xl shadow-2xl w-full max-w-md">
            <div class="flex items-center justify-between px-6 py-4 border-b border-dark-700">
              <h3 class="text-lg font-semibold text-white">Ausgaben-Kategorien</h3>
              <button @click="showCategoryForm = false" class="text-gray-400 hover:text-white"><XMarkIcon class="w-5 h-5" /></button>
            </div>
            <div class="p-6 space-y-4">
              <div class="space-y-2 max-h-48 overflow-y-auto">
                <div v-for="cat in financeStore.categories" :key="cat.id" class="flex items-center gap-3 p-2 rounded-lg bg-dark-700/50">
                  <span class="w-3 h-3 rounded-full" :style="{ backgroundColor: cat.color }"></span>
                  <span class="flex-1 text-sm text-white">{{ cat.name }}</span>
                  <button @click="deleteCategory(cat)" class="p-1 text-gray-500 hover:text-red-400"><TrashIcon class="w-3.5 h-3.5" /></button>
                </div>
                <p v-if="financeStore.categories.length === 0" class="text-gray-500 text-sm text-center py-2">Keine Kategorien</p>
              </div>
              <div class="border-t border-dark-700 pt-4">
                <p class="text-sm font-medium text-gray-300 mb-3">Neue Kategorie</p>
                <input v-model="categoryForm.name" type="text" class="input mb-3" placeholder="Name..." />
                <div class="flex gap-2 mb-3 flex-wrap">
                  <button
                    v-for="color in colorOptions" :key="color"
                    @click="categoryForm.color = color"
                    class="w-7 h-7 rounded-full border-2 transition-all"
                    :style="{ backgroundColor: color }"
                    :class="categoryForm.color === color ? 'border-white scale-110' : 'border-transparent'"
                  ></button>
                </div>
                <button @click="saveCategory" class="btn-primary w-full" :disabled="!categoryForm.name.trim()">
                  Kategorie erstellen
                </button>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Income Form Modal -->
    <Teleport to="body">
      <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100"
                  leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
        <div v-if="showIncomeForm" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4" @click.self="showIncomeForm = false">
          <div class="bg-dark-800 border border-dark-700 rounded-xl shadow-2xl w-full max-w-md">
            <div class="flex items-center justify-between px-6 py-4 border-b border-dark-700">
              <h3 class="text-lg font-semibold text-white">{{ editingIncome ? 'Einnahme bearbeiten' : 'Neue Einnahme' }}</h3>
              <button @click="showIncomeForm = false" class="text-gray-400 hover:text-white"><XMarkIcon class="w-5 h-5" /></button>
            </div>
            <div class="p-6 space-y-4">
              <div>
                <label class="label">Beschreibung *</label>
                <input v-model="incomeForm.description" type="text" class="input" placeholder="z.B. Kundenzahlung, Projekt XY..." />
              </div>
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="label">Betrag *</label>
                  <input v-model="incomeForm.amount" type="number" step="0.01" min="0" class="input" placeholder="0.00" />
                </div>
                <div>
                  <label class="label">Datum</label>
                  <input v-model="incomeForm.income_date" type="date" class="input" />
                </div>
              </div>
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="label">Kategorie</label>
                  <select v-model="incomeForm.category_id" class="input">
                    <option value="">Ohne Kategorie</option>
                    <option v-for="cat in financeStore.incomeCategories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                  </select>
                </div>
                <div>
                  <label class="label">Quelle</label>
                  <input v-model="incomeForm.source" type="text" class="input" placeholder="z.B. PayPal, Bar..." />
                </div>
              </div>
              <div>
                <label class="label">Beleg / Nachweis</label>
                <div class="flex items-center gap-2">
                  <label class="flex-1 flex items-center gap-2 cursor-pointer border border-dashed border-dark-600 hover:border-primary-500 rounded-lg p-3 transition-colors">
                    <PaperClipIcon class="w-4 h-4 text-gray-400" />
                    <span class="text-sm text-gray-400">
                      {{ incomeReceiptFile ? incomeReceiptFile.name : (incomeForm.receipt_file_id ? 'Beleg vorhanden (ersetzen...)' : 'Datei auswählen...') }}
                    </span>
                    <input type="file" class="hidden" accept="image/*,.pdf" @change="e => incomeReceiptFile = e.target.files[0]" />
                  </label>
                  <button v-if="incomeReceiptFile" @click="incomeReceiptFile = null; incomeForm.receipt_file_id = null" class="p-2 text-gray-500 hover:text-red-400">
                    <XMarkIcon class="w-4 h-4" />
                  </button>
                </div>
              </div>
              <div>
                <label class="label">Notizen</label>
                <textarea v-model="incomeForm.notes" class="input" rows="2" placeholder="Optional"></textarea>
              </div>
              <div class="flex gap-3 pt-2">
                <button @click="showIncomeForm = false" class="btn-secondary flex-1">Abbrechen</button>
                <button
                  @click="saveIncome"
                  class="btn-primary flex-1"
                  :disabled="!incomeForm.description || !incomeForm.amount || incomeReceiptUploading"
                >
                  <span v-if="incomeReceiptUploading">Hochladen...</span>
                  <span v-else>{{ editingIncome ? 'Speichern' : 'Hinzufügen' }}</span>
                </button>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Income Category Modal -->
    <Teleport to="body">
      <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100"
                  leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
        <div v-if="showIncomeCategoryForm" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4" @click.self="showIncomeCategoryForm = false">
          <div class="bg-dark-800 border border-dark-700 rounded-xl shadow-2xl w-full max-w-md">
            <div class="flex items-center justify-between px-6 py-4 border-b border-dark-700">
              <h3 class="text-lg font-semibold text-white">Einnahmen-Kategorien</h3>
              <button @click="showIncomeCategoryForm = false" class="text-gray-400 hover:text-white"><XMarkIcon class="w-5 h-5" /></button>
            </div>
            <div class="p-6 space-y-4">
              <div class="space-y-2 max-h-48 overflow-y-auto">
                <div v-for="cat in financeStore.incomeCategories" :key="cat.id" class="flex items-center gap-3 p-2 rounded-lg bg-dark-700/50">
                  <span class="w-3 h-3 rounded-full" :style="{ backgroundColor: cat.color }"></span>
                  <span class="flex-1 text-sm text-white">{{ cat.name }}</span>
                  <button @click="deleteIncomeCategory(cat)" class="p-1 text-gray-500 hover:text-red-400"><TrashIcon class="w-3.5 h-3.5" /></button>
                </div>
                <p v-if="financeStore.incomeCategories.length === 0" class="text-gray-500 text-sm text-center py-2">Keine Kategorien</p>
              </div>
              <div class="border-t border-dark-700 pt-4">
                <p class="text-sm font-medium text-gray-300 mb-3">Neue Kategorie</p>
                <input v-model="incomeCategoryForm.name" type="text" class="input mb-3" placeholder="Name..." />
                <div class="flex gap-2 mb-3 flex-wrap">
                  <button
                    v-for="color in incomeColorOptions" :key="color"
                    @click="incomeCategoryForm.color = color"
                    class="w-7 h-7 rounded-full border-2 transition-all"
                    :style="{ backgroundColor: color }"
                    :class="incomeCategoryForm.color === color ? 'border-white scale-110' : 'border-transparent'"
                  ></button>
                </div>
                <button @click="saveIncomeCategory" class="btn-primary w-full" :disabled="!incomeCategoryForm.name.trim()">
                  Kategorie erstellen
                </button>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>
