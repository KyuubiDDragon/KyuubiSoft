<script setup>
import { computed } from 'vue'
import { globalShortcuts, isShortcutsModalOpen } from '@/composables/useKeyboardShortcuts'
import { XMarkIcon } from '@heroicons/vue/24/outline'

const navigationShortcuts = computed(() =>
  globalShortcuts.filter(s => s.route)
)

const actionShortcuts = computed(() =>
  globalShortcuts.filter(s => s.action)
)

function close() {
  isShortcutsModalOpen.value = false
}

function formatKey(key) {
  return key
    .replace('Ctrl+', '⌘/')
    .replace('Alt+', '⌥')
    .replace('Shift+', '⇧')
    .replace(' ', ' → ')
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="isShortcutsModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4"
    >
      <!-- Backdrop -->
      <div
        class="absolute inset-0 bg-black/60 backdrop-blur-sm"
        @click="close"
      />

      <!-- Modal -->
      <div class="relative modal max-w-2xl w-full max-h-[80vh] overflow-hidden">
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-white/[0.06]">
          <h2 class="text-lg font-semibold text-white">Tastaturkürzel</h2>
          <button
            @click="close"
            class="p-1.5 text-gray-400 hover:text-white hover:bg-white/[0.04] rounded-lg transition-colors"
          >
            <XMarkIcon class="w-5 h-5" />
          </button>
        </div>

        <!-- Content -->
        <div class="p-6 overflow-y-auto max-h-[calc(80vh-8rem)]">
          <!-- Navigation -->
          <div class="mb-8">
            <h3 class="text-sm font-semibold text-gray-400 uppercase mb-4">Navigation</h3>
            <div class="grid grid-cols-2 gap-3">
              <div
                v-for="shortcut in navigationShortcuts"
                :key="shortcut.key"
                class="flex items-center justify-between p-3 bg-white/[0.03] rounded-xl"
              >
                <span class="text-gray-300 text-sm">{{ shortcut.description }}</span>
                <kbd class="kbd">
                  {{ formatKey(shortcut.key) }}
                </kbd>
              </div>
            </div>
          </div>

          <!-- Actions -->
          <div>
            <h3 class="text-sm font-semibold text-gray-400 uppercase mb-4">Aktionen</h3>
            <div class="grid grid-cols-2 gap-3">
              <div
                v-for="shortcut in actionShortcuts"
                :key="shortcut.key"
                class="flex items-center justify-between p-3 bg-white/[0.03] rounded-xl"
              >
                <span class="text-gray-300 text-sm">{{ shortcut.description }}</span>
                <kbd class="kbd">
                  {{ formatKey(shortcut.key) }}
                </kbd>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer hint -->
        <div class="px-6 py-3 bg-white/[0.02] border-t border-white/[0.06] text-center">
          <span class="text-xs text-gray-500">
            Drücke <kbd class="kbd mx-1">?</kbd> jederzeit um diese Hilfe anzuzeigen
          </span>
        </div>
      </div>
    </div>
  </Teleport>
</template>
