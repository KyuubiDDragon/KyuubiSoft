<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import api from '@/core/api/axios'
import { useProjectStore } from '@/stores/project'
import { useToast } from '@/composables/useToast'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
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
import { useI18n } from 'vue-i18n'
const { t } = useI18n()

const projectStore = useProjectStore()
const toast = useToast()
const { confirm } = useConfirmDialog()

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
  tls_ca: '',
  tls_cert: '',
  tls_key: '',
  portainer_url: '',
  portainer_api_token: '',
  portainer_endpoint_id: '',
  portainer_only: false,
  ssh_enabled: false,
  ssh_host: '',
  ssh_port: 22,
  ssh_user: '',
  ssh_password: '',
  ssh_private_key: '',
})

const savingPortainer = ref(false)

const groupedHosts = computed(() => {
  const grouped = {
    no_project: {
      project_id: null,
      project_name: t('dockerModule.noProject'),
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
    // Filter hosts by selected project
    const params = projectStore.getProjectFilter()
    const response = await api.get('/api/v1/docker/hosts', { params })
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
    projects.value = response.data.data.items || []
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
    tls_ca: '',
    tls_cert: '',
    tls_key: '',
    portainer_url: '',
    portainer_api_token: '',
    portainer_endpoint_id: '',
    portainer_only: false,
    ssh_enabled: false,
    ssh_host: '',
    ssh_port: 22,
    ssh_user: '',
    ssh_password: '',
    ssh_private_key: '',
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
    tls_ca: host.tls_ca || '',
    tls_cert: host.tls_cert || '',
    tls_key: host.tls_key || '',
    portainer_url: host.portainer_url || '',
    portainer_api_token: host.portainer_api_token || '',
    portainer_endpoint_id: host.portainer_endpoint_id || '',
    portainer_only: !!host.portainer_only,
    ssh_enabled: !!host.ssh_enabled,
    ssh_host: host.ssh_host || '',
    ssh_port: host.ssh_port || 22,
    ssh_user: host.ssh_user || '',
    ssh_password: '',
    ssh_private_key: '',
  }
  showModal.value = true
}

async function savePortainerConfig() {
  if (!form.value.id) return

  savingPortainer.value = true
  try {
    await api.put(`/api/v1/docker/hosts/${form.value.id}/portainer`, {
      portainer_url: form.value.portainer_url || null,
      portainer_api_token: form.value.portainer_api_token || null,
      portainer_endpoint_id: form.value.portainer_endpoint_id ? parseInt(form.value.portainer_endpoint_id) : null,
    })
    await fetchHosts()
  } catch (error) {
    console.error('Failed to save Portainer config:', error)
    toast.error(error.response?.data?.error || 'Failed to save Portainer configuration')
  } finally {
    savingPortainer.value = false
  }
}

async function saveHost() {
  try {
    const data = {
      name: form.value.name,
      description: form.value.description || null,
      project_id: form.value.project_id || null,
      type: form.value.type,
      portainer_url: form.value.portainer_url || null,
      portainer_api_token: form.value.portainer_api_token || null,
      portainer_endpoint_id: form.value.portainer_endpoint_id ? parseInt(form.value.portainer_endpoint_id) : null,
      portainer_only: form.value.portainer_only ? 1 : 0,
      ssh_enabled: form.value.ssh_enabled ? 1 : 0,
      ssh_host: form.value.ssh_host || null,
      ssh_port: form.value.ssh_port || 22,
      ssh_user: form.value.ssh_user || null,
      ssh_password: form.value.ssh_password || null,
      ssh_private_key: form.value.ssh_private_key || null,
    }

    if (form.value.type === 'socket') {
      data.socket_path = form.value.socket_path
    } else {
      data.tcp_host = form.value.tcp_host
      data.tcp_port = form.value.tcp_port
      data.tls_enabled = form.value.tls_enabled ? 1 : 0
      if (form.value.tls_enabled) {
        data.tls_ca = form.value.tls_ca
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
  if (!await confirm({ message: t('docker.confirmDeleteHost', { name: host.name }), type: 'danger', confirmText: t('common.delete') })) return

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

// Watch for project changes to reload hosts
watch(() => projectStore.selectedProjectId, () => {
  fetchHosts()
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-white flex items-center gap-2">
          <ServerStackIcon class="w-7 h-7" />
          {{ $t('dockerModule.hosts') }}
        </h1>
        <p class="text-gray-400 mt-1">{{ $t('dockerModule.hostsSubtitle') }}</p>
      </div>
      <button @click="openCreateModal" class="btn btn-primary flex items-center gap-2">
        <PlusIcon class="w-5 h-5" />
        {{ $t('dockerModule.addHost') }}
      </button>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="flex justify-center py-12">
      <ArrowPathIcon class="w-8 h-8 text-gray-400 animate-spin" />
    </div>

    <!-- Empty State -->
    <div v-else-if="hosts.length === 0" class="card p-12 text-center">
      <ServerStackIcon class="w-16 h-16 mx-auto text-gray-500 mb-4" />
      <h3 class="text-lg font-medium text-white mb-2">{{ $t('dockerModule.noHosts') }}</h3>
      <p class="text-gray-400 mb-6">
        {{ $t('dockerModule.noHostsHint') }}
      </p>
      <button @click="openCreateModal" class="btn btn-primary">
        <PlusIcon class="w-5 h-5 mr-2" />
        {{ $t('dockerModule.addFirstHost') }}
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
                      :title="$t('dockerModule.defaultHost')"
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
            <div class="flex items-center justify-between pt-3 border-t border-white/[0.06]">
              <div class="flex items-center gap-2">
                <button
                  @click="testConnection(host)"
                  class="p-1.5 text-gray-400 hover:text-white hover:bg-white/[0.04] rounded transition-colors"
                  :disabled="testingConnection === host.id"
                  :title="$t('dockerModule.testConnection')"
                >
                  <ArrowPathIcon
                    class="w-4 h-4"
                    :class="{ 'animate-spin': testingConnection === host.id }"
                  />
                </button>
                <button
                  v-if="!host.is_default"
                  @click="setDefault(host)"
                  class="p-1.5 text-gray-400 hover:text-yellow-400 hover:bg-white/[0.04] rounded transition-colors"
                  :title="$t('dockerModule.setAsDefault')"
                >
                  <StarIcon class="w-4 h-4" />
                </button>
              </div>
              <div class="flex items-center gap-2">
                <button
                  @click="openEditModal(host)"
                  class="p-1.5 text-gray-400 hover:text-white hover:bg-white/[0.04] rounded transition-colors"
                  :title="$t('common.edit')"
                >
                  <PencilIcon class="w-4 h-4" />
                </button>
                <button
                  @click="deleteHost(host)"
                  class="p-1.5 text-gray-400 hover:text-red-400 hover:bg-white/[0.04] rounded transition-colors"
                  :title="$t('common.delete')"
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
        <div class="fixed inset-0 bg-black/60 backdrop-blur-md" @click="showModal = false"></div>
        <div class="relative modal max-w-lg w-full max-h-[90vh] overflow-y-auto">
          <div class="p-6">
            <h2 class="text-xl font-bold text-white mb-6">
              {{ editMode ? $t('dockerModule.editHost') : $t('dockerModule.addHost') }}
            </h2>

            <form @submit.prevent="saveHost" class="space-y-4">
              <!-- Name -->
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">{{ $t('common.name') }} *</label>
                <input
                  v-model="form.name"
                  type="text"
                  class="input w-full"
                  :placeholder="$t('dockerModule.hostNamePlaceholder')"
                  required
                />
              </div>

              <!-- Description -->
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">{{ $t('common.description') }}</label>
                <input
                  v-model="form.description"
                  type="text"
                  class="input w-full"
                  :placeholder="$t('deploymentsModule.optionalDescription')"
                />
              </div>

              <!-- Project -->
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">{{ $t('dockerModule.project') }}</label>
                <select v-model="form.project_id" class="select w-full">
                  <option value="">{{ $t('dockerModule.noProject') }}</option>
                  <option v-for="project in projects" :key="project.id" :value="project.id">
                    {{ project.name }}
                  </option>
                </select>
              </div>

              <!-- Connection Type -->
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">{{ $t('dockerModule.connectionType') }}</label>
                <div class="grid grid-cols-2 gap-3">
                  <button
                    type="button"
                    @click="form.type = 'socket'"
                    class="p-3 rounded-lg border-2 text-left transition-colors"
                    :class="
                      form.type === 'socket'
                        ? 'border-primary-500 bg-primary-500/10'
                        : 'border-white/[0.08] hover:border-white/[0.12]'
                    "
                  >
                    <ComputerDesktopIcon class="w-5 h-5 text-blue-400 mb-1" />
                    <div class="font-medium text-white">Socket</div>
                    <div class="text-xs text-gray-400">{{ $t('dockerModule.localSocket') }}</div>
                  </button>
                  <button
                    type="button"
                    @click="form.type = 'tcp'"
                    class="p-3 rounded-lg border-2 text-left transition-colors"
                    :class="
                      form.type === 'tcp'
                        ? 'border-primary-500 bg-primary-500/10'
                        : 'border-white/[0.08] hover:border-white/[0.12]'
                    "
                  >
                    <GlobeAltIcon class="w-5 h-5 text-purple-400 mb-1" />
                    <div class="font-medium text-white">TCP</div>
                    <div class="text-xs text-gray-400">{{ $t('dockerModule.remoteApi') }}</div>
                  </button>
                </div>
              </div>

              <!-- Socket Path -->
              <div v-if="form.type === 'socket'">
                <label class="block text-sm font-medium text-gray-300 mb-1">{{ $t('dockerModule.socketPath') }}</label>
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
                    <label class="block text-sm font-medium text-gray-300 mb-1">{{ $t('dockerModule.host') }} *</label>
                    <input
                      v-model="form.tcp_host"
                      type="text"
                      class="input w-full"
                      placeholder="192.168.1.100"
                      required
                    />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">{{ $t('dockerModule.port') }}</label>
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
                    class="rounded bg-white/[0.08] border-white/[0.08] text-primary-500 focus:ring-primary-500"
                  />
                  <label for="tls_enabled" class="text-sm text-gray-300">
                    {{ $t('dockerModule.enableTls') }}
                  </label>
                </div>

                <!-- TLS Certificates -->
                <template v-if="form.tls_enabled">
                  <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">{{ $t('dockerModule.caCertificate') }}</label>
                    <textarea
                      v-model="form.tls_ca"
                      class="textarea w-full h-20 text-xs font-mono"
                      placeholder="-----BEGIN CERTIFICATE-----"
                    ></textarea>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">{{ $t('dockerModule.clientCertificate') }}</label>
                    <textarea
                      v-model="form.tls_cert"
                      class="textarea w-full h-20 text-xs font-mono"
                      placeholder="-----BEGIN CERTIFICATE-----"
                    ></textarea>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">{{ $t('dockerModule.clientKey') }}</label>
                    <textarea
                      v-model="form.tls_key"
                      class="textarea w-full h-20 text-xs font-mono"
                      placeholder="-----BEGIN RSA PRIVATE KEY-----"
                    ></textarea>
                  </div>
                </template>
              </template>

              <!-- Portainer Integration (only in edit mode) -->
              <div v-if="editMode" class="border-t border-white/[0.06] pt-4 mt-4">
                <h4 class="text-sm font-medium text-white mb-3">{{ $t('dockerModule.portainerIntegration') }}</h4>
                <p class="text-xs text-gray-400 mb-3">
                  {{ $t('dockerModule.portainerHint') }}
                </p>
                <div class="grid grid-cols-1 gap-3">
                  <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">{{ $t('dockerModule.portainerUrl') }}</label>
                    <input
                      v-model="form.portainer_url"
                      type="url"
                      class="input w-full"
                      placeholder="https://portainer.example.com"
                    />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">{{ $t('dockerModule.apiToken') }}</label>
                    <input
                      v-model="form.portainer_api_token"
                      type="password"
                      class="input w-full"
                      placeholder="ptr_xxxxxxxxxxxxx"
                    />
                    <p class="text-xs text-gray-500 mt-1">Erstelle einen API Token in Portainer unter Account Settings → Access Tokens</p>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">{{ $t('dockerModule.endpointId') }}</label>
                    <input
                      v-model="form.portainer_endpoint_id"
                      type="number"
                      class="input w-full"
                      placeholder="1"
                    />
                    <p class="text-xs text-gray-500 mt-1">Die Endpoint ID findest du in Portainer unter Environments</p>
                  </div>
                  <div class="bg-white/[0.04] rounded-lg p-4 border border-primary-500/30">
                    <label class="flex items-center gap-3 cursor-pointer">
                      <input
                        v-model="form.portainer_only"
                        type="checkbox"
                        class="w-5 h-5 rounded border-white/[0.06] bg-white/[0.04] text-primary-500 focus:ring-primary-500"
                      />
                      <div>
                        <span class="text-sm font-medium text-white">{{ $t('dockerModule.portainerOnly') }}</span>
                        <p class="text-xs text-gray-400 mt-0.5">
                          {{ $t('docker.portainerInfo') }}
                        </p>
                      </div>
                    </label>
                  </div>
                  <div class="flex justify-end">
                    <button
                      type="button"
                      @click="savePortainerConfig"
                      :disabled="savingPortainer"
                      class="btn btn-secondary text-sm"
                    >
                      {{ savingPortainer ? $t('common.saving') : $t('dockerModule.savePortainer') }}
                    </button>
                  </div>
                </div>
              </div>

              <!-- SSH Access (only in edit mode) -->
              <div v-if="editMode" class="border-t border-white/[0.06] pt-4 mt-4">
                <h4 class="text-sm font-medium text-white mb-3">{{ $t('dockerModule.sshAccess') }}</h4>
                <p class="text-xs text-gray-400 mb-3">
                  {{ $t('dockerModule.sshHint') }}
                </p>
                <div class="grid grid-cols-1 gap-3">
                  <div class="flex items-center gap-3">
                    <input
                      v-model="form.ssh_enabled"
                      type="checkbox"
                      id="ssh_enabled"
                      class="rounded bg-white/[0.08] border-white/[0.08] text-primary-500 focus:ring-primary-500"
                    />
                    <label for="ssh_enabled" class="text-sm text-gray-300">
                      {{ $t('dockerModule.enableSsh') }}
                    </label>
                  </div>

                  <template v-if="form.ssh_enabled">
                    <div class="grid grid-cols-2 gap-3">
                      <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">{{ $t('dockerModule.sshHost') }}</label>
                        <input
                          v-model="form.ssh_host"
                          type="text"
                          class="input w-full"
                          placeholder="192.168.1.100 oder hostname"
                        />
                      </div>
                      <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">{{ $t('dockerModule.sshPort') }}</label>
                        <input
                          v-model.number="form.ssh_port"
                          type="number"
                          class="input w-full"
                          placeholder="22"
                        />
                      </div>
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-300 mb-1">{{ $t('dockerModule.username') }}</label>
                      <input
                        v-model="form.ssh_user"
                        type="text"
                        class="input w-full"
                        placeholder="root"
                      />
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-300 mb-1">{{ $t('dockerModule.password') }}</label>
                      <input
                        v-model="form.ssh_password"
                        type="password"
                        class="input w-full"
                        :placeholder="$t('dockerModule.optionalWennPrivateKeyVerwendetWird')"
                      />
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-300 mb-1">{{ $t('dockerModule.privateKey') }}</label>
                      <textarea
                        v-model="form.ssh_private_key"
                        class="textarea w-full h-24 text-xs font-mono"
                        placeholder="-----BEGIN OPENSSH PRIVATE KEY-----"
                      ></textarea>
                      <p class="text-xs text-gray-500 mt-1">{{ $t('dockerModule.privateKeyHint') }}</p>
                    </div>
                  </template>
                </div>
              </div>

              <!-- Actions -->
              <div class="flex justify-end gap-3 pt-4">
                <button type="button" @click="showModal = false" class="btn btn-secondary">
                  {{ $t('common.cancel') }}
                </button>
                <button type="submit" class="btn btn-primary">
                  {{ editMode ? $t('common.save') : $t('common.add') }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
