<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/core/api/axios'
import { useUiStore } from '@/stores/ui'
import { useToast } from '@/composables/useToast'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
import {
  PlusIcon,
  TrashIcon,
  PencilIcon,
  XMarkIcon,
  BellIcon,
  CheckCircleIcon,
  ExclamationCircleIcon,
  BeakerIcon,
  LinkIcon,
} from '@heroicons/vue/24/outline'

const uiStore = useUiStore()
const toast = useToast()
const { confirm } = useConfirmDialog()

// State
const webhooks = ref([])
const availableEvents = ref([])
const loading = ref(true)
const showModal = ref(false)
const editingWebhook = ref(null)
const testing = ref(null)

// Form
const form = ref({
  name: '',
  url: '',
  type: 'discord',
  events: [],
  secret: '',
})

// Webhook types
const webhookTypes = [
  { value: 'discord', label: 'Discord', icon: '🎮' },
  { value: 'slack', label: 'Slack', icon: '💬' },
  { value: 'custom', label: 'Custom', icon: '🔗' },
]

// Fetch webhooks
async function fetchWebhooks() {
  loading.value = true
  try {
    const response = await api.get('/api/v1/webhooks')
    webhooks.value = response.data.data.items || []
  } catch (error) {
    uiStore.showError('Fehler beim Laden der Webhooks')
  } finally {
    loading.value = false
  }
}

// Fetch available events
async function fetchEvents() {
  try {
    const response = await api.get('/api/v1/webhooks/events')
    availableEvents.value = response.data.data.events || []
  } catch (error) {
    console.error('Failed to load events', error)
  }
}

// Open modal
function openModal(webhook = null) {
  editingWebhook.value = webhook
  if (webhook) {
    form.value = {
      name: webhook.name,
      url: webhook.url,
      type: webhook.type,
      events: [...webhook.events],
      secret: '',
    }
  } else {
    form.value = {
      name: '',
      url: '',
      type: 'discord',
      events: [],
      secret: '',
    }
  }
  showModal.value = true
}

// Save webhook
async function saveWebhook() {
  if (!form.value.name.trim()) {
    uiStore.showError('Name ist erforderlich')
    return
  }
  if (!form.value.url.trim()) {
    uiStore.showError('URL ist erforderlich')
    return
  }
  if (form.value.events.length === 0) {
    uiStore.showError('Mindestens ein Event ist erforderlich')
    return
  }

  try {
    if (editingWebhook.value) {
      await api.put(`/api/v1/webhooks/${editingWebhook.value.id}`, form.value)
      uiStore.showSuccess('Webhook aktualisiert')
    } else {
      await api.post('/api/v1/webhooks', form.value)
      uiStore.showSuccess('Webhook erstellt')
    }
    await fetchWebhooks()
    showModal.value = false
  } catch (error) {
    uiStore.showError(error.response?.data?.message || 'Fehler beim Speichern')
  }
}

// Delete webhook
async function deleteWebhook(webhook) {
  if (!await confirm({ message: `Webhook "${webhook.name}" wirklich löschen?`, type: 'danger', confirmText: 'Löschen' })) return

  try {
    await api.delete(`/api/v1/webhooks/${webhook.id}`)
    uiStore.showSuccess('Webhook gelöscht')
    await fetchWebhooks()
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
  }
}

// Toggle webhook active state
async function toggleActive(webhook) {
  try {
    await api.put(`/api/v1/webhooks/${webhook.id}`, {
      is_active: !webhook.is_active,
    })
    webhook.is_active = !webhook.is_active
  } catch (error) {
    uiStore.showError('Fehler beim Aktualisieren')
  }
}

// Test webhook
async function testWebhook(webhook) {
  testing.value = webhook.id
  try {
    const response = await api.post(`/api/v1/webhooks/${webhook.id}/test`)
    if (response.data.data.success) {
      uiStore.showSuccess('Test erfolgreich gesendet')
    } else {
      uiStore.showError(response.data.data.message)
    }
  } catch (error) {
    uiStore.showError('Test fehlgeschlagen')
  } finally {
    testing.value = null
  }
}

