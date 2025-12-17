<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue'
import { useWikiStore } from '@/stores/wiki'
import { marked } from 'marked'
import DOMPurify from 'dompurify'
import { useToast } from '@/composables/useToast'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
import {
  BookOpenIcon,
  PlusIcon,
  MagnifyingGlassIcon,
  FolderIcon,
  DocumentTextIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  PencilIcon,
  TrashIcon,
  ClockIcon,
  EyeIcon,
  MapIcon,
  LinkIcon,
  ArrowPathIcon,
  XMarkIcon,
  CheckIcon,
  EllipsisVerticalIcon,
  BookmarkIcon,
  GlobeAltIcon,
  Squares2X2Icon,
} from '@heroicons/vue/24/outline'
import { BookmarkIcon as BookmarkSolidIcon } from '@heroicons/vue/24/solid'

const wikiStore = useWikiStore()
const toast = useToast()
const { confirm } = useConfirmDialog()

// State
const sidebarCollapsed = ref(false)
const searchQuery = ref('')
const selectedCategory = ref(null)
const currentPageId = ref(null)
const isEditing = ref(false)
const isCreating = ref(false)
const showGraphView = ref(false)
const showCategoryModal = ref(false)
const showHistoryModal = ref(false)
const showDropdown = ref(false)
const editingCategory = ref(null)
const graphContainer = ref(null)

const editForm = ref({
  title: '',
  content: '',
  category_id: null,
  parent_id: null,
  is_published: false,
  is_pinned: false
})

const categoryForm = ref({
  name: '',
  color: '#6366f1',
  icon: '',
  description: ''
})

// Computed
const filteredPages = computed(() => {
  let result = wikiStore.pages
  if (selectedCategory.value) {
    result = result.filter(p => p.category_id === selectedCategory.value)
  }
  return result
})

const availableParents = computed(() => {
  return wikiStore.pages.filter(p => p.id !== currentPageId.value)
})

const renderedContent = computed(() => {
  if (!wikiStore.currentPage?.content) return ''

  // Replace wiki links [[Page Title]] with actual links
  let content = wikiStore.currentPage.content.replace(
    /\[\[([^\]|]+)(?:\|([^\]]+))?\]\]/g,
    (match, target, display) => {
      const displayText = display || target
      const page = wikiStore.pages.find(p => p.title === target || p.slug === target)
      if (page) {
        return `<a href="#" class="wiki-link text-indigo-400 hover:text-indigo-300 hover:underline" data-page-id="${page.id}">${displayText}</a>`
      }
      return `<span class="wiki-link-broken text-red-400">${displayText}</span>`
    }
  )

  // Parse markdown and sanitize
  const html = marked.parse(content)
  return DOMPurify.sanitize(html)
})

// Methods
function createNewPage() {
  currentPageId.value = null
  isCreating.value = true
  isEditing.value = false
  editForm.value = {
    title: '',
    content: '',
    category_id: selectedCategory.value,
    parent_id: null,
    is_published: false,
    is_pinned: false
  }
}

async function selectPage(pageId) {
  if (isEditing.value || isCreating.value) {
    if (!await confirm({ message: 'Ungespeicherte Änderungen verwerfen?', type: 'danger', confirmText: 'Verwerfen' })) return
  }

  currentPageId.value = pageId
  isEditing.value = false
  isCreating.value = false

  try {
    const page = await wikiStore.fetchPage(pageId)
    console.log('Page loaded:', page)
    if (!page) {
      console.error('Page not found or empty response')
    }
  } catch (err) {
    console.error('Failed to load page:', err)
    toast.error('Fehler beim Laden der Seite: ' + (err.response?.data?.error || err.message))
  }
}

function startEdit() {
  if (!wikiStore.currentPage) return
  editForm.value = {
    title: wikiStore.currentPage.title,
    content: wikiStore.currentPage.content || '',
    category_id: wikiStore.currentPage.category_id,
    parent_id: wikiStore.currentPage.parent_id,
    is_published: wikiStore.currentPage.is_published,
    is_pinned: wikiStore.currentPage.is_pinned
  }
  isEditing.value = true
  showDropdown.value = false
}

