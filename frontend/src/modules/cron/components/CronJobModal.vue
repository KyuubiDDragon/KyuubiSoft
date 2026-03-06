<script setup>
import { ref, watch, onMounted, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { XMarkIcon } from '@heroicons/vue/24/outline'
import api from '@/core/api/axios'
import { useCronStore } from '@/modules/cron/stores/cronStore'

const props = defineProps({
  show: { type: Boolean, default: false },
  job: { type: Object, default: null },
})

const emit = defineEmits(['close', 'saved'])

const { t } = useI18n()
const cronStore = useCronStore()

// Form state
const form = ref({
  connection_id: '',
  expression: '',
  command: '',
  description: '',
})

const connections = ref([])
const parsedDescription = ref('')
const parsedNextRuns = ref([])
const saving = ref(false)
let parseTimeout = null

// Preset expressions
const presets = computed(() => [
  { label: t('cron.everyMinute'), value: '* * * * *' },
  { label: t('cron.hourly'), value: '0 * * * *' },
  { label: t('cron.daily'), value: '0 0 * * *' },
  { label: t('cron.weekly'), value: '0 0 * * 1' },
  { label: t('cron.monthly'), value: '0 0 1 * *' },
])

// Watch for modal open/close to reset form
watch(() => props.show, (newVal) => {
  if (newVal) {
    if (props.job) {
      form.value = {
        connection_id: props.job.connection_id || '',
        expression: props.job.expression || '',
        command: props.job.command || '',
        description: props.job.description || '',
      }
    } else {
      form.value = {
        connection_id: '',
        expression: '',
        command: '',
        description: '',
      }
    }
    parsedDescription.value = ''
    parsedNextRuns.value = []
    fetchConnections()
    if (form.value.expression) {
      debouncedParse()
    }
  }
})

// Watch expression changes for live parsing
watch(() => form.value.expression, () => {
  debouncedParse()
})

function debouncedParse() {
  if (parseTimeout) clearTimeout(parseTimeout)
  parseTimeout = setTimeout(async () => {
    if (form.value.expression.trim()) {
      const result = await cronStore.parseExpression(form.value.expression.trim())
      if (result) {
        parsedDescription.value = result.description
        parsedNextRuns.value = result.next_runs || []
      } else {
        parsedDescription.value = ''
        parsedNextRuns.value = []
      }
    } else {
      parsedDescription.value = ''
      parsedNextRuns.value = []
    }
  }, 400)
}

async function fetchConnections() {
  try {
    const response = await api.get('/api/v1/connections')
    connections.value = response.data.data || []
  } catch (error) {
    connections.value = []
  }
}

function applyPreset(value) {
  form.value.expression = value
}

async function handleSave() {
  saving.value = true
  try {
    if (props.job) {
      const result = await cronStore.updateJob(props.job.id, {
        expression: form.value.expression,
        command: form.value.command,
        description: form.value.description || null,
        connection_id: form.value.connection_id || null,
      })
      if (result) {
        emit('saved')
      }
    } else {
      const result = await cronStore.createJob({
        expression: form.value.expression,
        command: form.value.command,
        description: form.value.description || undefined,
        connection_id: form.value.connection_id || null,
      })
      if (result) {
        emit('saved')
      }
    }
  } finally {
    saving.value = false
  }
}

function handleClose() {
  emit('close')
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="show"
      class="fixed inset-0 z-50 flex items-center justify-center p-4"
    >
      <div class="fixed inset-0 bg-black/60 backdrop-blur-sm"></div>
      <div class="relative card-glass w-full max-w-2xl max-h-[90vh] flex flex-col">
        <!-- Header -->
        <div class="flex items-center justify-between p-5 border-b border-white/[0.06]">
          <h3 class="text-lg font-semibold text-white">
            {{ job ? $t('cron.editJob') : $t('cron.createNewJob') }}
          </h3>
          <button @click="handleClose" class="btn-icon-sm">
            <XMarkIcon class="w-5 h-5" />
          </button>
        </div>

        <!-- Body -->
        <div class="p-5 space-y-5 overflow-y-auto">
          <!-- Server selection -->
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-1.5">{{ $t('cron.serverSelection') }}</label>
            <select v-model="form.connection_id" class="select w-full">
              <option value="">{{ $t('cron.localNoServer') }}</option>
              <option v-for="conn in connections" :key="conn.id" :value="conn.id">
                {{ conn.name }} ({{ conn.host }})
              </option>
            </select>
          </div>

          <!-- Cron Expression -->
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-1.5">{{ $t('cron.expression') }}</label>

            <!-- Presets -->
            <div class="flex flex-wrap gap-2 mb-3">
              <button
                v-for="preset in presets"
                :key="preset.value"
                @click="applyPreset(preset.value)"
                class="px-3 py-1 text-xs rounded-lg border transition-colors"
                :class="form.expression === preset.value
                  ? 'border-primary-500 bg-primary-500/15 text-primary-400'
                  : 'border-white/10 text-gray-400 hover:border-white/20 hover:text-gray-300'"
              >
                {{ preset.label }}
              </button>
            </div>

            <!-- Raw expression input -->
            <input
              v-model="form.expression"
              type="text"
              placeholder="* * * * *"
              class="input w-full font-mono"
            />

            <!-- Live preview -->
            <div v-if="parsedDescription" class="mt-2 p-3 rounded-lg bg-white/[0.03] border border-white/[0.06]">
              <p class="text-sm text-primary-400 font-medium">{{ parsedDescription }}</p>
              <div v-if="parsedNextRuns.length" class="mt-2">
                <p class="text-xs text-gray-500 mb-1">{{ $t('cron.nextExecutions') }}</p>
                <ul class="space-y-0.5">
                  <li v-for="(run, idx) in parsedNextRuns" :key="idx" class="text-xs text-gray-400 font-mono">
                    {{ run }}
                  </li>
                </ul>
              </div>
            </div>

            <p class="text-xs text-gray-600 mt-1">{{ $t('cron.formatHint') }}</p>
          </div>

          <!-- Command -->
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-1.5">{{ $t('cron.command') }}</label>
            <textarea
              v-model="form.command"
              rows="4"
              :placeholder="$t('cron.commandPlaceholder')"
              class="input w-full font-mono resize-y"
            ></textarea>
          </div>

          <!-- Description -->
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-1.5">{{ $t('common.description') }}</label>
            <input
              v-model="form.description"
              type="text"
              :placeholder="$t('cron.optionalDescription')"
              class="input w-full"
            />
          </div>
        </div>

        <!-- Footer -->
        <div class="flex items-center justify-end gap-3 p-5 border-t border-white/[0.06]">
          <button @click="handleClose" class="btn-secondary">
            {{ $t('common.cancel') }}
          </button>
          <button
            @click="handleSave"
            :disabled="saving || !form.expression.trim() || !form.command.trim()"
            class="btn-primary"
          >
            {{ saving ? $t('common.saving') : (job ? $t('common.refresh') : $t('common.create')) }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
