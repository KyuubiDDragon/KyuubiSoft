<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import {
  CloudArrowUpIcon,
  DocumentIcon,
  PhotoIcon,
  FilmIcon,
  MusicalNoteIcon,
  ArchiveBoxIcon,
  DocumentTextIcon,
  TrashIcon,
  ArrowDownTrayIcon,
  ShareIcon,
  PencilIcon,
  XMarkIcon,
  ClipboardDocumentIcon,
  EyeIcon,
  EyeSlashIcon,
  CheckIcon,
  MagnifyingGlassIcon,
  Squares2X2Icon,
  ListBulletIcon,
  FolderIcon,
  ChartBarIcon,
  LinkIcon,
} from '@heroicons/vue/24/outline'
import api from '@/core/api/axios'
import { useUiStore } from '@/stores/ui'

const uiStore = useUiStore()

// State
const files = ref([])
const isLoading = ref(true)
const isDragging = ref(false)
const isUploading = ref(false)
const uploadProgress = ref(0)
const searchQuery = ref('')
const viewMode = ref('grid') // 'grid' or 'list'
const stats = ref({ total_files: 0, total_size: 0, active_shares: 0, total_downloads: 0, total_views: 0 })
const thumbnailUrls = ref({}) // Cache for blob URLs

// Share Modal State
const showShareModal = ref(false)
const selectedFile = ref(null)
const shares = ref([]) // Array of shares for the file
const shareForm = ref({
  password: '',
  max_downloads: null,
  expires_at: '',
})
const showPassword = ref(false)
const isShareLoading = ref(false)
const copiedShareId = ref(null)

// Rename Modal State
const showRenameModal = ref(false)
const renameForm = ref({ name: '' })

// Preview Modal State
const showPreviewModal = ref(false)
const previewFile = ref(null)

// File input ref
const fileInput = ref(null)

// Computed
const filteredFiles = computed(() => {
  if (!searchQuery.value) return files.value
  const query = searchQuery.value.toLowerCase()
  return files.value.filter(f =>
    f.name.toLowerCase().includes(query) ||
    f.original_filename.toLowerCase().includes(query)
  )
})

function getShareLink(share) {
  return `${window.location.origin}/share/${share.share_token}`
}

// API Functions
async function loadFiles() {
  isLoading.value = true
  try {
    const response = await api.get('/api/v1/storage')
    files.value = response.data.data.items || []
    // Load thumbnails after files are loaded
    loadThumbnails()
  } catch (error) {
    uiStore.showError('Fehler beim Laden der Dateien')
  } finally {
    isLoading.value = false
  }
}

async function loadStats() {
  try {
    const response = await api.get('/api/v1/storage/stats')
    stats.value = response.data.data
  } catch (error) {
    console.error('Error loading stats:', error)
  }
}

async function uploadFile(file) {
  if (!file) return

  // Validate size (100MB)
  if (file.size > 100 * 1024 * 1024) {
    uiStore.showError('Datei zu groß (max. 100MB)')
    return
  }

  isUploading.value = true
  uploadProgress.value = 0

  try {
    const formData = new FormData()
    formData.append('file', file)

    const response = await api.post('/api/v1/storage/upload', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
      onUploadProgress: (progressEvent) => {
        uploadProgress.value = Math.round((progressEvent.loaded * 100) / progressEvent.total)
      },
    })

    files.value.unshift(response.data.data)
    uiStore.showSuccess('Datei erfolgreich hochgeladen')
    loadStats()
  } catch (error) {
    uiStore.showError(error.response?.data?.message || 'Upload fehlgeschlagen')
  } finally {
    isUploading.value = false
    uploadProgress.value = 0
  }
}

async function downloadFile(file) {
  try {
    const response = await api.get(`/api/v1/storage/${file.id}/download`, {
      responseType: 'blob',
    })

    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', file.original_filename)
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  } catch (error) {
    uiStore.showError('Download fehlgeschlagen')
  }
}

async function deleteFile(file) {
  if (!confirm(`"${file.name}" wirklich löschen?`)) return

  try {
    await api.delete(`/api/v1/storage/${file.id}`)
    files.value = files.value.filter(f => f.id !== file.id)
    uiStore.showSuccess('Datei gelöscht')
    loadStats()
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
  }
}

