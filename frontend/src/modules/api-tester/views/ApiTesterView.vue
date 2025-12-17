<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import api from '@/core/api/axios'
import { useUiStore } from '@/stores/ui'
import { useToast } from '@/composables/useToast'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
import {
  PlusIcon,
  FolderIcon,
  TrashIcon,
  PencilIcon,
  PlayIcon,
  ClockIcon,
  Cog6ToothIcon,
  DocumentDuplicateIcon,
  XMarkIcon,
  ChevronRightIcon,
  ChevronDownIcon,
  BookmarkIcon,
} from '@heroicons/vue/24/outline'

const uiStore = useUiStore()
const toast = useToast()
const { confirm } = useConfirmDialog()

// State
const collections = ref([])
const requests = ref([])
const environments = ref([])
const history = ref([])
const expandedCollections = ref([])
const selectedEnvironmentId = ref('')
const currentRequest = ref(null)
const isNewRequest = ref(false)
const sending = ref(false)
const response = ref(null)
const isLoading = ref(true)

// Modals
const showCollectionModal = ref(false)
const showEnvironmentModal = ref(false)
const showHistoryModal = ref(false)
const editingCollection = ref(null)
const editingEnvironment = ref(null)

// Tabs
const activeRequestTab = ref('params')
const activeResponseTab = ref('body')

// Form data
const requestForm = reactive({
  name: '',
  collection_id: '',
  method: 'GET',
  url: '',
  headers: {},
  body_type: 'none',
  body: '',
  auth_type: 'none',
  auth_config: {}
})

const queryParams = ref([{ key: '', value: '' }])

const collectionForm = reactive({
  name: '',
  description: '',
  color: '#6366f1'
})

const environmentForm = reactive({
  name: '',
  variables: {}
})

const colors = [
  '#6366f1', '#8b5cf6', '#ec4899', '#ef4444', '#f97316',
  '#eab308', '#22c55e', '#14b8a6', '#0ea5e9', '#64748b'
]

// Computed
const uncategorizedRequests = computed(() => {
  return requests.value.filter(r => !r.collection_id)
})

// Methods
async function loadData() {
  isLoading.value = true
  try {
    const [colRes, reqRes, envRes] = await Promise.all([
      api.get('/api/v1/api-tester/collections'),
      api.get('/api/v1/api-tester/requests'),
      api.get('/api/v1/api-tester/environments')
    ])
    collections.value = colRes.data.data?.items || []
    requests.value = reqRes.data.data?.items || []
    environments.value = envRes.data.data?.items || []

    const activeEnv = environments.value.find(e => e.is_active)
    if (activeEnv) {
      selectedEnvironmentId.value = activeEnv.id
    }
  } catch (error) {
    uiStore.showError('Fehler beim Laden der Daten')
  } finally {
    isLoading.value = false
  }
}

function getCollectionRequests(collectionId) {
  return requests.value.filter(r => r.collection_id === collectionId)
}

function toggleCollection(collectionId) {
  const index = expandedCollections.value.indexOf(collectionId)
  if (index > -1) {
    expandedCollections.value.splice(index, 1)
  } else {
    expandedCollections.value.push(collectionId)
  }
}

function selectRequest(req) {
  currentRequest.value = req
  isNewRequest.value = false
  requestForm.name = req.name
  requestForm.collection_id = req.collection_id || ''
  requestForm.method = req.method
  requestForm.url = req.url
  requestForm.headers = { ...req.headers }
  requestForm.body_type = req.body_type || 'none'
  requestForm.body = req.body || ''
  requestForm.auth_type = req.auth_type || 'none'
  requestForm.auth_config = { ...(req.auth_config || {}) }
  response.value = null
  parseQueryParams()
}

function createNewRequest() {
  currentRequest.value = null
  isNewRequest.value = true
  resetRequestForm()
}

function addRequestToCollection(collectionId) {
  createNewRequest()
  requestForm.collection_id = collectionId
}

function resetRequestForm() {
  requestForm.name = 'New Request'
  requestForm.collection_id = ''
  requestForm.method = 'GET'
  requestForm.url = ''
  requestForm.headers = {}
  requestForm.body_type = 'none'
  requestForm.body = ''
  requestForm.auth_type = 'none'
  requestForm.auth_config = {}
  queryParams.value = [{ key: '', value: '' }]
  response.value = null
}

