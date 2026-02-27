<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/core/api/axios'
import { useToast } from '@/composables/useToast'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
import {
  PlusIcon,
  ArrowPathIcon,
  TrashIcon,
  PencilIcon,
  FolderIcon,
  FolderPlusIcon,
  ChevronDownIcon,
  ChevronRightIcon,
  ExclamationCircleIcon,
  CheckCircleIcon,
  ClockIcon,
  CodeBracketIcon,
  ArrowTopRightOnSquareIcon,
  StarIcon,
  ShareIcon,
  ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline'

const toast = useToast()
const { confirm } = useConfirmDialog()

// State
const repositories = ref([])
const folders = ref([])
const stats = ref(null)
const isLoading = ref(true)
const showModal = ref(false)
const showFolderModal = ref(false)
const showDetailModal = ref(false)
const editingRepo = ref(null)
const selectedRepo = ref(null)
const syncingId = ref(null)

// Filters
const filters = ref({
  project_id: '',
  folder_id: null,
  provider: '',
})

// Form
const form = ref({
  name: '',
  repo_url: '',
  provider: 'github',
  api_token: '',
  default_branch: 'main',
  auto_sync: true,
  sync_interval: 300,
  notify_on_new_pr: true,
  notify_on_new_issue: true,
  notify_on_merge: true,
  notify_on_release: false,
  project_id: '',
  folder_id: '',
})

const folderForm = ref({
  name: '',
  color: '#6366F1',
})

const providers = [
  { value: 'github', label: 'GitHub', icon: 'github' },
  { value: 'gitlab', label: 'GitLab', icon: 'gitlab' },
  { value: 'bitbucket', label: 'Bitbucket', icon: 'bitbucket' },
  { value: 'gitea', label: 'Gitea', icon: 'gitea' },
  { value: 'custom', label: 'Custom', icon: 'git' },
]

const folderColors = [
  '#6366F1', '#8B5CF6', '#EC4899', '#EF4444', '#F97316',
  '#EAB308', '#22C55E', '#14B8A6', '#06B6D4', '#3B82F6',
]

// Computed
const groupedRepos = computed(() => {
  const groups = {}

  folders.value.forEach(folder => {
    groups[folder.id] = { folder, repositories: [], isCollapsed: folder.is_collapsed }
  })

  groups['unfiled'] = { folder: null, repositories: [], isCollapsed: false }

  repositories.value.forEach(repo => {
    const folderId = repo.folder_id || 'unfiled'
    if (groups[folderId]) {
      groups[folderId].repositories.push(repo)
    }
  })

  return groups
})

// Methods
const loadRepositories = async () => {
  try {
    isLoading.value = true
    const params = Object.fromEntries(
      Object.entries(filters.value).filter(([_, v]) => v)
    )
    const response = await api.get('/api/v1/git/repositories', { params })
    repositories.value = response.data.data.items
    folders.value = response.data.data.folders
  } catch (error) {
    console.error('Failed to load repositories:', error)
  } finally {
    isLoading.value = false
  }
}

const loadStats = async () => {
  try {
    const response = await api.get('/api/v1/git/repositories/stats')
    stats.value = response.data.data
  } catch (error) {
    console.error('Failed to load stats:', error)
  }
}

const loadRepoDetails = async (id) => {
  try {
    const response = await api.get(`/api/v1/git/repositories/${id}`)
    selectedRepo.value = response.data.data
    showDetailModal.value = true
  } catch (error) {
    console.error('Failed to load repo details:', error)
  }
}

const saveRepository = async () => {
  try {
    if (editingRepo.value) {
      await api.put(`/api/v1/git/repositories/${editingRepo.value.id}`, form.value)
    } else {
      await api.post('/api/v1/git/repositories', form.value)
    }
    showModal.value = false
    resetForm()
    await loadRepositories()
    await loadStats()
  } catch (error) {
    console.error('Failed to save repository:', error)
  }
}

const deleteRepository = async (id) => {
  if (!await confirm({ message: 'Repository wirklich entfernen?', type: 'danger', confirmText: 'Löschen' })) return
  try {
    await api.delete(`/api/v1/git/repositories/${id}`)
    await loadRepositories()
    await loadStats()
  } catch (error) {
    console.error('Failed to delete repository:', error)
  }
}

const syncRepository = async (id) => {
  try {
    syncingId.value = id
    await api.post(`/api/v1/git/repositories/${id}/sync`)
    await loadRepositories()
  } catch (error) {
    console.error('Failed to sync repository:', error)
  } finally {
    syncingId.value = null
  }
}

const saveFolder = async () => {
  try {
    await api.post('/api/v1/git/folders', folderForm.value)
    showFolderModal.value = false
    folderForm.value = { name: '', color: '#6366F1' }
    await loadRepositories()
  } catch (error) {
    console.error('Failed to save folder:', error)
  }
}

const toggleFolderCollapse = async (folder) => {
  folder.is_collapsed = !folder.is_collapsed
  try {
    await api.put(`/api/v1/git/folders/${folder.id}`, { is_collapsed: folder.is_collapsed })
  } catch (error) {
    console.error('Failed to update folder:', error)
  }
}

const editRepository = (repo) => {
  editingRepo.value = repo
  form.value = { ...repo }
  showModal.value = true
}

const resetForm = () => {
  editingRepo.value = null
  form.value = {
    name: '',
    repo_url: '',
    provider: 'github',
    api_token: '',
    default_branch: 'main',
    auto_sync: true,
    sync_interval: 300,
    notify_on_new_pr: true,
    notify_on_new_issue: true,
    notify_on_merge: true,
    notify_on_release: false,
    project_id: '',
    folder_id: '',
  }
}

const getProviderIcon = (provider) => {
  return providers.find(p => p.value === provider)?.label || provider
}

const formatDate = (date) => {
  if (!date) return '-'
  return new Date(date).toLocaleString('de-DE')
}

onMounted(() => {
  loadRepositories()
  loadStats()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex justify-between items-center">
      <div>
        <h1 class="text-3xl font-bold text-white">Git Repositories</h1>
        <p class="text-gray-400">Verwalte und überwache deine Git Repositories</p>
      </div>
      <div class="flex gap-2">
        <button @click="showFolderModal = true" class="btn-secondary">
          <FolderPlusIcon class="w-5 h-5 mr-2" />
          Ordner
        </button>
        <button @click="showModal = true" class="btn-primary">
          <PlusIcon class="w-5 h-5 mr-2" />
          Repository
        </button>
      </div>
    </div>

    <!-- Stats -->
    <div v-if="stats" class="grid grid-cols-4 gap-4">
      <div class="bg-white/[0.04] rounded-lg p-4 shadow-glass">
        <div class="text-gray-400 text-sm">Repositories</div>
        <div class="text-2xl font-bold text-white">{{ stats.total_repositories }}</div>
      </div>
      <div class="bg-blue-900/20 rounded-lg p-4 shadow-glass">
        <div class="text-blue-400 text-sm">Offene PRs</div>
        <div class="text-2xl font-bold text-blue-300">{{ stats.total_open_prs }}</div>
      </div>
      <div class="bg-amber-900/20 rounded-lg p-4 shadow-glass">
        <div class="text-amber-400 text-sm">Offene Issues</div>
        <div class="text-2xl font-bold text-amber-300">{{ stats.total_open_issues }}</div>
      </div>
      <div class="bg-white/[0.03] rounded-lg p-4 shadow-glass">
        <div class="text-gray-400 text-sm">Provider</div>
        <div class="text-sm text-gray-300">
          <span v-for="p in stats.by_provider" :key="p.provider" class="mr-2">
            {{ p.provider }}: {{ p.count }}
          </span>
        </div>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="flex justify-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
    </div>

    <!-- Repository List -->
    <div v-else class="space-y-4">
      <template v-for="(group, folderId) in groupedRepos" :key="folderId">
        <div v-if="group.repositories.length > 0 || group.folder"
             class="bg-white/[0.04] rounded-lg shadow overflow-hidden">
          <!-- Folder Header -->
          <div v-if="group.folder"
               class="px-4 py-3 bg-white/[0.03] cursor-pointer flex items-center justify-between"
               @click="toggleFolderCollapse(group.folder)">
            <div class="flex items-center gap-2">
              <FolderIcon class="w-5 h-5" :style="{ color: group.folder.color }" />
              <span class="font-medium text-white">{{ group.folder.name }}</span>
              <span class="text-sm text-gray-500">({{ group.repositories.length }})</span>
            </div>
            <component :is="group.isCollapsed ? ChevronRightIcon : ChevronDownIcon" class="w-5 h-5 text-gray-400" />
          </div>
          <div v-else class="px-4 py-3 bg-white/[0.03]">
            <span class="font-medium text-white">Ohne Ordner</span>
            <span class="text-sm text-gray-500 ml-2">({{ group.repositories.length }})</span>
          </div>

          <!-- Repositories -->
          <div v-if="!group.isCollapsed" class="divide-y divide-white/[0.06]">
            <div v-for="repo in group.repositories" :key="repo.id"
                 class="p-4 hover:bg-white/[0.04] flex items-center justify-between">
              <div class="flex items-center gap-4 flex-1 cursor-pointer" @click="loadRepoDetails(repo.id)">
                <div class="w-10 h-10 rounded-lg bg-white/[0.04] flex items-center justify-center">
                  <CodeBracketIcon class="w-6 h-6 text-gray-400" />
                </div>
                <div class="flex-1">
                  <div class="flex items-center gap-2">
                    <h3 class="font-medium text-white">{{ repo.name }}</h3>
                    <span class="text-xs px-2 py-0.5 rounded bg-white/[0.04] text-gray-400">
                      {{ getProviderIcon(repo.provider) }}
                    </span>
                    <span v-if="repo.is_private" class="text-xs px-2 py-0.5 rounded bg-yellow-900/30 text-yellow-400">
                      Privat
                    </span>
                  </div>
                  <p class="text-sm text-gray-400 truncate max-w-md">{{ repo.repo_url }}</p>
                </div>
                <div class="flex items-center gap-6 text-sm">
                  <div class="flex items-center gap-1 text-gray-500">
                    <StarIcon class="w-4 h-4" />
                    {{ repo.stars_count }}
                  </div>
                  <div class="flex items-center gap-1 text-gray-500">
                    <ShareIcon class="w-4 h-4" />
                    {{ repo.forks_count }}
                  </div>
                  <div v-if="repo.open_prs_count > 0" class="flex items-center gap-1 text-blue-600">
                    <span class="font-medium">{{ repo.open_prs_count }} PRs</span>
                  </div>
                  <div v-if="repo.open_issues_count > 0" class="flex items-center gap-1 text-amber-600">
                    <ExclamationCircleIcon class="w-4 h-4" />
                    {{ repo.open_issues_count }}
                  </div>
                </div>
              </div>
              <div class="flex items-center gap-2 ml-4">
                <button @click.stop="syncRepository(repo.id)"
                        :disabled="syncingId === repo.id"
                        class="p-2 text-gray-400 hover:text-indigo-600 disabled:opacity-50">
                  <ArrowPathIcon class="w-5 h-5" :class="{ 'animate-spin': syncingId === repo.id }" />
                </button>
                <a :href="repo.repo_url" target="_blank"
                   class="p-2 text-gray-400 hover:text-gray-600"
                   @click.stop>
                  <ArrowTopRightOnSquareIcon class="w-5 h-5" />
                </a>
                <button @click.stop="editRepository(repo)" class="p-2 text-gray-400 hover:text-indigo-600">
                  <PencilIcon class="w-5 h-5" />
                </button>
                <button @click.stop="deleteRepository(repo.id)" class="p-2 text-gray-400 hover:text-red-600">
                  <TrashIcon class="w-5 h-5" />
                </button>
              </div>
            </div>
          </div>
        </div>
      </template>

      <!-- Empty State -->
      <div v-if="repositories.length === 0"
           class="text-center py-12 bg-white/[0.04] rounded-lg shadow-glass">
        <CodeBracketIcon class="w-12 h-12 mx-auto text-gray-400" />
        <h3 class="mt-4 text-lg font-medium text-white">Keine Repositories</h3>
        <p class="mt-2 text-gray-500">Füge dein erstes Git Repository hinzu.</p>
        <button @click="showModal = true" class="mt-4 btn-primary">
          <PlusIcon class="w-5 h-5 mr-2" />
          Repository hinzufügen
        </button>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4">
          <div class="fixed inset-0 bg-black/60 backdrop-blur-md" @click="showModal = false"></div>
          <div class="relative bg-white/[0.04] rounded-lg shadow-float w-full max-w-lg p-6">
            <h2 class="text-xl font-bold text-white mb-4">
              {{ editingRepo ? 'Repository bearbeiten' : 'Neues Repository' }}
            </h2>
            <form @submit.prevent="saveRepository" class="space-y-4">
              <div>
                <label class="label">Name</label>
                <input v-model="form.name" type="text" required class="input" />
              </div>
              <div>
                <label class="label">Repository URL</label>
                <input v-model="form.repo_url" type="url" required placeholder="https://github.com/user/repo" class="input" />
              </div>
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="label">Provider</label>
                  <select v-model="form.provider" class="input">
                    <option v-for="p in providers" :key="p.value" :value="p.value">{{ p.label }}</option>
                  </select>
                </div>
                <div>
                  <label class="label">Branch</label>
                  <input v-model="form.default_branch" type="text" class="input" />
                </div>
              </div>
              <div>
                <label class="label">API Token (optional)</label>
                <input v-model="form.api_token" type="password" placeholder="Für private Repositories" class="input" />
              </div>
              <div class="flex items-center gap-4">
                <label class="flex items-center gap-2">
                  <input v-model="form.auto_sync" type="checkbox" class="rounded" />
                  <span class="text-sm text-gray-300">Auto-Sync</span>
                </label>
                <label class="flex items-center gap-2">
                  <input v-model="form.notify_on_new_pr" type="checkbox" class="rounded" />
                  <span class="text-sm text-gray-300">PR Benachrichtigung</span>
                </label>
              </div>
              <div class="flex justify-end gap-3 pt-4">
                <button type="button" @click="showModal = false; resetForm()" class="btn-secondary">Abbrechen</button>
                <button type="submit" class="btn-primary">Speichern</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Folder Modal -->
    <Teleport to="body">
      <div v-if="showFolderModal" class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4">
          <div class="fixed inset-0 bg-black/60 backdrop-blur-md" @click="showFolderModal = false"></div>
          <div class="relative bg-white/[0.04] rounded-lg shadow-float w-full max-w-md p-6">
            <h2 class="text-xl font-bold text-white mb-4">Neuer Ordner</h2>
            <form @submit.prevent="saveFolder" class="space-y-4">
              <div>
                <label class="label">Name</label>
                <input v-model="folderForm.name" type="text" required class="input" />
              </div>
              <div>
                <label class="label">Farbe</label>
                <div class="flex gap-2">
                  <button v-for="color in folderColors" :key="color" type="button"
                          @click="folderForm.color = color"
                          class="w-8 h-8 rounded-full border-2"
                          :class="folderForm.color === color ? 'border-white' : 'border-transparent'"
                          :style="{ backgroundColor: color }"></button>
                </div>
              </div>
              <div class="flex justify-end gap-3 pt-4">
                <button type="button" @click="showFolderModal = false" class="btn-secondary">Abbrechen</button>
                <button type="submit" class="btn-primary">Erstellen</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Detail Modal -->
    <Teleport to="body">
      <div v-if="showDetailModal && selectedRepo" class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4">
          <div class="fixed inset-0 bg-black/60 backdrop-blur-md" @click="showDetailModal = false"></div>
          <div class="relative bg-white/[0.04] rounded-lg shadow-float w-full max-w-4xl max-h-[90vh] overflow-y-auto p-6">
            <div class="flex justify-between items-start mb-6">
              <div>
                <h2 class="text-2xl font-bold text-white">{{ selectedRepo.repository.name }}</h2>
                <p class="text-gray-500">{{ selectedRepo.repository.repo_url }}</p>
              </div>
              <button @click="showDetailModal = false" class="text-gray-400 hover:text-gray-600">
                <span class="sr-only">Schließen</span>
                &times;
              </button>
            </div>

            <!-- Tabs -->
            <div class="grid grid-cols-2 gap-6">
              <!-- Pull Requests -->
              <div>
                <h3 class="font-semibold text-white mb-3">Pull Requests</h3>
                <div class="space-y-2 max-h-60 overflow-y-auto">
                  <div v-for="pr in selectedRepo.pull_requests" :key="pr.id"
                       class="p-3 bg-white/[0.03] rounded-lg">
                    <div class="flex items-center gap-2">
                      <span :class="pr.state === 'open' ? 'text-green-600' : pr.state === 'merged' ? 'text-purple-600' : 'text-red-600'">
                        #{{ pr.number }}
                      </span>
                      <span class="font-medium text-sm truncate">{{ pr.title }}</span>
                    </div>
                    <div class="text-xs text-gray-500 mt-1">
                      {{ pr.author }} - {{ formatDate(pr.external_updated_at) }}
                    </div>
                  </div>
                  <div v-if="!selectedRepo.pull_requests?.length" class="text-gray-500 text-sm">Keine PRs</div>
                </div>
              </div>

              <!-- Issues -->
              <div>
                <h3 class="font-semibold text-white mb-3">Issues</h3>
                <div class="space-y-2 max-h-60 overflow-y-auto">
                  <div v-for="issue in selectedRepo.issues" :key="issue.id"
                       class="p-3 bg-white/[0.03] rounded-lg">
                    <div class="flex items-center gap-2">
                      <span :class="issue.state === 'open' ? 'text-green-600' : 'text-red-600'">
                        #{{ issue.number }}
                      </span>
                      <span class="font-medium text-sm truncate">{{ issue.title }}</span>
                    </div>
                    <div class="text-xs text-gray-500 mt-1">
                      {{ issue.author }} - {{ formatDate(issue.external_updated_at) }}
                    </div>
                  </div>
                  <div v-if="!selectedRepo.issues?.length" class="text-gray-500 text-sm">Keine Issues</div>
                </div>
              </div>
            </div>

            <!-- Recent Commits -->
            <div class="mt-6">
              <h3 class="font-semibold text-white mb-3">Letzte Commits</h3>
              <div class="space-y-2 max-h-60 overflow-y-auto">
                <div v-for="commit in selectedRepo.commits?.slice(0, 10)" :key="commit.id"
                     class="p-3 bg-white/[0.03] rounded-lg flex items-start gap-3">
                  <code class="text-xs text-indigo-600 font-mono">{{ commit.sha?.substring(0, 7) }}</code>
                  <div class="flex-1">
                    <p class="text-sm text-white line-clamp-1">{{ commit.message }}</p>
                    <p class="text-xs text-gray-500">{{ commit.author_name }} - {{ formatDate(commit.committed_at) }}</p>
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

