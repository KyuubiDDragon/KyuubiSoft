<script setup>
import { ref, reactive, watch, nextTick } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import api from '@/core/api/axios'
import QRCode from 'qrcode'
import {
  UserIcon,
  ShieldCheckIcon,
  PaintBrushIcon,
  BellIcon,
  CheckCircleIcon,
  XCircleIcon,
  KeyIcon,
  ClipboardIcon,
  TrashIcon,
  PlusIcon,
  EyeIcon,
  EyeSlashIcon,
  ArrowDownTrayIcon,
  ArrowUpTrayIcon,
  DocumentArrowDownIcon,
  DocumentArrowUpIcon,
  TagIcon,
  SparklesIcon,
} from '@heroicons/vue/24/outline'
import { useExportImportStore } from '@/stores/exportImport'
import { useAIStore } from '@/stores/ai'
import TagManager from '@/components/TagManager.vue'

const authStore = useAuthStore()
const uiStore = useUiStore()
const exportImportStore = useExportImportStore()
const aiStore = useAIStore()

const activeTab = ref('profile')
const isSaving = ref(false)
const isChangingPassword = ref(false)

// 2FA state
const twoFactorEnabled = ref(false)
const twoFactorSetup = ref(null)
const qrCodeDataUrl = ref('')
const verificationCode = ref('')
const isEnabling2FA = ref(false)
const isDisabling2FA = ref(false)
const disableCode = ref('')

const tabs = [
  { id: 'profile', name: 'Profil', icon: UserIcon },
  { id: 'security', name: 'Sicherheit', icon: ShieldCheckIcon },
  { id: 'api-keys', name: 'API-Keys', icon: KeyIcon },
  { id: 'ai', name: 'AI Assistent', icon: SparklesIcon },
  { id: 'tags', name: 'Tags', icon: TagIcon },
  { id: 'export-import', name: 'Export/Import', icon: ArrowDownTrayIcon },
  { id: 'appearance', name: 'Darstellung', icon: PaintBrushIcon },
]

// Export/Import state
const exportTypes = ref([])
const allExportTypes = [
  { id: 'lists', name: 'Listen', icon: '📋' },
  { id: 'documents', name: 'Dokumente', icon: '📄' },
  { id: 'snippets', name: 'Snippets', icon: '💻' },
  { id: 'bookmarks', name: 'Lesezeichen', icon: '🔖' },
  { id: 'connections', name: 'Verbindungen', icon: '🔌' },
  { id: 'passwords', name: 'Passwörter', icon: '🔐' },
  { id: 'checklists', name: 'Checklisten', icon: '✅' },
  { id: 'kanban', name: 'Kanban-Boards', icon: '📊' },
  { id: 'projects', name: 'Projekte', icon: '📁' },
  { id: 'invoices', name: 'Rechnungen', icon: '💰' },
  { id: 'calendar', name: 'Kalender', icon: '📅' },
  { id: 'time_entries', name: 'Zeiteinträge', icon: '⏱️' },
]
const exportFormat = ref('json')
const importFile = ref(null)
const importData = ref(null)
const conflictResolution = ref('skip')
const selectedImportTypes = ref([])

function toggleExportType(typeId) {
  const idx = exportTypes.value.indexOf(typeId)
  if (idx >= 0) {
    exportTypes.value.splice(idx, 1)
  } else {
    exportTypes.value.push(typeId)
  }
}

function selectAllExportTypes() {
  exportTypes.value = allExportTypes.map(t => t.id)
}

function deselectAllExportTypes() {
  exportTypes.value = []
}

async function handleExport() {
  if (exportTypes.value.length === 0) {
    uiStore.showError('Bitte wähle mindestens einen Datentyp')
    return
  }
  try {
    await exportImportStore.exportData(exportTypes.value, exportFormat.value)
    uiStore.showSuccess('Export erfolgreich!')
  } catch (error) {
    uiStore.showError('Export fehlgeschlagen')
  }
}

function handleFileSelect(event) {
  const file = event.target.files[0]
  if (!file) return

  const reader = new FileReader()
  reader.onload = async (e) => {
    try {
      const data = JSON.parse(e.target.result)
      importData.value = data
      const validation = await exportImportStore.validateImport(data)
      if (validation.valid) {
        selectedImportTypes.value = validation.types
      }
    } catch (error) {
      uiStore.showError('Ungültige Datei')
      importData.value = null
    }
  }
  reader.readAsText(file)
}

async function handleImport() {
  if (!importData.value) {
    uiStore.showError('Keine Datei ausgewählt')
    return
  }
  if (selectedImportTypes.value.length === 0) {
    uiStore.showError('Bitte wähle mindestens einen Datentyp')
    return
  }

  try {
    const result = await exportImportStore.importData(importData.value, {
      conflictResolution: conflictResolution.value,
      types: selectedImportTypes.value,
    })
    if (result.success) {
      uiStore.showSuccess('Import erfolgreich!')
    } else {
      uiStore.showError('Import teilweise fehlgeschlagen')
    }
  } catch (error) {
    uiStore.showError('Import fehlgeschlagen')
  }
}

function resetImport() {
  importFile.value = null
  importData.value = null
  selectedImportTypes.value = []
  exportImportStore.reset()
}

// API Keys state
const apiKeys = ref([])
const availableScopes = ref({})
const isLoadingApiKeys = ref(false)
const isCreatingApiKey = ref(false)
const newApiKeyResult = ref(null)
const showNewApiKey = ref(false)
const newKeyForm = reactive({
  name: '',
  scopes: [],
  expires_in_days: 0,
})

