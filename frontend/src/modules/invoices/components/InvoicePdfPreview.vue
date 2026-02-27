<script setup>
import { ref, watch, computed } from 'vue'
import { useInvoiceHtml } from '../composables/useInvoiceHtml'
import { ArrowDownTrayIcon, ArrowPathIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  invoice: { type: Object, required: true },
  senderSettings: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['download'])

const { generateHtml, loadLogoDataUrl } = useInvoiceHtml()

const htmlContent = ref('')
const isLoading = ref(false)
const logoDataUrl = ref('')
const logoFileId = ref(null)

async function refreshPreview() {
  isLoading.value = true
  try {
    const currentLogoId = props.invoice.sender_logo_file_id || props.senderSettings?.logo_file_id
    if (currentLogoId && currentLogoId !== logoFileId.value) {
      logoFileId.value = currentLogoId
      logoDataUrl.value = await loadLogoDataUrl(currentLogoId)
    }
    htmlContent.value = generateHtml(props.invoice, props.senderSettings, logoDataUrl.value)
  } finally {
    isLoading.value = false
  }
}

// Re-generate when invoice changes
watch(() => props.invoice, () => refreshPreview(), { deep: true, immediate: true })
</script>

<template>
  <div class="flex flex-col h-full min-h-[600px]">
    <!-- Preview toolbar -->
    <div class="flex items-center justify-between px-6 py-3 border-b border-dark-700 flex-none">
      <div class="flex items-center gap-2">
        <div v-if="isLoading" class="flex items-center gap-2 text-gray-400 text-sm">
          <ArrowPathIcon class="w-4 h-4 animate-spin" />
          Vorschau wird aktualisiert...
        </div>
        <p v-else class="text-sm text-gray-400">A4-Vorschau · Aktualisiert sich automatisch</p>
      </div>
      <button
        @click="$emit('download')"
        class="flex items-center gap-2 px-4 py-2 bg-primary-600 hover:bg-primary-500 text-white rounded-lg text-sm transition-colors font-medium"
      >
        <ArrowDownTrayIcon class="w-4 h-4" />
        PDF herunterladen
      </button>
    </div>

    <!-- A4 Preview area -->
    <div class="flex-1 overflow-auto bg-dark-900 p-6">
      <div class="max-w-[794px] mx-auto">
        <!-- A4 shadow frame -->
        <div class="relative shadow-2xl rounded-sm overflow-hidden" style="aspect-ratio: 210 / 297;">
          <iframe
            v-if="htmlContent"
            :srcdoc="htmlContent"
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
