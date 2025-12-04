<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/core/api/axios'
import {
  ServerStackIcon,
  PlusIcon,
  PencilIcon,
  TrashIcon,
  CheckCircleIcon,
  XCircleIcon,
  ArrowPathIcon,
  StarIcon,
  ComputerDesktopIcon,
  GlobeAltIcon,
  FolderIcon,
} from '@heroicons/vue/24/outline'
import { StarIcon as StarIconSolid } from '@heroicons/vue/24/solid'

const hosts = ref([])
const projects = ref([])
const loading = ref(false)
const showModal = ref(false)
const editMode = ref(false)
const testingConnection = ref(null)

const form = ref({
  id: null,
  name: '',
  description: '',
  project_id: '',
  type: 'socket',
  socket_path: '/var/run/docker.sock',
  tcp_host: '',
  tcp_port: 2375,
  tls_enabled: false,
  tls_ca_cert: '',
  tls_cert: '',
  tls_key: '',
})

const groupedHosts = computed(() => {
  const grouped = {
    no_project: {
      project_id: null,
      project_name: 'Ohne Projekt',
      project_color: '#6b7280',
      hosts: [],
    },
  }

  hosts.value.forEach((host) => {
    if (host.project_id) {
      if (!grouped[host.project_id]) {
        grouped[host.project_id] = {
          project_id: host.project_id,
          project_name: host.project_name,
          project_color: host.project_color || '#6b7280',
          hosts: [],
        }
      }
      grouped[host.project_id].hosts.push(host)
    } else {
      grouped.no_project.hosts.push(host)
    }
  })

  return Object.values(grouped).filter((g) => g.hosts.length > 0)
})

async function fetchHosts() {
  loading.value = true
  try {
    const response = await api.get('/api/v1/docker/hosts')
    hosts.value = response.data.data.hosts
  } catch (error) {
    console.error('Failed to fetch Docker hosts:', error)
  } finally {
    loading.value = false
  }
}

async function fetchProjects() {
  try {
    const response = await api.get('/api/v1/projects')
    projects.value = response.data.data.projects || []
  } catch (error) {
    console.error('Failed to fetch projects:', error)
  }
}

function openCreateModal() {
  editMode.value = false
  form.value = {
    id: null,
    name: '',
    description: '',
    project_id: '',
    type: 'socket',
    socket_path: '/var/run/docker.sock',
    tcp_host: '',
    tcp_port: 2375,
    tls_enabled: false,
    tls_ca_cert: '',
    tls_cert: '',
    tls_key: '',
  }
  showModal.value = true
}

function openEditModal(host) {
  editMode.value = true
  form.value = {
    id: host.id,
    name: host.name,
    description: host.description || '',
    project_id: host.project_id || '',
    type: host.type,
    socket_path: host.socket_path || '/var/run/docker.sock',
    tcp_host: host.tcp_host || '',
    tcp_port: host.tcp_port || 2375,
    tls_enabled: !!host.tls_enabled,
    tls_ca_cert: host.tls_ca_cert || '',
    tls_cert: host.tls_cert || '',
    tls_key: host.tls_key || '',
  }
  showModal.value = true
}

async function saveHost() {
  try {
    const data = {
      name: form.value.name,
      description: form.value.description || null,
      project_id: form.value.project_id || null,
      type: form.value.type,
    }

    if (form.value.type === 'socket') {
      data.socket_path = form.value.socket_path
    } else {
      data.tcp_host = form.value.tcp_host
      data.tcp_port = form.value.tcp_port
      data.tls_enabled = form.value.tls_enabled ? 1 : 0
      if (form.value.tls_enabled) {
        data.tls_ca_cert = form.value.tls_ca_cert
        data.tls_cert = form.value.tls_cert
        data.tls_key = form.value.tls_key
      }
    }

    if (editMode.value) {
      await api.put(`/api/v1/docker/hosts/${form.value.id}`, data)
    } else {
      await api.post('/api/v1/docker/hosts', data)
    }

    showModal.value = false
    await fetchHosts()
  } catch (error) {
    console.error('Failed to save Docker host:', error)
  }
}

