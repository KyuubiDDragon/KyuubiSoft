<script setup>
import { ref, watch, computed } from 'vue'
import { useNotesStore } from '../../stores/notesStore'
import { useUiStore } from '@/stores/ui'
import {
  XMarkIcon,
  ClockIcon,
  ArrowPathIcon,
  EyeIcon,
  DocumentTextIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  },
  noteId: {
    type: String,
    required: true
  },
  currentTitle: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['close', 'restored'])

const notesStore = useNotesStore()
const uiStore = useUiStore()

const isLoading = ref(false)
const isRestoring = ref(false)
const versions = ref([])
const selectedVersion = ref(null)
const previewContent = ref(null)
const showPreview = ref(false)

// Format date
function formatDate(date) {
  return new Date(date).toLocaleString('de-DE', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

// Format relative time
function formatRelativeTime(date) {
  const now = new Date()
  const then = new Date(date)
  const diffMs = now - then
  const diffMins = Math.floor(diffMs / 60000)
  const diffHours = Math.floor(diffMs / 3600000)
  const diffDays = Math.floor(diffMs / 86400000)

  if (diffMins < 1) return 'Gerade eben'
  if (diffMins < 60) return `vor ${diffMins} Min.`
  if (diffHours < 24) return `vor ${diffHours} Std.`
  if (diffDays < 7) return `vor ${diffDays} Tagen`
  return formatDate(date)
}

// Load versions
async function loadVersions() {
  isLoading.value = true
  try {
    versions.value = await notesStore.getVersions(props.noteId)
  } catch (error) {
    uiStore.showError('Fehler beim Laden der Versionen')
  } finally {
    isLoading.value = false
  }
}

// Preview version
async function preview(version) {
  selectedVersion.value = version
  try {
    const fullVersion = await notesStore.getVersion(props.noteId, version.id)
    previewContent.value = fullVersion.content
    showPreview.value = true
  } catch (error) {
    uiStore.showError('Fehler beim Laden der Vorschau')
  }
}

// Restore version
async function restore(version) {
  if (!confirm(`Version ${version.version_number} wiederherstellen? Der aktuelle Inhalt wird als neue Version gespeichert.`)) {
    return
  }

  isRestoring.value = true
  try {
    await notesStore.restoreVersion(props.noteId, version.id)
    uiStore.showSuccess(`Version ${version.version_number} wiederhergestellt`)
    emit('restored')
    emit('close')
  } catch (error) {
    uiStore.showError('Fehler beim Wiederherstellen')
  } finally {
    isRestoring.value = false
  }
}

// Close preview
function closePreview() {
  showPreview.value = false
  selectedVersion.value = null
  previewContent.value = null
}

// Watch for show changes
watch(() => props.show, (show) => {
  if (show) {
    loadVersions()
    showPreview.value = false
    selectedVersion.value = null
  }
})
</script>

<template>
  <Teleport to="body">
    <div
      v-if="show"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/60"
      @click.self="$emit('close')"
    >
      <div class="bg-dark-800 rounded-xl shadow-xl w-full max-w-4xl max-h-[80vh] flex flex-col overflow-hidden">
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-dark-700">
          <div class="flex items-center gap-3">
            <ClockIcon class="w-5 h-5 text-primary-400" />
            <div>
              <h2 class="text-lg font-semibold text-white">Versionsgeschichte</h2>
              <p class="text-sm text-gray-500">{{ currentTitle }}</p>
            </div>
          </div>
          <button
            @click="$emit('close')"
            class="p-2 text-gray-400 hover:text-white hover:bg-dark-700 rounded-lg"
          >
            <XMarkIcon class="w-5 h-5" />
          </button>
        </div>

        <!-- Content -->
        <div class="flex-1 flex overflow-hidden">
          <!-- Versions list -->
          <div :class="['overflow-y-auto border-r border-dark-700', showPreview ? 'w-1/3' : 'w-full']">
            <!-- Loading -->
            <div v-if="isLoading" class="flex items-center justify-center py-12">
              <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-500"></div>
            </div>

            <!-- Empty -->
            <div v-else-if="versions.length === 0" class="text-center py-12 text-gray-500">
              <ClockIcon class="w-12 h-12 mx-auto mb-3 opacity-50" />
              <p>Keine Versionen vorhanden</p>
            </div>

            <!-- Versions -->
            <div v-else class="divide-y divide-dark-700">
              <div
                v-for="version in versions"
                :key="version.id"
                :class="[
                  'p-4 hover:bg-dark-700/50 transition-colors cursor-pointer',
                  selectedVersion?.id === version.id ? 'bg-primary-600/10' : ''
                ]"
                @click="preview(version)"
              >
                <div class="flex items-start justify-between gap-4">
                  <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                      <span class="text-sm font-medium text-white">
                        Version {{ version.version_number }}
                      </span>
                      <span
                        v-if="version.version_number === versions[0]?.version_number"
                        class="text-xs px-1.5 py-0.5 rounded bg-green-500/20 text-green-400"
                      >
                        Aktuell
                      </span>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">
                      {{ formatRelativeTime(version.created_at) }}
                    </p>
                    <p v-if="version.change_summary" class="text-sm text-gray-400 mt-1 truncate">
                      {{ version.change_summary }}
                    </p>
                    <div class="flex items-center gap-3 mt-2 text-xs text-gray-500">
                      <span v-if="version.created_by_name">von {{ version.created_by_name }}</span>
                      <span>{{ version.word_count }} Wörter</span>
                    </div>
                  </div>
                  <div class="flex items-center gap-1">
                    <button
                      @click.stop="preview(version)"
                      class="p-1.5 text-gray-500 hover:text-white hover:bg-dark-600 rounded"
                      title="Vorschau"
                    >
                      <EyeIcon class="w-4 h-4" />
                    </button>
                    <button
                      v-if="version.version_number !== versions[0]?.version_number"
                      @click.stop="restore(version)"
                      :disabled="isRestoring"
                      class="p-1.5 text-gray-500 hover:text-primary-400 hover:bg-dark-600 rounded disabled:opacity-50"
                      title="Wiederherstellen"
                    >
                      <ArrowPathIcon class="w-4 h-4" />
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Preview panel -->
          <div v-if="showPreview" class="w-2/3 flex flex-col overflow-hidden">
            <div class="flex items-center justify-between px-4 py-3 border-b border-dark-700 bg-dark-700/50">
              <div class="flex items-center gap-2">
                <DocumentTextIcon class="w-4 h-4 text-gray-400" />
                <span class="text-sm font-medium text-white">
                  Version {{ selectedVersion?.version_number }}
                </span>
              </div>
              <div class="flex items-center gap-2">
                <button
                  v-if="selectedVersion?.version_number !== versions[0]?.version_number"
                  @click="restore(selectedVersion)"
                  :disabled="isRestoring"
                  class="flex items-center gap-1.5 px-3 py-1.5 text-sm bg-primary-600 hover:bg-primary-700 text-white rounded-lg disabled:opacity-50"
                >
                  <ArrowPathIcon class="w-4 h-4" />
                  Wiederherstellen
                </button>
                <button
                  @click="closePreview"
                  class="p-1.5 text-gray-400 hover:text-white hover:bg-dark-600 rounded"
                >
                  <XMarkIcon class="w-4 h-4" />
                </button>
              </div>
            </div>
            <div class="flex-1 overflow-y-auto p-4">
              <div
                v-if="previewContent"
                class="prose prose-invert prose-sm max-w-none"
                v-html="previewContent"
              />
              <div v-else class="flex items-center justify-center h-full text-gray-500">
                Vorschau wird geladen...
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.prose :deep(h1) {
  @apply text-xl font-bold text-white mt-4 mb-2;
}
.prose :deep(h2) {
  @apply text-lg font-semibold text-white mt-3 mb-2;
}
.prose :deep(h3) {
  @apply text-base font-semibold text-white mt-2 mb-1;
}
.prose :deep(p) {
  @apply text-gray-300 mb-2;
}
.prose :deep(ul), .prose :deep(ol) {
  @apply ml-4 mb-2;
}
.prose :deep(li) {
  @apply text-gray-300 mb-1;
}
.prose :deep(blockquote) {
  @apply border-l-4 border-primary-500 pl-4 italic text-gray-400;
}
.prose :deep(code) {
  @apply bg-dark-700 px-1.5 py-0.5 rounded text-primary-400 text-sm;
}
.prose :deep(pre) {
  @apply bg-dark-700 rounded-lg p-4 overflow-x-auto;
}
.prose :deep(table) {
  @apply w-full border-collapse;
}
.prose :deep(th), .prose :deep(td) {
  @apply border border-dark-600 p-2 text-left;
}
.prose :deep(th) {
  @apply bg-dark-700 font-semibold;
}
</style>
