<script setup>
import { ref, computed, onMounted } from 'vue'
import {
  LinkIcon,
  EyeIcon,
  ArrowDownTrayIcon,
  ClipboardDocumentIcon,
  CheckIcon,
  TrashIcon,
  LockClosedIcon,
  ClockIcon,
  ExclamationTriangleIcon,
  XCircleIcon,
  CheckCircleIcon,
  FunnelIcon,
} from '@heroicons/vue/24/outline'
import api from '@/core/api/axios'
import { useUiStore } from '@/stores/ui'
import { useToast } from '@/composables/useToast'
import { useConfirmDialog } from '@/composables/useConfirmDialog'

const uiStore = useUiStore()
const toast = useToast()
const { confirm } = useConfirmDialog()

// State
const shares = ref([])
const summary = ref({ total: 0, active: 0, inactive: 0, expired: 0, total_views: 0, total_downloads: 0 })
const isLoading = ref(true)
const statusFilter = ref('all')
const copiedToken = ref(null)

// Computed
const filteredShares = computed(() => {
  if (statusFilter.value === 'all') return shares.value
  return shares.value.filter(s => s.status === statusFilter.value)
})

// API Functions
async function loadShares() {
  isLoading.value = true
  try {
    const response = await api.get('/api/v1/storage/shares')
    shares.value = response.data.data.items || []
    summary.value = response.data.data.summary || {}
  } catch (error) {
    uiStore.showError('Fehler beim Laden der Freigaben')
  } finally {
    isLoading.value = false
  }
}

async function toggleShareActive(share) {
  try {
    const response = await api.put(`/api/v1/storage/${share.file_id}/share`, {
      is_active: !share.is_active,
    })
    // Update local state
    const index = shares.value.findIndex(s => s.id === share.id)
    if (index !== -1) {
      shares.value[index].is_active = response.data.data.is_active
      shares.value[index].status = response.data.data.is_active ? 'active' : 'inactive'
    }
    uiStore.showSuccess(response.data.data.is_active ? 'Freigabe aktiviert' : 'Freigabe deaktiviert')
    loadShares() // Reload to update summary
  } catch (error) {
    uiStore.showError('Fehler beim Ändern des Status')
  }
}

async function deleteShare(share) {
  if (!await confirm({ message: `Freigabe für "${share.file.name}" wirklich löschen?`, type: 'danger', confirmText: 'Löschen' })) return

  try {
    await api.delete(`/api/v1/storage/${share.file_id}/share`)
    shares.value = shares.value.filter(s => s.id !== share.id)
    uiStore.showSuccess('Freigabe gelöscht')
    loadShares() // Reload to update summary
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
  }
}

function copyShareLink(share) {
  const url = `${window.location.origin}${share.share_url}`
  navigator.clipboard.writeText(url)
  copiedToken.value = share.share_token
  setTimeout(() => { copiedToken.value = null }, 2000)
}

function getStatusColor(status) {
  switch (status) {
    case 'active': return 'text-green-400 bg-green-400/10'
    case 'inactive': return 'text-gray-400 bg-gray-400/10'
    case 'expired': return 'text-red-400 bg-red-400/10'
    case 'limit_reached': return 'text-orange-400 bg-orange-400/10'
    default: return 'text-gray-400 bg-gray-400/10'
  }
}

function getStatusIcon(status) {
  switch (status) {
    case 'active': return CheckCircleIcon
    case 'inactive': return XCircleIcon
    case 'expired': return ClockIcon
    case 'limit_reached': return ExclamationTriangleIcon
    default: return LinkIcon
  }
}

function getStatusLabel(status) {
  switch (status) {
    case 'active': return 'Aktiv'
    case 'inactive': return 'Deaktiviert'
    case 'expired': return 'Abgelaufen'
    case 'limit_reached': return 'Limit erreicht'
    default: return status
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
  loadShares()
})
</script>

