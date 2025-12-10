<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import {
  ClipboardDocumentListIcon,
  CheckCircleIcon,
  XCircleIcon,
  ClockIcon,
  ExclamationTriangleIcon,
  PlusIcon,
  FolderIcon,
  ChevronDownIcon,
  ChevronRightIcon,
  UserIcon,
  TrashIcon,
  ArrowPathIcon,
  SignalIcon,
  HandThumbUpIcon,
  HandThumbDownIcon,
  QuestionMarkCircleIcon,
} from '@heroicons/vue/24/outline'
import { CheckCircleIcon as CheckCircleIconSolid } from '@heroicons/vue/24/solid'
import axios from 'axios'

const route = useRoute()

// State
const checklist = ref(null)
const isLoading = ref(true)
const error = ref(null)
const expandedCategories = ref({})
const testerName = ref(localStorage.getItem('checklist_tester_name') || '')
const showAddEntryModal = ref(false)
const selectedItem = ref(null)
const showAddItemModal = ref(false)
const addItemCategoryId = ref(null)
const refreshInterval = ref(null)

// Sync state
const isSyncing = ref(false)
const lastSyncTime = ref(null)
const syncError = ref(false)
const newEntryIds = ref(new Set())
const currentVersion = ref(null)

const newEntry = ref({
  status: 'passed',
  notes: '',
})

const newItem = ref({
  title: '',
  description: '',
  category_id: null,
  required_testers: 1,
})

// Computed
const token = computed(() => route.params.token)

const itemsByCategory = computed(() => {
  if (!checklist.value) return []

  const categories = checklist.value.categories || []
  const items = checklist.value.items || []

  const result = categories.map(cat => ({
    ...cat,
    items: items.filter(item => item.category_id === cat.id),
  }))

  const uncategorized = items.filter(item => !item.category_id)
  if (uncategorized.length > 0) {
    result.push({
      id: null,
      name: 'Allgemein',
      items: uncategorized,
    })
  }

  return result
})

const lastSyncTimeFormatted = computed(() => {
  if (!lastSyncTime.value) return ''
  const now = new Date()
  const diff = Math.floor((now - lastSyncTime.value) / 1000)
  if (diff < 5) return 'Gerade eben'
  if (diff < 60) return `vor ${diff}s`
  return `vor ${Math.floor(diff / 60)}m`
})

// API Functions
async function loadChecklist(silent = false) {
  if (!silent) {
    isLoading.value = true
  }
  isSyncing.value = true
  syncError.value = false

  try {
    const response = await axios.get(`/api/v1/checklists/public/${token.value}`)
    const newData = response.data.data

    // Detect new entries for animation
    if (checklist.value && newData.items) {
      const oldEntryIds = new Set()
      checklist.value.items?.forEach(item => {
        item.entries?.forEach(entry => oldEntryIds.add(entry.id))
      })

      newData.items.forEach(item => {
        item.entries?.forEach(entry => {
          if (!oldEntryIds.has(entry.id)) {
            newEntryIds.value.add(entry.id)
            setTimeout(() => {
              newEntryIds.value.delete(entry.id)
            }, 2000)
          }
        })
      })
    }

    checklist.value = newData

    // Initialize expanded categories
    const categories = checklist.value.categories || []
    categories.forEach(cat => {
      if (expandedCategories.value[cat.id] === undefined) {
        expandedCategories.value[cat.id] = true
      }
    })
    expandedCategories.value[null] = true

    lastSyncTime.value = new Date()
  } catch (err) {
    if (!silent) {
      if (err.response?.status === 404) {
        error.value = 'Checkliste nicht gefunden'
      } else if (err.response?.status === 403) {
        error.value = err.response.data?.error || 'Checkliste nicht verfügbar'
      } else {
        error.value = 'Ein Fehler ist aufgetreten'
      }
    } else {
      syncError.value = true
    }
  } finally {
    isLoading.value = false
    isSyncing.value = false
  }
}

