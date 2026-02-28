<script setup>
import { ref, watch, computed } from 'vue'
import { XMarkIcon, ArrowDownTrayIcon } from '@heroicons/vue/24/outline'
import { useContractHtml } from '../composables/useContractHtml'

const props = defineProps({
  show: Boolean,
  contract: { type: Object, default: null },
})

const emit = defineEmits(['close', 'download'])

const { generateHtml } = useContractHtml()

const previewHtml = computed(() => {
  if (!props.contract) return ''
  return generateHtml(props.contract)
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
        <div class="w-full max-w-3xl max-h-[95vh] flex flex-col bg-[#1a1b23] rounded-2xl border border-white/[0.08] shadow-2xl overflow-hidden">
          <!-- Header -->
          <div class="flex items-center justify-between px-6 py-3 border-b border-white/[0.06]">
            <div class="flex items-center gap-3">
              <h2 class="text-base font-bold text-white">{{ contract.contract_number }} — Vorschau</h2>
            </div>
            <div class="flex items-center gap-2">
              <button @click="$emit('download', contract)" class="flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-medium bg-primary-600 text-white hover:bg-primary-500">
                <ArrowDownTrayIcon class="w-3.5 h-3.5" /> PDF herunterladen
              </button>
              <button @click="$emit('close')" class="p-2 rounded-lg text-gray-400 hover:text-white hover:bg-white/[0.04]">
                <XMarkIcon class="w-5 h-5" />
              </button>
            </div>
          </div>

          <!-- Preview -->
          <div class="flex-1 overflow-y-auto bg-gray-200 p-6">
            <div class="mx-auto bg-white shadow-lg" style="width:210mm; min-height:297mm; max-width:100%;">
              <iframe
                :srcdoc="previewHtml"
                class="w-full border-0"
                style="min-height:297mm;"
                sandbox="allow-same-origin"
              ></iframe>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
