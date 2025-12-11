<template>
  <div class="wiki-container">
    <!-- Sidebar -->
    <aside class="wiki-sidebar" :class="{ collapsed: sidebarCollapsed }">
      <div class="sidebar-header">
        <h2 v-if="!sidebarCollapsed">Wiki</h2>
        <button class="btn-icon" @click="sidebarCollapsed = !sidebarCollapsed">
          <i :class="sidebarCollapsed ? 'fas fa-chevron-right' : 'fas fa-chevron-left'"></i>
        </button>
      </div>

      <div v-if="!sidebarCollapsed" class="sidebar-content">
        <!-- Search -->
        <div class="search-box">
          <i class="fas fa-search"></i>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search pages..."
            @input="debouncedSearch"
          />
        </div>

        <!-- Actions -->
        <div class="sidebar-actions">
          <button class="btn btn-primary btn-sm" @click="createNewPage">
            <i class="fas fa-plus"></i> New Page
          </button>
          <button class="btn btn-secondary btn-sm" @click="showGraphView = true">
            <i class="fas fa-project-diagram"></i>
          </button>
        </div>

        <!-- Search Results -->
        <div v-if="searchQuery && wikiStore.searchResults.length" class="search-results">
          <h4>Search Results</h4>
          <div
            v-for="result in wikiStore.searchResults"
            :key="result.id"
            class="page-item"
            @click="selectPage(result.id)"
          >
            <i class="fas fa-file-alt"></i>
            <span>{{ result.title }}</span>
          </div>
        </div>

        <!-- Categories -->
        <div v-else class="page-tree">
          <div class="category-section">
            <div
              class="category-header"
              :class="{ active: !selectedCategory }"
              @click="selectedCategory = null"
            >
              <i class="fas fa-folder"></i>
              <span>All Pages</span>
              <span class="count">{{ wikiStore.pages.length }}</span>
            </div>
          </div>

          <div
            v-for="category in wikiStore.categories"
            :key="category.id"
            class="category-section"
          >
            <div
              class="category-header"
              :class="{ active: selectedCategory === category.id }"
              :style="{ '--category-color': category.color }"
              @click="selectedCategory = category.id"
            >
              <i :class="category.icon || 'fas fa-folder'"></i>
              <span>{{ category.name }}</span>
              <span class="count">{{ category.page_count || 0 }}</span>
            </div>
          </div>

          <button class="btn-link add-category" @click="showCategoryModal = true">
            <i class="fas fa-plus"></i> Add Category
          </button>

          <!-- Page List -->
          <div class="page-list">
            <h4>Pages</h4>
            <div
              v-for="page in filteredPages"
              :key="page.id"
              class="page-item"
              :class="{ active: currentPageId === page.id }"
              @click="selectPage(page.id)"
            >
              <i v-if="page.icon" :class="page.icon"></i>
              <i v-else class="fas fa-file-alt"></i>
              <span>{{ page.title }}</span>
              <i v-if="page.is_pinned" class="fas fa-thumbtack pin-icon"></i>
            </div>
          </div>

          <!-- Recent Pages -->
          <div v-if="wikiStore.recentPages.length" class="recent-pages">
            <h4>Recent</h4>
            <div
              v-for="page in wikiStore.recentPages.slice(0, 5)"
              :key="page.id"
              class="page-item small"
              @click="selectPage(page.id)"
            >
              <i class="fas fa-clock"></i>
              <span>{{ page.title }}</span>
            </div>
          </div>
        </div>
      </div>
    </aside>

    <!-- Main Content -->
    <main class="wiki-main">
      <!-- No page selected -->
      <div v-if="!currentPageId && !isCreating" class="empty-state">
        <i class="fas fa-book-open"></i>
        <h3>Welcome to your Wiki</h3>
        <p>Select a page from the sidebar or create a new one</p>
        <button class="btn btn-primary" @click="createNewPage">
          <i class="fas fa-plus"></i> Create First Page
        </button>
      </div>

      <!-- Page Editor/Viewer -->
      <div v-else class="page-content">
        <!-- Header -->
        <div class="page-header">
          <div class="page-title-section">
            <input
              v-if="isEditing"
              v-model="editForm.title"
              type="text"
              class="page-title-input"
              placeholder="Page Title"
            />
            <h1 v-else>{{ wikiStore.currentPage?.title || 'New Page' }}</h1>

            <div class="page-meta" v-if="wikiStore.currentPage && !isCreating">
              <span v-if="wikiStore.currentPage.category_name" class="category-badge" :style="{ backgroundColor: wikiStore.currentPage.category_color }">
                {{ wikiStore.currentPage.category_name }}
              </span>
              <span class="meta-item">
                <i class="fas fa-eye"></i> {{ wikiStore.currentPage.view_count }} views
              </span>
              <span class="meta-item">
                <i class="fas fa-clock"></i> {{ wikiStore.currentPage.reading_time }} min read
              </span>
              <span class="meta-item">
                Updated {{ formatDate(wikiStore.currentPage.updated_at) }}
              </span>
            </div>
          </div>

          <div class="page-actions">
            <template v-if="isEditing || isCreating">
              <button class="btn btn-primary" @click="savePage" :disabled="wikiStore.loading">
                <i class="fas fa-save"></i> Save
              </button>
              <button class="btn btn-secondary" @click="cancelEdit">
                Cancel
              </button>
            </template>
            <template v-else>
              <button class="btn btn-secondary" @click="startEdit">
                <i class="fas fa-edit"></i> Edit
              </button>
              <div class="dropdown">
                <button class="btn btn-icon">
                  <i class="fas fa-ellipsis-v"></i>
                </button>
                <div class="dropdown-menu">
                  <button @click="togglePin">
                    <i :class="wikiStore.currentPage?.is_pinned ? 'fas fa-thumbtack' : 'far fa-thumbtack'"></i>
                    {{ wikiStore.currentPage?.is_pinned ? 'Unpin' : 'Pin' }}
                  </button>
                  <button @click="togglePublish">
                    <i :class="wikiStore.currentPage?.is_published ? 'fas fa-eye-slash' : 'fas fa-eye'"></i>
                    {{ wikiStore.currentPage?.is_published ? 'Unpublish' : 'Publish' }}
                  </button>
                  <button @click="showHistoryModal = true">
                    <i class="fas fa-history"></i> History
                  </button>
                  <hr />
                  <button class="danger" @click="confirmDelete">
                    <i class="fas fa-trash"></i> Delete
                  </button>
                </div>
              </div>
            </template>
          </div>
        </div>

        <!-- Editor Options -->
        <div v-if="isEditing || isCreating" class="editor-options">
          <select v-model="editForm.category_id">
            <option :value="null">No Category</option>
            <option v-for="cat in wikiStore.categories" :key="cat.id" :value="cat.id">
              {{ cat.name }}
            </option>
          </select>

          <select v-model="editForm.parent_id">
            <option :value="null">No Parent</option>
            <option v-for="page in availableParents" :key="page.id" :value="page.id">
              {{ page.title }}
            </option>
          </select>

          <label class="checkbox-label">
            <input type="checkbox" v-model="editForm.is_published" />
            Published
          </label>

          <label class="checkbox-label">
            <input type="checkbox" v-model="editForm.is_pinned" />
            Pinned
          </label>
        </div>

        <!-- Content Area -->
        <div class="content-area">
          <textarea
            v-if="isEditing || isCreating"
            v-model="editForm.content"
            class="markdown-editor"
            placeholder="Write your content in Markdown...

