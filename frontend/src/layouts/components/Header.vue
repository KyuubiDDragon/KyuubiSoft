<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import {
  MagnifyingGlassIcon,
  BellIcon,
  MoonIcon,
  SunIcon,
  ArrowRightOnRectangleIcon,
  UserIcon,
  Cog6ToothIcon,
} from '@heroicons/vue/24/outline'

const router = useRouter()
const authStore = useAuthStore()
const uiStore = useUiStore()

const showUserMenu = ref(false)
const searchQuery = ref('')

function toggleUserMenu() {
  showUserMenu.value = !showUserMenu.value
}

function closeUserMenu() {
  showUserMenu.value = false
}

async function logout() {
  closeUserMenu()
  authStore.logout()
  router.push('/login')
}

function goToSettings() {
  closeUserMenu()
  router.push('/settings')
}
</script>

<template>
  <header class="h-16 bg-dark-800 border-b border-dark-700 flex items-center justify-between px-6">
    <!-- Search -->
    <div class="flex-1 max-w-md">
      <div class="relative">
        <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-500" />
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Suchen..."
          class="input pl-10 py-2 bg-dark-900"
        />
      </div>
    </div>

    <!-- Actions -->
    <div class="flex items-center gap-2">
      <!-- Dark mode toggle -->
      <button
        @click="uiStore.toggleDarkMode"
        class="p-2 rounded-lg text-gray-400 hover:text-white hover:bg-dark-700 transition-colors"
      >
        <SunIcon v-if="uiStore.isDarkMode" class="w-5 h-5" />
        <MoonIcon v-else class="w-5 h-5" />
      </button>

      <!-- Notifications -->
      <button class="p-2 rounded-lg text-gray-400 hover:text-white hover:bg-dark-700 transition-colors relative">
        <BellIcon class="w-5 h-5" />
        <span class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span>
      </button>

      <!-- User menu -->
      <div class="relative">
        <button
          @click="toggleUserMenu"
          class="flex items-center gap-2 p-1.5 rounded-lg hover:bg-dark-700 transition-colors"
        >
          <div class="w-8 h-8 rounded-full bg-primary-600 flex items-center justify-center">
            <span class="text-sm font-semibold text-white">
              {{ authStore.user?.username?.[0]?.toUpperCase() || 'U' }}
            </span>
          </div>
        </button>

        <!-- Dropdown -->
        <Transition
          enter-active-class="transition ease-out duration-100"
          enter-from-class="transform opacity-0 scale-95"
          enter-to-class="transform opacity-100 scale-100"
          leave-active-class="transition ease-in duration-75"
          leave-from-class="transform opacity-100 scale-100"
          leave-to-class="transform opacity-0 scale-95"
        >
          <div
            v-if="showUserMenu"
            class="absolute right-0 mt-2 w-56 bg-dark-800 border border-dark-700 rounded-lg shadow-xl py-1 z-50"
          >
            <div class="px-4 py-3 border-b border-dark-700">
              <p class="text-sm font-medium text-white">{{ authStore.user?.username }}</p>
              <p class="text-xs text-gray-400">{{ authStore.user?.email }}</p>
            </div>

            <button
              @click="goToSettings"
              class="w-full flex items-center gap-3 px-4 py-2 text-sm text-gray-300 hover:bg-dark-700 hover:text-white transition-colors"
            >
              <Cog6ToothIcon class="w-4 h-4" />
              Einstellungen
            </button>

            <div class="border-t border-dark-700 mt-1 pt-1">
              <button
                @click="logout"
                class="w-full flex items-center gap-3 px-4 py-2 text-sm text-red-400 hover:bg-dark-700 hover:text-red-300 transition-colors"
              >
                <ArrowRightOnRectangleIcon class="w-4 h-4" />
                Abmelden
              </button>
            </div>
          </div>
        </Transition>
      </div>
    </div>
  </header>

  <!-- Click outside to close menu -->
  <div
    v-if="showUserMenu"
    @click="closeUserMenu"
    class="fixed inset-0 z-40"
  ></div>
</template>
