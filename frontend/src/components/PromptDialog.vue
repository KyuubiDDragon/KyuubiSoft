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
          class="absolute inset-0 bg-black/60 backdrop-blur-sm"
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
            class="relative bg-dark-800 border border-dark-700 rounded-xl shadow-2xl max-w-md w-full overflow-hidden"
          >
            <!-- Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-dark-700">
              <h3 class="text-lg font-semibold text-white">
                {{ dialogConfig.title }}
              </h3>
              <button
                @click="handleCancel"
                class="p-1.5 text-gray-400 hover:text-white hover:bg-dark-700 rounded-lg transition-colors"
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
                class="w-full px-4 py-2.5 bg-dark-700 border border-dark-600 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                @keydown="onInputKeydown"
              />
            </div>

            <!-- Footer -->
            <div class="flex justify-end gap-3 px-6 py-4 bg-dark-700/30 border-t border-dark-700">
              <button
                @click="handleCancel"
                class="px-4 py-2 text-sm font-medium text-gray-300 hover:text-white bg-dark-700 hover:bg-dark-600 rounded-lg transition-colors"
              >
                {{ dialogConfig.cancelText }}
              </button>
              <button
                @click="handleConfirm"
                class="px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-dark-800 focus:ring-primary-500"
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