function cancelEdit() {
  isEditing.value = false
  isCreating.value = false
  if (!currentPageId.value) {
    editForm.value = { title: '', content: '', category_id: null, parent_id: null, is_published: false, is_pinned: false }
  }
}

async function savePage() {
  if (!editForm.value.title.trim()) {
    toast.warning('Titel ist erforderlich')
    return
  }

  try {
    if (isCreating.value) {
      const page = await wikiStore.createPage(editForm.value)
      currentPageId.value = page.id
      isCreating.value = false
    } else {
      await wikiStore.updatePage(currentPageId.value, editForm.value)
      isEditing.value = false
    }
  } catch (err) {
    toast.error('Fehler beim Speichern: ' + (wikiStore.error || err.message))
  }
}

async function togglePin() {
  if (!wikiStore.currentPage) return
  await wikiStore.updatePage(currentPageId.value, {
    is_pinned: !wikiStore.currentPage.is_pinned
  })
  showDropdown.value = false
}

async function togglePublish() {
  if (!wikiStore.currentPage) return
  await wikiStore.updatePage(currentPageId.value, {
    is_published: !wikiStore.currentPage.is_published
  })
  showDropdown.value = false
}

async function confirmDelete() {
  if (!await confirm({ message: 'Diese Seite wirklich löschen?', type: 'danger', confirmText: 'Löschen' })) return

  try {
    await wikiStore.deletePage(currentPageId.value)
    currentPageId.value = null
    showDropdown.value = false
  } catch (err) {
    toast.error('Fehler beim Löschen')
  }
}

// Search with debounce
let searchTimeout = null
function debouncedSearch() {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    if (searchQuery.value.trim()) {
      wikiStore.searchPages(searchQuery.value)
    } else {
      wikiStore.clearSearch()
    }
  }, 300)
}

// Category methods
async function saveCategory() {
  if (!categoryForm.value.name.trim()) {
    toast.warning('Name ist erforderlich')
    return
  }

  try {
    if (editingCategory.value) {
      await wikiStore.updateCategory(editingCategory.value, categoryForm.value)
    } else {
      await wikiStore.createCategory(categoryForm.value)
    }
    showCategoryModal.value = false
    editingCategory.value = null
    categoryForm.value = { name: '', color: '#6366f1', icon: '', description: '' }
  } catch (err) {
    toast.error('Fehler beim Speichern der Kategorie')
  }
}

// History
async function restoreVersion(historyId) {
  if (!await confirm({ message: 'Diese Version wiederherstellen? Aktueller Inhalt wird in die Historie gespeichert.', type: 'danger', confirmText: 'Wiederherstellen' })) return

  try {
    await wikiStore.restoreFromHistory(currentPageId.value, historyId)
    showHistoryModal.value = false
  } catch (err) {
    toast.error('Fehler beim Wiederherstellen')
  }
}

// Watch for history modal
watch(showHistoryModal, async (show) => {
  if (show && currentPageId.value) {
    await wikiStore.fetchPageHistory(currentPageId.value)
  }
})

// Watch for graph view
watch(showGraphView, async (show) => {
  if (show) {
    await wikiStore.fetchGraphData()
    await nextTick()
    renderGraph()
  }
})

