<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  ArrowLeftIcon,
  PlusIcon,
  PencilIcon,
  TrashIcon,
  CheckCircleIcon,
  XCircleIcon,
  ClockIcon,
  ExclamationTriangleIcon,
  LinkIcon,
  CheckIcon,
  Cog6ToothIcon,
  FolderIcon,
  ChevronDownIcon,
  ChevronRightIcon,
  UsersIcon,
  ArrowPathIcon,
  QuestionMarkCircleIcon,
  DocumentDuplicateIcon,
  ArrowUturnLeftIcon,
} from '@heroicons/vue/24/outline'
import api from '@/core/api/axios'
import { useUiStore } from '@/stores/ui'
import { useToast } from '@/composables/useToast'
import { useConfirmDialog } from '@/composables/useConfirmDialog'

const route = useRoute()
const router = useRouter()
const uiStore = useUiStore()
const toast = useToast()
const { confirm } = useConfirmDialog()

// State
const checklist = ref(null)
const isLoading = ref(true)
const copiedToken = ref(false)
const showSettingsModal = ref(false)
const showAddItemModal = ref(false)
const showAddCategoryModal = ref(false)
const expandedCategories = ref({})

const newItem = ref({
  title: '',
  description: '',
  category_id: null,
  required_testers: 1,
})

const newCategory = ref({
  name: '',
  description: '',
})

const editingItem = ref(null)
const editingCategory = ref(null)
const newPassword = ref('')

// Computed
const checklistId = computed(() => route.params.id)

const itemsByCategory = computed(() => {
  if (!checklist.value) return []

  const categories = checklist.value.categories || []
  const items = checklist.value.items || []

  // Create result with categories
  const result = categories.map(cat => ({
    ...cat,
    items: items.filter(item => item.category_id === cat.id),
  }))

  // Add uncategorized items
  const uncategorized = items.filter(item => !item.category_id)
  if (uncategorized.length > 0) {
    result.push({
      id: null,
      name: 'Unkategorisiert',
      items: uncategorized,
    })
  }

  return result
})

const totalProgress = computed(() => {
  if (!checklist.value?.items?.length) return { completed: 0, total: 0, percentage: 0 }

  let total = 0
  let completed = 0

  for (const item of checklist.value.items) {
    if (item.required_testers === -1) {
      // Unlimited: count as 1 required, completed if at least 1 passed
      total += 1
      completed += (item.passed_count || 0) > 0 ? 1 : 0
    } else {
      total += item.required_testers
      completed += Math.min(item.passed_count || 0, item.required_testers)
    }
  }

  return {
    completed,
    total,
    percentage: total > 0 ? Math.round((completed / total) * 100) : 0,
  }
})

// API Functions
async function loadChecklist() {
  isLoading.value = true
  try {
    const response = await api.get(`/api/v1/checklists/${checklistId.value}`)
    checklist.value = response.data.data

    // Initialize expanded categories
    const categories = checklist.value.categories || []
    categories.forEach(cat => {
      if (expandedCategories.value[cat.id] === undefined) {
        expandedCategories.value[cat.id] = true
      }
    })
    expandedCategories.value[null] = true // Uncategorized always expanded
  } catch (error) {
    uiStore.showError('Fehler beim Laden der Checkliste')
    router.push({ name: 'checklists' })
  } finally {
    isLoading.value = false
  }
}

async function updateSettings() {
  try {
    const payload = {
      title: checklist.value.title,
      description: checklist.value.description,
      is_active: checklist.value.is_active,
      require_name: checklist.value.require_name,
      allow_add_items: checklist.value.allow_add_items,
      allow_comments: checklist.value.allow_comments,
    }

    // Include password if changed (empty string to remove, or new value to set)
    if (newPassword.value !== '' || checklist.value.has_password) {
      payload.password = newPassword.value
    }

    const response = await api.put(`/api/v1/checklists/${checklistId.value}`, payload)
    checklist.value = { ...checklist.value, ...response.data.data }
    showSettingsModal.value = false
    newPassword.value = ''
    uiStore.showSuccess('Einstellungen gespeichert')
  } catch (error) {
    uiStore.showError('Fehler beim Speichern')
  }
}