async function deleteHost(host) {
  if (!confirm(`Docker Host "${host.name}" wirklich löschen?`)) return

  try {
    await api.delete(`/api/v1/docker/hosts/${host.id}`)
    await fetchHosts()
  } catch (error) {
    console.error('Failed to delete Docker host:', error)
  }
}

async function setDefault(host) {
  try {
    await api.post(`/api/v1/docker/hosts/${host.id}/default`)
    await fetchHosts()
  } catch (error) {
    console.error('Failed to set default host:', error)
  }
}

async function testConnection(host) {
  testingConnection.value = host.id
  try {
    const response = await api.post(`/api/v1/docker/hosts/${host.id}/test`)
    const result = response.data.data

    // Update the host in the local state
    const index = hosts.value.findIndex((h) => h.id === host.id)
    if (index !== -1) {
      hosts.value[index].connection_status = result.connected ? 'connected' : 'error'
      hosts.value[index].last_error = result.error || null
      if (result.info) {
        hosts.value[index].docker_version = result.info.version
        hosts.value[index].api_version = result.info.api_version
        hosts.value[index].containers_count = result.info.containers
        hosts.value[index].images_count = result.info.images
      }
    }
  } catch (error) {
    console.error('Failed to test connection:', error)
  } finally {
    testingConnection.value = null
  }
}

function getStatusColor(status) {
  switch (status) {
    case 'connected':
      return 'text-green-400'
    case 'error':
      return 'text-red-400'
    default:
      return 'text-gray-400'
  }
}

function getStatusIcon(status) {
  switch (status) {
    case 'connected':
      return CheckCircleIcon
    case 'error':
      return XCircleIcon
    default:
      return XCircleIcon
  }
}

