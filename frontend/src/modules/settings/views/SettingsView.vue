<script setup>
import { ref, reactive, watch, nextTick, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { useToast } from '@/composables/useToast'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
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
  CurrencyDollarIcon,
  PhotoIcon,
  PencilIcon,
  CheckIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline'
import { useExportImportStore } from '@/stores/exportImport'
import { useAIStore } from '@/stores/ai'
import TagManager from '@/components/TagManager.vue'
import pushNotifications from '@/core/services/pushNotifications'

const { t } = useI18n()
const authStore = useAuthStore()
const uiStore = useUiStore()
const exportImportStore = useExportImportStore()
const aiStore = useAIStore()
const toast = useToast()
const { confirm } = useConfirmDialog()

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

const allTabs = computed(() => [
  { id: 'profile', name: t('settings.profile'), icon: UserIcon },
  { id: 'security', name: t('settings.security'), icon: ShieldCheckIcon },
  { id: 'notifications', name: t('settings.notifications'), icon: BellIcon },
  { id: 'api-keys', name: t('settings.apiKeys'), icon: KeyIcon, permission: 'apikeys.view' },
  { id: 'ai', name: t('settingsModule.aiAssistant'), icon: SparklesIcon, permission: 'ai.view' },
  { id: 'tags', name: t('settingsModule.tags'), icon: TagIcon },
  { id: 'invoices', name: t('settingsModule.invoices'), icon: CurrencyDollarIcon, permission: 'invoices.view' },
  { id: 'export-import', name: t('settings.exportImport'), icon: ArrowDownTrayIcon, permission: 'settings.edit' },
  { id: 'appearance', name: t('settings.appearance'), icon: PaintBrushIcon },
])

const tabs = computed(() =>
  allTabs.value.filter(tab => !tab.permission || authStore.hasPermission(tab.permission))
)

// Export/Import state
const exportTypes = ref([])
const allExportTypes = computed(() => [
  { id: 'lists', name: t('settingsModule.exportLists'), icon: '📋' },
  { id: 'documents', name: t('settingsModule.exportDocuments'), icon: '📄' },
  { id: 'snippets', name: t('settingsModule.settingsexportsnippets'), icon: '💻' },
  { id: 'bookmarks', name: t('settingsModule.exportBookmarks'), icon: '🔖' },
  { id: 'connections', name: t('settingsModule.exportConnections'), icon: '🔌' },
  { id: 'passwords', name: t('settingsModule.exportPasswords'), icon: '🔐' },
  { id: 'checklists', name: t('settingsModule.exportChecklists'), icon: '✅' },
  { id: 'kanban', name: t('settingsModule.exportKanban'), icon: '📊' },
  { id: 'projects', name: t('settingsModule.exportProjects'), icon: '📁' },
  { id: 'invoices', name: t('settingsModule.invoices'), icon: '💰' },
  { id: 'calendar', name: t('settingsModule.exportCalendar'), icon: '📅' },
  { id: 'time_entries', name: t('settingsModule.exportTimeEntries'), icon: '⏱️' },
])
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
  exportTypes.value = allExportTypes.value.map(tp => tp.id)
}

function deselectAllExportTypes() {
  exportTypes.value = []
}

async function handleExport() {
  if (exportTypes.value.length === 0) {
    uiStore.showError(t('settingsModule.selectAtLeastOneType'))
    return
  }
  try {
    await exportImportStore.exportData(exportTypes.value, exportFormat.value)
    uiStore.showSuccess(t('settingsModule.exportSuccess'))
  } catch (error) {
    uiStore.showError(t('settingsModule.exportFailed'))
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
      uiStore.showError(t('settingsModule.invalidFile'))
      importData.value = null
    }
  }
  reader.readAsText(file)
}

async function handleImport() {
  if (!importData.value) {
    uiStore.showError(t('settingsModule.noFileSelected'))
    return
  }
  if (selectedImportTypes.value.length === 0) {
    uiStore.showError(t('settingsModule.selectAtLeastOneType'))
    return
  }

  try {
    const result = await exportImportStore.importData(importData.value, {
      conflictResolution: conflictResolution.value,
      types: selectedImportTypes.value,
    })
    if (result.success) {
      uiStore.showSuccess(t('settingsModule.importSuccess'))
    } else {
      uiStore.showError(t('settingsModule.importPartiallyFailed'))
    }
  } catch (error) {
    uiStore.showError(t('settingsModule.importFailed'))
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
    uiStore.showError(t('settingsModule.apiKeyLoadError'))
  } finally {
    isLoadingApiKeys.value = false
  }
}

async function createApiKey() {
  if (!newKeyForm.name) {
    uiStore.showError(t('settingsModule.nameRequired'))
    return
  }
  if (newKeyForm.scopes.length === 0) {
    uiStore.showError(t('settingsModule.scopeRequired'))
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
    uiStore.showSuccess(t('settingsModule.apiKeyCreated'))
  } catch (error) {
    const message = error.response?.data?.error || t('settingsModule.createError')
    uiStore.showError(message)
  } finally {
    isCreatingApiKey.value = false
  }
}

async function revokeApiKey(keyId) {
  if (!await confirm({ message: t('settingsModule.revokeApiKeyConfirm'), type: 'danger', confirmText: t('settingsModule.revoke') })) return

  try {
    await api.post(`/api/v1/settings/api-keys/${keyId}/revoke`)
    await loadApiKeys()
    uiStore.showSuccess(t('settingsModule.apiKeyRevoked'))
  } catch (error) {
    uiStore.showError(t('settingsModule.revokeError'))
  }
}

async function deleteApiKey(keyId) {
  if (!await confirm({ message: t('settingsModule.deleteApiKeyConfirm'), type: 'danger', confirmText: t('common.delete') })) return

  try {
    await api.delete(`/api/v1/settings/api-keys/${keyId}`)
    await loadApiKeys()
    uiStore.showSuccess(t('settingsModule.apiKeyDeleted'))
  } catch (error) {
    uiStore.showError(t('settingsModule.deleteError'))
  }
}

