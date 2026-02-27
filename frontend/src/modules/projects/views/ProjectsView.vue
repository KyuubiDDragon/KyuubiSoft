<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/core/api/axios'
import { useUiStore } from '@/stores/ui'
import { useToast } from '@/composables/useToast'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
import {
  PlusIcon,
  TrashIcon,
  PencilIcon,
  XMarkIcon,
  FolderIcon,
  LinkIcon,
  StarIcon,
  ClockIcon,
  DocumentTextIcon,
  ListBulletIcon,
  ViewColumnsIcon,
  ServerIcon,
  CodeBracketIcon,
  ArchiveBoxIcon,
  CheckCircleIcon,
  FunnelIcon,
  ArrowsUpDownIcon,
  ArrowTopRightOnSquareIcon,
  UserGroupIcon,
  UserPlusIcon,
} from '@heroicons/vue/24/outline'
import { StarIcon as StarIconSolid, FolderIcon as FolderIconSolid } from '@heroicons/vue/24/solid'

const router = useRouter()
const uiStore = useUiStore()
const toast = useToast()
const { confirm } = useConfirmDialog()

// State
const projects = ref([])
const selectedProject = ref(null)
const loading = ref(true)
const showModal = ref(false)
const showLinkModal = ref(false)
const editingProject = ref(null)
const linkType = ref('document')
const linkableItems = ref([])
const loadingLinkable = ref(false)
const statusFilter = ref('')
const searchQuery = ref('')

// Linked items filter and sort
const linkedItemsFilter = ref('')
const linkedItemsSort = ref('date_desc')

// Members management
const showMembersModal = ref(false)
const projectShares = ref([])
const loadingShares = ref(false)
const newMemberEmail = ref('')
const newMemberPermission = ref('view')

// Form
const form = ref({
  name: '',
  description: '',
  color: '#6366f1',
  icon: 'folder',
})

// Colors
const colors = [
  '#6366f1', '#8B5CF6', '#EC4899', '#EF4444', '#F97316',
  '#EAB308', '#22C55E', '#14B8A6', '#06B6D4', '#3B82F6',
]

// Link types
const linkTypes = [
  { value: 'document', label: 'Dokument', icon: DocumentTextIcon },
  { value: 'list', label: 'Liste', icon: ListBulletIcon },
  { value: 'kanban_board', label: 'Kanban Board', icon: ViewColumnsIcon },
  { value: 'connection', label: 'Verbindung', icon: ServerIcon },
  { value: 'snippet', label: 'Snippet', icon: CodeBracketIcon },
]

// Status options
const statusOptions = [
  { value: 'active', label: 'Aktiv', icon: FolderIcon, color: 'text-green-400' },
  { value: 'completed', label: 'Abgeschlossen', icon: CheckCircleIcon, color: 'text-blue-400' },
  { value: 'archived', label: 'Archiviert', icon: ArchiveBoxIcon, color: 'text-gray-400' },
]

// Filtered projects
const filteredProjects = computed(() => {
  let result = projects.value

  if (statusFilter.value) {
    result = result.filter(p => p.status === statusFilter.value)
  }

  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    result = result.filter(p =>
      p.name.toLowerCase().includes(query) ||
      p.description?.toLowerCase().includes(query)
    )
  }

  return result
})

// Fetch projects
async function fetchProjects() {
  loading.value = true
  try {
    const response = await api.get('/api/v1/projects')
    projects.value = response.data.data.items || []
  } catch (error) {
    uiStore.showError('Fehler beim Laden der Projekte')
  } finally {
    loading.value = false
  }
}

// Fetch single project
async function fetchProject(projectId) {
  try {
    const response = await api.get(`/api/v1/projects/${projectId}`)
    selectedProject.value = response.data.data
  } catch (error) {
    uiStore.showError('Fehler beim Laden des Projekts')
    selectedProject.value = null
  }
}

// Open modal
function openModal(project = null) {
  editingProject.value = project
  if (project) {
    form.value = {
      name: project.name,
      description: project.description || '',
      color: project.color || '#6366f1',
      icon: project.icon || 'folder',
    }
  } else {
    form.value = { name: '', description: '', color: '#6366f1', icon: 'folder' }
  }
  showModal.value = true
}