function parseQueryParams() {
  try {
    const url = new URL(requestForm.url)
    const params = []
    url.searchParams.forEach((value, key) => {
      params.push({ key, value })
    })
    if (params.length === 0) {
      params.push({ key: '', value: '' })
    }
    queryParams.value = params
  } catch {
    queryParams.value = [{ key: '', value: '' }]
  }
}

function updateQueryParams() {
  try {
    let baseUrl = requestForm.url.split('?')[0]
    const params = new URLSearchParams()
    queryParams.value.forEach(p => {
      if (p.key) params.append(p.key, p.value)
    })
    const queryString = params.toString()
    requestForm.url = queryString ? `${baseUrl}?${queryString}` : baseUrl
  } catch {
    // Invalid URL
  }
}

function addQueryParam() {
  queryParams.value.push({ key: '', value: '' })
}

function removeQueryParam(index) {
  queryParams.value.splice(index, 1)
  if (queryParams.value.length === 0) {
    queryParams.value.push({ key: '', value: '' })
  }
  updateQueryParams()
}

function addHeader() {
  const newKey = `Header-${Object.keys(requestForm.headers).length + 1}`
  requestForm.headers[newKey] = ''
}

function updateHeaderKey(oldKey, event) {
  const newKey = event.target.value
  if (newKey && newKey !== oldKey) {
    requestForm.headers[newKey] = requestForm.headers[oldKey]
    delete requestForm.headers[oldKey]
  }
}

function removeHeader(key) {
  delete requestForm.headers[key]
}

function formatJson() {
  try {
    const parsed = JSON.parse(requestForm.body)
    requestForm.body = JSON.stringify(parsed, null, 2)
  } catch {
    uiStore.showError('Ungültiges JSON')
  }
}

async function sendRequest() {
  if (!requestForm.url) {
    uiStore.showError('URL ist erforderlich')
    return
  }

  sending.value = true
  response.value = null

  try {
    const res = await api.post('/api/v1/api-tester/execute', {
      request_id: currentRequest.value?.id,
      method: requestForm.method,
      url: requestForm.url,
      headers: requestForm.headers,
      body_type: requestForm.body_type,
      body: requestForm.body,
      auth_type: requestForm.auth_type,
      auth_config: requestForm.auth_config
    })
    response.value = res.data.data
  } catch (error) {
    response.value = {
      status: 0,
      error: error.message || 'Request failed',
      headers: {},
      body: '',
      time: 0,
      size: 0
    }
  } finally {
    sending.value = false
  }
}

async function saveRequest() {
  if (!requestForm.name || !requestForm.url) {
    uiStore.showError('Name und URL sind erforderlich')
    return
  }

  const data = {
    name: requestForm.name,
    collection_id: requestForm.collection_id || null,
    method: requestForm.method,
    url: requestForm.url,
    headers: requestForm.headers,
    body_type: requestForm.body_type,
    body: requestForm.body,
    auth_type: requestForm.auth_type,
    auth_config: requestForm.auth_config
  }

  try {
    if (currentRequest.value) {
      await api.put(`/api/v1/api-tester/requests/${currentRequest.value.id}`, data)
      uiStore.showSuccess('Request gespeichert')
    } else {
      const res = await api.post('/api/v1/api-tester/requests', data)
      currentRequest.value = res.data.data
      isNewRequest.value = false
      uiStore.showSuccess('Request erstellt')
    }
    await loadData()
  } catch (error) {
    uiStore.showError('Fehler beim Speichern')
  }
}

async function deleteRequest(req) {
  if (!await confirm({ message: 'Request wirklich löschen?', type: 'danger', confirmText: 'Löschen' })) return
  try {
    await api.delete(`/api/v1/api-tester/requests/${req.id}`)
    if (currentRequest.value?.id === req.id) {
      currentRequest.value = null
      resetRequestForm()
    }
    await loadData()
    uiStore.showSuccess('Request gelöscht')
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
  }
}

function getStatusClass(status) {
  if (!status) return 'bg-gray-600'
  if (status >= 200 && status < 300) return 'bg-green-600'
  if (status >= 300 && status < 400) return 'bg-blue-600'
  if (status >= 400 && status < 500) return 'bg-orange-600'
  return 'bg-red-600'
}