// Check for updates using version endpoint
async function checkForUpdates() {
  if (!checklist.value) return

  try {
    const response = await axios.get(`/api/v1/checklists/public/${token.value}/updates`, {
      params: { since: lastSyncTime.value?.toISOString() }
    })
    const { version } = response.data.data

    // If version changed, do a full reload
    if (currentVersion.value && version !== currentVersion.value) {
      await loadChecklist(true)
    }
    currentVersion.value = version
    lastSyncTime.value = new Date()
    syncError.value = false
  } catch (err) {
    syncError.value = true
  }
}

// Quick test - one click to mark as passed
async function quickTest(item) {
  if (checklist.value.require_name && !testerName.value.trim()) {
    alert('Bitte gib zuerst deinen Namen ein')
    return
  }

  try {
    const response = await axios.post(`/api/v1/checklists/public/${token.value}/entries`, {
      item_id: item.id,
      tester_name: testerName.value.trim() || 'Anonym',
      status: 'passed',
      notes: '',
    })

    if (testerName.value.trim()) {
      localStorage.setItem('checklist_tester_name', testerName.value.trim())
    }

    // Update local state
    item.entries = item.entries || []
    item.entries.unshift(response.data.data)
    item.passed_count = (item.passed_count || 0) + 1
    item.entry_count = (item.entry_count || 0) + 1
    recalculateProgress()

    // Highlight the new entry
    newEntryIds.value.add(response.data.data.id)
    setTimeout(() => {
      newEntryIds.value.delete(response.data.data.id)
    }, 2000)
  } catch (err) {
    alert(err.response?.data?.error || 'Fehler beim Speichern')
  }
}

// Quick fail - one click to mark as failed
async function quickFail(item) {
  if (checklist.value.require_name && !testerName.value.trim()) {
    alert('Bitte gib zuerst deinen Namen ein')
    return
  }

  try {
    const response = await axios.post(`/api/v1/checklists/public/${token.value}/entries`, {
      item_id: item.id,
      tester_name: testerName.value.trim() || 'Anonym',
      status: 'failed',
      notes: '',
    })

    if (testerName.value.trim()) {
      localStorage.setItem('checklist_tester_name', testerName.value.trim())
    }

    item.entries = item.entries || []
    item.entries.unshift(response.data.data)
    item.failed_count = (item.failed_count || 0) + 1
    item.entry_count = (item.entry_count || 0) + 1
    recalculateProgress()

    newEntryIds.value.add(response.data.data.id)
    setTimeout(() => {
      newEntryIds.value.delete(response.data.data.id)
    }, 2000)
  } catch (err) {
    alert(err.response?.data?.error || 'Fehler beim Speichern')
  }
}

// Quick uncertain - one click to mark as uncertain
async function quickUncertain(item) {
  if (checklist.value.require_name && !testerName.value.trim()) {
    alert('Bitte gib zuerst deinen Namen ein')
    return
  }

  try {
    const response = await axios.post(`/api/v1/checklists/public/${token.value}/entries`, {
      item_id: item.id,
      tester_name: testerName.value.trim() || 'Anonym',
      status: 'uncertain',
      notes: '',
    })

    if (testerName.value.trim()) {
      localStorage.setItem('checklist_tester_name', testerName.value.trim())
    }

    item.entries = item.entries || []
    item.entries.unshift(response.data.data)
    item.uncertain_count = (item.uncertain_count || 0) + 1
    item.entry_count = (item.entry_count || 0) + 1
    recalculateProgress()

    newEntryIds.value.add(response.data.data.id)
    setTimeout(() => {
      newEntryIds.value.delete(response.data.data.id)
    }, 2000)
  } catch (err) {
    alert(err.response?.data?.error || 'Fehler beim Speichern')
  }
}

