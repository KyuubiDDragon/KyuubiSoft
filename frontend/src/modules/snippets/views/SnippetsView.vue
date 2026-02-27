<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/core/api/axios'
import { useUiStore } from '@/stores/ui'
import { useProjectStore } from '@/stores/project'
import { useToast } from '@/composables/useToast'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
import {
  PlusIcon,
  CodeBracketIcon,
  MagnifyingGlassIcon,
  TrashIcon,
  PencilIcon,
  StarIcon,
  ClipboardIcon,
  CheckIcon,
  XMarkIcon,
  CommandLineIcon,
  FolderIcon,
} from '@heroicons/vue/24/outline'
import { StarIcon as StarIconSolid } from '@heroicons/vue/24/solid'

const route = useRoute()
const uiStore = useUiStore()
const projectStore = useProjectStore()
const toast = useToast()
const { confirm } = useConfirmDialog()

// Watch for project changes
watch(() => projectStore.selectedProjectId, () => {
  loadSnippets()
})

// State
const snippets = ref([])
const categories = ref([])
const languages = ref([])
const isLoading = ref(true)
const searchQuery = ref('')
const selectedLanguage = ref('')
const selectedCategory = ref('')
const showModal = ref(false)
const editingSnippet = ref(null)
const copiedId = ref('')

// Available languages
const languageOptions = [
  { value: 'text', label: 'Text' },
  { value: 'bash', label: 'Bash/Shell' },
  { value: 'javascript', label: 'JavaScript' },
  { value: 'typescript', label: 'TypeScript' },
  { value: 'python', label: 'Python' },
  { value: 'php', label: 'PHP' },
  { value: 'sql', label: 'SQL' },
  { value: 'json', label: 'JSON' },
  { value: 'yaml', label: 'YAML' },
  { value: 'html', label: 'HTML' },
  { value: 'css', label: 'CSS' },
  { value: 'markdown', label: 'Markdown' },
  { value: 'docker', label: 'Dockerfile' },
  { value: 'nginx', label: 'Nginx' },
  { value: 'git', label: 'Git' },
]

// Form
const form = reactive({
  title: '',
  description: '',
  content: '',
  language: 'bash',
  category: '',
  tags: [],
  is_favorite: false,
})

const tagInput = ref('')

// Computed
const filteredSnippets = computed(() => {
  let result = snippets.value

  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    result = result.filter(s =>
      s.title.toLowerCase().includes(query) ||
      s.content.toLowerCase().includes(query) ||
      s.description?.toLowerCase().includes(query)
    )
  }

  if (selectedLanguage.value) {
    result = result.filter(s => s.language === selectedLanguage.value)
  }

  if (selectedCategory.value) {
    result = result.filter(s => s.category === selectedCategory.value)
  }

  return result
})

// API Calls
onMounted(async () => {
  await loadSnippets()

  // Check for ?open=id query parameter to auto-open a snippet
  const openId = route.query.open
  if (openId) {
    const snippet = snippets.value.find(s => s.id === openId)
    if (snippet) {
      openEditModal(snippet)
    }
  }
})

async function loadSnippets() {
  isLoading.value = true
  try {
    const params = projectStore.selectedProjectId
      ? { project_id: projectStore.selectedProjectId }
      : {}
    const [snippetsRes, categoriesRes, languagesRes] = await Promise.all([
      api.get('/api/v1/snippets', { params }),
      api.get('/api/v1/snippets/categories'),
      api.get('/api/v1/snippets/languages'),
    ])
    snippets.value = snippetsRes.data.data?.items || []
    categories.value = categoriesRes.data.data || []
    languages.value = languagesRes.data.data || []
  } catch (error) {
    uiStore.showError('Fehler beim Laden der Snippets')
  } finally {
    isLoading.value = false
  }
}

async function saveSnippet() {
  try {
    const data = { ...form }

    if (editingSnippet.value) {
      await api.put(`/api/v1/snippets/${editingSnippet.value.id}`, data)
      uiStore.showSuccess('Snippet aktualisiert')
    } else {
      const response = await api.post('/api/v1/snippets', data)
      const newSnippet = response.data.data

      // Link to selected project if one is active
      if (projectStore.selectedProjectId) {
        await projectStore.linkToSelectedProject('snippet', newSnippet.id)
      }

      uiStore.showSuccess('Snippet erstellt')
    }

    await loadSnippets()
    closeModal()
  } catch (error) {
    uiStore.showError('Fehler beim Speichern')
  }
}

async function deleteSnippet(snippet) {
  if (!await confirm({ message: `Snippet "${snippet.title}" wirklich löschen?`, type: 'danger', confirmText: 'Löschen' })) return

  try {
    await api.delete(`/api/v1/snippets/${snippet.id}`)
    snippets.value = snippets.value.filter(s => s.id !== snippet.id)
    uiStore.showSuccess('Snippet gelöscht')
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
  }
}

async function toggleFavorite(snippet) {
  try {
    await api.put(`/api/v1/snippets/${snippet.id}`, {
      is_favorite: !snippet.is_favorite,
    })
    snippet.is_favorite = !snippet.is_favorite
  } catch (error) {
    uiStore.showError('Fehler beim Aktualisieren')
  }
}

