<script setup>
import { ref, reactive, onMounted, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/core/api/axios'
import { useUiStore } from '@/stores/ui'
import {
  ArrowLeftIcon,
  CommandLineIcon,
  PlayIcon,
  ClockIcon,
  BookmarkIcon,
  PlusIcon,
  TrashIcon,
  PencilIcon,
  XMarkIcon,
  ExclamationTriangleIcon,
  CheckCircleIcon,
  XCircleIcon,
  ShieldCheckIcon,
} from '@heroicons/vue/24/outline'

const route = useRoute()
const router = useRouter()
const uiStore = useUiStore()

// State
const connection = ref(null)
const loading = ref(true)
const executing = ref(false)
const commandInput = ref('')
const commandOutput = ref([])
const commandHistory = ref([])
const presets = ref([])
const sensitiveToken = ref(null)

// 2FA Modal
const show2FAModal = ref(false)
const twoFACode = ref('')
const verifying2FA = ref(false)

// Preset Modal
const showPresetModal = ref(false)
const editingPreset = ref(null)
const presetForm = reactive({
  name: '',
  command: '',
  description: '',
  is_dangerous: false,
})

// Terminal ref for scrolling
const terminalRef = ref(null)

// Fetch connection
async function fetchConnection() {
  loading.value = true
  try {
    const response = await api.get(`/api/v1/connections/${route.params.id}`)
    connection.value = response.data.data

    if (connection.value.type !== 'ssh' && connection.value.type !== 'sftp') {
      uiStore.showError('Diese Verbindung unterstützt kein SSH')
      router.push('/connections')
      return
    }

    // Load presets and history
    await Promise.all([fetchPresets(), fetchHistory()])
  } catch (error) {
    uiStore.showError('Verbindung nicht gefunden')
    router.push('/connections')
  } finally {
    loading.value = false
  }
}

// Fetch presets
async function fetchPresets() {
  try {
    const response = await api.get(`/api/v1/connections/${route.params.id}/presets`)
    presets.value = response.data.data.items || []
  } catch (error) {
    console.error('Error fetching presets:', error)
  }
}

// Fetch history
async function fetchHistory() {
  try {
    const response = await api.get(`/api/v1/connections/${route.params.id}/history`)
    commandHistory.value = response.data.data.items || []
  } catch (error) {
    console.error('Error fetching history:', error)
  }
}

// Execute command
async function executeCommand(command = null) {
  const cmd = command || commandInput.value.trim()
  if (!cmd) return

  // Check for 2FA token
  if (!sensitiveToken.value) {
    show2FAModal.value = true
    commandInput.value = cmd
    return
  }

  executing.value = true

  // Add to output
  commandOutput.value.push({
    type: 'command',
    content: `$ ${cmd}`,
    timestamp: new Date().toISOString(),
  })

  try {
    const response = await api.post(`/api/v1/connections/${route.params.id}/execute`, {
      command: cmd,
      sensitive_token: sensitiveToken.value,
    })

    commandOutput.value.push({
      type: response.data.data.success ? 'success' : 'error',
      content: response.data.data.output || '(keine Ausgabe)',
      exitCode: response.data.data.exit_code,
      timestamp: new Date().toISOString(),
    })

    commandInput.value = ''
    await fetchHistory()
  } catch (error) {
    if (error.response?.status === 428 && error.response?.data?.data?.requires_2fa) {
      sensitiveToken.value = null
      show2FAModal.value = true
      return
    }

    if (error.response?.status === 401) {
      sensitiveToken.value = null
      show2FAModal.value = true
      uiStore.showError('2FA-Token abgelaufen, bitte erneut verifizieren')
      return
    }

    commandOutput.value.push({
      type: 'error',
      content: error.response?.data?.message || 'Fehler bei der Ausführung',
      timestamp: new Date().toISOString(),
    })
  } finally {
    executing.value = false
    scrollToBottom()
  }
}

// Verify 2FA
async function verify2FA() {
  if (!twoFACode.value.trim()) {
    uiStore.showError('Bitte 2FA-Code eingeben')
    return
  }

  verifying2FA.value = true

  try {
    const response = await api.post('/api/v1/auth/2fa/verify-sensitive', {
      code: twoFACode.value,
      operation: 'ssh_terminal',
    })

    sensitiveToken.value = response.data.data.sensitive_token
    show2FAModal.value = false
    twoFACode.value = ''
    uiStore.showSuccess('2FA verifiziert - Token gültig für 5 Minuten')

    // Execute pending command if any
    if (commandInput.value) {
      await executeCommand()
    }
  } catch (error) {
    uiStore.showError(error.response?.data?.message || 'Ungültiger 2FA-Code')
  } finally {
    verifying2FA.value = false
  }
}

// Run preset
function runPreset(preset) {
  if (preset.is_dangerous) {
    if (!confirm(`WARNUNG: Dieser Befehl ist als gefährlich markiert!\n\n${preset.command}\n\nWirklich ausführen?`)) {
      return
    }
  }
  executeCommand(preset.command)
}

// Open preset modal
function openPresetModal(preset = null) {
  editingPreset.value = preset
  if (preset) {
    presetForm.name = preset.name
    presetForm.command = preset.command
    presetForm.description = preset.description || ''
    presetForm.is_dangerous = !!preset.is_dangerous
  } else {
    presetForm.name = ''
    presetForm.command = commandInput.value || ''
    presetForm.description = ''
    presetForm.is_dangerous = false
  }
  showPresetModal.value = true
}

// Save preset
async function savePreset() {
  if (!presetForm.name.trim() || !presetForm.command.trim()) {
    uiStore.showError('Name und Befehl sind erforderlich')
    return
  }

  try {
    if (editingPreset.value) {
      await api.put(
        `/api/v1/connections/${route.params.id}/presets/${editingPreset.value.id}`,
        presetForm
      )
      uiStore.showSuccess('Preset aktualisiert')
    } else {
      await api.post(`/api/v1/connections/${route.params.id}/presets`, presetForm)
      uiStore.showSuccess('Preset erstellt')
    }

    showPresetModal.value = false
    await fetchPresets()
  } catch (error) {
    uiStore.showError('Fehler beim Speichern')
  }
}

// Delete preset
async function deletePreset(preset) {
  if (!confirm('Preset wirklich löschen?')) return

  try {
    await api.delete(`/api/v1/connections/${route.params.id}/presets/${preset.id}`)
    uiStore.showSuccess('Preset gelöscht')
    await fetchPresets()
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
  }
}

// Use history command
function useHistoryCommand(cmd) {
  commandInput.value = cmd
}

// Scroll to bottom
function scrollToBottom() {
  nextTick(() => {
    if (terminalRef.value) {
      terminalRef.value.scrollTop = terminalRef.value.scrollHeight
    }
  })
}

// Clear output
function clearOutput() {
  commandOutput.value = []
}

// Format date
function formatDate(dateStr) {
  if (!dateStr) return ''
  return new Date(dateStr).toLocaleTimeString('de-DE', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  })
}

