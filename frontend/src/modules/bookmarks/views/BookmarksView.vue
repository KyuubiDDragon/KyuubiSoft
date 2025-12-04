<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/core/api/axios'
import { useUiStore } from '@/stores/ui'
import {
  PlusIcon,
  BookmarkIcon,
  TrashIcon,
  PencilIcon,
  StarIcon,
  MagnifyingGlassIcon,
  ArrowTopRightOnSquareIcon,
  TagIcon,
  XMarkIcon,
  FolderIcon,
  FolderOpenIcon,
  ChevronDownIcon,
  ChevronRightIcon,
  EllipsisVerticalIcon,
} from '@heroicons/vue/24/outline'
import { StarIcon as StarIconSolid } from '@heroicons/vue/24/solid'

const uiStore = useUiStore()

// State
const groups = ref([])
const ungroupedBookmarks = ref([])
const tags = ref([])
const isLoading = ref(true)
const showModal = ref(false)
const showTagModal = ref(false)
const showGroupModal = ref(false)
const editingBookmark = ref(null)
const editingGroup = ref(null)
const searchQuery = ref('')
const selectedTagId = ref('')
const expandedGroups = ref({})
const showGroupMenu = ref(null)

// Form
const form = ref({
  title: '',
  url: '',
  description: '',
  color: '#6366f1',
  is_favorite: false,
  tag_ids: [],
  group_id: null,
})

const tagForm = ref({
  name: '',
  color: '#6366f1',
})

const groupForm = ref({
  name: '',
  color: '#6366f1',
})

const colors = [
  '#6366f1', '#8B5CF6', '#EC4899', '#EF4444', '#F97316',
  '#EAB308', '#22C55E', '#14B8A6', '#06B6D4', '#3B82F6',
]

// Filtered bookmarks
const filteredUngrouped = computed(() => {
  let result = ungroupedBookmarks.value

  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    result = result.filter(b =>
      b.title.toLowerCase().includes(query) ||
      b.url.toLowerCase().includes(query) ||
      b.description?.toLowerCase().includes(query)
    )
  }

  if (selectedTagId.value) {
    result = result.filter(b =>
      b.tags?.some(t => t.id === selectedTagId.value)
    )
  }

  return result
})

const filteredGroups = computed(() => {
  return groups.value.map(group => {
    let bookmarks = group.bookmarks || []

    if (searchQuery.value) {
      const query = searchQuery.value.toLowerCase()
      bookmarks = bookmarks.filter(b =>
        b.title.toLowerCase().includes(query) ||
        b.url.toLowerCase().includes(query) ||
        b.description?.toLowerCase().includes(query)
      )
    }

    if (selectedTagId.value) {
      bookmarks = bookmarks.filter(b =>
        b.tags?.some(t => t.id === selectedTagId.value)
      )
    }

    return { ...group, bookmarks, filteredCount: bookmarks.length }
  }).filter(g => g.filteredCount > 0 || (!searchQuery.value && !selectedTagId.value))
})

const allGroups = computed(() => {
  return [{ id: null, name: 'Ohne Gruppe' }, ...groups.value]
})

// API Calls
onMounted(async () => {
  await Promise.all([loadBookmarks(), loadTags(), loadGroups()])
})

async function loadBookmarks() {
  isLoading.value = true
  try {
    const response = await api.get('/api/v1/bookmarks', {
      params: { grouped: 'true' }
    })
    const data = response.data.data
    groups.value = data.groups || []
    ungroupedBookmarks.value = data.ungrouped || []

    // Initialize expanded state for new groups
    groups.value.forEach(g => {
      if (!(g.id in expandedGroups.value)) {
        expandedGroups.value[g.id] = !g.is_collapsed
      }
    })
  } catch (error) {
    uiStore.showError('Fehler beim Laden der Lesezeichen')
  } finally {
    isLoading.value = false
  }
}

async function loadTags() {
  try {
    const response = await api.get('/api/v1/bookmarks/tags')
    tags.value = response.data.data?.items || []
  } catch (error) {
    console.error('Failed to load tags:', error)
  }
}

