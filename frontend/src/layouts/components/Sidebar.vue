<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { useProjectStore } from '@/stores/project'
import {
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
  BeakerIcon,
  Cog6ToothIcon,
  UsersIcon,
  ShieldCheckIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  ChevronUpDownIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const uiStore = useUiStore()
const projectStore = useProjectStore()

const showProjectDropdown = ref(false)

// Load projects on mount
onMounted(() => {
  projectStore.loadProjects()
})

function selectProject(projectId) {
  projectStore.selectProject(projectId)
  showProjectDropdown.value = false
}

function clearProjectSelection() {
  projectStore.clearSelection()
  showProjectDropdown.value = false
}

// Alle Menüpunkte mit optionalen Rollen/Berechtigungen
const allNavigation = [
  { name: 'Dashboard', href: '/', icon: HomeIcon },
  { name: 'Listen', href: '/lists', icon: ListBulletIcon },
  { name: 'Dokumente', href: '/documents', icon: DocumentTextIcon },
  { name: 'Verbindungen', href: '/connections', icon: ServerIcon },
  { name: 'Snippets', href: '/snippets', icon: CodeBracketIcon },
  { name: 'Kanban', href: '/kanban', icon: ViewColumnsIcon },
  { name: 'Projekte', href: '/projects', icon: FolderIcon },
  { name: 'Zeiterfassung', href: '/time', icon: ClockIcon },
  { name: 'Webhooks', href: '/webhooks', icon: BellIcon },
  { name: 'Bookmarks', href: '/bookmarks', icon: BookmarkIcon },
  { name: 'Uptime Monitor', href: '/uptime', icon: SignalIcon },
  { name: 'Rechnungen', href: '/invoices', icon: CurrencyDollarIcon },
  { name: 'API Tester', href: '/api-tester', icon: BeakerIcon },
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

      <!-- Project Selector -->
      <div class="px-3 py-3 border-b border-dark-700">
        <div v-if="!uiStore.sidebarCollapsed" class="relative">
          <button
            @click="showProjectDropdown = !showProjectDropdown"
            class="w-full flex items-center justify-between px-3 py-2 bg-dark-700 hover:bg-dark-600 rounded-lg transition-colors"
          >
            <div class="flex items-center gap-2 min-w-0">
              <FolderIcon class="w-4 h-4 text-gray-400 flex-shrink-0" />
              <span class="text-sm text-white truncate">
                {{ projectStore.selectedProject?.name || 'Alle Projekte' }}
              </span>
            </div>
            <ChevronUpDownIcon class="w-4 h-4 text-gray-400 flex-shrink-0" />
          </button>

          <!-- Dropdown -->
          <div
            v-if="showProjectDropdown"
            class="absolute left-0 right-0 mt-1 py-1 bg-dark-700 border border-dark-600 rounded-lg shadow-xl z-50 max-h-64 overflow-y-auto"
          >
            <!-- All Projects Option -->
            <button
              @click="clearProjectSelection"
              class="w-full flex items-center gap-2 px-3 py-2 text-left hover:bg-dark-600 transition-colors"
              :class="!projectStore.selectedProjectId ? 'bg-dark-600' : ''"
            >
              <span class="w-2 h-2 rounded-full bg-gray-500"></span>
              <span class="text-sm text-gray-300">Alle Projekte</span>
            </button>

            <div class="h-px bg-dark-600 my-1"></div>

            <!-- Project List -->
            <button
              v-for="project in projectStore.activeProjects"
              :key="project.id"
              @click="selectProject(project.id)"
              class="w-full flex items-center gap-2 px-3 py-2 text-left hover:bg-dark-600 transition-colors"
              :class="projectStore.selectedProjectId === project.id ? 'bg-dark-600' : ''"
            >
              <span
                class="w-2 h-2 rounded-full flex-shrink-0"
                :style="{ backgroundColor: project.color }"
              ></span>
              <span class="text-sm text-gray-300 truncate">{{ project.name }}</span>
            </button>

            <div v-if="projectStore.activeProjects.length === 0" class="px-3 py-2 text-xs text-gray-500">
              Keine Projekte vorhanden
            </div>
          </div>

          <!-- Backdrop -->
          <div
            v-if="showProjectDropdown"
            class="fixed inset-0 z-40"
            @click="showProjectDropdown = false"
          ></div>
        </div>

        <!-- Collapsed: Just show icon with color indicator -->
        <div v-else class="flex justify-center">
          <button
            @click="showProjectDropdown = !showProjectDropdown"
            class="p-2 bg-dark-700 hover:bg-dark-600 rounded-lg transition-colors relative"
            :title="projectStore.selectedProject?.name || 'Alle Projekte'"
          >
            <FolderIcon class="w-5 h-5 text-gray-400" />
            <span
              v-if="projectStore.selectedProject"
              class="absolute -top-1 -right-1 w-3 h-3 rounded-full border-2 border-dark-800"
              :style="{ backgroundColor: projectStore.selectedProject.color }"
            ></span>
          </button>
        </div>
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