onMounted(() => {
  fetchConnection()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-20">
      <div class="w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full animate-spin"></div>
    </div>

    <template v-else-if="connection">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
          <button
            @click="router.push('/connections')"
            class="p-2 text-gray-400 hover:text-white hover:bg-dark-700 rounded-lg transition-colors"
          >
            <ArrowLeftIcon class="w-5 h-5" />
          </button>
          <div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-2">
              <CommandLineIcon class="w-6 h-6 text-primary-400" />
              {{ connection.name }}
            </h1>
            <p class="text-gray-400 text-sm mt-1">
              {{ connection.username }}@{{ connection.host }}:{{ connection.port }}
            </p>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <span
            v-if="sensitiveToken"
            class="px-3 py-1 bg-green-500/20 text-green-400 rounded-full text-sm flex items-center gap-1"
          >
            <ShieldCheckIcon class="w-4 h-4" />
            2FA aktiv
          </span>
          <button
            @click="clearOutput"
            class="px-4 py-2 bg-dark-700 text-white rounded-lg hover:bg-dark-600 transition-colors"
          >
            Terminal leeren
          </button>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <!-- Terminal -->
        <div class="lg:col-span-3 space-y-4">
          <!-- Output -->
          <div
            ref="terminalRef"
            class="bg-dark-900 border border-dark-700 rounded-xl p-4 h-[500px] overflow-y-auto font-mono text-sm"
          >
            <div v-if="commandOutput.length === 0" class="text-gray-500 text-center py-8">
              Terminal bereit. Gib einen Befehl ein oder wähle ein Preset.
            </div>

            <div
              v-for="(item, index) in commandOutput"
              :key="index"
              class="mb-3"
            >
              <div
                v-if="item.type === 'command'"
                class="text-primary-400"
              >
                {{ item.content }}
              </div>
              <div
                v-else
                class="pl-4 whitespace-pre-wrap"
                :class="{
                  'text-gray-300': item.type === 'success',
                  'text-red-400': item.type === 'error',
                }"
              >
                {{ item.content }}
                <span
                  v-if="item.exitCode !== undefined && item.exitCode !== 0"
                  class="text-yellow-400 text-xs ml-2"
                >
                  (Exit: {{ item.exitCode }})
                </span>
              </div>
            </div>

            <div v-if="executing" class="flex items-center gap-2 text-gray-400">
              <div class="w-4 h-4 border-2 border-gray-400 border-t-transparent rounded-full animate-spin"></div>
              Wird ausgeführt...
            </div>
          </div>

          <!-- Input -->
          <div class="flex gap-2">
            <div class="flex-1 relative">
              <span class="absolute left-4 top-1/2 -translate-y-1/2 text-primary-400 font-mono">$</span>
              <input
                v-model="commandInput"
                type="text"
                class="w-full bg-dark-800 border border-dark-700 rounded-lg pl-8 pr-4 py-3 text-white font-mono focus:outline-none focus:border-primary-500"
                placeholder="Befehl eingeben..."
                @keydown.enter="executeCommand()"
                :disabled="executing"
              />
            </div>
            <button
              @click="executeCommand()"
              :disabled="executing || !commandInput.trim()"
              class="px-6 py-3 bg-primary-600 text-white rounded-lg hover:bg-primary-500 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
            >
              <PlayIcon class="w-5 h-5" />
              Ausführen
            </button>
            <button
              @click="openPresetModal()"
              class="px-4 py-3 bg-dark-700 text-white rounded-lg hover:bg-dark-600 transition-colors"
              title="Als Preset speichern"
            >
              <BookmarkIcon class="w-5 h-5" />
            </button>
          </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
          <!-- Presets -->
          <div class="bg-dark-800 border border-dark-700 rounded-xl p-4">
            <div class="flex items-center justify-between mb-4">
              <h3 class="font-medium text-white flex items-center gap-2">
                <BookmarkIcon class="w-5 h-5 text-primary-400" />
                Presets
              </h3>
              <button
                @click="openPresetModal()"
                class="p-1 text-gray-400 hover:text-white rounded transition-colors"
              >
                <PlusIcon class="w-5 h-5" />
              </button>
            </div>

            <div v-if="presets.length === 0" class="text-gray-500 text-sm text-center py-4">
              Keine Presets vorhanden
            </div>

            <div class="space-y-2">
              <div
                v-for="preset in presets"
                :key="preset.id"
                class="group flex items-center gap-2 p-2 rounded-lg hover:bg-dark-700 transition-colors"
              >
                <button
                  @click="runPreset(preset)"
                  class="flex-1 text-left"
                  :title="preset.command"
                >
                  <div class="flex items-center gap-2">
                    <span class="text-white text-sm">{{ preset.name }}</span>
                    <ExclamationTriangleIcon
                      v-if="preset.is_dangerous"
                      class="w-4 h-4 text-red-400"
                      title="Gefährlicher Befehl"
                    />
                  </div>
                  <p class="text-xs text-gray-500 truncate">{{ preset.command }}</p>
                </button>
                <button
                  @click="openPresetModal(preset)"
                  class="p-1 text-gray-400 hover:text-white opacity-0 group-hover:opacity-100 transition-all"
                >
                  <PencilIcon class="w-4 h-4" />
                </button>
                <button
                  @click="deletePreset(preset)"
                  class="p-1 text-gray-400 hover:text-red-400 opacity-0 group-hover:opacity-100 transition-all"
                >
                  <TrashIcon class="w-4 h-4" />
                </button>
              </div>
            </div>
          </div>

          <!-- History -->
          <div class="bg-dark-800 border border-dark-700 rounded-xl p-4">
            <h3 class="font-medium text-white flex items-center gap-2 mb-4">
              <ClockIcon class="w-5 h-5 text-primary-400" />
              Verlauf
            </h3>

            <div v-if="commandHistory.length === 0" class="text-gray-500 text-sm text-center py-4">
              Keine Befehle ausgeführt
            </div>

            <div class="space-y-2 max-h-64 overflow-y-auto">
              <button
                v-for="item in commandHistory"
                :key="item.id"
                @click="useHistoryCommand(item.command)"
                class="w-full text-left p-2 rounded-lg hover:bg-dark-700 transition-colors group"
              >
                <div class="flex items-center gap-2">
                  <component
                    :is="item.exit_code === 0 ? CheckCircleIcon : XCircleIcon"
                    class="w-4 h-4 flex-shrink-0"
                    :class="item.exit_code === 0 ? 'text-green-400' : 'text-red-400'"
                  />
                  <span class="text-gray-300 text-sm truncate font-mono">{{ item.command }}</span>
                </div>
                <p class="text-xs text-gray-500 mt-1">
                  {{ formatDate(item.executed_at) }}
                </p>
              </button>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- 2FA Modal -->
    <Teleport to="body">
      <div
        v-if="show2FAModal"
        class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
      >
        <div class="bg-dark-800 border border-dark-700 rounded-xl w-full max-w-md p-6">
          <div class="flex items-center justify-between mb-6">
            <h2 class="text-lg font-semibold text-white flex items-center gap-2">
              <ShieldCheckIcon class="w-6 h-6 text-primary-400" />
              2FA-Verifizierung erforderlich
            </h2>
            <button
              @click="show2FAModal = false"
              class="p-1 text-gray-400 hover:text-white rounded"
            >
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <p class="text-gray-400 mb-4">
            Für SSH-Zugriff ist eine 2FA-Verifizierung erforderlich. Der Token ist 5 Minuten gültig.
          </p>

          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">2FA-Code</label>
              <input
                v-model="twoFACode"
                type="text"
                inputmode="numeric"
                pattern="[0-9]*"
                maxlength="6"
                class="w-full bg-dark-700 border border-dark-600 rounded-lg px-4 py-3 text-white text-center text-xl tracking-widest font-mono focus:outline-none focus:border-primary-500"
                placeholder="000000"
                @keydown.enter="verify2FA"
              />
            </div>

            <button
              @click="verify2FA"
              :disabled="verifying2FA || twoFACode.length < 6"
              class="w-full px-4 py-3 bg-primary-600 text-white rounded-lg hover:bg-primary-500 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {{ verifying2FA ? 'Wird verifiziert...' : 'Verifizieren' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Preset Modal -->
    <Teleport to="body">
      <div
        v-if="showPresetModal"
        class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
      >
        <div class="bg-dark-800 border border-dark-700 rounded-xl w-full max-w-lg p-6">
          <div class="flex items-center justify-between mb-6">
            <h2 class="text-lg font-semibold text-white">
              {{ editingPreset ? 'Preset bearbeiten' : 'Neues Preset' }}
            </h2>
            <button
              @click="showPresetModal = false"
              class="p-1 text-gray-400 hover:text-white rounded"
            >
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Name</label>
              <input
                v-model="presetForm.name"
                type="text"
                class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-primary-500"
                placeholder="z.B. System-Status"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Befehl</label>
              <textarea
                v-model="presetForm.command"
                rows="3"
                class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white font-mono text-sm focus:outline-none focus:border-primary-500 resize-none"
                placeholder="htop"
              ></textarea>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Beschreibung (optional)</label>
              <input
                v-model="presetForm.description"
                type="text"
                class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-primary-500"
                placeholder="Zeigt den System-Status an"
              />
            </div>

            <div>
              <label class="flex items-center gap-2 cursor-pointer">
                <input
                  v-model="presetForm.is_dangerous"
                  type="checkbox"
                  class="w-4 h-4 rounded bg-dark-700 border-dark-600 text-red-600 focus:ring-red-500"
                />
                <span class="text-gray-300">Als gefährlich markieren</span>
                <ExclamationTriangleIcon class="w-4 h-4 text-red-400" />
              </label>
              <p class="text-xs text-gray-500 mt-1 ml-6">
                Gefährliche Befehle erfordern eine zusätzliche Bestätigung
              </p>
            </div>
          </div>

          <div class="flex items-center justify-end gap-3 mt-6">
            <button
              @click="showPresetModal = false"
              class="px-4 py-2 text-gray-400 hover:text-white transition-colors"
            >
              Abbrechen
            </button>
            <button
              @click="savePreset"
              class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-500 transition-colors"
            >
              {{ editingPreset ? 'Speichern' : 'Erstellen' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