async function addEntry() {
  if (checklist.value.require_name && !testerName.value.trim()) {
    alert('Bitte gib deinen Namen ein')
    return
  }

  try {
    const response = await axios.post(`/api/v1/checklists/public/${token.value}/entries`, {
      item_id: selectedItem.value.id,
      tester_name: testerName.value.trim() || 'Anonym',
      status: newEntry.value.status,
      notes: newEntry.value.notes,
    })

    if (testerName.value.trim()) {
      localStorage.setItem('checklist_tester_name', testerName.value.trim())
    }

    const item = checklist.value.items.find(i => i.id === selectedItem.value.id)
    if (item) {
      item.entries = item.entries || []
      item.entries.unshift(response.data.data)

      if (newEntry.value.status === 'passed') item.passed_count++
      else if (newEntry.value.status === 'failed') item.failed_count++
      else if (newEntry.value.status === 'in_progress') item.in_progress_count++
      item.entry_count++

      recalculateProgress()
    }

    showAddEntryModal.value = false
    selectedItem.value = null
    newEntry.value = { status: 'passed', notes: '' }
  } catch (err) {
    alert(err.response?.data?.error || 'Fehler beim Speichern')
  }
}

async function updateEntryStatus(entry, newStatus) {
  try {
    await axios.put(`/api/v1/checklists/public/${token.value}/entries/${entry.id}`, {
      status: newStatus,
    })

    const oldStatus = entry.status
    entry.status = newStatus

    for (const cat of itemsByCategory.value) {
      for (const item of cat.items) {
        const foundEntry = item.entries?.find(e => e.id === entry.id)
        if (foundEntry) {
          if (oldStatus === 'passed') item.passed_count--
          else if (oldStatus === 'failed') item.failed_count--
          else if (oldStatus === 'in_progress') item.in_progress_count--

          if (newStatus === 'passed') item.passed_count++
          else if (newStatus === 'failed') item.failed_count++
          else if (newStatus === 'in_progress') item.in_progress_count++

          recalculateProgress()
          break
        }
      }
    }
  } catch (err) {
    alert('Fehler beim Aktualisieren')
  }
}

async function deleteEntry(entry, item) {
  if (!confirm('Eintrag wirklich löschen?')) return

  try {
    await axios.delete(`/api/v1/checklists/public/${token.value}/entries/${entry.id}`)

    item.entries = item.entries.filter(e => e.id !== entry.id)
    if (entry.status === 'passed') item.passed_count--
    else if (entry.status === 'failed') item.failed_count--
    else if (entry.status === 'in_progress') item.in_progress_count--
    item.entry_count--

    recalculateProgress()
  } catch (err) {
    alert('Fehler beim Löschen')
  }
}

async function addItem() {
  if (!newItem.value.title.trim()) {
    alert('Titel ist erforderlich')
    return
  }

  try {
    const response = await axios.post(`/api/v1/checklists/public/${token.value}/items`, {
      ...newItem.value,
      added_by: testerName.value.trim() || 'Anonym',
    })

    checklist.value.items.push({
      ...response.data.data,
      entries: [],
      passed_count: 0,
      failed_count: 0,
      in_progress_count: 0,
      entry_count: 0,
    })

    showAddItemModal.value = false
    addItemCategoryId.value = null
    newItem.value = { title: '', description: '', category_id: null, required_testers: 1 }
  } catch (err) {
    alert(err.response?.data?.error || 'Fehler beim Erstellen')
  }
}

function recalculateProgress() {
  if (!checklist.value) return

  let totalRequired = 0
  let totalCompleted = 0

  for (const item of checklist.value.items) {
    if (item.required_testers === -1) {
      // Unlimited: count as 1 required, completed if at least 1 passed
      totalRequired += 1
      totalCompleted += (item.passed_count || 0) > 0 ? 1 : 0
    } else {
      totalRequired += item.required_testers || 1
      totalCompleted += Math.min(item.passed_count || 0, item.required_testers || 1)
    }
  }

  checklist.value.progress = {
    total_items: checklist.value.items.length,
    total_required: totalRequired,
    total_completed: totalCompleted,
    percentage: totalRequired > 0 ? Math.round((totalCompleted / totalRequired) * 100) : 0,
  }
}

