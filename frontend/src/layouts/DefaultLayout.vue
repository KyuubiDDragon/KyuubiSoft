<script setup lang="ts">
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

useKeyboardShortcuts()

const isMobile = ref(false)
const mobileSidebarOpen = ref(false)

const isFullBleed = computed(() => route.meta?.fullBleed === true)

function checkMobile() {
  isMobile.value = window.innerWidth < 1024
}

onMounted(() => {
  checkMobile()
  window.addEventListener('resize', checkMobile)
})

onUnmounted(() => {
  window.removeEventListener('resize', checkMobile)
})

function toggleMobileSidebar() {
  mobileSidebarOpen.value = !mobileSidebarOpen.value
}

function closeMobileSidebar() {
  mobileSidebarOpen.value = false
}
</script>

<template>
  <div class="min-h-screen bg-dark-950">
    <!-- Sidebar (Icon Rail + Flyout) -->
    <Sidebar
      :is-mobile="isMobile"
      :mobile-open="mobileSidebarOpen"
      @close="closeMobileSidebar"
    />

    <!-- Main content - offset by rail width -->
    <div
      class="transition-all duration-300 flex flex-col"
      :class="[
        isMobile ? '' : 'ml-rail',
        isFullBleed ? 'h-screen' : '',
      ]"
    >
      <!-- Header -->
      <Header
        :is-mobile="isMobile"
        @toggle-sidebar="toggleMobileSidebar"
      />

      <!-- Page content -->
      <main :class="isFullBleed ? 'flex-1 overflow-hidden' : 'p-4 lg:p-6'">
        <slot />
      </main>
    </div>

    <!-- Floating widgets -->
    <QuickNotes v-if="uiStore.showQuickNotes" />
    <QuickCapture v-if="uiStore.showQuickCapture" />
    <AIAssistant v-if="uiStore.showAIAssistant" />

    <!-- Overlays -->
    <KeyboardShortcutsModal />
    <CommandPalette />

    <!-- Loading overlay -->
    <Transition name="fade">
      <div
        v-if="uiStore.isLoading"
        class="fixed inset-0 bg-dark-950/80 backdrop-blur-md z-50 flex items-center justify-center"
      >
        <div class="flex flex-col items-center gap-4">
          <div class="w-12 h-12 border-3 border-primary-500 border-t-transparent rounded-full animate-spin" />
          <p class="text-sm text-gray-400 font-medium">Laden...</p>
        </div>
      </div>
    </Transition>
  </div>
</template>
