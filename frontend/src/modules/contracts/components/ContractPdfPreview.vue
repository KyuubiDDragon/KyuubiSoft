<script setup>
import { ref, watch, computed } from 'vue'
import { XMarkIcon, ArrowDownTrayIcon, CodeBracketIcon, EyeIcon, ArrowPathIcon } from '@heroicons/vue/24/outline'
import { useContractHtml } from '../composables/useContractHtml'

const props = defineProps({
  show: Boolean,
  contract: { type: Object, default: null },
  inline: { type: Boolean, default: false },
})

const emit = defineEmits(['close', 'download'])

const { generateHtml } = useContractHtml()
const editMode = ref(false)
const editedHtml = ref('')

const previewHtml = computed(() => {
  if (!props.contract) return ''
  return generateHtml(props.contract)
})

const hasEdits = computed(() => editedHtml.value !== '' && editedHtml.value !== previewHtml.value)
const displayHtml = computed(() => hasEdits.value ? editedHtml.value : previewHtml.value)

function toggleEditMode() {
  if (!editMode.value && !hasEdits.value) {
    editedHtml.value = previewHtml.value
  }
  editMode.value = !editMode.value
}

function resetEdits() {
  editedHtml.value = previewHtml.value
}

function handleDownload() {
  emit('download', props.contract, hasEdits.value ? editedHtml.value : null)
}

// Reset edit mode when a different contract is opened
watch(() => props.contract?.id, () => {
  editMode.value = false
  editedHtml.value = ''
})
</script>

<template>
  <!-- Inline mode (embedded in detail panel) -->
  <div v-if="inline && contract" class="flex flex-col h-full min-h-[600px]">
    <!-- Preview toolbar -->
    <div class="flex items-center justify-between px-6 py-3 border-b border-white/[0.06] flex-none">
      <div class="flex items-center gap-2">
        <p class="text-sm text-gray-400">
          A4-Vorschau · Aktualisiert sich automatisch
          <span v-if="hasEdits" class="ml-2 text-amber-400 font-medium">· Manuell bearbeitet</span>
        </p>
      </div>
      <div class="flex items-center gap-2">
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
        <button
          v-if="hasEdits"
          @click="resetEdits"
          class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm text-gray-400 hover:text-white hover:bg-white/[0.04] transition-colors"
        >
          <ArrowPathIcon class="w-3.5 h-3.5" />
          Zurücksetzen
        </button>
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

    <!-- A4 Preview area -->
    <div v-if="!editMode" class="flex-1 overflow-auto bg-white/[0.02] p-6">
      <div class="max-w-[794px] mx-auto">
        <div class="relative shadow-float rounded-sm overflow-hidden" style="aspect-ratio: 210 / 297;">
          <iframe
            v-if="displayHtml"
            :srcdoc="displayHtml"
            class="w-full h-full border-0 bg-white"
            sandbox="allow-same-origin"
            title="Vertragsvorschau"
          ></iframe>
          <div v-else class="w-full h-full bg-white flex items-center justify-center">
            <div class="w-8 h-8 border-4 border-gray-200 border-t-primary-400 rounded-full animate-spin"></div>
          </div>
        </div>
        <p class="text-center text-xs text-gray-600 mt-3">Dies ist eine Vorschau. Das endgültige PDF kann leicht abweichen.</p>
      </div>
    </div>
  </div>

  <!-- Standalone modal mode -->
  <Teleport v-else to="body">
    <Transition
      enter-active-class="transition ease-out duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition ease-in duration-150"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="show && contract"
        class="fixed inset-0 bg-black/70 backdrop-blur-md flex items-center justify-center z-50 p-6"
        @click.self="$emit('close')"
      >
        <div class="w-full max-w-4xl max-h-[95vh] flex flex-col bg-[#1a1b23] rounded-2xl border border-white/[0.08] shadow-2xl overflow-hidden">
          <!-- Header -->
          <div class="flex items-center justify-between px-6 py-3 border-b border-white/[0.06]">
            <div class="flex items-center gap-3">
              <h2 class="text-base font-bold text-white">{{ contract.contract_number }} — Vorschau</h2>
              <span v-if="hasEdits" class="text-xs text-amber-400 font-medium">Manuell bearbeitet</span>
            </div>
            <div class="flex items-center gap-2">
              <!-- Edit toggle -->
              <button
                @click="toggleEditMode"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium transition-colors"
                :class="editMode
                  ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30'
                  : 'text-gray-400 hover:text-white hover:bg-white/[0.04] border border-transparent'"
              >
                <CodeBracketIcon v-if="!editMode" class="w-3.5 h-3.5" />
                <EyeIcon v-else class="w-3.5 h-3.5" />
                {{ editMode ? 'Vorschau' : 'HTML bearbeiten' }}
              </button>
              <!-- Reset -->
              <button
                v-if="hasEdits"
                @click="resetEdits"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs text-gray-400 hover:text-white hover:bg-white/[0.04] transition-colors"
              >
                <ArrowPathIcon class="w-3.5 h-3.5" />
                Zurücksetzen
              </button>
              <!-- Download -->
              <button @click="handleDownload" class="flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-medium bg-primary-600 text-white hover:bg-primary-500">
                <ArrowDownTrayIcon class="w-3.5 h-3.5" /> PDF herunterladen
              </button>
              <button @click="$emit('close')" class="p-2 rounded-lg text-gray-400 hover:text-white hover:bg-white/[0.04]">
                <XMarkIcon class="w-5 h-5" />
              </button>
            </div>
          </div>

          <!-- Content -->
          <div v-if="editMode" class="flex-1 overflow-hidden flex">
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

          <!-- Preview -->
          <div v-else class="flex-1 overflow-y-auto bg-white/[0.02] p-6">
            <div class="max-w-[794px] mx-auto">
              <div class="relative shadow-float rounded-sm overflow-hidden" style="aspect-ratio: 210 / 297;">
                <iframe
                  v-if="displayHtml"
                  :srcdoc="displayHtml"
                  class="w-full h-full border-0 bg-white"
                  sandbox="allow-same-origin"
                  title="Vertragsvorschau"
                ></iframe>
                <div v-else class="w-full h-full bg-white flex items-center justify-center">
                  <div class="w-8 h-8 border-4 border-gray-200 border-t-primary-400 rounded-full animate-spin"></div>
                </div>
              </div>
              <p class="text-center text-xs text-gray-600 mt-3">Dies ist eine Vorschau. Das endgültige PDF kann leicht abweichen.</p>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