Use [[Page Title]] to link to other pages."
          ></textarea>

          <div v-else class="markdown-content" v-html="renderedContent"></div>
        </div>

        <!-- Backlinks -->
        <div v-if="wikiStore.currentPage?.backlinks?.length && !isEditing" class="backlinks-section">
          <h4><i class="fas fa-link"></i> Backlinks</h4>
          <div class="backlinks-list">
            <span
              v-for="link in wikiStore.currentPage.backlinks"
              :key="link.id"
              class="backlink"
              @click="selectPage(link.id)"
            >
              {{ link.title }}
            </span>
          </div>
        </div>

        <!-- Children Pages -->
        <div v-if="wikiStore.currentPage?.children?.length && !isEditing" class="children-section">
          <h4><i class="fas fa-sitemap"></i> Child Pages</h4>
          <div class="children-list">
            <div
              v-for="child in wikiStore.currentPage.children"
              :key="child.id"
              class="child-page"
              @click="selectPage(child.id)"
            >
              <i :class="child.icon || 'fas fa-file-alt'"></i>
              {{ child.title }}
            </div>
          </div>
        </div>
      </div>
    </main>

    <!-- Graph Modal -->
    <div v-if="showGraphView" class="modal-overlay" @click.self="showGraphView = false">
      <div class="modal graph-modal">
        <div class="modal-header">
          <h3>Knowledge Graph</h3>
          <button class="btn-icon" @click="showGraphView = false">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="modal-body">
          <div ref="graphContainer" class="graph-container"></div>
        </div>
      </div>
    </div>

    <!-- Category Modal -->
    <div v-if="showCategoryModal" class="modal-overlay" @click.self="showCategoryModal = false">
      <div class="modal">
        <div class="modal-header">
          <h3>{{ editingCategory ? 'Edit Category' : 'New Category' }}</h3>
          <button class="btn-icon" @click="showCategoryModal = false">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label>Name</label>
            <input v-model="categoryForm.name" type="text" placeholder="Category name" />
          </div>
          <div class="form-group">
            <label>Color</label>
            <input v-model="categoryForm.color" type="color" />
          </div>
          <div class="form-group">
            <label>Icon (Font Awesome class)</label>
            <input v-model="categoryForm.icon" type="text" placeholder="fas fa-folder" />
          </div>
          <div class="form-group">
            <label>Description</label>
            <textarea v-model="categoryForm.description" placeholder="Optional description"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="showCategoryModal = false">Cancel</button>
          <button class="btn btn-primary" @click="saveCategory">Save</button>
        </div>
      </div>
    </div>

    <!-- History Modal -->
    <div v-if="showHistoryModal" class="modal-overlay" @click.self="showHistoryModal = false">
      <div class="modal history-modal">
        <div class="modal-header">
          <h3>Page History</h3>
          <button class="btn-icon" @click="showHistoryModal = false">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="modal-body">
          <div v-if="wikiStore.pageHistory.length === 0" class="empty-history">
            No history available
          </div>
          <div v-else class="history-list">
            <div
              v-for="entry in wikiStore.pageHistory"
              :key="entry.id"
              class="history-item"
            >
              <div class="history-info">
                <span class="version">v{{ entry.version_number }}</span>
                <span class="title">{{ entry.title }}</span>
                <span class="meta">
                  by {{ entry.changed_by_name }} - {{ formatDate(entry.created_at) }}
                </span>
                <span v-if="entry.change_note" class="note">{{ entry.change_note }}</span>
              </div>
              <button class="btn btn-sm btn-secondary" @click="restoreVersion(entry.id)">
                Restore
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue'
import { useWikiStore } from '@/stores/wiki'
import { marked } from 'marked'
import DOMPurify from 'dompurify'

