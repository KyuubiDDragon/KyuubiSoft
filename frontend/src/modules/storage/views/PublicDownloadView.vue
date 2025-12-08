<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import {
  DocumentIcon,
  PhotoIcon,
  FilmIcon,
  MusicalNoteIcon,
  ArchiveBoxIcon,
  DocumentTextIcon,
  ArrowDownTrayIcon,
  LockClosedIcon,
  ExclamationTriangleIcon,
  CheckCircleIcon,
  ClockIcon,
  EyeIcon,
} from '@heroicons/vue/24/outline'
import axios from 'axios'

const route = useRoute()

// State
const shareInfo = ref(null)
const isLoading = ref(true)
const error = ref(null)
const password = ref('')
const showPasswordField = ref(false)
const isDownloading = ref(false)
const downloadError = ref(null)
const downloadSuccess = ref(false)

// Computed
const token = computed(() => route.params.token)

const fileIcon = computed(() => {
  if (!shareInfo.value) return DocumentIcon
  const mime = shareInfo.value.mime_type || ''
  if (mime.startsWith('image/')) return PhotoIcon
  if (mime.startsWith('video/')) return FilmIcon
  if (mime.startsWith('audio/')) return MusicalNoteIcon
  if (mime.includes('pdf') || mime.includes('document') || mime.includes('text')) return DocumentTextIcon
  if (mime.includes('zip') || mime.includes('archive') || mime.includes('compressed')) return ArchiveBoxIcon
  return DocumentIcon
})

const isExpiringSoon = computed(() => {
  if (!shareInfo.value?.expires_at) return false
  const expiresAt = new Date(shareInfo.value.expires_at)
  const now = new Date()
  const hoursLeft = (expiresAt - now) / (1000 * 60 * 60)
  return hoursLeft > 0 && hoursLeft < 24
})

const canDownload = computed(() => {
  if (!shareInfo.value) return false
  if (shareInfo.value.downloads_remaining !== null && shareInfo.value.downloads_remaining <= 0) return false
  return true
})

// Functions
async function loadShareInfo() {
  isLoading.value = true
  error.value = null

  try {
    const response = await axios.get(`/api/v1/storage/public/${token.value}`)
    shareInfo.value = response.data.data
    showPasswordField.value = shareInfo.value.has_password
  } catch (err) {
    if (err.response?.status === 403) {
      error.value = err.response.data.error || 'Freigabe nicht verfügbar'
    } else if (err.response?.status === 404) {
      error.value = 'Freigabe nicht gefunden'
    } else {
      error.value = 'Ein Fehler ist aufgetreten'
    }
  } finally {
    isLoading.value = false
  }
}

async function downloadFile() {
  if (!canDownload.value) return

  downloadError.value = null
  isDownloading.value = true

  try {
    const url = `/api/v1/storage/public/${token.value}/download`
    const params = shareInfo.value.has_password ? { password: password.value } : {}

    const response = await axios.get(url, {
      params,
      responseType: 'blob',
    })

    // Create download link
    const blobUrl = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = blobUrl
    link.setAttribute('download', shareInfo.value.original_filename)
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(blobUrl)

    downloadSuccess.value = true

    // Update counts locally
    if (shareInfo.value.downloads_remaining !== null) {
      shareInfo.value.downloads_remaining--
    }
    shareInfo.value.download_count++
  } catch (err) {
    if (err.response?.status === 401) {
      downloadError.value = 'Falsches Passwort'
    } else if (err.response?.status === 403) {
      downloadError.value = err.response.data?.error || 'Download nicht möglich'
      // Reload share info to get updated state
      loadShareInfo()
    } else {
      downloadError.value = 'Download fehlgeschlagen'
    }
  } finally {
    isDownloading.value = false
  }
}

function formatSize(bytes) {
  if (!bytes) return '0 B'
  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i]
}

