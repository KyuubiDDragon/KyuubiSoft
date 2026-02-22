<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { useUiStore } from '@/stores/ui'
import { useKeyboardShortcuts } from '@/composables/useKeyboardShortcuts'
import Sidebar from './components/Sidebar.vue'
import Header from './components/Header.vue'
import QuickNotes from '@/components/QuickNotes.vue'
import QuickCapture from '@/components/QuickCapture.vue'
import AIAssistant from '@/components/AIAssistant.vue'
import KeyboardShortcutsModal from '@/components/KeyboardShortcutsModal.vue'
import CommandPalette from '@/components/CommandPalette.vue'

const route = useRoute()
const uiStore = useUiStore()

// Initialize keyboard shortcuts
useKeyboardShortcuts()
const isMobile = ref(false)
const mobileSidebarOpen = ref(false)

// Check if current page needs full-bleed layout (no padding, full height)
const isFullBleed = computed(() => route.meta?.fullBleed === true)

// Check if mobile on mount and window resize
function checkMobile() {
  isMobile.value = window.innerWidth < 1024 // lg breakpoint
}

onMounted(() => {
  checkMobile()
  window.addEventListener('resize', checkMobile)
})

onUnmounted(() => {
  window.removeEventListener('resize', checkMobile)
})

const mainClass = computed(() => {
  // On mobile, no margin - full width
  if (isMobile.value) {
    return {}
  }
  // On desktop, apply sidebar margin
  return {
    'ml-64': !uiStore.sidebarCollapsed,
    'ml-20': uiStore.sidebarCollapsed,
  }
})

function toggleMobileSidebar() {
  mobileSidebarOpen.value = !mobileSidebarOpen.value
}

function closeMobileSidebar() {
  mobileSidebarOpen.value = false
}
</script>

<template>
  <div class="min-h-screen bg-dark-900">
    <!-- Mobile sidebar overlay -->
    <div
      v-if="isMobile && mobileSidebarOpen"
      class="fixed inset-0 bg-black/50 z-30 lg:hidden"
      @click="closeMobileSidebar"
    ></div>

    <!-- Sidebar -->
    <Sidebar
      :is-mobile="isMobile"
      :mobile-open="mobileSidebarOpen"
      @close="closeMobileSidebar"
    />

    <!-- Main content -->
    <div
      class="transition-all duration-300 flex flex-col"
      :class="[mainClass, { 'h-screen': isFullBleed }]"
    >
      <!-- Header (always visible) -->
      <Header
        :is-mobile="isMobile"
        @toggle-sidebar="toggleMobileSidebar"
      />

      <!-- Page content -->
      <main :class="isFullBleed ? 'flex-1 overflow-hidden' : 'p-4 lg:p-6'">
        <slot />
      </main>
    </div>

    <!-- Quick Notes Floating Widget -->
    <QuickNotes v-if="uiStore.showQuickNotes" />

    <!-- Quick Capture Floating Widget -->
    <QuickCapture v-if="uiStore.showQuickCapture" />

    <!-- AI Assistant Floating Widget -->
    <AIAssistant v-if="uiStore.showAIAssistant" />

    <!-- Keyboard Shortcuts Modal -->
    <KeyboardShortcutsModal />

    <!-- Command Palette (Ctrl+K) -->
    <CommandPalette />

    <!-- Loading overlay -->
    <Transition name="fade">
      <div
        v-if="uiStore.isLoading"
        class="fixed inset-0 bg-dark-900/80 backdrop-blur-sm z-50 flex items-center justify-center"
      >
        <div class="flex flex-col items-center gap-4">
          <div class="w-12 h-12 border-4 border-primary-600 border-t-transparent rounded-full animate-spin"></div>
          <p class="text-gray-300">Loading...</p>
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
