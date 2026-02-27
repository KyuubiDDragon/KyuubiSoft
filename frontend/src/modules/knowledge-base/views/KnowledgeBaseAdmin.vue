<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import {
  PlusIcon,
  PencilIcon,
  TrashIcon,
  FolderIcon,
  FolderOpenIcon,
  ChevronRightIcon,
  ChevronDownIcon,
  EyeIcon,
  EyeSlashIcon,
  MagnifyingGlassIcon,
  XMarkIcon,
  DocumentTextIcon,
  ChevronLeftIcon,
} from '@heroicons/vue/24/outline'
import { useKnowledgeBaseStore } from '@/modules/knowledge-base/stores/knowledgeBaseStore'

const store = useKnowledgeBaseStore()

// Local state
const selectedCategoryId = ref(null)
const expandedCategories = ref(new Set())
const showCategoryModal = ref(false)
const showArticleModal = ref(false)
const showDeleteConfirm = ref(false)
const deleteTarget = ref({ type: '', id: '', name: '' })
const articleFilter = ref('')
const articlePublishedFilter = ref('')

// Category form
const categoryForm = ref({
  id: null,
  name: '',
  slug: '',
  description: '',
  parent_id: null,
  icon: '',
  sort_order: 0,
  is_published: true,
})

// Article form
const articleForm = ref({
  id: null,
  title: '',
  slug: '',
  category_id: null,
  content: '',
  excerpt: '',
  tags: [],
  is_published: false,
})

const tagInput = ref('')

// Computed
const filteredArticles = computed(() => store.articles)

const flatCategories = computed(() => {
  const flat = []
  function flatten(cats, depth = 0) {
    for (const cat of cats) {
      flat.push({ ...cat, depth })
      if (cat.children?.length) {
        flatten(cat.children, depth + 1)
      }
    }
  }
  flatten(store.categories)
  return flat
})

const paginationRange = computed(() => {
  const current = store.pagination.page
  const total = store.totalPages
  const range = []
  const delta = 2

  const start = Math.max(2, current - delta)
  const end = Math.min(total - 1, current + delta)

  range.push(1)
  if (start > 2) range.push('...')
  for (let i = start; i <= end; i++) range.push(i)
  if (end < total - 1) range.push('...')
  if (total > 1) range.push(total)

  return range
})

// Methods
function toggleCategory(id) {
  if (expandedCategories.value.has(id)) {
    expandedCategories.value.delete(id)
  } else {
    expandedCategories.value.add(id)
  }
}

function selectCategory(id) {
  selectedCategoryId.value = id
  store.pagination.page = 1
  loadArticles()
}

function loadArticles() {
  const filters = {}
  if (selectedCategoryId.value) filters.category_id = selectedCategoryId.value
  if (articleFilter.value) filters.search = articleFilter.value
  if (articlePublishedFilter.value !== '') filters.is_published = articlePublishedFilter.value
  store.fetchArticles(filters)
}

function openCreateCategory(parentId = null) {
  categoryForm.value = {
    id: null,
    name: '',
    slug: '',
    description: '',
    parent_id: parentId,
    icon: '',
    sort_order: 0,
    is_published: true,
  }
  showCategoryModal.value = true
}

function openEditCategory(cat) {
  categoryForm.value = {
    id: cat.id,
    name: cat.name,
    slug: cat.slug,
    description: cat.description || '',
    parent_id: cat.parent_id,
    icon: cat.icon || '',
    sort_order: cat.sort_order || 0,
    is_published: !!cat.is_published,
  }
  showCategoryModal.value = true
}

async function saveCategory() {
  const data = { ...categoryForm.value }
  if (!data.slug) {
    data.slug = generateSlug(data.name)
  }

  if (data.id) {
    await store.updateCategory(data.id, data)
  } else {
    await store.createCategory(data)
  }
  showCategoryModal.value = false
}

function openCreateArticle() {
  articleForm.value = {
    id: null,
    title: '',
    slug: '',
    category_id: selectedCategoryId.value,
    content: '',
    excerpt: '',
    tags: [],
    is_published: false,
  }
  tagInput.value = ''
  showArticleModal.value = true
}

async function openEditArticle(article) {
  await store.fetchArticle(article.id)
  if (store.currentArticle) {
    articleForm.value = {
      id: store.currentArticle.id,
      title: store.currentArticle.title,
      slug: store.currentArticle.slug,
      category_id: store.currentArticle.category_id,
      content: store.currentArticle.content || '',
      excerpt: store.currentArticle.excerpt || '',
      tags: store.currentArticle.tags || [],
      is_published: !!store.currentArticle.is_published,
    }
    tagInput.value = ''
    showArticleModal.value = true
  }
}

