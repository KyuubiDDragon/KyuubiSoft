<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRecurringTasksStore } from '@/stores/recurringTasks'
import { useUiStore } from '@/stores/ui'
import { useToast } from '@/composables/useToast'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
import {
  ArrowPathIcon,
  PlusIcon,
  PencilIcon,
  TrashIcon,
  PlayIcon,
  PauseIcon,
  ForwardIcon,
  ClockIcon,
  CalendarIcon,
  XMarkIcon,
  CheckIcon,
  ChevronDownIcon,
  ListBulletIcon,
  RectangleStackIcon,
  ViewColumnsIcon,
  FolderIcon,
} from '@heroicons/vue/24/outline'

const { t } = useI18n()
const store = useRecurringTasksStore()
const uiStore = useUiStore()
const toast = useToast()
const { confirm } = useConfirmDialog()

const showModal = ref(false)
const showInstancesModal = ref(false)
const isEditing = ref(false)
const selectedTaskId = ref(null)
const instances = ref([])
const isLoadingInstances = ref(false)
const filterActive = ref('all') // 'all', 'active', 'inactive'

const form = ref({
  title: '',
  description: '',
  frequency: 'weekly',
  interval_value: 1,
  days_of_week: [],
  day_of_month: '',
  start_date: new Date().toISOString().split('T')[0],
  end_date: '',
  target_type: 'list',
  target_id: '',
  task_data: {},
})

const daysOfWeek = computed(() => [
  { value: 1, label: t('recurringTasks.mon') },
  { value: 2, label: t('recurringTasks.tue') },
  { value: 3, label: t('recurringTasks.wed') },
  { value: 4, label: t('recurringTasks.thu') },
  { value: 5, label: t('recurringTasks.fri') },
  { value: 6, label: t('recurringTasks.sat') },
  { value: 0, label: t('recurringTasks.sun') },
])

const targetTypeIcons = {
  list: ListBulletIcon,
  checklist: RectangleStackIcon,
  kanban: ViewColumnsIcon,
  project: FolderIcon,
}

const filteredTasks = computed(() => {
  if (filterActive.value === 'active') {
    return store.activeTasks
  } else if (filterActive.value === 'inactive') {
    return store.inactiveTasks
  }
  return store.tasks
})

onMounted(async () => {
  await store.loadTasks()
})

function openCreateModal() {
  isEditing.value = false
  resetForm()
  showModal.value = true
}

async function openEditModal(task) {
  isEditing.value = true
  selectedTaskId.value = task.id
  form.value = {
    title: task.title,
    description: task.description || '',
    frequency: task.frequency,
    interval_value: task.interval_value || 1,
    days_of_week: task.days_of_week ? task.days_of_week.split(',').map(d => parseInt(d)) : [],
    day_of_month: task.day_of_month || '',
    start_date: task.start_date,
    end_date: task.end_date || '',
    target_type: task.target_type || 'list',
    target_id: task.target_id || '',
    task_data: task.task_data || {},
  }
  showModal.value = true
}

function resetForm() {
  form.value = {
    title: '',
    description: '',
    frequency: 'weekly',
    interval_value: 1,
    days_of_week: [],
    day_of_month: '',
    start_date: new Date().toISOString().split('T')[0],
    end_date: '',
    target_type: 'list',
    target_id: '',
    task_data: {},
  }
}

async function saveTask() {
  if (!form.value.title.trim()) {
    uiStore.showError(t('recurringTasks.titleRequired'))
    return
  }

  const data = {
    ...form.value,
    days_of_week: form.value.days_of_week.length > 0 ? form.value.days_of_week.join(',') : null,
    end_date: form.value.end_date || null,
  }

  try {
    if (isEditing.value) {
      await store.updateTask(selectedTaskId.value, data)
      uiStore.showSuccess(t('recurringTasks.taskUpdated'))
    } else {
      await store.createTask(data)
      uiStore.showSuccess(t('recurringTasks.taskCreated'))
    }
    showModal.value = false
  } catch (error) {
    uiStore.showError(error.response?.data?.error || t('recurringTasks.errorSaving'))
  }
}