async function loadGroups() {
  try {
    const response = await api.get('/api/v1/bookmarks/groups')
    // Groups are already loaded with bookmarks, this is for the dropdown
  } catch (error) {
    console.error('Failed to load groups:', error)
  }
}

async function saveBookmark() {
  if (!form.value.url.trim()) {
    uiStore.showError('URL ist erforderlich')
    return
  }

  try {
    if (editingBookmark.value) {
      await api.put(`/api/v1/bookmarks/${editingBookmark.value.id}`, form.value)
      uiStore.showSuccess('Lesezeichen aktualisiert')
    } else {
      await api.post('/api/v1/bookmarks', form.value)
      uiStore.showSuccess('Lesezeichen erstellt')
    }
    await loadBookmarks()
    showModal.value = false
  } catch (error) {
    uiStore.showError('Fehler beim Speichern')
  }
}

async function deleteBookmark(bookmark) {
  if (!confirm(`"${bookmark.title}" wirklich löschen?`)) return

  try {
    await api.delete(`/api/v1/bookmarks/${bookmark.id}`)
    await loadBookmarks()
    uiStore.showSuccess('Lesezeichen gelöscht')
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
  }
}

async function toggleFavorite(bookmark) {
  try {
    await api.put(`/api/v1/bookmarks/${bookmark.id}`, {
      is_favorite: !bookmark.is_favorite,
    })
    bookmark.is_favorite = !bookmark.is_favorite
  } catch (error) {
    uiStore.showError('Fehler beim Aktualisieren')
  }
}

async function openBookmark(bookmark) {
  try {
    await api.post(`/api/v1/bookmarks/${bookmark.id}/click`)
    bookmark.click_count = (bookmark.click_count || 0) + 1
  } catch (error) {
    // Ignore
  }
  window.open(bookmark.url, '_blank')
}

async function moveBookmarkToGroup(bookmark, groupId) {
  try {
    await api.put(`/api/v1/bookmarks/${bookmark.id}/move`, {
      group_id: groupId
    })
    await loadBookmarks()
    uiStore.showSuccess('Lesezeichen verschoben')
  } catch (error) {
    uiStore.showError('Fehler beim Verschieben')
  }
}

// Groups
async function saveGroup() {
  if (!groupForm.value.name.trim()) return

  try {
    if (editingGroup.value) {
      await api.put(`/api/v1/bookmarks/groups/${editingGroup.value.id}`, groupForm.value)
      uiStore.showSuccess('Gruppe aktualisiert')
    } else {
      await api.post('/api/v1/bookmarks/groups', groupForm.value)
      uiStore.showSuccess('Gruppe erstellt')
    }
    await loadBookmarks()
    showGroupModal.value = false
    editingGroup.value = null
    groupForm.value = { name: '', color: '#6366f1' }
  } catch (error) {
    uiStore.showError('Fehler beim Speichern')
  }
}

async function deleteGroup(group) {
  const choice = confirm(`Gruppe "${group.name}" wirklich löschen?\n\nOK = Lesezeichen behalten\nAbbrechen = Abbrechen`)
  if (!choice) return

  try {
    await api.delete(`/api/v1/bookmarks/groups/${group.id}`)
    await loadBookmarks()
    uiStore.showSuccess('Gruppe gelöscht')
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
  }
}

function editGroup(group) {
  editingGroup.value = group
  groupForm.value = {
    name: group.name,
    color: group.color || '#6366f1',
  }
  showGroupModal.value = true
  showGroupMenu.value = null
}

async function toggleGroupCollapse(group) {
  expandedGroups.value[group.id] = !expandedGroups.value[group.id]
  try {
    await api.put(`/api/v1/bookmarks/groups/${group.id}`, {
      is_collapsed: !expandedGroups.value[group.id]
    })
  } catch (error) {
    // Ignore, local state is updated
  }
}

// Tags
async function saveTag() {
  if (!tagForm.value.name.trim()) return

  try {
    await api.post('/api/v1/bookmarks/tags', tagForm.value)
    await loadTags()
    tagForm.value = { name: '', color: '#6366f1' }
    uiStore.showSuccess('Tag erstellt')
  } catch (error) {
    uiStore.showError('Fehler beim Erstellen')
  }
}

