<script setup lang="ts">
import { computed, onMounted, ref, watch, type Component } from 'vue'
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
  ChevronUpDownIcon,
  XMarkIcon,
  StarIcon,
  MagnifyingGlassIcon,
  MapPinIcon,
  FolderIcon,
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
const activeGroupId = ref<string | null>(null)
const showProjectDropdown = ref(false)
const flyoutHoverTimeout = ref<ReturnType<typeof setTimeout> | null>(null)
const isHoveringFlyout = ref(false)

// Icon map for favorites
const favoriteIcons: Record<string, Component> = {
  list: () => import('@heroicons/vue/24/outline/ListBulletIcon'),
  document: () => import('@heroicons/vue/24/outline/DocumentTextIcon'),
  kanban_board: () => import('@heroicons/vue/24/outline/ViewColumnsIcon'),
  project: () => import('@heroicons/vue/24/outline/FolderIcon'),
  checklist: () => import('@heroicons/vue/24/outline/ClipboardDocumentCheckIcon'),
  snippet: () => import('@heroicons/vue/24/outline/CodeBracketIcon'),
  bookmark_group: () => import('@heroicons/vue/24/outline/BookmarkIcon'),
  connection: () => import('@heroicons/vue/24/outline/ServerIcon'),
}

// Load data on mount
onMounted(async () => {
  await featureStore.loadFeatures()
  projectStore.loadProjects()
  favoritesStore.load()
  quickAccessStore.load()
  // Set initial active group based on current route
  setActiveGroupFromRoute()
})

// Watch route changes
watch(() => route.path, () => {
  setActiveGroupFromRoute()
})