// Save project
async function saveProject() {
  if (!form.value.name.trim()) {
    uiStore.showError('Name ist erforderlich')
    return
  }

  try {
    if (editingProject.value) {
      await api.put(`/api/v1/projects/${editingProject.value.id}`, form.value)
      uiStore.showSuccess('Projekt aktualisiert')
      if (selectedProject.value?.id === editingProject.value.id) {
        await fetchProject(selectedProject.value.id)
      }
    } else {
      const response = await api.post('/api/v1/projects', form.value)
      uiStore.showSuccess('Projekt erstellt')
      await fetchProject(response.data.data.id)
    }
    await fetchProjects()
    showModal.value = false
  } catch (error) {
    uiStore.showError(error.response?.data?.message || 'Fehler beim Speichern')
  }
}

// Delete project
async function deleteProject(project) {
  if (!await confirm({ message: `Projekt "${project.name}" wirklich löschen?`, type: 'danger', confirmText: 'Löschen' })) return

  try {
    await api.delete(`/api/v1/projects/${project.id}`)
    uiStore.showSuccess('Projekt gelöscht')
    if (selectedProject.value?.id === project.id) {
      selectedProject.value = null
    }
    await fetchProjects()
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
  }
}

// Toggle favorite
async function toggleFavorite(project) {
  try {
    await api.put(`/api/v1/projects/${project.id}`, {
      is_favorite: !project.is_favorite,
    })
    project.is_favorite = !project.is_favorite
    if (selectedProject.value?.id === project.id) {
      selectedProject.value.is_favorite = project.is_favorite
    }
  } catch (error) {
    uiStore.showError('Fehler beim Aktualisieren')
  }
}

// Update status
async function updateStatus(project, status) {
  try {
    await api.put(`/api/v1/projects/${project.id}`, { status })
    project.status = status
    if (selectedProject.value?.id === project.id) {
      selectedProject.value.status = status
    }
    uiStore.showSuccess('Status aktualisiert')
  } catch (error) {
    uiStore.showError('Fehler beim Aktualisieren')
  }
}

// Select project
function selectProject(project) {
  fetchProject(project.id)
}

// Back to list
function backToList() {
  selectedProject.value = null
}

// Open link modal
async function openLinkModal(type) {
  linkType.value = type
  loadingLinkable.value = true
  showLinkModal.value = true

  try {
    const response = await api.get(`/api/v1/projects/${selectedProject.value.id}/linkable/${type}`)
    linkableItems.value = response.data.data.items || []
  } catch (error) {
    uiStore.showError('Fehler beim Laden')
    linkableItems.value = []
  } finally {
    loadingLinkable.value = false
  }
}

// Add link
async function addLink(item) {
  try {
    await api.post(`/api/v1/projects/${selectedProject.value.id}/links`, {
      type: linkType.value,
      item_id: item.id,
    })
    uiStore.showSuccess('Element verknüpft')
    showLinkModal.value = false
    await fetchProject(selectedProject.value.id)
  } catch (error) {
    uiStore.showError(error.response?.data?.message || 'Fehler beim Verknüpfen')
  }
}

// Remove link
async function removeLink(link) {
  if (!await confirm({ message: 'Verknüpfung wirklich entfernen?', type: 'danger', confirmText: 'Löschen' })) return

  try {
    await api.delete(`/api/v1/projects/${selectedProject.value.id}/links/${link.link_id}`)
    uiStore.showSuccess('Verknüpfung entfernt')
    await fetchProject(selectedProject.value.id)
  } catch (error) {
    uiStore.showError('Fehler beim Entfernen')
  }
}

// Format time
function formatTime(seconds) {
  if (!seconds) return '0h'
  const hours = Math.floor(seconds / 3600)
  const minutes = Math.floor((seconds % 3600) / 60)
  return hours > 0 ? `${hours}h ${minutes}m` : `${minutes}m`
}

// Get link icon
function getLinkIcon(type) {
  const linkType = linkTypes.find(t => t.value === type)
  return linkType?.icon || LinkIcon
}