async function loadApiKeys() {
  isLoadingApiKeys.value = true
  try {
    const response = await api.get('/api/v1/settings/api-keys')
    apiKeys.value = response.data.data.items || []
    availableScopes.value = response.data.data.available_scopes || {}
  } catch (error) {
    uiStore.showError('Fehler beim Laden der API-Keys')
  } finally {
    isLoadingApiKeys.value = false
  }
}

async function createApiKey() {
  if (!newKeyForm.name) {
    uiStore.showError('Name ist erforderlich')
    return
  }
  if (newKeyForm.scopes.length === 0) {
    uiStore.showError('Mindestens ein Scope ist erforderlich')
    return
  }

  isCreatingApiKey.value = true
  try {
    const response = await api.post('/api/v1/settings/api-keys', {
      name: newKeyForm.name,
      scopes: newKeyForm.scopes,
      expires_in_days: newKeyForm.expires_in_days || null,
    })
    newApiKeyResult.value = response.data.data
    showNewApiKey.value = true
    // Reset form
    newKeyForm.name = ''
    newKeyForm.scopes = []
    newKeyForm.expires_in_days = 0
    // Reload list
    await loadApiKeys()
    uiStore.showSuccess('API-Key erstellt!')
  } catch (error) {
    const message = error.response?.data?.error || 'Fehler beim Erstellen'
    uiStore.showError(message)
  } finally {
    isCreatingApiKey.value = false
  }
}

async function revokeApiKey(keyId) {
  if (!confirm('API-Key wirklich widerrufen?')) return

  try {
    await api.post(`/api/v1/settings/api-keys/${keyId}/revoke`)
    await loadApiKeys()
    uiStore.showSuccess('API-Key widerrufen')
  } catch (error) {
    uiStore.showError('Fehler beim Widerrufen')
  }
}

async function deleteApiKey(keyId) {
  if (!confirm('API-Key endgültig löschen?')) return

  try {
    await api.delete(`/api/v1/settings/api-keys/${keyId}`)
    await loadApiKeys()
    uiStore.showSuccess('API-Key gelöscht')
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
  }
}

function copyToClipboard(text) {
  navigator.clipboard.writeText(text)
  uiStore.showSuccess('In Zwischenablage kopiert!')
}