function setActiveGroupFromRoute() {
  for (const group of filteredGroups.value) {
    if (group.href && isActive(group.href)) {
      activeGroupId.value = group.id
      return
    }
    if (group.children?.some(child => isActive(child.href))) {
      activeGroupId.value = group.id
      return
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

// Active group's children for the flyout
const activeGroupChildren = computed<NavItem[]>(() => {
  if (!activeGroupId.value) return []
  const group = filteredGroups.value.find(g => g.id === activeGroupId.value)
  return group?.children ?? []
})

const activeGroupName = computed(() => {
  const group = filteredGroups.value.find(g => g.id === activeGroupId.value)
  return group?.name ?? ''
})

// Show flyout only for groups with children
const showFlyout = computed(() => {
  if (props.isMobile && props.mobileOpen) return activeGroupChildren.value.length > 0
  return activeGroupChildren.value.length > 0 && (isHoveringFlyout.value || isGroupActiveByRoute(activeGroupId.value))
})

function isGroupActiveByRoute(groupId: string | null): boolean {
  if (!groupId) return false
  const group = filteredGroups.value.find(g => g.id === groupId)
  if (!group) return false
  if (group.href) return isActive(group.href)
  return group.children?.some(child => isActive(child.href)) ?? false
}

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

// Interaction handlers
function handleRailClick(group: NavGroup) {
  if (group.href) {
    // Direct link group
    navigateTo(group.href)
    activeGroupId.value = group.id
    return
  }
  // Toggle flyout
  if (activeGroupId.value === group.id) {
    activeGroupId.value = null
  } else {
    activeGroupId.value = group.id
  }
}

function handleRailHover(group: NavGroup) {
  if (props.isMobile) return
  if (flyoutHoverTimeout.value) clearTimeout(flyoutHoverTimeout.value)
  isHoveringFlyout.value = true
  activeGroupId.value = group.id
}

function handleRailLeave() {
  if (props.isMobile) return
  flyoutHoverTimeout.value = setTimeout(() => {
    if (!isHoveringFlyout.value) {
      // Keep flyout open if a child is active
      if (!isGroupActiveByRoute(activeGroupId.value)) {
        activeGroupId.value = null
      }
    }
  }, 200)
}

function handleFlyoutEnter() {
  isHoveringFlyout.value = true
  if (flyoutHoverTimeout.value) clearTimeout(flyoutHoverTimeout.value)
}

function handleFlyoutLeave() {
  isHoveringFlyout.value = false
  flyoutHoverTimeout.value = setTimeout(() => {
    if (!isGroupActiveByRoute(activeGroupId.value)) {
      activeGroupId.value = null
    }
  }, 200)
}

function navigateTo(href: string) {
  router.push(href)
  if (props.isMobile) emit('close')
}

function navigateToFavorite(fav: any) {
  const favRoute = favoritesStore.getItemRoute(fav)
  router.push(favRoute)
  if (props.isMobile) emit('close')
}

function selectProject(projectId: number) {
  projectStore.selectProject(projectId)
  showProjectDropdown.value = false
}

function clearProjectSelection() {
  projectStore.clearSelection()
  showProjectDropdown.value = false
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

  <!-- ICON RAIL -->
  <aside
    class="fixed left-0 top-0 h-screen z-40 flex transition-transform duration-300"
    :class="[
      isMobile && !mobileOpen ? '-translate-x-full' : 'translate-x-0',
    ]"
  >
    <!-- Rail bar -->
    <div class="w-rail bg-dark-950/80 backdrop-blur-2xl border-r border-white/[0.06] flex flex-col items-center">
      <!-- Logo -->
      <div class="h-16 flex items-center justify-center shrink-0">
        <img
          src="/logo.png"
          alt="KyuubiSoft"
          class="w-9 h-9 drop-shadow-lg hover:scale-110 transition-transform duration-200 cursor-pointer"
          @click="navigateTo('/')"
        />
      </div>

      <!-- Project indicator -->
      <div class="px-2 pb-2 w-full shrink-0">
        <button
          @click="showProjectDropdown = !showProjectDropdown"
          class="w-full flex items-center justify-center p-2 rounded-xl
                 bg-white/[0.03] border border-white/[0.06]
                 hover:bg-white/[0.06] hover:border-white/[0.10]
                 transition-all duration-200 relative"
          :title="projectStore.selectedProject?.name || 'Alle Projekte'"
        >
          <FolderIcon class="w-4 h-4 text-gray-400" />
          <span
            v-if="projectStore.selectedProject"
            class="absolute -top-0.5 -right-0.5 w-2.5 h-2.5 rounded-full border-2 border-dark-950"
            :style="{ backgroundColor: projectStore.selectedProject.color }"
          />
        </button>

        <!-- Project dropdown -->
        <Transition name="fade">
          <div
            v-if="showProjectDropdown"
            class="absolute left-rail ml-2 top-16 z-50
                   bg-dark-900/95 backdrop-blur-2xl border border-white/[0.08]
                   rounded-xl shadow-float py-1.5 min-w-52 animate-scale-in"
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
      </div>

      <!-- Divider -->
      <div class="w-8 h-px bg-white/[0.06] mb-2" />

      <!-- Navigation groups -->
      <nav class="flex-1 w-full px-2 py-1 overflow-y-auto scrollbar-hide space-y-1">
        <div
          v-for="group in filteredGroups"
          :key="group.id"
          class="relative group/rail"
        >
          <button
            @click="handleRailClick(group)"
            @mouseenter="handleRailHover(group)"
            @mouseleave="handleRailLeave"
            class="nav-rail-item w-full"
            :class="{
              'nav-rail-item-active': isGroupActive(group),
              'bg-white/[0.06] text-gray-300': activeGroupId === group.id && !isGroupActive(group),
            }"
            :title="group.name"
          >
            <!-- Active indicator -->
            <span
              v-if="isGroupActive(group)"
              class="absolute left-0 top-1/2 -translate-y-1/2 w-0.5 h-5 bg-primary-500 rounded-r-full shadow-glow-sm"
            />
            <component
              :is="group.icon"
              class="w-5 h-5"
            />
          </button>

          <!-- Tooltip (desktop only, when no flyout) -->
          <div
            v-if="!isMobile && !group.children"
            class="absolute left-full ml-3 top-1/2 -translate-y-1/2
                   opacity-0 group-hover/rail:opacity-100
                   transition-opacity duration-150 pointer-events-none z-50"
          >
            <div class="tooltip">{{ group.name }}</div>
          </div>
        </div>
      </nav>

      <!-- Bottom section -->
      <div class="px-2 py-3 shrink-0 space-y-1 w-full">
        <!-- Search shortcut -->
        <button
          class="nav-rail-item w-full"
          title="Suche (Ctrl+K)"
          @click="uiStore.showCommandPalette = true"
        >
          <MagnifyingGlassIcon class="w-5 h-5" />
        </button>

        <!-- User avatar -->
        <div class="flex justify-center pt-1">
          <div class="avatar cursor-pointer hover:shadow-glow-sm transition-shadow" @click="navigateTo('/settings')">
            <span class="text-xs font-bold text-white">
              {{ authStore.user?.username?.[0]?.toUpperCase() || 'U' }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- FLYOUT PANEL -->
    <Transition name="slide">
      <div
        v-if="showFlyout"
        class="w-flyout bg-dark-950/90 backdrop-blur-2xl border-r border-white/[0.06] shadow-float flex flex-col"
        @mouseenter="handleFlyoutEnter"
        @mouseleave="handleFlyoutLeave"
      >
        <!-- Flyout header -->
        <div class="h-16 flex items-center px-4 border-b border-white/[0.06] shrink-0">
          <h2 class="text-sm font-semibold text-gray-200">{{ activeGroupName }}</h2>
        </div>

        <!-- Flyout items -->
        <nav class="flex-1 overflow-y-auto py-2 px-2">
          <button
            v-for="item in activeGroupChildren"
            :key="item.id"
            @click="navigateTo(item.href)"
            class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-all duration-150 relative group/item"
            :class="[
              isActive(item.href)
                ? 'bg-primary-500/10 text-primary-300'
                : 'text-gray-400 hover:bg-white/[0.06] hover:text-gray-200'
            ]"
          >
            <span
              v-if="isActive(item.href)"
              class="nav-active-bar"
            />
            <component
              :is="item.icon"
              class="w-4 h-4 shrink-0"
              :class="isActive(item.href) ? 'text-primary-400' : ''"
            />
            <span class="flex-1 text-left truncate">{{ item.name }}</span>
            <!-- Pin indicator -->
            <MapPinIcon
              v-if="quickAccessStore.isPinned(item.id)"
              class="w-3 h-3 text-primary-400 opacity-50"
            />
          </button>
        </nav>

        <!-- Favorites section (in flyout) -->
        <div
          v-if="favoritesStore.favorites.length > 0"
          class="border-t border-white/[0.06] px-2 py-2 shrink-0"
        >
          <div class="flex items-center gap-2 px-3 py-1.5 mb-1">
            <StarIcon class="w-3.5 h-3.5 text-yellow-500/80" />
            <span class="text-2xs font-semibold uppercase tracking-wider text-gray-500">Favoriten</span>
          </div>
          <button
            v-for="fav in favoritesStore.favorites.slice(0, 5)"
            :key="fav.id"
            @click="navigateToFavorite(fav)"
            class="w-full flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-xs text-gray-500
                   hover:bg-white/[0.04] hover:text-gray-300 transition-colors truncate"
          >
            <StarIcon class="w-3 h-3 shrink-0 text-yellow-500/60" />
            <span class="truncate">{{ fav.item?.title || fav.item?.name || 'Unbenannt' }}</span>
          </button>
        </div>
      </div>
    </Transition>
  </aside>

  <!-- Mobile close button -->
  <button
    v-if="isMobile && mobileOpen"
    @click="$emit('close')"
    class="fixed top-4 z-50 btn-icon-glass"
    :style="{ left: showFlyout ? 'calc(var(--rail-width, 4.5rem) + 15rem + 0.5rem)' : 'calc(var(--rail-width, 4.5rem) + 0.5rem)' }"
  >
    <XMarkIcon class="w-5 h-5" />
  </button>

  <!-- Backdrop for project dropdown -->
  <div
    v-if="showProjectDropdown"
    class="fixed inset-0 z-40"
    @click="showProjectDropdown = false"
  />
</template>
