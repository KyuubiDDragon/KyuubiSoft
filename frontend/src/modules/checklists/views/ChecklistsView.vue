<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  ClipboardDocumentListIcon,
  PlusIcon,
  LinkIcon,
  EyeIcon,
  PencilIcon,
  TrashIcon,
  CheckCircleIcon,
  XCircleIcon,
  ClipboardDocumentIcon,
  CheckIcon,
  UsersIcon,
} from '@heroicons/vue/24/outline'
import api from '@/core/api/axios'
import { useUiStore } from '@/stores/ui'
import { useToast } from '@/composables/useToast'
import { useConfirmDialog } from '@/composables/useConfirmDialog'

const router = useRouter()
const uiStore = useUiStore()
const toast = useToast()
const { confirm } = useConfirmDialog()

// State
const checklists = ref([])
const isLoading = ref(true)
const showCreateModal = ref(false)
const newChecklist = ref({
  title: '',
  description: '',
  allow_anonymous: true,
  require_name: true,
  allow_add_items: false,
  allow_comments: true,
})
const copiedToken = ref(null)

// API Functions
async function loadChecklists() {
  isLoading.value = true
  try {
    const response = await api.get('/api/v1/checklists')
    checklists.value = response.data.data.items || []
  } catch (error) {
    uiStore.showError('Fehler beim Laden der Checklisten')
  } finally {
    isLoading.value = false
  }
}

async function createChecklist() {
  if (!newChecklist.value.title.trim()) {
    uiStore.showError('Titel ist erforderlich')
    return
  }

  try {
    const response = await api.post('/api/v1/checklists', newChecklist.value)
    checklists.value.unshift(response.data.data)
    showCreateModal.value = false
    resetNewChecklist()
    uiStore.showSuccess('Checkliste erstellt')
    // Navigate to detail view
    router.push({ name: 'checklist-detail', params: { id: response.data.data.id } })
  } catch (error) {
    uiStore.showError('Fehler beim Erstellen')
  }
}

async function deleteChecklist(checklist) {
  if (!await confirm({ message: `Checkliste "${checklist.title}" wirklich löschen?`, type: 'danger', confirmText: 'Löschen' })) return

  try {
    await api.delete(`/api/v1/checklists/${checklist.id}`)
    checklists.value = checklists.value.filter(c => c.id !== checklist.id)
    uiStore.showSuccess('Checkliste gelöscht')
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
  }
}

async function toggleActive(checklist) {
  try {
    const response = await api.put(`/api/v1/checklists/${checklist.id}`, {
      is_active: !checklist.is_active,
    })
    const index = checklists.value.findIndex(c => c.id === checklist.id)
    if (index !== -1) {
      checklists.value[index] = response.data.data
    }
    uiStore.showSuccess(response.data.data.is_active ? 'Checkliste aktiviert' : 'Checkliste deaktiviert')
  } catch (error) {
    uiStore.showError('Fehler beim Ändern des Status')
  }
}

function resetNewChecklist() {
  newChecklist.value = {
    title: '',
    description: '',
    allow_anonymous: true,
    require_name: true,
    allow_add_items: false,
    allow_comments: true,
  }
}

function copyShareLink(checklist) {
  const url = `${window.location.origin}/checklist/${checklist.share_token}`
  navigator.clipboard.writeText(url)
  copiedToken.value = checklist.share_token
  setTimeout(() => { copiedToken.value = null }, 2000)
}

function openChecklist(checklist) {
  router.push({ name: 'checklist-detail', params: { id: checklist.id } })
}

function getProgress(checklist) {
  if (!checklist.item_count || checklist.item_count === 0) return 0
  return Math.round((checklist.completed_entries / checklist.item_count) * 100)
}

// Initialize
onMounted(() => {
  loadChecklists()
})
</script>

