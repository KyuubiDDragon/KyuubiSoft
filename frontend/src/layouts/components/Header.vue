<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { useQuickAccessStore } from '@/stores/quickAccess'
import { useLocaleStore } from '@/stores/locale'
import { getIconComponent } from '@/core/config/navigation'
import GlobalSearch from '@/components/GlobalSearch.vue'
import NotificationCenter from '@/components/NotificationCenter.vue'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import {
  ArrowRightOnRectangleIcon,
  Cog6ToothIcon,
  Bars3Icon,
  Squares2X2Icon,
  PencilSquareIcon,
  InboxArrowDownIcon,
  SparklesIcon,
  CheckIcon,
  EllipsisHorizontalIcon,
  ChatBubbleLeftRightIcon,
  LanguageIcon,
} from '@heroicons/vue/24/outline'

defineProps<{
  isMobile?: boolean
}>()

defineEmits(['toggle-sidebar'])

const router = useRouter()
const authStore = useAuthStore()
const uiStore = useUiStore()
const quickAccessStore = useQuickAccessStore()
const localeStore = useLocaleStore()

const showUserMenu = ref(false)
const showWidgetsMenu = ref(false)
const showQuickAccessOverflow = ref(false)
const showLanguageMenu = ref(false)

onMounted(() => {
  if (!quickAccessStore.isInitialized) {
    quickAccessStore.load()
  }
})