function getMethodClass(method) {
  const classes = {
    GET: 'bg-green-600/20 text-green-400',
    POST: 'bg-yellow-600/20 text-yellow-400',
    PUT: 'bg-blue-600/20 text-blue-400',
    PATCH: 'bg-purple-600/20 text-purple-400',
    DELETE: 'bg-red-600/20 text-red-400',
  }
  return classes[method] || 'bg-gray-600/20 text-gray-400'
}

function formatBytes(bytes) {
  if (!bytes) return '0 B'
  const sizes = ['B', 'KB', 'MB']
  const i = Math.floor(Math.log(bytes) / Math.log(1024))
  return `${(bytes / Math.pow(1024, i)).toFixed(1)} ${sizes[i]}`
}

function formatResponseBody(body) {
  if (!body) return ''
  try {
    const parsed = JSON.parse(body)
    return JSON.stringify(parsed, null, 2)
  } catch {
    return body
  }
}

// Collection methods
function openCollectionModal(collection = null) {
  editingCollection.value = collection
  if (collection) {
    collectionForm.name = collection.name
    collectionForm.description = collection.description || ''
    collectionForm.color = collection.color || '#6366f1'
  } else {
    collectionForm.name = ''
    collectionForm.description = ''
    collectionForm.color = '#6366f1'
  }
  showCollectionModal.value = true
}

async function saveCollection() {
  if (!collectionForm.name) {
    uiStore.showError('Name ist erforderlich')
    return
  }

  try {
    if (editingCollection.value) {
      await api.put(`/api/v1/api-tester/collections/${editingCollection.value.id}`, collectionForm)
      uiStore.showSuccess('Collection aktualisiert')
    } else {
      await api.post('/api/v1/api-tester/collections', collectionForm)
      uiStore.showSuccess('Collection erstellt')
    }
    await loadData()
    showCollectionModal.value = false
  } catch (error) {
    uiStore.showError('Fehler beim Speichern')
  }
}

async function deleteCollection(id) {
  if (!await confirm({ message: 'Collection und alle Requests löschen?', type: 'danger', confirmText: 'Löschen' })) return

  try {
    await api.delete(`/api/v1/api-tester/collections/${id}`)
    await loadData()
    uiStore.showSuccess('Collection gelöscht')
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
  }
}

// Environment methods
function openEnvironmentModal() {
  showEnvironmentModal.value = true
  editingEnvironment.value = null
}

function selectEnvironment(env) {
  editingEnvironment.value = env
  environmentForm.name = env.name
  environmentForm.variables = { ...env.variables }
}

function createNewEnvironment() {
  editingEnvironment.value = { id: null, name: '', variables: {}, is_active: false }
  environmentForm.name = ''
  environmentForm.variables = {}
}

function addEnvVar() {
  const newKey = `VAR_${Object.keys(environmentForm.variables).length + 1}`
  environmentForm.variables[newKey] = ''
}

function updateEnvVarKey(oldKey, event) {
  const newKey = event.target.value
  if (newKey && newKey !== oldKey) {
    environmentForm.variables[newKey] = environmentForm.variables[oldKey]
    delete environmentForm.variables[oldKey]
  }
}

function removeEnvVar(key) {
  delete environmentForm.variables[key]
}

async function saveEnvironment() {
  if (!environmentForm.name) {
    uiStore.showError('Name ist erforderlich')
    return
  }

  try {
    if (editingEnvironment.value?.id) {
      await api.put(`/api/v1/api-tester/environments/${editingEnvironment.value.id}`, environmentForm)
    } else {
      const res = await api.post('/api/v1/api-tester/environments', environmentForm)
      editingEnvironment.value = res.data.data
    }
    await loadData()
    uiStore.showSuccess('Environment gespeichert')
  } catch (error) {
    uiStore.showError('Fehler beim Speichern')
  }
}

async function activateEnvironment() {
  if (!editingEnvironment.value?.id) return

  try {
    await api.put(`/api/v1/api-tester/environments/${editingEnvironment.value.id}`, { is_active: true })
    selectedEnvironmentId.value = editingEnvironment.value.id
    await loadData()
    uiStore.showSuccess('Environment aktiviert')
  } catch (error) {
    uiStore.showError('Fehler beim Aktivieren')
  }
}

