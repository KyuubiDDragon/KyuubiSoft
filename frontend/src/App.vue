<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import AuthLayout from '@/layouts/AuthLayout.vue'
import PublicLayout from '@/layouts/PublicLayout.vue'
import PWAUpdatePrompt from '@/components/PWAUpdatePrompt.vue'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const uiStore = useUiStore()
const isAppReady = ref(false)

const layout = computed(() => {
  if (route.meta.layout === 'auth') {
    return AuthLayout
  }
  if (route.meta.layout === 'public') {
    return PublicLayout
  }
  return DefaultLayout
})

// Initialize auth state before showing app
onMounted(async () => {
  await authStore.initialize()

  // If not authenticated and on a protected route, redirect first
  // Skip redirect for auth layout (login/register) and public layout (public documents, etc.)
  if (!authStore.isAuthenticated && route.meta.requiresAuth !== false && route.meta.layout !== 'auth' && route.meta.layout !== 'public') {
    await router.replace({ name: 'login', query: { redirect: route.fullPath } })
  }

  isAppReady.value = true
})
</script>

<template>
  <!-- Loading screen while initializing -->
  <div v-if="!isAppReady" class="min-h-screen bg-dark-900 flex items-center justify-center">
    <div class="flex flex-col items-center gap-4">
      <div class="w-12 h-12 border-4 border-primary-600 border-t-transparent rounded-full animate-spin"></div>
      <p class="text-gray-400">Laden...</p>
    </div>
  </div>

  <!-- App content -->
  <div v-else :class="{ 'dark': uiStore.isDarkMode }">
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

    <!-- PWA Update Prompt -->
    <PWAUpdatePrompt />
  </div>
</template>