async function openShareModal(file) {
  selectedFile.value = file
  showShareModal.value = true
  isShareLoading.value = true
  shareForm.value = { password: '', max_downloads: null, expires_at: '' }

  try {
    const response = await api.get(`/api/v1/storage/${file.id}/share`)
    shares.value = response.data.data || []
  } catch (error) {
    shares.value = []
  } finally {
    isShareLoading.value = false
  }
}

async function createShare() {
  if (!selectedFile.value) return
  isShareLoading.value = true

  try {
    const payload = {
      password: shareForm.value.password || null,
      max_downloads: shareForm.value.max_downloads || null,
      expires_at: shareForm.value.expires_at || null,
    }

    const response = await api.post(`/api/v1/storage/${selectedFile.value.id}/share`, payload)
    shares.value.unshift(response.data.data)
    shareForm.value = { password: '', max_downloads: null, expires_at: '' }
    uiStore.showSuccess('Freigabe erstellt')

    // Update file in list
    const index = files.value.findIndex(f => f.id === selectedFile.value.id)
    if (index !== -1) {
      files.value[index].active_shares = shares.value.length
    }
    loadStats()
  } catch (error) {
    uiStore.showError('Fehler beim Erstellen der Freigabe')
  } finally {
    isShareLoading.value = false
  }
}

async function deleteShare(share) {
  if (!selectedFile.value) return
  if (!confirm('Freigabe wirklich löschen?')) return

  try {
    await api.delete(`/api/v1/storage/${selectedFile.value.id}/share?share_id=${share.id}`)
    shares.value = shares.value.filter(s => s.id !== share.id)
    uiStore.showSuccess('Freigabe gelöscht')

    // Update file in list
    const index = files.value.findIndex(f => f.id === selectedFile.value.id)
    if (index !== -1) {
      files.value[index].active_shares = shares.value.length
    }
    loadStats()
  } catch (error) {
    uiStore.showError('Fehler beim Löschen der Freigabe')
  }
}

async function toggleShareActive(share) {
  try {
    const response = await api.put(`/api/v1/storage/${selectedFile.value.id}/share`, {
      share_id: share.id,
      is_active: !share.is_active,
    })
    // Update share in list
    const index = shares.value.findIndex(s => s.id === share.id)
    if (index !== -1) {
      shares.value[index] = response.data.data
    }
    uiStore.showSuccess(response.data.data.is_active ? 'Freigabe aktiviert' : 'Freigabe deaktiviert')
  } catch (error) {
    uiStore.showError('Fehler beim Ändern des Status')
  }
}

function copyShareLink(share) {
  navigator.clipboard.writeText(getShareLink(share))
  copiedShareId.value = share.id
  setTimeout(() => { copiedShareId.value = null }, 2000)
}

function openRenameModal(file) {
  selectedFile.value = file
  renameForm.value.name = file.name
  showRenameModal.value = true
}

async function renameFile() {
  if (!selectedFile.value || !renameForm.value.name.trim()) return

  try {
    const response = await api.put(`/api/v1/storage/${selectedFile.value.id}`, {
      name: renameForm.value.name.trim(),
    })

    const index = files.value.findIndex(f => f.id === selectedFile.value.id)
    if (index !== -1) {
      files.value[index] = response.data.data
    }

    showRenameModal.value = false
    uiStore.showSuccess('Datei umbenannt')
  } catch (error) {
    uiStore.showError('Fehler beim Umbenennen')
  }
}

function openPreview(file) {
  if (isImage(file)) {
    previewFile.value = file
    showPreviewModal.value = true
  }
}

// Drag & Drop
function handleDragOver(e) {
  e.preventDefault()
  isDragging.value = true
}

function handleDragLeave() {
  isDragging.value = false
}

function handleDrop(e) {
  e.preventDefault()
  isDragging.value = false
  const droppedFiles = e.dataTransfer.files
  if (droppedFiles.length > 0) {
    uploadFile(droppedFiles[0])
  }
}

function handleFileSelect(e) {
  const selectedFiles = e.target.files
  if (selectedFiles.length > 0) {
    uploadFile(selectedFiles[0])
  }
  e.target.value = ''
}

