<template>
  <div class="api-tester">
    <!-- Header -->
    <div class="api-tester-header">
      <h1>API Tester</h1>
      <div class="header-actions">
        <select v-model="selectedEnvironmentId" class="environment-select" @change="setActiveEnvironment">
          <option value="">No Environment</option>
          <option v-for="env in environments" :key="env.id" :value="env.id">
            {{ env.name }}
          </option>
        </select>
        <button class="btn btn-secondary" @click="openEnvironmentModal">
          <i class="fas fa-cog"></i>
          Environments
        </button>
        <button class="btn btn-secondary" @click="openHistoryModal">
          <i class="fas fa-history"></i>
          History
        </button>
      </div>
    </div>

    <div class="api-tester-layout">
      <!-- Sidebar - Collections -->
      <div class="sidebar">
        <div class="sidebar-header">
          <h3>Collections</h3>
          <button class="btn btn-sm btn-primary" @click="openCollectionModal()">
            <i class="fas fa-plus"></i>
          </button>
        </div>
        <div class="collections-list">
          <div
            v-for="collection in collections"
            :key="collection.id"
            class="collection-item"
            :class="{ expanded: expandedCollections.includes(collection.id) }"
          >
            <div class="collection-header" @click="toggleCollection(collection.id)">
              <div class="collection-info">
                <span class="collection-icon" :style="{ color: collection.color }">
                  <i class="fas fa-folder"></i>
                </span>
                <span class="collection-name">{{ collection.name }}</span>
                <span class="request-count">({{ collection.request_count || 0 }})</span>
              </div>
              <div class="collection-actions">
                <button class="btn-icon" @click.stop="openCollectionModal(collection)" title="Edit">
                  <i class="fas fa-edit"></i>
                </button>
                <button class="btn-icon" @click.stop="addRequestToCollection(collection.id)" title="Add Request">
                  <i class="fas fa-plus"></i>
                </button>
                <button class="btn-icon danger" @click.stop="deleteCollection(collection.id)" title="Delete">
                  <i class="fas fa-trash"></i>
                </button>
              </div>
            </div>
            <div v-if="expandedCollections.includes(collection.id)" class="collection-requests">
              <div
                v-for="req in getCollectionRequests(collection.id)"
                :key="req.id"
                class="request-item"
                :class="{ active: currentRequest?.id === req.id }"
                @click="selectRequest(req)"
              >
                <span class="method-badge" :class="req.method.toLowerCase()">{{ req.method }}</span>
                <span class="request-name">{{ req.name }}</span>
              </div>
              <div v-if="getCollectionRequests(collection.id).length === 0" class="empty-collection">
                No requests yet
              </div>
            </div>
          </div>
        </div>

        <!-- Uncategorized Requests -->
        <div class="sidebar-section">
          <h4>Uncategorized</h4>
          <div
            v-for="req in uncategorizedRequests"
            :key="req.id"
            class="request-item"
            :class="{ active: currentRequest?.id === req.id }"
            @click="selectRequest(req)"
          >
            <span class="method-badge" :class="req.method.toLowerCase()">{{ req.method }}</span>
            <span class="request-name">{{ req.name }}</span>
          </div>
          <button class="btn btn-sm btn-ghost full-width" @click="createNewRequest()">
            <i class="fas fa-plus"></i> New Request
          </button>
        </div>
      </div>

      <!-- Main Content -->
      <div class="main-content">
        <!-- Request Bar -->
        <div class="request-bar">
          <select v-model="requestForm.method" class="method-select">
            <option value="GET">GET</option>
            <option value="POST">POST</option>
            <option value="PUT">PUT</option>
            <option value="PATCH">PATCH</option>
            <option value="DELETE">DELETE</option>
            <option value="HEAD">HEAD</option>
            <option value="OPTIONS">OPTIONS</option>
          </select>
          <input
            v-model="requestForm.url"
            type="text"
            class="url-input"
            placeholder="Enter request URL (e.g., https://api.example.com/users)"
          />
          <button class="btn btn-primary btn-send" :disabled="sending" @click="sendRequest">
            <i class="fas" :class="sending ? 'fa-spinner fa-spin' : 'fa-paper-plane'"></i>
            {{ sending ? 'Sending...' : 'Send' }}
          </button>
          <button v-if="currentRequest" class="btn btn-secondary" @click="saveRequest">
            <i class="fas fa-save"></i>
            Save
          </button>
        </div>

        <!-- Request Name (when editing) -->
        <div v-if="currentRequest || isNewRequest" class="request-name-bar">
          <input
            v-model="requestForm.name"
            type="text"
            class="request-name-input"
            placeholder="Request name"
          />
          <select v-model="requestForm.collection_id" class="collection-select">
            <option value="">No Collection</option>
            <option v-for="col in collections" :key="col.id" :value="col.id">
              {{ col.name }}
            </option>
          </select>
        </div>

        <!-- Request Tabs -->
        <div class="request-section">
          <div class="tabs">
            <button
              v-for="tab in requestTabs"
              :key="tab.id"
              class="tab"
              :class="{ active: activeRequestTab === tab.id }"
              @click="activeRequestTab = tab.id"
            >
              {{ tab.label }}
              <span v-if="tab.id === 'headers' && Object.keys(requestForm.headers).length" class="badge">
                {{ Object.keys(requestForm.headers).length }}
              </span>
            </button>
          </div>

          <div class="tab-content">
            <!-- Params Tab -->
            <div v-if="activeRequestTab === 'params'" class="params-tab">
              <p class="tab-hint">Query parameters are appended to the URL.</p>
              <div class="key-value-editor">
                <div v-for="(param, index) in queryParams" :key="index" class="key-value-row">
                  <input v-model="param.key" type="text" placeholder="Key" @input="updateQueryParams" />
                  <input v-model="param.value" type="text" placeholder="Value" @input="updateQueryParams" />
                  <button class="btn-icon danger" @click="removeQueryParam(index)">
                    <i class="fas fa-times"></i>
                  </button>
                </div>
                <button class="btn btn-sm btn-ghost" @click="addQueryParam">
                  <i class="fas fa-plus"></i> Add Parameter
                </button>
              </div>
            </div>

            <!-- Headers Tab -->
            <div v-if="activeRequestTab === 'headers'" class="headers-tab">
              <div class="key-value-editor">
                <div v-for="(value, key, index) in requestForm.headers" :key="index" class="key-value-row">
                  <input :value="key" type="text" placeholder="Header" @input="updateHeaderKey(key, $event)" />
                  <input v-model="requestForm.headers[key]" type="text" placeholder="Value" />
                  <button class="btn-icon danger" @click="removeHeader(key)">
                    <i class="fas fa-times"></i>
                  </button>
                </div>
                <button class="btn btn-sm btn-ghost" @click="addHeader">
                  <i class="fas fa-plus"></i> Add Header
                </button>
              </div>
            </div>

            <!-- Body Tab -->
            <div v-if="activeRequestTab === 'body'" class="body-tab">
              <div class="body-type-selector">
                <label v-for="type in bodyTypes" :key="type.value">
                  <input v-model="requestForm.body_type" type="radio" :value="type.value" />
                  {{ type.label }}
                </label>
              </div>
              <div v-if="requestForm.body_type !== 'none'" class="body-editor">
                <textarea
                  v-model="requestForm.body"
                  class="body-textarea"
                  :placeholder="getBodyPlaceholder()"
                  rows="10"
                ></textarea>
                <button
                  v-if="requestForm.body_type === 'json'"
                  class="btn btn-sm btn-ghost format-btn"
                  @click="formatJson"
                >
                  <i class="fas fa-indent"></i> Format JSON
                </button>
              </div>
            </div>

            <!-- Auth Tab -->
            <div v-if="activeRequestTab === 'auth'" class="auth-tab">
              <div class="auth-type-selector">
                <select v-model="requestForm.auth_type">
                  <option value="none">No Auth</option>
                  <option value="bearer">Bearer Token</option>
                  <option value="basic">Basic Auth</option>
                  <option value="api_key">API Key</option>
                </select>
              </div>

              <div v-if="requestForm.auth_type === 'bearer'" class="auth-config">
                <div class="form-group">
                  <label>Token</label>
                  <input v-model="requestForm.auth_config.token" type="text" placeholder="Enter bearer token" />
                </div>
              </div>

              <div v-if="requestForm.auth_type === 'basic'" class="auth-config">
                <div class="form-group">
                  <label>Username</label>
                  <input v-model="requestForm.auth_config.username" type="text" placeholder="Username" />
                </div>
                <div class="form-group">
                  <label>Password</label>
                  <input v-model="requestForm.auth_config.password" type="password" placeholder="Password" />
                </div>
              </div>

              <div v-if="requestForm.auth_type === 'api_key'" class="auth-config">
                <div class="form-group">
                  <label>Key</label>
                  <input v-model="requestForm.auth_config.key" type="text" placeholder="Header or query param name" />
                </div>
                <div class="form-group">
                  <label>Value</label>
                  <input v-model="requestForm.auth_config.value" type="text" placeholder="API key value" />
                </div>
                <div class="form-group">
                  <label>Add to</label>
                  <select v-model="requestForm.auth_config.add_to">
                    <option value="header">Header</option>
                    <option value="query">Query Params</option>
                  </select>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Response Section -->
        <div class="response-section" :class="{ 'has-response': response }">
          <div class="response-header">
            <h3>Response</h3>
            <div v-if="response" class="response-meta">
              <span class="status-badge" :class="getStatusClass(response.status)">
                {{ response.status }} {{ getStatusText(response.status) }}
              </span>
              <span class="meta-item">
                <i class="fas fa-clock"></i> {{ response.time }}ms
              </span>
              <span class="meta-item">
                <i class="fas fa-weight-hanging"></i> {{ formatBytes(response.size) }}
              </span>
            </div>
          </div>

          <div v-if="response" class="response-content">
            <div class="tabs">
              <button
                v-for="tab in responseTabs"
                :key="tab.id"
                class="tab"
                :class="{ active: activeResponseTab === tab.id }"
                @click="activeResponseTab = tab.id"
              >
                {{ tab.label }}
              </button>
            </div>

            <div class="tab-content">
              <div v-if="activeResponseTab === 'body'" class="response-body">
                <div v-if="response.error" class="error-message">
                  <i class="fas fa-exclamation-circle"></i>
                  {{ response.error }}
                </div>
                <pre v-else class="response-text">{{ formatResponseBody(response.body) }}</pre>
              </div>

              <div v-if="activeResponseTab === 'headers'" class="response-headers">
                <table class="headers-table">
                  <tr v-for="(value, key) in response.headers" :key="key">
                    <td class="header-key">{{ key }}</td>
                    <td class="header-value">{{ value }}</td>
                  </tr>
                </table>
              </div>
            </div>
          </div>

          <div v-else class="response-placeholder">
            <i class="fas fa-paper-plane"></i>
            <p>Send a request to see the response</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Collection Modal -->
    <div v-if="showCollectionModal" class="modal-overlay" @click.self="closeCollectionModal">
      <div class="modal modal-sm">
        <div class="modal-header">
          <h2>{{ editingCollection ? 'Edit Collection' : 'New Collection' }}</h2>
          <button class="btn-close" @click="closeCollectionModal">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label>Name *</label>
            <input v-model="collectionForm.name" type="text" placeholder="Collection name" />
          </div>
          <div class="form-group">
            <label>Description</label>
            <textarea v-model="collectionForm.description" rows="3" placeholder="Optional description"></textarea>
          </div>
          <div class="form-group">
            <label>Color</label>
            <div class="color-picker">
              <button
                v-for="color in collectionColors"
                :key="color"
                class="color-option"
                :class="{ active: collectionForm.color === color }"
                :style="{ backgroundColor: color }"
                @click="collectionForm.color = color"
              ></button>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="closeCollectionModal">Cancel</button>
          <button class="btn btn-primary" @click="saveCollection">
            {{ editingCollection ? 'Update' : 'Create' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Environment Modal -->
    <div v-if="showEnvironmentModal" class="modal-overlay" @click.self="closeEnvironmentModal">
      <div class="modal modal-lg">
        <div class="modal-header">
          <h2>Manage Environments</h2>
          <button class="btn-close" @click="closeEnvironmentModal">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="modal-body">
          <div class="environments-layout">
            <div class="environments-sidebar">
              <div
                v-for="env in environments"
                :key="env.id"
                class="environment-item"
                :class="{ active: editingEnvironment?.id === env.id }"
                @click="selectEnvironment(env)"
              >
                <span class="env-name">{{ env.name }}</span>
                <span v-if="env.is_active" class="active-badge">Active</span>
              </div>
              <button class="btn btn-sm btn-ghost full-width" @click="createNewEnvironment">
                <i class="fas fa-plus"></i> New Environment
              </button>
            </div>
            <div class="environment-editor">
              <div v-if="editingEnvironment" class="environment-form">
                <div class="form-group">
                  <label>Name *</label>
                  <input v-model="environmentForm.name" type="text" placeholder="Environment name" />
                </div>
                <div class="form-group">
                  <label>Variables</label>
                  <p class="hint">Use {{variableName}} in requests to reference these values.</p>
                  <div class="key-value-editor">
                    <div v-for="(value, key, index) in environmentForm.variables" :key="index" class="key-value-row">
                      <input :value="key" type="text" placeholder="Variable" @input="updateEnvVarKey(key, $event)" />
                      <input v-model="environmentForm.variables[key]" type="text" placeholder="Value" />
                      <button class="btn-icon danger" @click="removeEnvVar(key)">
                        <i class="fas fa-times"></i>
                      </button>
                    </div>
                    <button class="btn btn-sm btn-ghost" @click="addEnvVar">
                      <i class="fas fa-plus"></i> Add Variable
                    </button>
                  </div>
                </div>
                <div class="environment-actions">
                  <button class="btn btn-primary" @click="saveEnvironment">Save</button>
                  <button
                    v-if="!editingEnvironment.is_active"
                    class="btn btn-secondary"
                    @click="activateEnvironment"
                  >
                    Set as Active
                  </button>
                  <button class="btn btn-danger" @click="deleteEnvironment">Delete</button>
                </div>
              </div>
              <div v-else class="no-selection">
                <p>Select an environment to edit or create a new one</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- History Modal -->
    <div v-if="showHistoryModal" class="modal-overlay" @click.self="closeHistoryModal">
      <div class="modal modal-lg">
        <div class="modal-header">
          <h2>Request History</h2>
          <div class="header-actions">
            <button class="btn btn-danger btn-sm" @click="clearHistory">Clear All</button>
            <button class="btn-close" @click="closeHistoryModal">
              <i class="fas fa-times"></i>
            </button>
          </div>
        </div>
        <div class="modal-body">
          <div v-if="history.length === 0" class="empty-state">
            <i class="fas fa-history"></i>
            <p>No request history yet</p>
          </div>
          <div v-else class="history-list">
            <div
              v-for="item in history"
              :key="item.id"
              class="history-item"
              @click="loadFromHistory(item)"
            >
              <span class="method-badge" :class="item.method.toLowerCase()">{{ item.method }}</span>
              <span class="history-url">{{ item.url }}</span>
              <span class="status-badge" :class="getStatusClass(item.response_status)">
                {{ item.response_status }}
              </span>
              <span class="history-time">{{ item.response_time }}ms</span>
              <span class="history-date">{{ formatDate(item.executed_at) }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import api from '@/services/api'

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

// Tabs
const activeRequestTab = ref('params')
const activeResponseTab = ref('body')
const requestTabs = [
  { id: 'params', label: 'Params' },
  { id: 'headers', label: 'Headers' },
  { id: 'body', label: 'Body' },
  { id: 'auth', label: 'Auth' }
]
const responseTabs = [
  { id: 'body', label: 'Body' },
  { id: 'headers', label: 'Headers' }
]

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

const bodyTypes = [
  { value: 'none', label: 'None' },
  { value: 'json', label: 'JSON' },
  { value: 'form', label: 'Form' },
  { value: 'raw', label: 'Raw' }
]

// Modals
const showCollectionModal = ref(false)
const showEnvironmentModal = ref(false)
const showHistoryModal = ref(false)
const editingCollection = ref(null)
const editingEnvironment = ref(null)

const collectionForm = reactive({
  name: '',
  description: '',
  color: '#6366f1'
})

const environmentForm = reactive({
  name: '',
  variables: {}
})

const collectionColors = [
  '#6366f1', '#8b5cf6', '#ec4899', '#ef4444', '#f97316',
  '#eab308', '#22c55e', '#14b8a6', '#0ea5e9', '#64748b'
]

// Computed
const uncategorizedRequests = computed(() => {
  return requests.value.filter(r => !r.collection_id)
})

// Methods
async function loadData() {
  try {
    const [colRes, reqRes, envRes] = await Promise.all([
      api.get('/api-tester/collections'),
      api.get('/api-tester/requests'),
      api.get('/api-tester/environments')
    ])
    collections.value = colRes.data.items || []
    requests.value = reqRes.data.items || []
    environments.value = envRes.data.items || []

    const activeEnv = environments.value.find(e => e.is_active)
    if (activeEnv) {
      selectedEnvironmentId.value = activeEnv.id
    }
  } catch (error) {
    console.error('Failed to load data:', error)
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
  requestForm.auth_config = { ...req.auth_config }
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
    // Invalid URL, ignore
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

function getBodyPlaceholder() {
  switch (requestForm.body_type) {
    case 'json': return '{\n  "key": "value"\n}'
    case 'form': return 'key1=value1&key2=value2'
    default: return 'Enter request body'
  }
}

function formatJson() {
  try {
    const parsed = JSON.parse(requestForm.body)
    requestForm.body = JSON.stringify(parsed, null, 2)
  } catch {
    // Invalid JSON, ignore
  }
}

async function sendRequest() {
  if (!requestForm.url) return

  sending.value = true
  response.value = null

  try {
    const res = await api.post('/api-tester/execute', {
      request_id: currentRequest.value?.id,
      method: requestForm.method,
      url: requestForm.url,
      headers: requestForm.headers,
      body_type: requestForm.body_type,
      body: requestForm.body,
      auth_type: requestForm.auth_type,
      auth_config: requestForm.auth_config
    })
    response.value = res.data
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
  if (!requestForm.name || !requestForm.url) return

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
      await api.put(`/api-tester/requests/${currentRequest.value.id}`, data)
    } else {
      const res = await api.post('/api-tester/requests', data)
      currentRequest.value = res.data
      isNewRequest.value = false
    }
    await loadData()
  } catch (error) {
    console.error('Failed to save request:', error)
  }
}

function getStatusClass(status) {
  if (!status) return 'unknown'
  if (status >= 200 && status < 300) return 'success'
  if (status >= 300 && status < 400) return 'redirect'
  if (status >= 400 && status < 500) return 'client-error'
  return 'server-error'
}

function getStatusText(status) {
  const statusTexts = {
    200: 'OK', 201: 'Created', 204: 'No Content',
    301: 'Moved Permanently', 302: 'Found', 304: 'Not Modified',
    400: 'Bad Request', 401: 'Unauthorized', 403: 'Forbidden',
    404: 'Not Found', 405: 'Method Not Allowed', 422: 'Unprocessable Entity',
    500: 'Internal Server Error', 502: 'Bad Gateway', 503: 'Service Unavailable'
  }
  return statusTexts[status] || ''
}

function formatBytes(bytes) {
  if (!bytes) return '0 B'
  const sizes = ['B', 'KB', 'MB', 'GB']
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

function formatDate(dateStr) {
  return new Date(dateStr).toLocaleString()
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

function closeCollectionModal() {
  showCollectionModal.value = false
  editingCollection.value = null
}

async function saveCollection() {
  if (!collectionForm.name) return

  try {
    if (editingCollection.value) {
      await api.put(`/api-tester/collections/${editingCollection.value.id}`, collectionForm)
    } else {
      await api.post('/api-tester/collections', collectionForm)
    }
    await loadData()
    closeCollectionModal()
  } catch (error) {
    console.error('Failed to save collection:', error)
  }
}

async function deleteCollection(id) {
  if (!confirm('Delete this collection and all its requests?')) return

  try {
    await api.delete(`/api-tester/collections/${id}`)
    await loadData()
  } catch (error) {
    console.error('Failed to delete collection:', error)
  }
}

// Environment methods
function openEnvironmentModal() {
  showEnvironmentModal.value = true
  editingEnvironment.value = null
}

function closeEnvironmentModal() {
  showEnvironmentModal.value = false
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
  if (!environmentForm.name) return

  try {
    if (editingEnvironment.value?.id) {
      await api.put(`/api-tester/environments/${editingEnvironment.value.id}`, environmentForm)
    } else {
      const res = await api.post('/api-tester/environments', environmentForm)
      editingEnvironment.value = res.data
    }
    await loadData()
  } catch (error) {
    console.error('Failed to save environment:', error)
  }
}

async function activateEnvironment() {
  if (!editingEnvironment.value?.id) return

  try {
    await api.put(`/api-tester/environments/${editingEnvironment.value.id}`, { is_active: true })
    selectedEnvironmentId.value = editingEnvironment.value.id
    await loadData()
  } catch (error) {
    console.error('Failed to activate environment:', error)
  }
}

async function setActiveEnvironment() {
  if (!selectedEnvironmentId.value) return

  try {
    await api.put(`/api-tester/environments/${selectedEnvironmentId.value}`, { is_active: true })
    await loadData()
  } catch (error) {
    console.error('Failed to set active environment:', error)
  }
}

async function deleteEnvironment() {
  if (!editingEnvironment.value?.id) return
  if (!confirm('Delete this environment?')) return

  try {
    await api.delete(`/api-tester/environments/${editingEnvironment.value.id}`)
    editingEnvironment.value = null
    await loadData()
  } catch (error) {
    console.error('Failed to delete environment:', error)
  }
}

// History methods
async function openHistoryModal() {
  showHistoryModal.value = true
  try {
    const res = await api.get('/api-tester/history')
    history.value = res.data.items || []
  } catch (error) {
    console.error('Failed to load history:', error)
  }
}

function closeHistoryModal() {
  showHistoryModal.value = false
}

async function loadFromHistory(item) {
  try {
    const res = await api.get(`/api-tester/history/${item.id}`)
    const historyItem = res.data

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

    closeHistoryModal()
  } catch (error) {
    console.error('Failed to load history item:', error)
  }
}

async function clearHistory() {
  if (!confirm('Clear all request history?')) return

  try {
    await api.delete('/api-tester/history')
    history.value = []
  } catch (error) {
    console.error('Failed to clear history:', error)
  }
}

onMounted(() => {
  loadData()
})
</script>

<style scoped>
.api-tester {
  display: flex;
  flex-direction: column;
  height: 100%;
  background: var(--bg-primary);
}

.api-tester-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 1.5rem;
  background: var(--bg-secondary);
  border-bottom: 1px solid var(--border-color);
}

.api-tester-header h1 {
  font-size: 1.5rem;
  margin: 0;
}

.header-actions {
  display: flex;
  gap: 0.75rem;
  align-items: center;
}

.environment-select {
  padding: 0.5rem 1rem;
  background: var(--bg-tertiary);
  border: 1px solid var(--border-color);
  border-radius: 6px;
  color: var(--text-primary);
  min-width: 150px;
}

.api-tester-layout {
  display: flex;
  flex: 1;
  overflow: hidden;
}

/* Sidebar */
.sidebar {
  width: 280px;
  background: var(--bg-secondary);
  border-right: 1px solid var(--border-color);
  display: flex;
  flex-direction: column;
  overflow-y: auto;
}

.sidebar-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
  border-bottom: 1px solid var(--border-color);
}

.sidebar-header h3 {
  margin: 0;
  font-size: 0.875rem;
  text-transform: uppercase;
  color: var(--text-secondary);
}

.collections-list {
  flex: 1;
  overflow-y: auto;
}

.collection-item {
  border-bottom: 1px solid var(--border-color);
}

.collection-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem 1rem;
  cursor: pointer;
  transition: background 0.2s;
}

.collection-header:hover {
  background: var(--bg-tertiary);
}

.collection-info {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.collection-icon {
  font-size: 0.875rem;
}

.collection-name {
  font-weight: 500;
}

.request-count {
  color: var(--text-secondary);
  font-size: 0.75rem;
}

.collection-actions {
  display: flex;
  gap: 0.25rem;
  opacity: 0;
  transition: opacity 0.2s;
}

.collection-header:hover .collection-actions {
  opacity: 1;
}

.collection-requests {
  background: var(--bg-primary);
  padding: 0.5rem 0;
}

.request-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 1rem 0.5rem 2rem;
  cursor: pointer;
  transition: background 0.2s;
}

.request-item:hover {
  background: var(--bg-tertiary);
}

.request-item.active {
  background: var(--primary-color);
  color: white;
}

.request-item.active .method-badge {
  background: rgba(255, 255, 255, 0.2);
  color: white;
}

.method-badge {
  font-size: 0.625rem;
  font-weight: 700;
  padding: 0.125rem 0.375rem;
  border-radius: 3px;
  text-transform: uppercase;
}

.method-badge.get { background: #22c55e20; color: #22c55e; }
.method-badge.post { background: #eab30820; color: #eab308; }
.method-badge.put { background: #0ea5e920; color: #0ea5e9; }
.method-badge.patch { background: #8b5cf620; color: #8b5cf6; }
.method-badge.delete { background: #ef444420; color: #ef4444; }
.method-badge.head { background: #64748b20; color: #64748b; }
.method-badge.options { background: #ec489920; color: #ec4899; }

.request-name {
  font-size: 0.875rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.empty-collection {
  padding: 0.5rem 2rem;
  color: var(--text-secondary);
  font-size: 0.75rem;
  font-style: italic;
}

.sidebar-section {
  padding: 1rem;
  border-top: 1px solid var(--border-color);
}

.sidebar-section h4 {
  font-size: 0.75rem;
  text-transform: uppercase;
  color: var(--text-secondary);
  margin: 0 0 0.5rem 0;
}

/* Main Content */
.main-content {
  flex: 1;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.request-bar {
  display: flex;
  gap: 0.5rem;
  padding: 1rem;
  background: var(--bg-secondary);
  border-bottom: 1px solid var(--border-color);
}

.method-select {
  padding: 0.75rem 1rem;
  background: var(--bg-tertiary);
  border: 1px solid var(--border-color);
  border-radius: 6px;
  color: var(--text-primary);
  font-weight: 600;
  min-width: 100px;
}

.url-input {
  flex: 1;
  padding: 0.75rem 1rem;
  background: var(--bg-primary);
  border: 1px solid var(--border-color);
  border-radius: 6px;
  color: var(--text-primary);
  font-family: monospace;
}

.btn-send {
  min-width: 100px;
}

.request-name-bar {
  display: flex;
  gap: 0.5rem;
  padding: 0.5rem 1rem;
  background: var(--bg-tertiary);
  border-bottom: 1px solid var(--border-color);
}

.request-name-input {
  flex: 1;
  padding: 0.5rem;
  background: var(--bg-primary);
  border: 1px solid var(--border-color);
  border-radius: 4px;
  color: var(--text-primary);
}

.collection-select {
  padding: 0.5rem;
  background: var(--bg-primary);
  border: 1px solid var(--border-color);
  border-radius: 4px;
  color: var(--text-primary);
  min-width: 150px;
}

/* Request Section */
.request-section {
  border-bottom: 1px solid var(--border-color);
}

.tabs {
  display: flex;
  gap: 0;
  background: var(--bg-secondary);
  border-bottom: 1px solid var(--border-color);
}

.tab {
  padding: 0.75rem 1.5rem;
  background: none;
  border: none;
  color: var(--text-secondary);
  cursor: pointer;
  font-size: 0.875rem;
  border-bottom: 2px solid transparent;
  transition: all 0.2s;
}

.tab:hover {
  color: var(--text-primary);
}

.tab.active {
  color: var(--primary-color);
  border-bottom-color: var(--primary-color);
}

.tab .badge {
  background: var(--primary-color);
  color: white;
  font-size: 0.625rem;
  padding: 0.125rem 0.375rem;
  border-radius: 10px;
  margin-left: 0.25rem;
}

.tab-content {
  padding: 1rem;
  max-height: 200px;
  overflow-y: auto;
}

.tab-hint {
  color: var(--text-secondary);
  font-size: 0.75rem;
  margin-bottom: 0.75rem;
}

.key-value-editor {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.key-value-row {
  display: flex;
  gap: 0.5rem;
}

.key-value-row input {
  flex: 1;
  padding: 0.5rem;
  background: var(--bg-primary);
  border: 1px solid var(--border-color);
  border-radius: 4px;
  color: var(--text-primary);
  font-family: monospace;
  font-size: 0.875rem;
}

.body-type-selector {
  display: flex;
  gap: 1.5rem;
  margin-bottom: 1rem;
}

.body-type-selector label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
  font-size: 0.875rem;
}

.body-editor {
  position: relative;
}

.body-textarea {
  width: 100%;
  padding: 0.75rem;
  background: var(--bg-primary);
  border: 1px solid var(--border-color);
  border-radius: 6px;
  color: var(--text-primary);
  font-family: monospace;
  font-size: 0.875rem;
  resize: vertical;
}

.format-btn {
  position: absolute;
  top: 0.5rem;
  right: 0.5rem;
}

.auth-type-selector select {
  padding: 0.5rem 1rem;
  background: var(--bg-primary);
  border: 1px solid var(--border-color);
  border-radius: 6px;
  color: var(--text-primary);
  min-width: 200px;
}

.auth-config {
  margin-top: 1rem;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

/* Response Section */
.response-section {
  flex: 1;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.response-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
  background: var(--bg-secondary);
  border-bottom: 1px solid var(--border-color);
}

.response-header h3 {
  margin: 0;
  font-size: 1rem;
}

.response-meta {
  display: flex;
  gap: 1rem;
  align-items: center;
}

.status-badge {
  padding: 0.25rem 0.75rem;
  border-radius: 4px;
  font-weight: 600;
  font-size: 0.875rem;
}

.status-badge.success { background: #22c55e20; color: #22c55e; }
.status-badge.redirect { background: #0ea5e920; color: #0ea5e9; }
.status-badge.client-error { background: #f9731620; color: #f97316; }
.status-badge.server-error { background: #ef444420; color: #ef4444; }
.status-badge.unknown { background: #64748b20; color: #64748b; }

.meta-item {
  font-size: 0.875rem;
  color: var(--text-secondary);
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.response-content {
  flex: 1;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.response-content .tab-content {
  flex: 1;
  overflow-y: auto;
  max-height: none;
}

.response-body {
  height: 100%;
}

.error-message {
  color: #ef4444;
  padding: 1rem;
  background: #ef444410;
  border-radius: 6px;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.response-text {
  margin: 0;
  padding: 1rem;
  background: var(--bg-primary);
  border-radius: 6px;
  font-family: monospace;
  font-size: 0.875rem;
  white-space: pre-wrap;
  word-break: break-word;
  overflow-x: auto;
}

.headers-table {
  width: 100%;
  border-collapse: collapse;
}

.headers-table tr {
  border-bottom: 1px solid var(--border-color);
}

.headers-table td {
  padding: 0.5rem;
  font-size: 0.875rem;
}

.header-key {
  font-weight: 600;
  color: var(--primary-color);
  width: 30%;
}

.header-value {
  font-family: monospace;
  word-break: break-all;
}

.response-placeholder {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: var(--text-secondary);
}

.response-placeholder i {
  font-size: 3rem;
  margin-bottom: 1rem;
  opacity: 0.5;
}

/* Modals */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal {
  background: var(--bg-secondary);
  border-radius: 12px;
  width: 90%;
  max-height: 80vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
}

.modal-sm { max-width: 400px; }
.modal-lg { max-width: 800px; }

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 1.5rem;
  border-bottom: 1px solid var(--border-color);
}

.modal-header h2 {
  margin: 0;
  font-size: 1.25rem;
}

.modal-body {
  padding: 1.5rem;
  overflow-y: auto;
  flex: 1;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  padding: 1rem 1.5rem;
  border-top: 1px solid var(--border-color);
}

.form-group {
  margin-bottom: 1rem;
}

.form-group label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
  font-size: 0.875rem;
}

.form-group input,
.form-group textarea,
.form-group select {
  width: 100%;
  padding: 0.75rem;
  background: var(--bg-primary);
  border: 1px solid var(--border-color);
  border-radius: 6px;
  color: var(--text-primary);
}

.hint {
  font-size: 0.75rem;
  color: var(--text-secondary);
  margin-bottom: 0.5rem;
}

.color-picker {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.color-option {
  width: 32px;
  height: 32px;
  border-radius: 6px;
  border: 2px solid transparent;
  cursor: pointer;
  transition: transform 0.2s, border-color 0.2s;
}

.color-option:hover {
  transform: scale(1.1);
}

.color-option.active {
  border-color: white;
  box-shadow: 0 0 0 2px var(--primary-color);
}

/* Environments Modal */
.environments-layout {
  display: flex;
  gap: 1.5rem;
  min-height: 300px;
}

.environments-sidebar {
  width: 200px;
  border-right: 1px solid var(--border-color);
  padding-right: 1.5rem;
}

.environment-item {
  padding: 0.75rem;
  border-radius: 6px;
  cursor: pointer;
  margin-bottom: 0.5rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  transition: background 0.2s;
}

.environment-item:hover {
  background: var(--bg-tertiary);
}

.environment-item.active {
  background: var(--primary-color);
  color: white;
}

.active-badge {
  font-size: 0.625rem;
  background: #22c55e;
  color: white;
  padding: 0.125rem 0.375rem;
  border-radius: 3px;
}

.environment-editor {
  flex: 1;
}

.environment-form {
  height: 100%;
}

.environment-actions {
  display: flex;
  gap: 0.75rem;
  margin-top: 1.5rem;
}

.no-selection {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100%;
  color: var(--text-secondary);
}

/* History Modal */
.history-list {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.history-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem;
  background: var(--bg-tertiary);
  border-radius: 6px;
  cursor: pointer;
  transition: background 0.2s;
}

.history-item:hover {
  background: var(--bg-primary);
}

.history-url {
  flex: 1;
  font-family: monospace;
  font-size: 0.875rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.history-time {
  color: var(--text-secondary);
  font-size: 0.75rem;
}

.history-date {
  color: var(--text-secondary);
  font-size: 0.75rem;
  min-width: 120px;
  text-align: right;
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem;
  color: var(--text-secondary);
}

.empty-state i {
  font-size: 3rem;
  margin-bottom: 1rem;
  opacity: 0.5;
}

/* Buttons */
.btn {
  padding: 0.5rem 1rem;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 0.875rem;
  font-weight: 500;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  transition: all 0.2s;
}

.btn-primary {
  background: var(--primary-color);
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background: var(--primary-hover);
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-secondary {
  background: var(--bg-tertiary);
  color: var(--text-primary);
}

.btn-secondary:hover {
  background: var(--border-color);
}

.btn-danger {
  background: #ef4444;
  color: white;
}

.btn-danger:hover {
  background: #dc2626;
}

.btn-ghost {
  background: transparent;
  color: var(--text-secondary);
}

.btn-ghost:hover {
  background: var(--bg-tertiary);
  color: var(--text-primary);
}

.btn-sm {
  padding: 0.375rem 0.75rem;
  font-size: 0.75rem;
}

.btn-icon {
  background: none;
  border: none;
  color: var(--text-secondary);
  cursor: pointer;
  padding: 0.25rem;
  border-radius: 4px;
  transition: all 0.2s;
}

.btn-icon:hover {
  background: var(--bg-tertiary);
  color: var(--text-primary);
}

.btn-icon.danger:hover {
  background: #ef444420;
  color: #ef4444;
}

.btn-close {
  background: none;
  border: none;
  color: var(--text-secondary);
  cursor: pointer;
  padding: 0.5rem;
  border-radius: 6px;
  transition: all 0.2s;
}

.btn-close:hover {
  background: var(--bg-tertiary);
  color: var(--text-primary);
}

.full-width {
  width: 100%;
  justify-content: center;
}
</style>
