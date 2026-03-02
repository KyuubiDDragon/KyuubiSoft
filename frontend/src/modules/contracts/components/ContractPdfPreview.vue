<script setup>
import { ref, watch, computed } from 'vue'
import { XMarkIcon, ArrowDownTrayIcon, CodeBracketIcon, EyeIcon, ArrowPathIcon } from '@heroicons/vue/24/outline'
import { useContractHtml } from '../composables/useContractHtml'

const props = defineProps({
  show: Boolean,
  contract: { type: Object, default: null },
})

const emit = defineEmits(['close', 'download'])

const { generateHtml } = useContractHtml()
const iframeRef = ref(null)
const editMode = ref(false)
const editedHtml = ref('')

const previewHtml = computed(() => {
  if (!props.contract) return ''
  return generateHtml(props.contract)
})

const hasEdits = computed(() => editMode.value && editedHtml.value !== previewHtml.value)
const displayHtml = computed(() => hasEdits.value ? editedHtml.value : previewHtml.value)

function resizeIframe() {
  const iframe = iframeRef.value
  if (iframe?.contentDocument?.body) {
    iframe.style.height = iframe.contentDocument.body.scrollHeight + 'px'
  }
}

function toggleEditMode() {
  if (!editMode.value) {
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

// Reset edit mode when contract changes
watch(() => props.contract, () => {
  editMode.value = false
  editedHtml.value = ''
})
</script>

<template>
  <Teleport to="body">
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
            <!-- HTML Editor -->
            <textarea
              v-model="editedHtml"
              class="flex-1 w-full bg-[#0d0e12] text-gray-300 font-mono text-xs leading-relaxed p-4 resize-none focus:outline-none border-none"
              spellcheck="false"
              wrap="off"
            ></textarea>
          </div>

          <!-- Preview -->
          <div v-else class="flex-1 overflow-y-auto bg-gray-200 p-6">
            <div class="mx-auto bg-white shadow-lg" style="width:210mm; max-width:100%;">
              <iframe
                ref="iframeRef"
                :srcdoc="displayHtml"
                class="w-full border-0"
                scrolling="no"
                style="overflow:hidden;"
                @load="resizeIframe"
                sandbox="allow-same-origin"
              ></iframe>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
