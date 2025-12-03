<script setup>
import { ref, computed } from 'vue'
import api from '@/core/api/axios'
import { useUiStore } from '@/stores/ui'
import {
  ArrowDownTrayIcon,
  MusicalNoteIcon,
  FilmIcon,
  MagnifyingGlassIcon,
  ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline'

const uiStore = useUiStore()

// State
const url = ref('')
const loading = ref(false)
const downloading = ref(false)
const videoInfo = ref(null)
const audioOnly = ref(false)
const selectedFormat = ref('best')

// Computed
const formattedDuration = computed(() => {
  if (!videoInfo.value?.duration) return ''
  const mins = Math.floor(videoInfo.value.duration / 60)
  const secs = videoInfo.value.duration % 60
  return `${mins}:${secs.toString().padStart(2, '0')}`
})

const formattedViews = computed(() => {
  if (!videoInfo.value?.view_count) return ''
  return new Intl.NumberFormat('de-DE').format(videoInfo.value.view_count)
})

const videoFormats = computed(() => {
  if (!videoInfo.value?.formats) return []
  return videoInfo.value.formats
    .filter(f => f.vcodec !== 'none' && f.resolution !== 'audio only')
    .sort((a, b) => (b.quality || 0) - (a.quality || 0))
    .slice(0, 10)
})

// Methods
async function fetchInfo() {
  if (!url.value.trim()) {
    uiStore.showError('Bitte URL eingeben')
    return
  }

  loading.value = true
  videoInfo.value = null

  try {
    const res = await api.post('/api/v1/youtube/info', { url: url.value })
    videoInfo.value = res.data.data
  } catch (error) {
    uiStore.showError(error.response?.data?.error || 'Video nicht gefunden')
  } finally {
    loading.value = false
  }
}

async function downloadVideo() {
  if (!url.value.trim()) {
    uiStore.showError('Bitte URL eingeben')
    return
  }

  downloading.value = true

  try {
    const res = await api.post('/api/v1/youtube/download', {
      url: url.value,
      format: selectedFormat.value,
      audio_only: audioOnly.value
    })

    // Trigger download
    const downloadUrl = res.data.data.download_url
    const link = document.createElement('a')
    link.href = downloadUrl
    link.download = res.data.data.filename
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)

    uiStore.showSuccess('Download gestartet!')
  } catch (error) {
    uiStore.showError(error.response?.data?.error || 'Download fehlgeschlagen')
  } finally {
    downloading.value = false
  }
}

function formatBytes(bytes) {
  if (!bytes) return 'Unbekannt'
  const sizes = ['B', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(1024))
  return `${(bytes / Math.pow(1024, i)).toFixed(1)} ${sizes[i]}`
}

function clearForm() {
  url.value = ''
  videoInfo.value = null
  selectedFormat.value = 'best'
  audioOnly.value = false
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div>
      <h1 class="text-2xl font-bold text-white">YouTube Downloader</h1>
      <p class="text-gray-400 mt-1">Videos und Audio von YouTube herunterladen</p>
    </div>

    <!-- Disclaimer -->
    <div class="card p-4 bg-yellow-900/20 border border-yellow-700">
      <div class="flex gap-3">
        <ExclamationTriangleIcon class="w-6 h-6 text-yellow-500 flex-shrink-0" />
        <div class="text-sm text-yellow-200">
          <p class="font-semibold mb-1">Wichtiger Hinweis</p>
          <p class="text-yellow-300/80">
            Dieses Tool ist nur für den Download von urheberrechtsfreien Inhalten,
            Creative Commons Videos oder eigenen Inhalten gedacht.
            Das Herunterladen von urheberrechtlich geschütztem Material ohne Erlaubnis
            kann gegen geltendes Recht verstoßen.
          </p>
        </div>
      </div>
    </div>

    <!-- URL Input -->
    <div class="card p-6">
      <div class="flex gap-3">
        <input
          v-model="url"
          type="text"
          class="input flex-1"
          placeholder="https://www.youtube.com/watch?v=..."
          @keyup.enter="fetchInfo"
        />
        <button
          @click="fetchInfo"
          :disabled="loading"
          class="btn-secondary"
        >
          <MagnifyingGlassIcon v-if="!loading" class="w-5 h-5" />
          <span v-else class="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
        </button>
        <button
          v-if="videoInfo"
          @click="clearForm"
          class="btn-ghost"
        >
          Zurücksetzen
        </button>
      </div>
    </div>

    <!-- Video Info -->
    <div v-if="videoInfo" class="card p-6">
      <div class="flex gap-6">
        <!-- Thumbnail -->
        <div class="w-64 flex-shrink-0">
          <img
            :src="videoInfo.thumbnail"
            :alt="videoInfo.title"
            class="w-full rounded-lg"
          />
        </div>

        <!-- Details -->
        <div class="flex-1 space-y-4">
          <h2 class="text-xl font-semibold text-white">{{ videoInfo.title }}</h2>

          <div class="flex flex-wrap gap-4 text-sm text-gray-400">
            <span>{{ videoInfo.uploader }}</span>
            <span>{{ formattedDuration }}</span>
            <span>{{ formattedViews }} Aufrufe</span>
          </div>

          <p class="text-gray-400 text-sm line-clamp-3">
            {{ videoInfo.description }}
          </p>

          <!-- Download Options -->
          <div class="pt-4 border-t border-dark-600 space-y-4">
            <div class="flex gap-6">
              <!-- Audio Only Toggle -->
              <label class="flex items-center gap-2 cursor-pointer">
                <input
                  v-model="audioOnly"
                  type="checkbox"
                  class="w-4 h-4 rounded border-gray-600 text-primary-500 focus:ring-primary-500"
                />
                <MusicalNoteIcon class="w-5 h-5 text-gray-400" />
                <span class="text-gray-300">Nur Audio (MP3)</span>
              </label>
            </div>

            <!-- Format Selection (only for video) -->
            <div v-if="!audioOnly && videoFormats.length > 0">
              <label class="text-sm text-gray-400 mb-2 block">Qualität</label>
              <select v-model="selectedFormat" class="input w-64">
                <option value="best">Beste Qualität</option>
                <option v-for="fmt in videoFormats" :key="fmt.format_id" :value="fmt.format_id">
                  {{ fmt.resolution }} ({{ fmt.ext }}) - {{ formatBytes(fmt.filesize) }}
                </option>
              </select>
            </div>

            <!-- Download Button -->
            <button
              @click="downloadVideo"
              :disabled="downloading"
              class="btn-primary"
            >
              <ArrowDownTrayIcon v-if="!downloading" class="w-5 h-5 mr-2" />
              <span v-else class="w-5 h-5 mr-2 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
              {{ downloading ? 'Wird heruntergeladen...' : (audioOnly ? 'Audio herunterladen' : 'Video herunterladen') }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="!loading" class="card p-12 flex flex-col items-center justify-center text-center">
      <FilmIcon class="w-16 h-16 text-gray-600 mb-4" />
      <h3 class="text-lg font-medium text-gray-400 mb-2">YouTube URL eingeben</h3>
      <p class="text-gray-500 max-w-md">
        Füge eine YouTube-URL ein und klicke auf Suchen,
        um Video-Informationen zu laden und den Download zu starten.
      </p>
    </div>
  </div>
</template>