// Filtered and sorted linked items
const filteredLinkedItems = computed(() => {
  if (!selectedProject.value?.linked_items) return []

  let items = [...selectedProject.value.linked_items]

  // Filter by type
  if (linkedItemsFilter.value) {
    items = items.filter(item => item.type === linkedItemsFilter.value)
  }

  // Sort
  items.sort((a, b) => {
    switch (linkedItemsSort.value) {
      case 'name_asc':
        return (a.data.name || a.data.title || '').localeCompare(b.data.name || b.data.title || '')
      case 'name_desc':
        return (b.data.name || b.data.title || '').localeCompare(a.data.name || a.data.title || '')
      case 'type_asc':
        return a.type.localeCompare(b.type)
      case 'type_desc':
        return b.type.localeCompare(a.type)
      case 'date_asc':
        return new Date(a.linked_at) - new Date(b.linked_at)
      case 'date_desc':
      default:
        return new Date(b.linked_at) - new Date(a.linked_at)
    }
  })

  return items
})

// Get route for linked item
function getLinkedItemRoute(link) {
  switch (link.type) {
    case 'document':
      return { name: 'documents', query: { open: link.item_id } }
    case 'list':
      return { name: 'lists', query: { open: link.item_id } }
    case 'kanban_board':
      return { name: 'kanban', query: { open: link.item_id } }
    case 'connection':
      return { name: 'connections', query: { open: link.item_id } }
    case 'snippet':
      return { name: 'snippets', query: { open: link.item_id } }
    default:
      return null
  }
}

// Open linked item
function openLinkedItem(link) {
  const route = getLinkedItemRoute(link)
  if (route) {
    router.push(route)
  }
}

// Fetch project shares/members
async function fetchProjectShares() {
  if (!selectedProject.value) return
  loadingShares.value = true
  try {
    const response = await api.get(`/api/v1/projects/${selectedProject.value.id}/shares`)
    projectShares.value = response.data.data.shares || []
  } catch (error) {
    uiStore.showError('Fehler beim Laden der Mitglieder')
    projectShares.value = []
  } finally {
    loadingShares.value = false
  }
}

// Open members modal
async function openMembersModal() {
  showMembersModal.value = true
  newMemberEmail.value = ''
  newMemberPermission.value = 'view'
  await fetchProjectShares()
}

// Add project member
async function addMember() {
  if (!newMemberEmail.value.trim()) {
    uiStore.showError('E-Mail ist erforderlich')
    return
  }

  try {
    await api.post(`/api/v1/projects/${selectedProject.value.id}/shares`, {
      email: newMemberEmail.value.trim(),
      permission: newMemberPermission.value
    })
    uiStore.showSuccess('Mitglied hinzugefügt')
    newMemberEmail.value = ''
    await fetchProjectShares()
  } catch (error) {
    uiStore.showError(error.response?.data?.message || 'Fehler beim Hinzufügen')
  }
}

// Remove project member
async function removeMember(userId) {
  if (!await confirm({ message: 'Mitglied wirklich entfernen?', type: 'danger', confirmText: 'Löschen' })) return

  try {
    await api.delete(`/api/v1/projects/${selectedProject.value.id}/shares/${userId}`)
    uiStore.showSuccess('Mitglied entfernt')
    await fetchProjectShares()
  } catch (error) {
    uiStore.showError('Fehler beim Entfernen')
  }
}

// Update member permission
async function updateMemberPermission(userId, permission) {
  try {
    await api.post(`/api/v1/projects/${selectedProject.value.id}/shares`, {
      user_id: userId,
      permission
    })
    uiStore.showSuccess('Berechtigung aktualisiert')
    await fetchProjectShares()
  } catch (error) {
    uiStore.showError('Fehler beim Aktualisieren')
  }
}