function renderGraph() {
  const container = graphContainer.value
  if (!container) return

  const canvas = document.createElement('canvas')
  canvas.width = container.clientWidth
  canvas.height = container.clientHeight
  container.innerHTML = ''
  container.appendChild(canvas)

  const ctx = canvas.getContext('2d')
  const nodes = wikiStore.graphData.nodes.map((n, i) => ({
    ...n,
    x: Math.random() * canvas.width,
    y: Math.random() * canvas.height,
    vx: 0,
    vy: 0
  }))
  const edges = wikiStore.graphData.edges

  function tick() {
    nodes.forEach(node => {
      nodes.forEach(other => {
        if (node.id === other.id) return
        const dx = node.x - other.x
        const dy = node.y - other.y
        const dist = Math.sqrt(dx * dx + dy * dy) || 1
        const force = 1000 / (dist * dist)
        node.vx += (dx / dist) * force
        node.vy += (dy / dist) * force
      })
      node.vx += (canvas.width / 2 - node.x) * 0.001
      node.vy += (canvas.height / 2 - node.y) * 0.001
    })

    edges.forEach(edge => {
      const source = nodes.find(n => n.id === edge.source)
      const target = nodes.find(n => n.id === edge.target)
      if (!source || !target) return
      const dx = target.x - source.x
      const dy = target.y - source.y
      const dist = Math.sqrt(dx * dx + dy * dy) || 1
      const force = (dist - 100) * 0.01
      source.vx += (dx / dist) * force
      source.vy += (dy / dist) * force
      target.vx -= (dx / dist) * force
      target.vy -= (dy / dist) * force
    })

    nodes.forEach(node => {
      node.x += node.vx * 0.1
      node.y += node.vy * 0.1
      node.vx *= 0.9
      node.vy *= 0.9
      node.x = Math.max(50, Math.min(canvas.width - 50, node.x))
      node.y = Math.max(30, Math.min(canvas.height - 30, node.y))
    })

    ctx.clearRect(0, 0, canvas.width, canvas.height)

    ctx.strokeStyle = '#4b5563'
    ctx.lineWidth = 1
    edges.forEach(edge => {
      const source = nodes.find(n => n.id === edge.source)
      const target = nodes.find(n => n.id === edge.target)
      if (!source || !target) return
      ctx.beginPath()
      ctx.moveTo(source.x, source.y)
      ctx.lineTo(target.x, target.y)
      ctx.stroke()
    })

    nodes.forEach(node => {
      ctx.beginPath()
      ctx.arc(node.x, node.y, 20, 0, Math.PI * 2)
      ctx.fillStyle = node.id === currentPageId.value ? '#6366f1' : '#374151'
      ctx.fill()
      ctx.strokeStyle = '#6366f1'
      ctx.lineWidth = 2
      ctx.stroke()

      ctx.fillStyle = '#fff'
      ctx.font = '10px sans-serif'
      ctx.textAlign = 'center'
      ctx.textBaseline = 'middle'
      const label = node.label.length > 12 ? node.label.slice(0, 12) + '...' : node.label
      ctx.fillText(label, node.x, node.y)
    })
  }

  let frame = 0
  function animate() {
    tick()
    frame++
    if (frame < 200) {
      requestAnimationFrame(animate)
    }
  }
  animate()

  canvas.addEventListener('click', (e) => {
    const rect = canvas.getBoundingClientRect()
    const x = e.clientX - rect.left
    const y = e.clientY - rect.top

    nodes.forEach(node => {
      const dx = x - node.x
      const dy = y - node.y
      if (dx * dx + dy * dy < 400) {
        showGraphView.value = false
        selectPage(node.id)
      }
    })
  })
}

