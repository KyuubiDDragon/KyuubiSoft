<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/core/api/axios'
import {
  PlusIcon,
  ArrowPathIcon,
  TrashIcon,
  PencilIcon,
  ShieldCheckIcon,
  ShieldExclamationIcon,
  ExclamationTriangleIcon,
  CheckCircleIcon,
  XCircleIcon,
  ClockIcon,
  FolderPlusIcon,
  FolderIcon,
  ChevronDownIcon,
  ChevronRightIcon,
} from '@heroicons/vue/24/outline'

// State
const certificates = ref([])
const folders = ref([])
const stats = ref(null)
const isLoading = ref(true)
const showModal = ref(false)
const showFolderModal = ref(false)
const showDetailModal = ref(false)
const editingCert = ref(null)
const selectedCert = ref(null)
const checkingId = ref(null)

// Form
const form = ref({
  name: '',
  hostname: '',
  port: 443,
  warn_days_before: 30,
  critical_days_before: 7,
  check_interval: 86400,
  notify_on_expiry_warning: true,
  notify_on_expiry_critical: true,
  notify_on_expired: true,
  notify_on_renewed: true,
  project_id: '',
  folder_id: '',
})

const folderForm = ref({
  name: '',
  color: '#6366F1',
})

const folderColors = [
  '#6366F1', '#8B5CF6', '#EC4899', '#EF4444', '#F97316',
  '#EAB308', '#22C55E', '#14B8A6', '#06B6D4', '#3B82F6',
]

// Computed
const groupedCerts = computed(() => {
  const groups = {}
  folders.value.forEach(folder => {
    groups[folder.id] = { folder, certificates: [], isCollapsed: folder.is_collapsed }
  })
  groups['unfiled'] = { folder: null, certificates: [], isCollapsed: false }

  certificates.value.forEach(cert => {
    const folderId = cert.folder_id || 'unfiled'
    if (groups[folderId]) {
      groups[folderId].certificates.push(cert)
    }
  })
  return groups
})

// Methods
const loadCertificates = async () => {
  try {
    isLoading.value = true
    const response = await api.get('/api/v1/ssl/certificates')
    certificates.value = response.data.data.items
    folders.value = response.data.data.folders
    stats.value = response.data.data.stats
  } catch (error) {
    console.error('Failed to load certificates:', error)
  } finally {
    isLoading.value = false
  }
}

const loadCertDetails = async (id) => {
  try {
    const response = await api.get(`/api/v1/ssl/certificates/${id}`)
    selectedCert.value = response.data.data
    showDetailModal.value = true
  } catch (error) {
    console.error('Failed to load certificate details:', error)
  }
}

const saveCertificate = async () => {
  try {
    if (editingCert.value) {
      await api.put(`/api/v1/ssl/certificates/${editingCert.value.id}`, form.value)
    } else {
      await api.post('/api/v1/ssl/certificates', form.value)
    }
    showModal.value = false
    resetForm()
    await loadCertificates()
  } catch (error) {
    console.error('Failed to save certificate:', error)
  }
}

const deleteCertificate = async (id) => {
  if (!confirm('Zertifikat wirklich entfernen?')) return
  try {
    await api.delete(`/api/v1/ssl/certificates/${id}`)
    await loadCertificates()
  } catch (error) {
    console.error('Failed to delete certificate:', error)
  }
}

const checkCertificate = async (id) => {
  try {
    checkingId.value = id
    await api.post(`/api/v1/ssl/certificates/${id}/check`)
    await loadCertificates()
  } catch (error) {
    console.error('Failed to check certificate:', error)
  } finally {
    checkingId.value = null
  }
}

const checkAllCertificates = async () => {
  try {
    checkingId.value = 'all'
    await api.post('/api/v1/ssl/certificates/check-all')
    await loadCertificates()
  } catch (error) {
    console.error('Failed to check all certificates:', error)
  } finally {
    checkingId.value = null
  }
}

const saveFolder = async () => {
  try {
    await api.post('/api/v1/ssl/folders', folderForm.value)
    showFolderModal.value = false
    folderForm.value = { name: '', color: '#6366F1' }
    await loadCertificates()
  } catch (error) {
    console.error('Failed to save folder:', error)
  }
}

const toggleFolderCollapse = async (folder) => {
  folder.is_collapsed = !folder.is_collapsed
  try {
    await api.put(`/api/v1/ssl/folders/${folder.id}`, { is_collapsed: folder.is_collapsed })
  } catch (error) {
    console.error('Failed to update folder:', error)
  }
}

const editCertificate = (cert) => {
  editingCert.value = cert
  form.value = { ...cert }
  showModal.value = true
}

