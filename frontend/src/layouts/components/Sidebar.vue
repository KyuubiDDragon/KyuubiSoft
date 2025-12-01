<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import {
  HomeIcon,
  ListBulletIcon,
  DocumentTextIcon,
  ServerIcon,
  Cog6ToothIcon,
  ChartBarIcon,
  UsersIcon,
  ShieldCheckIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
} from '@heroicons/vue/24/outline'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const uiStore = useUiStore()

// Alle Menüpunkte mit optionalen Rollen/Berechtigungen
const allNavigation = [
  { name: 'Dashboard', href: '/', icon: HomeIcon },
  { name: 'Listen', href: '/lists', icon: ListBulletIcon },
  { name: 'Dokumente', href: '/documents', icon: DocumentTextIcon },
  { name: 'Verbindungen', href: '/connections', icon: ServerIcon },
  { name: 'Einstellungen', href: '/settings', icon: Cog6ToothIcon },
  // Admin-Bereich (nur für owner/admin sichtbar)
  { name: 'Benutzer', href: '/users', icon: UsersIcon, roles: ['owner', 'admin'] },
  { name: 'System', href: '/system', icon: ShieldCheckIcon, roles: ['owner'] },
]

// Gefilterte Navigation basierend auf Benutzerrechten
const navigation = computed(() => {
  return allNavigation.filter(item => {
    // Keine Einschränkung - immer sichtbar
    if (!item.roles && !item.permission) {
      return true
    }
    // Rollenbasierte Sichtbarkeit
    if (item.roles) {
      return item.roles.some(role => authStore.hasRole(role))
    }
    // Berechtigungsbasierte Sichtbarkeit
    if (item.permission) {
      return authStore.hasPermission(item.permission)
    }
    return false
  })
})

const sidebarClass = computed(() => ({
  'w-64': !uiStore.sidebarCollapsed,
  'w-20': uiStore.sidebarCollapsed,
}))

function isActive(href) {
  if (href === '/') {
    return route.path === '/'
  }
  return route.path.startsWith(href)
}

function navigateTo(href) {
  router.push(href)
}
</script>

<template>
  <aside
    class="fixed left-0 top-0 h-screen bg-dark-800 border-r border-dark-700 transition-all duration-300 z-40"
    :class="sidebarClass"
  >
    <div class="flex flex-col h-full">
      <!-- Logo -->
      <div class="h-16 flex items-center justify-center border-b border-dark-700">
        <h1
          v-if="!uiStore.sidebarCollapsed"
          class="text-xl font-bold text-gradient"
        >
          KyuubiSoft
        </h1>
        <span
          v-else
          class="text-2xl font-bold text-primary-500"
        >
          K
        </span>
      </div>

      <!-- Navigation -->
      <nav class="flex-1 py-4 px-3 space-y-1 overflow-y-auto">
        <button
          v-for="item in navigation"
          :key="item.name"
          @click="navigateTo(item.href)"
          class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200"
          :class="[
            isActive(item.href)
              ? 'bg-primary-600 text-white'
              : 'text-gray-400 hover:bg-dark-700 hover:text-white'
          ]"
        >
          <component :is="item.icon" class="w-5 h-5 flex-shrink-0" />
          <span
            v-if="!uiStore.sidebarCollapsed"
            class="font-medium"
          >
            {{ item.name }}
          </span>
        </button>
      </nav>

      <!-- User section -->
      <div class="p-4 border-t border-dark-700">
        <div
          v-if="!uiStore.sidebarCollapsed"
          class="flex items-center gap-3"
        >
          <div class="w-10 h-10 rounded-full bg-primary-600 flex items-center justify-center">
            <span class="text-sm font-semibold text-white">
              {{ authStore.user?.username?.[0]?.toUpperCase() || 'U' }}
            </span>
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-white truncate">
              {{ authStore.user?.username || 'User' }}
            </p>
            <p class="text-xs text-gray-400 truncate">
              {{ authStore.user?.email }}
            </p>
          </div>
        </div>
        <div
          v-else
          class="flex justify-center"
        >
          <div class="w-10 h-10 rounded-full bg-primary-600 flex items-center justify-center">
            <span class="text-sm font-semibold text-white">
              {{ authStore.user?.username?.[0]?.toUpperCase() || 'U' }}
            </span>
          </div>
        </div>
      </div>

      <!-- Collapse button -->
      <button
        @click="uiStore.toggleSidebarCollapse"
        class="absolute -right-3 top-20 w-6 h-6 bg-dark-700 border border-dark-600 rounded-full flex items-center justify-center text-gray-400 hover:text-white hover:bg-dark-600 transition-colors"
      >
        <ChevronLeftIcon
          v-if="!uiStore.sidebarCollapsed"
          class="w-4 h-4"
        />
        <ChevronRightIcon
          v-else
          class="w-4 h-4"
        />
      </button>
    </div>
  </aside>
</template>
