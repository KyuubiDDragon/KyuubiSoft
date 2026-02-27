<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { XMarkIcon, PlusIcon, TrashIcon, ChevronLeftIcon, ChevronRightIcon } from '@heroicons/vue/24/outline'
import { useNotificationRulesStore } from '@/modules/notification-rules/stores/notificationRulesStore'

const props = defineProps({
  show: {
    type: Boolean,
    required: true,
  },
  rule: {
    type: Object,
    default: null,
  },
})

const emit = defineEmits(['close', 'saved'])

const store = useNotificationRulesStore()

// Wizard state
const currentStep = ref(1)
const totalSteps = 3
const saving = ref(false)

// Form data
const form = ref({
  name: '',
  trigger_event: '',
  conditions: [],
  actions: [],
})

// Operators for conditions
const operators = [
  { value: 'equals', label: 'gleich' },
  { value: 'not_equals', label: 'ungleich' },
  { value: 'contains', label: 'enthält' },
  { value: 'not_contains', label: 'enthält nicht' },
  { value: 'greater_than', label: 'größer als' },
  { value: 'less_than', label: 'kleiner als' },
]

// Action types
const actionTypes = [
  { value: 'push', label: 'Push-Benachrichtigung' },
  { value: 'webhook', label: 'Webhook' },
]

// Group events by module
const groupedEvents = computed(() => {
  const groups = {}
  for (const event of store.availableEvents) {
    const module = event.module || 'Sonstige'
    if (!groups[module]) {
      groups[module] = []
    }
    groups[module].push(event)
  }
  return groups
})

// Step labels
const stepLabels = ['Grunddaten', 'Bedingungen', 'Aktionen']

// Validation
const isStep1Valid = computed(() => {
  return form.value.name.trim() !== '' && form.value.trigger_event !== ''
})

const isStep3Valid = computed(() => {
  if (form.value.actions.length === 0) return false
  return form.value.actions.every(action => {
    if (action.type === 'push') {
      return action.config.title?.trim() && action.config.message?.trim()
    }
    if (action.type === 'webhook') {
      return action.config.url?.trim()
    }
    return false
  })
})

const canProceed = computed(() => {
  if (currentStep.value === 1) return isStep1Valid.value
  if (currentStep.value === 2) return true // conditions are optional
  if (currentStep.value === 3) return isStep3Valid.value
  return false
})

const isEditMode = computed(() => props.rule !== null)

// Initialize form when modal opens or rule changes
watch(
  () => props.show,
  (newVal) => {
    if (newVal) {
      currentStep.value = 1
      if (props.rule) {
        form.value = {
          name: props.rule.name || '',
          trigger_event: props.rule.trigger_event || '',
          conditions: props.rule.conditions?.length
            ? JSON.parse(JSON.stringify(props.rule.conditions))
            : [],
          actions: props.rule.actions?.length
            ? JSON.parse(JSON.stringify(props.rule.actions))
            : [],
        }
      } else {
        form.value = {
          name: '',
          trigger_event: '',
          conditions: [],
          actions: [],
        }
      }
      // Ensure events are loaded
      if (store.availableEvents.length === 0) {
        store.fetchAvailableEvents()
      }
    }
  },
  { immediate: true }
)

// Conditions management
function addCondition() {
  form.value.conditions.push({
    field: '',
    operator: 'equals',
    value: '',
  })
}

function removeCondition(index) {
  form.value.conditions.splice(index, 1)
}

// Actions management
function addAction() {
  form.value.actions.push({
    type: 'push',
    config: {
      title: '',
      message: '',
      url: '',
    },
  })
}

function removeAction(index) {
  form.value.actions.splice(index, 1)
}

function onActionTypeChange(index, newType) {
  form.value.actions[index].type = newType
  form.value.actions[index].config = {
    title: '',
    message: '',
    url: '',
  }
}

// Navigation
function nextStep() {
  if (currentStep.value < totalSteps && canProceed.value) {
    currentStep.value++
  }
}

function prevStep() {
  if (currentStep.value > 1) {
    currentStep.value--
  }
}