const resetForm = () => {
  editingCert.value = null
  form.value = {
    name: '',
    hostname: '',
    port: 443,
    warn_days_before: 30,
    critical_days_before: 7,
    check_interval: 86400,
    notify_on_expiry_warning: true,
    notify_on_expiry_critical: true,
    notify_on_expired: true,
    notify_on_renewed: true,
    project_id: '',
    folder_id: '',
  }
}

const getStatusColor = (status) => {
  const colors = {
    valid: 'text-green-600 bg-green-100 dark:bg-green-900/30',
    expiring_soon: 'text-amber-600 bg-amber-100 dark:bg-amber-900/30',
    expired: 'text-red-600 bg-red-100 dark:bg-red-900/30',
    invalid: 'text-red-600 bg-red-100 dark:bg-red-900/30',
    error: 'text-gray-600 bg-gray-100 dark:bg-gray-700',
    pending: 'text-gray-500 bg-gray-100 dark:bg-gray-700',
  }
  return colors[status] || colors.pending
}

const getStatusIcon = (status) => {
  const icons = {
    valid: CheckCircleIcon,
    expiring_soon: ExclamationTriangleIcon,
    expired: XCircleIcon,
    invalid: ShieldExclamationIcon,
    error: XCircleIcon,
    pending: ClockIcon,
  }
  return icons[status] || ClockIcon
}

const getStatusLabel = (status) => {
  const labels = {
    valid: 'Gültig',
    expiring_soon: 'Läuft bald ab',
    expired: 'Abgelaufen',
    invalid: 'Ungültig',
    error: 'Fehler',
    pending: 'Ausstehend',
  }
  return labels[status] || status
}

const formatDate = (date) => {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('de-DE')
}

const getDaysColor = (days) => {
  if (days === null || days === undefined) return 'text-gray-500'
  if (days < 0) return 'text-red-600'
  if (days <= 7) return 'text-red-600'
  if (days <= 30) return 'text-amber-600'
  return 'text-green-600'
}