// Toggle event selection
function toggleEvent(eventValue) {
  const idx = form.value.events.indexOf(eventValue)
  if (idx >= 0) {
    form.value.events.splice(idx, 1)
  } else {
    form.value.events.push(eventValue)
  }
}

// Group events by category
const groupedEvents = computed(() => {
  const groups = {}
  for (const event of availableEvents.value) {
    if (!groups[event.category]) {
      groups[event.category] = []
    }
    groups[event.category].push(event)
  }
  return groups
})

// Category labels
const categoryLabels = {
  document: 'Dokumente',
  list: 'Listen',
  kanban: 'Kanban',
  project: 'Projekte',
  time: 'Zeiterfassung',
}

onMounted(() => {
  fetchWebhooks()
  fetchEvents()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-white">Webhooks</h1>
        <p class="text-gray-400 text-sm mt-1">Benachrichtigungen an Discord, Slack oder andere Dienste senden</p>
      </div>
      <button
        @click="openModal()"
        class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-500 transition-colors flex items-center gap-2"
      >
        <PlusIcon class="w-5 h-5" />
        <span>Neuer Webhook</span>
      </button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-20">
      <div class="w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full animate-spin"></div>
    </div>

    <!-- Webhooks List -->
    <div v-else class="space-y-4">
      <div
        v-for="webhook in webhooks"
        :key="webhook.id"
        class="bg-dark-800 border border-dark-700 rounded-xl p-4"
      >
        <div class="flex items-start justify-between">
          <div class="flex items-start gap-4">
            <div class="text-2xl">
              {{ webhookTypes.find(t => t.value === webhook.type)?.icon || '🔗' }}
            </div>
            <div>
              <div class="flex items-center gap-2">
                <h3 class="text-white font-semibold">{{ webhook.name }}</h3>
                <span
                  class="px-2 py-0.5 text-xs rounded-full"
                  :class="webhook.is_active ? 'bg-green-500/20 text-green-400' : 'bg-gray-500/20 text-gray-400'"
                >
                  {{ webhook.is_active ? 'Aktiv' : 'Inaktiv' }}
                </span>
              </div>
              <p class="text-gray-500 text-sm mt-1 font-mono truncate max-w-md">{{ webhook.url }}</p>
              <div class="flex flex-wrap gap-1 mt-2">
                <span
                  v-for="event in webhook.events"
                  :key="event"
                  class="px-2 py-0.5 bg-dark-700 text-gray-300 text-xs rounded"
                >
                  {{ event }}
                </span>
              </div>
              <div v-if="webhook.last_triggered_at" class="flex items-center gap-2 mt-2 text-xs text-gray-500">
                <component
                  :is="webhook.last_status === 'success' ? CheckCircleIcon : ExclamationCircleIcon"
                  class="w-4 h-4"
                  :class="webhook.last_status === 'success' ? 'text-green-400' : 'text-red-400'"
                />
                <span>
                  Zuletzt: {{ new Date(webhook.last_triggered_at).toLocaleString('de-DE') }}
                  <template v-if="webhook.failure_count > 0">
                    ({{ webhook.failure_count }} Fehler)
                  </template>
                </span>
              </div>
            </div>
          </div>

          <div class="flex items-center gap-2">
            <button
              @click="testWebhook(webhook)"
              :disabled="testing === webhook.id"
              class="p-2 text-gray-400 hover:text-white hover:bg-dark-600 rounded-lg transition-colors disabled:opacity-50"
              title="Test senden"
            >
              <BeakerIcon v-if="testing !== webhook.id" class="w-5 h-5" />
              <div v-else class="w-5 h-5 border-2 border-gray-400 border-t-transparent rounded-full animate-spin"></div>
            </button>
            <button
              @click="toggleActive(webhook)"
              class="p-2 text-gray-400 hover:text-white hover:bg-dark-600 rounded-lg transition-colors"
              :title="webhook.is_active ? 'Deaktivieren' : 'Aktivieren'"
            >
              <BellIcon class="w-5 h-5" :class="{ 'text-green-400': webhook.is_active }" />
            </button>
            <button
              @click="openModal(webhook)"
              class="p-2 text-gray-400 hover:text-white hover:bg-dark-600 rounded-lg transition-colors"
            >
              <PencilIcon class="w-5 h-5" />
            </button>
            <button
              @click="deleteWebhook(webhook)"
              class="p-2 text-gray-400 hover:text-red-400 hover:bg-dark-600 rounded-lg transition-colors"
            >
              <TrashIcon class="w-5 h-5" />
            </button>
          </div>
        </div>
      </div>

      <!-- Empty state -->
      <div
        v-if="webhooks.length === 0"
        class="bg-dark-800 border-2 border-dashed border-dark-600 rounded-xl p-8 text-center"
      >
        <LinkIcon class="w-12 h-12 text-gray-500 mx-auto mb-3" />
        <p class="text-gray-400">Keine Webhooks eingerichtet</p>
        <button
          @click="openModal()"
          class="mt-4 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-500 transition-colors"
        >
          Webhook erstellen
        </button>
      </div>
    </div>

    <!-- Modal -->
    <Teleport to="body">
      <div
        v-if="showModal"
        class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
        
      >
        <div class="bg-dark-800 border border-dark-700 rounded-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
          <div class="flex items-center justify-between p-4 border-b border-dark-700">
            <h2 class="text-lg font-semibold text-white">
              {{ editingWebhook ? 'Webhook bearbeiten' : 'Neuer Webhook' }}
            </h2>
            <button @click="showModal = false" class="p-1 text-gray-400 hover:text-white rounded">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <div class="p-4 space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Name *</label>
              <input
                v-model="form.name"
                type="text"
                class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white placeholder-gray-500 focus:outline-none focus:border-primary-500"
                placeholder="Mein Discord Webhook"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Typ</label>
              <div class="flex gap-2">
                <button
                  v-for="type in webhookTypes"
                  :key="type.value"
                  @click="form.type = type.value"
                  class="flex-1 px-3 py-2 rounded-lg border transition-colors"
                  :class="form.type === type.value
                    ? 'bg-primary-600 border-primary-500 text-white'
                    : 'bg-dark-700 border-dark-600 text-gray-300 hover:border-dark-500'"
                >
                  <span class="mr-1">{{ type.icon }}</span>
                  {{ type.label }}
                </button>
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Webhook URL *</label>
              <input
                v-model="form.url"
                type="url"
                class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white placeholder-gray-500 focus:outline-none focus:border-primary-500 font-mono text-sm"
                placeholder="https://discord.com/api/webhooks/..."
              />
            </div>

            <div v-if="form.type === 'custom'">
              <label class="block text-sm font-medium text-gray-300 mb-1">Secret (optional)</label>
              <input
                v-model="form.secret"
                type="text"
                class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white placeholder-gray-500 focus:outline-none focus:border-primary-500"
                placeholder="HMAC Secret für Signatur"
              />
              <p class="text-xs text-gray-500 mt-1">Wird für X-Signature Header verwendet</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Events *</label>
              <div class="space-y-3">
                <div v-for="(events, category) in groupedEvents" :key="category">
                  <h4 class="text-sm text-gray-400 mb-1">{{ categoryLabels[category] || category }}</h4>
                  <div class="flex flex-wrap gap-2">
                    <button
                      v-for="event in events"
                      :key="event.value"
                      @click="toggleEvent(event.value)"
                      class="px-3 py-1 text-sm rounded-lg border transition-colors"
                      :class="form.events.includes(event.value)
                        ? 'bg-primary-600 border-primary-500 text-white'
                        : 'bg-dark-700 border-dark-600 text-gray-300 hover:border-dark-500'"
                    >
                      {{ event.label }}
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="flex items-center justify-end gap-3 p-4 border-t border-dark-700">
            <button
              @click="showModal = false"
              class="px-4 py-2 text-gray-400 hover:text-white transition-colors"
            >
              Abbrechen
            </button>
            <button
              @click="saveWebhook"
              class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-500 transition-colors"
            >
              {{ editingWebhook ? 'Speichern' : 'Erstellen' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