function openAddEntry(item) {
  selectedItem.value = item
  showAddEntryModal.value = true
}

function openAddItemInCategory(categoryId) {
  addItemCategoryId.value = categoryId
  newItem.value.category_id = categoryId
  showAddItemModal.value = true
}

function toggleCategory(categoryId) {
  expandedCategories.value[categoryId] = !expandedCategories.value[categoryId]
}

function getStatusColor(status) {
  switch (status) {
    case 'passed': return 'text-green-400 bg-green-400/10 border-green-400/30'
    case 'failed': return 'text-red-400 bg-red-400/10 border-red-400/30'
    case 'in_progress': return 'text-blue-400 bg-blue-400/10 border-blue-400/30'
    case 'blocked': return 'text-orange-400 bg-orange-400/10 border-orange-400/30'
    case 'uncertain': return 'text-yellow-400 bg-yellow-400/10 border-yellow-400/30'
    default: return 'text-gray-400 bg-gray-400/10 border-gray-400/30'
  }
}

function getStatusIcon(status) {
  switch (status) {
    case 'passed': return CheckCircleIcon
    case 'failed': return XCircleIcon
    case 'in_progress': return ClockIcon
    case 'blocked': return ExclamationTriangleIcon
    case 'uncertain': return QuestionMarkCircleIcon
    default: return ClockIcon
  }
}

function getStatusLabel(status) {
  switch (status) {
    case 'passed': return 'OK'
    case 'failed': return 'Fehler'
    case 'in_progress': return 'In Arbeit'
    case 'blocked': return 'Blockiert'
    case 'uncertain': return 'Unsicher'
    default: return 'Offen'
  }
}

function getItemProgress(item) {
  const isUnlimited = item.required_testers === -1
  const required = isUnlimited ? null : (item.required_testers || 1)
  const passedCount = item.passed_count || 0
  const completed = isUnlimited ? passedCount : Math.min(passedCount, required)

  return {
    completed,
    required,
    isUnlimited,
    percentage: isUnlimited ? (passedCount > 0 ? 100 : 0) : Math.round((completed / required) * 100),
    isComplete: isUnlimited ? passedCount > 0 : completed >= required,
  }
}