onMounted(() => {
  loadCertificates()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex justify-between items-center">
      <div>
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">SSL Zertifikate</h1>
        <p class="text-gray-500 dark:text-gray-400">Überwache SSL-Zertifikate und erhalte Benachrichtigungen vor Ablauf</p>
      </div>
      <div class="flex gap-2">
        <button @click="checkAllCertificates" :disabled="checkingId === 'all'" class="btn-secondary">
          <ArrowPathIcon class="w-5 h-5 mr-2" :class="{ 'animate-spin': checkingId === 'all' }" />
          Alle prüfen
        </button>
        <button @click="showFolderModal = true" class="btn-secondary">
          <FolderPlusIcon class="w-5 h-5 mr-2" />
          Ordner
        </button>
        <button @click="showModal = true" class="btn-primary">
          <PlusIcon class="w-5 h-5 mr-2" />
          Zertifikat
        </button>
      </div>
    </div>

    <!-- Stats -->
    <div v-if="stats" class="grid grid-cols-5 gap-4">
      <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
        <div class="text-gray-500 dark:text-gray-400 text-sm">Gesamt</div>
        <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.total }}</div>
      </div>
      <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4 shadow">
        <div class="text-green-600 dark:text-green-400 text-sm">Gültig</div>
        <div class="text-2xl font-bold text-green-700 dark:text-green-300">{{ stats.valid }}</div>
      </div>
      <div class="bg-amber-50 dark:bg-amber-900/20 rounded-lg p-4 shadow">
        <div class="text-amber-600 dark:text-amber-400 text-sm">Bald ablaufend</div>
        <div class="text-2xl font-bold text-amber-700 dark:text-amber-300">{{ stats.expiring_soon }}</div>
      </div>
      <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4 shadow">
        <div class="text-red-600 dark:text-red-400 text-sm">Abgelaufen</div>
        <div class="text-2xl font-bold text-red-700 dark:text-red-300">{{ stats.expired }}</div>
      </div>
      <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 shadow">
        <div class="text-gray-500 dark:text-gray-400 text-sm">Fehler</div>
        <div class="text-2xl font-bold text-gray-700 dark:text-gray-300">{{ stats.error }}</div>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="flex justify-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
    </div>

    <!-- Certificate List -->
    <div v-else class="space-y-4">
      <template v-for="(group, folderId) in groupedCerts" :key="folderId">
        <div v-if="group.certificates.length > 0 || group.folder"
             class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
          <!-- Folder Header -->
          <div v-if="group.folder"
               class="px-4 py-3 bg-gray-50 dark:bg-gray-700 cursor-pointer flex items-center justify-between"
               @click="toggleFolderCollapse(group.folder)">
            <div class="flex items-center gap-2">
              <FolderIcon class="w-5 h-5" :style="{ color: group.folder.color }" />
              <span class="font-medium text-gray-900 dark:text-white">{{ group.folder.name }}</span>
              <span class="text-sm text-gray-500">({{ group.certificates.length }})</span>
            </div>
            <component :is="group.isCollapsed ? ChevronRightIcon : ChevronDownIcon" class="w-5 h-5 text-gray-400" />
          </div>
          <div v-else class="px-4 py-3 bg-gray-50 dark:bg-gray-700">
            <span class="font-medium text-gray-900 dark:text-white">Ohne Ordner</span>
            <span class="text-sm text-gray-500 ml-2">({{ group.certificates.length }})</span>
          </div>

          <!-- Certificates -->
          <div v-if="!group.isCollapsed" class="divide-y divide-gray-200 dark:divide-gray-700">
            <div v-for="cert in group.certificates" :key="cert.id"
                 class="p-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 flex items-center justify-between">
              <div class="flex items-center gap-4 flex-1 cursor-pointer" @click="loadCertDetails(cert.id)">
                <div class="w-10 h-10 rounded-lg flex items-center justify-center"
                     :class="getStatusColor(cert.current_status)">
                  <component :is="getStatusIcon(cert.current_status)" class="w-6 h-6" />
                </div>
                <div class="flex-1">
                  <div class="flex items-center gap-2">
                    <h3 class="font-medium text-gray-900 dark:text-white">{{ cert.name }}</h3>
                    <span class="text-xs px-2 py-0.5 rounded" :class="getStatusColor(cert.current_status)">
                      {{ getStatusLabel(cert.current_status) }}
                    </span>
                  </div>
                  <p class="text-sm text-gray-500 dark:text-gray-400">{{ cert.hostname }}:{{ cert.port }}</p>
                </div>
                <div class="flex items-center gap-8 text-sm">
                  <div class="text-center">
                    <div class="text-gray-500 text-xs">Tage bis Ablauf</div>
                    <div class="font-bold text-lg" :class="getDaysColor(cert.days_until_expiry)">
                      {{ cert.days_until_expiry ?? '-' }}
                    </div>
                  </div>
                  <div class="text-center">
                    <div class="text-gray-500 text-xs">Gültig bis</div>
                    <div class="font-medium text-gray-700 dark:text-gray-300">
                      {{ formatDate(cert.valid_until) }}
                    </div>
                  </div>
                  <div class="text-center">
                    <div class="text-gray-500 text-xs">Aussteller</div>
                    <div class="font-medium text-gray-700 dark:text-gray-300 max-w-32 truncate">
                      {{ cert.issuer || '-' }}
                    </div>
                  </div>
                </div>
              </div>
              <div class="flex items-center gap-2 ml-4">
                <button @click.stop="checkCertificate(cert.id)"
                        :disabled="checkingId === cert.id"
                        class="p-2 text-gray-400 hover:text-indigo-600 disabled:opacity-50">
                  <ArrowPathIcon class="w-5 h-5" :class="{ 'animate-spin': checkingId === cert.id }" />
                </button>
                <button @click.stop="editCertificate(cert)" class="p-2 text-gray-400 hover:text-indigo-600">
                  <PencilIcon class="w-5 h-5" />
                </button>
                <button @click.stop="deleteCertificate(cert.id)" class="p-2 text-gray-400 hover:text-red-600">
                  <TrashIcon class="w-5 h-5" />
                </button>
              </div>
            </div>
          </div>
        </div>
      </template>

      <!-- Empty State -->
      <div v-if="certificates.length === 0"
           class="text-center py-12 bg-white dark:bg-gray-800 rounded-lg shadow">
        <ShieldCheckIcon class="w-12 h-12 mx-auto text-gray-400" />
        <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">Keine Zertifikate</h3>
        <p class="mt-2 text-gray-500">Füge dein erstes SSL-Zertifikat zur Überwachung hinzu.</p>
        <button @click="showModal = true" class="mt-4 btn-primary">
          <PlusIcon class="w-5 h-5 mr-2" />
          Zertifikat hinzufügen
        </button>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4">
          <div class="fixed inset-0 bg-black/50" @click="showModal = false"></div>
          <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-lg p-6">
            <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4">
              {{ editingCert ? 'Zertifikat bearbeiten' : 'Neues Zertifikat' }}
            </h2>
            <form @submit.prevent="saveCertificate" class="space-y-4">
              <div>
                <label class="label">Name</label>
                <input v-model="form.name" type="text" required class="input" />
              </div>
              <div class="grid grid-cols-3 gap-4">
                <div class="col-span-2">
                  <label class="label">Hostname</label>
                  <input v-model="form.hostname" type="text" required placeholder="example.com" class="input" />
                </div>
                <div>
                  <label class="label">Port</label>
                  <input v-model.number="form.port" type="number" min="1" max="65535" class="input" />
                </div>
              </div>
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="label">Warnung (Tage)</label>
                  <input v-model.number="form.warn_days_before" type="number" min="1" class="input" />
                </div>
                <div>
                  <label class="label">Kritisch (Tage)</label>
                  <input v-model.number="form.critical_days_before" type="number" min="1" class="input" />
                </div>
              </div>
              <div class="flex flex-wrap items-center gap-4">
                <label class="flex items-center gap-2">
                  <input v-model="form.notify_on_expiry_warning" type="checkbox" class="rounded" />
                  <span class="text-sm text-gray-700 dark:text-gray-300">Warnung</span>
                </label>
                <label class="flex items-center gap-2">
                  <input v-model="form.notify_on_expiry_critical" type="checkbox" class="rounded" />
                  <span class="text-sm text-gray-700 dark:text-gray-300">Kritisch</span>
                </label>
                <label class="flex items-center gap-2">
                  <input v-model="form.notify_on_expired" type="checkbox" class="rounded" />
                  <span class="text-sm text-gray-700 dark:text-gray-300">Abgelaufen</span>
                </label>
                <label class="flex items-center gap-2">
                  <input v-model="form.notify_on_renewed" type="checkbox" class="rounded" />
                  <span class="text-sm text-gray-700 dark:text-gray-300">Erneuert</span>
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
          <div class="fixed inset-0 bg-black/50" @click="showFolderModal = false"></div>
          <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md p-6">
            <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4">Neuer Ordner</h2>
            <form @submit.prevent="saveFolder" class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Name</label>
                <input v-model="folderForm.name" type="text" required
                       class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Farbe</label>
                <div class="flex gap-2">
                  <button v-for="color in folderColors" :key="color" type="button"
                          @click="folderForm.color = color"
                          class="w-8 h-8 rounded-full border-2"
                          :class="folderForm.color === color ? 'border-gray-900 dark:border-white' : 'border-transparent'"
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
      <div v-if="showDetailModal && selectedCert" class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4">
          <div class="fixed inset-0 bg-black/50" @click="showDetailModal = false"></div>
          <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-2xl p-6">
            <div class="flex justify-between items-start mb-6">
              <div>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">{{ selectedCert.certificate.name }}</h2>
                <p class="text-gray-500">{{ selectedCert.certificate.hostname }}:{{ selectedCert.certificate.port }}</p>
              </div>
              <button @click="showDetailModal = false" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
            </div>

            <!-- Certificate Info -->
            <div class="grid grid-cols-2 gap-4 mb-6">
              <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                <div class="text-sm text-gray-500">Subject</div>
                <div class="font-medium">{{ selectedCert.certificate.subject || '-' }}</div>
              </div>
              <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                <div class="text-sm text-gray-500">Issuer</div>
                <div class="font-medium">{{ selectedCert.certificate.issuer || '-' }}</div>
              </div>
              <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                <div class="text-sm text-gray-500">Gültig von</div>
                <div class="font-medium">{{ formatDate(selectedCert.certificate.valid_from) }}</div>
              </div>
              <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                <div class="text-sm text-gray-500">Gültig bis</div>
                <div class="font-medium">{{ formatDate(selectedCert.certificate.valid_until) }}</div>
              </div>
            </div>

            <!-- SANs -->
            <div v-if="selectedCert.certificate.san_domains?.length" class="mb-6">
              <h3 class="font-semibold text-gray-900 dark:text-white mb-2">Subject Alternative Names</h3>
              <div class="flex flex-wrap gap-2">
                <span v-for="san in selectedCert.certificate.san_domains" :key="san"
                      class="px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded text-sm">
                  {{ san }}
                </span>
              </div>
            </div>

            <!-- Recent Checks -->
            <div>
              <h3 class="font-semibold text-gray-900 dark:text-white mb-2">Letzte Prüfungen</h3>
              <div class="space-y-2 max-h-40 overflow-y-auto">
                <div v-for="check in selectedCert.checks?.slice(0, 10)" :key="check.id"
                     class="flex items-center justify-between p-2 bg-gray-50 dark:bg-gray-700 rounded">
                  <div class="flex items-center gap-2">
                    <component :is="getStatusIcon(check.status)" class="w-4 h-4" :class="getStatusColor(check.status).split(' ')[0]" />
                    <span class="text-sm">{{ getStatusLabel(check.status) }}</span>
                  </div>
                  <div class="text-sm text-gray-500">
                    {{ check.response_time_ms }}ms - {{ formatDate(check.checked_at) }}
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

<style scoped>
.btn-primary {
  @apply inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors;
}
.btn-secondary {
  @apply inline-flex items-center px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors;
}
</style>