async function saveArticle() {
  const data = { ...articleForm.value }
  if (!data.slug) {
    data.slug = generateSlug(data.title)
  }

  if (data.id) {
    await store.updateArticle(data.id, data)
  } else {
    await store.createArticle(data)
  }
  showArticleModal.value = false
  loadArticles()
}

function confirmDelete(type, id, name) {
  deleteTarget.value = { type, id, name }
  showDeleteConfirm.value = true
}

async function executeDelete() {
  if (deleteTarget.value.type === 'category') {
    await store.deleteCategory(deleteTarget.value.id)
    if (selectedCategoryId.value === deleteTarget.value.id) {
      selectedCategoryId.value = null
    }
  } else {
    await store.deleteArticle(deleteTarget.value.id)
    loadArticles()
  }
  showDeleteConfirm.value = false
}

function addTag() {
  const tag = tagInput.value.trim()
  if (tag && !articleForm.value.tags.includes(tag)) {
    articleForm.value.tags.push(tag)
  }
  tagInput.value = ''
}

function removeTag(index) {
  articleForm.value.tags.splice(index, 1)
}

function generateSlug(text) {
  return text
    .toLowerCase()
    .replace(/[äÄ]/g, 'ae')
    .replace(/[öÖ]/g, 'oe')
    .replace(/[üÜ]/g, 'ue')
    .replace(/ß/g, 'ss')
    .replace(/[^a-z0-9-]/g, '-')
    .replace(/-+/g, '-')
    .replace(/^-|-$/g, '')
    || 'untitled'
}

function goToPage(page) {
  if (page === '...' || page < 1 || page > store.totalPages) return
  store.setPage(page)
  loadArticles()
}

// Lifecycle
onMounted(async () => {
  await store.fetchCategories()
  loadArticles()
})