// Helpers
function isImage(file) {
  return file.mime_type?.startsWith('image/')
}

function getFileIcon(file) {
  const mime = file.mime_type || ''
  if (mime.startsWith('image/')) return PhotoIcon
  if (mime.startsWith('video/')) return FilmIcon
  if (mime.startsWith('audio/')) return MusicalNoteIcon
  if (mime.includes('pdf') || mime.includes('document') || mime.includes('text')) return DocumentTextIcon
  if (mime.includes('zip') || mime.includes('archive') || mime.includes('compressed')) return ArchiveBoxIcon
  return DocumentIcon
}

// Load thumbnail with authentication
async function loadThumbnail(file) {
  if (!isImage(file) || thumbnailUrls.value[file.id]) return

  try {
    const response = await api.get(`/api/v1/storage/${file.id}/thumbnail`, {
      responseType: 'blob'
    })
    const blobUrl = URL.createObjectURL(response.data)
    thumbnailUrls.value[file.id] = blobUrl
  } catch (error) {
    console.error('Thumbnail load error:', error)
  }
}

// Load all thumbnails for image files
async function loadThumbnails() {
  const imageFiles = files.value.filter(f => isImage(f))
  await Promise.all(imageFiles.map(f => loadThumbnail(f)))
}

// Cleanup blob URLs on unmount
function cleanupThumbnails() {
  Object.values(thumbnailUrls.value).forEach(url => URL.revokeObjectURL(url))
  thumbnailUrls.value = {}
}

function getThumbnailUrl(file) {
  return thumbnailUrls.value[file.id] || null
}

function formatSize(bytes) {
  if (!bytes) return '0 B'
  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i]
}

