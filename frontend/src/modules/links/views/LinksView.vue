<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '@/core/api/axios'
import { useUiStore } from '@/stores/ui'
import {
  LinkIcon,
  PlusIcon,
  TrashIcon,
  ClipboardDocumentIcon,
  ChartBarIcon,
  QrCodeIcon,
  XMarkIcon,
  EyeIcon,
  EyeSlashIcon,
  CalendarIcon,
  CursorArrowRaysIcon,
  GlobeAltIcon,
  DevicePhoneMobileIcon,
  ComputerDesktopIcon,
  ArrowPathIcon,
  CheckIcon,
  LockClosedIcon,
  ClockIcon,
} from '@heroicons/vue/24/outline'

const uiStore = useUiStore()

// State
const isLoading = ref(true)
const links = ref([])
const stats = ref(null)
const searchQuery = ref('')

// Modals
const showCreateModal = ref(false)
const showStatsModal = ref(false)
const showQrModal = ref(false)
const selectedLink = ref(null)
const linkStats = ref(null)
const qrData = ref(null)
const isProcessing = ref(false)
const copied = ref(false)

// Form
const linkForm = ref({
  url: '',
  title: '',
  custom_code: '',
  password: '',
  expires_at: '',
  max_clicks: null,
})

// Computed
const baseUrl = computed(() => {
  return window.location.origin
})

const filteredLinks = computed(() => {
  if (!searchQuery.value) return links.value
  const query = searchQuery.value.toLowerCase()
  return links.value.filter(link =>
    link.title?.toLowerCase().includes(query) ||
    link.original_url.toLowerCase().includes(query) ||
    link.short_code.toLowerCase().includes(query)
  )
})

// Fetch data
async function fetchData() {
  isLoading.value = true
  try {
    const [linksRes, statsRes] = await Promise.all([
      api.get('/api/v1/links'),
      api.get('/api/v1/links/stats'),
    ])
    links.value = linksRes.data.data || []
    stats.value = statsRes.data.data
  } catch (error) {
    console.error('Failed to fetch links:', error)
    uiStore.showError('Fehler beim Laden der Links')
  } finally {
    isLoading.value = false
  }
}

// Create link
function openCreateModal() {
  linkForm.value = {
    url: '',
    title: '',
    custom_code: '',
    password: '',
    expires_at: '',
    max_clicks: null,
  }
  showCreateModal.value = true
}

async function createLink() {
  if (!linkForm.value.url) {
    uiStore.showError('URL ist erforderlich')
    return
  }

  isProcessing.value = true
  try {
    const payload = {
      url: linkForm.value.url,
      title: linkForm.value.title || null,
      custom_code: linkForm.value.custom_code || null,
      password: linkForm.value.password || null,
      expires_at: linkForm.value.expires_at || null,
      max_clicks: linkForm.value.max_clicks || null,
    }

    await api.post('/api/v1/links', payload)
    uiStore.showSuccess('Link erstellt')
    showCreateModal.value = false
    await fetchData()
  } catch (error) {
    uiStore.showError(error.response?.data?.message || 'Fehler beim Erstellen')
  } finally {
    isProcessing.value = false
  }
}

// Delete link
async function deleteLink(link) {
  if (!confirm(`Link "${link.short_code}" wirklich löschen?`)) return

  try {
    await api.delete(`/api/v1/links/${link.id}`)
    uiStore.showSuccess('Link gelöscht')
    await fetchData()
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
  }
}

// Toggle active state
async function toggleActive(link) {
  try {
    await api.put(`/api/v1/links/${link.id}`, {
      is_active: !link.is_active
    })
    link.is_active = !link.is_active
  } catch (error) {
    uiStore.showError('Fehler beim Umschalten')
  }
}

// View stats
async function viewStats(link) {
  selectedLink.value = link
  showStatsModal.value = true
  linkStats.value = null

  try {
    const response = await api.get(`/api/v1/links/${link.id}/stats`)
    linkStats.value = response.data.data
  } catch (error) {
    uiStore.showError('Fehler beim Laden der Statistiken')
  }
}

