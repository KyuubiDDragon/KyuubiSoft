<script setup>
import { ref, watch, nextTick } from 'vue'
import { isOpen, inputValue, dialogConfig, usePromptDialog } from '@/composables/usePromptDialog'
import { XMarkIcon } from '@heroicons/vue/24/outline'

const { handleConfirm, handleCancel } = usePromptDialog()
const inputRef = ref(null)

// Focus input when dialog opens
watch(isOpen, async (open) => {
  if (open) {
    await nextTick()
    inputRef.value?.focus()
    inputRef.value?.select()
  }
})

function onKeydown(e) {
  if (e.key === 'Escape') {
    handleCancel()
  }
}

function onInputKeydown(e) {
  if (e.key === 'Enter') {
    e.preventDefault()
    e.stopPropagation()
    handleConfirm()
  }
}
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition-opacity duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-150"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="isOpen"
        class="fixed inset-0 z-[100] flex items-center justify-center p-4"
        @keydown="onKeydown"
      >
        <!-- Backdrop -->
        <div
          class="absolute inset-0 bg-black/60 backdrop-blur-md"
          @click="handleCancel"
        />

        <!-- Dialog -->
        <Transition
          enter-active-class="transition-all duration-200"
          enter-from-class="opacity-0 scale-95"
          enter-to-class="opacity-100 scale-100"
          leave-active-class="transition-all duration-150"
          leave-from-class="opacity-100 scale-100"
          leave-to-class="opacity-0 scale-95"
        >
          <div
            v-if="isOpen"
            class="relative modal max-w-md w-full overflow-hidden"
          >
            <!-- Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-white/[0.06]">
              <h3 class="text-lg font-semibold text-white">
                {{ dialogConfig.title }}
              </h3>
              <button
                @click="handleCancel"
                class="p-1.5 text-gray-400 hover:text-white hover:bg-white/[0.04] rounded-lg transition-colors"
              >
                <XMarkIcon class="w-5 h-5" />
              </button>
            </div>

            <!-- Content -->
            <div class="px-6 py-5">
              <p v-if="dialogConfig.message" class="text-gray-300 mb-4">
                {{ dialogConfig.message }}
              </p>
              <input
                ref="inputRef"
                v-model="inputValue"
                :type="dialogConfig.inputType"
                :placeholder="dialogConfig.placeholder"
                autocomplete="off"
                class="input"
                @keydown="onInputKeydown"
              />
            </div>

            <!-- Footer -->
            <div class="flex justify-end gap-3 px-6 py-4 bg-white/[0.02] border-t border-white/[0.06]">
              <button
                @click="handleCancel"
                class="btn-secondary btn-sm"
              >
                {{ dialogConfig.cancelText }}
              </button>
              <button
                @click="handleConfirm"
                class="btn-primary btn-sm"
              >
                {{ dialogConfig.confirmText }}
              </button>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>