function formatDate(dateStr) {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString('de-DE', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

// Initialize
onMounted(() => {
  loadFiles()
  loadStats()
})

onUnmounted(() => {
  cleanupThumbnails()
})
</script>

<template>
  <div class="space-y-4">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
      <h1 class="text-2xl font-bold text-white">Cloud Storage</h1>

      <div class="flex gap-2">
        <!-- Search -->
        <div class="relative">
          <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Suchen..."
            class="pl-9 pr-3 py-1.5 text-sm bg-dark-700 border border-dark-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500"
          />
        </div>

        <!-- View Mode Toggle -->
        <div class="flex bg-dark-700 rounded-lg p-0.5">
          <button
            @click="viewMode = 'grid'"
            :class="[
              'p-1.5 rounded-md transition-colors',
              viewMode === 'grid' ? 'bg-primary-600 text-white' : 'text-gray-400 hover:text-white'
            ]"
            title="Kachelansicht"
          >
            <Squares2X2Icon class="w-4 h-4" />
          </button>
          <button
            @click="viewMode = 'list'"
            :class="[
              'p-1.5 rounded-md transition-colors',
              viewMode === 'list' ? 'bg-primary-600 text-white' : 'text-gray-400 hover:text-white'
            ]"
            title="Listenansicht"
          >
            <ListBulletIcon class="w-4 h-4" />
          </button>
        </div>

        <!-- Upload Button -->
        <button
          @click="fileInput.click()"
          :disabled="isUploading"
          class="flex items-center gap-1.5 px-3 py-1.5 bg-primary-600 hover:bg-primary-700 disabled:opacity-50 rounded-lg text-white text-sm font-medium transition-colors"
        >
          <CloudArrowUpIcon class="w-4 h-4" />
          <span>Hochladen</span>
        </button>
        <input
          ref="fileInput"
          type="file"
          class="hidden"
          @change="handleFileSelect"
        />
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
      <div class="bg-dark-800 rounded-lg p-3 border border-dark-700">
        <div class="flex items-center gap-2">
          <FolderIcon class="w-5 h-5 text-primary-400" />
          <span class="text-gray-400 text-sm">Dateien</span>
        </div>
        <p class="text-xl font-bold text-white mt-1">{{ stats.total_files }}</p>
      </div>
      <div class="bg-dark-800 rounded-lg p-3 border border-dark-700">
        <div class="flex items-center gap-2">
          <ChartBarIcon class="w-5 h-5 text-blue-400" />
          <span class="text-gray-400 text-sm">Speicher</span>
        </div>
        <p class="text-xl font-bold text-white mt-1">{{ formatSize(stats.total_size) }}</p>
      </div>
      <div class="bg-dark-800 rounded-lg p-3 border border-dark-700">
        <div class="flex items-center gap-2">
          <LinkIcon class="w-5 h-5 text-green-400" />
          <span class="text-gray-400 text-sm">Freigaben</span>
        </div>
        <p class="text-xl font-bold text-white mt-1">{{ stats.active_shares }}</p>
      </div>
      <div class="bg-dark-800 rounded-lg p-3 border border-dark-700">
        <div class="flex items-center gap-2">
          <ArrowDownTrayIcon class="w-5 h-5 text-orange-400" />
          <span class="text-gray-400 text-sm">Downloads</span>
        </div>
        <p class="text-xl font-bold text-white mt-1">{{ stats.total_downloads || 0 }}</p>
      </div>
    </div>

    <!-- Drop Zone / File Area -->
    <div
      class="bg-dark-800 rounded-xl border-2 transition-colors"
      :class="isDragging ? 'border-primary-500 border-dashed' : 'border-dark-700'"
      @dragover="handleDragOver"
      @dragleave="handleDragLeave"
      @drop="handleDrop"
    >
      <!-- Upload Progress -->
      <div v-if="isUploading" class="p-4 border-b border-dark-700">
        <div class="flex items-center gap-3">
          <div class="flex-1 h-2 bg-dark-600 rounded-full overflow-hidden">
            <div
              class="h-full bg-primary-500 transition-all duration-300"
              :style="{ width: `${uploadProgress}%` }"
            ></div>
          </div>
          <span class="text-sm text-gray-400">{{ uploadProgress }}%</span>
        </div>
      </div>

      <!-- Drag Overlay -->
      <div v-if="isDragging" class="p-12 text-center">
        <CloudArrowUpIcon class="w-16 h-16 mx-auto text-primary-500 mb-4" />
        <p class="text-lg text-white font-medium">Datei hier ablegen</p>
        <p class="text-gray-400">zum Hochladen</p>
      </div>

      <!-- Loading -->
      <div v-else-if="isLoading" class="p-12 text-center">
        <div class="w-8 h-8 border-2 border-primary-500 border-t-transparent rounded-full animate-spin mx-auto"></div>
        <p class="text-gray-400 mt-4">Lade Dateien...</p>
      </div>

      <!-- Empty State -->
      <div v-else-if="files.length === 0" class="p-12 text-center">
        <CloudArrowUpIcon class="w-16 h-16 mx-auto text-gray-600 mb-4" />
        <p class="text-lg text-white font-medium">Keine Dateien vorhanden</p>
        <p class="text-gray-400 mb-4">Lade deine erste Datei hoch oder ziehe sie hierher</p>
        <button
          @click="fileInput.click()"
          class="px-4 py-2 bg-primary-600 hover:bg-primary-700 rounded-lg text-white font-medium"
        >
          Datei auswählen
        </button>
      </div>

      <!-- Grid View -->
      <div v-else-if="viewMode === 'grid'" class="p-3">
        <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 xl:grid-cols-10 gap-2">
          <div
            v-for="file in filteredFiles"
            :key="file.id"
            class="group relative bg-dark-700 rounded-lg overflow-hidden border border-dark-600 hover:border-primary-500/50 transition-all"
          >
            <!-- Thumbnail / Icon -->
            <div
              class="aspect-square relative cursor-pointer"
              @click="openPreview(file)"
            >
              <!-- Image Preview -->
              <img
                v-if="isImage(file) && getThumbnailUrl(file)"
                :src="getThumbnailUrl(file)"
                :alt="file.name"
                class="w-full h-full object-cover"
              />
              <!-- Loading placeholder for images -->
              <div v-else-if="isImage(file)" class="w-full h-full flex items-center justify-center bg-dark-600">
                <div class="w-4 h-4 border-2 border-primary-500 border-t-transparent rounded-full animate-spin"></div>
              </div>
              <!-- Icon for non-images -->
              <div v-else class="w-full h-full flex items-center justify-center bg-dark-600">
                <component :is="getFileIcon(file)" class="w-8 h-8 text-gray-500" />
              </div>

              <!-- Share badge -->
              <div
                v-if="file.active_shares > 0"
                class="absolute top-1 right-1 p-1 bg-primary-600 rounded-full"
              >
                <ShareIcon class="w-2.5 h-2.5 text-white" />
              </div>

              <!-- Hover overlay with actions -->
              <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-1">
                <button
                  @click.stop="downloadFile(file)"
                  class="p-1.5 bg-white/10 hover:bg-white/20 rounded transition-colors"
                  title="Herunterladen"
                >
                  <ArrowDownTrayIcon class="w-3.5 h-3.5 text-white" />
                </button>
                <button
                  @click.stop="openShareModal(file)"
                  class="p-1.5 bg-white/10 hover:bg-white/20 rounded transition-colors"
                  title="Freigeben"
                >
                  <ShareIcon class="w-3.5 h-3.5 text-white" />
                </button>
                <button
                  @click.stop="deleteFile(file)"
                  class="p-1.5 bg-white/10 hover:bg-red-500/50 rounded transition-colors"
                  title="Löschen"
                >
                  <TrashIcon class="w-3.5 h-3.5 text-white" />
                </button>
              </div>
            </div>

            <!-- File Info -->
            <div class="p-1.5">
              <p
                class="text-xs font-medium text-white truncate cursor-pointer hover:text-primary-400"
                :title="file.name"
                @click="openRenameModal(file)"
              >
                {{ file.name }}
              </p>
              <p class="text-[10px] text-gray-500">{{ formatSize(file.size) }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- List View -->
      <div v-else class="divide-y divide-dark-700">
        <div
          v-for="file in filteredFiles"
          :key="file.id"
          class="flex items-center gap-3 p-3 hover:bg-dark-700/50 transition-colors group"
        >
          <!-- Thumbnail / Icon -->
          <div
            class="w-10 h-10 flex-shrink-0 rounded-lg overflow-hidden cursor-pointer"
            @click="openPreview(file)"
          >
            <img
              v-if="isImage(file) && getThumbnailUrl(file)"
              :src="getThumbnailUrl(file)"
              :alt="file.name"
              class="w-full h-full object-cover"
            />
            <div v-else-if="isImage(file)" class="w-full h-full flex items-center justify-center bg-dark-600">
              <div class="w-3 h-3 border-2 border-primary-500 border-t-transparent rounded-full animate-spin"></div>
            </div>
            <div v-else class="w-full h-full flex items-center justify-center bg-dark-600">
              <component :is="getFileIcon(file)" class="w-5 h-5 text-primary-400" />
            </div>
          </div>

          <!-- Info -->
          <div class="flex-1 min-w-0">
            <p class="text-white font-medium truncate">{{ file.name }}</p>
            <p class="text-sm text-gray-400">
              {{ file.original_filename }} · {{ formatSize(file.size) }} · {{ formatDate(file.created_at) }}
            </p>
          </div>

          <!-- Share indicator -->
          <div v-if="file.active_shares > 0" class="flex items-center gap-1 text-primary-400 text-sm">
            <ShareIcon class="w-4 h-4" />
            <span>Freigegeben</span>
          </div>

          <!-- Actions -->
          <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
            <button
              @click="openRenameModal(file)"
              class="p-2 hover:bg-dark-600 rounded-lg text-gray-400 hover:text-white transition-colors"
              title="Umbenennen"
            >
              <PencilIcon class="w-5 h-5" />
            </button>
            <button
              @click="openShareModal(file)"
              class="p-2 hover:bg-dark-600 rounded-lg text-gray-400 hover:text-white transition-colors"
              title="Freigeben"
            >
              <ShareIcon class="w-5 h-5" />
            </button>
            <button
              @click="downloadFile(file)"
              class="p-2 hover:bg-dark-600 rounded-lg text-gray-400 hover:text-white transition-colors"
              title="Herunterladen"
            >
              <ArrowDownTrayIcon class="w-5 h-5" />
            </button>
            <button
              @click="deleteFile(file)"
              class="p-2 hover:bg-dark-600 rounded-lg text-gray-400 hover:text-red-400 transition-colors"
              title="Löschen"
            >
              <TrashIcon class="w-5 h-5" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Share Modal -->
    <Teleport to="body">
      <div
        v-if="showShareModal"
        class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4"
        @click.self="showShareModal = false"
      >
        <div class="bg-dark-800 rounded-xl border border-dark-700 w-full max-w-2xl max-h-[90vh] flex flex-col">
          <!-- Header -->
          <div class="flex items-center justify-between p-4 border-b border-dark-700">
            <h3 class="text-lg font-semibold text-white">
              Freigaben: {{ selectedFile?.name }}
            </h3>
            <button
              @click="showShareModal = false"
              class="p-1 hover:bg-dark-700 rounded-lg transition-colors"
            >
              <XMarkIcon class="w-5 h-5 text-gray-400" />
            </button>
          </div>

          <!-- Content -->
          <div class="flex-1 overflow-y-auto p-4 space-y-4">
            <!-- Existing Shares List -->
            <div v-if="shares.length > 0" class="space-y-2">
              <label class="block text-sm font-medium text-gray-300">Bestehende Freigaben ({{ shares.length }})</label>
              <div class="space-y-2 max-h-60 overflow-y-auto">
                <div
                  v-for="share in shares"
                  :key="share.id"
                  class="bg-dark-700 rounded-lg p-3 border border-dark-600"
                >
                  <div class="flex items-center gap-2 mb-2">
                    <input
                      :value="getShareLink(share)"
                      readonly
                      class="flex-1 px-2 py-1 bg-dark-600 border border-dark-500 rounded text-white text-xs"
                    />
                    <button
                      @click="copyShareLink(share)"
                      class="p-1.5 bg-dark-600 hover:bg-dark-500 rounded transition-colors"
                      :title="copiedShareId === share.id ? 'Kopiert!' : 'Link kopieren'"
                    >
                      <CheckIcon v-if="copiedShareId === share.id" class="w-4 h-4 text-green-500" />
                      <ClipboardDocumentIcon v-else class="w-4 h-4 text-gray-400" />
                    </button>
                    <button
                      @click="toggleShareActive(share)"
                      class="p-1.5 rounded transition-colors"
                      :class="share.is_active ? 'bg-green-600/20 text-green-400' : 'bg-dark-600 text-gray-400'"
                      :title="share.is_active ? 'Aktiv - Klicken zum Deaktivieren' : 'Inaktiv - Klicken zum Aktivieren'"
                    >
                      <CheckIcon v-if="share.is_active" class="w-4 h-4" />
                      <XMarkIcon v-else class="w-4 h-4" />
                    </button>
                    <button
                      @click="deleteShare(share)"
                      class="p-1.5 bg-dark-600 hover:bg-red-600/30 rounded transition-colors text-gray-400 hover:text-red-400"
                      title="Löschen"
                    >
                      <TrashIcon class="w-4 h-4" />
                    </button>
                  </div>
                  <div class="flex items-center gap-3 text-xs text-gray-400">
                    <span class="flex items-center gap-1">
                      <EyeIcon class="w-3.5 h-3.5" />
                      {{ share.view_count }} Aufrufe
                    </span>
                    <span class="flex items-center gap-1">
                      <ArrowDownTrayIcon class="w-3.5 h-3.5" />
                      {{ share.download_count }}{{ share.max_downloads ? ` / ${share.max_downloads}` : '' }} Downloads
                    </span>
                    <span v-if="share.has_password" class="flex items-center gap-1 text-yellow-400">
                      <EyeSlashIcon class="w-3.5 h-3.5" />
                      Passwort
                    </span>
                    <span v-if="share.expires_at" class="text-orange-400">
                      Läuft ab: {{ formatDate(share.expires_at) }}
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <div v-else-if="!isShareLoading" class="text-center py-4 text-gray-400">
              Noch keine Freigaben vorhanden
            </div>

            <!-- Divider -->
            <div class="border-t border-dark-600 pt-4">
              <label class="block text-sm font-medium text-gray-300 mb-3">Neue Freigabe erstellen</label>

              <!-- Password -->
              <div class="space-y-2 mb-3">
                <label class="block text-xs text-gray-400">Passwort (optional)</label>
                <div class="relative">
                  <input
                    v-model="shareForm.password"
                    :type="showPassword ? 'text' : 'password'"
                    placeholder="Passwort eingeben..."
                    class="w-full px-3 py-2 pr-10 bg-dark-700 border border-dark-600 rounded-lg text-white placeholder-gray-500 text-sm"
                  />
                  <button
                    type="button"
                    @click="showPassword = !showPassword"
                    class="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-gray-400 hover:text-white"
                  >
                    <EyeSlashIcon v-if="showPassword" class="w-4 h-4" />
                    <EyeIcon v-else class="w-4 h-4" />
                  </button>
                </div>
              </div>

              <div class="grid grid-cols-2 gap-3">
                <!-- Max Downloads -->
                <div class="space-y-2">
                  <label class="block text-xs text-gray-400">Max. Downloads</label>
                  <input
                    v-model.number="shareForm.max_downloads"
                    type="number"
                    min="1"
                    placeholder="Unbegrenzt"
                    class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white placeholder-gray-500 text-sm"
                  />
                </div>

                <!-- Expires At -->
                <div class="space-y-2">
                  <label class="block text-xs text-gray-400">Ablaufdatum</label>
                  <input
                    v-model="shareForm.expires_at"
                    type="datetime-local"
                    class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white text-sm"
                  />
                </div>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="flex items-center justify-end gap-2 p-4 border-t border-dark-700">
            <button
              @click="showShareModal = false"
              class="px-4 py-2 bg-dark-700 hover:bg-dark-600 rounded-lg text-white font-medium transition-colors"
            >
              Schließen
            </button>
            <button
              @click="createShare"
              :disabled="isShareLoading"
              class="px-4 py-2 bg-primary-600 hover:bg-primary-700 disabled:opacity-50 rounded-lg text-white font-medium transition-colors"
            >
              Neue Freigabe erstellen
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Rename Modal -->
    <Teleport to="body">
      <div
        v-if="showRenameModal"
        class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4"
        @click.self="showRenameModal = false"
      >
        <div class="bg-dark-800 rounded-xl border border-dark-700 w-full max-w-md">
          <div class="p-4 border-b border-dark-700">
            <h3 class="text-lg font-semibold text-white">Datei umbenennen</h3>
          </div>
          <div class="p-4">
            <input
              v-model="renameForm.name"
              type="text"
              placeholder="Neuer Name"
              class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white placeholder-gray-500"
              @keyup.enter="renameFile"
            />
          </div>
          <div class="flex justify-end gap-2 p-4 border-t border-dark-700">
            <button
              @click="showRenameModal = false"
              class="px-4 py-2 bg-dark-700 hover:bg-dark-600 rounded-lg text-white font-medium"
            >
              Abbrechen
            </button>
            <button
              @click="renameFile"
              class="px-4 py-2 bg-primary-600 hover:bg-primary-700 rounded-lg text-white font-medium"
            >
              Umbenennen
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Image Preview Modal -->
    <Teleport to="body">
      <div
        v-if="showPreviewModal && previewFile"
        class="fixed inset-0 bg-black/90 flex items-center justify-center z-50 p-4"
        @click.self="showPreviewModal = false"
      >
        <button
          @click="showPreviewModal = false"
          class="absolute top-4 right-4 p-2 bg-white/10 hover:bg-white/20 rounded-lg transition-colors"
        >
          <XMarkIcon class="w-6 h-6 text-white" />
        </button>
        <img
          :src="getThumbnailUrl(previewFile)"
          :alt="previewFile.name"
          class="max-w-full max-h-full object-contain rounded-lg"
        />
        <div class="absolute bottom-4 left-1/2 -translate-x-1/2 flex items-center gap-4 bg-black/50 backdrop-blur-sm px-4 py-2 rounded-lg">
          <span class="text-white font-medium">{{ previewFile.name }}</span>
          <span class="text-gray-400">{{ formatSize(previewFile.size) }}</span>
          <button
            @click="downloadFile(previewFile)"
            class="flex items-center gap-2 px-3 py-1 bg-primary-600 hover:bg-primary-700 rounded-lg text-white text-sm transition-colors"
          >
            <ArrowDownTrayIcon class="w-4 h-4" />
            Download
          </button>
        </div>
      </div>
    </Teleport>
  </div>
</template>