async function deleteEnvironment() {
  if (!editingEnvironment.value?.id) return
  if (!await confirm({ message: 'Environment löschen?', type: 'danger', confirmText: 'Löschen' })) return

  try {
    await api.delete(`/api/v1/api-tester/environments/${editingEnvironment.value.id}`)
    editingEnvironment.value = null
    await loadData()
    uiStore.showSuccess('Environment gelöscht')
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
  }
}

// History methods
async function openHistoryModal() {
  showHistoryModal.value = true
  try {
    const res = await api.get('/api/v1/api-tester/history')
    history.value = res.data.data?.items || []
  } catch (error) {
    uiStore.showError('Fehler beim Laden')
  }
}

async function loadFromHistory(item) {
  try {
    const res = await api.get(`/api/v1/api-tester/history/${item.id}`)
    const historyItem = res.data.data

    currentRequest.value = null
    isNewRequest.value = true
    requestForm.name = 'From History'
    requestForm.method = historyItem.method
    requestForm.url = historyItem.url
    requestForm.headers = historyItem.request_headers || {}
    requestForm.body = historyItem.request_body || ''
    requestForm.body_type = historyItem.request_body ? 'raw' : 'none'

    response.value = {
      status: historyItem.response_status,
      headers: historyItem.response_headers || {},
      body: historyItem.response_body,
      time: historyItem.response_time,
      size: historyItem.response_size,
      error: historyItem.error_message
    }

    showHistoryModal.value = false
  } catch (error) {
    uiStore.showError('Fehler beim Laden')
  }
}

async function clearHistory() {
  if (!await confirm({ message: 'Gesamte Historie löschen?', type: 'danger', confirmText: 'Löschen' })) return

  try {
    await api.delete('/api/v1/api-tester/history')
    history.value = []
    uiStore.showSuccess('Historie gelöscht')
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
  }
}

