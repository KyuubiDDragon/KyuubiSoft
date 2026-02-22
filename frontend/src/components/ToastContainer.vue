<script setup>
import { toasts, useToast } from '@/composables/useToast'
import {
  CheckCircleIcon,
  ExclamationCircleIcon,
  ExclamationTriangleIcon,
  InformationCircleIcon,
  XMarkIcon
} from '@heroicons/vue/24/outline'

const { removeToast } = useToast()

const iconMap = {
  success: CheckCircleIcon,
  error: ExclamationCircleIcon,
  warning: ExclamationTriangleIcon,
  info: InformationCircleIcon,
}

const styleMap = {
  success: {
    wrapper: 'bg-dark-800 border-green-500/25',
    icon: 'text-green-400',
    bar: 'bg-green-500',
  },
  error: {
    wrapper: 'bg-dark-800 border-red-500/25',
    icon: 'text-red-400',
    bar: 'bg-red-500',
  },
  warning: {
    wrapper: 'bg-dark-800 border-yellow-500/25',
    icon: 'text-yellow-400',
    bar: 'bg-yellow-500',
  },
  info: {
    wrapper: 'bg-dark-800 border-primary-500/25',
    icon: 'text-primary-400',
    bar: 'bg-primary-500',
  },
}
</script>

<template>
  <Teleport to="body">
    <!-- Bottom-right toast stack -->
    <div class="fixed bottom-5 right-5 z-[100] flex flex-col-reverse gap-2.5 max-w-sm w-full pointer-events-none">
      <TransitionGroup
        enter-active-class="transition-all duration-250 ease-out"
        enter-from-class="opacity-0 translate-y-4 scale-95"
        enter-to-class="opacity-100 translate-y-0 scale-100"
        leave-active-class="transition-all duration-200 ease-in absolute"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 translate-y-2"
      >
        <div
          v-for="t in toasts"
          :key="t.id"
          class="pointer-events-auto rounded-xl border shadow-lg overflow-hidden"
          :class="(styleMap[t.type] || styleMap.info).wrapper"
        >
          <!-- Main content -->
          <div class="flex items-start gap-3 px-4 py-3.5">
            <component
              :is="iconMap[t.type] || iconMap.info"
              class="w-5 h-5 shrink-0 mt-0.5"
              :class="(styleMap[t.type] || styleMap.info).icon"
            />
            <p class="flex-1 text-sm text-gray-200 leading-snug">{{ t.message }}</p>
            <button
              @click="removeToast(t.id)"
              class="shrink-0 p-1 rounded-md hover:bg-white/10 transition-colors ml-1"
            >
              <XMarkIcon class="w-3.5 h-3.5 text-gray-500" />
            </button>
          </div>

          <!-- Progress bar (auto-dismiss indicator) -->
          <div
            v-if="t.duration > 0"
            class="h-0.5 opacity-50"
            :class="(styleMap[t.type] || styleMap.info).bar"
            :style="{
              animation: `toast-progress ${t.duration}ms linear forwards`
            }"
          />
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>