function toggleScope(scope) {
  const idx = newKeyForm.scopes.indexOf(scope)
  if (idx >= 0) {
    newKeyForm.scopes.splice(idx, 1)
  } else {
    newKeyForm.scopes.push(scope)
  }
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

// Load API keys when tab is selected
watch(activeTab, (newTab) => {
  if (newTab === 'api-keys' && apiKeys.value.length === 0) {
    loadApiKeys()
  }
  if (newTab === 'export-import' && Object.keys(exportImportStore.stats).length === 0) {
    exportImportStore.loadStats()
  }
  if (newTab === 'ai' && !aiStore.settings) {
    aiStore.fetchSettings()
  }
})

// AI Settings state
const aiForm = reactive({
  provider: 'openai',
  api_key: '',
  model: 'gpt-4o-mini',
  api_base_url: '',
  max_tokens: 2000,
  temperature: 0.7,
})
const showAiApiKey = ref(false)
const isSavingAi = ref(false)

// Watch AI settings to populate form
watch(() => aiStore.settings, (settings) => {
  if (settings) {
    aiForm.provider = settings.provider || 'openai'
    aiForm.model = settings.model || 'gpt-4o-mini'
    aiForm.api_base_url = settings.api_base_url || ''
    aiForm.max_tokens = settings.max_tokens || 2000
    aiForm.temperature = settings.temperature || 0.7
    // Don't populate api_key - it's not returned from server
  }
}, { immediate: true })

// Auto-select first model when provider changes
watch(() => aiForm.provider, (newProvider) => {
  const provider = aiStore.providers.find(p => p.value === newProvider)
  if (provider && provider.models.length > 0) {
    // Check if current model is valid for this provider
    const validModel = provider.models.find(m => m.id === aiForm.model)
    if (!validModel) {
      // Set first model as default
      aiForm.model = provider.models[0].id
    }
  } else if (newProvider === 'custom') {
    // Keep custom model or clear it
    if (!aiForm.model) {
      aiForm.model = ''
    }
  }
})

async function saveAiSettings() {
  isSavingAi.value = true
  try {
    await aiStore.saveSettings({
      provider: aiForm.provider,
      api_key: aiForm.api_key || undefined,
      model: aiForm.model,
      api_base_url: aiForm.api_base_url || undefined,
      max_tokens: aiForm.max_tokens,
      temperature: aiForm.temperature,
    })
    aiForm.api_key = '' // Clear after save
    uiStore.showSuccess('AI-Einstellungen gespeichert!')
  } catch (error) {
    uiStore.showError(error.message || 'Fehler beim Speichern')
  } finally {
    isSavingAi.value = false
  }
}

async function removeAiApiKey() {
  if (!confirm('API-Key wirklich entfernen?')) return
  try {
    await aiStore.removeApiKey()
    uiStore.showSuccess('API-Key entfernt')
  } catch (error) {
    uiStore.showError('Fehler beim Entfernen')
  }
}

const profile = reactive({
  username: authStore.user?.username || '',
  email: authStore.user?.email || '',
})

const security = reactive({
  currentPassword: '',
  newPassword: '',
  confirmPassword: '',
})

// Watch for auth store user changes
watch(() => authStore.user, (user) => {
  if (user) {
    profile.username = user.username || ''
    profile.email = user.email || ''
    twoFactorEnabled.value = !!user.two_factor_enabled
  }
}, { immediate: true })

async function saveProfile() {
  if (isSaving.value) return
  isSaving.value = true

  try {
    const response = await api.put('/api/v1/users/me/profile', {
      username: profile.username
    })
    // Update auth store with new user data
    if (response.data.data) {
      authStore.user = { ...authStore.user, ...response.data.data }
    }
    uiStore.showSuccess('Profil gespeichert!')
  } catch (error) {
    const message = error.response?.data?.error || 'Fehler beim Speichern'
    uiStore.showError(message)
  } finally {
    isSaving.value = false
  }
}

async function changePassword() {
  if (isChangingPassword.value) return

  if (security.newPassword !== security.confirmPassword) {
    uiStore.showError('Passwörter stimmen nicht überein')
    return
  }

  if (security.newPassword.length < 8) {
    uiStore.showError('Passwort muss mindestens 8 Zeichen lang sein')
    return
  }

  isChangingPassword.value = true

  try {
    await api.put('/api/v1/users/me/password', {
      current_password: security.currentPassword,
      new_password: security.newPassword,
      confirm_password: security.confirmPassword
    })
    uiStore.showSuccess('Passwort geändert!')
    security.currentPassword = ''
    security.newPassword = ''
    security.confirmPassword = ''
  } catch (error) {
    const message = error.response?.data?.error || 'Fehler beim Ändern des Passworts'
    uiStore.showError(message)
  } finally {
    isChangingPassword.value = false
  }
}

async function startEnable2FA() {
  isEnabling2FA.value = true
  try {
    const response = await api.post('/api/v1/auth/2fa/enable')
    twoFactorSetup.value = response.data.data

    // Generate QR code from otpauth URL
    if (twoFactorSetup.value.qr_code_url) {
      qrCodeDataUrl.value = await QRCode.toDataURL(twoFactorSetup.value.qr_code_url, {
        width: 200,
        margin: 2,
        color: {
          dark: '#000000',
          light: '#ffffff'
        }
      })
    }
  } catch (error) {
    const message = error.response?.data?.error || 'Fehler beim Aktivieren von 2FA'
    uiStore.showError(message)
    isEnabling2FA.value = false
  }
}

async function verify2FA() {
  if (!verificationCode.value || verificationCode.value.length !== 6) {
    uiStore.showError('Bitte gib einen gültigen 6-stelligen Code ein')
    return
  }

  try {
    await api.post('/api/v1/auth/2fa/verify', {
      code: verificationCode.value
    })

    // Refresh user data to get updated two_factor_enabled status
    await authStore.fetchUser()

    twoFactorEnabled.value = true
    twoFactorSetup.value = null
    qrCodeDataUrl.value = ''
    verificationCode.value = ''
    isEnabling2FA.value = false
    uiStore.showSuccess('2FA erfolgreich aktiviert!')
  } catch (error) {
    const message = error.response?.data?.error || 'Ungültiger Code'
    uiStore.showError(message)
  }
}

function cancelEnable2FA() {
  twoFactorSetup.value = null
  qrCodeDataUrl.value = ''
  verificationCode.value = ''
  isEnabling2FA.value = false
}

async function disable2FA() {
  if (!disableCode.value || disableCode.value.length !== 6) {
    uiStore.showError('Bitte gib deinen aktuellen 2FA-Code ein')
    return
  }

  try {
    await api.delete('/api/v1/auth/2fa/disable', {
      data: { code: disableCode.value }
    })
    twoFactorEnabled.value = false
    disableCode.value = ''
    isDisabling2FA.value = false
    uiStore.showSuccess('2FA deaktiviert')
  } catch (error) {
    const message = error.response?.data?.error || 'Ungültiger Code'
    uiStore.showError(message)
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div>
      <h1 class="text-2xl font-bold text-white">Einstellungen</h1>
      <p class="text-gray-400 mt-1">Verwalte dein Konto und deine Präferenzen</p>
    </div>

    <div class="flex flex-col lg:flex-row gap-6">
      <!-- Sidebar tabs -->
      <div class="lg:w-64 shrink-0">
        <nav class="flex lg:flex-col gap-1 overflow-x-auto lg:overflow-visible">
          <button
            v-for="tab in tabs"
            :key="tab.id"
            @click="activeTab = tab.id"
            class="flex items-center gap-3 px-4 py-2.5 rounded-lg transition-all duration-200 whitespace-nowrap"
            :class="[
              activeTab === tab.id
                ? 'bg-primary-600 text-white'
                : 'text-gray-400 hover:bg-dark-700 hover:text-white'
            ]"
          >
            <component :is="tab.icon" class="w-5 h-5" />
            {{ tab.name }}
          </button>
        </nav>
      </div>

      <!-- Content -->
      <div class="flex-1">
        <!-- Profile -->
        <div v-if="activeTab === 'profile'" class="card p-6">
          <h2 class="text-lg font-semibold text-white mb-6">Profil</h2>

          <div class="space-y-4">
            <div>
              <label class="label">Benutzername</label>
              <input
                v-model="profile.username"
                type="text"
                class="input"
              />
            </div>

            <div>
              <label class="label">E-Mail</label>
              <input
                v-model="profile.email"
                type="email"
                class="input bg-dark-700/50"
                disabled
              />
              <p class="mt-1 text-xs text-gray-500">E-Mail kann nicht geändert werden</p>
            </div>

            <button
              @click="saveProfile"
              :disabled="isSaving"
              class="btn-primary"
            >
              <span v-if="isSaving" class="flex items-center gap-2">
                <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" />
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
                Speichern...
              </span>
              <span v-else>Speichern</span>
            </button>
          </div>
        </div>

        <!-- Security -->
        <div v-if="activeTab === 'security'" class="space-y-6">
          <div class="card p-6">
            <h2 class="text-lg font-semibold text-white mb-6">Passwort ändern</h2>

            <div class="space-y-4">
              <div>
                <label class="label">Aktuelles Passwort</label>
                <input
                  v-model="security.currentPassword"
                  type="password"
                  class="input"
                  autocomplete="current-password"
                />
              </div>

              <div>
                <label class="label">Neues Passwort</label>
                <input
                  v-model="security.newPassword"
                  type="password"
                  class="input"
                  autocomplete="new-password"
                />
              </div>

              <div>
                <label class="label">Passwort bestätigen</label>
                <input
                  v-model="security.confirmPassword"
                  type="password"
                  class="input"
                  autocomplete="new-password"
                />
              </div>

              <button
                @click="changePassword"
                :disabled="isChangingPassword"
                class="btn-primary"
              >
                <span v-if="isChangingPassword" class="flex items-center gap-2">
                  <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                  </svg>
                  Ändern...
                </span>
                <span v-else>Passwort ändern</span>
              </button>
            </div>
          </div>

          <!-- 2FA Section -->
          <div class="card p-6">
            <div class="flex items-center justify-between mb-4">
              <div>
                <h2 class="text-lg font-semibold text-white">Zwei-Faktor-Authentifizierung</h2>
                <p class="text-gray-400 text-sm mt-1">
                  Erhöhe die Sicherheit deines Kontos durch 2FA.
                </p>
              </div>
              <div v-if="twoFactorEnabled" class="flex items-center gap-2 text-green-400">
                <CheckCircleIcon class="w-5 h-5" />
                <span class="text-sm font-medium">Aktiv</span>
              </div>
              <div v-else class="flex items-center gap-2 text-gray-500">
                <XCircleIcon class="w-5 h-5" />
                <span class="text-sm">Inaktiv</span>
              </div>
            </div>

            <!-- 2FA Setup Flow -->
            <div v-if="twoFactorSetup" class="space-y-4 border-t border-dark-700 pt-4">
              <p class="text-gray-300">Scanne den QR-Code mit deiner Authenticator-App:</p>

              <div class="bg-white p-4 rounded-lg inline-block">
                <img v-if="qrCodeDataUrl" :src="qrCodeDataUrl" alt="2FA QR Code" class="w-48 h-48" />
                <div v-else class="w-48 h-48 flex items-center justify-center">
                  <svg class="animate-spin h-8 w-8 text-gray-400" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                  </svg>
                </div>
              </div>

              <div class="bg-dark-700/50 rounded-lg p-4">
                <p class="text-sm text-gray-400 mb-2">Manueller Schlüssel:</p>
                <code class="text-primary-400 text-sm break-all">{{ twoFactorSetup.secret }}</code>
              </div>

              <div>
                <label class="label">Bestätigungscode</label>
                <input
                  v-model="verificationCode"
                  type="text"
                  inputmode="numeric"
                  pattern="[0-9]*"
                  maxlength="6"
                  class="input w-48 text-center tracking-widest text-lg"
                  placeholder="000000"
                />
              </div>

              <div class="flex gap-3">
                <button @click="cancelEnable2FA" class="btn-secondary">
                  Abbrechen
                </button>
                <button @click="verify2FA" class="btn-primary">
                  Verifizieren & Aktivieren
                </button>
              </div>
            </div>

            <!-- 2FA Disable Flow -->
            <div v-else-if="isDisabling2FA" class="space-y-4 border-t border-dark-700 pt-4">
              <p class="text-gray-300">Gib deinen aktuellen 2FA-Code ein, um 2FA zu deaktivieren:</p>

              <div>
                <label class="label">2FA-Code</label>
                <input
                  v-model="disableCode"
                  type="text"
                  inputmode="numeric"
                  pattern="[0-9]*"
                  maxlength="6"
                  class="input w-48 text-center tracking-widest text-lg"
                  placeholder="000000"
                />
              </div>

              <div class="flex gap-3">
                <button @click="isDisabling2FA = false; disableCode = ''" class="btn-secondary">
                  Abbrechen
                </button>
                <button @click="disable2FA" class="btn-primary bg-red-600 hover:bg-red-700">
                  2FA Deaktivieren
                </button>
              </div>
            </div>

            <!-- 2FA Action Buttons -->
            <div v-else class="pt-2">
              <button
                v-if="!twoFactorEnabled"
                @click="startEnable2FA"
                :disabled="isEnabling2FA"
                class="btn-primary"
              >
                <span v-if="isEnabling2FA">Laden...</span>
                <span v-else>2FA aktivieren</span>
              </button>
              <button
                v-else
                @click="isDisabling2FA = true"
                class="btn-secondary text-red-400"
              >
                2FA deaktivieren
              </button>
            </div>
          </div>
        </div>

        <!-- API Keys -->
        <div v-if="activeTab === 'api-keys'" class="space-y-6">
          <!-- New API Key Result Modal -->
          <div v-if="showNewApiKey && newApiKeyResult" class="card p-6 border-2 border-green-500/50 bg-green-500/10">
            <div class="flex items-start justify-between mb-4">
              <div>
                <h3 class="text-lg font-semibold text-green-400">Neuer API-Key erstellt!</h3>
                <p class="text-sm text-gray-400 mt-1">Kopiere den Key jetzt - er wird nur einmal angezeigt!</p>
              </div>
              <button @click="showNewApiKey = false; newApiKeyResult = null" class="text-gray-400 hover:text-white">
                <XCircleIcon class="w-6 h-6" />
              </button>
            </div>
            <div class="bg-dark-800 rounded-lg p-4">
              <div class="flex items-center justify-between gap-4">
                <code class="text-primary-400 font-mono text-sm break-all flex-1">{{ newApiKeyResult.key }}</code>
                <button @click="copyToClipboard(newApiKeyResult.key)" class="btn-secondary shrink-0">
                  <ClipboardIcon class="w-5 h-5" />
                  Kopieren
                </button>
              </div>
            </div>
          </div>

          <!-- Create New Key -->
          <div class="card p-6">
            <h2 class="text-lg font-semibold text-white mb-4">Neuen API-Key erstellen</h2>

            <div class="space-y-4">
              <div>
                <label class="label">Name</label>
                <input
                  v-model="newKeyForm.name"
                  type="text"
                  class="input"
                  placeholder="z.B. Mobile App, CI/CD Pipeline"
                />
              </div>

              <div>
                <label class="label">Berechtigungen (Scopes)</label>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-2 mt-2">
                  <label
                    v-for="(label, scope) in availableScopes"
                    :key="scope"
                    class="flex items-center gap-2 p-2 rounded-lg cursor-pointer transition-colors"
                    :class="newKeyForm.scopes.includes(scope) ? 'bg-primary-600/20 border border-primary-500' : 'bg-dark-700 hover:bg-dark-600'"
                  >
                    <input
                      type="checkbox"
                      :checked="newKeyForm.scopes.includes(scope)"
                      @change="toggleScope(scope)"
                      class="rounded border-gray-600 bg-dark-700 text-primary-600 focus:ring-primary-600"
                    />
                    <span class="text-sm text-gray-300">{{ label }}</span>
                  </label>
                </div>
              </div>

              <div>
                <label class="label">Gültigkeit (optional)</label>
                <select v-model="newKeyForm.expires_in_days" class="input">
                  <option :value="0">Unbegrenzt</option>
                  <option :value="7">7 Tage</option>
                  <option :value="30">30 Tage</option>
                  <option :value="90">90 Tage</option>
                  <option :value="365">1 Jahr</option>
                </select>
              </div>

              <button
                @click="createApiKey"
                :disabled="isCreatingApiKey"
                class="btn-primary flex items-center gap-2"
              >
                <PlusIcon class="w-5 h-5" />
                <span v-if="isCreatingApiKey">Erstellen...</span>
                <span v-else>API-Key erstellen</span>
              </button>
            </div>
          </div>

          <!-- Existing Keys -->
          <div class="card p-6">
            <h2 class="text-lg font-semibold text-white mb-4">Vorhandene API-Keys</h2>

            <div v-if="isLoadingApiKeys" class="text-center py-8">
              <svg class="animate-spin h-8 w-8 text-primary-500 mx-auto" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
            </div>

            <div v-else-if="apiKeys.length === 0" class="text-center py-8 text-gray-400">
              <KeyIcon class="w-12 h-12 mx-auto mb-2 opacity-50" />
              <p>Noch keine API-Keys erstellt</p>
            </div>

            <div v-else class="space-y-4">
              <div
                v-for="key in apiKeys"
                :key="key.id"
                class="bg-dark-700/50 rounded-lg p-4"
                :class="{ 'opacity-50': !key.is_active }"
              >
                <div class="flex items-start justify-between gap-4">
                  <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                      <h3 class="font-medium text-white truncate">{{ key.name }}</h3>
                      <span
                        v-if="!key.is_active"
                        class="px-2 py-0.5 text-xs rounded-full bg-red-500/20 text-red-400"
                      >
                        Widerrufen
                      </span>
                    </div>
                    <p class="text-sm text-gray-500 font-mono mt-1">{{ key.key_prefix }}...</p>
                    <div class="flex flex-wrap gap-1 mt-2">
                      <span
                        v-for="scope in key.scopes"
                        :key="scope"
                        class="px-2 py-0.5 text-xs rounded-full bg-primary-600/20 text-primary-400"
                      >
                        {{ scope }}
                      </span>
                    </div>
                    <div class="text-xs text-gray-500 mt-2 space-x-4">
                      <span>Erstellt: {{ formatDate(key.created_at) }}</span>
                      <span v-if="key.last_used_at">Zuletzt: {{ formatDate(key.last_used_at) }}</span>
                      <span v-if="key.expires_at" class="text-yellow-500">Läuft ab: {{ formatDate(key.expires_at) }}</span>
                    </div>
                  </div>
                  <div class="flex gap-2 shrink-0">
                    <button
                      v-if="key.is_active"
                      @click="revokeApiKey(key.id)"
                      class="p-2 text-yellow-400 hover:bg-yellow-500/10 rounded-lg transition-colors"
                      title="Widerrufen"
                    >
                      <EyeSlashIcon class="w-5 h-5" />
                    </button>
                    <button
                      @click="deleteApiKey(key.id)"
                      class="p-2 text-red-400 hover:bg-red-500/10 rounded-lg transition-colors"
                      title="Löschen"
                    >
                      <TrashIcon class="w-5 h-5" />
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- API Documentation -->
          <div class="card p-6">
            <h2 class="text-lg font-semibold text-white mb-4">API-Verwendung</h2>
            <p class="text-gray-400 text-sm mb-4">
              Füge den API-Key im Header <code class="text-primary-400">X-API-Key</code> hinzu:
            </p>
            <div class="bg-dark-800 rounded-lg p-4">
              <code class="text-sm text-gray-300">
                curl -H "X-API-Key: ks_xxx..." /api/v1/lists
              </code>
            </div>
          </div>
        </div>

        <!-- AI Assistant Settings -->
        <div v-if="activeTab === 'ai'" class="space-y-6">
          <div class="card p-6">
            <div class="flex items-center gap-3 mb-6">
              <SparklesIcon class="w-6 h-6 text-purple-400" />
              <div>
                <h2 class="text-lg font-semibold text-white">AI Assistent Konfiguration</h2>
                <p class="text-sm text-gray-400">Verwende deinen eigenen API-Key um den AI-Assistenten zu nutzen</p>
              </div>
            </div>

            <!-- Status -->
            <div class="mb-6 p-4 rounded-lg" :class="aiStore.isConfigured ? 'bg-green-500/10 border border-green-500/30' : 'bg-yellow-500/10 border border-yellow-500/30'">
              <div class="flex items-center gap-3">
                <CheckCircleIcon v-if="aiStore.isConfigured" class="w-5 h-5 text-green-400" />
                <XCircleIcon v-else class="w-5 h-5 text-yellow-400" />
                <span :class="aiStore.isConfigured ? 'text-green-300' : 'text-yellow-300'">
                  {{ aiStore.isConfigured ? 'AI Assistent ist konfiguriert und bereit' : 'API-Key erforderlich um AI zu nutzen' }}
                </span>
              </div>
            </div>

            <div class="space-y-4">
              <!-- Provider -->
              <div>
                <label class="label">Provider</label>
                <select v-model="aiForm.provider" class="input">
                  <option v-for="provider in aiStore.providers" :key="provider.value" :value="provider.value">
                    {{ provider.label }}
                  </option>
                </select>
              </div>

              <!-- API Key -->
              <div>
                <label class="label">API-Key</label>
                <div class="flex items-center gap-2">
                  <div class="relative flex-1">
                    <input
                      v-model="aiForm.api_key"
                      :type="showAiApiKey ? 'text' : 'password'"
                      class="input pr-10"
                      :placeholder="aiStore.isConfigured ? '••••••••••••••••' : 'Dein API-Key...'"
                    />
                    <button
                      type="button"
                      @click="showAiApiKey = !showAiApiKey"
                      class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white"
                    >
                      <EyeSlashIcon v-if="showAiApiKey" class="w-5 h-5" />
                      <EyeIcon v-else class="w-5 h-5" />
                    </button>
                  </div>
                  <button
                    v-if="aiStore.isConfigured"
                    @click="removeAiApiKey"
                    class="btn-secondary text-red-400 hover:text-red-300"
                    title="API-Key entfernen"
                  >
                    <TrashIcon class="w-5 h-5" />
                  </button>
                </div>
                <p class="text-xs text-gray-500 mt-1">
                  Wird verschlüsselt gespeichert. Niemals mit anderen geteilt.
                </p>
              </div>

              <!-- Model -->
              <div>
                <label class="label">Model</label>
                <select v-model="aiForm.model" class="input">
                  <option v-for="model in aiStore.providers.find(p => p.value === aiForm.provider)?.models || []" :key="model.id" :value="model.id">
                    {{ model.name }}
                  </option>
                  <option v-if="aiForm.provider === 'custom'" value="">Benutzerdefiniert</option>
                </select>
                <input
                  v-if="aiForm.provider === 'custom' || aiForm.provider === 'ollama'"
                  v-model="aiForm.model"
                  type="text"
                  class="input mt-2"
                  placeholder="Model-Name (z.B. gpt-4, llama3.2)"
                />
                <p class="text-xs text-gray-500 mt-1">Model-ID: {{ aiForm.model }}</p>
              </div>

              <!-- API Base URL (for Ollama/Custom) -->
              <div v-if="aiForm.provider === 'ollama' || aiForm.provider === 'custom'">
                <label class="label">API Base URL</label>
                <input
                  v-model="aiForm.api_base_url"
                  type="text"
                  class="input"
                  :placeholder="aiForm.provider === 'ollama' ? 'http://localhost:11434' : 'https://api.example.com'"
                />
              </div>

              <!-- Advanced Settings -->
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="label">Max Tokens</label>
                  <input
                    v-model.number="aiForm.max_tokens"
                    type="number"
                    class="input"
                    min="100"
                    max="8000"
                  />
                </div>
                <div>
                  <label class="label">Temperature</label>
                  <input
                    v-model.number="aiForm.temperature"
                    type="number"
                    class="input"
                    min="0"
                    max="2"
                    step="0.1"
                  />
                </div>
              </div>

              <button
                @click="saveAiSettings"
                :disabled="isSavingAi"
                class="btn-primary flex items-center gap-2"
              >
                <span v-if="isSavingAi">Speichern...</span>
                <span v-else>Einstellungen speichern</span>
              </button>
            </div>
          </div>

          <!-- Usage Stats (if configured) -->
          <div v-if="aiStore.settings && aiStore.settings.total_requests > 0" class="card p-6">
            <h3 class="text-lg font-semibold text-white mb-4">Nutzungsstatistiken</h3>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
              <div class="bg-dark-700 rounded-lg p-4">
                <p class="text-2xl font-bold text-white">{{ aiStore.settings.total_requests || 0 }}</p>
                <p class="text-sm text-gray-400">Anfragen</p>
              </div>
              <div class="bg-dark-700 rounded-lg p-4">
                <p class="text-2xl font-bold text-white">{{ (aiStore.settings.total_tokens_used || 0).toLocaleString() }}</p>
                <p class="text-sm text-gray-400">Tokens verwendet</p>
              </div>
              <div class="bg-dark-700 rounded-lg p-4">
                <p class="text-sm font-bold text-white">{{ aiStore.settings.last_used_at ? formatDate(aiStore.settings.last_used_at) : '-' }}</p>
                <p class="text-sm text-gray-400">Letzte Nutzung</p>
              </div>
            </div>
          </div>

          <!-- Provider Links -->
          <div class="card p-6">
            <h3 class="text-lg font-semibold text-white mb-4">API-Key erhalten</h3>
            <div class="space-y-2 text-sm">
              <p class="text-gray-400">Erstelle einen API-Key bei deinem gewünschten Anbieter:</p>
              <ul class="space-y-1">
                <li><a href="https://platform.openai.com/api-keys" target="_blank" class="text-primary-400 hover:text-primary-300">OpenAI Platform</a></li>
                <li><a href="https://console.anthropic.com/settings/keys" target="_blank" class="text-primary-400 hover:text-primary-300">Anthropic Console</a></li>
                <li><a href="https://openrouter.ai/keys" target="_blank" class="text-primary-400 hover:text-primary-300">OpenRouter</a></li>
                <li><span class="text-gray-400">Ollama: Kein API-Key erforderlich (lokal)</span></li>
              </ul>
            </div>
          </div>
        </div>

        <!-- Tags -->
        <div v-if="activeTab === 'tags'" class="card p-6">
          <TagManager />
        </div>

        <!-- Export/Import -->
        <div v-if="activeTab === 'export-import'" class="space-y-6">
          <!-- Export Section -->
          <div class="card p-6">
            <div class="flex items-center gap-3 mb-6">
              <DocumentArrowDownIcon class="w-6 h-6 text-primary-400" />
              <h2 class="text-lg font-semibold text-white">Daten exportieren</h2>
            </div>

            <p class="text-gray-400 text-sm mb-4">
              Exportiere deine Daten als Backup oder um sie in eine andere Instanz zu übertragen.
            </p>

            <!-- Export Stats -->
            <div v-if="Object.keys(exportImportStore.stats).length > 0" class="mb-6">
              <h3 class="text-sm font-medium text-gray-400 mb-3">Verfügbare Daten:</h3>
              <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2">
                <div
                  v-for="type in allExportTypes"
                  :key="type.id"
                  class="flex items-center justify-between p-2 bg-dark-700/50 rounded-lg text-sm"
                >
                  <span class="text-gray-300">{{ type.icon }} {{ type.name }}</span>
                  <span class="text-primary-400 font-medium">{{ exportImportStore.stats[type.id] || 0 }}</span>
                </div>
              </div>
            </div>

            <!-- Type Selection -->
            <div class="mb-4">
              <div class="flex items-center justify-between mb-3">
                <label class="label mb-0">Zu exportierende Daten:</label>
                <div class="flex gap-2">
                  <button @click="selectAllExportTypes" class="text-xs text-primary-400 hover:text-primary-300">
                    Alle auswählen
                  </button>
                  <span class="text-gray-600">|</span>
                  <button @click="deselectAllExportTypes" class="text-xs text-gray-400 hover:text-gray-300">
                    Keine
                  </button>
                </div>
              </div>
              <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                <label
                  v-for="type in allExportTypes"
                  :key="type.id"
                  class="flex items-center gap-2 p-2 rounded-lg cursor-pointer transition-colors"
                  :class="exportTypes.includes(type.id) ? 'bg-primary-600/20 border border-primary-500' : 'bg-dark-700 hover:bg-dark-600'"
                >
                  <input
                    type="checkbox"
                    :checked="exportTypes.includes(type.id)"
                    @change="toggleExportType(type.id)"
                    class="rounded border-gray-600 bg-dark-700 text-primary-600 focus:ring-primary-600"
                  />
                  <span class="text-sm text-gray-300">{{ type.icon }} {{ type.name }}</span>
                </label>
              </div>
            </div>

            <!-- Format Selection -->
            <div class="mb-6">
              <label class="label">Format:</label>
              <div class="flex gap-4">
                <label class="flex items-center gap-2 cursor-pointer">
                  <input
                    type="radio"
                    v-model="exportFormat"
                    value="json"
                    class="text-primary-600 focus:ring-primary-600"
                  />
                  <span class="text-gray-300">JSON</span>
                  <span class="text-xs text-gray-500">(empfohlen)</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                  <input
                    type="radio"
                    v-model="exportFormat"
                    value="csv"
                    class="text-primary-600 focus:ring-primary-600"
                  />
                  <span class="text-gray-300">CSV (ZIP)</span>
                </label>
              </div>
            </div>

            <button
              @click="handleExport"
              :disabled="exportImportStore.isExporting || exportTypes.length === 0"
              class="btn-primary flex items-center gap-2"
            >
              <ArrowDownTrayIcon class="w-5 h-5" />
              <span v-if="exportImportStore.isExporting">Exportiere...</span>
              <span v-else>Export starten</span>
            </button>
          </div>

          <!-- Import Section -->
          <div class="card p-6">
            <div class="flex items-center gap-3 mb-6">
              <DocumentArrowUpIcon class="w-6 h-6 text-green-400" />
              <h2 class="text-lg font-semibold text-white">Daten importieren</h2>
            </div>

            <p class="text-gray-400 text-sm mb-4">
              Importiere Daten aus einem vorherigen Export.
            </p>

            <!-- File Upload -->
            <div class="mb-6">
              <label class="label">Datei auswählen:</label>
              <input
                type="file"
                accept=".json"
                @change="handleFileSelect"
                class="block w-full text-sm text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-primary-600 file:text-white hover:file:bg-primary-700 cursor-pointer"
              />
            </div>

            <!-- Validation Result -->
            <div v-if="exportImportStore.importValidation" class="mb-6">
              <div
                v-if="exportImportStore.importValidation.valid"
                class="bg-green-500/10 border border-green-500/30 rounded-lg p-4"
              >
                <h3 class="text-green-400 font-medium mb-2">Datei validiert</h3>
                <p class="text-sm text-gray-400 mb-3">
                  Gefundene Daten:
                </p>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                  <label
                    v-for="type in exportImportStore.importValidation.types"
                    :key="type"
                    class="flex items-center gap-2 p-2 rounded-lg cursor-pointer transition-colors"
                    :class="selectedImportTypes.includes(type) ? 'bg-green-600/20 border border-green-500' : 'bg-dark-700 hover:bg-dark-600'"
                  >
                    <input
                      type="checkbox"
                      :checked="selectedImportTypes.includes(type)"
                      @change="() => {
                        const idx = selectedImportTypes.indexOf(type)
                        if (idx >= 0) selectedImportTypes.splice(idx, 1)
                        else selectedImportTypes.push(type)
                      }"
                      class="rounded border-gray-600 bg-dark-700 text-green-600 focus:ring-green-600"
                    />
                    <span class="text-sm text-gray-300">
                      {{ allExportTypes.find(t => t.id === type)?.name || type }}
                      <span class="text-green-400">({{ exportImportStore.importValidation.counts[type] }})</span>
                    </span>
                  </label>
                </div>
              </div>
              <div
                v-else
                class="bg-red-500/10 border border-red-500/30 rounded-lg p-4"
              >
                <h3 class="text-red-400 font-medium mb-2">Validierung fehlgeschlagen</h3>
                <ul class="text-sm text-gray-400 list-disc list-inside">
                  <li v-for="error in exportImportStore.importValidation.errors" :key="error">
                    {{ error }}
                  </li>
                </ul>
              </div>
            </div>

            <!-- Conflict Resolution -->
            <div v-if="exportImportStore.importValidation?.valid" class="mb-6">
              <label class="label">Bei Konflikten:</label>
              <div class="flex flex-col gap-2">
                <label class="flex items-center gap-2 cursor-pointer">
                  <input
                    type="radio"
                    v-model="conflictResolution"
                    value="skip"
                    class="text-primary-600 focus:ring-primary-600"
                  />
                  <span class="text-gray-300">Überspringen</span>
                  <span class="text-xs text-gray-500">(bestehende Daten behalten)</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                  <input
                    type="radio"
                    v-model="conflictResolution"
                    value="replace"
                    class="text-primary-600 focus:ring-primary-600"
                  />
                  <span class="text-gray-300">Ersetzen</span>
                  <span class="text-xs text-gray-500">(bestehende Daten überschreiben)</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                  <input
                    type="radio"
                    v-model="conflictResolution"
                    value="rename"
                    class="text-primary-600 focus:ring-primary-600"
                  />
                  <span class="text-gray-300">Umbenennen</span>
                  <span class="text-xs text-gray-500">(importierte Daten umbenennen)</span>
                </label>
              </div>
            </div>

            <!-- Import Actions -->
            <div class="flex gap-3">
              <button
                v-if="importData"
                @click="resetImport"
                class="btn-secondary"
              >
                Zurücksetzen
              </button>
              <button
                @click="handleImport"
                :disabled="exportImportStore.isImporting || !exportImportStore.importValidation?.valid || selectedImportTypes.length === 0"
                class="btn-primary flex items-center gap-2"
              >
                <ArrowUpTrayIcon class="w-5 h-5" />
                <span v-if="exportImportStore.isImporting">Importiere...</span>
                <span v-else>Import starten</span>
              </button>
            </div>

            <!-- Import Result -->
            <div v-if="exportImportStore.importResult" class="mt-6">
              <div
                :class="exportImportStore.importResult.success ? 'bg-green-500/10 border-green-500/30' : 'bg-yellow-500/10 border-yellow-500/30'"
                class="border rounded-lg p-4"
              >
                <h3 :class="exportImportStore.importResult.success ? 'text-green-400' : 'text-yellow-400'" class="font-medium mb-3">
                  Import {{ exportImportStore.importResult.success ? 'abgeschlossen' : 'mit Warnungen' }}
                </h3>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
                  <div v-for="(count, type) in exportImportStore.importResult.imported" :key="type">
                    <span class="text-gray-400">{{ allExportTypes.find(t => t.id === type)?.name || type }}:</span>
                    <span class="text-green-400 ml-2">{{ count }} importiert</span>
                    <span v-if="exportImportStore.importResult.skipped[type]" class="text-yellow-400 ml-1">
                      ({{ exportImportStore.importResult.skipped[type] }} übersprungen)
                    </span>
                  </div>
                </div>
                <div v-if="Object.keys(exportImportStore.importResult.errors).length > 0" class="mt-4 pt-4 border-t border-dark-700">
                  <h4 class="text-red-400 font-medium mb-2">Fehler:</h4>
                  <ul class="text-sm text-gray-400 list-disc list-inside">
                    <li v-for="(errors, type) in exportImportStore.importResult.errors" :key="type">
                      {{ type }}: {{ Array.isArray(errors) ? errors.join(', ') : errors }}
                    </li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Appearance -->
        <div v-if="activeTab === 'appearance'" class="card p-6">
          <h2 class="text-lg font-semibold text-white mb-6">Darstellung</h2>

          <div class="space-y-4">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-white font-medium">Dark Mode</p>
                <p class="text-gray-400 text-sm">Dunkles Farbschema verwenden</p>
              </div>
              <button
                @click="uiStore.toggleDarkMode"
                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                :class="uiStore.isDarkMode ? 'bg-primary-600' : 'bg-dark-600'"
              >
                <span
                  class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                  :class="uiStore.isDarkMode ? 'translate-x-6' : 'translate-x-1'"
                />
              </button>
            </div>

            <div class="flex items-center justify-between">
              <div>
                <p class="text-white font-medium">Kompakte Sidebar</p>
                <p class="text-gray-400 text-sm">Sidebar standardmäßig einklappen</p>
              </div>
              <button
                @click="uiStore.toggleSidebarCollapse"
                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                :class="uiStore.sidebarCollapsed ? 'bg-primary-600' : 'bg-dark-600'"
              >
                <span
                  class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                  :class="uiStore.sidebarCollapsed ? 'translate-x-6' : 'translate-x-1'"
                />
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