function copyToClipboard(text) {
  navigator.clipboard.writeText(text)
  uiStore.showSuccess(t('settingsModule.copiedToClipboard'))
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
// ─── Invoice Settings ────────────────────────────────────────────────────────

const invoiceSettings = reactive({
  invoice_sender_name: '',
  invoice_company: '',
  invoice_address: '',
  invoice_email: '',
  invoice_phone: '',
  invoice_steuernummer: '',
  invoice_vat_id: '',
  invoice_bank_details: '',
  invoice_logo_file_id: null,
  invoice_default_payment_terms: t('invoicesModule.zahlbarInnerhalbVon30TagenNachRechnungsdatum'),
  invoice_number_prefix: 'RE',
  kleinunternehmer_mode: false,
  default_hourly_rate: 50,
})
const invoiceLogoPreviewUrl = ref(null)
const invoiceLogoFile = ref(null)
const invoiceLogoUploading = ref(false)
const invoiceSettingsLoaded = ref(false)
const invoiceSettingsSaving = ref(false)

async function loadLogoPreview(fileId) {
  try {
    const response = await api.get(`/api/v1/storage/${fileId}/thumbnail`, { responseType: 'blob' })
    if (invoiceLogoPreviewUrl.value) URL.revokeObjectURL(invoiceLogoPreviewUrl.value)
    invoiceLogoPreviewUrl.value = URL.createObjectURL(response.data)
  } catch {
    invoiceLogoPreviewUrl.value = null
  }
}

async function loadInvoiceSettings() {
  try {
    const response = await api.get('/api/v1/settings/user')
    const s = response.data.data
    invoiceSettings.invoice_sender_name = s.invoice_sender_name ?? ''
    invoiceSettings.invoice_company = s.invoice_company ?? ''
    invoiceSettings.invoice_address = s.invoice_address ?? ''
    invoiceSettings.invoice_email = s.invoice_email ?? ''
    invoiceSettings.invoice_phone = s.invoice_phone ?? ''
    invoiceSettings.invoice_steuernummer = s.invoice_steuernummer ?? ''
    invoiceSettings.invoice_vat_id = s.invoice_vat_id ?? ''
    invoiceSettings.invoice_bank_details = s.invoice_bank_details ?? ''
    invoiceSettings.invoice_logo_file_id = s.invoice_logo_file_id ?? null
    invoiceSettings.invoice_default_payment_terms = s.invoice_default_payment_terms ?? t('invoicesModule.zahlbarInnerhalbVon30TagenNachRechnungsdatum')
    invoiceSettings.invoice_number_prefix = s.invoice_number_prefix ?? 'RE'
    invoiceSettings.kleinunternehmer_mode = s.kleinunternehmer_mode ?? false
    invoiceSettings.default_hourly_rate = parseFloat(s.default_hourly_rate ?? 50)
    invoiceSettingsLoaded.value = true
    if (invoiceSettings.invoice_logo_file_id) {
      await loadLogoPreview(invoiceSettings.invoice_logo_file_id)
    }
  } catch (e) {
    console.warn('Invoice settings load failed:', e)
    toast.warning(t('settingsModule.invoiceSettingsLoadError'))
  }
}

function onLogoFileChange(event) {
  const file = event.target.files?.[0]
  if (!file) return
  invoiceLogoFile.value = file
  if (invoiceLogoPreviewUrl.value) URL.revokeObjectURL(invoiceLogoPreviewUrl.value)
  invoiceLogoPreviewUrl.value = URL.createObjectURL(file)
}

function removeLogo() {
  invoiceSettings.invoice_logo_file_id = null
  invoiceLogoFile.value = null
  if (invoiceLogoPreviewUrl.value) URL.revokeObjectURL(invoiceLogoPreviewUrl.value)
  invoiceLogoPreviewUrl.value = null
}

async function saveInvoiceSettings() {
  invoiceSettingsSaving.value = true
  try {
    if (invoiceLogoFile.value) {
      invoiceLogoUploading.value = true
      try {
        const formData = new FormData()
        formData.append('file', invoiceLogoFile.value)
        const resp = await api.post('/api/v1/storage', formData, {
          headers: { 'Content-Type': 'multipart/form-data' },
        })
        invoiceSettings.invoice_logo_file_id = resp.data.data?.id ?? null
        invoiceLogoFile.value = null
      } finally {
        invoiceLogoUploading.value = false
      }
    }
    await api.put('/api/v1/settings/user', {
      invoice_sender_name: invoiceSettings.invoice_sender_name,
      invoice_company: invoiceSettings.invoice_company,
      invoice_address: invoiceSettings.invoice_address,
      invoice_email: invoiceSettings.invoice_email,
      invoice_phone: invoiceSettings.invoice_phone,
      invoice_steuernummer: invoiceSettings.invoice_steuernummer,
      invoice_vat_id: invoiceSettings.invoice_vat_id,
      invoice_bank_details: invoiceSettings.invoice_bank_details,
      invoice_logo_file_id: invoiceSettings.invoice_logo_file_id,
      invoice_default_payment_terms: invoiceSettings.invoice_default_payment_terms,
      invoice_number_prefix: invoiceSettings.invoice_number_prefix || 'RE',
      kleinunternehmer_mode: invoiceSettings.kleinunternehmer_mode,
      default_hourly_rate: parseFloat(invoiceSettings.default_hourly_rate) || 50,
    })
    toast.success(t('settingsModule.invoiceSettingsSaved'))
  } catch (e) {
    toast.error(t('settingsModule.saveFailed') + ': ' + (e?.response?.data?.message ?? e?.message ?? t('common.error')))
  } finally {
    invoiceSettingsSaving.value = false
  }
}

// ─── Service Catalog ─────────────────────────────────────────────────────────

const serviceCatalog = ref([])
const catalogLoading = ref(false)
const showCatalogForm = ref(false)
const editingCatalogItem = ref(null)
const catalogForm = reactive({
  name: '',
  description: '',
  unit: 'Stunde',
  unit_price: 0,
})
const catalogUnits = ['Stunde', t('invoicesModule.stueck'), 'Pauschal', 'Tag', 'Monat', 'km']

async function loadServiceCatalog() {
  catalogLoading.value = true
  try {
    const response = await api.get('/api/v1/service-catalog')
    serviceCatalog.value = response.data.data?.items ?? []
  } catch (e) {
    toast.warning(t('settingsModule.serviceCatalogLoadError'))
  } finally {
    catalogLoading.value = false
  }
}

function openAddCatalogItem() {
  editingCatalogItem.value = null
  catalogForm.name = ''
  catalogForm.description = ''
  catalogForm.unit = 'Stunde'
  catalogForm.unit_price = 0
  showCatalogForm.value = true
}

function openEditCatalogItem(item) {
  editingCatalogItem.value = item
  catalogForm.name = item.name
  catalogForm.description = item.description ?? ''
  catalogForm.unit = item.unit
  catalogForm.unit_price = parseFloat(item.unit_price)
  showCatalogForm.value = true
}

function cancelCatalogForm() {
  showCatalogForm.value = false
  editingCatalogItem.value = null
}

async function saveCatalogItem() {
  if (!catalogForm.name.trim()) {
    toast.warning(t('settingsModule.nameRequired'))
    return
  }
  const payload = {
    name: catalogForm.name.trim(),
    description: catalogForm.description.trim() || null,
    unit: catalogForm.unit,
    unit_price: parseFloat(catalogForm.unit_price) || 0,
  }
  try {
    if (editingCatalogItem.value) {
      await api.put(`/api/v1/service-catalog/${editingCatalogItem.value.id}`, payload)
      toast.success(t('settingsModule.serviceUpdated'))
    } else {
      await api.post('/api/v1/service-catalog', payload)
      toast.success(t('settingsModule.serviceAdded'))
    }
    showCatalogForm.value = false
    editingCatalogItem.value = null
    await loadServiceCatalog()
  } catch (e) {
    toast.error(t('settingsModule.saveFailed') + ': ' + (e?.response?.data?.error ?? e?.message ?? t('common.error')))
  }
}

async function deleteCatalogItem(item) {
  if (!await confirm({ message: t('settings.deleteServiceConfirm', { name: item.name }), type: 'danger', confirmText: t('common.delete') })) return
  try {
    await api.delete(`/api/v1/service-catalog/${item.id}`)
    toast.success(t('settingsModule.serviceDeleted'))
    await loadServiceCatalog()
  } catch (e) {
    toast.error(t('settingsModule.deleteFailed'))
  }
}

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
  if (newTab === 'notifications') {
    loadPushSettings()
  }
  if (newTab === 'invoices') {
    if (!invoiceSettingsLoaded.value) loadInvoiceSettings()
    if (serviceCatalog.value.length === 0) loadServiceCatalog()
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
  context_enabled: true,
  tools_enabled: true,
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
    aiForm.context_enabled = settings.context_enabled !== 0 && settings.context_enabled !== false
    aiForm.tools_enabled = settings.tools_enabled !== 0 && settings.tools_enabled !== false
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
      context_enabled: aiForm.context_enabled,
      tools_enabled: aiForm.tools_enabled,
    })
    aiForm.api_key = '' // Clear after save
    uiStore.showSuccess(t('settingsModule.aiSettingsSaved'))
  } catch (error) {
    uiStore.showError(error.message || t('settingsModule.saveError'))
  } finally {
    isSavingAi.value = false
  }
}

