<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { useQuickAccessStore } from '@/stores/quickAccess'
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
  EllipsisHorizontalIcon,
  HomeIcon,
  ListBulletIcon,
  DocumentTextIcon,
  ServerIcon,
  CodeBracketIcon,
  ViewColumnsIcon,
  FolderIcon,
  ClockIcon,
  BellIcon,
  BookmarkIcon,
  SignalIcon,
  CurrencyDollarIcon,
  WrenchScrewdriverIcon,
  UsersIcon,
  ShieldCheckIcon,
  DocumentDuplicateIcon,
  BriefcaseIcon,
  CommandLineIcon,
  CalendarIcon,
  CubeIcon,
  TicketIcon,
  TagIcon,
  NewspaperIcon,
  CloudArrowUpIcon,
  CloudIcon,
  LinkIcon,
  ClipboardDocumentListIcon,
  ClipboardDocumentCheckIcon,
  KeyIcon,
  ArrowPathIcon,
  ChatBubbleLeftRightIcon,
  BookOpenIcon,
  BoltIcon,
  ArchiveBoxIcon,
  LockClosedIcon,
  PhotoIcon,
} from '@heroicons/vue/24/outline'
import Breadcrumbs from '@/components/Breadcrumbs.vue'

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
const quickAccessStore = useQuickAccessStore()

const showUserMenu = ref(false)
const showWidgetsMenu = ref(false)
const showQuickAccessOverflow = ref(false)

// Icon mapping from string names to components
const iconMap = {
  HomeIcon,
  ListBulletIcon,
  DocumentTextIcon,
  ServerIcon,
  CodeBracketIcon,
  ViewColumnsIcon,
  FolderIcon,
  ClockIcon,
  BellIcon,
  BookmarkIcon,
  SignalIcon,
  CurrencyDollarIcon,
  WrenchScrewdriverIcon,
  Cog6ToothIcon,
  UsersIcon,
  ShieldCheckIcon,
  DocumentDuplicateIcon,
  BriefcaseIcon,
  CommandLineIcon,
  CalendarIcon,
  CubeIcon,
  TicketIcon,
  TagIcon,
  NewspaperIcon,
  CloudArrowUpIcon,
  CloudIcon,
  LinkIcon,
  ClipboardDocumentListIcon,
  ClipboardDocumentCheckIcon,
  KeyIcon,
  ArrowPathIcon,
  InboxArrowDownIcon,
  ChatBubbleLeftRightIcon,
  BookOpenIcon,
  BoltIcon,
  ArchiveBoxIcon,
  LockClosedIcon,
  PhotoIcon,
  PencilSquareIcon,
}

// Load quick access on mount
onMounted(() => {
  if (!quickAccessStore.isInitialized) {
    quickAccessStore.load()
  }
})

// Get icon component from name
function getIconComponent(iconName) {
  return iconMap[iconName] || HomeIcon
}

