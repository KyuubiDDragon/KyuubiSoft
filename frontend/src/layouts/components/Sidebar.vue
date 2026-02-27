<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { useProjectStore } from '@/stores/project'
import { useFeatureStore } from '@/stores/features'
import { useFavoritesStore } from '@/stores/favorites'
import { useQuickAccessStore } from '@/stores/quickAccess'
import {
  navigationGroups,
  type NavGroup,
  type NavItem,
} from '@/core/config/navigation'
import {
  ChevronRightIcon,
  ChevronDownIcon,
  ChevronDoubleLeftIcon,
  ChevronDoubleRightIcon,
  XMarkIcon,
  StarIcon,
  MagnifyingGlassIcon,
  FolderIcon,
  Cog6ToothIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps<{
  isMobile?: boolean
  mobileOpen?: boolean
}>()

const emit = defineEmits(['close'])

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const uiStore = useUiStore()
const projectStore = useProjectStore()
const featureStore = useFeatureStore()
const favoritesStore = useFavoritesStore()
const quickAccessStore = useQuickAccessStore()

// State
const showProjectDropdown = ref(false)
const popoverGroup = ref<NavGroup | null>(null)
const popoverPosition = ref({ top: 0, left: 0 })

// Tree filter
const treeFilter = ref('')
const treeFilterInput = ref<HTMLInputElement | null>(null)

// Sidebar collapsed state (use store)
const collapsed = computed(() => !props.isMobile && uiStore.sidebarCollapsed)

// Section expanded/collapsed state
const expandedSections = ref<Record<string, boolean>>({})

// Load section state from localStorage
const storedSections = localStorage.getItem('sidebarSections')
if (storedSections) {
  try {
    expandedSections.value = JSON.parse(storedSections)
  } catch { /* ignore */ }
}

// Save section state on change
watch(expandedSections, (val) => {
  localStorage.setItem('sidebarSections', JSON.stringify(val))
}, { deep: true })

// Load data on mount
onMounted(async () => {
  await featureStore.loadFeatures()
  projectStore.loadProjects()
  favoritesStore.load()
  quickAccessStore.load()
  // Auto-expand section containing active route
  autoExpandActiveSection()
})

// Watch route changes
watch(() => route.path, () => {
  autoExpandActiveSection()
  // Close popover on navigation
  popoverGroup.value = null
})

function autoExpandActiveSection() {
  for (const group of filteredGroups.value) {
    if (group.children?.some(child => isActive(child.href))) {
      expandedSections.value[group.id] = true
    }
  }
}

// Filter navigation based on permissions and features
function filterItem(item: NavItem): boolean {
  if (item.feature && !featureStore.isEnabled(item.feature)) return false
  if (item.permission && !authStore.hasPermission(item.permission)) return false
  return true
}

const filteredGroups = computed<NavGroup[]>(() => {
  return navigationGroups
    .map(group => {
      if (group.feature && !featureStore.isEnabled(group.feature)) return null
      if (group.permission && !authStore.hasPermission(group.permission)) return null
      if (group.children) {
        const filteredChildren = group.children.filter(filterItem)
        if (filteredChildren.length === 0) return null
        return { ...group, children: filteredChildren }
      }
      return group
    })
    .filter((g): g is NavGroup => g !== null)
})

// Tree-filtered navigation groups (applies text filter on top of permission/feature filtering)
const filteredTreeGroups = computed<NavGroup[]>(() => {
  const query = treeFilter.value.trim().toLowerCase()
  if (!query) return filteredGroups.value

  return filteredGroups.value
    .map(group => {
      const groupNameMatches = group.name.toLowerCase().includes(query)

      if (group.href) {
        return groupNameMatches ? group : null
      }

      const matchingChildren = group.children?.filter(
        child => child.name.toLowerCase().includes(query)
      ) ?? []

      if (groupNameMatches) return group
      if (matchingChildren.length > 0) return { ...group, children: matchingChildren }
      return null
    })
    .filter((g): g is NavGroup => g !== null)
})

// All navigation hrefs for sibling detection
const allHrefs = computed(() => {
  const hrefs = new Set<string>()
  for (const group of navigationGroups) {
    if (group.href) hrefs.add(group.href)
    if (group.children) {
      for (const child of group.children) {
        hrefs.add(child.href)
      }
    }
  }
  return hrefs
})

function isActive(href: string): boolean {
  if (href === '/') return route.path === '/'
  if (route.path === href) return true
  if (allHrefs.value.has(route.path)) return false

  const hrefSegments = href.split('/').filter(Boolean)
  const pathSegments = route.path.split('/').filter(Boolean)
  if (pathSegments.length <= hrefSegments.length) return false
  return hrefSegments.every((seg, i) => pathSegments[i] === seg)
}

function isGroupActive(group: NavGroup): boolean {
  if (group.href) return isActive(group.href)
  return group.children?.some(child => isActive(child.href)) ?? false
}

// Section toggle
function toggleSection(groupId: string) {
  expandedSections.value[groupId] = !expandedSections.value[groupId]
}

function isSectionExpanded(groupId: string): boolean {
  if (treeFilter.value.trim()) return true
  return expandedSections.value[groupId] ?? false
}

// Collapsed mode: popover for groups with children
function handleCollapsedGroupClick(group: NavGroup, event: MouseEvent) {
  if (group.href) {
    navigateTo(group.href)
    return
  }
  // Show popover with children
  const target = event.currentTarget as HTMLElement
  const rect = target.getBoundingClientRect()
  popoverPosition.value = {
    top: rect.top,
    left: rect.right + 8,
  }
  if (popoverGroup.value?.id === group.id) {
    popoverGroup.value = null
  } else {
    popoverGroup.value = group
  }
}

// Tree filter highlight
function escapeRegex(str: string): string {
  return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

function highlightMatch(text: string): string {
  if (!treeFilter.value.trim()) return text
  const query = treeFilter.value.trim()
  const regex = new RegExp(`(${escapeRegex(query)})`, 'gi')
  return text.replace(regex, '<mark class="tree-highlight">$1</mark>')
}

// Navigation
function navigateTo(href: string) {
  router.push(href)
  treeFilter.value = ''
  popoverGroup.value = null
  if (props.isMobile) emit('close')
}

function navigateToFavorite(fav: any) {
  const favRoute = favoritesStore.getItemRoute(fav)
  router.push(favRoute)
  popoverGroup.value = null
  if (props.isMobile) emit('close')
}

// Project selection
function selectProject(projectId: string) {
  projectStore.selectProject(projectId)
  showProjectDropdown.value = false
}

function clearProjectSelection() {
  projectStore.clearSelection()
  showProjectDropdown.value = false
}

function closePopover() {
  popoverGroup.value = null
}
</script>

<template>
  <!-- Mobile overlay -->
  <Transition name="fade">
    <div
      v-if="isMobile && mobileOpen"
      class="fixed inset-0 bg-black/50 backdrop-blur-sm z-30"
      @click="$emit('close')"
    />
  </Transition>

  <!-- SIDEBAR -->
  <aside
    class="fixed left-0 top-0 h-screen z-40 flex flex-col
           bg-dark-900/90 backdrop-blur-2xl border-r border-white/[0.10]
           transition-all duration-300 ease-smooth"
    :class="[
      collapsed ? 'w-16' : 'w-60',
      isMobile && !mobileOpen ? '-translate-x-full' : 'translate-x-0',
    ]"
  >
    <!-- Header: Logo + Collapse toggle -->
    <div class="h-14 flex items-center shrink-0 border-b border-white/[0.08]" :class="collapsed ? 'px-3 justify-center' : 'px-4 justify-between'">
      <div class="flex items-center gap-3 min-w-0 cursor-pointer" @click="navigateTo('/')">
        <img
          src="/logo.png"
          alt="KyuubiSoft"
          class="w-8 h-8 drop-shadow-lg shrink-0 hover:scale-110 transition-transform duration-200"
        />
        <span v-if="!collapsed" class="text-sm font-bold text-white truncate">KyuubiSoft</span>
      </div>
      <button
        v-if="!collapsed && !isMobile"
        @click="uiStore.toggleSidebarCollapse()"
        class="p-1 rounded-lg text-gray-500 hover:text-gray-300 hover:bg-white/[0.06] transition-all"
        title="Sidebar einklappen"
      >
        <ChevronDoubleLeftIcon class="w-4 h-4" />
      </button>
      <button
        v-if="collapsed && !isMobile"
        @click="uiStore.toggleSidebarCollapse()"
        class="p-1 rounded-lg text-gray-500 hover:text-gray-300 hover:bg-white/[0.06] transition-all"
        title="Sidebar ausklappen"
      >
        <ChevronDoubleRightIcon class="w-4 h-4" />
      </button>
    </div>

    <!-- Project Selector -->
    <div class="shrink-0" :class="collapsed ? 'px-2 py-2' : 'px-3 py-2'">
      <button
        @click="showProjectDropdown = !showProjectDropdown"
        class="w-full flex items-center rounded-xl transition-all duration-200 relative
               bg-white/[0.04] border border-white/[0.08]
               hover:bg-white/[0.07] hover:border-white/[0.12]"
        :class="collapsed ? 'p-2 justify-center' : 'px-3 py-2 gap-2.5'"
        :title="projectStore.selectedProject?.name || 'Alle Projekte'"
      >
        <FolderIcon class="w-4 h-4 text-gray-400 shrink-0" />
        <template v-if="!collapsed">
          <span class="flex-1 text-left text-sm text-gray-300 truncate">
            {{ projectStore.selectedProject?.name || 'Alle Projekte' }}
          </span>
          <ChevronDownIcon class="w-3.5 h-3.5 text-gray-500 shrink-0" />
        </template>
        <span
          v-if="projectStore.selectedProject"
          class="absolute -top-0.5 -right-0.5 w-2.5 h-2.5 rounded-full border-2 border-dark-950"
          :style="{ backgroundColor: projectStore.selectedProject.color }"
        />
      </button>
    </div>

    <!-- Tree Filter / Search -->
    <div class="shrink-0" :class="collapsed ? 'px-2 pb-2' : 'px-3 pb-2'">
      <!-- Collapsed: icon button opens command palette -->
      <button
        v-if="collapsed"
        @click="uiStore.showCommandPalette = true"
        class="w-full flex items-center justify-center p-2 rounded-xl
               bg-white/[0.03] border border-white/[0.06]
               hover:bg-white/[0.06] hover:border-white/[0.10]
               transition-all duration-200"
        title="Suche (Ctrl+K)"
      >
        <MagnifyingGlassIcon class="w-4 h-4 text-gray-500" />
      </button>

      <!-- Expanded: inline filter input -->
      <div v-else class="relative">
        <MagnifyingGlassIcon class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-500 pointer-events-none" />
        <input
          ref="treeFilterInput"
          v-model="treeFilter"
          type="text"
          placeholder="Filter..."
          class="w-full pl-8 pr-8 py-1.5 rounded-lg text-xs text-gray-200
                 bg-white/[0.04] border border-white/[0.08]
                 placeholder-gray-600
                 focus:outline-none focus:bg-white/[0.06] focus:border-white/[0.12]
                 transition-all duration-200"
          @keydown.escape="treeFilter = ''; ($event.target as HTMLInputElement).blur()"
          @keydown.meta.k.prevent="uiStore.showCommandPalette = true"
          @keydown.ctrl.k.prevent="uiStore.showCommandPalette = true"
        />
        <button
          v-if="treeFilter"
          @click="treeFilter = ''"
          class="absolute right-2 top-1/2 -translate-y-1/2 p-0.5 rounded text-gray-500 hover:text-gray-300 transition-colors"
        >
          <XMarkIcon class="w-3 h-3" />
        </button>
        <kbd
          v-else
          class="absolute right-2 top-1/2 -translate-y-1/2
                 hidden sm:inline-flex items-center px-1 py-0.5
                 text-2xs text-gray-600 bg-white/[0.04] border border-white/[0.06] rounded
                 cursor-pointer"
          @click="uiStore.showCommandPalette = true"
        >
          &#8984;K
        </kbd>
      </div>
    </div>

    <!-- Divider -->
    <div class="mx-3 h-px bg-white/[0.08]" />

    <!-- Scrollable Tree Navigation -->
    <nav class="flex-1 overflow-y-auto scrollbar-hide py-1" :class="collapsed ? 'px-2' : 'px-1'">

      <!-- ===== COLLAPSED MODE ===== -->
      <template v-if="collapsed">
        <div class="space-y-0.5">
          <template v-for="group in filteredGroups" :key="group.id">
            <button
              @click="group.href ? navigateTo(group.href) : handleCollapsedGroupClick(group, $event)"
              class="sidebar-item-collapsed w-full"
              :class="{
                'sidebar-item-collapsed-active': isGroupActive(group),
                'bg-white/[0.06] text-gray-300': popoverGroup?.id === group.id && !isGroupActive(group),
              }"
              :title="group.name"
            >
              <span v-if="isGroupActive(group)" class="nav-active-bar" />
              <component :is="group.icon" class="w-5 h-5" />
            </button>
          </template>
        </div>

        <!-- Collapsed: Favorites icon -->
        <template v-if="favoritesStore.favorites.length > 0">
          <div class="h-px bg-white/[0.06] my-2" />
          <button
            class="sidebar-item-collapsed w-full"
            title="Favoriten"
            @click="navigateTo('/settings')"
          >
            <StarIcon class="w-5 h-5 text-yellow-500/60" />
          </button>
        </template>
      </template>

      <!-- ===== EXPANDED MODE: TREE VIEW ===== -->
      <template v-else>
        <div class="tree-root">
          <template v-for="group in filteredTreeGroups" :key="group.id">

            <!-- LEAF NODE: Direct link groups (Dashboard, News, Wiki) -->
            <div v-if="group.href" class="relative">
              <button
                @click="navigateTo(group.href!)"
                class="tree-node-row"
                :class="{ 'tree-node-row-active': isGroupActive(group) }"
              >
                <span v-if="isGroupActive(group)" class="nav-active-bar" />
                <span class="tree-arrow-spacer" />
                <component :is="group.icon" class="tree-icon" />
                <span
                  v-if="!treeFilter.trim()"
                  class="tree-label"
                >{{ group.name }}</span>
                <span
                  v-else
                  class="tree-label"
                  v-html="highlightMatch(group.name)"
                />
              </button>
            </div>

            <!-- FOLDER NODE: Section groups -->
            <div v-else class="relative">
              <!-- Folder row -->
              <button
                @click="toggleSection(group.id)"
                class="tree-node-row tree-folder-row"
                :class="{
                  'tree-node-row-active': isGroupActive(group) && !isSectionExpanded(group.id),
                }"
              >
                <ChevronRightIcon
                  class="tree-arrow"
                  :class="{ 'tree-arrow-expanded': isSectionExpanded(group.id) }"
                />
                <component :is="group.icon" class="tree-icon" />
                <span
                  v-if="!treeFilter.trim()"
                  class="tree-label"
                >{{ group.name }}</span>
                <span
                  v-else
                  class="tree-label"
                  v-html="highlightMatch(group.name)"
                />
                <span
                  v-if="isGroupActive(group) && !isSectionExpanded(group.id)"
                  class="w-1.5 h-1.5 rounded-full bg-primary-400 shrink-0 ml-auto"
                />
              </button>

              <!-- Children (with tree lines) -->
              <Transition name="tree-expand">
                <div v-show="isSectionExpanded(group.id)" class="tree-children">
                  <div
                    v-for="item in group.children"
                    :key="item.id"
                    class="tree-child-node"
                  >
                    <button
                      @click="navigateTo(item.href)"
                      class="tree-node-row"
                      :class="{ 'tree-node-row-active': isActive(item.href) }"
                    >
                      <span v-if="isActive(item.href)" class="nav-active-bar" />
                      <span class="tree-indent" />
                      <component :is="item.icon" class="tree-icon tree-icon-sm" />
                      <span
                        v-if="!treeFilter.trim()"
                        class="tree-label"
                      >{{ item.name }}</span>
                      <span
                        v-else
                        class="tree-label"
                        v-html="highlightMatch(item.name)"
                      />
                    </button>
                  </div>
                </div>
              </Transition>
            </div>

          </template>

          <!-- Favorites "folder" -->
          <template v-if="favoritesStore.favorites.length > 0 && !treeFilter.trim()">
            <div class="tree-divider" />
            <div class="relative">
              <button
                @click="toggleSection('_favorites')"
                class="tree-node-row tree-folder-row"
              >
                <ChevronRightIcon
                  class="tree-arrow"
                  :class="{ 'tree-arrow-expanded': isSectionExpanded('_favorites') }"
                />
                <StarIcon class="tree-icon text-yellow-500/80" />
                <span class="tree-label text-gray-500">Favoriten</span>
              </button>

              <Transition name="tree-expand">
                <div v-show="isSectionExpanded('_favorites')" class="tree-children">
                  <div
                    v-for="fav in favoritesStore.favorites.slice(0, 5)"
                    :key="fav.item_id"
                    class="tree-child-node"
                  >
                    <button
                      @click="navigateToFavorite(fav)"
                      class="tree-node-row"
                    >
                      <span class="tree-indent" />
                      <StarIcon class="tree-icon tree-icon-sm text-yellow-500/60" />
                      <span class="tree-label truncate">
                        {{ (fav as any).item?.title || (fav as any).item?.name || 'Unbenannt' }}
                      </span>
                    </button>
                  </div>
                </div>
              </Transition>
            </div>
          </template>
        </div>

        <!-- Empty state when filter matches nothing -->
        <div v-if="filteredTreeGroups.length === 0 && treeFilter.trim()" class="px-3 py-6 text-center">
          <p class="text-xs text-gray-500">Keine Ergebnisse</p>
        </div>
      </template>
    </nav>

    <!-- Bottom: User section -->
    <div class="shrink-0 border-t border-white/[0.08]" :class="collapsed ? 'px-2 py-2' : 'px-3 py-3'">
      <div v-if="!collapsed" class="flex items-center gap-3">
        <div class="avatar cursor-pointer hover:shadow-glow-sm transition-shadow shrink-0" @click="navigateTo('/settings')">
          <span class="text-xs font-bold text-white">
            {{ authStore.user?.username?.[0]?.toUpperCase() || 'U' }}
          </span>
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-medium text-gray-200 truncate">{{ authStore.user?.username || 'User' }}</p>
          <p class="text-2xs text-gray-500 truncate">{{ authStore.user?.email || '' }}</p>
        </div>
        <button
          @click="navigateTo('/settings')"
          class="p-1 rounded-lg text-gray-500 hover:text-gray-300 hover:bg-white/[0.06] transition-all shrink-0"
          title="Einstellungen"
        >
          <Cog6ToothIcon class="w-4 h-4" />
        </button>
      </div>
      <div v-else class="flex justify-center">
        <div class="avatar cursor-pointer hover:shadow-glow-sm transition-shadow" @click="navigateTo('/settings')">
          <span class="text-xs font-bold text-white">
            {{ authStore.user?.username?.[0]?.toUpperCase() || 'U' }}
          </span>
        </div>
      </div>
    </div>
  </aside>

  <!-- Mobile close button -->
  <button
    v-if="isMobile && mobileOpen"
    @click="$emit('close')"
    class="fixed top-4 z-50 btn-icon-glass"
    :style="{ left: 'calc(15rem + 0.5rem)' }"
  >
    <XMarkIcon class="w-5 h-5" />
  </button>

  <!-- Project dropdown (teleported to body for z-index) -->
  <Teleport to="body">
    <Transition name="fade">
      <div v-if="showProjectDropdown" class="fixed inset-0 z-[45]" @click="showProjectDropdown = false" />
    </Transition>
    <Transition name="fade">
      <div
        v-if="showProjectDropdown"
        class="fixed z-50 bg-dark-900/[0.98] backdrop-blur-2xl border border-white/[0.12]
               rounded-xl shadow-float py-1.5 min-w-52 animate-scale-in"
        :style="{
          top: '4.5rem',
          left: collapsed ? '4.5rem' : '1rem',
        }"
      >
        <div class="dropdown-header">Projekte</div>
        <button
          @click="clearProjectSelection"
          class="dropdown-item"
          :class="!projectStore.selectedProjectId ? 'dropdown-item-active' : ''"
        >
          <span class="w-2 h-2 rounded-full bg-gray-500" />
          <span>Alle Projekte</span>
        </button>
        <div class="dropdown-divider" />
        <button
          v-for="project in projectStore.activeProjects"
          :key="project.id"
          @click="selectProject(project.id)"
          class="dropdown-item"
          :class="projectStore.selectedProjectId === project.id ? 'dropdown-item-active' : ''"
        >
          <span
            class="w-2 h-2 rounded-full shrink-0"
            :style="{ backgroundColor: project.color }"
          />
          <span class="truncate">{{ project.name }}</span>
        </button>
        <div v-if="projectStore.activeProjects.length === 0" class="px-3 py-2 text-xs text-gray-500">
          Keine Projekte
        </div>
      </div>
    </Transition>
  </Teleport>

  <!-- Collapsed mode: group popover (teleported to body) -->
  <Teleport to="body">
    <Transition name="fade">
      <div v-if="popoverGroup" class="fixed inset-0 z-[45]" @click="closePopover" />
    </Transition>
    <Transition name="fade">
      <div
        v-if="popoverGroup"
        class="sidebar-popover"
        :style="{
          top: popoverPosition.top + 'px',
          left: popoverPosition.left + 'px',
        }"
      >
        <div class="dropdown-header">{{ popoverGroup.name }}</div>
        <button
          v-for="item in popoverGroup.children"
          :key="item.id"
          @click="navigateTo(item.href)"
          class="dropdown-item"
          :class="{ 'dropdown-item-active': isActive(item.href) }"
        >
          <component :is="item.icon" class="w-4 h-4 shrink-0" />
          <span class="truncate">{{ item.name }}</span>
        </button>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
/* Tree expand/collapse transition */
.tree-expand-enter-active {
  transition: all 0.2s ease-out;
  overflow: hidden;
}
.tree-expand-leave-active {
  transition: all 0.15s ease-in;
  overflow: hidden;
}
.tree-expand-enter-from,
.tree-expand-leave-to {
  opacity: 0;
  max-height: 0;
}
.tree-expand-enter-to,
.tree-expand-leave-from {
  opacity: 1;
  max-height: 40rem;
}
</style>