async function deleteTask(task) {
  if (!await confirm({ message: t('recurringTasks.confirmDelete', { title: task.title }), type: 'danger', confirmText: t('common.delete') })) return

  try {
    await store.deleteTask(task.id)
    uiStore.showSuccess(t('recurringTasks.taskDeleted'))
  } catch (error) {
    uiStore.showError(t('recurringTasks.errorDeleting'))
  }
}

async function toggleTask(task) {
  try {
    await store.toggleTask(task.id)
    uiStore.showSuccess(task.is_active ? t('recurringTasks.taskPaused') : t('recurringTasks.taskActivated'))
  } catch (error) {
    uiStore.showError(t('recurringTasks.errorToggling'))
  }
}

async function skipOccurrence(task) {
  try {
    await store.skipOccurrence(task.id)
    uiStore.showSuccess(t('recurringTasks.occurrenceSkipped'))
  } catch (error) {
    uiStore.showError(t('recurringTasks.errorSkipping'))
  }
}

async function processNow(task) {
  try {
    const result = await store.processTask(task.id)
    uiStore.showSuccess(t('recurringTasks.taskCreated'))
  } catch (error) {
    uiStore.showError(error.response?.data?.error || t('recurringTasks.errorProcessing'))
  }
}

async function showInstances(task) {
  selectedTaskId.value = task.id
  isLoadingInstances.value = true
  showInstancesModal.value = true

  try {
    instances.value = await store.getInstances(task.id)
  } catch (error) {
    uiStore.showError(t('recurringTasks.errorLoadingHistory'))
  } finally {
    isLoadingInstances.value = false
  }
}

function toggleDayOfWeek(day) {
  const index = form.value.days_of_week.indexOf(day)
  if (index >= 0) {
    form.value.days_of_week.splice(index, 1)
  } else {
    form.value.days_of_week.push(day)
  }
}

function formatDate(dateStr) {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString('de-DE', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  })
}