async function removeAiApiKey() {
  if (!await confirm({ message: t('settingsModule.removeApiKeyConfirm'), type: 'danger', confirmText: t('settingsModule.remove') })) return
  try {
    await aiStore.removeApiKey()
    uiStore.showSuccess(t('settingsModule.apiKeyRemoved'))
  } catch (error) {
    uiStore.showError(t('settingsModule.removeError'))
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

// Push Notifications State
const pushSupported = ref(pushNotifications.isSupported)
const pushPermission = ref(pushNotifications.getPermissionStatus())
const isSubscribed = ref(false)
const pushSubscriptions = ref([])
const isLoadingPush = ref(false)
const isSendingTest = ref(false)
const pushPreferences = reactive({
  push_enabled: true,
  email_enabled: true,
  quiet_hours_start: null,
  quiet_hours_end: null,
  notify_tasks: true,
  notify_calendar: true,
  notify_tickets: true,
  notify_uptime: true,
  notify_chat: true,
  notify_inbox: true,
  notify_recurring: true,
  notify_backups: true,
  notify_system: true,
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
    uiStore.showSuccess(t('settingsModule.profileSaved'))
  } catch (error) {
    const message = error.response?.data?.error || t('settingsModule.saveError')
    uiStore.showError(message)
  } finally {
    isSaving.value = false
  }
}

async function changePassword() {
  if (isChangingPassword.value) return

  if (security.newPassword !== security.confirmPassword) {
    uiStore.showError(t('authModule.passwordsDoNotMatch'))
    return
  }

  if (security.newPassword.length < 8) {
    uiStore.showError(t('settingsModule.passwordMinLength'))
    return
  }

  isChangingPassword.value = true

  try {
    await api.put('/api/v1/users/me/password', {
      current_password: security.currentPassword,
      new_password: security.newPassword,
      confirm_password: security.confirmPassword
    })
    uiStore.showSuccess(t('settingsModule.passwordChanged'))
    security.currentPassword = ''
    security.newPassword = ''
    security.confirmPassword = ''
  } catch (error) {
    const message = error.response?.data?.error || t('settingsModule.passwordChangeError')
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
    const message = error.response?.data?.error || t('settingsModule.twoFactorEnableError')
    uiStore.showError(message)
    isEnabling2FA.value = false
  }
}

async function verify2FA() {
  if (!verificationCode.value || verificationCode.value.length !== 6) {
    uiStore.showError(t('settingsModule.enterValid6DigitCode'))
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
    uiStore.showSuccess(t('settingsModule.twoFactorEnabled'))
  } catch (error) {
    const message = error.response?.data?.error || t('settingsModule.invalidCode')
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
    uiStore.showError(t('settingsModule.enterCurrent2FACode'))
    return
  }

  try {
    await api.delete('/api/v1/auth/2fa/disable', {
      data: { code: disableCode.value }
    })
    twoFactorEnabled.value = false
    disableCode.value = ''
    isDisabling2FA.value = false
    uiStore.showSuccess(t('settingsModule.twoFactorDisabled'))
  } catch (error) {
    const message = error.response?.data?.error || t('settingsModule.invalidCode')
    uiStore.showError(message)
  }
}

// Push Notification Functions
async function loadPushSettings() {
  isLoadingPush.value = true
  try {
    await pushNotifications.init()
    pushPermission.value = pushNotifications.getPermissionStatus()
    isSubscribed.value = await pushNotifications.isSubscribed()
    pushSubscriptions.value = await pushNotifications.getSubscriptions()

    const prefs = await pushNotifications.getPreferences()
    if (prefs) {
      Object.assign(pushPreferences, prefs)
    }
  } catch (error) {
    console.error('Failed to load push settings:', error)
  } finally {
    isLoadingPush.value = false
  }
}

async function enablePushNotifications() {
  try {
    await pushNotifications.subscribe()
    isSubscribed.value = true
    pushPermission.value = 'granted'
    pushSubscriptions.value = await pushNotifications.getSubscriptions()
    uiStore.showSuccess(t('settingsModule.pushEnabled'))
  } catch (error) {
    if (error.message?.includes('denied')) {
      uiStore.showError(t('settingsModule.notificationsBlocked'))
    } else {
      uiStore.showError(t('settingsModule.pushEnableError'))
    }
  }
}

async function disablePushNotifications() {
  try {
    await pushNotifications.unsubscribe()
    isSubscribed.value = false
    pushSubscriptions.value = await pushNotifications.getSubscriptions()
    uiStore.showSuccess(t('settingsModule.pushDisabled'))
  } catch (error) {
    uiStore.showError(t('settingsModule.pushDisableError'))
  }
}

async function savePushPreferences() {
  try {
    await pushNotifications.updatePreferences(pushPreferences)
    uiStore.showSuccess(t('settingsModule.settingsSaved'))
  } catch (error) {
    uiStore.showError(t('settingsModule.saveError'))
  }
}

async function sendTestNotification() {
  isSendingTest.value = true
  try {
    await pushNotifications.sendTest()
    uiStore.showSuccess(t('settingsModule.testNotificationSent'))
  } catch (error) {
    uiStore.showError(t('settingsModule.testNotificationError'))
  } finally {
    isSendingTest.value = false
  }
}

// ─── External Notification Channels ─────────────────────────────────────────

const ntfyConfig = reactive({ url: '', token: '', priority: 'default', enabled: false })
const gotifyConfig = reactive({ url: '', token: '', priority: 5, enabled: false })
const healthchecksConfig = reactive({ uuid: '', base_url: 'https://hc-ping.com', enabled: false })
const isSavingExternalChannels = ref(false)

async function loadExternalChannels() {
  try {
    const response = await api.get('/api/v1/notifications/channels')
    const channels = response.data.data || []
    for (const ch of channels) {
      const cfg = ch.config ? JSON.parse(ch.config) : {}
      if (ch.channel_type === 'ntfy') {
        Object.assign(ntfyConfig, { ...cfg, enabled: !!ch.is_enabled })
      } else if (ch.channel_type === 'gotify') {
        Object.assign(gotifyConfig, { ...cfg, enabled: !!ch.is_enabled })
      } else if (ch.channel_type === 'healthchecks') {
        Object.assign(healthchecksConfig, { ...cfg, enabled: !!ch.is_enabled })
      }
    }
  } catch { /* channels not yet configured */ }
}

async function saveExternalChannel(type, config, enabled) {
  await api.put(`/api/v1/notifications/channels/${type}`, {
    is_enabled: enabled,
    config,
  })
}

async function saveAllExternalChannels() {
  isSavingExternalChannels.value = true
  try {
    const { enabled: ntfyEnabled, ...ntfyCfg } = ntfyConfig
    const { enabled: gotifyEnabled, ...gotifyCfg } = gotifyConfig
    const { enabled: hcEnabled, ...hcCfg } = healthchecksConfig
    await Promise.all([
      saveExternalChannel('ntfy', ntfyCfg, ntfyEnabled),
      saveExternalChannel('gotify', gotifyCfg, gotifyEnabled),
      saveExternalChannel('healthchecks', hcCfg, hcEnabled),
    ])
    uiStore.showSuccess(t('settingsModule.externalChannelsSaved'))
  } catch (error) {
    uiStore.showError(t('settingsModule.saveError'))
  } finally {
    isSavingExternalChannels.value = false
  }
}

// Load external channels when notifications tab is opened
watch(activeTab, (tab) => {
  if (tab === 'notifications') loadExternalChannels()
}, { immediate: true })
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div>
      <h1 class="text-2xl font-bold text-white">{{ $t('settings.title') }}</h1>
      <p class="text-gray-400 mt-1">{{ $t('settingsModule.manageAccountPreferences') }}</p>
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
                : 'text-gray-400 hover:bg-white/[0.04] hover:text-white'
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
          <h2 class="text-lg font-semibold text-white mb-6">{{ $t('settings.profile') }}</h2>

          <div class="space-y-4">
            <div>
              <label class="label">{{ $t('auth.username') }}</label>
              <input
                v-model="profile.username"
                type="text"
                class="input"
              />
            </div>

            <div>
              <label class="label">{{ $t('auth.email') }}</label>
              <input
                v-model="profile.email"
                type="email"
                class="input bg-white/[0.03]"
                disabled
              />
              <p class="mt-1 text-xs text-gray-500">{{ $t('settingsModule.emailCannotBeChanged') }}</p>
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
                {{ $t('common.saving') }}
              </span>
              <span v-else>{{ $t('common.save') }}</span>
            </button>
          </div>
        </div>

        <!-- Security -->
        <div v-if="activeTab === 'security'" class="space-y-6">
          <div class="card p-6">
            <h2 class="text-lg font-semibold text-white mb-6">{{ $t('settingsModule.changePassword') }}</h2>

            <div class="space-y-4">
              <div>
                <label class="label">{{ $t('settingsModule.currentPassword') }}</label>
                <input
                  v-model="security.currentPassword"
                  type="password"
                  class="input"
                  autocomplete="current-password"
                />
              </div>

              <div>
                <label class="label">{{ $t('settingsModule.newPassword') }}</label>
                <input
                  v-model="security.newPassword"
                  type="password"
                  class="input"
                  autocomplete="new-password"
                />
              </div>

              <div>
                <label class="label">{{ $t('auth.confirmPassword') }}</label>
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
                  {{ $t('settingsModule.changing') }}
                </span>
                <span v-else>{{ $t('settingsModule.changePassword') }}</span>
              </button>
            </div>
          </div>

          <!-- 2FA Section -->
          <div class="card p-6">
            <div class="flex items-center justify-between mb-4">
              <div>
                <h2 class="text-lg font-semibold text-white">{{ $t('settingsModule.twoFactorAuth') }}</h2>
                <p class="text-gray-400 text-sm mt-1">
                  {{ $t('settingsModule.twoFactorDescription') }}
                </p>
              </div>
              <div v-if="twoFactorEnabled" class="flex items-center gap-2 text-green-400">
                <CheckCircleIcon class="w-5 h-5" />
                <span class="text-sm font-medium">{{ $t('common.active') }}</span>
              </div>
              <div v-else class="flex items-center gap-2 text-gray-500">
                <XCircleIcon class="w-5 h-5" />
                <span class="text-sm">{{ $t('common.inactive') }}</span>
              </div>
            </div>

            <!-- 2FA Setup Flow -->
            <div v-if="twoFactorSetup" class="space-y-4 border-t border-white/[0.06] pt-4">
              <p class="text-gray-300">{{ $t('settingsModule.scanQrCode') }}</p>

              <div class="bg-white p-4 rounded-lg inline-block">
                <img v-if="qrCodeDataUrl" :src="qrCodeDataUrl" alt="2FA QR Code" class="w-48 h-48" />
                <div v-else class="w-48 h-48 flex items-center justify-center">
                  <svg class="animate-spin h-8 w-8 text-gray-400" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                  </svg>
                </div>
              </div>

              <div class="bg-white/[0.03] rounded-lg p-4">
                <p class="text-sm text-gray-400 mb-2">{{ $t('settingsModule.manualKey') }}</p>
                <code class="text-primary-400 text-sm break-all">{{ twoFactorSetup.secret }}</code>
              </div>

              <div>
                <label class="label">{{ $t('settingsModule.verificationCode') }}</label>
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
                  {{ $t('settingsModule.verifyAndActivate') }}
                </button>
              </div>
            </div>

            <!-- 2FA Disable Flow -->
            <div v-else-if="isDisabling2FA" class="space-y-4 border-t border-white/[0.06] pt-4">
              <p class="text-gray-300">{{ $t('settingsModule.enter2FAToDisable') }}</p>

              <div>
                <label class="label">{{ $t('settingsModule.twoFactorCode') }}</label>
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
                  {{ $t('settingsModule.disable2FA') }}
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
                <span v-if="isEnabling2FA">{{ $t('common.loading') }}</span>
                <span v-else>{{ $t('settingsModule.enable2FA') }}</span>
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

        <!-- Notifications -->
        <div v-if="activeTab === 'notifications'" class="space-y-6">
          <!-- Push Notifications -->
          <div class="card p-6">
            <div class="flex items-center gap-3 mb-6">
              <BellIcon class="w-6 h-6 text-primary-400" />
              <div>
                <h2 class="text-lg font-semibold text-white">{{ $t('settingsModule.pushNotifications') }}</h2>
                <p class="text-sm text-gray-400">{{ $t('settingsModule.receiveBrowserNotifications') }}</p>
              </div>
            </div>

            <!-- Loading State -->
            <div v-if="isLoadingPush" class="flex justify-center py-8">
              <svg class="animate-spin h-8 w-8 text-primary-500" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
            </div>

            <div v-else>
              <!-- Not Supported Warning -->
              <div v-if="!pushSupported" class="bg-yellow-500/10 border border-yellow-500/30 rounded-lg p-4 mb-6">
                <p class="text-yellow-300">{{ $t('settingsModule.pushNotSupported') }}</p>
              </div>

              <!-- Permission Denied Warning -->
              <div v-else-if="pushPermission === 'denied'" class="bg-red-500/10 border border-red-500/30 rounded-lg p-4 mb-6">
                <p class="text-red-300">{{ $t('settingsModule.notificationsBlockedInBrowser') }}</p>
                <p class="text-sm text-gray-400 mt-2">{{ $t('settingsModule.enableInBrowserSettings') }}</p>
              </div>

              <!-- Status & Toggle -->
              <div v-else class="mb-6">
                <div class="flex items-center justify-between p-4 bg-white/[0.03] rounded-lg">
                  <div class="flex items-center gap-3">
                    <div v-if="isSubscribed" class="w-3 h-3 rounded-full bg-green-400 animate-pulse" />
                    <div v-else class="w-3 h-3 rounded-full bg-gray-500" />
                    <span class="text-white">
                      {{ isSubscribed ? $t('settingsModule.pushActive') : $t('settingsModule.pushInactive') }}
                    </span>
                  </div>
                  <button
                    v-if="isSubscribed"
                    @click="disablePushNotifications"
                    class="btn-secondary text-red-400"
                  >
                    Deaktivieren
                  </button>
                  <button
                    v-else
                    @click="enablePushNotifications"
                    class="btn-primary"
                  >
                    Aktivieren
                  </button>
                </div>

                <!-- Test Notification -->
                <button
                  v-if="isSubscribed"
                  @click="sendTestNotification"
                  :disabled="isSendingTest"
                  class="mt-4 btn-secondary flex items-center gap-2"
                >
                  <BellIcon class="w-4 h-4" />
                  <span v-if="isSendingTest">{{ $t('settingsModule.sending') }}</span>
                  <span v-else>{{ $t('settingsModule.sendTestNotification') }}</span>
                </button>
              </div>

              <!-- Active Subscriptions -->
              <div v-if="pushSubscriptions.length > 0" class="mb-6">
                <h3 class="text-sm font-medium text-gray-400 mb-3">{{ $t('settings.activeDevices', { count: pushSubscriptions.length }) }}</h3>
                <div class="space-y-2">
                  <div
                    v-for="sub in pushSubscriptions"
                    :key="sub.id"
                    class="flex items-center justify-between p-3 bg-white/[0.03] rounded-lg text-sm"
                  >
                    <div>
                      <p class="text-white">{{ sub.device_name || $t('settingsModule.unknownDevice') }}</p>
                      <p class="text-xs text-gray-500">{{ $t('settingsModule.lastActive') }}: {{ sub.last_used_at ? formatDate(sub.last_used_at) : $t('common.never') }}</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Notification Preferences -->
          <div class="card p-6">
            <h2 class="text-lg font-semibold text-white mb-6">{{ $t('settingsModule.notificationSettings') }}</h2>

            <!-- Global Settings -->
            <div class="space-y-4 mb-6">
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-white font-medium">{{ $t('settingsModule.pushNotifications') }}</p>
                  <p class="text-gray-400 text-sm">{{ $t('settingsModule.enableBrowserNotifications') }}</p>
                </div>
                <button
                  @click="pushPreferences.push_enabled = !pushPreferences.push_enabled"
                  class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                  :class="pushPreferences.push_enabled ? 'bg-primary-600' : 'bg-white/[0.08]'"
                >
                  <span
                    class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                    :class="pushPreferences.push_enabled ? 'translate-x-6' : 'translate-x-1'"
                  />
                </button>
              </div>

              <div class="flex items-center justify-between">
                <div>
                  <p class="text-white font-medium">{{ $t('settingsModule.emailNotifications') }}</p>
                  <p class="text-gray-400 text-sm">{{ $t('settingsModule.receiveEmailUpdates') }}</p>
                </div>
                <button
                  @click="pushPreferences.email_enabled = !pushPreferences.email_enabled"
                  class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                  :class="pushPreferences.email_enabled ? 'bg-primary-600' : 'bg-white/[0.08]'"
                >
                  <span
                    class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                    :class="pushPreferences.email_enabled ? 'translate-x-6' : 'translate-x-1'"
                  />
                </button>
              </div>
            </div>

            <!-- Quiet Hours -->
            <div class="border-t border-white/[0.06] pt-4 mb-6">
              <h3 class="text-sm font-medium text-gray-400 mb-3">{{ $t('settingsModule.quietHours') }}</h3>
              <p class="text-xs text-gray-500 mb-3">{{ $t('settingsModule.noNotificationsInPeriod') }}</p>
              <div class="flex items-center gap-4">
                <div>
                  <label class="label text-xs">{{ $t('common.from') }}</label>
                  <input
                    v-model="pushPreferences.quiet_hours_start"
                    type="time"
                    class="input w-32"
                  />
                </div>
                <div>
                  <label class="label text-xs">{{ $t('common.to') }}</label>
                  <input
                    v-model="pushPreferences.quiet_hours_end"
                    type="time"
                    class="input w-32"
                  />
                </div>
              </div>
            </div>

            <!-- Category Settings -->
            <div class="border-t border-white/[0.06] pt-4">
              <h3 class="text-sm font-medium text-gray-400 mb-3">{{ $t('settingsModule.notificationsByCategory') }}</h3>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <label class="flex items-center gap-3 p-3 bg-white/[0.03] rounded-lg cursor-pointer hover:bg-white/[0.04]">
                  <input
                    v-model="pushPreferences.notify_tasks"
                    type="checkbox"
                    class="w-4 h-4 rounded border-white/[0.08] bg-white/[0.04] text-primary-500"
                  />
                  <span class="text-white">{{ $t('settingsModule.tasksAndLists') }}</span>
                </label>
                <label class="flex items-center gap-3 p-3 bg-white/[0.03] rounded-lg cursor-pointer hover:bg-white/[0.04]">
                  <input
                    v-model="pushPreferences.notify_calendar"
                    type="checkbox"
                    class="w-4 h-4 rounded border-white/[0.08] bg-white/[0.04] text-primary-500"
                  />
                  <span class="text-white">{{ $t('settingsModule.calendarReminders') }}</span>
                </label>
                <label class="flex items-center gap-3 p-3 bg-white/[0.03] rounded-lg cursor-pointer hover:bg-white/[0.04]">
                  <input
                    v-model="pushPreferences.notify_tickets"
                    type="checkbox"
                    class="w-4 h-4 rounded border-white/[0.08] bg-white/[0.04] text-primary-500"
                  />
                  <span class="text-white">{{ $t('settingsModule.tickets') }}</span>
                </label>
                <label class="flex items-center gap-3 p-3 bg-white/[0.03] rounded-lg cursor-pointer hover:bg-white/[0.04]">
                  <input
                    v-model="pushPreferences.notify_uptime"
                    type="checkbox"
                    class="w-4 h-4 rounded border-white/[0.08] bg-white/[0.04] text-primary-500"
                  />
                  <span class="text-white">{{ $t('settingsModule.uptimeWarnings') }}</span>
                </label>
                <label class="flex items-center gap-3 p-3 bg-white/[0.03] rounded-lg cursor-pointer hover:bg-white/[0.04]">
                  <input
                    v-model="pushPreferences.notify_chat"
                    type="checkbox"
                    class="w-4 h-4 rounded border-white/[0.08] bg-white/[0.04] text-primary-500"
                  />
                  <span class="text-white">{{ $t('settingsModule.chatMessages') }}</span>
                </label>
                <label class="flex items-center gap-3 p-3 bg-white/[0.03] rounded-lg cursor-pointer hover:bg-white/[0.04]">
                  <input
                    v-model="pushPreferences.notify_inbox"
                    type="checkbox"
                    class="w-4 h-4 rounded border-white/[0.08] bg-white/[0.04] text-primary-500"
                  />
                  <span class="text-white">{{ $t('settingsModule.inbox') }}</span>
                </label>
                <label class="flex items-center gap-3 p-3 bg-white/[0.03] rounded-lg cursor-pointer hover:bg-white/[0.04]">
                  <input
                    v-model="pushPreferences.notify_recurring"
                    type="checkbox"
                    class="w-4 h-4 rounded border-white/[0.08] bg-white/[0.04] text-primary-500"
                  />
                  <span class="text-white">{{ $t('navigation.recurringTasks') }}</span>
                </label>
                <label class="flex items-center gap-3 p-3 bg-white/[0.03] rounded-lg cursor-pointer hover:bg-white/[0.04]">
                  <input
                    v-model="pushPreferences.notify_backups"
                    type="checkbox"
                    class="w-4 h-4 rounded border-white/[0.08] bg-white/[0.04] text-primary-500"
                  />
                  <span class="text-white">{{ $t('settingsModule.backupStatus') }}</span>
                </label>
                <label class="flex items-center gap-3 p-3 bg-white/[0.03] rounded-lg cursor-pointer hover:bg-white/[0.04]">
                  <input
                    v-model="pushPreferences.notify_system"
                    type="checkbox"
                    class="w-4 h-4 rounded border-white/[0.08] bg-white/[0.04] text-primary-500"
                  />
                  <span class="text-white">{{ $t('settingsModule.systemNotifications') }}</span>
                </label>
              </div>
            </div>

            <button @click="savePushPreferences" class="btn-primary mt-6">
              {{ $t('settingsModule.saveSettings') }}
            </button>
          </div>

          <!-- External Notification Channels -->
          <div class="card p-6">
            <h2 class="text-lg font-semibold text-white mb-6">{{ $t('settingsModule.externalNotificationChannels') }}</h2>

            <!-- ntfy.sh -->
            <div class="mb-6 pb-6 border-b border-white/[0.06]">
              <div class="flex items-center justify-between mb-3">
                <div>
                  <h3 class="font-medium text-white">ntfy.sh</h3>
                  <p class="text-sm text-gray-400">{{ $t('settingsModule.ntfyDescription') }}</p>
                </div>
                <button
                  @click="ntfyConfig.enabled = !ntfyConfig.enabled"
                  class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                  :class="ntfyConfig.enabled ? 'bg-primary-600' : 'bg-white/[0.08]'"
                >
                  <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                    :class="ntfyConfig.enabled ? 'translate-x-6' : 'translate-x-1'" />
                </button>
              </div>
              <div v-if="ntfyConfig.enabled" class="space-y-3">
                <div>
                  <label class="label">{{ $t('settingsModule.topicUrl') }}</label>
                  <input v-model="ntfyConfig.url" type="url" class="input" placeholder="https://ntfy.sh/my-topic" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                  <div>
                    <label class="label">{{ $t('settingsModule.settingsaccesstokenoptional') }}</label>
                    <input v-model="ntfyConfig.token" type="password" class="input" placeholder="tk_..." />
                  </div>
                  <div>
                    <label class="label">{{ $t('settingsModule.defaultPriority') }}</label>
                    <select v-model="ntfyConfig.priority" class="input">
                      <option value="min">Min</option>
                      <option value="low">{{ $t('settingsModule.priorityLow') }}</option>
                      <option value="default">{{ $t('settingsModule.defaultPriorityOption') }}</option>
                      <option value="high">{{ $t('settingsModule.priorityHigh') }}</option>
                      <option value="urgent">{{ $t('settingsModule.priorityUrgent') }}</option>
                    </select>
                  </div>
                </div>
              </div>
            </div>

            <!-- Gotify -->
            <div class="mb-6 pb-6 border-b border-white/[0.06]">
              <div class="flex items-center justify-between mb-3">
                <div>
                  <h3 class="font-medium text-white">Gotify</h3>
                  <p class="text-sm text-gray-400">{{ $t('settingsModule.gotifyDescription') }}</p>
                </div>
                <button
                  @click="gotifyConfig.enabled = !gotifyConfig.enabled"
                  class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                  :class="gotifyConfig.enabled ? 'bg-primary-600' : 'bg-white/[0.08]'"
                >
                  <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                    :class="gotifyConfig.enabled ? 'translate-x-6' : 'translate-x-1'" />
                </button>
              </div>
              <div v-if="gotifyConfig.enabled" class="space-y-3">
                <div>
                  <label class="label">{{ $t('settingsModule.serverUrl') }}</label>
                  <input v-model="gotifyConfig.url" type="url" class="input" placeholder="https://gotify.example.com" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                  <div>
                    <label class="label">{{ $t('settingsModule.appToken') }}</label>
                    <input v-model="gotifyConfig.token" type="password" class="input" placeholder="App-Token" />
                  </div>
                  <div>
                    <label class="label">{{ $t('settingsModule.defaultPriority1to10') }}</label>
                    <input v-model.number="gotifyConfig.priority" type="number" min="1" max="10" class="input" />
                  </div>
                </div>
              </div>
            </div>

            <!-- Healthchecks.io -->
            <div class="mb-6">
              <div class="flex items-center justify-between mb-3">
                <div>
                  <h3 class="font-medium text-white">Healthchecks.io</h3>
                  <p class="text-sm text-gray-400">{{ $t('settingsModule.healthchecksDescription') }}</p>
                </div>
                <button
                  @click="healthchecksConfig.enabled = !healthchecksConfig.enabled"
                  class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                  :class="healthchecksConfig.enabled ? 'bg-primary-600' : 'bg-white/[0.08]'"
                >
                  <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                    :class="healthchecksConfig.enabled ? 'translate-x-6' : 'translate-x-1'" />
                </button>
              </div>
              <div v-if="healthchecksConfig.enabled" class="space-y-3">
                <div>
                  <label class="label">{{ $t('settingsModule.checkUuid') }}</label>
                  <input v-model="healthchecksConfig.uuid" type="text" class="input font-mono"
                    placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" />
                </div>
                <div>
                  <label class="label">{{ $t('settingsModule.baseUrlSelfHosted') }}</label>
                  <input v-model="healthchecksConfig.base_url" type="url" class="input"
                    placeholder="https://hc-ping.com" />
                </div>
              </div>
            </div>

            <button
              @click="saveAllExternalChannels"
              :disabled="isSavingExternalChannels"
              class="btn-primary"
            >
              <span v-if="isSavingExternalChannels">{{ $t('common.saving') }}</span>
              <span v-else>{{ $t('settingsModule.saveExternalChannels') }}</span>
            </button>
          </div>
        </div>

        <!-- API Keys -->
        <div v-if="activeTab === 'api-keys'" class="space-y-6">
          <!-- New API Key Result Modal -->
          <div v-if="showNewApiKey && newApiKeyResult" class="card p-6 border-2 border-green-500/50 bg-green-500/10">
            <div class="flex items-start justify-between mb-4">
              <div>
                <h3 class="text-lg font-semibold text-green-400">{{ $t('settingsModule.newApiKeyCreated') }}</h3>
                <p class="text-sm text-gray-400 mt-1">{{ $t('settingsModule.copyKeyNow') }}</p>
              </div>
              <button @click="showNewApiKey = false; newApiKeyResult = null" class="text-gray-400 hover:text-white">
                <XCircleIcon class="w-6 h-6" />
              </button>
            </div>
            <div class="bg-white/[0.04] rounded-lg p-4">
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
            <h2 class="text-lg font-semibold text-white mb-4">{{ $t('settingsModule.createNewApiKey') }}</h2>

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
                <label class="label">{{ $t('settingsModule.permissions') }}</label>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-2 mt-2">
                  <label
                    v-for="(label, scope) in availableScopes"
                    :key="scope"
                    class="flex items-center gap-2 p-2 rounded-lg cursor-pointer transition-colors"
                    :class="newKeyForm.scopes.includes(scope) ? 'bg-primary-600/20 border border-primary-500' : 'bg-white/[0.04] hover:bg-white/[0.04]'"
                  >
                    <input
                      type="checkbox"
                      :checked="newKeyForm.scopes.includes(scope)"
                      @change="toggleScope(scope)"
                      class="rounded border-gray-600 bg-white/[0.04] text-primary-600 focus:ring-primary-600"
                    />
                    <span class="text-sm text-gray-300">{{ label }}</span>
                  </label>
                </div>
              </div>

              <div>
                <label class="label">{{ $t('settingsModule.settingsvalidityoptional') }}</label>
                <select v-model="newKeyForm.expires_in_days" class="input">
                  <option :value="0">{{ $t('settingsModule.unlimited') }}</option>
                  <option :value="7">{{ $t('settingsModule.sevenDays') }}</option>
                  <option :value="30">{{ $t('settingsModule.thirtyDays') }}</option>
                  <option :value="90">{{ $t('statusPage.90Tage') }}</option>
                  <option :value="365">{{ $t('settingsModule.oneYear') }}</option>
                </select>
              </div>

              <button
                @click="createApiKey"
                :disabled="isCreatingApiKey"
                class="btn-primary flex items-center gap-2"
              >
                <PlusIcon class="w-5 h-5" />
                <span v-if="isCreatingApiKey">{{ $t('settingsModule.creating') }}</span>
                <span v-else>{{ $t('settingsModule.createApiKey') }}</span>
              </button>
            </div>
          </div>

          <!-- Existing Keys -->
          <div class="card p-6">
            <h2 class="text-lg font-semibold text-white mb-4">{{ $t('settingsModule.existingApiKeys') }}</h2>

            <div v-if="isLoadingApiKeys" class="text-center py-8">
              <svg class="animate-spin h-8 w-8 text-primary-500 mx-auto" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
            </div>

            <div v-else-if="apiKeys.length === 0" class="text-center py-8 text-gray-400">
              <KeyIcon class="w-12 h-12 mx-auto mb-2 opacity-50" />
              <p>{{ $t('settingsModule.noApiKeysYet') }}</p>
            </div>

            <div v-else class="space-y-4">
              <div
                v-for="key in apiKeys"
                :key="key.id"
                class="bg-white/[0.03] rounded-lg p-4"
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
                      <span>{{ $t('settingsModule.created') }}: {{ formatDate(key.created_at) }}</span>
                      <span v-if="key.last_used_at">{{ $t('settingsModule.lastUsed') }}: {{ formatDate(key.last_used_at) }}</span>
                      <span v-if="key.expires_at" class="text-yellow-500">{{ $t('settingsModule.expiresAt') }}: {{ formatDate(key.expires_at) }}</span>
                    </div>
                  </div>
                  <div class="flex gap-2 shrink-0">
                    <button
                      v-if="key.is_active"
                      @click="revokeApiKey(key.id)"
                      class="p-2 text-yellow-400 hover:bg-yellow-500/10 rounded-lg transition-colors"
                      :title="$t('settingsModule.revoke')"
                    >
                      <EyeSlashIcon class="w-5 h-5" />
                    </button>
                    <button
                      @click="deleteApiKey(key.id)"
                      class="p-2 text-red-400 hover:bg-red-500/10 rounded-lg transition-colors"
                      :title="$t('common.delete')"
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
            <h2 class="text-lg font-semibold text-white mb-4">{{ $t('settingsModule.apiUsage') }}</h2>
            <p class="text-gray-400 text-sm mb-4">
              {{ $t('settingsModule.addApiKeyHeader') }} <code class="text-primary-400">X-API-Key</code>
            </p>
            <div class="bg-white/[0.04] rounded-lg p-4">
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
                <h2 class="text-lg font-semibold text-white">{{ $t('settingsModule.aiAssistantConfig') }}</h2>
                <p class="text-sm text-gray-400">{{ $t('settingsModule.aiAssistantDescription') }}</p>
              </div>
            </div>

            <!-- Status -->
            <div class="mb-6 p-4 rounded-lg" :class="aiStore.isConfigured ? 'bg-green-500/10 border border-green-500/30' : 'bg-yellow-500/10 border border-yellow-500/30'">
              <div class="flex items-center gap-3">
                <CheckCircleIcon v-if="aiStore.isConfigured" class="w-5 h-5 text-green-400" />
                <XCircleIcon v-else class="w-5 h-5 text-yellow-400" />
                <span :class="aiStore.isConfigured ? 'text-green-300' : 'text-yellow-300'">
                  {{ aiStore.isConfigured ? $t('settingsModule.aiConfigured') : $t('settingsModule.aiKeyRequired') }}
                </span>
              </div>
            </div>

            <div class="space-y-4">
              <!-- Provider -->
              <div>
                <label class="label">{{ $t('settingsModule.provider') }}</label>
                <select v-model="aiForm.provider" class="input">
                  <option v-for="provider in aiStore.providers" :key="provider.value" :value="provider.value">
                    {{ provider.label }}
                  </option>
                </select>
              </div>

              <!-- API Key -->
              <div>
                <label class="label">{{ $t('settings.apiKeys') }}</label>
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
                    :title="$t('settingsModule.removeApiKey')"
                  >
                    <TrashIcon class="w-5 h-5" />
                  </button>
                </div>
                <p class="text-xs text-gray-500 mt-1">
                  {{ $t('settingsModule.apiKeyEncryptedNote') }}
                </p>
              </div>

              <!-- Model -->
              <div>
                <label class="label">{{ $t('settingsModule.model') }}</label>
                <select v-model="aiForm.model" class="input">
                  <option v-for="model in aiStore.providers.find(p => p.value === aiForm.provider)?.models || []" :key="model.id" :value="model.id">
                    {{ model.name }}
                  </option>
                  <option v-if="aiForm.provider === 'custom'" value="">{{ $t('common.custom') }}</option>
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
                <label class="label">{{ $t('settingsModule.apiBaseUrl') }}</label>
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
                  <label class="label">{{ $t('settingsModule.maxTokens') }}</label>
                  <input
                    v-model.number="aiForm.max_tokens"
                    type="number"
                    class="input"
                    min="100"
                    max="8000"
                  />
                </div>
                <div>
                  <label class="label">{{ $t('settingsModule.temperature') }}</label>
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

              <!-- Context & Tools Settings -->
              <div class="border-t border-white/[0.06] pt-4 mt-4">
                <h3 class="text-sm font-semibold text-gray-300 mb-3">{{ $t('settingsModule.contextAndTools') }}</h3>
                <div class="space-y-3">
                  <label class="flex items-center gap-3 cursor-pointer">
                    <input
                      v-model="aiForm.context_enabled"
                      type="checkbox"
                      class="w-4 h-4 rounded border-white/[0.08] bg-white/[0.04] text-primary-500 focus:ring-primary-500"
                    />
                    <div>
                      <span class="text-white">{{ $t('settingsModule.includeUserContext') }}</span>
                      <p class="text-xs text-gray-500">{{ $t('settingsModule.aiContextDescription') }}</p>
                    </div>
                  </label>
                  <label class="flex items-center gap-3 cursor-pointer">
                    <input
                      v-model="aiForm.tools_enabled"
                      type="checkbox"
                      class="w-4 h-4 rounded border-white/[0.08] bg-white/[0.04] text-primary-500 focus:ring-primary-500"
                    />
                    <div>
                      <span class="text-white">{{ $t('settingsModule.enableSystemTools') }}</span>
                      <p class="text-xs text-gray-500">{{ $t('settingsModule.systemToolsDescription') }}</p>
                    </div>
                  </label>
                </div>
              </div>

              <button
                @click="saveAiSettings"
                :disabled="isSavingAi"
                class="btn-primary flex items-center gap-2"
              >
                <span v-if="isSavingAi">{{ $t('common.saving') }}</span>
                <span v-else>{{ $t('settingsModule.saveSettings') }}</span>
              </button>
            </div>
          </div>

          <!-- Usage Stats (if configured) -->
          <div v-if="aiStore.settings && aiStore.settings.total_requests > 0" class="card p-6">
            <h3 class="text-lg font-semibold text-white mb-4">{{ $t('settingsModule.usageStats') }}</h3>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
              <div class="bg-white/[0.04] rounded-lg p-4">
                <p class="text-2xl font-bold text-white">{{ aiStore.settings.total_requests || 0 }}</p>
                <p class="text-sm text-gray-400">{{ $t('settingsModule.requests') }}</p>
              </div>
              <div class="bg-white/[0.04] rounded-lg p-4">
                <p class="text-2xl font-bold text-white">{{ (aiStore.settings.total_tokens_used || 0).toLocaleString() }}</p>
                <p class="text-sm text-gray-400">{{ $t('settingsModule.tokensUsed') }}</p>
              </div>
              <div class="bg-white/[0.04] rounded-lg p-4">
                <p class="text-sm font-bold text-white">{{ aiStore.settings.last_used_at ? formatDate(aiStore.settings.last_used_at) : '-' }}</p>
                <p class="text-sm text-gray-400">{{ $t('settingsModule.lastUsed') }}</p>
              </div>
            </div>
          </div>

          <!-- Provider Links -->
          <div class="card p-6">
            <h3 class="text-lg font-semibold text-white mb-4">{{ $t('settingsModule.getApiKey') }}</h3>
            <div class="space-y-2 text-sm">
              <p class="text-gray-400">{{ $t('settingsModule.getApiKeyDescription') }}</p>
              <ul class="space-y-1">
                <li><a href="https://platform.openai.com/api-keys" target="_blank" class="text-primary-400 hover:text-primary-300">OpenAI Platform</a></li>
                <li><a href="https://console.anthropic.com/settings/keys" target="_blank" class="text-primary-400 hover:text-primary-300">Anthropic Console</a></li>
                <li><a href="https://openrouter.ai/keys" target="_blank" class="text-primary-400 hover:text-primary-300">OpenRouter</a></li>
                <li><span class="text-gray-400">{{ $t('settingsModule.ollamaNoKeyNeeded') }}</span></li>
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
              <h2 class="text-lg font-semibold text-white">{{ $t('settingsModule.exportData') }}</h2>
            </div>

            <p class="text-gray-400 text-sm mb-4">
              {{ $t('settingsModule.exportDescription') }}
            </p>

            <!-- Export Stats -->
            <div v-if="Object.keys(exportImportStore.stats).length > 0" class="mb-6">
              <h3 class="text-sm font-medium text-gray-400 mb-3">{{ $t('settingsModule.availableData') }}</h3>
              <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2">
                <div
                  v-for="type in allExportTypes"
                  :key="type.id"
                  class="flex items-center justify-between p-2 bg-white/[0.03] rounded-lg text-sm"
                >
                  <span class="text-gray-300">{{ type.icon }} {{ type.name }}</span>
                  <span class="text-primary-400 font-medium">{{ exportImportStore.stats[type.id] || 0 }}</span>
                </div>
              </div>
            </div>

            <!-- Type Selection -->
            <div class="mb-4">
              <div class="flex items-center justify-between mb-3">
                <label class="label mb-0">{{ $t('settingsModule.dataToExport') }}</label>
                <div class="flex gap-2">
                  <button @click="selectAllExportTypes" class="text-xs text-primary-400 hover:text-primary-300">
                    {{ $t('settingsModule.selectAll') }}
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
                  :class="exportTypes.includes(type.id) ? 'bg-primary-600/20 border border-primary-500' : 'bg-white/[0.04] hover:bg-white/[0.04]'"
                >
                  <input
                    type="checkbox"
                    :checked="exportTypes.includes(type.id)"
                    @change="toggleExportType(type.id)"
                    class="rounded border-gray-600 bg-white/[0.04] text-primary-600 focus:ring-primary-600"
                  />
                  <span class="text-sm text-gray-300">{{ type.icon }} {{ type.name }}</span>
                </label>
              </div>
            </div>

            <!-- Format Selection -->
            <div class="mb-6">
              <label class="label">{{ $t('settingsModule.format') }}:</label>
              <div class="flex gap-4">
                <label class="flex items-center gap-2 cursor-pointer">
                  <input
                    type="radio"
                    v-model="exportFormat"
                    value="json"
                    class="text-primary-600 focus:ring-primary-600"
                  />
                  <span class="text-gray-300">JSON</span>
                  <span class="text-xs text-gray-500">({{ $t('settingsModule.recommended') }})</span>
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
              <span v-if="exportImportStore.isExporting">{{ $t('settingsModule.exporting') }}</span>
              <span v-else>{{ $t('settingsModule.startExport') }}</span>
            </button>
          </div>

          <!-- Import Section -->
          <div class="card p-6">
            <div class="flex items-center gap-3 mb-6">
              <DocumentArrowUpIcon class="w-6 h-6 text-green-400" />
              <h2 class="text-lg font-semibold text-white">{{ $t('settingsModule.importData') }}</h2>
            </div>

            <p class="text-gray-400 text-sm mb-4">
              {{ $t('settingsModule.importDescription') }}
            </p>

            <!-- File Upload -->
            <div class="mb-6">
              <label class="label">{{ $t('settingsModule.selectFile') }}</label>
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
                <h3 class="text-green-400 font-medium mb-2">{{ $t('settingsModule.fileValidated') }}</h3>
                <p class="text-sm text-gray-400 mb-3">
                  {{ $t('settingsModule.foundData') }}
                </p>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                  <label
                    v-for="type in exportImportStore.importValidation.types"
                    :key="type"
                    class="flex items-center gap-2 p-2 rounded-lg cursor-pointer transition-colors"
                    :class="selectedImportTypes.includes(type) ? 'bg-green-600/20 border border-green-500' : 'bg-white/[0.04] hover:bg-white/[0.04]'"
                  >
                    <input
                      type="checkbox"
                      :checked="selectedImportTypes.includes(type)"
                      @change="() => {
                        const idx = selectedImportTypes.indexOf(type)
                        if (idx >= 0) selectedImportTypes.splice(idx, 1)
                        else selectedImportTypes.push(type)
                      }"
                      class="rounded border-gray-600 bg-white/[0.04] text-green-600 focus:ring-green-600"
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
                <h3 class="text-red-400 font-medium mb-2">{{ $t('settingsModule.validationFailed') }}</h3>
                <ul class="text-sm text-gray-400 list-disc list-inside">
                  <li v-for="error in exportImportStore.importValidation.errors" :key="error">
                    {{ error }}
                  </li>
                </ul>
              </div>
            </div>

            <!-- Conflict Resolution -->
            <div v-if="exportImportStore.importValidation?.valid" class="mb-6">
              <label class="label">{{ $t('settingsModule.onConflicts') }}:</label>
              <div class="flex flex-col gap-2">
                <label class="flex items-center gap-2 cursor-pointer">
                  <input
                    type="radio"
                    v-model="conflictResolution"
                    value="skip"
                    class="text-primary-600 focus:ring-primary-600"
                  />
                  <span class="text-gray-300">{{ $t('settingsModule.skip') }}</span>
                  <span class="text-xs text-gray-500">({{ $t('settingsModule.keepExisting') }})</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                  <input
                    type="radio"
                    v-model="conflictResolution"
                    value="replace"
                    class="text-primary-600 focus:ring-primary-600"
                  />
                  <span class="text-gray-300">{{ $t('settingsModule.replace') }}</span>
                  <span class="text-xs text-gray-500">{{ $t('settingsModule.overwriteExisting') }}</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                  <input
                    type="radio"
                    v-model="conflictResolution"
                    value="rename"
                    class="text-primary-600 focus:ring-primary-600"
                  />
                  <span class="text-gray-300">{{ $t('settingsModule.rename') }}</span>
                  <span class="text-xs text-gray-500">({{ $t('settingsModule.renameImported') }})</span>
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
                {{ $t('common.reset') }}
              </button>
              <button
                @click="handleImport"
                :disabled="exportImportStore.isImporting || !exportImportStore.importValidation?.valid || selectedImportTypes.length === 0"
                class="btn-primary flex items-center gap-2"
              >
                <ArrowUpTrayIcon class="w-5 h-5" />
                <span v-if="exportImportStore.isImporting">{{ $t('settingsModule.importing') }}</span>
                <span v-else>{{ $t('settingsModule.startImport') }}</span>
              </button>
            </div>

            <!-- Import Result -->
            <div v-if="exportImportStore.importResult" class="mt-6">
              <div
                :class="exportImportStore.importResult.success ? 'bg-green-500/10 border-green-500/30' : 'bg-yellow-500/10 border-yellow-500/30'"
                class="border rounded-lg p-4"
              >
                <h3 :class="exportImportStore.importResult.success ? 'text-green-400' : 'text-yellow-400'" class="font-medium mb-3">
                  {{ exportImportStore.importResult.success ? $t('settingsModule.importComplete') : $t('settingsModule.importWithWarnings') }}
                </h3>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
                  <div v-for="(count, type) in exportImportStore.importResult.imported" :key="type">
                    <span class="text-gray-400">{{ allExportTypes.find(t => t.id === type)?.name || type }}:</span>
                    <span class="text-green-400 ml-2">{{ count }} {{ $t('settingsModule.imported') }}</span>
                    <span v-if="exportImportStore.importResult.skipped[type]" class="text-yellow-400 ml-1">
                      ({{ exportImportStore.importResult.skipped[type] }} {{ $t('settingsModule.skipped') }})
                    </span>
                  </div>
                </div>
                <div v-if="Object.keys(exportImportStore.importResult.errors).length > 0" class="mt-4 pt-4 border-t border-white/[0.06]">
                  <h4 class="text-red-400 font-medium mb-2">{{ $t('common.error') }}</h4>
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

        <!-- Invoice Settings -->
        <div v-if="activeTab === 'invoices'" class="space-y-6">

          <!-- Kleinunternehmer Mode -->
          <div class="card p-6">
            <h2 class="text-lg font-semibold text-white mb-1">{{ $t('settingsModule.taxStatus') }}</h2>
            <p class="text-sm text-gray-400 mb-4">{{ $t('settingsModule.taxStatusDescription') }}</p>
            <div class="flex items-center justify-between">
              <div>
                <p class="text-white font-medium">{{ $t('settingsModule.smallBusiness') }}</p>
                <p class="text-gray-400 text-sm">{{ $t('settingsModule.smallBusinessVatNote') }}</p>
              </div>
              <button
                @click="invoiceSettings.kleinunternehmer_mode = !invoiceSettings.kleinunternehmer_mode"
                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                :class="invoiceSettings.kleinunternehmer_mode ? 'bg-primary-600' : 'bg-white/[0.08]'"
              >
                <span
                  class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                  :class="invoiceSettings.kleinunternehmer_mode ? 'translate-x-6' : 'translate-x-1'"
                />
              </button>
            </div>
            <div v-if="invoiceSettings.kleinunternehmer_mode" class="mt-3 bg-amber-500/10 border border-amber-500/30 rounded-lg px-3 py-2 text-sm text-amber-300">
              {{ $t('settingsModule.smallBusinessLimits') }}
            </div>
          </div>

          <!-- Absenderdaten -->
          <div class="card p-6 space-y-4">
            <h2 class="text-lg font-semibold text-white">{{ $t('settingsModule.senderData') }}</h2>
            <p class="text-sm text-gray-400">{{ $t('settingsModule.senderDataDescription') }}</p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="label">{{ $t('settingsModule.name') }} *</label>
                <input v-model="invoiceSettings.invoice_sender_name" type="text" class="input" placeholder="Max Mustermann" />
              </div>
              <div>
                <label class="label">{{ $t('settingsModule.company') }}</label>
                <input v-model="invoiceSettings.invoice_company" type="text" class="input" placeholder="Mustermann GbR" />
              </div>
              <div>
                <label class="label">{{ $t('auth.email') }}</label>
                <input v-model="invoiceSettings.invoice_email" type="email" class="input" placeholder="rechnungen@beispiel.de" />
              </div>
              <div>
                <label class="label">{{ $t('settingsModule.phone') }}</label>
                <input v-model="invoiceSettings.invoice_phone" type="text" class="input" placeholder="+49 123 456789" />
              </div>
            </div>

            <div>
              <label class="label">{{ $t('settingsModule.address') }}</label>
              <textarea v-model="invoiceSettings.invoice_address" class="input" rows="3" :placeholder="$t('settingsModule.addressPlaceholder')"></textarea>
            </div>
          </div>

          <!-- Steuerliche Angaben -->
          <div class="card p-6 space-y-4">
            <h2 class="text-lg font-semibold text-white">{{ $t('settingsModule.taxDetails') }}</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="label">{{ $t('settingsModule.taxNumber') }}</label>
                <input v-model="invoiceSettings.invoice_steuernummer" type="text" class="input" placeholder="12/345/67890" />
                <p class="text-xs text-gray-500 mt-1">{{ $t('settingsModule.taxNumberRequired') }}</p>
              </div>
              <div>
                <label class="label">{{ $t('settingsModule.vatId') }}</label>
                <input v-model="invoiceSettings.invoice_vat_id" type="text" class="input" placeholder="DE123456789" />
                <p class="text-xs text-gray-500 mt-1">{{ $t('settingsModule.vatIdNote') }}</p>
              </div>
            </div>
          </div>

          <!-- Bankverbindung -->
          <div class="card p-6 space-y-4">
            <h2 class="text-lg font-semibold text-white">{{ $t('settingsModule.bankDetails') }}</h2>
            <p class="text-sm text-gray-400">{{ $t('settingsModule.bankDetailsDescription') }}</p>
            <div>
              <label class="label">{{ $t('settingsModule.bankDataLabel') }}</label>
              <textarea v-model="invoiceSettings.invoice_bank_details" class="input" rows="3" placeholder="Kontoinhaber: Max Mustermann&#10;IBAN: DE89 3704 0044 0532 0130 00&#10;BIC: COBADEFFXXX&#10;Sparkasse Musterstadt"></textarea>
            </div>
          </div>

          <!-- Rechnungsnummer-Präfix -->
          <div class="card p-6 space-y-4">
            <h2 class="text-lg font-semibold text-white">{{ $t('settingsModule.invoiceNumber') }}</h2>
            <p class="text-sm text-gray-400">{{ $t('settingsModule.invoiceNumberPrefixDescription') }}</p>
            <div>
              <label class="label">{{ $t('settingsModule.prefix') }}</label>
              <input v-model="invoiceSettings.invoice_number_prefix" type="text" class="input max-w-xs" placeholder="RE" maxlength="10" />
              <p class="text-xs text-gray-500 mt-1">
                Format: <span class="font-mono text-gray-400">{{ (invoiceSettings.invoice_number_prefix || 'RE').toUpperCase() }}-{{ new Date().getFullYear() }}-0001</span>
              </p>
            </div>
          </div>

          <!-- Zahlungsziel -->
          <div class="card p-6 space-y-4">
            <h2 class="text-lg font-semibold text-white">{{ $t('settingsModule.defaultPaymentTerms') }}</h2>
            <p class="text-sm text-gray-400">{{ $t('settingsModule.paymentTermsDescription') }}</p>
            <div>
              <label class="label">{{ $t('settingsModule.paymentDeadline') }}</label>
              <input v-model="invoiceSettings.invoice_default_payment_terms" type="text" class="input" placeholder=$t('invoicesModule.zahlbarInnerhalbVon30TagenNachRechnungsdatum') />
            </div>
          </div>

          <!-- Logo -->
          <div class="card p-6 space-y-4">
            <h2 class="text-lg font-semibold text-white">{{ $t('settingsModule.companyLogo') }}</h2>
            <p class="text-sm text-gray-400">{{ $t('settingsModule.logoDescription') }}</p>

            <!-- Current logo preview -->
            <div v-if="invoiceLogoPreviewUrl" class="flex items-start gap-4">
              <div class="bg-white rounded-lg p-3 max-w-[200px]">
                <img :src="invoiceLogoPreviewUrl" alt="Firmenlogo" class="max-h-16 object-contain" />
              </div>
              <button @click="removeLogo" class="flex items-center gap-2 text-red-400 hover:text-red-300 text-sm mt-2">
                <TrashIcon class="w-4 h-4" />
                {{ $t('settingsModule.removeLogo') }}
              </button>
            </div>

            <!-- Upload area -->
            <div>
              <label class="flex flex-col items-center justify-center w-full h-28 border-2 border-dashed border-white/[0.08] rounded-lg cursor-pointer hover:border-primary-500 hover:bg-white/[0.04] transition-colors">
                <div class="flex flex-col items-center gap-2">
                  <PhotoIcon class="w-8 h-8 text-gray-400" />
                  <p class="text-sm text-gray-400">
                    {{ invoiceLogoFile ? invoiceLogoFile.name : $t('settingsModule.selectOrDropLogo') }}
                  </p>
                  <p class="text-xs text-gray-500">PNG, JPG, SVG · Max. 5 MB</p>
                </div>
                <input type="file" class="hidden" accept="image/*" @change="onLogoFileChange" />
              </label>
            </div>
          </div>

          <!-- Standard-Stundensatz -->
          <div class="card p-6 space-y-4">
            <h2 class="text-lg font-semibold text-white">{{ $t('settingsModule.defaultHourlyRate') }}</h2>
            <p class="text-sm text-gray-400">{{ $t('settingsModule.hourlyRateDescription') }}</p>
            <div class="flex items-center gap-3 max-w-xs">
              <input
                v-model.number="invoiceSettings.default_hourly_rate"
                type="number"
                min="0"
                step="0.01"
                class="input flex-1"
                placeholder="50.00"
              />
              <span class="text-gray-400 text-sm whitespace-nowrap">€ / Std</span>
            </div>
          </div>

          <!-- Save button -->
          <div class="flex justify-end">
            <button
              @click="saveInvoiceSettings"
              :disabled="invoiceSettingsSaving || invoiceLogoUploading"
              class="btn-primary px-6"
            >
              <span v-if="invoiceSettingsSaving || invoiceLogoUploading">{{ $t('common.saving') }}</span>
              <span v-else>{{ $t('settingsModule.saveSettings') }}</span>
            </button>
          </div>

          <!-- Leistungskatalog -->
          <div class="card p-6 space-y-4">
            <div class="flex items-center justify-between">
              <div>
                <h2 class="text-lg font-semibold text-white">{{ $t('settingsModule.serviceCatalog') }}</h2>
                <p class="text-sm text-gray-400 mt-0.5">{{ $t('settingsModule.serviceCatalogDescription') }}</p>
              </div>
              <button @click="openAddCatalogItem" class="btn-primary flex items-center gap-2 text-sm px-3 py-2">
                <PlusIcon class="w-4 h-4" />
                {{ $t('settingsModule.addService') }}
              </button>
            </div>

            <!-- Add/Edit Form -->
            <div v-if="showCatalogForm" class="bg-white/[0.04] rounded-lg p-4 space-y-3 border border-white/[0.08]">
              <h3 class="text-sm font-medium text-white">{{ editingCatalogItem ? $t('settingsModule.editService') : $t('settingsModule.newService') }}</h3>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label class="label">{{ $t('settingsModule.name') }} *</label>
                  <input v-model="catalogForm.name" type="text" class="input" placeholder="z.B. Webentwicklung" />
                </div>
                <div>
                  <label class="label">{{ $t('settingsModule.unit') }}</label>
                  <select v-model="catalogForm.unit" class="input">
                    <option v-for="u in catalogUnits" :key="u" :value="u">{{ u }}</option>
                  </select>
                </div>
                <div>
                  <label class="label">{{ $t('settingsModule.unitPrice') }}</label>
                  <input v-model.number="catalogForm.unit_price" type="number" min="0" step="0.01" class="input" placeholder="0.00" />
                </div>
                <div>
                  <label class="label">{{ $t('scripts.scriptsbeschreibungoptional') }}</label>
                  <input v-model="catalogForm.description" type="text" class="input" :placeholder="$t('settingsModule.shortDescription')" />
                </div>
              </div>
              <div class="flex gap-2 justify-end">
                <button @click="cancelCatalogForm" class="btn-secondary text-sm px-3 py-1.5 flex items-center gap-1">
                  <XMarkIcon class="w-4 h-4" /> Abbrechen
                </button>
                <button @click="saveCatalogItem" class="btn-primary text-sm px-3 py-1.5 flex items-center gap-1">
                  <CheckIcon class="w-4 h-4" /> Speichern
                </button>
              </div>
            </div>

            <!-- Catalog List -->
            <div v-if="catalogLoading" class="text-center py-6 text-gray-400 text-sm">{{ $t('settingsModule.loadingCatalog') }}</div>
            <div v-else-if="serviceCatalog.length === 0 && !showCatalogForm" class="text-center py-8 text-gray-500 text-sm">
              Noch keine Leistungen angelegt. Klicke auf „{{ $t('settingsModule.addService') }}".
            </div>
            <div v-else class="divide-y divide-white/[0.06]">
              <div
                v-for="item in serviceCatalog"
                :key="item.id"
                class="flex items-center justify-between py-3 gap-4"
              >
                <div class="flex-1 min-w-0">
                  <p class="text-white text-sm font-medium truncate">{{ item.name }}</p>
                  <p v-if="item.description" class="text-gray-400 text-xs truncate">{{ item.description }}</p>
                </div>
                <div class="text-right shrink-0">
                  <p class="text-white text-sm font-mono">{{ parseFloat(item.unit_price).toFixed(2) }} €</p>
                  <p class="text-gray-500 text-xs">/ {{ item.unit }}</p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                  <button @click="openEditCatalogItem(item)" class="text-gray-400 hover:text-white transition-colors p-1" :title="$t('common.edit')">
                    <PencilIcon class="w-4 h-4" />
                  </button>
                  <button @click="deleteCatalogItem(item)" class="text-gray-400 hover:text-red-400 transition-colors p-1" :title="$t('common.delete')">
                    <TrashIcon class="w-4 h-4" />
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Appearance -->
        <div v-if="activeTab === 'appearance'" class="space-y-6">
          <!-- Theme Presets -->
          <div class="card p-6">
            <h2 class="text-lg font-semibold text-white mb-2">{{ $t('settingsModule.colorScheme') }}</h2>
            <p class="text-sm text-gray-400 mb-5">{{ $t('settingsModule.chooseColorScheme') }}</p>

            <div class="grid grid-cols-2 gap-3">
              <!-- Slate preset -->
              <button
                @click="uiStore.setThemePreset('slate')"
                class="relative p-4 rounded-xl border-2 transition-all duration-200 text-left"
                :class="uiStore.themePreset === 'slate'
                  ? 'border-primary-500 bg-primary-500/5'
                  : 'border-white/[0.08] bg-white/[0.03] hover:border-white/[0.15] hover:bg-white/[0.05]'"
              >
                <div v-if="uiStore.themePreset === 'slate'" class="absolute top-2 right-2">
                  <CheckCircleIcon class="w-5 h-5 text-primary-400" />
                </div>
                <p class="text-sm font-semibold text-white mb-1">{{ $t('settingsModule.themeStandard') }}</p>
                <p class="text-2xs text-gray-500 mb-3">Neutral & modern</p>
                <div class="flex gap-1.5">
                  <span class="w-5 h-5 rounded-md" style="background: rgb(26, 26, 32)" />
                  <span class="w-5 h-5 rounded-md" style="background: rgb(32, 32, 40)" />
                  <span class="w-5 h-5 rounded-md" style="background: rgb(46, 46, 56)" />
                  <span class="w-5 h-5 rounded-md" style="background: rgb(60, 60, 72)" />
                  <span class="w-5 h-5 rounded-md" style="background: rgb(107, 107, 128)" />
                </div>
              </button>

              <!-- Midnight preset -->
              <button
                @click="uiStore.setThemePreset('midnight')"
                class="relative p-4 rounded-xl border-2 transition-all duration-200 text-left"
                :class="uiStore.themePreset === 'midnight'
                  ? 'border-primary-500 bg-primary-500/5'
                  : 'border-white/[0.08] bg-white/[0.03] hover:border-white/[0.15] hover:bg-white/[0.05]'"
              >
                <div v-if="uiStore.themePreset === 'midnight'" class="absolute top-2 right-2">
                  <CheckCircleIcon class="w-5 h-5 text-primary-400" />
                </div>
                <p class="text-sm font-semibold text-white mb-1">{{ $t('settingsModule.themeDark') }}</p>
                <p class="text-2xs text-gray-500 mb-3">{{ $t('settingsModule.idealForOled') }}</p>
                <div class="flex gap-1.5">
                  <span class="w-5 h-5 rounded-md" style="background: rgb(10, 10, 15)" />
                  <span class="w-5 h-5 rounded-md" style="background: rgb(17, 17, 24)" />
                  <span class="w-5 h-5 rounded-md" style="background: rgb(28, 28, 38)" />
                  <span class="w-5 h-5 rounded-md" style="background: rgb(42, 42, 54)" />
                  <span class="w-5 h-5 rounded-md" style="background: rgb(107, 107, 128)" />
                </div>
              </button>

              <!-- Stone preset -->
              <button
                @click="uiStore.setThemePreset('stone')"
                class="relative p-4 rounded-xl border-2 transition-all duration-200 text-left"
                :class="uiStore.themePreset === 'stone'
                  ? 'border-primary-500 bg-primary-500/5'
                  : 'border-white/[0.08] bg-white/[0.03] hover:border-white/[0.15] hover:bg-white/[0.05]'"
              >
                <div v-if="uiStore.themePreset === 'stone'" class="absolute top-2 right-2">
                  <CheckCircleIcon class="w-5 h-5 text-primary-400" />
                </div>
                <p class="text-sm font-semibold text-white mb-1">{{ $t('settingsModule.themeWarm') }}</p>
                <p class="text-2xs text-gray-500 mb-3">{{ $t('settingsModule.warmGrayTones') }}</p>
                <div class="flex gap-1.5">
                  <span class="w-5 h-5 rounded-md" style="background: rgb(28, 27, 26)" />
                  <span class="w-5 h-5 rounded-md" style="background: rgb(36, 34, 32)" />
                  <span class="w-5 h-5 rounded-md" style="background: rgb(52, 50, 46)" />
                  <span class="w-5 h-5 rounded-md" style="background: rgb(66, 64, 58)" />
                  <span class="w-5 h-5 rounded-md" style="background: rgb(107, 105, 100)" />
                </div>
              </button>

              <!-- Nord preset -->
              <button
                @click="uiStore.setThemePreset('nord')"
                class="relative p-4 rounded-xl border-2 transition-all duration-200 text-left"
                :class="uiStore.themePreset === 'nord'
                  ? 'border-primary-500 bg-primary-500/5'
                  : 'border-white/[0.08] bg-white/[0.03] hover:border-white/[0.15] hover:bg-white/[0.05]'"
              >
                <div v-if="uiStore.themePreset === 'nord'" class="absolute top-2 right-2">
                  <CheckCircleIcon class="w-5 h-5 text-primary-400" />
                </div>
                <p class="text-sm font-semibold text-white mb-1">{{ $t('settingsModule.themeNord') }}</p>
                <p class="text-2xs text-gray-500 mb-3">{{ $t('settingsModule.coolBlueTones') }}</p>
                <div class="flex gap-1.5">
                  <span class="w-5 h-5 rounded-md" style="background: rgb(22, 27, 36)" />
                  <span class="w-5 h-5 rounded-md" style="background: rgb(30, 35, 46)" />
                  <span class="w-5 h-5 rounded-md" style="background: rgb(46, 52, 66)" />
                  <span class="w-5 h-5 rounded-md" style="background: rgb(59, 66, 82)" />
                  <span class="w-5 h-5 rounded-md" style="background: rgb(107, 117, 138)" />
                </div>
              </button>
            </div>
          </div>

          <!-- Other appearance settings -->
          <div class="card p-6">
            <h2 class="text-lg font-semibold text-white mb-6">{{ $t('settingsModule.general') }}</h2>

            <div class="space-y-4">
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-white font-medium">{{ $t('settings.darkMode') }}</p>
                  <p class="text-gray-400 text-sm">{{ $t('settingsModule.useDarkTheme') }}</p>
                </div>
                <button
                  @click="uiStore.toggleDarkMode"
                  class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                  :class="uiStore.isDarkMode ? 'bg-primary-600' : 'bg-white/[0.08]'"
                >
                  <span
                    class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                    :class="uiStore.isDarkMode ? 'translate-x-6' : 'translate-x-1'"
                  />
                </button>
              </div>

              <div class="flex items-center justify-between">
                <div>
                  <p class="text-white font-medium">{{ $t('settingsModule.compactSidebar') }}</p>
                  <p class="text-gray-400 text-sm">{{ $t('settingsModule.collapseSidebarByDefault') }}</p>
                </div>
                <button
                  @click="uiStore.toggleSidebarCollapse"
                  class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                  :class="uiStore.sidebarCollapsed ? 'bg-primary-600' : 'bg-white/[0.08]'"
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
  </div>
</template>