async function addCategory() {
  if (!newCategory.value.name.trim()) {
    uiStore.showError('Name ist erforderlich')
    return
  }

  try {
    const response = await api.post(`/api/v1/checklists/${checklistId.value}/categories`, newCategory.value)
    checklist.value.categories = checklist.value.categories || []
    checklist.value.categories.push(response.data.data)
    expandedCategories.value[response.data.data.id] = true
    showAddCategoryModal.value = false
    newCategory.value = { name: '', description: '' }
    uiStore.showSuccess('Kategorie erstellt')
  } catch (error) {
    uiStore.showError('Fehler beim Erstellen')
  }
}

async function updateCategory(category) {
  try {
    await api.put(`/api/v1/checklists/${checklistId.value}/categories/${category.id}`, {
      name: category.name,
      description: category.description,
    })
    editingCategory.value = null
    uiStore.showSuccess('Kategorie aktualisiert')
  } catch (error) {
    uiStore.showError('Fehler beim Aktualisieren')
  }
}

async function deleteCategory(category) {
  if (!await confirm({ message: `Kategorie "${category.name}" wirklich löschen? Die Punkte werden nicht gelöscht.`, type: 'danger', confirmText: 'Löschen' })) return

  try {
    await api.delete(`/api/v1/checklists/${checklistId.value}/categories/${category.id}`)
    checklist.value.categories = checklist.value.categories.filter(c => c.id !== category.id)
    // Move items to uncategorized
    checklist.value.items = checklist.value.items.map(item => {
      if (item.category_id === category.id) {
        return { ...item, category_id: null }
      }
      return item
    })
    uiStore.showSuccess('Kategorie gelöscht')
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
  }
}

async function addItem() {
  if (!newItem.value.title.trim()) {
    uiStore.showError('Titel ist erforderlich')
    return
  }

  try {
    const response = await api.post(`/api/v1/checklists/${checklistId.value}/items`, newItem.value)
    checklist.value.items = checklist.value.items || []
    checklist.value.items.push({ ...response.data.data, entries: [], passed_count: 0, failed_count: 0, entry_count: 0 })
    showAddItemModal.value = false
    newItem.value = { title: '', description: '', category_id: null, required_testers: 1 }
    uiStore.showSuccess('Testpunkt erstellt')
  } catch (error) {
    uiStore.showError('Fehler beim Erstellen')
  }
}

async function updateItem(item) {
  try {
    await api.put(`/api/v1/checklists/${checklistId.value}/items/${item.id}`, {
      title: item.title,
      description: item.description,
      category_id: item.category_id,
      required_testers: item.required_testers,
    })
    editingItem.value = null
    uiStore.showSuccess('Testpunkt aktualisiert')
  } catch (error) {
    uiStore.showError('Fehler beim Aktualisieren')
  }
}

async function deleteItem(item) {
  if (!await confirm({ message: `Testpunkt "${item.title}" wirklich löschen?`, type: 'danger', confirmText: 'Löschen' })) return

  try {
    await api.delete(`/api/v1/checklists/${checklistId.value}/items/${item.id}`)
    checklist.value.items = checklist.value.items.filter(i => i.id !== item.id)
    uiStore.showSuccess('Testpunkt gelöscht')
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
  }
}

async function duplicateChecklist() {
  if (!await confirm({ message: 'Checkliste duplizieren? Es wird eine Kopie mit allen Kategorien und Testpunkten erstellt (ohne Einträge).', type: 'danger', confirmText: 'Löschen' })) return

  try {
    const response = await api.post(`/api/v1/checklists/${checklistId.value}/duplicate`)
    uiStore.showSuccess('Checkliste dupliziert')
    router.push({ name: 'checklist-detail', params: { id: response.data.data.id } })
  } catch (error) {
    uiStore.showError('Fehler beim Duplizieren')
  }
}

async function resetEntries() {
  if (!await confirm({ message: 'Alle Testeinträge wirklich zurücksetzen? Diese Aktion kann nicht rückgängig gemacht werden!', type: 'danger', confirmText: 'Löschen' })) return

  try {
    await api.post(`/api/v1/checklists/${checklistId.value}/reset`)
    // Reset local state
    checklist.value.items = checklist.value.items.map(item => ({
      ...item,
      entries: [],
      passed_count: 0,
      failed_count: 0,
      uncertain_count: 0,
      in_progress_count: 0,
      entry_count: 0,
    }))
    uiStore.showSuccess('Alle Einträge wurden zurückgesetzt')
  } catch (error) {
    uiStore.showError('Fehler beim Zurücksetzen')
  }
}

