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
} from '@heroicons/vue/24/outline'
import { useFinanceStore } from '../stores/financeStore.js'

const financeStore = useFinanceStore()

// Date range
const selectedMonth = ref(new Date().toISOString().slice(0, 7)) // YYYY-MM

const fromDate = computed(() => `${selectedMonth.value}-01`)
const toDate = computed(() => {
  const [year, month] = selectedMonth.value.split('-')
  const lastDay = new Date(parseInt(year), parseInt(month), 0).getDate()
  return `${selectedMonth.value}-${String(lastDay).padStart(2, '0')}`
})

// Form state
const showExpenseForm = ref(false)
const showCategoryForm = ref(false)
const editingExpense = ref(null)

const expenseForm = ref({
  description: '',
  amount: '',
  expense_date: new Date().toISOString().split('T')[0],
  category_id: '',
  currency: 'EUR',
  notes: '',
  is_recurring: false,
})

const categoryForm = ref({ name: '', color: '#3B82F6' })

const colorOptions = [
  '#3B82F6', '#10B981', '#F59E0B', '#EF4444',
  '#8B5CF6', '#EC4899', '#06B6D4', '#84CC16',
]

// Computed
const totalThisMonth = computed(() => financeStore.summary?.total || 0)

const topCategories = computed(() => {
  if (!financeStore.summary) return []
  return (financeStore.summary.by_category || [])
    .filter(c => c.total > 0)
    .slice(0, 5)
})

function formatCurrency(amount) {
  return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(amount)
}

function getCategoryName(categoryId) {
  const cat = financeStore.categories.find(c => c.id === categoryId)
  return cat?.name || 'Ohne Kategorie'
}

function getCategoryColor(categoryId) {
  const cat = financeStore.categories.find(c => c.id === categoryId)
  return cat?.color || '#6B7280'
}

// Expense form
function openCreateExpense() {
  editingExpense.value = null
  expenseForm.value = {
    description: '',
    amount: '',
    expense_date: new Date().toISOString().split('T')[0],
    category_id: '',
    currency: 'EUR',
    notes: '',
    is_recurring: false,
  }
  showExpenseForm.value = true
}

function openEditExpense(expense) {
  editingExpense.value = expense
  expenseForm.value = {
    description: expense.description,
    amount: expense.amount,
    expense_date: expense.expense_date,
    category_id: expense.category_id || '',
    currency: expense.currency,
    notes: expense.notes || '',
    is_recurring: expense.is_recurring,
  }
  showExpenseForm.value = true
}

async function saveExpense() {
  if (!expenseForm.value.description || !expenseForm.value.amount) return

  const data = {
    ...expenseForm.value,
    amount: parseFloat(expenseForm.value.amount),
    category_id: expenseForm.value.category_id || null,
  }

  if (editingExpense.value) {
    await financeStore.updateExpense(editingExpense.value.id, data)
  } else {
    await financeStore.createExpense(data)
  }

  showExpenseForm.value = false
  await loadData()
}

async function deleteExpense(expense) {
  if (!confirm(`"${expense.description}" wirklich löschen?`)) return
  await financeStore.deleteExpense(expense.id)
  await financeStore.fetchSummary({ from: fromDate.value, to: toDate.value })
}

// Category form
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

async function loadData() {
  await Promise.all([
    financeStore.fetchExpenses({ from: fromDate.value, to: toDate.value }),
    financeStore.fetchSummary({ from: fromDate.value, to: toDate.value }),
    financeStore.fetchCategories(),
  ])
}