const wikiStore = useWikiStore()

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
        return `<a href="#" class="wiki-link" data-page-id="${page.id}">${displayText}</a>`
      }
      return `<span class="wiki-link broken">${displayText}</span>`
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
    if (!confirm('Discard unsaved changes?')) return
  }

  currentPageId.value = pageId
  isEditing.value = false
  isCreating.value = false

  try {
    await wikiStore.fetchPage(pageId)
  } catch (err) {
    console.error('Failed to load page:', err)
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
    alert('Title is required')
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
    alert('Failed to save page: ' + (wikiStore.error || err.message))
  }
}

async function togglePin() {
  if (!wikiStore.currentPage) return
  await wikiStore.updatePage(currentPageId.value, {
    is_pinned: !wikiStore.currentPage.is_pinned
  })
}

async function togglePublish() {
  if (!wikiStore.currentPage) return
  await wikiStore.updatePage(currentPageId.value, {
    is_published: !wikiStore.currentPage.is_published
  })
}

async function confirmDelete() {
  if (!confirm('Are you sure you want to delete this page?')) return

  try {
    await wikiStore.deletePage(currentPageId.value)
    currentPageId.value = null
  } catch (err) {
    alert('Failed to delete page')
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
    alert('Name is required')
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
    alert('Failed to save category')
  }
}