watch(articlePublishedFilter, () => {
  store.pagination.page = 1
  loadArticles()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-white">Wissensbasis</h1>
        <p class="text-gray-400 mt-1">Kategorien und Artikel verwalten</p>
      </div>
    </div>

    <!-- Two-Panel Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

      <!-- Left Sidebar: Category Tree -->
      <div class="lg:col-span-1">
        <div class="card-glass p-4">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-semibold text-gray-300 uppercase tracking-wider">Kategorien</h2>
            <button @click="openCreateCategory()" class="btn-icon-sm" title="Kategorie erstellen">
              <PlusIcon class="w-4 h-4" />
            </button>
          </div>

          <!-- All Articles button -->
          <button
            @click="selectedCategoryId = null; store.pagination.page = 1; loadArticles()"
            class="w-full text-left px-3 py-2 rounded-lg text-sm transition-colors mb-1"
            :class="selectedCategoryId === null
              ? 'bg-primary-600/20 text-primary-300 border border-primary-500/30'
              : 'text-gray-400 hover:bg-white/[0.04] hover:text-white'"
          >
            <div class="flex items-center gap-2">
              <DocumentTextIcon class="w-4 h-4" />
              <span>Alle Artikel</span>
            </div>
          </button>

          <!-- Category Tree -->
          <div class="space-y-0.5 mt-1">
            <template v-for="cat in store.categories" :key="cat.id">
              <div>
                <div
                  class="flex items-center gap-1 px-3 py-2 rounded-lg text-sm cursor-pointer transition-colors group"
                  :class="selectedCategoryId === cat.id
                    ? 'bg-primary-600/20 text-primary-300 border border-primary-500/30'
                    : 'text-gray-400 hover:bg-white/[0.04] hover:text-white'"
                  @click="selectCategory(cat.id)"
                >
                  <!-- Expand toggle -->
                  <button
                    v-if="cat.children?.length"
                    @click.stop="toggleCategory(cat.id)"
                    class="p-0.5 rounded hover:bg-white/10"
                  >
                    <ChevronDownIcon v-if="expandedCategories.has(cat.id)" class="w-3.5 h-3.5" />
                    <ChevronRightIcon v-else class="w-3.5 h-3.5" />
                  </button>
                  <span v-else class="w-4"></span>

                  <FolderOpenIcon v-if="expandedCategories.has(cat.id)" class="w-4 h-4 text-amber-400 shrink-0" />
                  <FolderIcon v-else class="w-4 h-4 text-gray-500 shrink-0" />

                  <span class="truncate flex-1">{{ cat.name }}</span>

                  <EyeSlashIcon v-if="!cat.is_published" class="w-3.5 h-3.5 text-gray-600" title="Unveröffentlicht" />

                  <!-- Actions -->
                  <div class="hidden group-hover:flex items-center gap-0.5">
                    <button @click.stop="openCreateCategory(cat.id)" class="p-0.5 rounded hover:bg-white/10" title="Unterkategorie">
                      <PlusIcon class="w-3.5 h-3.5" />
                    </button>
                    <button @click.stop="openEditCategory(cat)" class="p-0.5 rounded hover:bg-white/10" title="Bearbeiten">
                      <PencilIcon class="w-3.5 h-3.5" />
                    </button>
                    <button @click.stop="confirmDelete('category', cat.id, cat.name)" class="p-0.5 rounded hover:bg-red-500/20 text-red-400" title="Löschen">
                      <TrashIcon class="w-3.5 h-3.5" />
                    </button>
                  </div>
                </div>

                <!-- Children -->
                <div v-if="cat.children?.length && expandedCategories.has(cat.id)" class="ml-4">
                  <div
                    v-for="child in cat.children"
                    :key="child.id"
                    class="flex items-center gap-1 px-3 py-1.5 rounded-lg text-sm cursor-pointer transition-colors group"
                    :class="selectedCategoryId === child.id
                      ? 'bg-primary-600/20 text-primary-300 border border-primary-500/30'
                      : 'text-gray-500 hover:bg-white/[0.04] hover:text-white'"
                    @click="selectCategory(child.id)"
                  >
                    <FolderIcon class="w-3.5 h-3.5 text-gray-600 shrink-0" />
                    <span class="truncate flex-1">{{ child.name }}</span>
                    <EyeSlashIcon v-if="!child.is_published" class="w-3 h-3 text-gray-600" />
                    <div class="hidden group-hover:flex items-center gap-0.5">
                      <button @click.stop="openEditCategory(child)" class="p-0.5 rounded hover:bg-white/10">
                        <PencilIcon class="w-3 h-3" />
                      </button>
                      <button @click.stop="confirmDelete('category', child.id, child.name)" class="p-0.5 rounded hover:bg-red-500/20 text-red-400">
                        <TrashIcon class="w-3 h-3" />
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </template>
          </div>

          <div v-if="store.categories.length === 0 && !store.loading" class="text-center py-6 text-gray-600 text-sm">
            Keine Kategorien vorhanden
          </div>
        </div>
      </div>

      <!-- Right Content: Articles -->
      <div class="lg:col-span-3">
        <!-- Filters & Actions -->
        <div class="card-glass p-4 mb-4">
          <div class="flex flex-wrap items-center gap-3">
            <div class="relative flex-1 min-w-[200px]">
              <MagnifyingGlassIcon class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-500" />
              <input
                v-model="articleFilter"
                @keyup.enter="store.pagination.page = 1; loadArticles()"
                type="text"
                placeholder="Artikel suchen..."
                class="input pl-9 w-full"
              />
            </div>

            <select v-model="articlePublishedFilter" class="select w-auto min-w-[140px]">
              <option value="">Alle Status</option>
              <option value="1">Veröffentlicht</option>
              <option value="0">Entwurf</option>
            </select>

            <button @click="store.pagination.page = 1; loadArticles()" class="btn-secondary">
              Suchen
            </button>

            <button @click="openCreateArticle()" class="btn-primary ml-auto">
              <PlusIcon class="w-5 h-5 mr-1" />
              Artikel erstellen
            </button>
          </div>
        </div>

        <!-- Loading -->
        <div v-if="store.loading" class="flex justify-center py-12">
          <div class="w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full animate-spin"></div>
        </div>

        <!-- Article Cards -->
        <div v-else class="space-y-3">
          <div
            v-for="article in filteredArticles"
            :key="article.id"
            class="card-glass p-4 hover:bg-white/[0.04] transition-colors cursor-pointer group"
            @click="openEditArticle(article)"
          >
            <div class="flex items-start justify-between gap-4">
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-1">
                  <h3 class="text-white font-medium truncate">{{ article.title }}</h3>
                  <span
                    :class="article.is_published
                      ? 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30'
                      : 'bg-gray-500/15 text-gray-400 border border-gray-500/30'"
                    class="text-xs px-2 py-0.5 rounded-full whitespace-nowrap"
                  >
                    {{ article.is_published ? 'Veröffentlicht' : 'Entwurf' }}
                  </span>
                </div>
                <p v-if="article.excerpt" class="text-gray-500 text-sm line-clamp-2 mb-2">{{ article.excerpt }}</p>
                <div class="flex items-center gap-4 text-xs text-gray-600">
                  <span v-if="article.category_name" class="flex items-center gap-1">
                    <FolderIcon class="w-3 h-3" />
                    {{ article.category_name }}
                  </span>
                  <span class="flex items-center gap-1">
                    <EyeIcon class="w-3 h-3" />
                    {{ article.view_count }} Aufrufe
                  </span>
                  <span class="text-emerald-500">{{ article.helpful_count }} hilfreich</span>
                  <span class="text-red-400">{{ article.not_helpful_count }} nicht hilfreich</span>
                </div>
              </div>

              <div class="hidden group-hover:flex items-center gap-1 shrink-0">
                <button
                  @click.stop="openEditArticle(article)"
                  class="p-1.5 rounded-lg hover:bg-white/10 text-gray-400 hover:text-white"
                  title="Bearbeiten"
                >
                  <PencilIcon class="w-4 h-4" />
                </button>
                <button
                  @click.stop="confirmDelete('article', article.id, article.title)"
                  class="p-1.5 rounded-lg hover:bg-red-500/20 text-gray-400 hover:text-red-400"
                  title="Löschen"
                >
                  <TrashIcon class="w-4 h-4" />
                </button>
              </div>
            </div>
          </div>

          <div v-if="filteredArticles.length === 0" class="card-glass p-12 text-center text-gray-500">
            Keine Artikel gefunden
          </div>
        </div>

        <!-- Pagination -->
        <div v-if="store.totalPages > 1" class="flex items-center justify-between mt-4">
          <p class="text-sm text-gray-500">{{ store.pagination.total }} Artikel gesamt</p>
          <div class="flex items-center gap-1">
            <button
              @click="goToPage(store.pagination.page - 1)"
              :disabled="store.pagination.page <= 1"
              class="btn-icon-sm"
            >
              <ChevronLeftIcon class="w-4 h-4" />
            </button>
            <button
              v-for="(page, idx) in paginationRange"
              :key="idx"
              @click="goToPage(page)"
              class="min-w-[2rem] h-8 px-2 rounded-lg text-sm font-medium transition-colors"
              :class="page === store.pagination.page
                ? 'bg-primary-600 text-white'
                : page === '...'
                  ? 'text-gray-500 cursor-default'
                  : 'text-gray-400 hover:bg-white/[0.06] hover:text-white'"
            >
              {{ page }}
            </button>
            <button
              @click="goToPage(store.pagination.page + 1)"
              :disabled="store.pagination.page >= store.totalPages"
              class="btn-icon-sm"
            >
              <ChevronRightIcon class="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Category Modal -->
    <Teleport to="body">
      <div v-if="showCategoryModal" class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showCategoryModal = false"></div>
        <div class="relative card-glass p-6 w-full max-w-lg mx-4">
          <div class="flex items-center justify-between mb-6">
            <h2 class="text-lg font-semibold text-white">
              {{ categoryForm.id ? 'Kategorie bearbeiten' : 'Neue Kategorie' }}
            </h2>
            <button @click="showCategoryModal = false" class="btn-icon-sm">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <div class="space-y-4">
            <div>
              <label class="block text-sm text-gray-400 mb-1">Name *</label>
              <input v-model="categoryForm.name" type="text" class="input w-full" placeholder="Kategorie-Name" />
            </div>

            <div>
              <label class="block text-sm text-gray-400 mb-1">Slug</label>
              <input v-model="categoryForm.slug" type="text" class="input w-full" placeholder="Wird automatisch generiert" />
            </div>

            <div>
              <label class="block text-sm text-gray-400 mb-1">Beschreibung</label>
              <textarea v-model="categoryForm.description" class="input w-full" rows="3" placeholder="Beschreibung..."></textarea>
            </div>

            <div>
              <label class="block text-sm text-gray-400 mb-1">Übergeordnete Kategorie</label>
              <select v-model="categoryForm.parent_id" class="select w-full">
                <option :value="null">Keine (Wurzelebene)</option>
                <option
                  v-for="cat in flatCategories"
                  :key="cat.id"
                  :value="cat.id"
                  :disabled="cat.id === categoryForm.id"
                >
                  {{ '─'.repeat(cat.depth) }} {{ cat.name }}
                </option>
              </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm text-gray-400 mb-1">Icon</label>
                <input v-model="categoryForm.icon" type="text" class="input w-full" placeholder="z.B. folder" />
              </div>
              <div>
                <label class="block text-sm text-gray-400 mb-1">Sortierung</label>
                <input v-model.number="categoryForm.sort_order" type="number" class="input w-full" />
              </div>
            </div>

            <div class="flex items-center gap-2">
              <input id="cat-published" v-model="categoryForm.is_published" type="checkbox" class="rounded bg-white/5 border-white/10 text-primary-500" />
              <label for="cat-published" class="text-sm text-gray-300">Veröffentlicht</label>
            </div>
          </div>

          <div class="flex justify-end gap-3 mt-6">
            <button @click="showCategoryModal = false" class="btn-secondary">Abbrechen</button>
            <button @click="saveCategory" class="btn-primary" :disabled="!categoryForm.name.trim()">Speichern</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Article Modal -->
    <Teleport to="body">
      <div v-if="showArticleModal" class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showArticleModal = false"></div>
        <div class="relative card-glass p-6 w-full max-w-3xl mx-4 max-h-[90vh] overflow-y-auto">
          <div class="flex items-center justify-between mb-6">
            <h2 class="text-lg font-semibold text-white">
              {{ articleForm.id ? 'Artikel bearbeiten' : 'Neuer Artikel' }}
            </h2>
            <button @click="showArticleModal = false" class="btn-icon-sm">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <div class="space-y-4">
            <div>
              <label class="block text-sm text-gray-400 mb-1">Titel *</label>
              <input v-model="articleForm.title" type="text" class="input w-full" placeholder="Artikeltitel" />
            </div>

            <div>
              <label class="block text-sm text-gray-400 mb-1">Slug</label>
              <input v-model="articleForm.slug" type="text" class="input w-full" placeholder="Wird automatisch generiert" />
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm text-gray-400 mb-1">Kategorie</label>
                <select v-model="articleForm.category_id" class="select w-full">
                  <option :value="null">Keine Kategorie</option>
                  <option v-for="cat in flatCategories" :key="cat.id" :value="cat.id">
                    {{ '─'.repeat(cat.depth) }} {{ cat.name }}
                  </option>
                </select>
              </div>
              <div class="flex items-center pt-6">
                <input id="art-published" v-model="articleForm.is_published" type="checkbox" class="rounded bg-white/5 border-white/10 text-primary-500" />
                <label for="art-published" class="text-sm text-gray-300 ml-2">Veröffentlicht</label>
              </div>
            </div>

            <div>
              <label class="block text-sm text-gray-400 mb-1">Auszug</label>
              <textarea v-model="articleForm.excerpt" class="input w-full" rows="2" placeholder="Kurze Zusammenfassung..."></textarea>
            </div>

            <div>
              <label class="block text-sm text-gray-400 mb-1">Inhalt</label>
              <textarea
                v-model="articleForm.content"
                class="input w-full font-mono text-sm"
                rows="12"
                placeholder="Artikelinhalt (HTML oder Markdown)..."
              ></textarea>
            </div>

            <div>
              <label class="block text-sm text-gray-400 mb-1">Tags</label>
              <div class="flex flex-wrap gap-2 mb-2">
                <span
                  v-for="(tag, idx) in articleForm.tags"
                  :key="idx"
                  class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs bg-primary-500/15 text-primary-300 border border-primary-500/30"
                >
                  {{ tag }}
                  <button @click="removeTag(idx)" class="hover:text-red-400">
                    <XMarkIcon class="w-3 h-3" />
                  </button>
                </span>
              </div>
              <div class="flex gap-2">
                <input
                  v-model="tagInput"
                  type="text"
                  class="input flex-1"
                  placeholder="Tag hinzufügen..."
                  @keyup.enter="addTag"
                />
                <button @click="addTag" class="btn-secondary">Hinzufügen</button>
              </div>
            </div>
          </div>

          <div class="flex justify-end gap-3 mt-6">
            <button @click="showArticleModal = false" class="btn-secondary">Abbrechen</button>
            <button @click="saveArticle" class="btn-primary" :disabled="!articleForm.title.trim()">Speichern</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Delete Confirmation -->
    <Teleport to="body">
      <div v-if="showDeleteConfirm" class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showDeleteConfirm = false"></div>
        <div class="relative card-glass p-6 w-full max-w-md mx-4">
          <h2 class="text-lg font-semibold text-white mb-4">Löschen bestätigen</h2>
          <p class="text-gray-400 mb-6">
            Möchten Sie
            <span class="text-white font-medium">{{ deleteTarget.name }}</span>
            wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.
          </p>
          <div class="flex justify-end gap-3">
            <button @click="showDeleteConfirm = false" class="btn-secondary">Abbrechen</button>
            <button @click="executeDelete" class="btn-primary bg-red-600 hover:bg-red-700">Löschen</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