function copyShareLink() {
  const url = `${window.location.origin}/checklist/${checklist.value.share_token}`
  navigator.clipboard.writeText(url)
  copiedToken.value = true
  setTimeout(() => { copiedToken.value = false }, 2000)
}

function openPublicView() {
  window.open(`/checklist/${checklist.value.share_token}`, '_blank')
}

function toggleCategory(categoryId) {
  expandedCategories.value[categoryId] = !expandedCategories.value[categoryId]
}

function openAddItemInCategory(categoryId) {
  newItem.value.category_id = categoryId
  showAddItemModal.value = true
}

function getStatusColor(status) {
  switch (status) {
    case 'passed': return 'text-green-400 bg-green-400/10'
    case 'failed': return 'text-red-400 bg-red-400/10'
    case 'in_progress': return 'text-blue-400 bg-blue-400/10'
    case 'blocked': return 'text-orange-400 bg-orange-400/10'
    case 'uncertain': return 'text-yellow-400 bg-yellow-400/10'
    default: return 'text-gray-400 bg-gray-400/10'
  }
}

function getStatusLabel(status) {
  switch (status) {
    case 'passed': return 'Bestanden'
    case 'failed': return 'Fehlgeschlagen'
    case 'in_progress': return 'In Bearbeitung'
    case 'blocked': return 'Blockiert'
    case 'uncertain': return 'Unsicher'
    default: return 'Ausstehend'
  }
}