// View QR code
async function viewQr(link) {
  selectedLink.value = link
  showQrModal.value = true
  qrData.value = null

  try {
    const response = await api.get(`/api/v1/links/${link.id}/qr`)
    qrData.value = response.data.data
  } catch (error) {
    uiStore.showError('Fehler beim Laden des QR-Codes')
  }
}

// Copy to clipboard
async function copyToClipboard(text) {
  try {
    await navigator.clipboard.writeText(text)
    copied.value = true
    setTimeout(() => copied.value = false, 2000)
    uiStore.showSuccess('In Zwischenablage kopiert')
  } catch (error) {
    uiStore.showError('Kopieren fehlgeschlagen')
  }
}

// Get short URL
function getShortUrl(code) {
  return `${baseUrl.value}/s/${code}`
}

// Helpers
function formatDate(dateStr) {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString('de-DE', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  })
}

function formatDateTime(dateStr) {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleString('de-DE')
}

function isExpired(link) {
  if (!link.expires_at) return false
  return new Date(link.expires_at) < new Date()
}

function isLimitReached(link) {
  if (!link.max_clicks) return false
  return link.click_count >= link.max_clicks
}

onMounted(fetchData)
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-white">Link Shortener</h1>
        <p class="text-gray-400 mt-1">Erstelle und verwalte kurze URLs mit Statistiken</p>
      </div>
      <button @click="openCreateModal" class="btn-primary">
        <PlusIcon class="w-5 h-5 mr-2" />
        Link erstellen
      </button>
    </div>

    <!-- Stats Cards -->
    <div v-if="stats" class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <div class="card p-4">
        <div class="flex items-center gap-3">
          <div class="p-2 rounded-lg bg-blue-500/20">
            <LinkIcon class="w-6 h-6 text-blue-400" />
          </div>
          <div>
            <p class="text-gray-400 text-sm">Aktive Links</p>
            <p class="text-xl font-bold text-white">{{ stats.active_links }}</p>
          </div>
        </div>
      </div>
      <div class="card p-4">
        <div class="flex items-center gap-3">
          <div class="p-2 rounded-lg bg-green-500/20">
            <CursorArrowRaysIcon class="w-6 h-6 text-green-400" />
          </div>
          <div>
            <p class="text-gray-400 text-sm">Gesamt Klicks</p>
            <p class="text-xl font-bold text-white">{{ stats.total_clicks.toLocaleString() }}</p>
          </div>
        </div>
      </div>
      <div class="card p-4">
        <div class="flex items-center gap-3">
          <div class="p-2 rounded-lg bg-purple-500/20">
            <ChartBarIcon class="w-6 h-6 text-purple-400" />
          </div>
          <div>
            <p class="text-gray-400 text-sm">Klicks heute</p>
            <p class="text-xl font-bold text-white">{{ stats.clicks_today }}</p>
          </div>
        </div>
      </div>
      <div class="card p-4">
        <div class="flex items-center gap-3">
          <div class="p-2 rounded-lg bg-yellow-500/20">
            <GlobeAltIcon class="w-6 h-6 text-yellow-400" />
          </div>
          <div>
            <p class="text-gray-400 text-sm">Gesamt Links</p>
            <p class="text-xl font-bold text-white">{{ stats.total_links }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Search -->
    <div class="card p-4">
      <input
        v-model="searchQuery"
        type="text"
        placeholder="Links durchsuchen..."
        class="w-full px-4 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:border-primary-500"
      />
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="card p-12 text-center">
      <ArrowPathIcon class="w-8 h-8 text-gray-500 mx-auto animate-spin" />
      <p class="text-gray-500 mt-2">Lade Links...</p>
    </div>

    <!-- Links List -->
    <div v-else-if="filteredLinks.length === 0" class="card p-12 text-center">
      <LinkIcon class="w-16 h-16 text-gray-600 mx-auto mb-4" />
      <h3 class="text-lg font-semibold text-white mb-2">Keine Links vorhanden</h3>
      <p class="text-gray-500 mb-4">Erstelle deinen ersten kurzen Link.</p>
      <button @click="openCreateModal" class="btn-primary">
        <PlusIcon class="w-4 h-4 mr-2" />
        Link erstellen
      </button>
    </div>

    <div v-else class="space-y-3">
      <div
        v-for="link in filteredLinks"
        :key="link.id"
        class="card p-4 hover:bg-dark-700/50 transition-colors"
      >
        <div class="flex items-center justify-between">
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 mb-1">
              <h3 class="text-white font-medium truncate">
                {{ link.title || link.short_code }}
              </h3>
              <span
                v-if="!link.is_active"
                class="px-1.5 py-0.5 text-xs bg-gray-500/20 text-gray-400 rounded"
              >
                Inaktiv
              </span>
              <span
                v-else-if="isExpired(link)"
                class="px-1.5 py-0.5 text-xs bg-red-500/20 text-red-400 rounded"
              >
                Abgelaufen
              </span>
              <span
                v-else-if="isLimitReached(link)"
                class="px-1.5 py-0.5 text-xs bg-yellow-500/20 text-yellow-400 rounded"
              >
                Limit erreicht
              </span>
              <LockClosedIcon
                v-if="link.password_hash"
                class="w-4 h-4 text-gray-500"
                title="Passwortgeschützt"
              />
            </div>

            <div class="flex items-center gap-2 mb-2">
              <a
                :href="getShortUrl(link.short_code)"
                target="_blank"
                class="text-primary-400 hover:text-primary-300 text-sm font-mono"
              >
                {{ getShortUrl(link.short_code) }}
              </a>
              <button
                @click="copyToClipboard(getShortUrl(link.short_code))"
                class="p-1 text-gray-500 hover:text-white rounded"
                title="Kopieren"
              >
                <ClipboardDocumentIcon class="w-4 h-4" />
              </button>
            </div>

            <p class="text-sm text-gray-500 truncate">
              {{ link.original_url }}
            </p>

            <div class="flex items-center gap-4 mt-2 text-xs text-gray-500">
              <span class="flex items-center gap-1">
                <CursorArrowRaysIcon class="w-3 h-3" />
                {{ link.click_count }} Klicks
              </span>
              <span class="flex items-center gap-1">
                <CalendarIcon class="w-3 h-3" />
                {{ formatDate(link.created_at) }}
              </span>
              <span v-if="link.expires_at" class="flex items-center gap-1">
                <ClockIcon class="w-3 h-3" />
                Läuft ab: {{ formatDate(link.expires_at) }}
              </span>
              <span v-if="link.max_clicks" class="flex items-center gap-1">
                Max: {{ link.max_clicks }}
              </span>
            </div>
          </div>

          <div class="flex items-center gap-2 ml-4">
            <button
              @click="viewStats(link)"
              class="p-2 text-gray-400 hover:text-white hover:bg-dark-600 rounded-lg"
              title="Statistiken"
            >
              <ChartBarIcon class="w-5 h-5" />
            </button>
            <button
              @click="viewQr(link)"
              class="p-2 text-gray-400 hover:text-white hover:bg-dark-600 rounded-lg"
              title="QR-Code"
            >
              <QrCodeIcon class="w-5 h-5" />
            </button>
            <button
              @click="toggleActive(link)"
              class="p-2 rounded-lg"
              :class="link.is_active
                ? 'text-green-400 hover:bg-green-500/10'
                : 'text-gray-500 hover:bg-gray-500/10'"
              :title="link.is_active ? 'Deaktivieren' : 'Aktivieren'"
            >
              <EyeIcon v-if="link.is_active" class="w-5 h-5" />
              <EyeSlashIcon v-else class="w-5 h-5" />
            </button>
            <button
              @click="deleteLink(link)"
              class="p-2 text-gray-400 hover:text-red-400 hover:bg-red-500/10 rounded-lg"
              title="Löschen"
            >
              <TrashIcon class="w-5 h-5" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Create Modal -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition ease-out duration-200"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition ease-in duration-150"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="showCreateModal"
          class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
        >
          <div class="bg-dark-800 border border-dark-700 rounded-xl shadow-2xl w-full max-w-md">
            <div class="flex items-center justify-between px-6 py-4 border-b border-dark-700">
              <h3 class="text-lg font-semibold text-white">Neuer Short Link</h3>
              <button @click="showCreateModal = false" class="text-gray-400 hover:text-white">
                <XMarkIcon class="w-5 h-5" />
              </button>
            </div>
            <form @submit.prevent="createLink" class="p-6 space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-400 mb-1">URL *</label>
                <input
                  v-model="linkForm.url"
                  type="url"
                  class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white"
                  placeholder="https://example.com/lange-url"
                  required
                />
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-400 mb-1">Titel (optional)</label>
                <input
                  v-model="linkForm.title"
                  type="text"
                  class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white"
                  placeholder="Mein Link"
                />
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-400 mb-1">Custom Code (optional)</label>
                <div class="flex items-center gap-2">
                  <span class="text-gray-500 text-sm">{{ baseUrl }}/s/</span>
                  <input
                    v-model="linkForm.custom_code"
                    type="text"
                    class="flex-1 px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white font-mono"
                    placeholder="mein-link"
                    pattern="[a-zA-Z0-9_-]+"
                  />
                </div>
                <p class="text-xs text-gray-500 mt-1">Nur Buchstaben, Zahlen, - und _</p>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-400 mb-1">Passwort (optional)</label>
                <input
                  v-model="linkForm.password"
                  type="password"
                  class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white"
                  placeholder="Link mit Passwort schützen"
                />
              </div>

              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-400 mb-1">Ablaufdatum</label>
                  <input
                    v-model="linkForm.expires_at"
                    type="datetime-local"
                    class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white"
                  />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-400 mb-1">Max. Klicks</label>
                  <input
                    v-model.number="linkForm.max_clicks"
                    type="number"
                    min="1"
                    class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white"
                    placeholder="Unbegrenzt"
                  />
                </div>
              </div>

              <div class="flex gap-2 pt-4">
                <button type="button" @click="showCreateModal = false" class="btn-secondary flex-1">
                  Abbrechen
                </button>
                <button type="submit" class="btn-primary flex-1" :disabled="isProcessing">
                  <ArrowPathIcon v-if="isProcessing" class="w-4 h-4 mr-2 animate-spin" />
                  <LinkIcon v-else class="w-4 h-4 mr-2" />
                  Erstellen
                </button>
              </div>
            </form>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Stats Modal -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition ease-out duration-200"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition ease-in duration-150"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="showStatsModal"
          class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
        >
          <div class="bg-dark-800 border border-dark-700 rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col">
            <div class="flex items-center justify-between px-6 py-4 border-b border-dark-700">
              <h3 class="text-lg font-semibold text-white">
                Statistiken: {{ selectedLink?.title || selectedLink?.short_code }}
              </h3>
              <button @click="showStatsModal = false" class="text-gray-400 hover:text-white">
                <XMarkIcon class="w-5 h-5" />
              </button>
            </div>

            <div class="p-6 overflow-y-auto">
              <div v-if="!linkStats" class="text-center py-8">
                <ArrowPathIcon class="w-8 h-8 text-gray-500 mx-auto animate-spin" />
              </div>

              <div v-else class="space-y-6">
                <!-- Total clicks -->
                <div class="text-center p-6 bg-dark-700/50 rounded-lg">
                  <p class="text-4xl font-bold text-white">{{ linkStats.total_clicks.toLocaleString() }}</p>
                  <p class="text-gray-400">Gesamt Klicks</p>
                </div>

                <!-- Devices -->
                <div>
                  <h4 class="text-white font-medium mb-3">Geräte</h4>
                  <div class="grid grid-cols-3 gap-3">
                    <div
                      v-for="device in linkStats.devices"
                      :key="device.device_type"
                      class="bg-dark-700/50 rounded-lg p-3 text-center"
                    >
                      <component
                        :is="device.device_type === 'mobile' ? DevicePhoneMobileIcon : ComputerDesktopIcon"
                        class="w-6 h-6 mx-auto text-gray-400 mb-1"
                      />
                      <p class="text-lg font-bold text-white">{{ device.count }}</p>
                      <p class="text-xs text-gray-500 capitalize">{{ device.device_type }}</p>
                    </div>
                  </div>
                </div>

                <!-- Browsers -->
                <div v-if="linkStats.browsers?.length">
                  <h4 class="text-white font-medium mb-3">Browser</h4>
                  <div class="space-y-2">
                    <div
                      v-for="browser in linkStats.browsers"
                      :key="browser.browser"
                      class="flex items-center justify-between"
                    >
                      <span class="text-gray-300">{{ browser.browser }}</span>
                      <span class="text-gray-500">{{ browser.count }}</span>
                    </div>
                  </div>
                </div>

                <!-- OS -->
                <div v-if="linkStats.operating_systems?.length">
                  <h4 class="text-white font-medium mb-3">Betriebssysteme</h4>
                  <div class="space-y-2">
                    <div
                      v-for="os in linkStats.operating_systems"
                      :key="os.os"
                      class="flex items-center justify-between"
                    >
                      <span class="text-gray-300">{{ os.os }}</span>
                      <span class="text-gray-500">{{ os.count }}</span>
                    </div>
                  </div>
                </div>

                <!-- Referrers -->
                <div v-if="linkStats.referrers?.length">
                  <h4 class="text-white font-medium mb-3">Referrer</h4>
                  <div class="space-y-2">
                    <div
                      v-for="ref in linkStats.referrers"
                      :key="ref.referrer"
                      class="flex items-center justify-between"
                    >
                      <span class="text-gray-300 truncate">{{ ref.referrer }}</span>
                      <span class="text-gray-500">{{ ref.count }}</span>
                    </div>
                  </div>
                </div>

                <!-- Recent clicks -->
                <div v-if="linkStats.recent_clicks?.length">
                  <h4 class="text-white font-medium mb-3">Letzte Klicks</h4>
                  <div class="space-y-2 max-h-48 overflow-y-auto">
                    <div
                      v-for="click in linkStats.recent_clicks.slice(0, 10)"
                      :key="click.id"
                      class="flex items-center justify-between text-sm bg-dark-700/50 rounded px-3 py-2"
                    >
                      <span class="text-gray-300">{{ click.browser }} / {{ click.os }}</span>
                      <span class="text-gray-500">{{ formatDateTime(click.clicked_at) }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- QR Modal -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition ease-out duration-200"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition ease-in duration-150"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="showQrModal"
          class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
        >
          <div class="bg-dark-800 border border-dark-700 rounded-xl shadow-2xl w-full max-w-sm">
            <div class="flex items-center justify-between px-6 py-4 border-b border-dark-700">
              <h3 class="text-lg font-semibold text-white">QR-Code</h3>
              <button @click="showQrModal = false" class="text-gray-400 hover:text-white">
                <XMarkIcon class="w-5 h-5" />
              </button>
            </div>

            <div class="p-6 text-center">
              <div v-if="!qrData" class="py-8">
                <ArrowPathIcon class="w-8 h-8 text-gray-500 mx-auto animate-spin" />
              </div>

              <template v-else>
                <div class="bg-white p-4 rounded-lg inline-block mb-4">
                  <img :src="qrData.qr_url" alt="QR Code" class="w-48 h-48" />
                </div>

                <p class="text-sm text-gray-400 mb-4 font-mono">{{ qrData.short_url }}</p>

                <div class="flex gap-2">
                  <button
                    @click="copyToClipboard(qrData.short_url)"
                    class="btn-secondary flex-1"
                  >
                    <ClipboardDocumentIcon class="w-4 h-4 mr-2" />
                    URL kopieren
                  </button>
                  <a
                    :href="qrData.qr_url"
                    download="qr-code.png"
                    class="btn-primary flex-1"
                  >
                    QR herunterladen
                  </a>
                </div>
              </template>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>