function formatDate(dateStr) {
  if (!dateStr) return null
  return new Date(dateStr).toLocaleDateString('de-DE', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

onMounted(() => {
  loadShareInfo()
})
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900 flex items-center justify-center p-4">
    <div class="w-full max-w-md">
      <!-- Loading -->
      <div v-if="isLoading" class="bg-gray-800/80 backdrop-blur-sm rounded-2xl border border-gray-700/50 p-8 text-center shadow-2xl">
        <div class="w-12 h-12 border-3 border-indigo-500 border-t-transparent rounded-full animate-spin mx-auto"></div>
        <p class="text-gray-400 mt-4">Lade Freigabe...</p>
      </div>

      <!-- Error -->
      <div v-else-if="error" class="bg-gray-800/80 backdrop-blur-sm rounded-2xl border border-gray-700/50 p-8 text-center shadow-2xl">
        <div class="w-20 h-20 mx-auto bg-red-500/10 rounded-full flex items-center justify-center mb-4">
          <ExclamationTriangleIcon class="w-10 h-10 text-red-500" />
        </div>
        <h2 class="text-xl font-semibold text-white mb-2">Nicht verfügbar</h2>
        <p class="text-gray-400">{{ error }}</p>
      </div>

      <!-- Share Info -->
      <div v-else-if="shareInfo" class="bg-gray-800/80 backdrop-blur-sm rounded-2xl border border-gray-700/50 overflow-hidden shadow-2xl">
        <!-- File Preview -->
        <div class="p-8 text-center bg-gradient-to-b from-gray-700/30 to-transparent">
          <div class="w-24 h-24 mx-auto bg-gradient-to-br from-indigo-500/20 to-purple-500/20 rounded-2xl flex items-center justify-center mb-4 ring-1 ring-indigo-500/30">
            <component :is="fileIcon" class="w-12 h-12 text-indigo-400" />
          </div>
          <h2 class="text-xl font-semibold text-white mb-1">{{ shareInfo.name }}</h2>
          <p class="text-gray-400 text-sm">{{ shareInfo.original_filename }}</p>
          <p class="text-gray-500 text-sm mt-1">{{ formatSize(shareInfo.size) }}</p>
        </div>

        <!-- Stats -->
        <div class="px-6 py-4 bg-gray-900/50 flex justify-center gap-8 border-y border-gray-700/50">
          <div class="text-center">
            <div class="flex items-center justify-center gap-1.5 text-gray-400">
              <EyeIcon class="w-4 h-4" />
              <span class="text-lg font-semibold text-white">{{ shareInfo.view_count || 0 }}</span>
            </div>
            <p class="text-xs text-gray-500">Aufrufe</p>
          </div>
          <div class="text-center">
            <div class="flex items-center justify-center gap-1.5 text-gray-400">
              <ArrowDownTrayIcon class="w-4 h-4" />
              <span class="text-lg font-semibold text-white">{{ shareInfo.download_count || 0 }}</span>
            </div>
            <p class="text-xs text-gray-500">Downloads</p>
          </div>
        </div>

        <!-- Info -->
        <div class="p-4 space-y-3">
          <!-- Downloads remaining -->
          <div v-if="shareInfo.max_downloads" class="flex items-center justify-between text-sm px-2">
            <span class="text-gray-400">Downloads übrig</span>
            <span
              class="font-medium"
              :class="shareInfo.downloads_remaining <= 0 ? 'text-red-400' : 'text-white'"
            >
              {{ shareInfo.downloads_remaining }} / {{ shareInfo.max_downloads }}
            </span>
          </div>

          <!-- Expires -->
          <div v-if="shareInfo.expires_at" class="flex items-center justify-between text-sm px-2">
            <div class="flex items-center gap-2 text-gray-400">
              <ClockIcon class="w-4 h-4" />
              <span>Gültig bis</span>
            </div>
            <span
              class="font-medium"
              :class="isExpiringSoon ? 'text-yellow-400' : 'text-white'"
            >
              {{ formatDate(shareInfo.expires_at) }}
            </span>
          </div>
        </div>

        <!-- Download Section -->
        <div class="p-4 pt-0">
          <!-- Password Field -->
          <div v-if="showPasswordField && canDownload" class="mb-4">
            <label class="flex items-center gap-2 text-sm font-medium text-gray-300 mb-2">
              <LockClosedIcon class="w-4 h-4" />
              Passwort erforderlich
            </label>
            <input
              v-model="password"
              type="password"
              placeholder="Passwort eingeben..."
              class="w-full px-4 py-3 bg-gray-700/50 border border-gray-600 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
              @keyup.enter="downloadFile"
            />
          </div>

          <!-- Download Error -->
          <div v-if="downloadError" class="mb-4 p-3 bg-red-500/10 border border-red-500/20 rounded-xl">
            <p class="text-red-400 text-sm text-center">{{ downloadError }}</p>
          </div>

          <!-- Success Message -->
          <div v-if="downloadSuccess" class="mb-4 p-3 bg-green-500/10 border border-green-500/20 rounded-xl flex items-center justify-center gap-2">
            <CheckCircleIcon class="w-5 h-5 text-green-400" />
            <p class="text-green-400 text-sm">Download gestartet!</p>
          </div>

          <!-- Download Button -->
          <button
            @click="downloadFile"
            :disabled="!canDownload || isDownloading || (showPasswordField && !password)"
            class="w-full flex items-center justify-center gap-2 px-4 py-4 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:from-indigo-600 disabled:hover:to-purple-600 rounded-xl text-white font-medium transition-all shadow-lg shadow-indigo-500/25"
          >
            <template v-if="isDownloading">
              <div class="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
              <span>Wird heruntergeladen...</span>
            </template>
            <template v-else-if="!canDownload">
              <ExclamationTriangleIcon class="w-5 h-5" />
              <span>Download-Limit erreicht</span>
            </template>
            <template v-else>
              <ArrowDownTrayIcon class="w-5 h-5" />
              <span>Jetzt herunterladen</span>
            </template>
          </button>
        </div>

        <!-- Owner info -->
        <div class="px-6 py-3 bg-gray-900/50 border-t border-gray-700/50 text-center">
          <p class="text-xs text-gray-500">
            Geteilt von <span class="text-gray-400">{{ shareInfo.owner_name }}</span>
          </p>
        </div>
      </div>

      <!-- Footer -->
      <p class="text-center text-gray-600 text-xs mt-6">
        Powered by KyuubiSoft Cloud
      </p>
    </div>
  </div>
</template>