async function copySnippet(snippet) {
  try {
    // Call API to track usage
    await api.post(`/api/v1/snippets/${snippet.id}/copy`)

    // Copy to clipboard
    await navigator.clipboard.writeText(snippet.content)

    copiedId.value = snippet.id
    snippet.use_count = (snippet.use_count || 0) + 1

    setTimeout(() => {
      copiedId.value = ''
    }, 2000)
  } catch (error) {
    uiStore.showError('Kopieren fehlgeschlagen')
  }
}

// Modal helpers
function openCreateModal() {
  editingSnippet.value = null
  Object.assign(form, {
    title: '',
    description: '',
    content: '',
    language: 'bash',
    category: '',
    tags: [],
    is_favorite: false,
  })
  showModal.value = true
}

function openEditModal(snippet) {
  editingSnippet.value = snippet
  Object.assign(form, {
    title: snippet.title,
    description: snippet.description || '',
    content: snippet.content,
    language: snippet.language || 'text',
    category: snippet.category || '',
    tags: snippet.tags || [],
    is_favorite: snippet.is_favorite,
  })
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  editingSnippet.value = null
}

function addTag() {
  const tag = tagInput.value.trim()
  if (tag && !form.tags.includes(tag)) {
    form.tags.push(tag)
  }
  tagInput.value = ''
}

function removeTag(tag) {
  form.tags = form.tags.filter(t => t !== tag)
}