// History
async function restoreVersion(historyId) {
  if (!confirm('Restore this version? Current content will be saved to history.')) return

  try {
    await wikiStore.restoreFromHistory(currentPageId.value, historyId)
    showHistoryModal.value = false
  } catch (err) {
    alert('Failed to restore version')
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
  // Simple force-directed graph using canvas
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
    // Simple force simulation
    nodes.forEach(node => {
      // Repulsion from other nodes
      nodes.forEach(other => {
        if (node.id === other.id) return
        const dx = node.x - other.x
        const dy = node.y - other.y
        const dist = Math.sqrt(dx * dx + dy * dy) || 1
        const force = 1000 / (dist * dist)
        node.vx += (dx / dist) * force
        node.vy += (dy / dist) * force
      })

      // Attraction to center
      node.vx += (canvas.width / 2 - node.x) * 0.001
      node.vy += (canvas.height / 2 - node.y) * 0.001
    })

    // Edge attraction
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

    // Apply velocities with damping
    nodes.forEach(node => {
      node.x += node.vx * 0.1
      node.y += node.vy * 0.1
      node.vx *= 0.9
      node.vy *= 0.9
      // Keep in bounds
      node.x = Math.max(50, Math.min(canvas.width - 50, node.x))
      node.y = Math.max(30, Math.min(canvas.height - 30, node.y))
    })

    // Draw
    ctx.clearRect(0, 0, canvas.width, canvas.height)

    // Draw edges
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

    // Draw nodes
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

  // Animation loop
  let frame = 0
  function animate() {
    tick()
    frame++
    if (frame < 200) {
      requestAnimationFrame(animate)
    }
  }
  animate()

  // Click handler
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

<style scoped>
.wiki-container {
  display: flex;
  height: 100%;
  background: var(--bg-primary);
}

.wiki-sidebar {
  width: 280px;
  background: var(--bg-secondary);
  border-right: 1px solid var(--border-color);
  display: flex;
  flex-direction: column;
  transition: width 0.2s;
}

.wiki-sidebar.collapsed {
  width: 50px;
}

.sidebar-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem;
  border-bottom: 1px solid var(--border-color);
}

.sidebar-header h2 {
  margin: 0;
  font-size: 1.25rem;
}

.sidebar-content {
  flex: 1;
  overflow-y: auto;
  padding: 1rem;
}

.search-box {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem;
  background: var(--bg-primary);
  border-radius: 6px;
  margin-bottom: 1rem;
}

.search-box input {
  flex: 1;
  border: none;
  background: transparent;
  outline: none;
  color: var(--text-primary);
}

.sidebar-actions {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 1rem;
}

.sidebar-actions .btn-primary {
  flex: 1;
}

.category-section {
  margin-bottom: 0.25rem;
}

.category-header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem;
  border-radius: 6px;
  cursor: pointer;
  transition: background 0.2s;
}

.category-header:hover {
  background: var(--bg-hover);
}

.category-header.active {
  background: var(--bg-active);
  border-left: 3px solid var(--category-color, var(--primary-color));
}

