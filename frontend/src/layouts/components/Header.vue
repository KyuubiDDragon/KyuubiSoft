<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import GlobalSearch from '@/components/GlobalSearch.vue'
import NotificationCenter from '@/components/NotificationCenter.vue'
import {
  MoonIcon,
  SunIcon,
  ArrowRightOnRectangleIcon,
  Cog6ToothIcon,
  Bars3Icon,
  Squares2X2Icon,
  PencilSquareIcon,
  InboxArrowDownIcon,
  SparklesIcon,
  CheckIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  isMobile: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['toggle-sidebar'])

const router = useRouter()
const authStore = useAuthStore()
const uiStore = useUiStore()

const showUserMenu = ref(false)
const showWidgetsMenu = ref(false)

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
  <header class="h-16 bg-dark-800 border-b border-dark-700 flex items-center justify-between px-4 lg:px-6">
    <!-- Mobile menu button -->
    <button
      v-if="isMobile"
      @click="$emit('toggle-sidebar')"
      class="p-2 rounded-lg text-gray-400 hover:text-white hover:bg-dark-700 transition-colors mr-2"
    >
      <Bars3Icon class="w-6 h-6" />
    </button>

    <!-- Global Search -->
    <div class="flex-1">
      <GlobalSearch />
    </div>

    <!-- Actions -->
    <div class="flex items-center gap-2">
      <!-- Dark mode toggle -->
      <button
        @click="uiStore.toggleDarkMode"
        class="p-2 rounded-lg text-gray-400 hover:text-white hover:bg-dark-700 transition-colors"
        title="Dark Mode umschalten"
      >
        <SunIcon v-if="uiStore.isDarkMode" class="w-5 h-5" />
        <MoonIcon v-else class="w-5 h-5" />
      </button>

      <!-- Notifications -->
      <NotificationCenter />

      <!-- Widgets menu -->
      <div class="relative">
        <button
          @click="showWidgetsMenu = !showWidgetsMenu"
          class="p-2 rounded-lg text-gray-400 hover:text-white hover:bg-dark-700 transition-colors"
          title="Widgets"
        >
          <Squares2X2Icon class="w-5 h-5" />
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
            v-if="showWidgetsMenu"
            class="absolute right-0 mt-2 w-52 bg-dark-800 border border-dark-700 rounded-lg shadow-xl py-1 z-50"
          >
            <div class="px-4 py-2 border-b border-dark-700">
              <p class="text-sm font-medium text-white">Widgets</p>
            </div>

            <button
              @click="uiStore.toggleQuickNotes(); showWidgetsMenu = false"
              class="w-full flex items-center justify-between gap-3 px-4 py-2 text-sm text-gray-300 hover:bg-dark-700 hover:text-white transition-colors"
            >
              <div class="flex items-center gap-3">
                <PencilSquareIcon class="w-4 h-4" />
                Quick Notes
              </div>
              <CheckIcon v-if="uiStore.showQuickNotes" class="w-4 h-4 text-primary-400" />
            </button>

            <button
              @click="uiStore.toggleQuickCapture(); showWidgetsMenu = false"
              class="w-full flex items-center justify-between gap-3 px-4 py-2 text-sm text-gray-300 hover:bg-dark-700 hover:text-white transition-colors"
            >
              <div class="flex items-center gap-3">
                <InboxArrowDownIcon class="w-4 h-4" />
                Quick Capture
              </div>
              <CheckIcon v-if="uiStore.showQuickCapture" class="w-4 h-4 text-primary-400" />
            </button>

            <button
              @click="uiStore.toggleAIAssistant(); showWidgetsMenu = false"
              class="w-full flex items-center justify-between gap-3 px-4 py-2 text-sm text-gray-300 hover:bg-dark-700 hover:text-white transition-colors"
            >
              <div class="flex items-center gap-3">
                <SparklesIcon class="w-4 h-4" />
                AI Assistent
              </div>
              <CheckIcon v-if="uiStore.showAIAssistant" class="w-4 h-4 text-primary-400" />
            </button>
          </div>
        </Transition>
      </div>

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

  <!-- Click outside to close menus -->
  <div
    v-if="showUserMenu"
    @click="closeUserMenu"
    class="fixed inset-0 z-40"
  ></div>
  <div
    v-if="showWidgetsMenu"
    @click="showWidgetsMenu = false"
    class="fixed inset-0 z-40"
  ></div>
</template>
