<script setup>
import { ref, watch, computed } from 'vue'
import { useInvoiceHtml } from '../composables/useInvoiceHtml'
import { ArrowDownTrayIcon, ArrowPathIcon, CodeBracketIcon, EyeIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  invoice: { type: Object, required: true },
  senderSettings: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['download'])

const { generateHtml, loadLogoDataUrl } = useInvoiceHtml()

const htmlContent = ref('')
const editedHtml = ref('')
const isLoading = ref(false)
const logoDataUrl = ref('')
const logoFileId = ref(null)
const editMode = ref(false)

// Track whether user has made manual edits (persists across edit/preview toggle)
const hasEdits = computed(() => editedHtml.value !== '' && editedHtml.value !== htmlContent.value)

// What to show in the iframe - edited version if user changed it, otherwise original
const displayHtml = computed(() => hasEdits.value ? editedHtml.value : htmlContent.value)

async function refreshPreview() {
  isLoading.value = true
  try {
    const currentLogoId = props.invoice.sender_logo_file_id || props.senderSettings?.logo_file_id
    if (currentLogoId && currentLogoId !== logoFileId.value) {
      logoFileId.value = currentLogoId
      logoDataUrl.value = await loadLogoDataUrl(currentLogoId)
    }
    htmlContent.value = generateHtml(props.invoice, props.senderSettings, logoDataUrl.value)
    // Only reset edited HTML if user hasn't manually edited
    if (!hasEdits.value) {
      editedHtml.value = htmlContent.value
    }
  } finally {
    isLoading.value = false
  }
}

function toggleEditMode() {
  if (!editMode.value && !hasEdits.value) {
    editedHtml.value = htmlContent.value
  }
  editMode.value = !editMode.value
}

function resetEdits() {
  editedHtml.value = htmlContent.value
}

function handleDownload() {
  emit('download', hasEdits.value ? editedHtml.value : null)
}

// Re-generate when invoice changes
watch(() => props.invoice, () => refreshPreview(), { deep: true, immediate: true })
</script>

<template>
  <div class="flex flex-col h-full min-h-[600px]">
    <!-- Preview toolbar -->
    <div class="flex items-center justify-between px-6 py-3 border-b border-white/[0.06] flex-none">
      <div class="flex items-center gap-2">
        <div v-if="isLoading" class="flex items-center gap-2 text-gray-400 text-sm">
          <ArrowPathIcon class="w-4 h-4 animate-spin" />
          Vorschau wird aktualisiert...
        </div>
        <p v-else class="text-sm text-gray-400">
          A4-Vorschau · Aktualisiert sich automatisch
          <span v-if="hasEdits" class="ml-2 text-amber-400 font-medium">· Manuell bearbeitet</span>
        </p>
      </div>
      <div class="flex items-center gap-2">
        <!-- Edit toggle -->
        <button
          @click="toggleEditMode"
          class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm transition-colors"
          :class="editMode
            ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30'
            : 'text-gray-400 hover:text-white hover:bg-white/[0.04] border border-transparent'"
        >
          <CodeBracketIcon v-if="!editMode" class="w-4 h-4" />
          <EyeIcon v-else class="w-4 h-4" />
          {{ editMode ? 'Vorschau' : 'HTML bearbeiten' }}
        </button>
        <!-- Reset -->
        <button
          v-if="hasEdits"
          @click="resetEdits"
          class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm text-gray-400 hover:text-white hover:bg-white/[0.04] transition-colors"
        >
          <ArrowPathIcon class="w-3.5 h-3.5" />
          Zurücksetzen
        </button>
        <!-- Download -->
        <button
          @click="handleDownload"
          class="flex items-center gap-2 px-4 py-2 bg-primary-600 hover:bg-primary-500 text-white rounded-lg text-sm transition-colors font-medium"
        >
          <ArrowDownTrayIcon class="w-4 h-4" />
          PDF herunterladen
        </button>
      </div>
    </div>

    <!-- Content area -->
    <div v-if="editMode" class="flex-1 overflow-hidden flex">
      <!-- HTML Editor -->
      <div class="flex-1 flex flex-col min-w-0">
        <div class="px-4 py-2 bg-white/[0.02] border-b border-white/[0.06] flex items-center justify-between">
          <span class="text-xs text-gray-500 font-mono">HTML-Quelltext</span>
          <span v-if="hasEdits" class="text-xs text-amber-400">Geändert</span>
        </div>
        <textarea
          v-model="editedHtml"
          class="flex-1 w-full bg-[#0d0e12] text-gray-300 font-mono text-xs leading-relaxed p-4 resize-none focus:outline-none border-none"
          spellcheck="false"
          wrap="off"
        ></textarea>
      </div>
    </div>

    <!-- A4 Preview area (shown when not in edit mode OR always for reference) -->
    <div v-if="!editMode" class="flex-1 overflow-auto bg-white/[0.02] p-6">
      <div class="max-w-[794px] mx-auto">
        <!-- A4 shadow frame -->
        <div class="relative shadow-float rounded-sm overflow-hidden" style="aspect-ratio: 210 / 297;">
          <iframe
            v-if="displayHtml"
            :srcdoc="displayHtml"
            class="w-full h-full border-0 bg-white"
            sandbox="allow-same-origin"
            title="Rechnungsvorschau"
          ></iframe>
          <div v-else class="w-full h-full bg-white flex items-center justify-center">
            <div class="w-8 h-8 border-4 border-gray-200 border-t-primary-400 rounded-full animate-spin"></div>
          </div>
        </div>
        <p class="text-center text-xs text-gray-600 mt-3">Dies ist eine Vorschau. Das endgültige PDF kann leicht abweichen.</p>
      </div>
    </div>
  </div>
</template>
