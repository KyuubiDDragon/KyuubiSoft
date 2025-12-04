<script setup>
import { computed, onMounted, ref, watch } from 'vue'
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
  WrenchScrewdriverIcon,
  Cog6ToothIcon,
  UsersIcon,
  ShieldCheckIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  ChevronDownIcon,
  ChevronUpDownIcon,
  XMarkIcon,
  DocumentDuplicateIcon,
  BriefcaseIcon,
  CommandLineIcon,
  CalendarIcon,
  CubeIcon,
} from '@heroicons/vue/24/outline'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const uiStore = useUiStore()
const projectStore = useProjectStore()

const showProjectDropdown = ref(false)
const expandedGroups = ref([]) // Only expand groups with active routes

// Load projects on mount
onMounted(() => {
  projectStore.loadProjects()
  // Expand group containing current route
  expandGroupForCurrentRoute()
})

// Watch route changes to expand relevant group
watch(() => route.path, () => {
  expandGroupForCurrentRoute()
})

function expandGroupForCurrentRoute() {
  for (const group of navigationGroups.value) {
    if (group.children) {
      const hasActiveChild = group.children.some(child => isActive(child.href))
      if (hasActiveChild && !expandedGroups.value.includes(group.id)) {
        expandedGroups.value.push(group.id)
      }
    }
  }
}

function selectProject(projectId) {
  projectStore.selectProject(projectId)
  showProjectDropdown.value = false
}

function clearProjectSelection() {
  projectStore.clearSelection()
  showProjectDropdown.value = false
}

function toggleGroup(groupId) {
  const index = expandedGroups.value.indexOf(groupId)
  if (index === -1) {
    expandedGroups.value.push(groupId)
  } else {
    expandedGroups.value.splice(index, 1)
  }
}

function isGroupExpanded(groupId) {
  return expandedGroups.value.includes(groupId)
}

// Navigation mit Gruppen
const allNavigationGroups = [
  // Dashboard - Standalone
  { id: 'dashboard', name: 'Dashboard', href: '/', icon: HomeIcon },

  // Inhalte
  {
    id: 'inhalte',
    name: 'Inhalte',
    icon: DocumentDuplicateIcon,
    children: [
      { name: 'Listen', href: '/lists', icon: ListBulletIcon },
      { name: 'Dokumente', href: '/documents', icon: DocumentTextIcon },
      { name: 'Snippets', href: '/snippets', icon: CodeBracketIcon },
      { name: 'Bookmarks', href: '/bookmarks', icon: BookmarkIcon },
    ],
  },

  // Projektmanagement
  {
    id: 'projektmanagement',
    name: 'Projektmanagement',
    icon: BriefcaseIcon,
    children: [
      { name: 'Kanban', href: '/kanban', icon: ViewColumnsIcon },
      { name: 'Projekte', href: '/projects', icon: FolderIcon },
      { name: 'Kalender', href: '/calendar', icon: CalendarIcon },
      { name: 'Zeiterfassung', href: '/time', icon: ClockIcon },
    ],
  },

  // Entwicklung & Tools
  {
    id: 'entwicklung',
    name: 'Entwicklung & Tools',
    icon: CommandLineIcon,
    children: [
      { name: 'Verbindungen', href: '/connections', icon: ServerIcon },
      { name: 'Webhooks', href: '/webhooks', icon: BellIcon },
      { name: 'Uptime Monitor', href: '/uptime', icon: SignalIcon },
      { name: 'Toolbox', href: '/toolbox', icon: WrenchScrewdriverIcon },
    ],
  },

  // Docker
  {
    id: 'docker',
    name: 'Docker',
    icon: CubeIcon,
    children: [
      { name: 'Container Manager', href: '/docker', icon: ServerIcon },
      { name: 'Docker Hosts', href: '/docker/hosts', icon: ServerIcon },
      { name: 'Dockerfile Generator', href: '/docker/dockerfile', icon: DocumentTextIcon },
      { name: 'Compose Builder', href: '/docker/compose', icon: ViewColumnsIcon },
      { name: 'Command Builder', href: '/docker/command', icon: CommandLineIcon },
      { name: '.dockerignore', href: '/docker/ignore', icon: ShieldCheckIcon },
    ],
  },

  // Business
  {
    id: 'business',
    name: 'Business',
    icon: CurrencyDollarIcon,
    children: [
      { name: 'Rechnungen', href: '/invoices', icon: CurrencyDollarIcon },
    ],
  },

  // Administration
  {
    id: 'administration',
    name: 'Administration',
    icon: Cog6ToothIcon,
    children: [
      { name: 'Einstellungen', href: '/settings', icon: Cog6ToothIcon },
      { name: 'Benutzer', href: '/users', icon: UsersIcon, roles: ['owner', 'admin'] },
      { name: 'System', href: '/system', icon: ShieldCheckIcon, roles: ['owner'] },
    ],
  },
]