// Save
async function handleSave() {
  if (!canProceed.value) return

  saving.value = true
  const payload = {
    name: form.value.name.trim(),
    trigger_event: form.value.trigger_event,
    conditions: form.value.conditions.filter(c => c.field.trim() !== ''),
    actions: form.value.actions,
  }

  let success = false
  if (isEditMode.value) {
    success = await store.updateRule(props.rule.id, payload)
  } else {
    success = await store.createRule(payload)
  }

  saving.value = false
  if (success) {
    emit('saved')
    emit('close')
  }
}
</script>

<template>
  <Teleport to="body">
    <div v-if="show" class="modal-overlay" @click.self="emit('close')">
      <div class="modal modal-lg">
        <!-- Header -->
        <div class="modal-header">
          <h2>{{ isEditMode ? 'Regel bearbeiten' : 'Neue Regel erstellen' }}</h2>
          <button @click="emit('close')" class="btn-icon-sm">
            <XMarkIcon class="w-5 h-5" />
          </button>
        </div>

        <!-- Step Indicator -->
        <div class="px-6 py-4 border-b border-white/[0.06]">
          <div class="flex items-center justify-between">
            <div
              v-for="(label, idx) in stepLabels"
              :key="idx"
              class="flex items-center"
              :class="idx < stepLabels.length - 1 ? 'flex-1' : ''"
            >
              <div class="flex items-center gap-2">
                <div
                  class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium transition-colors"
                  :class="currentStep > idx + 1
                    ? 'bg-emerald-500/20 text-emerald-400'
                    : currentStep === idx + 1
                      ? 'bg-primary-500/20 text-primary-400'
                      : 'bg-white/[0.06] text-gray-500'"
                >
                  {{ idx + 1 }}
                </div>
                <span
                  class="text-sm font-medium whitespace-nowrap"
                  :class="currentStep === idx + 1 ? 'text-white' : 'text-gray-500'"
                >
                  {{ label }}
                </span>
              </div>
              <div
                v-if="idx < stepLabels.length - 1"
                class="flex-1 mx-4 h-px"
                :class="currentStep > idx + 1 ? 'bg-emerald-500/30' : 'bg-white/[0.06]'"
              ></div>
            </div>
          </div>
        </div>

        <!-- Body -->
        <div class="modal-body space-y-6" style="min-height: 320px;">
          <!-- Step 1: Name + Trigger Event -->
          <div v-if="currentStep === 1" class="space-y-5">
            <div>
              <label class="text-xs text-gray-500 uppercase tracking-wider block mb-2">
                Regelname
              </label>
              <input
                v-model="form.name"
                type="text"
                class="input w-full"
                placeholder="z.B. Server-Ausfall Benachrichtigung"
              />
            </div>

            <div>
              <label class="text-xs text-gray-500 uppercase tracking-wider block mb-2">
                Trigger-Ereignis
              </label>
              <select v-model="form.trigger_event" class="select w-full">
                <option value="" disabled>Ereignis auswählen...</option>
                <optgroup
                  v-for="(events, module) in groupedEvents"
                  :key="module"
                  :label="module"
                >
                  <option
                    v-for="event in events"
                    :key="event.key"
                    :value="event.key"
                  >
                    {{ event.label }}
                  </option>
                </optgroup>
              </select>
            </div>
          </div>

          <!-- Step 2: Conditions -->
          <div v-if="currentStep === 2" class="space-y-4">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm text-gray-400">
                  Bedingungen sind optional. Ohne Bedingungen wird die Regel bei jedem Trigger-Ereignis ausgelöst.
                </p>
              </div>
              <button @click="addCondition" class="btn-secondary shrink-0">
                <PlusIcon class="w-4 h-4 mr-1" />
                Bedingung
              </button>
            </div>

            <div v-if="form.conditions.length === 0" class="text-center py-8">
              <p class="text-gray-500 text-sm">Keine Bedingungen definiert</p>
              <p class="text-gray-600 text-xs mt-1">Regel wird bei jedem passenden Ereignis ausgelöst</p>
            </div>

            <div
              v-for="(condition, idx) in form.conditions"
              :key="idx"
              class="card-glass p-4"
            >
              <div class="flex items-start gap-3">
                <div class="flex-1 grid grid-cols-1 md:grid-cols-3 gap-3">
                  <div>
                    <label class="text-xs text-gray-500 block mb-1">Feld</label>
                    <input
                      v-model="condition.field"
                      type="text"
                      class="input w-full"
                      placeholder="z.B. status"
                    />
                  </div>
                  <div>
                    <label class="text-xs text-gray-500 block mb-1">Operator</label>
                    <select v-model="condition.operator" class="select w-full">
                      <option
                        v-for="op in operators"
                        :key="op.value"
                        :value="op.value"
                      >
                        {{ op.label }}
                      </option>
                    </select>
                  </div>
                  <div>
                    <label class="text-xs text-gray-500 block mb-1">Wert</label>
                    <input
                      v-model="condition.value"
                      type="text"
                      class="input w-full"
                      placeholder="z.B. critical"
                    />
                  </div>
                </div>
                <button
                  @click="removeCondition(idx)"
                  class="btn-icon-sm text-red-400 hover:text-red-300 mt-5"
                  title="Bedingung entfernen"
                >
                  <TrashIcon class="w-4 h-4" />
                </button>
              </div>
            </div>
          </div>

          <!-- Step 3: Actions -->
          <div v-if="currentStep === 3" class="space-y-4">
            <div class="flex items-center justify-between">
              <p class="text-sm text-gray-400">
                Definiere mindestens eine Aktion, die bei Auslösung ausgeführt wird.
              </p>
              <button @click="addAction" class="btn-secondary shrink-0">
                <PlusIcon class="w-4 h-4 mr-1" />
                Aktion
              </button>
            </div>

            <div v-if="form.actions.length === 0" class="text-center py-8">
              <p class="text-gray-500 text-sm">Keine Aktionen definiert</p>
              <p class="text-gray-600 text-xs mt-1">Mindestens eine Aktion ist erforderlich</p>
            </div>

            <div
              v-for="(action, idx) in form.actions"
              :key="idx"
              class="card-glass p-4"
            >
              <div class="flex items-start gap-3">
                <div class="flex-1 space-y-3">
                  <div>
                    <label class="text-xs text-gray-500 block mb-1">Aktionstyp</label>
                    <select
                      :value="action.type"
                      @change="onActionTypeChange(idx, $event.target.value)"
                      class="select w-full"
                    >
                      <option
                        v-for="at in actionTypes"
                        :key="at.value"
                        :value="at.value"
                      >
                        {{ at.label }}
                      </option>
                    </select>
                  </div>

                  <!-- Push notification config -->
                  <template v-if="action.type === 'push'">
                    <div>
                      <label class="text-xs text-gray-500 block mb-1">Titel</label>
                      <input
                        v-model="action.config.title"
                        type="text"
                        class="input w-full"
                        placeholder="Benachrichtigungstitel"
                      />
                    </div>
                    <div>
                      <label class="text-xs text-gray-500 block mb-1">Nachricht</label>
                      <input
                        v-model="action.config.message"
                        type="text"
                        class="input w-full"
                        placeholder="Benachrichtigungstext"
                      />
                    </div>
                  </template>

                  <!-- Webhook config -->
                  <template v-if="action.type === 'webhook'">
                    <div>
                      <label class="text-xs text-gray-500 block mb-1">Webhook URL</label>
                      <input
                        v-model="action.config.url"
                        type="url"
                        class="input w-full"
                        placeholder="https://example.com/webhook"
                      />
                    </div>
                  </template>
                </div>
                <button
                  @click="removeAction(idx)"
                  class="btn-icon-sm text-red-400 hover:text-red-300 mt-5"
                  title="Aktion entfernen"
                >
                  <TrashIcon class="w-4 h-4" />
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="modal-footer flex items-center justify-between">
          <button
            v-if="currentStep > 1"
            @click="prevStep"
            class="btn-secondary"
          >
            <ChevronLeftIcon class="w-4 h-4 mr-1" />
            Zurück
          </button>
          <div v-else></div>

          <div class="flex items-center gap-3">
            <button @click="emit('close')" class="btn-secondary">Abbrechen</button>
            <button
              v-if="currentStep < totalSteps"
              @click="nextStep"
              :disabled="!canProceed"
              class="btn-primary"
            >
              Weiter
              <ChevronRightIcon class="w-4 h-4 ml-1" />
            </button>
            <button
              v-else
              @click="handleSave"
              :disabled="!canProceed || saving"
              class="btn-primary"
            >
              <span v-if="saving" class="flex items-center gap-2">
                <div class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
                Speichern...
              </span>
              <span v-else>
                {{ isEditMode ? 'Aktualisieren' : 'Erstellen' }}
              </span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>