onMounted(() => {
  fetchProjects()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-4">
        <button
          v-if="selectedProject"
          @click="backToList"
          class="p-2 text-gray-400 hover:text-white hover:bg-white/[0.04] rounded-lg transition-colors"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </button>
        <div>
          <h1 class="text-2xl font-bold text-white">
            {{ selectedProject ? selectedProject.name : 'Projekte' }}
          </h1>
          <p v-if="selectedProject?.description" class="text-gray-400 text-sm mt-1">
            {{ selectedProject.description }}
          </p>
        </div>
      </div>
      <div class="flex items-center gap-3">
        <template v-if="selectedProject">
          <button
            @click="toggleFavorite(selectedProject)"
            class="p-2 text-gray-400 hover:text-yellow-400 hover:bg-white/[0.04] rounded-lg transition-colors"
          >
            <component :is="selectedProject.is_favorite ? StarIconSolid : StarIcon" class="w-5 h-5" :class="{ 'text-yellow-400': selectedProject.is_favorite }" />
          </button>
          <button
            v-if="selectedProject.is_owner"
            @click="openMembersModal"
            class="px-4 py-2 bg-dark-700 text-white rounded-lg hover:bg-white/[0.04] transition-colors flex items-center gap-2"
          >
            <UserGroupIcon class="w-5 h-5" />
            <span>Mitglieder</span>
          </button>
          <button
            @click="openModal(selectedProject)"
            class="px-4 py-2 bg-dark-700 text-white rounded-lg hover:bg-white/[0.04] transition-colors flex items-center gap-2"
          >
            <PencilIcon class="w-5 h-5" />
            <span>Bearbeiten</span>
          </button>
        </template>
        <template v-else>
          <select
            v-model="statusFilter"
            class="bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-primary-500"
          >
            <option value="">Alle Status</option>
            <option v-for="status in statusOptions" :key="status.value" :value="status.value">
              {{ status.label }}
            </option>
          </select>
          <button
            @click="openModal()"
            class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-500 transition-colors flex items-center gap-2"
          >
            <PlusIcon class="w-5 h-5" />
            <span>Neues Projekt</span>
          </button>
        </template>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-20">
      <div class="w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full animate-spin"></div>
    </div>

    <!-- Projects Grid -->
    <div v-else-if="!selectedProject" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div
        v-for="project in filteredProjects"
        :key="project.id"
        @click="selectProject(project)"
        class="bg-dark-800 border border-dark-700 rounded-xl p-4 cursor-pointer hover:border-white/[0.08] transition-all group"
      >
        <div class="flex items-start justify-between mb-3">
          <div
            class="w-10 h-10 rounded-lg flex items-center justify-center"
            :style="{ backgroundColor: project.color || '#6366f1' }"
          >
            <FolderIconSolid class="w-6 h-6 text-white" />
          </div>
          <div class="flex items-center gap-1">
            <button
              @click.stop="toggleFavorite(project)"
              class="p-1.5 text-gray-400 hover:text-yellow-400 rounded opacity-0 group-hover:opacity-100 transition-all"
            >
              <component :is="project.is_favorite ? StarIconSolid : StarIcon" class="w-4 h-4" :class="{ 'text-yellow-400 opacity-100': project.is_favorite }" />
            </button>
            <button
              @click.stop="openModal(project)"
              class="p-1.5 text-gray-400 hover:text-white hover:bg-white/[0.04] rounded opacity-0 group-hover:opacity-100 transition-all"
            >
              <PencilIcon class="w-4 h-4" />
            </button>
            <button
              @click.stop="deleteProject(project)"
              class="p-1.5 text-gray-400 hover:text-red-400 hover:bg-white/[0.04] rounded opacity-0 group-hover:opacity-100 transition-all"
            >
              <TrashIcon class="w-4 h-4" />
            </button>
          </div>
        </div>
        <h3 class="text-white font-semibold mb-1">{{ project.name }}</h3>
        <p v-if="project.description" class="text-gray-400 text-sm line-clamp-2 mb-3">
          {{ project.description }}
        </p>
        <div class="flex items-center gap-4 text-sm text-gray-500">
          <span class="flex items-center gap-1">
            <LinkIcon class="w-4 h-4" />
            {{ project.link_count || 0 }}
          </span>
          <span class="flex items-center gap-1">
            <ClockIcon class="w-4 h-4" />
            {{ formatTime(project.total_time_seconds) }}
          </span>
          <span
            class="px-2 py-0.5 rounded text-xs"
            :class="{
              'bg-green-500/20 text-green-400': project.status === 'active',
              'bg-blue-500/20 text-blue-400': project.status === 'completed',
              'bg-gray-500/20 text-gray-400': project.status === 'archived',
            }"
          >
            {{ statusOptions.find(s => s.value === project.status)?.label }}
          </span>
        </div>
      </div>

      <!-- Empty state -->
      <div
        v-if="filteredProjects.length === 0"
        @click="openModal()"
        class="bg-dark-800 border-2 border-dashed border-dark-600 rounded-xl p-8 cursor-pointer hover:border-white/[0.08] transition-colors flex flex-col items-center justify-center text-center col-span-full"
      >
        <FolderIcon class="w-12 h-12 text-gray-500 mb-3" />
        <p class="text-gray-400">Kein Projekt vorhanden</p>
        <p class="text-primary-500 mt-1">Klicken um ein Projekt zu erstellen</p>
      </div>
    </div>

    <!-- Project Detail View -->
    <div v-else class="space-y-6">
      <!-- Stats -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-dark-800 border border-dark-700 rounded-xl p-4">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-blue-500/20 rounded-lg flex items-center justify-center">
              <LinkIcon class="w-5 h-5 text-blue-400" />
            </div>
            <div>
              <p class="text-2xl font-bold text-white">{{ selectedProject.linked_items?.length || 0 }}</p>
              <p class="text-sm text-gray-400">Verknüpfte Elemente</p>
            </div>
          </div>
        </div>
        <div class="bg-dark-800 border border-dark-700 rounded-xl p-4">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-green-500/20 rounded-lg flex items-center justify-center">
              <ClockIcon class="w-5 h-5 text-green-400" />
            </div>
            <div>
              <p class="text-2xl font-bold text-white">{{ formatTime(selectedProject.time_stats?.total_seconds) }}</p>
              <p class="text-sm text-gray-400">Erfasste Zeit</p>
            </div>
          </div>
        </div>
        <div class="bg-dark-800 border border-dark-700 rounded-xl p-4">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-purple-500/20 rounded-lg flex items-center justify-center">
              <component :is="statusOptions.find(s => s.value === selectedProject.status)?.icon || FolderIcon" class="w-5 h-5 text-purple-400" />
            </div>
            <div>
              <p class="text-lg font-bold text-white">{{ statusOptions.find(s => s.value === selectedProject.status)?.label }}</p>
              <div class="flex gap-1 mt-1">
                <button
                  v-for="status in statusOptions"
                  :key="status.value"
                  @click="updateStatus(selectedProject, status.value)"
                  class="px-2 py-0.5 text-xs rounded transition-colors"
                  :class="selectedProject.status === status.value
                    ? 'bg-primary-600 text-white'
                    : 'bg-dark-700 text-gray-400 hover:text-white'"
                >
                  {{ status.label }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Linked Items -->
      <div class="bg-dark-800 border border-dark-700 rounded-xl">
        <div class="flex items-center justify-between p-4 border-b border-dark-700">
          <h2 class="text-lg font-semibold text-white">Verknüpfte Elemente</h2>
          <div class="flex items-center gap-3">
            <!-- Filter -->
            <select
              v-model="linkedItemsFilter"
              class="bg-dark-700 border border-dark-600 rounded-lg px-3 py-1.5 text-sm text-white focus:outline-none focus:border-primary-500"
            >
              <option value="">Alle Typen</option>
              <option v-for="type in linkTypes" :key="type.value" :value="type.value">
                {{ type.label }}
              </option>
            </select>
            <!-- Sort -->
            <select
              v-model="linkedItemsSort"
              class="bg-dark-700 border border-dark-600 rounded-lg px-3 py-1.5 text-sm text-white focus:outline-none focus:border-primary-500"
            >
              <option value="date_desc">Neueste zuerst</option>
              <option value="date_asc">Älteste zuerst</option>
              <option value="name_asc">Name A-Z</option>
              <option value="name_desc">Name Z-A</option>
              <option value="type_asc">Typ A-Z</option>
              <option value="type_desc">Typ Z-A</option>
            </select>
            <!-- Add buttons -->
            <div class="flex gap-1 border-l border-dark-600 pl-3">
              <button
                v-for="type in linkTypes"
                :key="type.value"
                @click="openLinkModal(type.value)"
                class="p-2 text-gray-400 hover:text-white hover:bg-white/[0.04] rounded-lg transition-colors"
                :title="type.label + ' verknüpfen'"
              >
                <component :is="type.icon" class="w-5 h-5" />
              </button>
            </div>
          </div>
        </div>
        <div class="p-4">
          <div v-if="filteredLinkedItems.length" class="space-y-2">
            <div
              v-for="link in filteredLinkedItems"
              :key="link.link_id"
              @click="openLinkedItem(link)"
              class="flex items-center justify-between p-3 bg-dark-700 rounded-lg group cursor-pointer hover:bg-white/[0.04] transition-colors"
            >
              <div class="flex items-center gap-3">
                <component :is="getLinkIcon(link.type)" class="w-5 h-5 text-gray-400" />
                <div>
                  <p class="text-white group-hover:text-primary-400 transition-colors">{{ link.data.name || link.data.title }}</p>
                  <p class="text-xs text-gray-500">{{ linkTypes.find(t => t.value === link.type)?.label }}</p>
                </div>
              </div>
              <div class="flex items-center gap-2">
                <ArrowTopRightOnSquareIcon class="w-4 h-4 text-gray-500 opacity-0 group-hover:opacity-100 transition-opacity" />
                <button
                  @click.stop="removeLink(link)"
                  class="p-1.5 text-gray-400 hover:text-red-400 rounded opacity-0 group-hover:opacity-100 transition-all"
                >
                  <TrashIcon class="w-4 h-4" />
                </button>
              </div>
            </div>
          </div>
          <div v-else-if="selectedProject.linked_items?.length && linkedItemsFilter" class="text-center py-8">
            <FunnelIcon class="w-10 h-10 text-gray-600 mx-auto mb-2" />
            <p class="text-gray-400">Keine Elemente für diesen Filter</p>
            <button @click="linkedItemsFilter = ''" class="text-sm text-primary-500 hover:text-primary-400 mt-1">
              Filter zurücksetzen
            </button>
          </div>
          <div v-else class="text-center py-8">
            <LinkIcon class="w-10 h-10 text-gray-600 mx-auto mb-2" />
            <p class="text-gray-400">Noch keine Elemente verknüpft</p>
            <p class="text-sm text-gray-500">Verwende die Buttons oben um Dokumente, Listen etc. zu verknüpfen</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Project Modal -->
    <Teleport to="body">
      <div
        v-if="showModal"
        class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
        
      >
        <div class="bg-dark-800 border border-dark-700 rounded-xl w-full max-w-md">
          <div class="flex items-center justify-between p-4 border-b border-dark-700">
            <h2 class="text-lg font-semibold text-white">
              {{ editingProject ? 'Projekt bearbeiten' : 'Neues Projekt' }}
            </h2>
            <button @click="showModal = false" class="p-1 text-gray-400 hover:text-white rounded">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <div class="p-4 space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Name *</label>
              <input
                v-model="form.name"
                type="text"
                class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white placeholder-gray-500 focus:outline-none focus:border-primary-500"
                placeholder="Projektname"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Beschreibung</label>
              <textarea
                v-model="form.description"
                rows="3"
                class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white placeholder-gray-500 focus:outline-none focus:border-primary-500 resize-none"
                placeholder="Optionale Beschreibung..."
              ></textarea>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Farbe</label>
              <div class="flex flex-wrap gap-2">
                <button
                  v-for="color in colors"
                  :key="color"
                  @click="form.color = color"
                  class="w-8 h-8 rounded-lg transition-transform hover:scale-110"
                  :class="{ 'ring-2 ring-white ring-offset-2 ring-offset-dark-800': form.color === color }"
                  :style="{ backgroundColor: color }"
                ></button>
              </div>
            </div>
          </div>

          <div class="flex items-center justify-end gap-3 p-4 border-t border-dark-700">
            <button
              @click="showModal = false"
              class="px-4 py-2 text-gray-400 hover:text-white transition-colors"
            >
              Abbrechen
            </button>
            <button
              @click="saveProject"
              class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-500 transition-colors"
            >
              {{ editingProject ? 'Speichern' : 'Erstellen' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Link Modal -->
    <Teleport to="body">
      <div
        v-if="showLinkModal"
        class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
        
      >
        <div class="bg-dark-800 border border-dark-700 rounded-xl w-full max-w-md max-h-[80vh] overflow-hidden flex flex-col">
          <div class="flex items-center justify-between p-4 border-b border-dark-700">
            <h2 class="text-lg font-semibold text-white">
              {{ linkTypes.find(t => t.value === linkType)?.label }} verknüpfen
            </h2>
            <button @click="showLinkModal = false" class="p-1 text-gray-400 hover:text-white rounded">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <div class="p-4 overflow-y-auto flex-1">
            <div v-if="loadingLinkable" class="flex items-center justify-center py-8">
              <div class="w-6 h-6 border-2 border-primary-600 border-t-transparent rounded-full animate-spin"></div>
            </div>
            <div v-else-if="linkableItems.length === 0" class="text-center py-8">
              <p class="text-gray-400">Keine verfügbaren Elemente</p>
            </div>
            <div v-else class="space-y-2">
              <button
                v-for="item in linkableItems"
                :key="item.id"
                @click="addLink(item)"
                class="w-full flex items-center gap-3 p-3 bg-dark-700 rounded-lg hover:bg-white/[0.04] transition-colors text-left"
              >
                <component :is="getLinkIcon(linkType)" class="w-5 h-5 text-gray-400" />
                <div>
                  <p class="text-white">{{ item.name || item.title }}</p>
                  <p v-if="item.type || item.language" class="text-xs text-gray-500">{{ item.type || item.language }}</p>
                </div>
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Members Modal -->
    <Teleport to="body">
      <div
        v-if="showMembersModal"
        class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
        
      >
        <div class="bg-dark-800 border border-dark-700 rounded-xl w-full max-w-lg max-h-[80vh] overflow-hidden flex flex-col">
          <div class="flex items-center justify-between p-4 border-b border-dark-700">
            <h2 class="text-lg font-semibold text-white">
              Projektmitglieder
            </h2>
            <button @click="showMembersModal = false" class="p-1 text-gray-400 hover:text-white rounded">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <div class="p-4 space-y-4 overflow-y-auto flex-1">
            <!-- Add member form -->
            <div class="bg-dark-700 rounded-lg p-4">
              <h3 class="text-sm font-medium text-gray-300 mb-3">Mitglied hinzufügen</h3>
              <div class="flex gap-2">
                <input
                  v-model="newMemberEmail"
                  type="email"
                  placeholder="E-Mail Adresse"
                  class="flex-1 bg-dark-600 border border-dark-500 rounded-lg px-3 py-2 text-white placeholder-gray-500 focus:outline-none focus:border-primary-500"
                  @keyup.enter="addMember"
                />
                <select
                  v-model="newMemberPermission"
                  class="bg-dark-600 border border-dark-500 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-primary-500"
                >
                  <option value="view">Lesen</option>
                  <option value="edit">Bearbeiten</option>
                </select>
                <button
                  @click="addMember"
                  class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-500 transition-colors"
                >
                  <UserPlusIcon class="w-5 h-5" />
                </button>
              </div>
              <p class="text-xs text-gray-500 mt-2">
                Eingeschränkte Benutzer sehen nur Inhalte in Projekten, denen sie zugewiesen sind.
              </p>
            </div>

            <!-- Members list -->
            <div>
              <h3 class="text-sm font-medium text-gray-300 mb-3">Aktuelle Mitglieder</h3>
              <div v-if="loadingShares" class="flex items-center justify-center py-8">
                <div class="w-6 h-6 border-2 border-primary-600 border-t-transparent rounded-full animate-spin"></div>
              </div>
              <div v-else-if="projectShares.length === 0" class="text-center py-8 text-gray-500">
                Noch keine Mitglieder hinzugefügt
              </div>
              <div v-else class="space-y-2">
                <div
                  v-for="share in projectShares"
                  :key="share.user_id"
                  class="flex items-center justify-between p-3 bg-dark-700 rounded-lg"
                >
                  <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-primary-600 flex items-center justify-center">
                      <span class="text-xs font-semibold text-white">
                        {{ share.username?.[0]?.toUpperCase() || 'U' }}
                      </span>
                    </div>
                    <div>
                      <p class="text-white text-sm">{{ share.username }}</p>
                      <p class="text-xs text-gray-500">{{ share.email }}</p>
                    </div>
                  </div>
                  <div class="flex items-center gap-2">
                    <select
                      :value="share.permission"
                      @change="updateMemberPermission(share.user_id, $event.target.value)"
                      class="bg-dark-600 border border-dark-500 rounded px-2 py-1 text-sm text-white focus:outline-none focus:border-primary-500"
                    >
                      <option value="view">Lesen</option>
                      <option value="edit">Bearbeiten</option>
                    </select>
                    <button
                      @click="removeMember(share.user_id)"
                      class="p-1.5 text-gray-400 hover:text-red-400 rounded transition-colors"
                    >
                      <TrashIcon class="w-4 h-4" />
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