// Filter navigation based on user permissions
function filterItem(item) {
  if (!item.roles && !item.permission) return true
  if (item.roles) return item.roles.some(role => authStore.hasRole(role))
  if (item.permission) return authStore.hasPermission(item.permission)
  return false
}

const navigationGroups = computed(() => {
  return allNavigationGroups
    .map(group => {
      if (group.children) {
        const filteredChildren = group.children.filter(filterItem)
        // Hide group if no children are visible
        if (filteredChildren.length === 0) return null
        return { ...group, children: filteredChildren }
      }
      return filterItem(group) ? group : null
    })
    .filter(Boolean)
})

// Check if any child in group is active
function isGroupActive(group) {
  if (!group.children) return false
  return group.children.some(child => isActive(child.href))
}

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
      <div class="h-16 flex items-center justify-center border-b border-dark-700 gap-2">
        <img
          src="/logo.png"
          alt="KyuubiSoft"
          class="w-10 h-10"
        />
        <h1
          v-if="!uiStore.sidebarCollapsed"
          class="text-xl font-bold text-gradient"
        >
          KyuubiSoft
        </h1>
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
        <template v-for="group in navigationGroups" :key="group.id">
          <!-- Standalone item (no children) -->
          <button
            v-if="!group.children"
            @click="navigateTo(group.href)"
            class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200"
            :class="[
              isActive(group.href)
                ? 'bg-primary-600 text-white'
                : 'text-gray-400 hover:bg-dark-700 hover:text-white'
            ]"
          >
            <component :is="group.icon" class="w-5 h-5 flex-shrink-0" />
            <span v-if="!uiStore.sidebarCollapsed" class="font-medium">
              {{ group.name }}
            </span>
          </button>

          <!-- Group with children -->
          <div v-else class="space-y-1">
            <!-- Group header -->
            <button
              @click="uiStore.sidebarCollapsed ? null : toggleGroup(group.id)"
              class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg transition-all duration-200"
              :class="[
                isGroupActive(group)
                  ? 'text-primary-400'
                  : 'text-gray-400 hover:bg-dark-700 hover:text-white'
              ]"
            >
              <div class="flex items-center gap-3">
                <component :is="group.icon" class="w-5 h-5 flex-shrink-0" />
                <span v-if="!uiStore.sidebarCollapsed" class="font-medium text-sm">
                  {{ group.name }}
                </span>
              </div>
              <ChevronDownIcon
                v-if="!uiStore.sidebarCollapsed"
                class="w-4 h-4 transition-transform duration-200"
                :class="{ 'rotate-180': isGroupExpanded(group.id) }"
              />
            </button>

            <!-- Children (expanded view) -->
            <div
              v-if="!uiStore.sidebarCollapsed && isGroupExpanded(group.id)"
              class="ml-4 pl-4 border-l border-dark-600 space-y-1"
            >
              <button
                v-for="child in group.children"
                :key="child.name"
                @click="navigateTo(child.href)"
                class="w-full flex items-center gap-3 px-3 py-2 rounded-lg transition-all duration-200"
                :class="[
                  isActive(child.href)
                    ? 'bg-primary-600 text-white'
                    : 'text-gray-400 hover:bg-dark-700 hover:text-white'
                ]"
              >
                <component :is="child.icon" class="w-4 h-4 flex-shrink-0" />
                <span class="font-medium text-sm">{{ child.name }}</span>
              </button>
            </div>

            <!-- Collapsed view: Show tooltip/dropdown on hover -->
            <div
              v-if="uiStore.sidebarCollapsed"
              class="group relative"
            >
              <div
                class="absolute left-full ml-2 top-0 hidden group-hover:block z-50"
              >
                <div class="bg-dark-700 border border-dark-600 rounded-lg shadow-xl py-2 min-w-48">
                  <div class="px-3 py-1 text-xs text-gray-500 font-semibold uppercase">
                    {{ group.name }}
                  </div>
                  <button
                    v-for="child in group.children"
                    :key="child.name"
                    @click="navigateTo(child.href)"
                    class="w-full flex items-center gap-3 px-3 py-2 transition-colors"
                    :class="[
                      isActive(child.href)
                        ? 'bg-primary-600 text-white'
                        : 'text-gray-300 hover:bg-dark-600 hover:text-white'
                    ]"
                  >
                    <component :is="child.icon" class="w-4 h-4 flex-shrink-0" />
                    <span class="text-sm">{{ child.name }}</span>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </template>
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
