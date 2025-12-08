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
  UserIcon,
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

    // Update download count locally
    if (shareInfo.value.downloads_remaining !== null) {
      shareInfo.value.downloads_remaining--
      shareInfo.value.download_count++
    }
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
  <div class="min-h-screen bg-dark-900 flex items-center justify-center p-4">
    <div class="w-full max-w-md">
      <!-- Logo -->
      <div class="text-center mb-8">
        <div class="flex items-center justify-center gap-2 mb-2">
          <img src="/logo.png" alt="KyuubiSoft" class="w-10 h-10" />
          <h1 class="text-2xl font-bold text-gradient">KyuubiSoft</h1>
        </div>
        <p class="text-gray-400">Cloud Storage</p>
      </div>

      <!-- Loading -->
      <div v-if="isLoading" class="bg-dark-800 rounded-xl border border-dark-700 p-8 text-center">
        <div class="w-10 h-10 border-2 border-primary-500 border-t-transparent rounded-full animate-spin mx-auto"></div>
        <p class="text-gray-400 mt-4">Lade Freigabe...</p>
      </div>

      <!-- Error -->
      <div v-else-if="error" class="bg-dark-800 rounded-xl border border-dark-700 p-8 text-center">
        <ExclamationTriangleIcon class="w-16 h-16 mx-auto text-red-500 mb-4" />
        <h2 class="text-xl font-semibold text-white mb-2">Nicht verfügbar</h2>
        <p class="text-gray-400">{{ error }}</p>
      </div>

      <!-- Share Info -->
      <div v-else-if="shareInfo" class="bg-dark-800 rounded-xl border border-dark-700 overflow-hidden">
        <!-- File Preview -->
        <div class="p-8 text-center border-b border-dark-700">
          <div class="w-20 h-20 mx-auto bg-dark-700 rounded-2xl flex items-center justify-center mb-4">
            <component :is="fileIcon" class="w-10 h-10 text-primary-400" />
          </div>
          <h2 class="text-xl font-semibold text-white mb-1">{{ shareInfo.name }}</h2>
          <p class="text-gray-400 text-sm">{{ shareInfo.original_filename }}</p>
          <p class="text-gray-500 text-sm mt-1">{{ formatSize(shareInfo.size) }}</p>
        </div>

        <!-- Info -->
        <div class="p-4 space-y-3 border-b border-dark-700">
          <!-- Owner -->
          <div class="flex items-center gap-3 text-sm">
            <UserIcon class="w-5 h-5 text-gray-500" />
            <span class="text-gray-400">Geteilt von</span>
            <span class="text-white ml-auto">{{ shareInfo.owner_name }}</span>
          </div>

          <!-- Downloads remaining -->
          <div v-if="shareInfo.max_downloads" class="flex items-center gap-3 text-sm">
            <ArrowDownTrayIcon class="w-5 h-5 text-gray-500" />
            <span class="text-gray-400">Downloads übrig</span>
            <span
              class="ml-auto"
              :class="shareInfo.downloads_remaining <= 0 ? 'text-red-400' : 'text-white'"
            >
              {{ shareInfo.downloads_remaining }} / {{ shareInfo.max_downloads }}
            </span>
          </div>

          <!-- Expires -->
          <div v-if="shareInfo.expires_at" class="flex items-center gap-3 text-sm">
            <ClockIcon class="w-5 h-5 text-gray-500" />
            <span class="text-gray-400">Gültig bis</span>
            <span
              class="ml-auto"
              :class="isExpiringSoon ? 'text-yellow-400' : 'text-white'"
            >
              {{ formatDate(shareInfo.expires_at) }}
            </span>
          </div>
        </div>

        <!-- Download Section -->
        <div class="p-4">
          <!-- Password Field -->
          <div v-if="showPasswordField && canDownload" class="mb-4">
            <label class="block text-sm font-medium text-gray-300 mb-2">
              <LockClosedIcon class="w-4 h-4 inline mr-1" />
              Passwort erforderlich
            </label>
            <input
              v-model="password"
              type="password"
              placeholder="Passwort eingeben..."
              class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500"
              @keyup.enter="downloadFile"
            />
          </div>

          <!-- Download Error -->
          <div v-if="downloadError" class="mb-4 p-3 bg-red-500/10 border border-red-500/20 rounded-lg">
            <p class="text-red-400 text-sm">{{ downloadError }}</p>
          </div>

          <!-- Success Message -->
          <div v-if="downloadSuccess" class="mb-4 p-3 bg-green-500/10 border border-green-500/20 rounded-lg flex items-center gap-2">
            <CheckCircleIcon class="w-5 h-5 text-green-400" />
            <p class="text-green-400 text-sm">Download gestartet!</p>
          </div>

          <!-- Download Button -->
          <button
            @click="downloadFile"
            :disabled="!canDownload || isDownloading || (showPasswordField && !password)"
            class="w-full flex items-center justify-center gap-2 px-4 py-3 bg-primary-600 hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg text-white font-medium transition-colors"
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
              <span>Herunterladen</span>
            </template>
          </button>
        </div>
      </div>

      <!-- Footer -->
      <p class="text-center text-gray-500 text-sm mt-6">
        Powered by KyuubiSoft
      </p>
    </div>
  </div>
</template>

<style scoped>
.text-gradient {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}
</style>
