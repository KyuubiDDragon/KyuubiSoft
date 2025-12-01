<script setup>
import { computed } from 'vue'
import { useUiStore } from '@/stores/ui'
import Sidebar from './components/Sidebar.vue'
import Header from './components/Header.vue'

const uiStore = useUiStore()

const mainClass = computed(() => ({
  'ml-64': !uiStore.sidebarCollapsed,
  'ml-20': uiStore.sidebarCollapsed,
}))
</script>

<template>
  <div class="min-h-screen bg-dark-900">
    <!-- Sidebar -->
    <Sidebar />

    <!-- Main content -->
    <div
      class="transition-all duration-300"
      :class="mainClass"
    >
      <!-- Header -->
      <Header />

      <!-- Page content -->
      <main class="p-6">
        <slot />
      </main>
    </div>

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