onMounted(() => {
  fetchHosts()
  fetchProjects()
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-white flex items-center gap-2">
          <ServerStackIcon class="w-7 h-7" />
          Docker Hosts
        </h1>
        <p class="text-gray-400 mt-1">Verwalte deine Docker-Hosts und Server</p>
      </div>
      <button @click="openCreateModal" class="btn btn-primary flex items-center gap-2">
        <PlusIcon class="w-5 h-5" />
        Host hinzufügen
      </button>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="flex justify-center py-12">
      <ArrowPathIcon class="w-8 h-8 text-gray-400 animate-spin" />
    </div>

    <!-- Empty State -->
    <div v-else-if="hosts.length === 0" class="card p-12 text-center">
      <ServerStackIcon class="w-16 h-16 mx-auto text-gray-500 mb-4" />
      <h3 class="text-lg font-medium text-white mb-2">Keine Docker Hosts konfiguriert</h3>
      <p class="text-gray-400 mb-6">
        Füge Docker-Hosts hinzu, um Container auf verschiedenen Servern zu verwalten.
      </p>
      <button @click="openCreateModal" class="btn btn-primary">
        <PlusIcon class="w-5 h-5 mr-2" />
        Ersten Host hinzufügen
      </button>
    </div>

    <!-- Grouped Hosts List -->
    <div v-else class="space-y-6">
      <div v-for="group in groupedHosts" :key="group.project_id || 'no_project'" class="space-y-3">
        <!-- Project Header -->
        <div class="flex items-center gap-2">
          <FolderIcon class="w-5 h-5" :style="{ color: group.project_color }" />
          <h2 class="text-lg font-medium text-white">{{ group.project_name }}</h2>
          <span class="text-sm text-gray-500">({{ group.hosts.length }})</span>
        </div>

        <!-- Hosts in this Project -->
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
          <div
            v-for="host in group.hosts"
            :key="host.id"
            class="card p-4 hover:ring-1 hover:ring-primary-500/50 transition-all"
          >
            <div class="flex items-start justify-between mb-3">
              <div class="flex items-center gap-3">
                <div
                  class="w-10 h-10 rounded-lg flex items-center justify-center"
                  :class="host.type === 'socket' ? 'bg-blue-500/20' : 'bg-purple-500/20'"
                >
                  <ComputerDesktopIcon
                    v-if="host.type === 'socket'"
                    class="w-5 h-5 text-blue-400"
                  />
                  <GlobeAltIcon v-else class="w-5 h-5 text-purple-400" />
                </div>
                <div>
                  <div class="flex items-center gap-2">
                    <h3 class="font-medium text-white">{{ host.name }}</h3>
                    <button
                      v-if="host.is_default"
                      class="text-yellow-400"
                      title="Standard-Host"
                    >
                      <StarIconSolid class="w-4 h-4" />
                    </button>
                  </div>
                  <p class="text-sm text-gray-400">
                    {{ host.type === 'socket' ? host.socket_path : `${host.tcp_host}:${host.tcp_port}` }}
                  </p>
                </div>
              </div>
              <component
                :is="getStatusIcon(host.connection_status)"
                class="w-5 h-5"
                :class="getStatusColor(host.connection_status)"
              />
            </div>

            <!-- Host Info -->
            <div v-if="host.connection_status === 'connected'" class="mb-3 text-sm">
              <div class="grid grid-cols-2 gap-2 text-gray-400">
                <div>Version: <span class="text-white">{{ host.docker_version || '-' }}</span></div>
                <div>API: <span class="text-white">{{ host.api_version || '-' }}</span></div>
                <div>Container: <span class="text-white">{{ host.containers_count || 0 }}</span></div>
                <div>Images: <span class="text-white">{{ host.images_count || 0 }}</span></div>
              </div>
            </div>

            <!-- Error Message -->
            <div v-else-if="host.last_error" class="mb-3">
              <p class="text-sm text-red-400 truncate" :title="host.last_error">
                {{ host.last_error }}
              </p>
            </div>

            <!-- Description -->
            <p v-if="host.description" class="text-sm text-gray-400 mb-3 truncate">
              {{ host.description }}
            </p>

            <!-- Actions -->
            <div class="flex items-center justify-between pt-3 border-t border-dark-600">
              <div class="flex items-center gap-2">
                <button
                  @click="testConnection(host)"
                  class="p-1.5 text-gray-400 hover:text-white hover:bg-dark-600 rounded transition-colors"
                  :disabled="testingConnection === host.id"
                  title="Verbindung testen"
                >
                  <ArrowPathIcon
                    class="w-4 h-4"
                    :class="{ 'animate-spin': testingConnection === host.id }"
                  />
                </button>
                <button
                  v-if="!host.is_default"
                  @click="setDefault(host)"
                  class="p-1.5 text-gray-400 hover:text-yellow-400 hover:bg-dark-600 rounded transition-colors"
                  title="Als Standard setzen"
                >
                  <StarIcon class="w-4 h-4" />
                </button>
              </div>
              <div class="flex items-center gap-2">
                <button
                  @click="openEditModal(host)"
                  class="p-1.5 text-gray-400 hover:text-white hover:bg-dark-600 rounded transition-colors"
                  title="Bearbeiten"
                >
                  <PencilIcon class="w-4 h-4" />
                </button>
                <button
                  @click="deleteHost(host)"
                  class="p-1.5 text-gray-400 hover:text-red-400 hover:bg-dark-600 rounded transition-colors"
                  title="Löschen"
                >
                  <TrashIcon class="w-4 h-4" />
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50" @click="showModal = false"></div>
        <div class="relative bg-dark-700 rounded-xl shadow-xl max-w-lg w-full max-h-[90vh] overflow-y-auto">
          <div class="p-6">
            <h2 class="text-xl font-bold text-white mb-6">
              {{ editMode ? 'Docker Host bearbeiten' : 'Docker Host hinzufügen' }}
            </h2>

            <form @submit.prevent="saveHost" class="space-y-4">
              <!-- Name -->
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Name *</label>
                <input
                  v-model="form.name"
                  type="text"
                  class="input w-full"
                  placeholder="z.B. Produktionsserver"
                  required
                />
              </div>

              <!-- Description -->
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Beschreibung</label>
                <input
                  v-model="form.description"
                  type="text"
                  class="input w-full"
                  placeholder="Optionale Beschreibung"
                />
              </div>

              <!-- Project -->
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Projekt</label>
                <select v-model="form.project_id" class="input w-full">
                  <option value="">Kein Projekt</option>
                  <option v-for="project in projects" :key="project.id" :value="project.id">
                    {{ project.name }}
                  </option>
                </select>
              </div>

              <!-- Connection Type -->
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Verbindungstyp</label>
                <div class="grid grid-cols-2 gap-3">
                  <button
                    type="button"
                    @click="form.type = 'socket'"
                    class="p-3 rounded-lg border-2 text-left transition-colors"
                    :class="
                      form.type === 'socket'
                        ? 'border-primary-500 bg-primary-500/10'
                        : 'border-dark-500 hover:border-dark-400'
                    "
                  >
                    <ComputerDesktopIcon class="w-5 h-5 text-blue-400 mb-1" />
                    <div class="font-medium text-white">Socket</div>
                    <div class="text-xs text-gray-400">Lokaler Docker Socket</div>
                  </button>
                  <button
                    type="button"
                    @click="form.type = 'tcp'"
                    class="p-3 rounded-lg border-2 text-left transition-colors"
                    :class="
                      form.type === 'tcp'
                        ? 'border-primary-500 bg-primary-500/10'
                        : 'border-dark-500 hover:border-dark-400'
                    "
                  >
                    <GlobeAltIcon class="w-5 h-5 text-purple-400 mb-1" />
                    <div class="font-medium text-white">TCP</div>
                    <div class="text-xs text-gray-400">Remote Docker API</div>
                  </button>
                </div>
              </div>

              <!-- Socket Path -->
              <div v-if="form.type === 'socket'">
                <label class="block text-sm font-medium text-gray-300 mb-1">Socket Pfad</label>
                <input
                  v-model="form.socket_path"
                  type="text"
                  class="input w-full"
                  placeholder="/var/run/docker.sock"
                />
              </div>

              <!-- TCP Settings -->
              <template v-else>
                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Host *</label>
                    <input
                      v-model="form.tcp_host"
                      type="text"
                      class="input w-full"
                      placeholder="192.168.1.100"
                      required
                    />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Port</label>
                    <input
                      v-model.number="form.tcp_port"
                      type="number"
                      class="input w-full"
                      placeholder="2375"
                    />
                  </div>
                </div>

                <!-- TLS Toggle -->
                <div class="flex items-center gap-3">
                  <input
                    v-model="form.tls_enabled"
                    type="checkbox"
                    id="tls_enabled"
                    class="rounded bg-dark-600 border-dark-500 text-primary-500 focus:ring-primary-500"
                  />
                  <label for="tls_enabled" class="text-sm text-gray-300">
                    TLS/SSL aktivieren (Port 2376)
                  </label>
                </div>

                <!-- TLS Certificates -->
                <template v-if="form.tls_enabled">
                  <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">CA Zertifikat</label>
                    <textarea
                      v-model="form.tls_ca_cert"
                      class="input w-full h-20 text-xs font-mono"
                      placeholder="-----BEGIN CERTIFICATE-----"
                    ></textarea>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Client Zertifikat</label>
                    <textarea
                      v-model="form.tls_cert"
                      class="input w-full h-20 text-xs font-mono"
                      placeholder="-----BEGIN CERTIFICATE-----"
                    ></textarea>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Client Key</label>
                    <textarea
                      v-model="form.tls_key"
                      class="input w-full h-20 text-xs font-mono"
                      placeholder="-----BEGIN RSA PRIVATE KEY-----"
                    ></textarea>
                  </div>
                </template>
              </template>

              <!-- Actions -->
              <div class="flex justify-end gap-3 pt-4">
                <button type="button" @click="showModal = false" class="btn btn-secondary">
                  Abbrechen
                </button>
                <button type="submit" class="btn btn-primary">
                  {{ editMode ? 'Speichern' : 'Hinzufügen' }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