<template>
  <div class="space-y-4">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
      <div>
        <h1 class="text-2xl font-bold text-white">Test-Checklisten</h1>
        <p class="text-gray-400 text-sm mt-1">Erstelle und teile Checklisten für kollaboratives Testen</p>
      </div>

      <button
        @click="showCreateModal = true"
        class="btn-primary"
      >
        <PlusIcon class="w-5 h-5" />
        <span>Neue Checkliste</span>
      </button>
    </div>

    <!-- Content -->
    <div class="bg-white/[0.04] rounded-xl border border-white/[0.06]">
      <!-- Loading -->
      <div v-if="isLoading" class="p-12 text-center">
        <div class="w-8 h-8 border-2 border-primary-500 border-t-transparent rounded-full animate-spin mx-auto"></div>
        <p class="text-gray-400 mt-4">Lade Checklisten...</p>
      </div>

      <!-- Empty State -->
      <div v-else-if="checklists.length === 0" class="p-12 text-center">
        <ClipboardDocumentListIcon class="w-16 h-16 mx-auto text-gray-600 mb-4" />
        <p class="text-lg text-white font-medium">Keine Checklisten vorhanden</p>
        <p class="text-gray-400 mb-6">Erstelle deine erste Checkliste für kollaboratives Testen</p>
        <button
          @click="showCreateModal = true"
          class="btn-primary"
        >
          Checkliste erstellen
        </button>
      </div>

      <!-- Checklists List -->
      <div v-else class="divide-y divide-white/[0.06]">
        <div
          v-for="checklist in checklists"
          :key="checklist.id"
          class="p-4 hover:bg-white/[0.04] transition-colors cursor-pointer"
          @click="openChecklist(checklist)"
        >
          <div class="flex items-start justify-between gap-4">
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-3">
                <h3 class="text-white font-medium truncate">{{ checklist.title }}</h3>
                <span
                  v-if="!checklist.is_active"
                  class="px-2 py-0.5 text-xs rounded-full bg-gray-500/20 text-gray-400"
                >
                  Deaktiviert
                </span>
              </div>
              <p v-if="checklist.description" class="text-gray-400 text-sm mt-1 line-clamp-2">
                {{ checklist.description }}
              </p>

              <!-- Stats -->
              <div class="flex items-center gap-4 mt-3 text-sm">
                <div class="flex items-center gap-1.5 text-gray-400">
                  <ClipboardDocumentListIcon class="w-4 h-4" />
                  <span>{{ checklist.item_count || 0 }} Punkte</span>
                </div>
                <div class="flex items-center gap-1.5 text-gray-400">
                  <UsersIcon class="w-4 h-4" />
                  <span>{{ checklist.completed_entries || 0 }} Tests</span>
                </div>
                <div v-if="checklist.item_count > 0" class="flex items-center gap-2">
                  <div class="w-24 h-1.5 bg-white/[0.08] rounded-full overflow-hidden">
                    <div
                      class="h-full bg-green-500 rounded-full transition-all"
                      :style="{ width: getProgress(checklist) + '%' }"
                    ></div>
                  </div>
                  <span class="text-gray-400 text-xs">{{ getProgress(checklist) }}%</span>
                </div>
              </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-1" @click.stop>
              <!-- Copy Link -->
              <button
                @click="copyShareLink(checklist)"
                class="p-2 hover:bg-white/[0.04] rounded-lg transition-colors"
                :title="copiedToken === checklist.share_token ? 'Kopiert!' : 'Link kopieren'"
              >
                <CheckIcon v-if="copiedToken === checklist.share_token" class="w-5 h-5 text-green-500" />
                <LinkIcon v-else class="w-5 h-5 text-gray-400 hover:text-white" />
              </button>

              <!-- Toggle Active -->
              <button
                @click="toggleActive(checklist)"
                class="p-2 hover:bg-white/[0.04] rounded-lg transition-colors"
                :title="checklist.is_active ? 'Deaktivieren' : 'Aktivieren'"
              >
                <CheckCircleIcon v-if="checklist.is_active" class="w-5 h-5 text-green-400" />
                <XCircleIcon v-else class="w-5 h-5 text-gray-400" />
              </button>

              <!-- Delete -->
              <button
                @click="deleteChecklist(checklist)"
                class="p-2 hover:bg-white/[0.04] rounded-lg transition-colors"
                title="Löschen"
              >
                <TrashIcon class="w-5 h-5 text-gray-400 hover:text-red-400" />
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Create Modal -->
    <Teleport to="body">
      <div
        v-if="showCreateModal"
        class="fixed inset-0 bg-black/60 backdrop-blur-md flex items-center justify-center z-50 p-4"
      >
        <div class="modal w-full max-w-lg">
          <div class="p-4 border-b border-white/[0.06]">
            <h2 class="text-lg font-semibold text-white">Neue Checkliste erstellen</h2>
          </div>

          <div class="p-4 space-y-4">
            <!-- Title -->
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Titel *</label>
              <input
                v-model="newChecklist.title"
                type="text"
                placeholder="z.B. System-Test Sprint 23"
                class="input"
                @keyup.enter="createChecklist"
              />
            </div>

            <!-- Description -->
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Beschreibung</label>
              <textarea
                v-model="newChecklist.description"
                rows="3"
                placeholder="Optionale Beschreibung..."
                class="textarea"
              ></textarea>
            </div>

            <!-- Settings -->
            <div class="space-y-3">
              <label class="flex items-center gap-3 cursor-pointer">
                <input
                  type="checkbox"
                  v-model="newChecklist.require_name"
                  class="w-4 h-4 rounded border-white/[0.06] text-primary-600 focus:ring-primary-500"
                />
                <span class="text-gray-300 text-sm">Name bei Einträgen erforderlich</span>
              </label>

              <label class="flex items-center gap-3 cursor-pointer">
                <input
                  type="checkbox"
                  v-model="newChecklist.allow_add_items"
                  class="w-4 h-4 rounded border-white/[0.06] text-primary-600 focus:ring-primary-500"
                />
                <span class="text-gray-300 text-sm">Externe dürfen Punkte hinzufügen</span>
              </label>

              <label class="flex items-center gap-3 cursor-pointer">
                <input
                  type="checkbox"
                  v-model="newChecklist.allow_comments"
                  class="w-4 h-4 rounded border-white/[0.06] text-primary-600 focus:ring-primary-500"
                />
                <span class="text-gray-300 text-sm">Kommentare/Notizen erlauben</span>
              </label>
            </div>
          </div>

          <div class="p-4 border-t border-white/[0.06] flex justify-end gap-3">
            <button
              @click="showCreateModal = false"
              class="px-4 py-2 text-gray-400 hover:text-white transition-colors"
            >
              Abbrechen
            </button>
            <button
              @click="createChecklist"
              class="btn-primary"
            >
              Erstellen
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