function navigateToQuickAccess(item: any) {
  router.push(item.nav_href)
  showQuickAccessOverflow.value = false
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
  <header class="h-14 bg-dark-900/75 backdrop-blur-xl border-b border-white/[0.08] shadow-lg shadow-black/20 flex items-center justify-between px-4 lg:px-6 gap-3 shrink-0 relative z-20">
    <!-- Mobile menu button -->
    <button
      v-if="isMobile"
      @click="$emit('toggle-sidebar')"
      class="btn-icon-sm shrink-0"
    >
      <Bars3Icon class="w-5 h-5" />
    </button>

    <!-- Left: Breadcrumbs + Search -->
    <div class="flex items-center gap-4 flex-1 min-w-0">
      <!-- Breadcrumbs (desktop only) -->
      <div v-if="!isMobile" class="hidden md:flex items-center min-w-0">
        <Breadcrumbs />
      </div>

      <!-- Global Search (compact) -->
      <div class="shrink-0">
        <GlobalSearch />
      </div>
    </div>

    <!-- Quick Access Icons (desktop only) -->
    <div v-if="quickAccessStore.items.length > 0 && !isMobile" class="flex items-center gap-0.5 shrink-0">
      <button
        v-for="item in quickAccessStore.visibleItems"
        :key="item.nav_id"
        @click="navigateToQuickAccess(item)"
        class="btn-icon-sm"
        :title="$t(item.nav_name)"
      >
        <component :is="getIconComponent(item.nav_icon)" class="w-4 h-4" />
      </button>

      <!-- Overflow dropdown -->
      <div v-if="quickAccessStore.hasOverflow" class="relative">
        <button
          @click="showQuickAccessOverflow = !showQuickAccessOverflow"
          class="btn-icon-sm"
          :title="$t('common.more')"
        >
          <EllipsisHorizontalIcon class="w-4 h-4" />
        </button>

        <Transition name="fade">
          <div
            v-if="showQuickAccessOverflow"
            class="dropdown right-0 mt-2"
          >
            <button
              v-for="item in quickAccessStore.overflowItems"
              :key="item.nav_id"
              @click="navigateToQuickAccess(item)"
              class="dropdown-item"
            >
              <component :is="getIconComponent(item.nav_icon)" class="w-4 h-4 shrink-0" />
              {{ $t(item.nav_name) }}
            </button>
          </div>
        </Transition>
      </div>

      <!-- Divider -->
      <div class="w-px h-5 bg-white/[0.10] mx-1.5" />
    </div>

    <!-- Actions -->
    <div class="flex items-center gap-0.5 shrink-0">
      <!-- Inbox -->
      <button
        @click="router.push('/inbox')"
        class="btn-icon-sm"
        :title="$t('header.inbox')"
      >
        <InboxArrowDownIcon class="w-4 h-4" />
      </button>

      <!-- Team Chat -->
      <button
        @click="router.push('/chat')"
        class="btn-icon-sm"
        :title="$t('header.teamChat')"
      >
        <ChatBubbleLeftRightIcon class="w-4 h-4" />
      </button>

      <!-- Divider -->
      <div class="w-px h-5 bg-white/[0.10] mx-1" />

      <!-- Notifications -->
      <NotificationCenter />

      <!-- Widgets menu -->
      <div class="relative">
        <button
          @click="showWidgetsMenu = !showWidgetsMenu"
          class="btn-icon-sm"
          :title="$t('header.widgets')"
        >
          <Squares2X2Icon class="w-4 h-4" />
        </button>

        <Transition name="fade">
          <div
            v-if="showWidgetsMenu"
            class="dropdown right-0 mt-2"
          >
            <div class="dropdown-header">{{ $t('header.widgets') }}</div>

            <button
              @click="uiStore.toggleQuickNotes(); showWidgetsMenu = false"
              class="dropdown-item justify-between"
            >
              <div class="flex items-center gap-2.5">
                <PencilSquareIcon class="w-4 h-4 shrink-0" />
                <span>{{ $t('header.quickNotes') }}</span>
              </div>
              <div v-if="uiStore.showQuickNotes" class="status-online" />
            </button>

            <button
              @click="uiStore.toggleQuickCapture(); showWidgetsMenu = false"
              class="dropdown-item justify-between"
            >
              <div class="flex items-center gap-2.5">
                <InboxArrowDownIcon class="w-4 h-4 shrink-0" />
                <span>{{ $t('header.quickCapture') }}</span>
              </div>
              <div v-if="uiStore.showQuickCapture" class="status-online" />
            </button>

            <button
              @click="uiStore.toggleAIAssistant(); showWidgetsMenu = false"
              class="dropdown-item justify-between"
            >
              <div class="flex items-center gap-2.5">
                <SparklesIcon class="w-4 h-4 shrink-0" />
                <span>{{ $t('header.aiAssistant') }}</span>
              </div>
              <div v-if="uiStore.showAIAssistant" class="status-online" />
            </button>

            <!-- Quick Access Settings -->
            <template v-if="quickAccessStore.items.length > 0">
              <div class="dropdown-divider" />
              <div class="px-3 py-2">
                <p class="text-2xs font-semibold text-gray-500 uppercase tracking-wider mb-2">{{ $t('header.quickAccessCount') }}</p>
                <div class="flex items-center gap-2.5">
                  <input
                    type="range"
                    :value="quickAccessStore.maxVisible"
                    @input="quickAccessStore.updateMaxVisible(Number(($event.target as HTMLInputElement).value))"
                    min="1"
                    max="10"
                    class="flex-1 h-1 bg-white/[0.08] rounded-lg appearance-none cursor-pointer accent-primary-500"
                  />
                  <span class="text-xs text-gray-400 w-4 text-center tabular-nums">{{ quickAccessStore.maxVisible }}</span>
                </div>
              </div>
            </template>
          </div>
        </Transition>
      </div>

      <!-- Language switcher -->
      <div class="relative">
        <button
          @click="showLanguageMenu = !showLanguageMenu"
          class="btn-icon-sm"
          :title="$t('header.language')"
        >
          <LanguageIcon class="w-4 h-4" />
        </button>

        <Transition name="fade">
          <div
            v-if="showLanguageMenu"
            class="dropdown right-0 mt-2"
          >
            <div class="dropdown-header">{{ $t('header.language') }}</div>
            <button
              v-for="loc in localeStore.getAvailableLocales()"
              :key="loc.code"
              @click="localeStore.setLocale(loc.code); showLanguageMenu = false"
              class="dropdown-item justify-between"
            >
              <div class="flex items-center gap-2.5">
                <span class="text-sm">{{ loc.flag }}</span>
                <span>{{ loc.name }}</span>
              </div>
              <CheckIcon v-if="localeStore.currentLocale === loc.code" class="w-4 h-4 text-primary-400" />
            </button>
          </div>
        </Transition>
      </div>

      <!-- User menu -->
      <div class="relative ml-0.5">
        <button
          @click="showUserMenu = !showUserMenu"
          class="flex items-center p-1 rounded-xl hover:bg-white/[0.10] transition-colors"
        >
          <div class="avatar">
            <span class="text-xs font-bold text-white">
              {{ authStore.user?.username?.[0]?.toUpperCase() || 'U' }}
            </span>
          </div>
        </button>

        <Transition name="fade">
          <div
            v-if="showUserMenu"
            class="dropdown right-0 mt-2"
          >
            <div class="px-3 py-3 border-b border-white/[0.06]">
              <p class="text-sm font-semibold text-white">{{ authStore.user?.username }}</p>
              <p class="text-xs text-gray-500 mt-0.5">{{ authStore.user?.email }}</p>
            </div>

            <div class="py-1">
              <button @click="goToSettings" class="dropdown-item">
                <Cog6ToothIcon class="w-4 h-4 shrink-0" />
                {{ $t('common.settings') }}
              </button>
            </div>

            <div class="border-t border-white/[0.06] py-1">
              <button @click="logout" class="dropdown-item-danger">
                <ArrowRightOnRectangleIcon class="w-4 h-4 shrink-0" />
                {{ $t('auth.logout') }}
              </button>
            </div>
          </div>
        </Transition>
      </div>
    </div>
  </header>

  <!-- Click outside to close menus -->
  <div v-if="showUserMenu" @click="closeUserMenu" class="fixed inset-0 z-10" />
  <div v-if="showWidgetsMenu" @click="showWidgetsMenu = false" class="fixed inset-0 z-10" />
  <div v-if="showQuickAccessOverflow" @click="showQuickAccessOverflow = false" class="fixed inset-0 z-10" />
  <div v-if="showLanguageMenu" @click="showLanguageMenu = false" class="fixed inset-0 z-10" />
</template>
