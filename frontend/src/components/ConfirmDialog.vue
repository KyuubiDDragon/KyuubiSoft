<script setup>
import { isOpen, dialogConfig, useConfirmDialog } from '@/composables/useConfirmDialog'
import {
  ExclamationTriangleIcon,
  TrashIcon,
  InformationCircleIcon,
  XMarkIcon
} from '@heroicons/vue/24/outline'

const { handleConfirm, handleCancel } = useConfirmDialog()

const iconMap = {
  warning: ExclamationTriangleIcon,
  danger: TrashIcon,
  info: InformationCircleIcon
}

const buttonColorMap = {
  warning: 'bg-yellow-600 hover:bg-yellow-700 focus:ring-yellow-500',
  danger: 'bg-red-600 hover:bg-red-700 focus:ring-red-500',
  info: 'bg-primary-600 hover:bg-primary-700 focus:ring-primary-500'
}

const iconColorMap = {
  warning: 'text-yellow-400 bg-yellow-400/10',
  danger: 'text-red-400 bg-red-400/10',
  info: 'text-primary-400 bg-primary-400/10'
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
            <div class="flex items-center gap-4 px-6 py-5">
              <div
                :class="[
                  'flex items-center justify-center w-12 h-12 rounded-full',
                  iconColorMap[dialogConfig.type] || iconColorMap.warning
                ]"
              >
                <component
                  :is="iconMap[dialogConfig.type] || iconMap.warning"
                  class="w-6 h-6"
                />
              </div>
              <div class="flex-1">
                <h3 class="text-lg font-semibold text-white">
                  {{ dialogConfig.title }}
                </h3>
              </div>
              <button
                @click="handleCancel"
                class="p-1.5 text-gray-400 hover:text-white hover:bg-dark-700 rounded-lg transition-colors"
              >
                <XMarkIcon class="w-5 h-5" />
              </button>
            </div>

            <!-- Content -->
            <div class="px-6 pb-6">
              <p class="text-gray-300 whitespace-pre-wrap">{{ dialogConfig.message }}</p>
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
                :class="[
                  'px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-dark-800',
                  buttonColorMap[dialogConfig.type] || buttonColorMap.warning
                ]"
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