.category-header .count {
  margin-left: auto;
  font-size: 0.75rem;
  color: var(--text-secondary);
}

.add-category {
  display: block;
  margin: 0.5rem 0;
  font-size: 0.85rem;
}

.page-list, .recent-pages {
  margin-top: 1rem;
}

.page-list h4, .recent-pages h4 {
  font-size: 0.75rem;
  text-transform: uppercase;
  color: var(--text-secondary);
  margin-bottom: 0.5rem;
}

.page-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem;
  border-radius: 6px;
  cursor: pointer;
  transition: background 0.2s;
}

.page-item:hover {
  background: var(--bg-hover);
}

.page-item.active {
  background: var(--bg-active);
}

.page-item.small {
  padding: 0.25rem 0.5rem;
  font-size: 0.85rem;
}

.pin-icon {
  margin-left: auto;
  color: var(--primary-color);
  font-size: 0.75rem;
}

/* Main Content */
.wiki-main {
  flex: 1;
  overflow-y: auto;
  padding: 2rem;
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 100%;
  text-align: center;
  color: var(--text-secondary);
}

.empty-state i {
  font-size: 4rem;
  margin-bottom: 1rem;
  opacity: 0.5;
}

.page-content {
  max-width: 900px;
  margin: 0 auto;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid var(--border-color);
}

.page-title-input {
  font-size: 2rem;
  font-weight: bold;
  border: none;
  background: transparent;
  width: 100%;
  color: var(--text-primary);
  outline: none;
}

.page-meta {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-top: 0.5rem;
  font-size: 0.85rem;
  color: var(--text-secondary);
}

.category-badge {
  padding: 0.25rem 0.5rem;
  border-radius: 4px;
  color: white;
  font-size: 0.75rem;
}