function formatDate(dateStr) {
  if (!dateStr) return ''
  const date = new Date(dateStr)
  return date.toLocaleDateString('de-DE', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

// Handle wiki link clicks
document.addEventListener('click', (e) => {
  if (e.target.classList.contains('wiki-link') && e.target.dataset.pageId) {
    e.preventDefault()
    selectPage(e.target.dataset.pageId)
  }
})

// Init
onMounted(async () => {
  await Promise.all([
    wikiStore.fetchPages(),
    wikiStore.fetchCategories(),
    wikiStore.fetchRecentPages()
  ])
})
</script>

<template>
  <!-- Negative margin to counteract parent padding, calc height to fill available space -->
  <div class="flex bg-dark-900 -m-4 lg:-m-6" style="min-height: calc(100vh - 64px);">
    <!-- Sidebar -->
    <aside
      class="bg-dark-800 border-r border-dark-600 flex flex-col transition-all duration-200"
      :class="sidebarCollapsed ? 'w-12' : 'w-72'"
    >
      <div class="flex items-center justify-between p-4 border-b border-dark-600">
        <div v-if="!sidebarCollapsed" class="flex items-center gap-2">
          <BookOpenIcon class="w-6 h-6 text-indigo-400" />
          <h2 class="text-lg font-semibold text-white">Wiki</h2>
        </div>
        <button
          class="p-1.5 text-gray-400 hover:text-white rounded-lg hover:bg-dark-700"
          @click="sidebarCollapsed = !sidebarCollapsed"
        >
          <ChevronLeftIcon v-if="!sidebarCollapsed" class="w-5 h-5" />
          <ChevronRightIcon v-else class="w-5 h-5" />
        </button>
      </div>

      <div v-if="!sidebarCollapsed" class="flex-1 overflow-y-auto p-4 space-y-4">
        <!-- Search -->
        <div class="relative">
          <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-500" />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Suchen..."
            class="w-full pl-9 pr-4 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white text-sm placeholder-gray-500 focus:outline-none focus:border-indigo-500"
            @input="debouncedSearch"
          />
        </div>

        <!-- Actions -->
        <div class="flex gap-2">
          <button
            class="flex-1 flex items-center justify-center gap-2 px-3 py-2 bg-indigo-600 hover:bg-indigo-500 rounded-lg text-white text-sm"
            @click="createNewPage"
          >
            <PlusIcon class="w-4 h-4" />
            Neue Seite
          </button>
          <button
            class="p-2 bg-dark-700 hover:bg-dark-600 rounded-lg text-gray-400 hover:text-white"
            @click="showGraphView = true"
            title="Graph-Ansicht"
          >
            <Squares2X2Icon class="w-5 h-5" />
          </button>
        </div>

        <!-- Search Results -->
        <div v-if="searchQuery && wikiStore.searchResults.length" class="space-y-1">
          <h4 class="text-xs font-medium text-gray-500 uppercase tracking-wider">Suchergebnisse</h4>
          <button
            v-for="result in wikiStore.searchResults"
            :key="result.id"
            class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-left text-sm text-gray-300 hover:bg-dark-700"
            @click="selectPage(result.id)"
          >
            <DocumentTextIcon class="w-4 h-4 text-gray-500" />
            {{ result.title }}
          </button>
        </div>

        <!-- Categories & Pages -->
        <div v-else class="space-y-4">
          <!-- All Pages -->
          <button
            class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-left text-sm transition-colors"
            :class="!selectedCategory ? 'bg-indigo-600/20 text-indigo-300' : 'text-gray-300 hover:bg-dark-700'"
            @click="selectedCategory = null"
          >
            <FolderIcon class="w-4 h-4" />
            <span class="flex-1">Alle Seiten</span>
            <span class="text-xs text-gray-500">{{ wikiStore.pages.length }}</span>
          </button>

          <!-- Categories -->
          <div v-for="category in wikiStore.categories" :key="category.id">
            <button
              class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-left text-sm transition-colors"
              :class="selectedCategory === category.id ? 'bg-indigo-600/20 text-indigo-300' : 'text-gray-300 hover:bg-dark-700'"
              @click="selectedCategory = category.id"
            >
              <div
                class="w-3 h-3 rounded-full"
                :style="{ backgroundColor: category.color }"
              ></div>
              <span class="flex-1">{{ category.name }}</span>
              <span class="text-xs text-gray-500">{{ category.page_count || 0 }}</span>
            </button>
          </div>

          <button
            class="w-full text-left text-sm text-indigo-400 hover:text-indigo-300 px-3 py-1"
            @click="showCategoryModal = true"
          >
            <PlusIcon class="w-4 h-4 inline mr-1" />
            Kategorie hinzufügen
          </button>

          <!-- Page List -->
          <div class="space-y-1">
            <h4 class="text-xs font-medium text-gray-500 uppercase tracking-wider px-3">Seiten</h4>
            <button
              v-for="page in filteredPages"
              :key="page.id"
              class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-left text-sm transition-colors"
              :class="currentPageId === page.id ? 'bg-dark-600 text-white' : 'text-gray-300 hover:bg-dark-700'"
              @click="selectPage(page.id)"
            >
              <DocumentTextIcon class="w-4 h-4 text-gray-500" />
              <span class="flex-1 truncate">{{ page.title }}</span>
              <BookmarkSolidIcon v-if="page.is_pinned" class="w-3 h-3 text-yellow-400" />
            </button>
          </div>

          <!-- Recent Pages -->
          <div v-if="wikiStore.recentPages.length" class="space-y-1">
            <h4 class="text-xs font-medium text-gray-500 uppercase tracking-wider px-3">Zuletzt bearbeitet</h4>
            <button
              v-for="page in wikiStore.recentPages.slice(0, 5)"
              :key="page.id"
              class="w-full flex items-center gap-2 px-3 py-1.5 rounded-lg text-left text-xs text-gray-400 hover:bg-dark-700 hover:text-gray-300"
              @click="selectPage(page.id)"
            >
              <ClockIcon class="w-3 h-3" />
              {{ page.title }}
            </button>
          </div>
        </div>
      </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 overflow-y-auto">
      <!-- Loading state -->
      <div v-if="wikiStore.loading && currentPageId" class="flex flex-col items-center justify-center h-full text-center p-8">
        <ArrowPathIcon class="w-12 h-12 text-indigo-400 animate-spin mb-4" />
        <p class="text-gray-400">Seite wird geladen...</p>
      </div>

      <!-- No page selected -->
      <div v-else-if="!currentPageId && !isCreating" class="flex flex-col items-center justify-center h-full text-center p-8">
        <BookOpenIcon class="w-20 h-20 text-gray-600 mb-4" />
        <h3 class="text-xl font-semibold text-gray-400 mb-2">Willkommen im Wiki</h3>
        <p class="text-gray-500 mb-6">Wähle eine Seite aus der Sidebar oder erstelle eine neue.</p>
        <button
          class="flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-500 rounded-lg text-white"
          @click="createNewPage"
        >
          <PlusIcon class="w-5 h-5" />
          Erste Seite erstellen
        </button>
      </div>

      <!-- Page Editor/Viewer -->
      <div v-else class="max-w-4xl mx-auto p-8">
        <!-- Header -->
        <div class="flex items-start justify-between mb-6 pb-4 border-b border-dark-600">
          <div class="flex-1">
            <input
              v-if="isEditing || isCreating"
              v-model="editForm.title"
              type="text"
              class="w-full text-3xl font-bold bg-transparent text-white border-none outline-none placeholder-gray-600"
              placeholder="Seitentitel"
            />
            <h1 v-else class="text-3xl font-bold text-white">{{ wikiStore.currentPage?.title || 'Neue Seite' }}</h1>

            <div v-if="wikiStore.currentPage && !isCreating" class="flex items-center gap-4 mt-2 text-sm text-gray-500">
              <span
                v-if="wikiStore.currentPage.category_name"
                class="px-2 py-0.5 rounded-full text-xs text-white"
                :style="{ backgroundColor: wikiStore.currentPage.category_color }"
              >
                {{ wikiStore.currentPage.category_name }}
              </span>
              <span class="flex items-center gap-1">
                <EyeIcon class="w-4 h-4" />
                {{ wikiStore.currentPage.view_count }} Aufrufe
              </span>
              <span class="flex items-center gap-1">
                <ClockIcon class="w-4 h-4" />
                {{ wikiStore.currentPage.reading_time }} Min.
              </span>
              <span>
                Aktualisiert {{ formatDate(wikiStore.currentPage.updated_at) }}
              </span>
            </div>
          </div>

          <div class="flex items-center gap-2">
            <template v-if="isEditing || isCreating">
              <button
                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 rounded-lg text-white text-sm"
                :disabled="wikiStore.loading"
                @click="savePage"
              >
                <CheckIcon class="w-4 h-4 inline mr-1" />
                Speichern
              </button>
              <button
                class="px-4 py-2 bg-dark-700 hover:bg-dark-600 rounded-lg text-white text-sm"
                @click="cancelEdit"
              >
                Abbrechen
              </button>
            </template>
            <template v-else>
              <button
                class="px-4 py-2 bg-dark-700 hover:bg-dark-600 rounded-lg text-white text-sm"
                @click="startEdit"
              >
                <PencilIcon class="w-4 h-4 inline mr-1" />
                Bearbeiten
              </button>
              <div class="relative">
                <button
                  class="p-2 bg-dark-700 hover:bg-dark-600 rounded-lg text-gray-400 hover:text-white"
                  @click="showDropdown = !showDropdown"
                >
                  <EllipsisVerticalIcon class="w-5 h-5" />
                </button>
                <div
                  v-if="showDropdown"
                  class="absolute right-0 top-full mt-1 w-48 bg-dark-700 border border-dark-600 rounded-lg shadow-xl z-50 py-1"
                >
                  <button
                    class="w-full px-4 py-2 text-left text-sm text-gray-300 hover:bg-dark-600 flex items-center gap-2"
                    @click="togglePin"
                  >
                    <BookmarkIcon class="w-4 h-4" />
                    {{ wikiStore.currentPage?.is_pinned ? 'Nicht mehr anpinnen' : 'Anpinnen' }}
                  </button>
                  <button
                    class="w-full px-4 py-2 text-left text-sm text-gray-300 hover:bg-dark-600 flex items-center gap-2"
                    @click="togglePublish"
                  >
                    <GlobeAltIcon class="w-4 h-4" />
                    {{ wikiStore.currentPage?.is_published ? 'Verstecken' : 'Veröffentlichen' }}
                  </button>
                  <button
                    class="w-full px-4 py-2 text-left text-sm text-gray-300 hover:bg-dark-600 flex items-center gap-2"
                    @click="showHistoryModal = true; showDropdown = false"
                  >
                    <ArrowPathIcon class="w-4 h-4" />
                    Versionen
                  </button>
                  <hr class="border-dark-600 my-1" />
                  <button
                    class="w-full px-4 py-2 text-left text-sm text-red-400 hover:bg-dark-600 flex items-center gap-2"
                    @click="confirmDelete"
                  >
                    <TrashIcon class="w-4 h-4" />
                    Löschen
                  </button>
                </div>
              </div>
            </template>
          </div>
        </div>

        <!-- Editor Options -->
        <div v-if="isEditing || isCreating" class="flex flex-wrap gap-4 mb-4 p-4 bg-dark-800 rounded-lg">
          <div>
            <label class="block text-xs text-gray-500 mb-1">Kategorie</label>
            <select
              v-model="editForm.category_id"
              class="px-3 py-1.5 bg-dark-700 border border-dark-600 rounded text-sm text-white"
            >
              <option :value="null">Keine Kategorie</option>
              <option v-for="cat in wikiStore.categories" :key="cat.id" :value="cat.id">
                {{ cat.name }}
              </option>
            </select>
          </div>

          <div>
            <label class="block text-xs text-gray-500 mb-1">Übergeordnete Seite</label>
            <select
              v-model="editForm.parent_id"
              class="px-3 py-1.5 bg-dark-700 border border-dark-600 rounded text-sm text-white"
            >
              <option :value="null">Keine</option>
              <option v-for="page in availableParents" :key="page.id" :value="page.id">
                {{ page.title }}
              </option>
            </select>
          </div>

          <label class="flex items-center gap-2 text-sm text-gray-300">
            <input type="checkbox" v-model="editForm.is_published" class="rounded bg-dark-700 border-dark-600 text-indigo-600" />
            Veröffentlicht
          </label>

          <label class="flex items-center gap-2 text-sm text-gray-300">
            <input type="checkbox" v-model="editForm.is_pinned" class="rounded bg-dark-700 border-dark-600 text-indigo-600" />
            Angepinnt
          </label>
        </div>

        <!-- Content Area -->
        <div class="min-h-[400px]">
          <textarea
            v-if="isEditing || isCreating"
            v-model="editForm.content"
            class="w-full min-h-[400px] p-4 bg-dark-800 border border-dark-600 rounded-lg text-white font-mono text-sm resize-y focus:outline-none focus:border-indigo-500"
            placeholder="Schreibe deinen Inhalt in Markdown...

Nutze [[Seitentitel]] um auf andere Seiten zu verlinken."
          ></textarea>

          <div
            v-else
            class="prose prose-invert prose-indigo max-w-none"
            v-html="renderedContent"
          ></div>
        </div>

        <!-- Backlinks -->
        <div v-if="wikiStore.currentPage?.backlinks?.length && !isEditing" class="mt-8 pt-4 border-t border-dark-600">
          <h4 class="flex items-center gap-2 text-sm font-medium text-gray-400 mb-3">
            <LinkIcon class="w-4 h-4" />
            Backlinks
          </h4>
          <div class="flex flex-wrap gap-2">
            <button
              v-for="link in wikiStore.currentPage.backlinks"
              :key="link.id"
              class="px-3 py-1 bg-dark-700 hover:bg-dark-600 rounded-full text-sm text-gray-300"
              @click="selectPage(link.id)"
            >
              {{ link.title }}
            </button>
          </div>
        </div>

        <!-- Children Pages -->
        <div v-if="wikiStore.currentPage?.children?.length && !isEditing" class="mt-6">
          <h4 class="flex items-center gap-2 text-sm font-medium text-gray-400 mb-3">
            <MapIcon class="w-4 h-4" />
            Unterseiten
          </h4>
          <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
            <button
              v-for="child in wikiStore.currentPage.children"
              :key="child.id"
              class="flex items-center gap-2 p-3 bg-dark-800 hover:bg-dark-700 rounded-lg text-left"
              @click="selectPage(child.id)"
            >
              <DocumentTextIcon class="w-5 h-5 text-gray-500" />
              <span class="text-sm text-gray-300">{{ child.title }}</span>
            </button>
          </div>
        </div>
      </div>
    </main>

    <!-- Graph Modal -->
    <Teleport to="body">
      <div v-if="showGraphView" class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black/60" @click="showGraphView = false"></div>
        <div class="relative bg-dark-800 rounded-xl border border-dark-600 w-full max-w-4xl h-[600px] flex flex-col">
          <div class="flex items-center justify-between p-4 border-b border-dark-600">
            <h3 class="text-lg font-semibold text-white">Wissensgraph</h3>
            <button class="p-1 text-gray-400 hover:text-white" @click="showGraphView = false">
              <XMarkIcon class="w-6 h-6" />
            </button>
          </div>
          <div ref="graphContainer" class="flex-1 bg-dark-900 rounded-b-xl"></div>
        </div>
      </div>
    </Teleport>

    <!-- Category Modal -->
    <Teleport to="body">
      <div v-if="showCategoryModal" class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black/60" @click="showCategoryModal = false"></div>
        <div class="relative bg-dark-800 rounded-xl border border-dark-600 p-6 w-full max-w-md">
          <h3 class="text-lg font-semibold text-white mb-4">
            {{ editingCategory ? 'Kategorie bearbeiten' : 'Neue Kategorie' }}
          </h3>
          <div class="space-y-4">
            <div>
              <label class="block text-sm text-gray-400 mb-1">Name</label>
              <input
                v-model="categoryForm.name"
                type="text"
                class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white"
                placeholder="Kategoriename"
              />
            </div>
            <div>
              <label class="block text-sm text-gray-400 mb-1">Farbe</label>
              <input
                v-model="categoryForm.color"
                type="color"
                class="w-16 h-10 bg-dark-700 border border-dark-600 rounded cursor-pointer"
              />
            </div>
            <div>
              <label class="block text-sm text-gray-400 mb-1">Beschreibung</label>
              <textarea
                v-model="categoryForm.description"
                class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white resize-none"
                rows="2"
                placeholder="Optionale Beschreibung"
              ></textarea>
            </div>
          </div>
          <div class="flex justify-end gap-2 mt-6">
            <button class="px-4 py-2 bg-dark-700 hover:bg-dark-600 rounded-lg text-white" @click="showCategoryModal = false">
              Abbrechen
            </button>
            <button class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 rounded-lg text-white" @click="saveCategory">
              Speichern
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- History Modal -->
    <Teleport to="body">
      <div v-if="showHistoryModal" class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black/60" @click="showHistoryModal = false"></div>
        <div class="relative bg-dark-800 rounded-xl border border-dark-600 w-full max-w-lg max-h-[80vh] flex flex-col">
          <div class="flex items-center justify-between p-4 border-b border-dark-600">
            <h3 class="text-lg font-semibold text-white">Versionshistorie</h3>
            <button class="p-1 text-gray-400 hover:text-white" @click="showHistoryModal = false">
              <XMarkIcon class="w-6 h-6" />
            </button>
          </div>
          <div class="flex-1 overflow-y-auto p-4">
            <div v-if="wikiStore.pageHistory.length === 0" class="text-center py-8 text-gray-500">
              Keine Versionen verfügbar
            </div>
            <div v-else class="space-y-3">
              <div
                v-for="entry in wikiStore.pageHistory"
                :key="entry.id"
                class="flex items-center justify-between p-4 bg-dark-700 rounded-lg"
              >
                <div>
                  <div class="flex items-center gap-2">
                    <span class="text-indigo-400 font-medium">v{{ entry.version_number }}</span>
                    <span class="text-white">{{ entry.title }}</span>
                  </div>
                  <div class="text-sm text-gray-500 mt-1">
                    von {{ entry.changed_by_name }} - {{ formatDate(entry.created_at) }}
                  </div>
                  <div v-if="entry.change_note" class="text-sm text-gray-400 italic mt-1">
                    {{ entry.change_note }}
                  </div>
                </div>
                <button
                  class="px-3 py-1.5 bg-dark-600 hover:bg-dark-500 rounded text-sm text-white"
                  @click="restoreVersion(entry.id)"
                >
                  Wiederherstellen
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style>
/* Prose styling for markdown content */
.prose {
  color: #e5e7eb;
  line-height: 1.75;
}

.prose h1, .prose h2, .prose h3, .prose h4 {
  color: #fff;
  margin-top: 1.5rem;
  margin-bottom: 0.75rem;
}

.prose h1 { font-size: 2rem; }
.prose h2 { font-size: 1.5rem; }
.prose h3 { font-size: 1.25rem; }

.prose p {
  margin-bottom: 1rem;
}

.prose code {
  background: #374151;
  padding: 0.125rem 0.375rem;
  border-radius: 0.25rem;
  font-size: 0.875em;
}

.prose pre {
  background: #1f2937;
  padding: 1rem;
  border-radius: 0.5rem;
  overflow-x: auto;
  margin: 1rem 0;
}

.prose pre code {
  background: transparent;
  padding: 0;
}

.prose blockquote {
  border-left: 4px solid #6366f1;
  margin: 1rem 0;
  padding-left: 1rem;
  color: #9ca3af;
  font-style: italic;
}

.prose ul, .prose ol {
  margin: 1rem 0;
  padding-left: 1.5rem;
}

.prose li {
  margin: 0.25rem 0;
}

.prose a {
  color: #818cf8;
}

.prose a:hover {
  text-decoration: underline;
}

.prose img {
  max-width: 100%;
  border-radius: 0.5rem;
  margin: 1rem 0;
}

.prose table {
  width: 100%;
  border-collapse: collapse;
  margin: 1rem 0;
}

.prose th, .prose td {
  border: 1px solid #374151;
  padding: 0.5rem 0.75rem;
  text-align: left;
}

.prose th {
  background: #1f2937;
  font-weight: 600;
}
</style>