async function deleteTag(tag) {
  if (!confirm(`Tag "${tag.name}" wirklich löschen?`)) return

  try {
    await api.delete(`/api/v1/bookmarks/tags/${tag.id}`)
    await loadTags()
    if (selectedTagId.value === tag.id) selectedTagId.value = ''
    uiStore.showSuccess('Tag gelöscht')
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
  }
}

// Modal
function openCreateModal(groupId = null) {
  editingBookmark.value = null
  form.value = {
    title: '',
    url: '',
    description: '',
    color: '#6366f1',
    is_favorite: false,
    tag_ids: [],
    group_id: groupId,
  }
  showModal.value = true
}

function openEditModal(bookmark) {
  editingBookmark.value = bookmark
  form.value = {
    title: bookmark.title,
    url: bookmark.url,
    description: bookmark.description || '',
    color: bookmark.color || '#6366f1',
    is_favorite: bookmark.is_favorite,
    tag_ids: bookmark.tags?.map(t => t.id) || [],
    group_id: bookmark.group_id || null,
  }
  showModal.value = true
}

function openCreateGroupModal() {
  editingGroup.value = null
  groupForm.value = { name: '', color: '#6366f1' }
  showGroupModal.value = true
}

function getDomain(url) {
  try {
    return new URL(url).hostname
  } catch {
    return url
  }
}