.meta-item {
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.page-actions {
  display: flex;
  gap: 0.5rem;
}

.editor-options {
  display: flex;
  gap: 1rem;
  margin-bottom: 1rem;
  padding: 1rem;
  background: var(--bg-secondary);
  border-radius: 8px;
}

.editor-options select {
  padding: 0.5rem;
  border: 1px solid var(--border-color);
  border-radius: 4px;
  background: var(--bg-primary);
  color: var(--text-primary);
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
}

.content-area {
  min-height: 400px;
}

.markdown-editor {
  width: 100%;
  min-height: 400px;
  padding: 1rem;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  background: var(--bg-secondary);
  color: var(--text-primary);
  font-family: 'Monaco', 'Menlo', monospace;
  font-size: 0.9rem;
  line-height: 1.6;
  resize: vertical;
}

.markdown-content {
  line-height: 1.8;
}

.markdown-content :deep(h1),
.markdown-content :deep(h2),
.markdown-content :deep(h3) {
  margin-top: 1.5rem;
  margin-bottom: 0.5rem;
}

.markdown-content :deep(code) {
  background: var(--bg-secondary);
  padding: 0.125rem 0.25rem;
  border-radius: 4px;
  font-size: 0.9em;
}

.markdown-content :deep(pre) {
  background: var(--bg-secondary);
  padding: 1rem;
  border-radius: 8px;
  overflow-x: auto;
}

.markdown-content :deep(blockquote) {
  border-left: 4px solid var(--primary-color);
  margin: 1rem 0;
  padding-left: 1rem;
  color: var(--text-secondary);
}

.markdown-content :deep(.wiki-link) {
  color: var(--primary-color);
  cursor: pointer;
  text-decoration: none;
}

.markdown-content :deep(.wiki-link:hover) {
  text-decoration: underline;
}

.markdown-content :deep(.wiki-link.broken) {
  color: #ef4444;
  cursor: default;
}

.backlinks-section, .children-section {
  margin-top: 2rem;
  padding-top: 1rem;
  border-top: 1px solid var(--border-color);
}

.backlinks-section h4, .children-section h4 {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: var(--text-secondary);
  margin-bottom: 0.5rem;
}

.backlinks-list {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.backlink {
  padding: 0.25rem 0.75rem;
  background: var(--bg-secondary);
  border-radius: 999px;
  font-size: 0.85rem;
  cursor: pointer;
  transition: background 0.2s;
}

.backlink:hover {
  background: var(--bg-hover);
}

.children-list {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 0.5rem;
}

.child-page {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem;
  background: var(--bg-secondary);
  border-radius: 8px;
  cursor: pointer;
  transition: background 0.2s;
}

.child-page:hover {
  background: var(--bg-hover);
}

/* Dropdown */
.dropdown {
  position: relative;
}

.dropdown-menu {
  position: absolute;
  right: 0;
  top: 100%;
  min-width: 150px;
  background: var(--bg-secondary);
  border: 1px solid var(--border-color);
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.2);
  z-index: 100;
  display: none;
}

.dropdown:hover .dropdown-menu {
  display: block;
}

.dropdown-menu button {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  width: 100%;
  padding: 0.75rem 1rem;
  border: none;
  background: none;
  color: var(--text-primary);
  cursor: pointer;
  text-align: left;
}

.dropdown-menu button:hover {
  background: var(--bg-hover);
}

.dropdown-menu button.danger {
  color: #ef4444;
}

.dropdown-menu hr {
  margin: 0.25rem 0;
  border: none;
  border-top: 1px solid var(--border-color);
}

/* Modals */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0,0,0,0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal {
  background: var(--bg-secondary);
  border-radius: 12px;
  width: 100%;
  max-width: 500px;
  max-height: 80vh;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.graph-modal {
  max-width: 900px;
  height: 600px;
}

.history-modal {
  max-width: 600px;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 1.5rem;
  border-bottom: 1px solid var(--border-color);
}

.modal-header h3 {
  margin: 0;
}

.modal-body {
  flex: 1;
  padding: 1.5rem;
  overflow-y: auto;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  padding: 1rem 1.5rem;
  border-top: 1px solid var(--border-color);
}

.form-group {
  margin-bottom: 1rem;
}

.form-group label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
}

.form-group input,
.form-group textarea {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid var(--border-color);
  border-radius: 6px;
  background: var(--bg-primary);
  color: var(--text-primary);
}

.graph-container {
  width: 100%;
  height: 100%;
  background: var(--bg-primary);
  border-radius: 8px;
}

.history-list {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.history-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
  background: var(--bg-primary);
  border-radius: 8px;
}

.history-info {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.history-info .version {
  font-weight: bold;
  color: var(--primary-color);
}

.history-info .meta {
  font-size: 0.85rem;
  color: var(--text-secondary);
}

.history-info .note {
  font-size: 0.85rem;
  font-style: italic;
  color: var(--text-secondary);
}

.empty-history {
  text-align: center;
  color: var(--text-secondary);
  padding: 2rem;
}

/* Buttons */
.btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 1rem;
  border: none;
  border-radius: 6px;
  font-size: 0.9rem;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-primary {
  background: var(--primary-color);
  color: white;
}

.btn-primary:hover {
  opacity: 0.9;
}

.btn-secondary {
  background: var(--bg-hover);
  color: var(--text-primary);
}

.btn-secondary:hover {
  background: var(--bg-active);
}

.btn-sm {
  padding: 0.375rem 0.75rem;
  font-size: 0.85rem;
}

.btn-icon {
  padding: 0.5rem;
  background: none;
  border: none;
  color: var(--text-secondary);
  cursor: pointer;
  border-radius: 6px;
}

.btn-icon:hover {
  background: var(--bg-hover);
  color: var(--text-primary);
}

.btn-link {
  background: none;
  border: none;
  color: var(--primary-color);
  cursor: pointer;
  padding: 0.5rem;
  font-size: 0.85rem;
}

.btn-link:hover {
  text-decoration: underline;
}
</style>