onMounted(() => {
  loadData()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-white">API Tester</h1>
        <p class="text-gray-400 mt-1">Teste und debugge deine APIs</p>
      </div>
      <div class="flex gap-2">
        <select
          v-model="selectedEnvironmentId"
          class="input w-40"
        >
          <option value="">Kein Environment</option>
          <option v-for="env in environments" :key="env.id" :value="env.id">
            {{ env.name }}
          </option>
        </select>
        <button @click="openEnvironmentModal" class="btn-secondary">
          <Cog6ToothIcon class="w-5 h-5" />
        </button>
        <button @click="openHistoryModal" class="btn-secondary">
          <ClockIcon class="w-5 h-5" />
        </button>
      </div>
    </div>

    <div class="flex gap-6">
      <!-- Sidebar -->
      <div class="w-72 flex-shrink-0 space-y-4">
        <!-- Collections -->
        <div class="card p-4">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-gray-400 uppercase">Collections</h3>
            <button @click="openCollectionModal()" class="btn-icon">
              <PlusIcon class="w-4 h-4" />
            </button>
          </div>

          <div class="space-y-1">
            <div v-for="collection in collections" :key="collection.id">
              <div
                class="flex items-center justify-between p-2 rounded-lg hover:bg-dark-700 cursor-pointer group"
                @click="toggleCollection(collection.id)"
              >
                <div class="flex items-center gap-2">
                  <component
                    :is="expandedCollections.includes(collection.id) ? ChevronDownIcon : ChevronRightIcon"
                    class="w-4 h-4 text-gray-500"
                  />
                  <FolderIcon class="w-4 h-4" :style="{ color: collection.color }" />
                  <span class="text-sm text-white">{{ collection.name }}</span>
                  <span class="text-xs text-gray-500">({{ collection.request_count || 0 }})</span>
                </div>
                <div class="flex gap-1 opacity-0 group-hover:opacity-100">
                  <button @click.stop="addRequestToCollection(collection.id)" class="btn-icon-sm">
                    <PlusIcon class="w-3 h-3" />
                  </button>
                  <button @click.stop="openCollectionModal(collection)" class="btn-icon-sm">
                    <PencilIcon class="w-3 h-3" />
                  </button>
                  <button @click.stop="deleteCollection(collection.id)" class="btn-icon-sm text-red-400">
                    <TrashIcon class="w-3 h-3" />
                  </button>
                </div>
              </div>

              <div v-if="expandedCollections.includes(collection.id)" class="ml-6 space-y-1">
                <div
                  v-for="req in getCollectionRequests(collection.id)"
                  :key="req.id"
                  class="flex items-center gap-2 p-2 rounded-lg cursor-pointer group"
                  :class="currentRequest?.id === req.id ? 'bg-primary-600' : 'hover:bg-dark-700'"
                  @click="selectRequest(req)"
                >
                  <span class="text-[10px] font-bold px-1.5 py-0.5 rounded" :class="getMethodClass(req.method)">
                    {{ req.method }}
                  </span>
                  <span class="text-sm text-gray-300 truncate flex-1">{{ req.name }}</span>
                  <button @click.stop="deleteRequest(req)" class="btn-icon-sm opacity-0 group-hover:opacity-100 text-red-400">
                    <TrashIcon class="w-3 h-3" />
                  </button>
                </div>
                <div v-if="getCollectionRequests(collection.id).length === 0" class="text-xs text-gray-500 p-2">
                  Keine Requests
                </div>
              </div>
            </div>
          </div>

          <!-- Uncategorized -->
          <div class="mt-4 pt-4 border-t border-dark-600">
            <h4 class="text-xs font-semibold text-gray-500 uppercase mb-2">Unkategorisiert</h4>
            <div class="space-y-1">
              <div
                v-for="req in uncategorizedRequests"
                :key="req.id"
                class="flex items-center gap-2 p-2 rounded-lg cursor-pointer group"
                :class="currentRequest?.id === req.id ? 'bg-primary-600' : 'hover:bg-dark-700'"
                @click="selectRequest(req)"
              >
                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded" :class="getMethodClass(req.method)">
                  {{ req.method }}
                </span>
                <span class="text-sm text-gray-300 truncate flex-1">{{ req.name }}</span>
              </div>
            </div>
            <button @click="createNewRequest" class="btn-ghost w-full mt-2 text-sm">
              <PlusIcon class="w-4 h-4 mr-1" />
              Neuer Request
            </button>
          </div>
        </div>
      </div>

      <!-- Main Content -->
      <div class="flex-1 space-y-4">
        <!-- Request Bar -->
        <div class="card p-4">
          <div class="flex gap-2">
            <select v-model="requestForm.method" class="input w-28 font-bold">
              <option>GET</option>
              <option>POST</option>
              <option>PUT</option>
              <option>PATCH</option>
              <option>DELETE</option>
              <option>HEAD</option>
              <option>OPTIONS</option>
            </select>
            <input
              v-model="requestForm.url"
              type="text"
              class="input flex-1 font-mono"
              placeholder="https://api.example.com/users"
            />
            <button @click="sendRequest" :disabled="sending" class="btn-primary px-6">
              <PlayIcon v-if="!sending" class="w-5 h-5 mr-1" />
              <span v-else class="animate-spin w-5 h-5 mr-1 border-2 border-white border-t-transparent rounded-full"></span>
              {{ sending ? 'Senden...' : 'Senden' }}
            </button>
            <button v-if="currentRequest || isNewRequest" @click="saveRequest" class="btn-secondary">
              <BookmarkIcon class="w-5 h-5" />
            </button>
          </div>

          <div v-if="currentRequest || isNewRequest" class="flex gap-2 mt-3">
            <input
              v-model="requestForm.name"
              type="text"
              class="input flex-1"
              placeholder="Request Name"
            />
            <select v-model="requestForm.collection_id" class="input w-48">
              <option value="">Keine Collection</option>
              <option v-for="col in collections" :key="col.id" :value="col.id">
                {{ col.name }}
              </option>
            </select>
          </div>
        </div>

        <!-- Request Tabs -->
        <div class="card p-4">
          <div class="flex border-b border-dark-600 -mx-4 -mt-4 px-4">
            <button
              v-for="tab in ['params', 'headers', 'body', 'auth']"
              :key="tab"
              class="px-4 py-3 text-sm font-medium border-b-2 -mb-px capitalize"
              :class="activeRequestTab === tab ? 'border-primary-500 text-primary-500' : 'border-transparent text-gray-400 hover:text-white'"
              @click="activeRequestTab = tab"
            >
              {{ tab }}
            </button>
          </div>

          <div class="mt-4">
            <!-- Params -->
            <div v-if="activeRequestTab === 'params'" class="space-y-2">
              <p class="text-xs text-gray-500 mb-3">Query Parameter werden an die URL angehängt.</p>
              <div v-for="(param, index) in queryParams" :key="index" class="flex gap-2">
                <input v-model="param.key" type="text" class="input flex-1 font-mono text-sm" placeholder="Key" @input="updateQueryParams" />
                <input v-model="param.value" type="text" class="input flex-1 font-mono text-sm" placeholder="Value" @input="updateQueryParams" />
                <button @click="removeQueryParam(index)" class="btn-icon text-red-400">
                  <XMarkIcon class="w-4 h-4" />
                </button>
              </div>
              <button @click="addQueryParam" class="btn-ghost text-sm">
                <PlusIcon class="w-4 h-4 mr-1" /> Parameter hinzufügen
              </button>
            </div>

            <!-- Headers -->
            <div v-if="activeRequestTab === 'headers'" class="space-y-2">
              <div v-for="(value, key) in requestForm.headers" :key="key" class="flex gap-2">
                <input :value="key" type="text" class="input flex-1 font-mono text-sm" placeholder="Header" @input="updateHeaderKey(key, $event)" />
                <input v-model="requestForm.headers[key]" type="text" class="input flex-1 font-mono text-sm" placeholder="Value" />
                <button @click="removeHeader(key)" class="btn-icon text-red-400">
                  <XMarkIcon class="w-4 h-4" />
                </button>
              </div>
              <button @click="addHeader" class="btn-ghost text-sm">
                <PlusIcon class="w-4 h-4 mr-1" /> Header hinzufügen
              </button>
            </div>

            <!-- Body -->
            <div v-if="activeRequestTab === 'body'">
              <div class="flex gap-4 mb-3">
                <label v-for="type in ['none', 'json', 'form', 'raw']" :key="type" class="flex items-center gap-2 text-sm">
                  <input v-model="requestForm.body_type" type="radio" :value="type" class="text-primary-500" />
                  <span class="capitalize">{{ type }}</span>
                </label>
              </div>
              <div v-if="requestForm.body_type !== 'none'" class="relative">
                <textarea
                  v-model="requestForm.body"
                  class="input w-full h-40 font-mono text-sm"
                  placeholder="Request Body"
                ></textarea>
                <button
                  v-if="requestForm.body_type === 'json'"
                  @click="formatJson"
                  class="absolute top-2 right-2 btn-ghost text-xs"
                >
                  Format JSON
                </button>
              </div>
            </div>

            <!-- Auth -->
            <div v-if="activeRequestTab === 'auth'">
              <select v-model="requestForm.auth_type" class="input w-48 mb-4">
                <option value="none">Keine Auth</option>
                <option value="bearer">Bearer Token</option>
                <option value="basic">Basic Auth</option>
                <option value="api_key">API Key</option>
              </select>

              <div v-if="requestForm.auth_type === 'bearer'" class="space-y-3">
                <div>
                  <label class="text-sm text-gray-400 mb-1 block">Token</label>
                  <input v-model="requestForm.auth_config.token" type="text" class="input w-full font-mono" placeholder="Bearer Token" />
                </div>
              </div>

              <div v-if="requestForm.auth_type === 'basic'" class="space-y-3">
                <div>
                  <label class="text-sm text-gray-400 mb-1 block">Username</label>
                  <input v-model="requestForm.auth_config.username" type="text" class="input w-full" />
                </div>
                <div>
                  <label class="text-sm text-gray-400 mb-1 block">Password</label>
                  <input v-model="requestForm.auth_config.password" type="password" class="input w-full" />
                </div>
              </div>

              <div v-if="requestForm.auth_type === 'api_key'" class="space-y-3">
                <div class="flex gap-3">
                  <div class="flex-1">
                    <label class="text-sm text-gray-400 mb-1 block">Key</label>
                    <input v-model="requestForm.auth_config.key" type="text" class="input w-full" placeholder="X-API-Key" />
                  </div>
                  <div class="flex-1">
                    <label class="text-sm text-gray-400 mb-1 block">Value</label>
                    <input v-model="requestForm.auth_config.value" type="text" class="input w-full" />
                  </div>
                </div>
                <div>
                  <label class="text-sm text-gray-400 mb-1 block">Hinzufügen zu</label>
                  <select v-model="requestForm.auth_config.add_to" class="input w-40">
                    <option value="header">Header</option>
                    <option value="query">Query Params</option>
                  </select>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Response -->
        <div class="card p-4">
          <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold text-white">Response</h3>
            <div v-if="response" class="flex items-center gap-4 text-sm">
              <span class="px-2 py-1 rounded font-bold text-white" :class="getStatusClass(response.status)">
                {{ response.status || 'Error' }}
              </span>
              <span class="text-gray-400">{{ response.time }}ms</span>
              <span class="text-gray-400">{{ formatBytes(response.size) }}</span>
            </div>
          </div>

          <div v-if="response">
            <div class="flex border-b border-dark-600 -mx-4 px-4 mb-4">
              <button
                v-for="tab in ['body', 'headers']"
                :key="tab"
                class="px-4 py-2 text-sm font-medium border-b-2 -mb-px capitalize"
                :class="activeResponseTab === tab ? 'border-primary-500 text-primary-500' : 'border-transparent text-gray-400 hover:text-white'"
                @click="activeResponseTab = tab"
              >
                {{ tab }}
              </button>
            </div>

            <div v-if="activeResponseTab === 'body'">
              <div v-if="response.error" class="p-4 bg-red-900/20 border border-red-800 rounded-lg text-red-400">
                {{ response.error }}
              </div>
              <pre v-else class="p-4 bg-dark-900 rounded-lg text-sm font-mono text-gray-300 overflow-auto max-h-96">{{ formatResponseBody(response.body) }}</pre>
            </div>

            <div v-if="activeResponseTab === 'headers'" class="space-y-1">
              <div v-for="(value, key) in response.headers" :key="key" class="flex gap-4 py-1 border-b border-dark-700">
                <span class="text-primary-400 font-medium w-48 shrink-0">{{ key }}</span>
                <span class="text-gray-300 font-mono text-sm break-all">{{ value }}</span>
              </div>
            </div>
          </div>

          <div v-else class="flex flex-col items-center justify-center py-12 text-gray-500">
            <PlayIcon class="w-12 h-12 mb-3 opacity-50" />
            <p>Sende einen Request um die Response zu sehen</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Collection Modal -->
    <div v-if="showCollectionModal" class="modal-overlay" >
      <div class="modal max-w-md">
        <div class="modal-header">
          <h2>{{ editingCollection ? 'Collection bearbeiten' : 'Neue Collection' }}</h2>
          <button @click="showCollectionModal = false" class="btn-icon">
            <XMarkIcon class="w-5 h-5" />
          </button>
        </div>
        <div class="modal-body space-y-4">
          <div>
            <label class="text-sm text-gray-400 mb-1 block">Name *</label>
            <input v-model="collectionForm.name" type="text" class="input w-full" />
          </div>
          <div>
            <label class="text-sm text-gray-400 mb-1 block">Beschreibung</label>
            <textarea v-model="collectionForm.description" class="input w-full" rows="2"></textarea>
          </div>
          <div>
            <label class="text-sm text-gray-400 mb-1 block">Farbe</label>
            <div class="flex gap-2">
              <button
                v-for="color in colors"
                :key="color"
                class="w-8 h-8 rounded-lg border-2 transition-transform hover:scale-110"
                :class="collectionForm.color === color ? 'border-white' : 'border-transparent'"
                :style="{ backgroundColor: color }"
                @click="collectionForm.color = color"
              ></button>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button @click="showCollectionModal = false" class="btn-secondary">Abbrechen</button>
          <button @click="saveCollection" class="btn-primary">Speichern</button>
        </div>
      </div>
    </div>

    <!-- Environment Modal -->
    <div v-if="showEnvironmentModal" class="modal-overlay" >
      <div class="modal max-w-3xl">
        <div class="modal-header">
          <h2>Environments</h2>
          <button @click="showEnvironmentModal = false" class="btn-icon">
            <XMarkIcon class="w-5 h-5" />
          </button>
        </div>
        <div class="modal-body">
          <div class="flex gap-6">
            <div class="w-48 border-r border-dark-600 pr-6">
              <div class="space-y-1">
                <div
                  v-for="env in environments"
                  :key="env.id"
                  class="p-2 rounded-lg cursor-pointer flex items-center justify-between"
                  :class="editingEnvironment?.id === env.id ? 'bg-primary-600' : 'hover:bg-dark-700'"
                  @click="selectEnvironment(env)"
                >
                  <span class="text-sm">{{ env.name }}</span>
                  <span v-if="env.is_active" class="text-[10px] bg-green-600 text-white px-1.5 py-0.5 rounded">Active</span>
                </div>
              </div>
              <button @click="createNewEnvironment" class="btn-ghost w-full mt-3 text-sm">
                <PlusIcon class="w-4 h-4 mr-1" /> Neu
              </button>
            </div>

            <div class="flex-1">
              <div v-if="editingEnvironment" class="space-y-4">
                <div>
                  <label class="text-sm text-gray-400 mb-1 block">Name *</label>
                  <input v-model="environmentForm.name" type="text" class="input w-full" />
                </div>
                <div>
                  <label class="text-sm text-gray-400 mb-1 block">Variablen</label>
                  <p class="text-xs text-gray-500 mb-2">Verwende {{variableName}} in Requests.</p>
                  <div class="space-y-2">
                    <div v-for="(value, key) in environmentForm.variables" :key="key" class="flex gap-2">
                      <input :value="key" type="text" class="input flex-1 font-mono text-sm" @input="updateEnvVarKey(key, $event)" />
                      <input v-model="environmentForm.variables[key]" type="text" class="input flex-1 font-mono text-sm" />
                      <button @click="removeEnvVar(key)" class="btn-icon text-red-400">
                        <XMarkIcon class="w-4 h-4" />
                      </button>
                    </div>
                    <button @click="addEnvVar" class="btn-ghost text-sm">
                      <PlusIcon class="w-4 h-4 mr-1" /> Variable hinzufügen
                    </button>
                  </div>
                </div>
                <div class="flex gap-2 pt-4">
                  <button @click="saveEnvironment" class="btn-primary">Speichern</button>
                  <button v-if="!editingEnvironment.is_active && editingEnvironment.id" @click="activateEnvironment" class="btn-secondary">
                    Aktivieren
                  </button>
                  <button v-if="editingEnvironment.id" @click="deleteEnvironment" class="btn-danger">Löschen</button>
                </div>
              </div>
              <div v-else class="flex items-center justify-center h-48 text-gray-500">
                Wähle ein Environment oder erstelle ein neues
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- History Modal -->
    <div v-if="showHistoryModal" class="modal-overlay" >
      <div class="modal max-w-3xl">
        <div class="modal-header">
          <h2>Request Historie</h2>
          <div class="flex items-center gap-2">
            <button @click="clearHistory" class="btn-danger text-sm">Löschen</button>
            <button @click="showHistoryModal = false" class="btn-icon">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>
        </div>
        <div class="modal-body">
          <div v-if="history.length === 0" class="flex flex-col items-center justify-center py-12 text-gray-500">
            <ClockIcon class="w-12 h-12 mb-3 opacity-50" />
            <p>Keine Historie vorhanden</p>
          </div>
          <div v-else class="space-y-2">
            <div
              v-for="item in history"
              :key="item.id"
              class="flex items-center gap-3 p-3 bg-dark-700 rounded-lg cursor-pointer hover:bg-dark-600"
              @click="loadFromHistory(item)"
            >
              <span class="text-[10px] font-bold px-1.5 py-0.5 rounded" :class="getMethodClass(item.method)">
                {{ item.method }}
              </span>
              <span class="flex-1 font-mono text-sm text-gray-300 truncate">{{ item.url }}</span>
              <span class="px-2 py-0.5 rounded text-xs font-bold text-white" :class="getStatusClass(item.response_status)">
                {{ item.response_status }}
              </span>
              <span class="text-xs text-gray-500">{{ item.response_time }}ms</span>
              <span class="text-xs text-gray-500">{{ new Date(item.executed_at).toLocaleString() }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