function isGroupExpanded(groupId) {
  return expandedGroups.value[groupId] ?? true
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-white">Lesezeichen</h1>
        <p class="text-gray-400 mt-1">Speichere und organisiere deine Links</p>
      </div>
      <div class="flex gap-2">
        <button @click="showTagModal = true" class="btn-secondary">
          <TagIcon class="w-5 h-5 mr-2" />
          Tags
        </button>
        <button @click="openCreateGroupModal" class="btn-secondary">
          <FolderIcon class="w-5 h-5 mr-2" />
          Gruppe
        </button>
        <button @click="openCreateModal()" class="btn-primary">
          <PlusIcon class="w-5 h-5 mr-2" />
          Neues Lesezeichen
        </button>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-col sm:flex-row gap-4">
      <div class="relative flex-1">
        <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Suchen..."
          class="input pl-10 w-full"
        />
      </div>
      <select v-model="selectedTagId" class="input w-full sm:w-48">
        <option value="">Alle Tags</option>
        <option v-for="tag in tags" :key="tag.id" :value="tag.id">
          {{ tag.name }} ({{ tag.bookmark_count || 0 }})
        </option>
      </select>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="flex justify-center py-12">
      <div class="w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full animate-spin"></div>
    </div>

    <!-- Empty state -->
    <div v-else-if="filteredGroups.length === 0 && filteredUngrouped.length === 0" class="card p-12 text-center">
      <BookmarkIcon class="w-16 h-16 mx-auto text-gray-600 mb-4" />
      <h3 class="text-lg font-medium text-white mb-2">Keine Lesezeichen</h3>
      <p class="text-gray-400 mb-6">Füge dein erstes Lesezeichen hinzu</p>
      <button @click="openCreateModal()" class="btn-primary">
        <PlusIcon class="w-5 h-5 mr-2" />
        Lesezeichen hinzufügen
      </button>
    </div>

    <!-- Content -->
    <div v-else class="space-y-6">
      <!-- Groups -->
      <div v-for="group in filteredGroups" :key="group.id" class="card overflow-hidden">
        <!-- Group Header -->
        <div
          class="flex items-center justify-between p-4 bg-dark-700/50 cursor-pointer hover:bg-dark-700 transition-colors"
          @click="toggleGroupCollapse(group)"
        >
          <div class="flex items-center gap-3">
            <div
              class="w-8 h-8 rounded-lg flex items-center justify-center"
              :style="{ backgroundColor: group.color + '20' }"
            >
              <FolderOpenIcon v-if="isGroupExpanded(group.id)" class="w-5 h-5" :style="{ color: group.color }" />
              <FolderIcon v-else class="w-5 h-5" :style="{ color: group.color }" />
            </div>
            <div>
              <h3 class="font-medium text-white">{{ group.name }}</h3>
              <p class="text-sm text-gray-500">{{ group.filteredCount || group.bookmark_count }} Lesezeichen</p>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <button
              @click.stop="openCreateModal(group.id)"
              class="p-2 text-gray-400 hover:text-white hover:bg-dark-600 rounded-lg transition-colors"
              title="Lesezeichen hinzufügen"
            >
              <PlusIcon class="w-5 h-5" />
            </button>
            <div class="relative">
              <button
                @click.stop="showGroupMenu = showGroupMenu === group.id ? null : group.id"
                class="p-2 text-gray-400 hover:text-white hover:bg-dark-600 rounded-lg transition-colors"
              >
                <EllipsisVerticalIcon class="w-5 h-5" />
              </button>
              <!-- Group Menu -->
              <div
                v-if="showGroupMenu === group.id"
                class="absolute right-0 top-full mt-1 w-40 bg-dark-700 border border-dark-600 rounded-lg shadow-xl z-10"
              >
                <button
                  @click.stop="editGroup(group)"
                  class="w-full px-4 py-2 text-left text-sm text-gray-300 hover:bg-dark-600 flex items-center gap-2"
                >
                  <PencilIcon class="w-4 h-4" />
                  Bearbeiten
                </button>
                <button
                  @click.stop="deleteGroup(group); showGroupMenu = null"
                  class="w-full px-4 py-2 text-left text-sm text-red-400 hover:bg-dark-600 flex items-center gap-2"
                >
                  <TrashIcon class="w-4 h-4" />
                  Löschen
                </button>
              </div>
            </div>
            <ChevronDownIcon
              class="w-5 h-5 text-gray-400 transition-transform"
              :class="{ '-rotate-90': !isGroupExpanded(group.id) }"
            />
          </div>
        </div>

        <!-- Group Bookmarks -->
        <div v-show="isGroupExpanded(group.id)" class="p-4">
          <div v-if="group.bookmarks.length === 0" class="text-center py-6 text-gray-500">
            Keine Lesezeichen in dieser Gruppe
          </div>
          <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <div
              v-for="bookmark in group.bookmarks"
              :key="bookmark.id"
              class="card-hover p-4 group cursor-pointer bg-dark-800"
              @click="openBookmark(bookmark)"
            >
              <div class="flex items-start gap-3">
                <img
                  :src="bookmark.favicon"
                  :alt="bookmark.title"
                  class="w-8 h-8 rounded"
                  @error="$event.target.src = 'https://www.google.com/s2/favicons?domain=example.com&sz=64'"
                />
                <div class="flex-1 min-w-0">
                  <h3 class="font-medium text-white truncate group-hover:text-primary-400 transition-colors">
                    {{ bookmark.title }}
                  </h3>
                  <p class="text-sm text-gray-500 truncate">{{ getDomain(bookmark.url) }}</p>
                </div>
                <button
                  @click.stop="toggleFavorite(bookmark)"
                  class="p-1 text-gray-400 hover:text-yellow-400 rounded transition-colors"
                >
                  <StarIconSolid v-if="bookmark.is_favorite" class="w-5 h-5 text-yellow-400" />
                  <StarIcon v-else class="w-5 h-5" />
                </button>
              </div>

              <p v-if="bookmark.description" class="text-sm text-gray-400 mt-2 line-clamp-2">
                {{ bookmark.description }}
              </p>

              <!-- Tags -->
              <div v-if="bookmark.tags?.length" class="flex flex-wrap gap-1 mt-3">
                <span
                  v-for="tag in bookmark.tags"
                  :key="tag.id"
                  class="px-2 py-0.5 text-xs rounded-full bg-dark-600 text-gray-300"
                >
                  {{ tag.name }}
                </span>
              </div>

              <!-- Actions -->
              <div class="flex items-center justify-between mt-3 pt-3 border-t border-dark-700">
                <span class="text-xs text-gray-500">{{ bookmark.click_count || 0 }} Klicks</span>
                <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                  <button
                    @click.stop="openEditModal(bookmark)"
                    class="p-1.5 text-gray-400 hover:text-white hover:bg-dark-600 rounded"
                  >
                    <PencilIcon class="w-4 h-4" />
                  </button>
                  <button
                    @click.stop="deleteBookmark(bookmark)"
                    class="p-1.5 text-gray-400 hover:text-red-400 hover:bg-dark-600 rounded"
                  >
                    <TrashIcon class="w-4 h-4" />
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Ungrouped Bookmarks -->
      <div v-if="filteredUngrouped.length > 0">
        <h3 class="text-lg font-medium text-white mb-4">Ohne Gruppe</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
          <div
            v-for="bookmark in filteredUngrouped"
            :key="bookmark.id"
            class="card-hover p-4 group cursor-pointer"
            @click="openBookmark(bookmark)"
          >
            <div class="flex items-start gap-3">
              <img
                :src="bookmark.favicon"
                :alt="bookmark.title"
                class="w-8 h-8 rounded"
                @error="$event.target.src = 'https://www.google.com/s2/favicons?domain=example.com&sz=64'"
              />
              <div class="flex-1 min-w-0">
                <h3 class="font-medium text-white truncate group-hover:text-primary-400 transition-colors">
                  {{ bookmark.title }}
                </h3>
                <p class="text-sm text-gray-500 truncate">{{ getDomain(bookmark.url) }}</p>
              </div>
              <button
                @click.stop="toggleFavorite(bookmark)"
                class="p-1 text-gray-400 hover:text-yellow-400 rounded transition-colors"
              >
                <StarIconSolid v-if="bookmark.is_favorite" class="w-5 h-5 text-yellow-400" />
                <StarIcon v-else class="w-5 h-5" />
              </button>
            </div>

            <p v-if="bookmark.description" class="text-sm text-gray-400 mt-2 line-clamp-2">
              {{ bookmark.description }}
            </p>

            <!-- Tags -->
            <div v-if="bookmark.tags?.length" class="flex flex-wrap gap-1 mt-3">
              <span
                v-for="tag in bookmark.tags"
                :key="tag.id"
                class="px-2 py-0.5 text-xs rounded-full bg-dark-600 text-gray-300"
              >
                {{ tag.name }}
              </span>
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-between mt-3 pt-3 border-t border-dark-700">
              <span class="text-xs text-gray-500">{{ bookmark.click_count || 0 }} Klicks</span>
              <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                <button
                  @click.stop="openEditModal(bookmark)"
                  class="p-1.5 text-gray-400 hover:text-white hover:bg-dark-600 rounded"
                >
                  <PencilIcon class="w-4 h-4" />
                </button>
                <button
                  @click.stop="deleteBookmark(bookmark)"
                  class="p-1.5 text-gray-400 hover:text-red-400 hover:bg-dark-600 rounded"
                >
                  <TrashIcon class="w-4 h-4" />
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <Teleport to="body">
      <div
        v-if="showModal"
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
        
      >
        <div class="bg-dark-800 rounded-xl w-full max-w-md border border-dark-700">
          <div class="p-4 border-b border-dark-700 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-white">
              {{ editingBookmark ? 'Lesezeichen bearbeiten' : 'Neues Lesezeichen' }}
            </h2>
            <button @click="showModal = false" class="text-gray-400 hover:text-white">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <form @submit.prevent="saveBookmark" class="p-4 space-y-4">
            <div>
              <label class="label">URL *</label>
              <input v-model="form.url" type="url" class="input" placeholder="https://..." required />
            </div>

            <div>
              <label class="label">Titel</label>
              <input v-model="form.title" type="text" class="input" placeholder="Optional - wird aus URL extrahiert" />
            </div>

            <div>
              <label class="label">Beschreibung</label>
              <textarea v-model="form.description" class="input" rows="2" placeholder="Optional"></textarea>
            </div>

            <div>
              <label class="label">Gruppe</label>
              <select v-model="form.group_id" class="input">
                <option :value="null">Keine Gruppe</option>
                <option v-for="group in groups" :key="group.id" :value="group.id">
                  {{ group.name }}
                </option>
              </select>
            </div>

            <div>
              <label class="label">Tags</label>
              <div class="flex flex-wrap gap-2">
                <button
                  v-for="tag in tags"
                  :key="tag.id"
                  type="button"
                  @click="form.tag_ids.includes(tag.id)
                    ? form.tag_ids = form.tag_ids.filter(id => id !== tag.id)
                    : form.tag_ids.push(tag.id)"
                  class="px-3 py-1 rounded-full text-sm transition-colors"
                  :class="form.tag_ids.includes(tag.id)
                    ? 'bg-primary-600 text-white'
                    : 'bg-dark-600 text-gray-300 hover:bg-dark-500'"
                >
                  {{ tag.name }}
                </button>
              </div>
            </div>

            <div>
              <label class="flex items-center gap-2 cursor-pointer">
                <input v-model="form.is_favorite" type="checkbox" class="checkbox" />
                <span class="text-gray-300">Als Favorit markieren</span>
              </label>
            </div>

            <div class="flex gap-3 pt-4">
              <button type="button" @click="showModal = false" class="btn-secondary flex-1">
                Abbrechen
              </button>
              <button type="submit" class="btn-primary flex-1">
                {{ editingBookmark ? 'Speichern' : 'Hinzufügen' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- Tag Management Modal -->
    <Teleport to="body">
      <div
        v-if="showTagModal"
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
        
      >
        <div class="bg-dark-800 rounded-xl w-full max-w-md border border-dark-700">
          <div class="p-4 border-b border-dark-700 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-white">Tags verwalten</h2>
            <button @click="showTagModal = false" class="text-gray-400 hover:text-white">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <div class="p-4">
            <form @submit.prevent="saveTag" class="flex gap-2 mb-4">
              <input
                v-model="tagForm.name"
                type="text"
                class="input flex-1"
                placeholder="Neuer Tag..."
                required
              />
              <button type="submit" class="btn-primary">
                <PlusIcon class="w-5 h-5" />
              </button>
            </form>

            <div class="space-y-2 max-h-64 overflow-y-auto">
              <div
                v-for="tag in tags"
                :key="tag.id"
                class="flex items-center justify-between p-3 bg-dark-700 rounded-lg"
              >
                <div class="flex items-center gap-2">
                  <span class="text-white">{{ tag.name }}</span>
                  <span class="text-xs text-gray-500">({{ tag.bookmark_count || 0 }})</span>
                </div>
                <button
                  @click="deleteTag(tag)"
                  class="p-1 text-red-400 hover:text-red-300 rounded"
                >
                  <TrashIcon class="w-4 h-4" />
                </button>
              </div>
              <p v-if="tags.length === 0" class="text-center text-gray-500 py-4">
                Noch keine Tags erstellt
              </p>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Group Management Modal -->
    <Teleport to="body">
      <div
        v-if="showGroupModal"
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
        
      >
        <div class="bg-dark-800 rounded-xl w-full max-w-md border border-dark-700">
          <div class="p-4 border-b border-dark-700 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-white">
              {{ editingGroup ? 'Gruppe bearbeiten' : 'Neue Gruppe' }}
            </h2>
            <button @click="showGroupModal = false; editingGroup = null" class="text-gray-400 hover:text-white">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <form @submit.prevent="saveGroup" class="p-4 space-y-4">
            <div>
              <label class="label">Name *</label>
              <input
                v-model="groupForm.name"
                type="text"
                class="input"
                placeholder="Gruppenname..."
                required
              />
            </div>

            <div>
              <label class="label">Farbe</label>
              <div class="flex flex-wrap gap-2">
                <button
                  v-for="color in colors"
                  :key="color"
                  type="button"
                  @click="groupForm.color = color"
                  class="w-8 h-8 rounded-lg transition-transform hover:scale-110"
                  :class="{ 'ring-2 ring-white ring-offset-2 ring-offset-dark-800': groupForm.color === color }"
                  :style="{ backgroundColor: color }"
                ></button>
              </div>
            </div>

            <div class="flex gap-3 pt-4">
              <button type="button" @click="showGroupModal = false; editingGroup = null" class="btn-secondary flex-1">
                Abbrechen
              </button>
              <button type="submit" class="btn-primary flex-1">
                {{ editingGroup ? 'Speichern' : 'Erstellen' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </div>
</template>
