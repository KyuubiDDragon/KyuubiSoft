<script setup>
import { ref, onMounted } from 'vue'
import { useNotesStore } from '../../stores/notesStore'
import { useUiStore } from '@/stores/ui'
import {
  XMarkIcon,
  ClockIcon,
  ArrowPathIcon,
  EyeIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
  noteId: {
    type: String,
    required: true
  }
})

const emit = defineEmits(['close'])

const notesStore = useNotesStore()
const uiStore = useUiStore()

const versions = ref([])
const isLoading = ref(true)
const selectedVersion = ref(null)
const previewContent = ref('')
const isRestoring = ref(false)

onMounted(async () => {
  try {
    versions.value = await notesStore.fetchVersions(props.noteId)
  } catch (error) {
    uiStore.showError('Fehler beim Laden der Versionen')
  } finally {
    isLoading.value = false
  }
})

async function loadPreview(version) {
  selectedVersion.value = version
  // In a real implementation, you might fetch the full version content
  previewContent.value = version.content || 'Vorschau nicht verfügbar'
}

async function restoreVersion(version) {
  if (!confirm(`Version ${version.version_number} wiederherstellen?`)) return

  isRestoring.value = true
  try {
    await notesStore.restoreVersion(props.noteId, version.id)
    uiStore.showSuccess(`Version ${version.version_number} wiederhergestellt`)
    emit('close')
  } catch (error) {
    uiStore.showError('Fehler beim Wiederherstellen')
  } finally {
    isRestoring.value = false
  }
}

function formatDate(dateStr) {
  if (!dateStr) return ''
  return new Date(dateStr).toLocaleString('de-DE', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

function formatRelativeTime(dateStr) {
  if (!dateStr) return ''
  const date = new Date(dateStr)
  const now = new Date()
  const diff = now - date
  const minutes = Math.floor(diff / 60000)
  const hours = Math.floor(minutes / 60)
  const days = Math.floor(hours / 24)

  if (minutes < 60) return `vor ${minutes} Min`
  if (hours < 24) return `vor ${hours} Std`
  if (days < 7) return `vor ${days} Tagen`
  return formatDate(dateStr)
}
</script>

<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <!-- Backdrop -->
    <div
      class="absolute inset-0 bg-black/50 backdrop-blur-sm"
      @click="$emit('close')"
    />

    <!-- Modal -->
    <div class="relative w-full max-w-4xl h-[80vh] bg-dark-800 rounded-xl shadow-2xl border border-dark-600 overflow-hidden flex flex-col">
      <!-- Header -->
      <div class="flex items-center justify-between px-6 py-4 border-b border-dark-600">
        <h2 class="text-lg font-semibold text-white flex items-center gap-2">
          <ClockIcon class="h-5 w-5" />
          Versionshistorie
        </h2>
        <button
          @click="$emit('close')"
          class="rounded p-1 text-gray-400 hover:bg-dark-700 hover:text-white"
        >
          <XMarkIcon class="h-5 w-5" />
        </button>
      </div>

      <!-- Content -->
      <div class="flex-1 flex overflow-hidden">
        <!-- Versions list -->
        <div class="w-80 border-r border-dark-600 overflow-y-auto">
          <!-- Loading -->
          <div v-if="isLoading" class="p-4 text-center text-gray-500">
            Laden...
          </div>

          <!-- Empty state -->
          <div v-else-if="versions.length === 0" class="p-8 text-center text-gray-500">
            <ClockIcon class="h-12 w-12 mx-auto mb-2 opacity-50" />
            <p>Keine Versionen verfügbar</p>
          </div>

          <!-- Versions -->
          <div v-else class="divide-y divide-dark-700">
            <button
              v-for="version in versions"
              :key="version.id"
              @click="loadPreview(version)"
              :class="[
                'w-full p-4 text-left transition-colors',
                selectedVersion?.id === version.id
                  ? 'bg-dark-700'
                  : 'hover:bg-dark-700/50'
              ]"
            >
              <div class="flex items-center justify-between mb-1">
                <span class="font-medium text-white">
                  Version {{ version.version_number }}
                </span>
                <span class="text-xs text-gray-500">
                  {{ formatRelativeTime(version.created_at) }}
                </span>
              </div>

              <div v-if="version.change_summary" class="text-sm text-gray-400 mb-2">
                {{ version.change_summary }}
              </div>

              <div class="flex items-center gap-2 text-xs text-gray-500">
                <span>{{ version.word_count }} Wörter</span>
                <span v-if="version.created_by_name">
                  • {{ version.created_by_name }}
                </span>
              </div>
            </button>
          </div>
        </div>

        <!-- Preview -->
        <div class="flex-1 flex flex-col overflow-hidden">
          <div v-if="!selectedVersion" class="flex-1 flex items-center justify-center text-gray-500">
            <div class="text-center">
              <EyeIcon class="h-12 w-12 mx-auto mb-2 opacity-50" />
              <p>Wähle eine Version für die Vorschau</p>
            </div>
          </div>

          <template v-else>
            <!-- Preview header -->
            <div class="flex items-center justify-between px-4 py-3 border-b border-dark-700 bg-dark-850">
              <div>
                <div class="font-medium text-white">
                  {{ selectedVersion.title }}
                </div>
                <div class="text-xs text-gray-500">
                  {{ formatDate(selectedVersion.created_at) }}
                </div>
              </div>
              <button
                @click="restoreVersion(selectedVersion)"
                :disabled="isRestoring"
                class="flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
              >
                <ArrowPathIcon :class="['h-4 w-4', isRestoring ? 'animate-spin' : '']" />
                Wiederherstellen
              </button>
            </div>

            <!-- Preview content -->
            <div class="flex-1 overflow-y-auto p-4">
              <div
                class="prose prose-invert max-w-none"
                v-html="selectedVersion.content || previewContent"
              />
            </div>
          </template>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.bg-dark-850 {
  background-color: rgb(24, 24, 27);
}
</style>