<template>
  <div class="space-y-4">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
      <h1 class="text-2xl font-bold text-white">Freigaben</h1>

      <!-- Filter -->
      <div class="flex items-center gap-2">
        <FunnelIcon class="w-4 h-4 text-gray-400" />
        <select
          v-model="statusFilter"
          class="px-3 py-1.5 text-sm bg-dark-700 border border-dark-600 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary-500"
        >
          <option value="all">Alle ({{ summary.total }})</option>
          <option value="active">Aktiv ({{ summary.active }})</option>
          <option value="inactive">Deaktiviert ({{ summary.inactive }})</option>
          <option value="expired">Abgelaufen ({{ summary.expired }})</option>
        </select>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
      <div class="bg-dark-800 rounded-lg p-3 border border-dark-700">
        <div class="flex items-center gap-2">
          <LinkIcon class="w-5 h-5 text-primary-400" />
          <span class="text-gray-400 text-sm">Gesamt</span>
        </div>
        <p class="text-xl font-bold text-white mt-1">{{ summary.total }}</p>
      </div>
      <div class="bg-dark-800 rounded-lg p-3 border border-dark-700">
        <div class="flex items-center gap-2">
          <CheckCircleIcon class="w-5 h-5 text-green-400" />
          <span class="text-gray-400 text-sm">Aktiv</span>
        </div>
        <p class="text-xl font-bold text-white mt-1">{{ summary.active }}</p>
      </div>
      <div class="bg-dark-800 rounded-lg p-3 border border-dark-700">
        <div class="flex items-center gap-2">
          <EyeIcon class="w-5 h-5 text-blue-400" />
          <span class="text-gray-400 text-sm">Aufrufe</span>
        </div>
        <p class="text-xl font-bold text-white mt-1">{{ summary.total_views }}</p>
      </div>
      <div class="bg-dark-800 rounded-lg p-3 border border-dark-700">
        <div class="flex items-center gap-2">
          <ArrowDownTrayIcon class="w-5 h-5 text-orange-400" />
          <span class="text-gray-400 text-sm">Downloads</span>
        </div>
        <p class="text-xl font-bold text-white mt-1">{{ summary.total_downloads }}</p>
      </div>
    </div>

    <!-- Shares List -->
    <div class="bg-dark-800 rounded-xl border border-dark-700">
      <!-- Loading -->
      <div v-if="isLoading" class="p-12 text-center">
        <div class="w-8 h-8 border-2 border-primary-500 border-t-transparent rounded-full animate-spin mx-auto"></div>
        <p class="text-gray-400 mt-4">Lade Freigaben...</p>
      </div>

      <!-- Empty State -->
      <div v-else-if="filteredShares.length === 0" class="p-12 text-center">
        <LinkIcon class="w-16 h-16 mx-auto text-gray-600 mb-4" />
        <p class="text-lg text-white font-medium">Keine Freigaben vorhanden</p>
        <p class="text-gray-400">Erstelle Freigaben im Cloud Storage</p>
      </div>

      <!-- Shares Table -->
      <div v-else class="overflow-x-auto">
        <table class="w-full">
          <thead class="bg-dark-700/50">
            <tr>
              <th class="text-left px-4 py-3 text-xs font-medium text-gray-400 uppercase">Datei</th>
              <th class="text-left px-4 py-3 text-xs font-medium text-gray-400 uppercase">Status</th>
              <th class="text-center px-4 py-3 text-xs font-medium text-gray-400 uppercase">Aufrufe</th>
              <th class="text-center px-4 py-3 text-xs font-medium text-gray-400 uppercase">Downloads</th>
              <th class="text-left px-4 py-3 text-xs font-medium text-gray-400 uppercase">Erstellt</th>
              <th class="text-left px-4 py-3 text-xs font-medium text-gray-400 uppercase">Läuft ab</th>
              <th class="text-right px-4 py-3 text-xs font-medium text-gray-400 uppercase">Aktionen</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-dark-700">
            <tr
              v-for="share in filteredShares"
              :key="share.id"
              class="hover:bg-dark-700/30 transition-colors"
            >
              <!-- File -->
              <td class="px-4 py-3">
                <div class="flex items-center gap-3">
                  <div>
                    <p class="text-white font-medium text-sm">{{ share.file.name }}</p>
                    <p class="text-gray-500 text-xs">{{ formatSize(share.file.size) }}</p>
                  </div>
                </div>
              </td>

              <!-- Status -->
              <td class="px-4 py-3">
                <div class="flex items-center gap-2">
                  <span
                    class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium"
                    :class="getStatusColor(share.status)"
                  >
                    <component :is="getStatusIcon(share.status)" class="w-3.5 h-3.5" />
                    {{ getStatusLabel(share.status) }}
                  </span>
                  <LockClosedIcon v-if="share.has_password" class="w-4 h-4 text-gray-500" title="Passwortgeschützt" />
                </div>
              </td>

              <!-- Views -->
              <td class="px-4 py-3 text-center">
                <span class="text-white font-medium">{{ share.view_count }}</span>
              </td>

              <!-- Downloads -->
              <td class="px-4 py-3 text-center">
                <div class="flex items-center justify-center gap-1">
                  <span class="text-white font-medium">{{ share.download_count }}</span>
                  <span v-if="share.max_downloads" class="text-gray-500 text-xs">/ {{ share.max_downloads }}</span>
                </div>
              </td>

              <!-- Created -->
              <td class="px-4 py-3">
                <span class="text-gray-400 text-sm">{{ formatDate(share.created_at) }}</span>
              </td>

              <!-- Expires -->
              <td class="px-4 py-3">
                <span
                  class="text-sm"
                  :class="share.status === 'expired' ? 'text-red-400' : 'text-gray-400'"
                >
                  {{ share.expires_at ? formatDate(share.expires_at) : 'Nie' }}
                </span>
              </td>

              <!-- Actions -->
              <td class="px-4 py-3">
                <div class="flex items-center justify-end gap-1">
                  <!-- Copy Link -->
                  <button
                    @click="copyShareLink(share)"
                    class="p-1.5 hover:bg-dark-600 rounded transition-colors"
                    :title="copiedToken === share.share_token ? 'Kopiert!' : 'Link kopieren'"
                  >
                    <CheckIcon v-if="copiedToken === share.share_token" class="w-4 h-4 text-green-500" />
                    <ClipboardDocumentIcon v-else class="w-4 h-4 text-gray-400 hover:text-white" />
                  </button>

                  <!-- Toggle Active -->
                  <button
                    @click="toggleShareActive(share)"
                    class="p-1.5 hover:bg-dark-600 rounded transition-colors"
                    :title="share.is_active ? 'Deaktivieren' : 'Aktivieren'"
                  >
                    <CheckCircleIcon v-if="share.is_active" class="w-4 h-4 text-green-400 hover:text-green-300" />
                    <XCircleIcon v-else class="w-4 h-4 text-gray-400 hover:text-white" />
                  </button>

                  <!-- Delete -->
                  <button
                    @click="deleteShare(share)"
                    class="p-1.5 hover:bg-dark-600 rounded transition-colors"
                    title="Löschen"
                  >
                    <TrashIcon class="w-4 h-4 text-gray-400 hover:text-red-400" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