onMounted(loadData)
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between flex-wrap gap-3">
      <div>
        <h1 class="text-2xl font-bold text-white">Ausgaben-Tracker</h1>
        <p class="text-gray-400 mt-1">{{ formatCurrency(totalThisMonth) }} in diesem Monat</p>
      </div>
      <div class="flex items-center gap-2">
        <input v-model="selectedMonth" type="month" class="input text-sm" @change="loadData" />
        <button @click="showCategoryForm = true" class="btn-secondary text-sm">
          Kategorien
        </button>
        <button @click="openCreateExpense" class="btn-primary text-sm">
          <PlusIcon class="w-4 h-4 mr-1" />
          Ausgabe
        </button>
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div class="card p-4">
        <div class="flex items-center gap-3 mb-2">
          <div class="w-10 h-10 rounded-lg bg-red-500/20 flex items-center justify-center">
            <ArrowTrendingDownIcon class="w-5 h-5 text-red-400" />
          </div>
          <span class="text-gray-400 text-sm">Gesamt</span>
        </div>
        <p class="text-2xl font-bold text-white">{{ formatCurrency(totalThisMonth) }}</p>
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
            :style="{ backgroundColor: getCategoryColor(expense.category_id) }"
          ></span>
          <div class="flex-1 min-w-0">
            <p class="text-sm text-white truncate">{{ expense.description }}</p>
            <p class="text-xs text-gray-500">{{ getCategoryName(expense.category_id) }} · {{ expense.expense_date }}</p>
          </div>
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

    <!-- Expense Form Modal -->
    <Teleport to="body">
      <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100"
                  leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
        <div v-if="showExpenseForm" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4" @click.self="showExpenseForm = false">
          <div class="bg-dark-800 border border-dark-700 rounded-xl shadow-2xl w-full max-w-md">
            <div class="flex items-center justify-between px-6 py-4 border-b border-dark-700">
              <h3 class="text-lg font-semibold text-white">{{ editingExpense ? 'Ausgabe bearbeiten' : 'Neue Ausgabe' }}</h3>
              <button @click="showExpenseForm = false" class="text-gray-400 hover:text-white">
                <XMarkIcon class="w-5 h-5" />
              </button>
            </div>
            <div class="p-6 space-y-4">
              <div>
                <label class="label">Beschreibung *</label>
                <input v-model="expenseForm.description" type="text" class="input" placeholder="z.B. Lebensmittel, Miete..." />
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
                  <option v-for="cat in financeStore.categories" :key="cat.id" :value="cat.id">
                    {{ cat.name }}
                  </option>
                </select>
              </div>
              <div>
                <label class="label">Notizen</label>
                <textarea v-model="expenseForm.notes" class="input" rows="2" placeholder="Optional"></textarea>
              </div>
              <div class="flex gap-3 pt-2">
                <button @click="showExpenseForm = false" class="btn-secondary flex-1">Abbrechen</button>
                <button @click="saveExpense" class="btn-primary flex-1" :disabled="!expenseForm.description || !expenseForm.amount">
                  {{ editingExpense ? 'Speichern' : 'Hinzufügen' }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Category Manager Modal -->
    <Teleport to="body">
      <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100"
                  leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
        <div v-if="showCategoryForm" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4" @click.self="showCategoryForm = false">
          <div class="bg-dark-800 border border-dark-700 rounded-xl shadow-2xl w-full max-w-md">
            <div class="flex items-center justify-between px-6 py-4 border-b border-dark-700">
              <h3 class="text-lg font-semibold text-white">Kategorien verwalten</h3>
              <button @click="showCategoryForm = false" class="text-gray-400 hover:text-white">
                <XMarkIcon class="w-5 h-5" />
              </button>
            </div>
            <div class="p-6 space-y-4">
              <!-- Existing categories -->
              <div class="space-y-2 max-h-48 overflow-y-auto">
                <div v-for="cat in financeStore.categories" :key="cat.id" class="flex items-center gap-3 p-2 rounded-lg bg-dark-700/50">
                  <span class="w-3 h-3 rounded-full" :style="{ backgroundColor: cat.color }"></span>
                  <span class="flex-1 text-sm text-white">{{ cat.name }}</span>
                  <button @click="deleteCategory(cat)" class="p-1 text-gray-500 hover:text-red-400">
                    <TrashIcon class="w-3.5 h-3.5" />
                  </button>
                </div>
                <p v-if="financeStore.categories.length === 0" class="text-gray-500 text-sm text-center py-2">Keine Kategorien</p>
              </div>

              <!-- New category form -->
              <div class="border-t border-dark-700 pt-4">
                <p class="text-sm font-medium text-gray-300 mb-3">Neue Kategorie</p>
                <div class="flex gap-2 mb-3">
                  <input v-model="categoryForm.name" type="text" class="input flex-1" placeholder="Name..." />
                </div>
                <div class="flex gap-2 mb-3 flex-wrap">
                  <button
                    v-for="color in colorOptions"
                    :key="color"
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
  </div>
</template>
