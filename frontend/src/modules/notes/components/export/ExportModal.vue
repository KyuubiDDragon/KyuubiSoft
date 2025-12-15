<script setup>
import { ref, computed } from 'vue'
import { useExport } from '../../composables/useExport'

const props = defineProps({
  show: {
    type: Boolean,
    default: false,
  },
  note: {
    type: Object,
    default: null,
  },
})

const emit = defineEmits(['close'])

const { isExporting, exportError, exportMarkdown, exportHTML, exportPDF, exportText, exportJSON } = useExport()

const selectedFormat = ref('markdown')

const formats = [
  {
    id: 'markdown',
    name: 'Markdown',
    description: 'Standard-Textformat für Dokumentation',
    icon: '📝',
    extension: '.md',
  },
  {
    id: 'html',
    name: 'HTML',
    description: 'Webseite mit Styling',
    icon: '🌐',
    extension: '.html',
  },
  {
    id: 'pdf',
    name: 'PDF',
    description: 'Druckbares Dokument',
    icon: '📄',
    extension: '.pdf',
  },
  {
    id: 'text',
    name: 'Text',
    description: 'Reiner Text ohne Formatierung',
    icon: '📃',
    extension: '.txt',
  },
  {
    id: 'json',
    name: 'JSON',
    description: 'Backup & Import Format',
    icon: '💾',
    extension: '.json',
  },
]

const selectedFormatInfo = computed(() => {
  return formats.find(f => f.id === selectedFormat.value)
})

async function handleExport() {
  if (!props.note) return

  let success = false

  switch (selectedFormat.value) {
    case 'markdown':
      success = exportMarkdown(props.note)
      break
    case 'html':
      success = exportHTML(props.note)
      break
    case 'pdf':
      success = await exportPDF(props.note)
      break
    case 'text':
      success = exportText(props.note)
      break
    case 'json':
      success = exportJSON(props.note)
      break
  }

  if (success) {
    emit('close')
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
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
      <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm"
        @click="handleBackdropClick"
      >
        <div class="bg-dark-800 rounded-xl shadow-2xl w-full max-w-lg mx-4 overflow-hidden">
          <!-- Header -->
          <div class="flex items-center justify-between px-6 py-4 border-b border-dark-700">
            <div>
              <h2 class="text-xl font-semibold text-white">Notiz exportieren</h2>
              <p class="text-sm text-gray-500 mt-0.5">{{ note?.title || 'Untitled' }}</p>
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
            <!-- Format Selection -->
            <div class="space-y-2">
              <label class="block text-sm font-medium text-gray-300 mb-3">
                Export-Format wählen
              </label>

              <div class="grid grid-cols-1 gap-2">
                <button
                  v-for="format in formats"
                  :key="format.id"
                  @click="selectedFormat = format.id"
                  :class="[
                    'flex items-center gap-3 p-3 rounded-lg border transition-all text-left',
                    selectedFormat === format.id
                      ? 'border-primary-500 bg-primary-500/10'
                      : 'border-dark-600 hover:border-dark-500 bg-dark-700/50'
                  ]"
                >
                  <span class="text-2xl">{{ format.icon }}</span>
                  <div class="flex-1">
                    <div class="flex items-center gap-2">
                      <span class="font-medium text-white">{{ format.name }}</span>
                      <span class="text-xs text-gray-500 bg-dark-600 px-1.5 py-0.5 rounded">
                        {{ format.extension }}
                      </span>
                    </div>
                    <p class="text-sm text-gray-500">{{ format.description }}</p>
                  </div>
                  <div
                    :class="[
                      'w-5 h-5 rounded-full border-2 flex items-center justify-center',
                      selectedFormat === format.id
                        ? 'border-primary-500 bg-primary-500'
                        : 'border-dark-500'
                    ]"
                  >
                    <svg
                      v-if="selectedFormat === format.id"
                      class="w-3 h-3 text-white"
                      fill="currentColor"
                      viewBox="0 0 20 20"
                    >
                      <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                    </svg>
                  </div>
                </button>
              </div>
            </div>

            <!-- Error Message -->
            <div
              v-if="exportError"
              class="mt-4 p-3 bg-red-500/10 border border-red-500/20 rounded-lg text-red-400 text-sm"
            >
              {{ exportError }}
            </div>

            <!-- Info about selected format -->
            <div
              v-if="selectedFormatInfo"
              class="mt-4 p-3 bg-dark-700 rounded-lg text-sm text-gray-400"
            >
              <template v-if="selectedFormat === 'markdown'">
                Markdown ist ideal für Dokumentation und kann in vielen Editoren geöffnet werden.
                Wiki-Links werden als <code class="text-primary-400">[[Link]]</code> exportiert.
              </template>
              <template v-else-if="selectedFormat === 'html'">
                Die HTML-Datei enthält ein vollständiges Styling und kann direkt im Browser geöffnet werden.
              </template>
              <template v-else-if="selectedFormat === 'pdf'">
                Das PDF ist druckfertig und enthält alle Formatierungen. Optimal zum Teilen.
              </template>
              <template v-else-if="selectedFormat === 'text'">
                Nur der Textinhalt ohne Formatierung. Kompatibel mit allen Anwendungen.
              </template>
              <template v-else-if="selectedFormat === 'json'">
                Vollständiges Backup inklusive Metadaten. Kann später wieder importiert werden.
              </template>
            </div>
          </div>

          <!-- Footer -->
          <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-dark-700 bg-dark-800/50">
            <button
              @click="handleClose"
              class="px-4 py-2 text-sm font-medium text-gray-400 hover:text-white transition-colors"
            >
              Abbrechen
            </button>
            <button
              @click="handleExport"
              :disabled="isExporting"
              class="px-4 py-2 text-sm font-medium bg-primary-600 hover:bg-primary-700 text-white rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
            >
              <svg
                v-if="isExporting"
                class="w-4 h-4 animate-spin"
                fill="none"
                viewBox="0 0 24 24"
              >
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
              </svg>
              <svg
                v-else
                class="w-4 h-4"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
              </svg>
              {{ isExporting ? 'Exportiere...' : 'Exportieren' }}
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