function formatDate(dateStr) {
  if (!dateStr) return ''
  return new Date(dateStr).toLocaleDateString('de-DE', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

// Initialize
onMounted(() => {
  loadChecklist()
})

watch(() => route.params.id, () => {
  if (route.params.id) {
    loadChecklist()
  }
})
</script>

<template>
  <div class="space-y-4">
    <!-- Loading -->
    <div v-if="isLoading" class="bg-dark-800 rounded-xl border border-dark-700 p-12 text-center">
      <div class="w-8 h-8 border-2 border-primary-500 border-t-transparent rounded-full animate-spin mx-auto"></div>
      <p class="text-gray-400 mt-4">Lade Checkliste...</p>
    </div>

    <template v-else-if="checklist">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row justify-between items-start gap-4">
        <div class="flex items-start gap-4">
          <button
            @click="router.push({ name: 'checklists' })"
            class="p-2 hover:bg-dark-700 rounded-lg transition-colors"
          >
            <ArrowLeftIcon class="w-5 h-5 text-gray-400" />
          </button>
          <div>
            <div class="flex items-center gap-3">
              <h1 class="text-2xl font-bold text-white">{{ checklist.title }}</h1>
              <span
                v-if="!checklist.is_active"
                class="px-2 py-0.5 text-xs rounded-full bg-gray-500/20 text-gray-400"
              >
                Deaktiviert
              </span>
            </div>
            <p v-if="checklist.description" class="text-gray-400 mt-1">{{ checklist.description }}</p>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <button
            @click="loadChecklist"
            class="p-2 hover:bg-dark-700 rounded-lg transition-colors"
            title="Aktualisieren"
          >
            <ArrowPathIcon class="w-5 h-5 text-gray-400" />
          </button>
          <button
            @click="copyShareLink"
            class="flex items-center gap-2 px-3 py-2 bg-dark-700 hover:bg-dark-600 rounded-lg text-white transition-colors"
          >
            <CheckIcon v-if="copiedToken" class="w-5 h-5 text-green-500" />
            <LinkIcon v-else class="w-5 h-5" />
            <span>{{ copiedToken ? 'Kopiert!' : 'Link kopieren' }}</span>
          </button>
          <button
            @click="openPublicView"
            class="px-3 py-2 bg-primary-600 hover:bg-primary-500 rounded-lg text-white transition-colors"
          >
            Öffentliche Ansicht
          </button>
          <button
            @click="showSettingsModal = true"
            class="p-2 hover:bg-dark-700 rounded-lg transition-colors"
          >
            <Cog6ToothIcon class="w-5 h-5 text-gray-400" />
          </button>
        </div>
      </div>

      <!-- Progress Card -->
      <div class="bg-dark-800 rounded-xl border border-dark-700 p-4">
        <div class="flex items-center justify-between mb-2">
          <span class="text-gray-400 text-sm">Gesamtfortschritt</span>
          <span class="text-white font-medium">{{ totalProgress.completed }} / {{ totalProgress.total }} Tests</span>
        </div>
        <div class="w-full h-3 bg-dark-600 rounded-full overflow-hidden">
          <div
            class="h-full bg-gradient-to-r from-green-500 to-green-400 rounded-full transition-all duration-500"
            :style="{ width: totalProgress.percentage + '%' }"
          ></div>
        </div>
        <div class="text-right mt-1">
          <span class="text-green-400 text-sm font-medium">{{ totalProgress.percentage }}%</span>
        </div>
      </div>

      <!-- Actions Bar -->
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
          <button
            @click="showAddCategoryModal = true"
            class="flex items-center gap-2 px-3 py-2 bg-dark-700 hover:bg-dark-600 rounded-lg text-white transition-colors"
          >
            <FolderIcon class="w-4 h-4" />
            <span>Kategorie</span>
          </button>
          <button
            @click="showAddItemModal = true"
            class="flex items-center gap-2 px-3 py-2 bg-primary-600 hover:bg-primary-500 rounded-lg text-white transition-colors"
          >
            <PlusIcon class="w-4 h-4" />
            <span>Testpunkt</span>
          </button>
        </div>
        <div class="flex items-center gap-2">
          <button
            @click="duplicateChecklist"
            class="flex items-center gap-2 px-3 py-2 bg-dark-700 hover:bg-dark-600 rounded-lg text-white transition-colors"
            title="Checkliste duplizieren"
          >
            <DocumentDuplicateIcon class="w-4 h-4" />
            <span class="hidden sm:inline">Duplizieren</span>
          </button>
          <button
            @click="resetEntries"
            class="flex items-center gap-2 px-3 py-2 bg-dark-700 hover:bg-red-600/20 text-gray-300 hover:text-red-400 rounded-lg transition-colors"
            title="Alle Einträge zurücksetzen"
          >
            <ArrowUturnLeftIcon class="w-4 h-4" />
            <span class="hidden sm:inline">Zurücksetzen</span>
          </button>
        </div>
      </div>

      <!-- Items by Category -->
      <div class="space-y-4">
        <div
          v-for="category in itemsByCategory"
          :key="category.id || 'uncategorized'"
          class="bg-dark-800 rounded-xl border border-dark-700 overflow-hidden"
        >
          <!-- Category Header -->
          <div
            class="flex items-center justify-between p-4 bg-dark-700/50 cursor-pointer"
            @click="toggleCategory(category.id)"
          >
            <div class="flex items-center gap-3">
              <ChevronDownIcon
                v-if="expandedCategories[category.id]"
                class="w-5 h-5 text-gray-400"
              />
              <ChevronRightIcon v-else class="w-5 h-5 text-gray-400" />
              <FolderIcon class="w-5 h-5 text-primary-400" />
              <span class="text-white font-medium">{{ category.name }}</span>
              <span class="text-gray-500 text-sm">({{ category.items.length }})</span>
            </div>

            <div class="flex items-center gap-1" @click.stop>
              <button
                @click="openAddItemInCategory(category.id)"
                class="p-1.5 hover:bg-dark-600 rounded transition-colors"
                title="Testpunkt hinzufügen"
              >
                <PlusIcon class="w-4 h-4 text-primary-400" />
              </button>
              <template v-if="category.id">
                <button
                  @click="editingCategory = { ...category }"
                  class="p-1.5 hover:bg-dark-600 rounded transition-colors"
                >
                  <PencilIcon class="w-4 h-4 text-gray-400" />
                </button>
                <button
                  @click="deleteCategory(category)"
                  class="p-1.5 hover:bg-dark-600 rounded transition-colors"
                >
                  <TrashIcon class="w-4 h-4 text-gray-400 hover:text-red-400" />
                </button>
              </template>
            </div>
          </div>

          <!-- Items -->
          <div v-if="expandedCategories[category.id]" class="divide-y divide-dark-700">
            <div v-if="category.items.length === 0" class="p-8 text-center text-gray-500">
              Keine Testpunkte in dieser Kategorie
            </div>

            <div
              v-for="item in category.items"
              :key="item.id"
              class="p-4"
            >
              <div class="flex items-start justify-between gap-4">
                <div class="flex-1">
                  <h4 class="text-white font-medium">{{ item.title }}</h4>
                  <p v-if="item.description" class="text-gray-400 text-sm mt-1">{{ item.description }}</p>

                  <!-- Item Stats -->
                  <div class="flex items-center gap-4 mt-3">
                    <div class="flex items-center gap-1.5">
                      <UsersIcon class="w-4 h-4 text-gray-500" />
                      <span class="text-gray-400 text-sm">
                        {{ item.required_testers === -1 ? '∞' : item.required_testers }} Tester benötigt
                      </span>
                    </div>
                    <div class="flex items-center gap-2">
                      <span
                        v-if="item.passed_count > 0"
                        class="flex items-center gap-1 text-green-400 text-sm"
                      >
                        <CheckCircleIcon class="w-4 h-4" />
                        {{ item.passed_count }}
                      </span>
                      <span
                        v-if="item.uncertain_count > 0"
                        class="flex items-center gap-1 text-yellow-400 text-sm"
                      >
                        <QuestionMarkCircleIcon class="w-4 h-4" />
                        {{ item.uncertain_count }}
                      </span>
                      <span
                        v-if="item.failed_count > 0"
                        class="flex items-center gap-1 text-red-400 text-sm"
                      >
                        <XCircleIcon class="w-4 h-4" />
                        {{ item.failed_count }}
                      </span>
                      <span
                        v-if="item.in_progress_count > 0"
                        class="flex items-center gap-1 text-blue-400 text-sm"
                      >
                        <ClockIcon class="w-4 h-4" />
                        {{ item.in_progress_count }}
                      </span>
                    </div>
                  </div>

                  <!-- Entries -->
                  <div v-if="item.entries && item.entries.length > 0" class="mt-3 space-y-2">
                    <div
                      v-for="entry in item.entries"
                      :key="entry.id"
                      class="flex items-center gap-3 p-2 bg-dark-700/50 rounded-lg text-sm"
                    >
                      <span
                        class="px-2 py-0.5 rounded text-xs font-medium"
                        :class="getStatusColor(entry.status)"
                      >
                        {{ getStatusLabel(entry.status) }}
                      </span>
                      <span class="text-white">{{ entry.tester_name }}</span>
                      <span v-if="entry.notes" class="text-gray-400 truncate">{{ entry.notes }}</span>
                      <span class="text-gray-500 ml-auto">{{ formatDate(entry.created_at) }}</span>
                    </div>
                  </div>
                </div>

                <!-- Item Actions -->
                <div class="flex items-center gap-1">
                  <button
                    @click="editingItem = { ...item }"
                    class="p-2 hover:bg-dark-600 rounded-lg transition-colors"
                  >
                    <PencilIcon class="w-4 h-4 text-gray-400" />
                  </button>
                  <button
                    @click="deleteItem(item)"
                    class="p-2 hover:bg-dark-600 rounded-lg transition-colors"
                  >
                    <TrashIcon class="w-4 h-4 text-gray-400 hover:text-red-400" />
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div
        v-if="(!checklist.items || checklist.items.length === 0) && (!checklist.categories || checklist.categories.length === 0)"
        class="bg-dark-800 rounded-xl border border-dark-700 p-12 text-center"
      >
        <p class="text-gray-400 mb-4">Füge Testpunkte hinzu, um loszulegen</p>
        <button
          @click="showAddItemModal = true"
          class="px-4 py-2 bg-primary-600 hover:bg-primary-500 rounded-lg text-white transition-colors"
        >
          Ersten Testpunkt erstellen
        </button>
      </div>
    </template>

    <!-- Settings Modal -->
    <Teleport to="body">
      <div
        v-if="showSettingsModal && checklist"
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
      >
        <div class="bg-dark-800 rounded-xl border border-dark-700 w-full max-w-lg">
          <div class="p-4 border-b border-dark-700">
            <h2 class="text-lg font-semibold text-white">Einstellungen</h2>
          </div>

          <div class="p-4 space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Titel</label>
              <input
                v-model="checklist.title"
                type="text"
                class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary-500"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Beschreibung</label>
              <textarea
                v-model="checklist.description"
                rows="3"
                class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary-500 resize-none"
              ></textarea>
            </div>

            <div class="space-y-3">
              <label class="flex items-center gap-3 cursor-pointer">
                <input
                  type="checkbox"
                  v-model="checklist.is_active"
                  class="w-4 h-4 rounded border-dark-600 text-primary-600 focus:ring-primary-500"
                />
                <span class="text-gray-300 text-sm">Checkliste aktiv</span>
              </label>

              <label class="flex items-center gap-3 cursor-pointer">
                <input
                  type="checkbox"
                  v-model="checklist.require_name"
                  class="w-4 h-4 rounded border-dark-600 text-primary-600 focus:ring-primary-500"
                />
                <span class="text-gray-300 text-sm">Name bei Einträgen erforderlich</span>
              </label>

              <label class="flex items-center gap-3 cursor-pointer">
                <input
                  type="checkbox"
                  v-model="checklist.allow_add_items"
                  class="w-4 h-4 rounded border-dark-600 text-primary-600 focus:ring-primary-500"
                />
                <span class="text-gray-300 text-sm">Externe dürfen Punkte hinzufügen</span>
              </label>

              <label class="flex items-center gap-3 cursor-pointer">
                <input
                  type="checkbox"
                  v-model="checklist.allow_comments"
                  class="w-4 h-4 rounded border-dark-600 text-primary-600 focus:ring-primary-500"
                />
                <span class="text-gray-300 text-sm">Kommentare/Notizen erlauben</span>
              </label>
            </div>

            <!-- Password Protection -->
            <div class="pt-4 border-t border-dark-700">
              <label class="block text-sm font-medium text-gray-300 mb-1">
                Passwortschutz
                <span v-if="checklist.has_password" class="text-green-400 text-xs ml-2">(aktiv)</span>
              </label>
              <div class="flex gap-2">
                <input
                  v-model="newPassword"
                  type="password"
                  :placeholder="checklist.has_password ? 'Neues Passwort (leer = entfernen)' : 'Passwort setzen (optional)'"
                  class="flex-1 px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500"
                />
              </div>
              <p class="text-gray-500 text-xs mt-1">
                {{ checklist.has_password ? 'Leer lassen um Passwort zu entfernen, oder neues Passwort eingeben' : 'Optional: Schütze die Checkliste mit einem Passwort' }}
              </p>
            </div>
          </div>

          <div class="p-4 border-t border-dark-700 flex justify-end gap-3">
            <button
              @click="showSettingsModal = false"
              class="px-4 py-2 text-gray-400 hover:text-white transition-colors"
            >
              Abbrechen
            </button>
            <button
              @click="updateSettings"
              class="px-4 py-2 bg-primary-600 hover:bg-primary-500 rounded-lg text-white transition-colors"
            >
              Speichern
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Add Category Modal -->
    <Teleport to="body">
      <div
        v-if="showAddCategoryModal"
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
      >
        <div class="bg-dark-800 rounded-xl border border-dark-700 w-full max-w-md">
          <div class="p-4 border-b border-dark-700">
            <h2 class="text-lg font-semibold text-white">Neue Kategorie</h2>
          </div>

          <div class="p-4 space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Name *</label>
              <input
                v-model="newCategory.name"
                type="text"
                placeholder="z.B. Login-Tests"
                class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500"
                @keyup.enter="addCategory"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Beschreibung</label>
              <input
                v-model="newCategory.description"
                type="text"
                placeholder="Optional..."
                class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500"
              />
            </div>
          </div>

          <div class="p-4 border-t border-dark-700 flex justify-end gap-3">
            <button
              @click="showAddCategoryModal = false"
              class="px-4 py-2 text-gray-400 hover:text-white transition-colors"
            >
              Abbrechen
            </button>
            <button
              @click="addCategory"
              class="px-4 py-2 bg-primary-600 hover:bg-primary-500 rounded-lg text-white transition-colors"
            >
              Erstellen
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
      >
        <div class="bg-dark-800 rounded-xl border border-dark-700 w-full max-w-md">
          <div class="p-4 border-b border-dark-700">
            <h2 class="text-lg font-semibold text-white">Neuer Testpunkt</h2>
          </div>

          <div class="p-4 space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Titel *</label>
              <input
                v-model="newItem.title"
                type="text"
                placeholder="z.B. Login mit E-Mail"
                class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500"
                @keyup.enter="addItem"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Beschreibung</label>
              <textarea
                v-model="newItem.description"
                rows="2"
                placeholder="Testanweisungen..."
                class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500 resize-none"
              ></textarea>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Kategorie</label>
              <select
                v-model="newItem.category_id"
                class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary-500"
              >
                <option :value="null">Keine Kategorie</option>
                <option v-for="cat in checklist?.categories" :key="cat.id" :value="cat.id">
                  {{ cat.name }}
                </option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Benötigte Tester</label>
              <input
                v-model.number="newItem.required_testers"
                type="number"
                min="-1"
                class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary-500"
              />
              <p class="text-gray-500 text-xs mt-1">-1 = unbegrenzt (∞), sonst Anzahl der Tester</p>
            </div>
          </div>

          <div class="p-4 border-t border-dark-700 flex justify-end gap-3">
            <button
              @click="showAddItemModal = false; newItem.category_id = null"
              class="px-4 py-2 text-gray-400 hover:text-white transition-colors"
            >
              Abbrechen
            </button>
            <button
              @click="addItem"
              class="px-4 py-2 bg-primary-600 hover:bg-primary-500 rounded-lg text-white transition-colors"
            >
              Erstellen
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Edit Item Modal -->
    <Teleport to="body">
      <div
        v-if="editingItem"
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
      >
        <div class="bg-dark-800 rounded-xl border border-dark-700 w-full max-w-md">
          <div class="p-4 border-b border-dark-700">
            <h2 class="text-lg font-semibold text-white">Testpunkt bearbeiten</h2>
          </div>

          <div class="p-4 space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Titel</label>
              <input
                v-model="editingItem.title"
                type="text"
                class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary-500"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Beschreibung</label>
              <textarea
                v-model="editingItem.description"
                rows="2"
                class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary-500 resize-none"
              ></textarea>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Kategorie</label>
              <select
                v-model="editingItem.category_id"
                class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary-500"
              >
                <option :value="null">Keine Kategorie</option>
                <option v-for="cat in checklist?.categories" :key="cat.id" :value="cat.id">
                  {{ cat.name }}
                </option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Benötigte Tester</label>
              <input
                v-model.number="editingItem.required_testers"
                type="number"
                min="-1"
                class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary-500"
              />
              <p class="text-gray-500 text-xs mt-1">-1 = unbegrenzt (∞)</p>
            </div>
          </div>

          <div class="p-4 border-t border-dark-700 flex justify-end gap-3">
            <button
              @click="editingItem = null"
              class="px-4 py-2 text-gray-400 hover:text-white transition-colors"
            >
              Abbrechen
            </button>
            <button
              @click="updateItem(editingItem)"
              class="px-4 py-2 bg-primary-600 hover:bg-primary-500 rounded-lg text-white transition-colors"
            >
              Speichern
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Edit Category Modal -->
    <Teleport to="body">
      <div
        v-if="editingCategory"
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
      >
        <div class="bg-dark-800 rounded-xl border border-dark-700 w-full max-w-md">
          <div class="p-4 border-b border-dark-700">
            <h2 class="text-lg font-semibold text-white">Kategorie bearbeiten</h2>
          </div>

          <div class="p-4 space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Name</label>
              <input
                v-model="editingCategory.name"
                type="text"
                class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary-500"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Beschreibung</label>
              <input
                v-model="editingCategory.description"
                type="text"
                class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary-500"
              />
            </div>
          </div>

          <div class="p-4 border-t border-dark-700 flex justify-end gap-3">
            <button
              @click="editingCategory = null"
              class="px-4 py-2 text-gray-400 hover:text-white transition-colors"
            >
              Abbrechen
            </button>
            <button
              @click="updateCategory(editingCategory)"
              class="px-4 py-2 bg-primary-600 hover:bg-primary-500 rounded-lg text-white transition-colors"
            >
              Speichern
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