function formatDate(dateStr) {
  if (!dateStr) return ''
  return new Date(dateStr).toLocaleDateString('de-DE', {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function isNewEntry(entryId) {
  return newEntryIds.value.has(entryId)
}

// Smart sync - use version checking
function startAutoRefresh() {
  // Initial full load, then version checks
  refreshInterval.value = setInterval(() => {
    checkForUpdates()
  }, 3000)
}

function manualRefresh() {
  loadChecklist(true)
}

// Initialize
onMounted(() => {
  loadChecklist()
  startAutoRefresh()
})

onUnmounted(() => {
  if (refreshInterval.value) {
    clearInterval(refreshInterval.value)
  }
})
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900 py-8 px-4">
    <div class="max-w-4xl mx-auto">
      <!-- Loading -->
      <div v-if="isLoading" class="bg-gray-800/80 backdrop-blur-sm rounded-2xl border border-gray-700/50 p-12 text-center">
        <div class="w-12 h-12 border-3 border-indigo-500 border-t-transparent rounded-full animate-spin mx-auto"></div>
        <p class="text-gray-400 mt-4">Lade Checkliste...</p>
      </div>

      <!-- Error -->
      <div v-else-if="error" class="bg-gray-800/80 backdrop-blur-sm rounded-2xl border border-gray-700/50 p-12 text-center">
        <ExclamationTriangleIcon class="w-16 h-16 mx-auto text-red-500 mb-4" />
        <h2 class="text-xl font-semibold text-white mb-2">Nicht verfügbar</h2>
        <p class="text-gray-400">{{ error }}</p>
      </div>

      <!-- Checklist -->
      <template v-else-if="checklist">
        <!-- Sync Status Bar -->
        <div class="flex items-center justify-between mb-4 px-2">
          <div class="flex items-center gap-2">
            <span
              class="w-2 h-2 rounded-full"
              :class="syncError ? 'bg-red-500' : 'bg-green-500 animate-pulse'"
            ></span>
            <span class="text-xs text-gray-400">
              {{ syncError ? 'Offline' : 'Live' }}
            </span>
            <span v-if="lastSyncTime" class="text-xs text-gray-500">
              · {{ lastSyncTimeFormatted }}
            </span>
          </div>
          <button
            @click="manualRefresh"
            class="flex items-center gap-1 text-xs text-gray-400 hover:text-white transition-colors"
            :class="{ 'animate-spin': isSyncing }"
          >
            <ArrowPathIcon class="w-4 h-4" />
          </button>
        </div>

        <!-- Header -->
        <div class="bg-gray-800/80 backdrop-blur-sm rounded-2xl border border-gray-700/50 p-6 mb-6">
          <div class="flex items-start justify-between gap-4">
            <div>
              <h1 class="text-2xl font-bold text-white">{{ checklist.title }}</h1>
              <p v-if="checklist.description" class="text-gray-400 mt-2">{{ checklist.description }}</p>
              <p class="text-gray-500 text-sm mt-2">von {{ checklist.owner_name }}</p>
            </div>
            <ClipboardDocumentListIcon class="w-10 h-10 text-indigo-400 flex-shrink-0" />
          </div>

          <!-- Progress -->
          <div class="mt-6">
            <div class="flex items-center justify-between mb-2">
              <span class="text-gray-400 text-sm">Fortschritt</span>
              <span class="text-white font-medium">
                {{ checklist.progress.total_completed }} / {{ checklist.progress.total_required }}
              </span>
            </div>
            <div class="w-full h-4 bg-gray-700 rounded-full overflow-hidden">
              <div
                class="h-full bg-gradient-to-r from-indigo-500 to-purple-500 rounded-full transition-all duration-500"
                :style="{ width: checklist.progress.percentage + '%' }"
              ></div>
            </div>
            <div class="text-right mt-1">
              <span class="text-indigo-400 font-bold">{{ checklist.progress.percentage }}%</span>
            </div>
          </div>
        </div>

        <!-- Your Name -->
        <div class="bg-gray-800/80 backdrop-blur-sm rounded-2xl border border-gray-700/50 p-4 mb-6">
          <div class="flex items-center gap-3">
            <UserIcon class="w-5 h-5 text-gray-400" />
            <input
              v-model="testerName"
              type="text"
              :placeholder="checklist.require_name ? 'Dein Name (erforderlich) *' : 'Dein Name (optional)'"
              class="flex-1 px-3 py-2 bg-gray-700/50 border border-gray-600 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500"
              @change="testerName && localStorage.setItem('checklist_tester_name', testerName)"
            />
          </div>
        </div>

        <!-- Add Item Button (if allowed) -->
        <div v-if="checklist.allow_add_items" class="mb-6">
          <button
            @click="showAddItemModal = true"
            class="flex items-center gap-2 px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg text-white transition-colors"
          >
            <PlusIcon class="w-5 h-5" />
            <span>Testpunkt hinzufügen</span>
          </button>
        </div>

        <!-- Items by Category -->
        <div class="space-y-4">
          <div
            v-for="category in itemsByCategory"
            :key="category.id || 'uncategorized'"
            class="bg-gray-800/80 backdrop-blur-sm rounded-2xl border border-gray-700/50 overflow-hidden"
          >
            <!-- Category Header -->
            <div
              class="flex items-center justify-between p-4 bg-gray-700/30"
            >
              <div class="flex items-center gap-3 cursor-pointer" @click="toggleCategory(category.id)">
                <ChevronDownIcon
                  v-if="expandedCategories[category.id]"
                  class="w-5 h-5 text-gray-400 transition-transform"
                />
                <ChevronRightIcon v-else class="w-5 h-5 text-gray-400" />
                <FolderIcon class="w-5 h-5 text-indigo-400" />
                <span class="text-white font-medium">{{ category.name }}</span>
                <span class="text-gray-500 text-sm">({{ category.items.length }})</span>
              </div>
              <button
                v-if="checklist.allow_add_items"
                @click.stop="openAddItemInCategory(category.id)"
                class="p-1.5 bg-gray-600 hover:bg-gray-500 rounded-lg text-white transition-colors"
                title="Testpunkt in dieser Kategorie hinzufügen"
              >
                <PlusIcon class="w-4 h-4" />
              </button>
            </div>

            <!-- Items -->
            <div v-if="expandedCategories[category.id]" class="divide-y divide-gray-700/50">
              <div v-if="category.items.length === 0" class="p-6 text-center text-gray-500">
                Keine Testpunkte
              </div>

              <div
                v-for="item in category.items"
                :key="item.id"
                class="p-4"
              >
                <div class="flex items-start justify-between gap-4">
                  <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-3 flex-wrap">
                      <h4 class="text-white font-medium">{{ item.title }}</h4>
                      <span
                        v-if="getItemProgress(item).isComplete"
                        class="px-2 py-0.5 text-xs rounded-full bg-green-500/20 text-green-400 flex items-center gap-1"
                      >
                        <CheckCircleIconSolid class="w-3 h-3" />
                        Fertig
                      </span>
                    </div>
                    <p v-if="item.description" class="text-gray-400 text-sm mt-1">{{ item.description }}</p>

                    <!-- Progress Bar -->
                    <div class="flex items-center gap-3 mt-3">
                      <div class="flex items-center gap-2 text-sm">
                        <div class="w-24 h-2 bg-gray-700 rounded-full overflow-hidden">
                          <div
                            class="h-full rounded-full transition-all duration-300"
                            :class="getItemProgress(item).isComplete ? 'bg-green-500' : 'bg-indigo-500'"
                            :style="{ width: getItemProgress(item).percentage + '%' }"
                          ></div>
                        </div>
                        <span class="text-gray-400 text-xs font-medium">
                          {{ getItemProgress(item).completed }}/{{ getItemProgress(item).isUnlimited ? '∞' : getItemProgress(item).required }}
                        </span>
                      </div>
                      <!-- Status counts -->
                      <div class="flex items-center gap-2">
                        <span v-if="item.passed_count > 0" class="flex items-center gap-1 text-green-400 text-xs">
                          <CheckCircleIcon class="w-4 h-4" />
                          {{ item.passed_count }}
                        </span>
                        <span v-if="item.uncertain_count > 0" class="flex items-center gap-1 text-yellow-400 text-xs">
                          <QuestionMarkCircleIcon class="w-4 h-4" />
                          {{ item.uncertain_count }}
                        </span>
                        <span v-if="item.failed_count > 0" class="flex items-center gap-1 text-red-400 text-xs">
                          <XCircleIcon class="w-4 h-4" />
                          {{ item.failed_count }}
                        </span>
                      </div>
                    </div>

                    <!-- Entries -->
                    <div v-if="item.entries && item.entries.length > 0" class="mt-3 space-y-1.5">
                      <div
                        v-for="entry in item.entries"
                        :key="entry.id"
                        class="flex items-center gap-2 p-2 bg-gray-700/30 rounded-lg text-sm transition-all duration-500"
                        :class="{ 'ring-2 ring-indigo-500 ring-opacity-50 bg-indigo-900/20': isNewEntry(entry.id) }"
                      >
                        <component
                          :is="getStatusIcon(entry.status)"
                          class="w-4 h-4 flex-shrink-0"
                          :class="getStatusColor(entry.status).split(' ')[0]"
                        />
                        <span class="text-white font-medium">{{ entry.tester_name }}</span>
                        <span
                          class="px-1.5 py-0.5 rounded text-xs border"
                          :class="getStatusColor(entry.status)"
                        >
                          {{ getStatusLabel(entry.status) }}
                        </span>
                        <span v-if="entry.notes" class="text-gray-400 truncate flex-1">{{ entry.notes }}</span>
                        <span class="text-gray-500 text-xs flex-shrink-0">{{ formatDate(entry.created_at) }}</span>

                        <!-- Entry Actions -->
                        <div v-if="entry.tester_name === testerName" class="flex items-center gap-1 ml-2">
                          <button
                            v-if="entry.status !== 'passed'"
                            @click="updateEntryStatus(entry, 'passed')"
                            class="p-1 hover:bg-gray-600 rounded"
                            title="Als OK markieren"
                          >
                            <CheckCircleIcon class="w-4 h-4 text-green-400" />
                          </button>
                          <button
                            v-if="entry.status !== 'uncertain'"
                            @click="updateEntryStatus(entry, 'uncertain')"
                            class="p-1 hover:bg-gray-600 rounded"
                            title="Als Unsicher markieren"
                          >
                            <QuestionMarkCircleIcon class="w-4 h-4 text-yellow-400" />
                          </button>
                          <button
                            v-if="entry.status !== 'failed'"
                            @click="updateEntryStatus(entry, 'failed')"
                            class="p-1 hover:bg-gray-600 rounded"
                            title="Als Fehler markieren"
                          >
                            <XCircleIcon class="w-4 h-4 text-red-400" />
                          </button>
                          <button
                            @click="deleteEntry(entry, item)"
                            class="p-1 hover:bg-gray-600 rounded"
                            title="Löschen"
                          >
                            <TrashIcon class="w-4 h-4 text-gray-400" />
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Quick Test Buttons -->
                  <div class="flex items-center gap-1.5 flex-shrink-0">
                    <button
                      @click="quickTest(item)"
                      class="flex items-center gap-1 px-2.5 py-2 bg-green-600 hover:bg-green-500 rounded-lg text-white text-sm transition-colors"
                      title="Als OK markieren"
                    >
                      <HandThumbUpIcon class="w-5 h-5" />
                      <span class="hidden sm:inline">OK</span>
                    </button>
                    <button
                      @click="quickUncertain(item)"
                      class="flex items-center gap-1 px-2.5 py-2 bg-yellow-600 hover:bg-yellow-500 rounded-lg text-white text-sm transition-colors"
                      title="Als Unsicher markieren"
                    >
                      <QuestionMarkCircleIcon class="w-5 h-5" />
                      <span class="hidden sm:inline">?</span>
                    </button>
                    <button
                      @click="quickFail(item)"
                      class="flex items-center gap-1 px-2.5 py-2 bg-red-600 hover:bg-red-500 rounded-lg text-white text-sm transition-colors"
                      title="Als Fehler markieren"
                    >
                      <HandThumbDownIcon class="w-5 h-5" />
                      <span class="hidden sm:inline">Fehler</span>
                    </button>
                    <button
                      @click="openAddEntry(item)"
                      class="p-2 bg-gray-600 hover:bg-gray-500 rounded-lg text-white transition-colors"
                      title="Mit Notizen"
                    >
                      <PlusIcon class="w-5 h-5" />
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-8">
          <div class="flex items-center justify-center gap-2 text-gray-500 text-xs">
            <SignalIcon class="w-4 h-4" />
            <span>Echtzeit-Sync via Redis</span>
          </div>
          <p class="text-gray-600 text-xs mt-2">KyuubiSoft</p>
        </div>
      </template>

      <!-- Add Entry Modal -->
      <Teleport to="body">
        <div
          v-if="showAddEntryModal && selectedItem"
          class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
          @click.self="showAddEntryModal = false"
        >
          <div class="bg-gray-800 rounded-2xl border border-gray-700 w-full max-w-md">
            <div class="p-4 border-b border-gray-700">
              <h2 class="text-lg font-semibold text-white">Test mit Notizen</h2>
              <p class="text-gray-400 text-sm mt-1">{{ selectedItem.title }}</p>
            </div>

            <div class="p-4 space-y-4">
              <div v-if="!testerName">
                <label class="block text-sm font-medium text-gray-300 mb-1">
                  Dein Name {{ checklist.require_name ? '*' : '' }}
                </label>
                <input
                  v-model="testerName"
                  type="text"
                  placeholder="Name eingeben..."
                  class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Status</label>
                <div class="grid grid-cols-3 gap-2">
                  <button
                    v-for="status in ['passed', 'uncertain', 'failed', 'in_progress', 'blocked']"
                    :key="status"
                    @click="newEntry.status = status"
                    class="flex items-center justify-center gap-2 px-3 py-2 rounded-lg border transition-colors"
                    :class="newEntry.status === status
                      ? getStatusColor(status)
                      : 'border-gray-600 text-gray-400 hover:border-gray-500'"
                  >
                    <component :is="getStatusIcon(status)" class="w-4 h-4" />
                    <span class="text-sm">{{ getStatusLabel(status) }}</span>
                  </button>
                </div>
              </div>

              <div v-if="checklist.allow_comments">
                <label class="block text-sm font-medium text-gray-300 mb-1">Notizen</label>
                <textarea
                  v-model="newEntry.notes"
                  rows="3"
                  placeholder="z.B. Fehler bei Schritt 3..."
                  class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
                ></textarea>
              </div>
            </div>

            <div class="p-4 border-t border-gray-700 flex justify-end gap-3">
              <button
                @click="showAddEntryModal = false"
                class="px-4 py-2 text-gray-400 hover:text-white transition-colors"
              >
                Abbrechen
              </button>
              <button
                @click="addEntry"
                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 rounded-lg text-white transition-colors"
              >
                Speichern
              </button>
            </div>
          </div>
        </div>
      </Teleport>

      <!-- Add Item Modal -->
      <Teleport to="body">
        <div
          v-if="showAddItemModal"
          class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
          @click.self="showAddItemModal = false"
        >
          <div class="bg-gray-800 rounded-2xl border border-gray-700 w-full max-w-md">
            <div class="p-4 border-b border-gray-700">
              <h2 class="text-lg font-semibold text-white">Neuer Testpunkt</h2>
            </div>

            <div class="p-4 space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Titel *</label>
                <input
                  v-model="newItem.title"
                  type="text"
                  placeholder="z.B. Login mit E-Mail"
                  class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Beschreibung</label>
                <textarea
                  v-model="newItem.description"
                  rows="2"
                  placeholder="Testanweisungen..."
                  class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
                ></textarea>
              </div>

              <div v-if="checklist?.categories?.length > 0">
                <label class="block text-sm font-medium text-gray-300 mb-1">Kategorie</label>
                <select
                  v-model="newItem.category_id"
                  class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
                  <option :value="null">Keine Kategorie</option>
                  <option v-for="cat in checklist.categories" :key="cat.id" :value="cat.id">
                    {{ cat.name }}
                  </option>
                </select>
              </div>
            </div>

            <div class="p-4 border-t border-gray-700 flex justify-end gap-3">
              <button
                @click="showAddItemModal = false"
                class="px-4 py-2 text-gray-400 hover:text-white transition-colors"
              >
                Abbrechen
              </button>
              <button
                @click="addItem"
                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 rounded-lg text-white transition-colors"
              >
                Erstellen
              </button>
            </div>
          </div>
        </div>
      </Teleport>
    </div>
  </div>
</template>