function getLanguageLabel(lang) {
  return languageOptions.find(l => l.value === lang)?.label || lang
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-white">Snippets</h1>
        <p class="text-gray-400 mt-1">Speichere häufig genutzte Befehle und Code</p>
      </div>
      <button @click="openCreateModal" class="btn-primary">
        <PlusIcon class="w-5 h-5 mr-2" />
        Neues Snippet
      </button>
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
      <select v-model="selectedLanguage" class="input w-full sm:w-48">
        <option value="">Alle Sprachen</option>
        <option v-for="lang in languageOptions" :key="lang.value" :value="lang.value">
          {{ lang.label }}
        </option>
      </select>
      <select v-model="selectedCategory" class="input w-full sm:w-48">
        <option value="">Alle Kategorien</option>
        <option v-for="cat in categories" :key="cat.category" :value="cat.category">
          {{ cat.category }} ({{ cat.count }})
        </option>
      </select>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="flex justify-center py-12">
      <div class="w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full animate-spin"></div>
    </div>

    <!-- Empty state -->
    <div v-else-if="filteredSnippets.length === 0" class="card p-12 text-center">
      <CodeBracketIcon class="w-16 h-16 mx-auto text-gray-600 mb-4" />
      <h3 class="text-lg font-medium text-white mb-2">
        {{ searchQuery || selectedLanguage || selectedCategory ? 'Keine Ergebnisse' : 'Keine Snippets' }}
      </h3>
      <p class="text-gray-400 mb-6">
        {{ searchQuery || selectedLanguage || selectedCategory
          ? 'Versuche andere Suchkriterien'
          : 'Erstelle dein erstes Snippet'
        }}
      </p>
      <button v-if="!searchQuery && !selectedLanguage && !selectedCategory" @click="openCreateModal" class="btn-primary">
        <PlusIcon class="w-5 h-5 mr-2" />
        Snippet erstellen
      </button>
    </div>

    <!-- Snippets list -->
    <div v-else class="space-y-3">
      <div
        v-for="snippet in filteredSnippets"
        :key="snippet.id"
        class="card-hover p-4 group"
      >
        <div class="flex items-start gap-4">
          <!-- Icon -->
          <div class="flex-shrink-0 w-10 h-10 bg-white/[0.08] rounded-lg flex items-center justify-center">
            <CommandLineIcon v-if="snippet.language === 'bash'" class="w-5 h-5 text-green-400" />
            <CodeBracketIcon v-else class="w-5 h-5 text-primary-400" />
          </div>

          <!-- Content -->
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 mb-1">
              <h3 class="font-medium text-white">{{ snippet.title }}</h3>
              <span class="px-2 py-0.5 text-xs rounded bg-white/[0.08] text-gray-300">
                {{ getLanguageLabel(snippet.language) }}
              </span>
              <span v-if="snippet.category" class="px-2 py-0.5 text-xs rounded bg-primary-600/20 text-primary-400">
                {{ snippet.category }}
              </span>
            </div>

            <p v-if="snippet.description" class="text-sm text-gray-400 mb-2">
              {{ snippet.description }}
            </p>

            <!-- Code preview -->
            <pre class="bg-white/[0.02] rounded-lg p-3 text-sm text-gray-300 font-mono overflow-x-auto max-h-24">{{ snippet.content }}</pre>

            <!-- Tags -->
            <div v-if="snippet.tags?.length" class="flex flex-wrap gap-1 mt-2">
              <span
                v-for="tag in snippet.tags"
                :key="tag"
                class="px-2 py-0.5 text-xs rounded-full bg-white/[0.08] text-gray-400"
              >
                #{{ tag }}
              </span>
            </div>

            <!-- Meta -->
            <div class="flex items-center gap-4 mt-2 text-xs text-gray-500">
              <span>{{ snippet.use_count || 0 }}x verwendet</span>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
            <button
              @click="toggleFavorite(snippet)"
              class="p-2 rounded hover:bg-white/[0.04] transition-colors"
            >
              <StarIconSolid v-if="snippet.is_favorite" class="w-5 h-5 text-yellow-400" />
              <StarIcon v-else class="w-5 h-5 text-gray-500 hover:text-yellow-400" />
            </button>
            <button
              @click="copySnippet(snippet)"
              class="p-2 rounded hover:bg-white/[0.04] transition-colors"
              :class="copiedId === snippet.id ? 'text-green-400' : 'text-gray-400 hover:text-white'"
            >
              <CheckIcon v-if="copiedId === snippet.id" class="w-5 h-5" />
              <ClipboardIcon v-else class="w-5 h-5" />
            </button>
            <button
              @click="openEditModal(snippet)"
              class="p-2 text-gray-400 hover:text-white rounded hover:bg-white/[0.04] transition-colors"
            >
              <PencilIcon class="w-5 h-5" />
            </button>
            <button
              @click="deleteSnippet(snippet)"
              class="p-2 text-red-400 hover:text-red-300 rounded hover:bg-red-400/10 transition-colors"
            >
              <TrashIcon class="w-5 h-5" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <Teleport to="body">
      <div
        v-if="showModal"
        class="fixed inset-0 bg-black/60 backdrop-blur-md flex items-center justify-center z-50 p-4"
        
      >
        <div class="modal w-full max-w-2xl max-h-[90vh] overflow-y-auto">
          <div class="p-6 border-b border-white/[0.06] flex items-center justify-between">
            <h2 class="text-xl font-bold text-white">
              {{ editingSnippet ? 'Snippet bearbeiten' : 'Neues Snippet' }}
            </h2>
            <button @click="closeModal" class="text-gray-400 hover:text-white">
              <XMarkIcon class="w-6 h-6" />
            </button>
          </div>

          <form @submit.prevent="saveSnippet" class="p-6 space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div class="col-span-2 sm:col-span-1">
                <label class="label">Titel *</label>
                <input v-model="form.title" type="text" class="input" required placeholder="SSH Login Production" />
              </div>

              <div class="col-span-2 sm:col-span-1">
                <label class="label">Sprache</label>
                <select v-model="form.language" class="input">
                  <option v-for="lang in languageOptions" :key="lang.value" :value="lang.value">
                    {{ lang.label }}
                  </option>
                </select>
              </div>

              <div class="col-span-2 sm:col-span-1">
                <label class="label">Kategorie</label>
                <input
                  v-model="form.category"
                  type="text"
                  class="input"
                  placeholder="SSH, Git, Docker..."
                  list="category-options"
                />
                <datalist id="category-options">
                  <option v-for="cat in categories" :key="cat.category" :value="cat.category" />
                </datalist>
              </div>

              <div class="col-span-2 sm:col-span-1">
                <label class="label">Tags</label>
                <div class="flex gap-2">
                  <input
                    v-model="tagInput"
                    @keydown.enter.prevent="addTag"
                    type="text"
                    class="input flex-1"
                    placeholder="Tag hinzufügen..."
                  />
                  <button type="button" @click="addTag" class="btn-secondary">
                    <PlusIcon class="w-5 h-5" />
                  </button>
                </div>
                <div v-if="form.tags.length" class="flex flex-wrap gap-1 mt-2">
                  <span
                    v-for="tag in form.tags"
                    :key="tag"
                    class="px-2 py-1 text-xs rounded-full bg-white/[0.08] text-gray-300 flex items-center gap-1"
                  >
                    #{{ tag }}
                    <button type="button" @click="removeTag(tag)" class="hover:text-red-400">
                      <XMarkIcon class="w-3 h-3" />
                    </button>
                  </span>
                </div>
              </div>

              <div class="col-span-2">
                <label class="label">Beschreibung</label>
                <input v-model="form.description" type="text" class="input" placeholder="Optional" />
              </div>

              <div class="col-span-2">
                <label class="label">Code/Befehl *</label>
                <textarea
                  v-model="form.content"
                  class="input font-mono text-sm"
                  rows="8"
                  required
                  placeholder="ssh user@host -p 22"
                ></textarea>
              </div>

              <div class="col-span-2">
                <label class="flex items-center gap-2 cursor-pointer">
                  <input v-model="form.is_favorite" type="checkbox" class="checkbox" />
                  <span class="text-gray-300">Als Favorit markieren</span>
                </label>
              </div>
            </div>

            <div class="flex gap-3 pt-4">
              <button type="button" @click="closeModal" class="btn-secondary flex-1">
                Abbrechen
              </button>
              <button type="submit" class="btn-primary flex-1">
                {{ editingSnippet ? 'Speichern' : 'Erstellen' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </div>
</template>
