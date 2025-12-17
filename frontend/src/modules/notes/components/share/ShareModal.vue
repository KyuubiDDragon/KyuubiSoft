<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useToast } from '@/composables/useToast'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
import api from '@/core/api/axios'

const props = defineProps({
  show: {
    type: Boolean,
    default: false,
  },
  noteId: {
    type: String,
    required: true,
  },
  noteTitle: {
    type: String,
    default: 'Untitled',
  },
})

const emit = defineEmits(['close', 'updated'])

const toast = useToast()
const { confirm } = useConfirmDialog()

// State
const loading = ref(false)
const error = ref(null)
const copied = ref(false)

// Share settings
const isShared = ref(false)
const shareUrl = ref('')
const shareToken = ref('')
const viewCount = ref(0)
const settings = ref({
  enabled: false,
  show_author: false,
  hide_date: false,
  allow_comments: false,
  expires_at: null,
})

// Load share status
async function loadShareStatus() {
  if (!props.noteId) return

  loading.value = true
  error.value = null

  try {
    const response = await api.get(`/api/v1/notes/${props.noteId}/share`)
    const data = response.data.data

    isShared.value = data.is_shared
    shareUrl.value = data.url || ''
    shareToken.value = data.token || ''
    viewCount.value = data.views || 0
    settings.value = {
      enabled: data.settings?.enabled || false,
      show_author: data.settings?.show_author || false,
      hide_date: data.settings?.hide_date || false,
      allow_comments: data.settings?.allow_comments || false,
      expires_at: data.settings?.expires_at || null,
    }
  } catch (err) {
    console.error('Failed to load share status:', err)
    error.value = 'Fehler beim Laden der Freigabe-Einstellungen'
  } finally {
    loading.value = false
  }
}

// Toggle sharing
async function toggleSharing() {
  loading.value = true
  error.value = null

  try {
    if (settings.value.enabled) {
      // Disable sharing
      await api.delete(`/api/v1/notes/${props.noteId}/share`)
      settings.value.enabled = false
      isShared.value = false
    } else {
      // Enable sharing
      const response = await api.post(`/api/v1/notes/${props.noteId}/share`, {
        enabled: true,
        show_author: settings.value.show_author,
        hide_date: settings.value.hide_date,
      })
      const data = response.data.data
      settings.value.enabled = true
      isShared.value = true
      shareUrl.value = data.url
      shareToken.value = data.token
    }
    emit('updated')
  } catch (err) {
    console.error('Failed to toggle sharing:', err)
    error.value = 'Fehler beim Ändern der Freigabe'
  } finally {
    loading.value = false
  }
}

// Update settings
async function updateSettings() {
  if (!settings.value.enabled) return

  loading.value = true
  error.value = null

  try {
    const response = await api.post(`/api/v1/notes/${props.noteId}/share`, {
      enabled: true,
      show_author: settings.value.show_author,
      hide_date: settings.value.hide_date,
      expires_at: settings.value.expires_at,
    })
    const data = response.data.data
    shareUrl.value = data.url
    emit('updated')
  } catch (err) {
    console.error('Failed to update settings:', err)
    error.value = 'Fehler beim Aktualisieren der Einstellungen'
  } finally {
    loading.value = false
  }
}

// Regenerate link
async function regenerateLink() {
  if (!await confirm({ message: 'Bisheriger Link wird ungültig. Fortfahren?', type: 'danger', confirmText: 'Löschen' })) return

  loading.value = true
  error.value = null

  try {
    const response = await api.post(`/api/v1/notes/${props.noteId}/share/regenerate`)
    const data = response.data.data
    shareUrl.value = data.url
    shareToken.value = data.token
    viewCount.value = 0
    emit('updated')
  } catch (err) {
    console.error('Failed to regenerate link:', err)
    error.value = 'Fehler beim Generieren des neuen Links'
  } finally {
    loading.value = false
  }
}

// Copy link to clipboard
async function copyLink() {
  try {
    await navigator.clipboard.writeText(shareUrl.value)
    copied.value = true
    setTimeout(() => {
      copied.value = false
    }, 2000)
  } catch (err) {
    console.error('Failed to copy:', err)
  }
}

// Open public page
function openPublicPage() {
  if (shareUrl.value) {
    window.open(shareUrl.value, '_blank')
  }
}

function handleClose() {
  emit('close')
}

function handleBackdropClick(e) {
  if (e.target === e.currentTarget) {
    handleClose()
  }
}

// Watch for show changes
watch(() => props.show, (newVal) => {
  if (newVal && props.noteId) {
    loadShareStatus()
  }
})

