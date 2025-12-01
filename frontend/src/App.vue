<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import AuthLayout from '@/layouts/AuthLayout.vue'

const route = useRoute()
const authStore = useAuthStore()
const uiStore = useUiStore()

const layout = computed(() => {
  if (route.meta.layout === 'auth') {
    return AuthLayout
  }
  return DefaultLayout
})

// Initialize auth state
authStore.initialize()
</script>

<template>
  <div :class="{ 'dark': uiStore.isDarkMode }">
    <component :is="layout">
      <RouterView />
    </component>

    <!-- Global notification toast -->
    <Teleport to="body">
      <div
        v-if="uiStore.notification"
        class="fixed top-4 right-4 z-50 max-w-sm"
      >
        <div
          :class="[
            'rounded-lg px-4 py-3 shadow-lg',
            uiStore.notification.type === 'success' && 'bg-green-600 text-white',
            uiStore.notification.type === 'error' && 'bg-red-600 text-white',
            uiStore.notification.type === 'warning' && 'bg-yellow-500 text-black',
            uiStore.notification.type === 'info' && 'bg-blue-600 text-white',
          ]"
        >
          <p class="font-medium">{{ uiStore.notification.message }}</p>
        </div>
      </div>
    </Teleport>
  </div>
</template>