function formatDateTime(dateStr) {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString('de-DE', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function getDaysUntil(dateStr) {
  if (!dateStr) return null
  const date = new Date(dateStr)
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  const diff = Math.ceil((date - today) / (1000 * 60 * 60 * 24))
  if (diff === 0) return t('recurringTasks.today')
  if (diff === 1) return t('recurringTasks.tomorrow')
  if (diff < 0) return t('recurringTasks.overdue')
  return t('recurringTasks.inDays', { count: diff })
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-xl font-semibold text-white">{{ $t('recurringTasks.title') }}</h2>
        <p class="text-gray-400 text-sm mt-1">{{ $t('recurringTasks.subtitle') }}</p>
      </div>
      <button @click="openCreateModal" class="btn-primary flex items-center gap-2">
        <PlusIcon class="w-5 h-5" />
        {{ $t('recurringTasks.newTask') }}
      </button>
    </div>

    <!-- Filter -->
    <div class="flex gap-2">
      <button
        @click="filterActive = 'all'"
        class="px-4 py-2 rounded-lg text-sm transition-colors"
        :class="filterActive === 'all' ? 'bg-primary-600 text-white' : 'bg-white/[0.04] text-gray-400 hover:bg-white/[0.06]'"
      >
        {{ $t('recurringTasks.all') }} ({{ store.tasks.length }})
      </button>
      <button
        @click="filterActive = 'active'"
        class="px-4 py-2 rounded-lg text-sm transition-colors"
        :class="filterActive === 'active' ? 'bg-green-600 text-white' : 'bg-white/[0.04] text-gray-400 hover:bg-white/[0.06]'"
      >
        {{ $t('recurringTasks.active') }} ({{ store.activeTasks.length }})
      </button>
      <button
        @click="filterActive = 'inactive'"
        class="px-4 py-2 rounded-lg text-sm transition-colors"
        :class="filterActive === 'inactive' ? 'bg-gray-600 text-white' : 'bg-white/[0.04] text-gray-400 hover:bg-white/[0.06]'"
      >
        {{ $t('recurringTasks.paused') }} ({{ store.inactiveTasks.length }})
      </button>
    </div>

    <!-- Loading -->
    <div v-if="store.isLoading" class="text-center py-12">
      <ArrowPathIcon class="w-8 h-8 animate-spin text-primary-500 mx-auto" />
      <p class="text-gray-400 mt-2">{{ $t('common.loading') }}...</p>
    </div>

    <!-- Empty State -->
    <div v-else-if="filteredTasks.length === 0" class="text-center py-12">
      <ClockIcon class="w-16 h-16 text-gray-600 mx-auto mb-4" />
      <h3 class="text-lg font-medium text-white mb-2">{{ $t('recurringTasks.emptyTitle') }}</h3>
      <p class="text-gray-400 mb-4">{{ $t('recurringTasks.emptyDescription') }}</p>
      <button @click="openCreateModal" class="btn-primary">
        <PlusIcon class="w-5 h-5 mr-2" />
        {{ $t('recurringTasks.createFirst') }}
      </button>
    </div>

    <!-- Task List -->
    <div v-else class="space-y-4">
      <div
        v-for="task in filteredTasks"
        :key="task.id"
        class="card p-4"
        :class="{ 'opacity-60': !task.is_active }"
      >
        <div class="flex items-start gap-4">
          <!-- Status Icon -->
          <div
            class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0"
            :class="task.is_active ? 'bg-green-500/20 text-green-400' : 'bg-gray-500/20 text-gray-400'"
          >
            <component :is="targetTypeIcons[task.target_type] || ClockIcon" class="w-5 h-5" />
          </div>

          <!-- Content -->
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
              <h3 class="font-medium text-white truncate">{{ task.title }}</h3>
              <span
                v-if="!task.is_active"
                class="px-2 py-0.5 text-xs rounded-full bg-gray-500/20 text-gray-400"
              >
                {{ $t('recurringTasks.paused') }}
              </span>
            </div>

            <p v-if="task.description" class="text-gray-400 text-sm mt-1 line-clamp-2">
              {{ task.description }}
            </p>

            <div class="flex flex-wrap items-center gap-3 mt-2 text-sm">
              <!-- Frequency -->
              <span class="text-primary-400">
                <ArrowPathIcon class="w-4 h-4 inline mr-1" />
                {{ store.formatFrequency(task) }}
                <span v-if="task.frequency === 'weekly' && task.days_of_week" class="text-gray-500">
                  ({{ store.formatDaysOfWeek(task.days_of_week) }})
                </span>
              </span>

              <!-- Next Occurrence -->
              <span v-if="task.next_occurrence" class="text-gray-400">
                <CalendarIcon class="w-4 h-4 inline mr-1" />
                {{ $t('recurringTasks.next') }}: {{ formatDate(task.next_occurrence) }}
                <span
                  class="ml-1"
                  :class="{
                    'text-green-400': getDaysUntil(task.next_occurrence) === 'Heute',
                    'text-yellow-400': getDaysUntil(task.next_occurrence) === 'Morgen',
                    'text-red-400': getDaysUntil(task.next_occurrence) === 'Überfällig',
                  }"
                >
                  ({{ getDaysUntil(task.next_occurrence) }})
                </span>
              </span>

              <!-- Target Type -->
              <span class="text-gray-500">
                {{ store.targetTypeLabels[task.target_type] }}
              </span>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex items-center gap-1 shrink-0">
            <button
              @click="processNow(task)"
              class="p-2 text-green-400 hover:bg-green-500/10 rounded-lg transition-colors"
              :title="$t('recurringTasks.executeNow')"
              :disabled="!task.is_active"
            >
              <PlayIcon class="w-5 h-5" />
            </button>
            <button
              @click="skipOccurrence(task)"
              class="p-2 text-yellow-400 hover:bg-yellow-500/10 rounded-lg transition-colors"
              :title="$t('recurringTasks.skip')"
              :disabled="!task.is_active"
            >
              <ForwardIcon class="w-5 h-5" />
            </button>
            <button
              @click="toggleTask(task)"
              class="p-2 rounded-lg transition-colors"
              :class="task.is_active ? 'text-gray-400 hover:bg-gray-500/10' : 'text-green-400 hover:bg-green-500/10'"
              :title="task.is_active ? $t('recurringTasks.pause') : $t('recurringTasks.activate')"
            >
              <PauseIcon v-if="task.is_active" class="w-5 h-5" />
              <PlayIcon v-else class="w-5 h-5" />
            </button>
            <button
              @click="showInstances(task)"
              class="p-2 text-primary-400 hover:bg-primary-500/10 rounded-lg transition-colors"
              :title="$t('recurringTasks.history')"
            >
              <ClockIcon class="w-5 h-5" />
            </button>
            <button
              @click="openEditModal(task)"
              class="p-2 text-primary-400 hover:bg-primary-500/10 rounded-lg transition-colors"
              :title="$t('common.edit')"
            >
              <PencilIcon class="w-5 h-5" />
            </button>
            <button
              @click="deleteTask(task)"
              class="p-2 text-red-400 hover:bg-red-500/10 rounded-lg transition-colors"
              :title="$t('common.delete')"
            >
              <TrashIcon class="w-5 h-5" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-md" @click="showModal = false" />
        <div class="relative modal max-w-lg w-full max-h-[90vh] overflow-y-auto">
          <div class="sticky top-0 bg-dark-900/95 backdrop-blur-xl px-6 py-4 border-b border-white/[0.06] flex items-center justify-between">
            <h3 class="text-lg font-semibold text-white">
              {{ isEditing ? $t('recurringTasks.editTask') : $t('recurringTasks.newRecurringTask') }}
            </h3>
            <button @click="showModal = false" class="text-gray-400 hover:text-white">
              <XMarkIcon class="w-6 h-6" />
            </button>
          </div>

          <div class="p-6 space-y-4">
            <!-- Title -->
            <div>
              <label class="label">{{ $t('recurringTasks.labelTitle') }} *</label>
              <input v-model="form.title" type="text" class="input" :placeholder="$t('recurringTasks.titlePlaceholder')" />
            </div>

            <!-- Description -->
            <div>
              <label class="label">{{ $t('recurringTasks.labelDescription') }}</label>
              <textarea v-model="form.description" class="input" rows="2" :placeholder="$t('recurringTasks.optional')" />
            </div>

            <!-- Frequency -->
            <div>
              <label class="label">{{ $t('recurringTasks.labelFrequency') }} *</label>
              <select v-model="form.frequency" class="input">
                <option value="daily">{{ $t('recurringTasks.daily') }}</option>
                <option value="weekly">{{ $t('recurringTasks.weekly') }}</option>
                <option value="biweekly">{{ $t('recurringTasks.biweekly') }}</option>
                <option value="monthly">{{ $t('recurringTasks.monthly') }}</option>
                <option value="yearly">{{ $t('recurringTasks.yearly') }}</option>
                <option value="custom">{{ $t('recurringTasks.custom') }}</option>
              </select>
            </div>

            <!-- Custom Interval -->
            <div v-if="form.frequency === 'custom'">
              <label class="label">{{ $t('recurringTasks.everyXDays') }}</label>
              <input v-model.number="form.interval_value" type="number" min="1" class="input w-32" />
            </div>

            <!-- Days of Week (for weekly) -->
            <div v-if="form.frequency === 'weekly' || form.frequency === 'biweekly'">
              <label class="label">{{ $t('recurringTasks.weekdays') }}</label>
              <div class="flex flex-wrap gap-2">
                <button
                  v-for="day in daysOfWeek"
                  :key="day.value"
                  @click="toggleDayOfWeek(day.value)"
                  class="w-10 h-10 rounded-lg text-sm font-medium transition-colors"
                  :class="form.days_of_week.includes(day.value)
                    ? 'bg-primary-600 text-white'
                    : 'bg-white/[0.04] text-gray-400 hover:bg-white/[0.06]'"
                >
                  {{ day.label }}
                </button>
              </div>
            </div>

            <!-- Day of Month (for monthly) -->
            <div v-if="form.frequency === 'monthly'">
              <label class="label">{{ $t('recurringTasks.dayOfMonth') }}</label>
              <input v-model="form.day_of_month" type="text" class="input w-32" :placeholder="$t('recurringTasks.dayOfMonthPlaceholder')" />
            </div>

            <!-- Date Range -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="label">{{ $t('recurringTasks.startDate') }} *</label>
                <input v-model="form.start_date" type="date" class="input" />
              </div>
              <div>
                <label class="label">{{ $t('recurringTasks.endDate') }}</label>
                <input v-model="form.end_date" type="date" class="input" />
              </div>
            </div>

            <!-- Target Type -->
            <div>
              <label class="label">{{ $t('recurringTasks.targetType') }}</label>
              <select v-model="form.target_type" class="input">
                <option value="list">{{ $t('recurringTasks.targetList') }}</option>
                <option value="checklist">{{ $t('recurringTasks.targetChecklist') }}</option>
                <option value="kanban">{{ $t('recurringTasks.targetKanban') }}</option>
                <option value="project">{{ $t('recurringTasks.targetProject') }}</option>
              </select>
            </div>
          </div>

          <div class="sticky bottom-0 bg-dark-900/95 backdrop-blur-xl px-6 py-4 border-t border-white/[0.06] flex justify-end gap-3">
            <button @click="showModal = false" class="btn-secondary">{{ $t('common.cancel') }}</button>
            <button @click="saveTask" class="btn-primary">
              <CheckIcon class="w-5 h-5 mr-2" />
              {{ isEditing ? $t('common.save') : $t('common.create') }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Instances Modal -->
    <Teleport to="body">
      <div v-if="showInstancesModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-md" @click="showInstancesModal = false" />
        <div class="relative modal max-w-lg w-full max-h-[90vh] overflow-y-auto">
          <div class="sticky top-0 bg-dark-900/95 backdrop-blur-xl px-6 py-4 border-b border-white/[0.06] flex items-center justify-between">
            <h3 class="text-lg font-semibold text-white">{{ $t('recurringTasks.executionHistory') }}</h3>
            <button @click="showInstancesModal = false" class="text-gray-400 hover:text-white">
              <XMarkIcon class="w-6 h-6" />
            </button>
          </div>

          <div class="p-6">
            <div v-if="isLoadingInstances" class="text-center py-8">
              <ArrowPathIcon class="w-8 h-8 animate-spin text-primary-500 mx-auto" />
            </div>

            <div v-else-if="instances.length === 0" class="text-center py-8 text-gray-400">
              <ClockIcon class="w-12 h-12 mx-auto mb-2 opacity-50" />
              <p>{{ $t('recurringTasks.noExecutions') }}</p>
            </div>

            <div v-else class="space-y-3">
              <div
                v-for="instance in instances"
                :key="instance.id"
                class="bg-white/[0.03] rounded-xl p-3 flex items-center justify-between"
              >
                <div>
                  <p class="text-white text-sm">{{ formatDateTime(instance.created_at) }}</p>
                  <p class="text-gray-500 text-xs">
                    {{ store.targetTypeLabels[instance.created_item_type] || instance.created_item_type }}
                  </p>
                </div>
                <span class="text-green-400 text-xs">{{ $t('recurringTasks.created') }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