// Load on mount if visible
onMounted(() => {
  if (props.show && props.noteId) {
    loadShareStatus()
  }
})
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
      <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm"
        @click="handleBackdropClick"
      >
        <div class="bg-dark-800 rounded-xl shadow-2xl w-full max-w-md mx-4 overflow-hidden">
          <!-- Header -->
          <div class="flex items-center justify-between px-6 py-4 border-b border-dark-700">
            <div>
              <h2 class="text-xl font-semibold text-white">Seite teilen</h2>
              <p class="text-sm text-gray-500 mt-0.5 truncate max-w-[250px]">{{ noteTitle }}</p>
            </div>
            <button
              @click="handleClose"
              class="p-2 hover:bg-dark-700 rounded-lg transition-colors"
            >
              <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Content -->
          <div class="p-6">
            <!-- Loading -->
            <div v-if="loading && !shareUrl" class="flex items-center justify-center py-8">
              <svg class="w-8 h-8 animate-spin text-primary-500" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
              </svg>
            </div>

            <template v-else>
              <!-- Toggle Sharing -->
              <div class="flex items-center justify-between mb-6">
                <div>
                  <h3 class="text-white font-medium">Im Web veröffentlichen</h3>
                  <p class="text-sm text-gray-500">Jeder mit dem Link kann die Seite sehen</p>
                </div>
                <button
                  @click="toggleSharing"
                  :disabled="loading"
                  :class="[
                    'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none',
                    settings.enabled ? 'bg-primary-600' : 'bg-dark-600'
                  ]"
                >
                  <span
                    :class="[
                      'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                      settings.enabled ? 'translate-x-5' : 'translate-x-0'
                    ]"
                  />
                </button>
              </div>

              <!-- Share Link (when enabled) -->
              <template v-if="settings.enabled && shareUrl">
                <!-- Link input -->
                <div class="mb-4">
                  <label class="block text-sm text-gray-400 mb-2">Link zum Teilen</label>
                  <div class="flex gap-2">
                    <input
                      :value="shareUrl"
                      readonly
                      class="flex-1 bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-sm text-gray-300 focus:outline-none"
                    />
                    <button
                      @click="copyLink"
                      class="px-3 py-2 bg-dark-700 hover:bg-dark-600 rounded-lg transition-colors"
                      :title="copied ? 'Kopiert!' : 'Link kopieren'"
                    >
                      <svg v-if="!copied" class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                      </svg>
                      <svg v-else class="w-5 h-5 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                      </svg>
                    </button>
                    <button
                      @click="openPublicPage"
                      class="px-3 py-2 bg-dark-700 hover:bg-dark-600 rounded-lg transition-colors"
                      title="Seite öffnen"
                    >
                      <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                      </svg>
                    </button>
                  </div>
                </div>

                <!-- Stats -->
                <div class="flex items-center gap-4 mb-4 text-sm text-gray-500">
                  <span class="flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    {{ viewCount }} Aufrufe
                  </span>
                </div>

                <!-- Settings -->
                <div class="border-t border-dark-700 pt-4 space-y-3">
                  <label class="flex items-center justify-between cursor-pointer">
                    <span class="text-sm text-gray-300">Autor anzeigen</span>
                    <input
                      v-model="settings.show_author"
                      type="checkbox"
                      @change="updateSettings"
                      class="w-4 h-4 rounded border-dark-600 bg-dark-700 text-primary-600 focus:ring-primary-500 focus:ring-offset-0"
                    />
                  </label>

                  <label class="flex items-center justify-between cursor-pointer">
                    <span class="text-sm text-gray-300">Datum ausblenden</span>
                    <input
                      v-model="settings.hide_date"
                      type="checkbox"
                      @change="updateSettings"
                      class="w-4 h-4 rounded border-dark-600 bg-dark-700 text-primary-600 focus:ring-primary-500 focus:ring-offset-0"
                    />
                  </label>
                </div>

                <!-- Regenerate Link -->
                <div class="border-t border-dark-700 pt-4 mt-4">
                  <button
                    @click="regenerateLink"
                    :disabled="loading"
                    class="text-sm text-gray-400 hover:text-white transition-colors flex items-center gap-1"
                  >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    Neuen Link generieren
                  </button>
                </div>
              </template>

              <!-- Not shared info -->
              <template v-else-if="!settings.enabled">
                <div class="bg-dark-700 rounded-lg p-4 text-center">
                  <svg class="w-12 h-12 mx-auto mb-3 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                  </svg>
                  <p class="text-gray-400 text-sm">
                    Diese Seite ist privat. Aktiviere die Freigabe, um einen öffentlichen Link zu erstellen.
                  </p>
                </div>
              </template>

              <!-- Error -->
              <div
                v-if="error"
                class="mt-4 p-3 bg-red-500/10 border border-red-500/20 rounded-lg text-red-400 text-sm"
              >
                {{ error }}
              </div>
            </template>
          </div>

          <!-- Footer -->
          <div class="flex items-center justify-end px-6 py-4 border-t border-dark-700 bg-dark-800/50">
            <button
              @click="handleClose"
              class="px-4 py-2 text-sm font-medium text-gray-400 hover:text-white transition-colors"
            >
              Schließen
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
  transition: all 0.2s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}

.modal-enter-from > div,
.modal-leave-to > div {
  transform: scale(0.95);
}
</style>