// Navigate to quick access item
function navigateToQuickAccess(item) {
  router.push(item.nav_href)
  showQuickAccessOverflow.value = false
}

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
  <header class="h-14 bg-dark-800/95 border-b border-dark-700/60 flex items-center justify-between px-3 lg:px-5 gap-3 shrink-0">
    <!-- Mobile menu button -->
    <button
      v-if="isMobile"
      @click="$emit('toggle-sidebar')"
      class="btn-icon shrink-0"
    >
      <Bars3Icon class="w-5 h-5" />
    </button>

    <!-- Left: Search + Breadcrumbs -->
    <div class="flex items-center gap-3 flex-1 min-w-0">
      <!-- Global Search (compact) -->
      <div class="shrink-0">
        <GlobalSearch />
      </div>

      <!-- Breadcrumbs (desktop only) -->
      <div v-if="!isMobile" class="hidden md:flex items-center min-w-0">
        <Breadcrumbs />
      </div>
    </div>

    <!-- Quick Access Icons (desktop only) -->
    <div v-if="quickAccessStore.items.length > 0 && !isMobile" class="flex items-center gap-0.5 shrink-0">
      <button
        v-for="item in quickAccessStore.visibleItems"
        :key="item.id"
        @click="navigateToQuickAccess(item)"
        class="btn-icon"
        :title="item.nav_name"
      >
        <component :is="getIconComponent(item.nav_icon)" class="w-4.5 h-4.5" style="width: 1.125rem; height: 1.125rem;" />
      </button>

      <!-- Overflow dropdown -->
      <div v-if="quickAccessStore.hasOverflow" class="relative">
        <button
          @click="showQuickAccessOverflow = !showQuickAccessOverflow"
          class="btn-icon"
          title="Weitere"
        >
          <EllipsisHorizontalIcon style="width: 1.125rem; height: 1.125rem;" />
        </button>

        <Transition
          enter-active-class="transition ease-out duration-100"
          enter-from-class="transform opacity-0 scale-95"
          enter-to-class="transform opacity-100 scale-100"
          leave-active-class="transition ease-in duration-75"
          leave-from-class="transform opacity-100 scale-100"
          leave-to-class="transform opacity-0 scale-95"
        >
          <div
            v-if="showQuickAccessOverflow"
            class="absolute right-0 mt-2 w-48 bg-dark-800 border border-dark-700/80 rounded-xl shadow-2xl py-1.5 z-50"
          >
            <button
              v-for="item in quickAccessStore.overflowItems"
              :key="item.id"
              @click="navigateToQuickAccess(item)"
              class="w-full flex items-center gap-3 px-3 py-2 text-sm text-gray-400 hover:bg-dark-700/60 hover:text-white transition-colors"
            >
              <component :is="getIconComponent(item.nav_icon)" class="w-4 h-4 shrink-0" />
              {{ item.nav_name }}
            </button>
          </div>
        </Transition>
      </div>

      <!-- Divider -->
      <div class="w-px h-5 bg-dark-600/80 mx-1.5"></div>
    </div>

    <!-- Actions -->
    <div class="flex items-center gap-0.5 shrink-0">
      <!-- Inbox -->
      <button
        @click="router.push('/inbox')"
        class="btn-icon"
        title="Inbox"
      >
        <InboxArrowDownIcon style="width: 1.125rem; height: 1.125rem;" />
      </button>

      <!-- Team Chat -->
      <button
        @click="router.push('/chat')"
        class="btn-icon"
        title="Team Chat"
      >
        <ChatBubbleLeftRightIcon style="width: 1.125rem; height: 1.125rem;" />
      </button>

      <!-- Divider -->
      <div class="w-px h-5 bg-dark-600/80 mx-1"></div>

      <!-- Dark mode toggle -->
      <button
        @click="uiStore.toggleDarkMode"
        class="btn-icon"
        title="Dark Mode umschalten"
      >
        <SunIcon v-if="uiStore.isDarkMode" style="width: 1.125rem; height: 1.125rem;" />
        <MoonIcon v-else style="width: 1.125rem; height: 1.125rem;" />
      </button>

      <!-- Notifications -->
      <NotificationCenter />

      <!-- Widgets menu -->
      <div class="relative">
        <button
          @click="showWidgetsMenu = !showWidgetsMenu"
          class="btn-icon"
          title="Widgets"
        >
          <Squares2X2Icon style="width: 1.125rem; height: 1.125rem;" />
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
            class="absolute right-0 mt-2 w-52 bg-dark-800 border border-dark-700/80 rounded-xl shadow-2xl py-1.5 z-50"
          >
            <div class="px-3 py-2 border-b border-dark-700/60">
              <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Widgets</p>
            </div>

            <div class="py-1">
              <button
                @click="uiStore.toggleQuickNotes(); showWidgetsMenu = false"
                class="w-full flex items-center justify-between gap-3 px-3 py-2 text-sm text-gray-400 hover:bg-dark-700/60 hover:text-white transition-colors"
              >
                <div class="flex items-center gap-2.5">
                  <PencilSquareIcon class="w-4 h-4 shrink-0" />
                  <span>Quick Notes</span>
                </div>
                <div v-if="uiStore.showQuickNotes" class="w-4 h-4 rounded-full bg-primary-500/20 flex items-center justify-center">
                  <CheckIcon class="w-3 h-3 text-primary-400" />
                </div>
              </button>

              <button
                @click="uiStore.toggleQuickCapture(); showWidgetsMenu = false"
                class="w-full flex items-center justify-between gap-3 px-3 py-2 text-sm text-gray-400 hover:bg-dark-700/60 hover:text-white transition-colors"
              >
                <div class="flex items-center gap-2.5">
                  <InboxArrowDownIcon class="w-4 h-4 shrink-0" />
                  <span>Quick Capture</span>
                </div>
                <div v-if="uiStore.showQuickCapture" class="w-4 h-4 rounded-full bg-primary-500/20 flex items-center justify-center">
                  <CheckIcon class="w-3 h-3 text-primary-400" />
                </div>
              </button>

              <button
                @click="uiStore.toggleAIAssistant(); showWidgetsMenu = false"
                class="w-full flex items-center justify-between gap-3 px-3 py-2 text-sm text-gray-400 hover:bg-dark-700/60 hover:text-white transition-colors"
              >
                <div class="flex items-center gap-2.5">
                  <SparklesIcon class="w-4 h-4 shrink-0" />
                  <span>AI Assistent</span>
                </div>
                <div v-if="uiStore.showAIAssistant" class="w-4 h-4 rounded-full bg-primary-500/20 flex items-center justify-center">
                  <CheckIcon class="w-3 h-3 text-primary-400" />
                </div>
              </button>
            </div>

            <!-- Quick Access Settings -->
            <div v-if="quickAccessStore.items.length > 0" class="border-t border-dark-700/60 pt-1 pb-1">
              <div class="px-3 py-2">
                <p class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-2">Quick Access Anzahl</p>
                <div class="flex items-center gap-2.5">
                  <input
                    type="range"
                    :value="quickAccessStore.maxVisible"
                    @input="quickAccessStore.updateMaxVisible(Number($event.target.value))"
                    min="1"
                    max="10"
                    class="flex-1 h-1 bg-dark-600 rounded-lg appearance-none cursor-pointer accent-primary-500"
                  />
                  <span class="text-xs text-gray-400 w-4 text-center tabular-nums">{{ quickAccessStore.maxVisible }}</span>
                </div>
              </div>
            </div>
          </div>
        </Transition>
      </div>

      <!-- User menu -->
      <div class="relative ml-0.5">
        <button
          @click="toggleUserMenu"
          class="flex items-center p-1 rounded-lg hover:bg-dark-700/60 transition-colors"
        >
          <div class="w-7 h-7 rounded-lg bg-gradient-to-br from-primary-600 to-primary-700 flex items-center justify-center shadow-sm">
            <span class="text-xs font-bold text-white">
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
            class="absolute right-0 mt-2 w-56 bg-dark-800 border border-dark-700/80 rounded-xl shadow-2xl py-1.5 z-50"
          >
            <div class="px-3 py-2.5 border-b border-dark-700/60">
              <p class="text-sm font-semibold text-white">{{ authStore.user?.username }}</p>
              <p class="text-xs text-gray-500 mt-0.5">{{ authStore.user?.email }}</p>
            </div>

            <div class="py-1">
              <button
                @click="goToSettings"
                class="w-full flex items-center gap-2.5 px-3 py-2 text-sm text-gray-400 hover:bg-dark-700/60 hover:text-white transition-colors"
              >
                <Cog6ToothIcon class="w-4 h-4 shrink-0" />
                Einstellungen
              </button>
            </div>

            <div class="border-t border-dark-700/60 py-1">
              <button
                @click="logout"
                class="w-full flex items-center gap-2.5 px-3 py-2 text-sm text-red-400 hover:bg-red-500/10 hover:text-red-300 transition-colors"
              >
                <ArrowRightOnRectangleIcon class="w-4 h-4 shrink-0" />
                Abmelden
              </button>
            </div>
          </div>
        </Transition>
      </div>
    </div>
  </header>

  <!-- Click outside to close menus -->
  <div v-if="showUserMenu" @click="closeUserMenu" class="fixed inset-0 z-40" />
  <div v-if="showWidgetsMenu" @click="showWidgetsMenu = false" class="fixed inset-0 z-40" />
  <div v-if="showQuickAccessOverflow" @click="showQuickAccessOverflow = false" class="fixed inset-0 z-40" />
</template>
