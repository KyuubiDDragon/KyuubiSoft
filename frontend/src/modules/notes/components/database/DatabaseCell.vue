<script setup>
import { ref, computed, watch, nextTick, onMounted } from 'vue'
import { useDatabaseStore } from '../../stores/databaseStore'
import { CheckIcon, XMarkIcon, CalendarIcon, LinkIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  value: {
    type: [String, Number, Boolean, Array, Object],
    default: null
  },
  property: {
    type: Object,
    required: true
  },
  isEditing: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['edit', 'update', 'cancel'])

const databaseStore = useDatabaseStore()
const inputRef = ref(null)
const localValue = ref(props.value)

// Select options from property config
const selectOptions = computed(() => {
  return props.property.config?.options || []
})

// Format display value based on type
const displayValue = computed(() => {
  const val = props.value

  switch (props.property.type) {
    case 'text':
    case 'url':
    case 'email':
    case 'phone':
      return val || ''

    case 'number':
      if (val === null || val === undefined) return ''
      const config = props.property.config || {}
      if (config.format === 'currency') {
        return new Intl.NumberFormat('de-DE', {
          style: 'currency',
          currency: 'EUR'
        }).format(val)
      }
      if (config.format === 'percent') {
        return `${val}%`
      }
      return val.toString()

    case 'checkbox':
      return val ? true : false

    case 'date':
      if (!val || !val.start) return ''
      const date = new Date(val.start)
      const formatted = date.toLocaleDateString('de-DE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
      })
      if (val.end) {
        const endDate = new Date(val.end)
        return `${formatted} - ${endDate.toLocaleDateString('de-DE', {
          day: '2-digit',
          month: '2-digit',
          year: 'numeric'
        })}`
      }
      return formatted

    case 'select':
      const option = selectOptions.value.find(o => o.id === val || o.name === val)
      return option ? option.name : val || ''

    case 'multi_select':
      if (!Array.isArray(val) || val.length === 0) return []
      return val.map(v => {
        const opt = selectOptions.value.find(o => o.id === v || o.name === v)
        return opt || { name: v, color: 'gray' }
      })

    case 'created_time':
    case 'updated_time':
      if (!val) return ''
      return new Date(val).toLocaleString('de-DE')

    default:
      return val || ''
  }
})

// Watch for value changes
watch(() => props.value, (newVal) => {
  localValue.value = newVal
})

// Focus input when editing starts
watch(() => props.isEditing, async (editing) => {
  if (editing) {
    localValue.value = props.value
    await nextTick()
    inputRef.value?.focus()
    inputRef.value?.select?.()
  }
})

// Handle save
function save() {
  emit('update', localValue.value)
}

// Handle key events
function handleKeydown(e) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault()
    save()
  }
  if (e.key === 'Escape') {
    emit('cancel')
  }
}

// Toggle checkbox
function toggleCheckbox() {
  emit('update', !props.value)
}

// Select option
function selectOption(option) {
  emit('update', option.id || option.name)
}

// Toggle multi-select option
function toggleMultiSelectOption(option) {
  const current = Array.isArray(props.value) ? [...props.value] : []
  const optionId = option.id || option.name
  const index = current.indexOf(optionId)

  if (index === -1) {
    current.push(optionId)
  } else {
    current.splice(index, 1)
  }

  emit('update', current)
}

// Check if multi-select option is selected
function isMultiSelectSelected(option) {
  if (!Array.isArray(props.value)) return false
  return props.value.includes(option.id) || props.value.includes(option.name)
}

// Update date
function updateDate(dateStr) {
  if (!dateStr) {
    emit('update', { start: null, end: null })
  } else {
    emit('update', { start: dateStr, end: props.value?.end || null })
  }
}
</script>

<template>
  <div
    class="database-cell min-h-[32px] flex items-center cursor-pointer"
    @click="!isEditing && $emit('edit')"
  >
    <!-- Checkbox -->
    <template v-if="property.type === 'checkbox'">
      <button
        @click.stop="toggleCheckbox"
        :class="[
          'w-5 h-5 rounded border flex items-center justify-center transition-colors',
          value ? 'bg-primary-600 border-primary-600' : 'border-dark-500 hover:border-primary-500'
        ]"
      >
        <CheckIcon v-if="value" class="w-3 h-3 text-white" />
      </button>
    </template>

    <!-- Text, URL, Email, Phone -->
    <template v-else-if="['text', 'url', 'email', 'phone'].includes(property.type)">
      <div v-if="!isEditing" class="truncate text-sm text-gray-300 w-full">
        <a
          v-if="property.type === 'url' && displayValue"
          :href="displayValue"
          target="_blank"
          class="text-primary-400 hover:underline flex items-center gap-1"
          @click.stop
        >
          <LinkIcon class="w-3 h-3" />
          {{ displayValue }}
        </a>
        <a
          v-else-if="property.type === 'email' && displayValue"
          :href="`mailto:${displayValue}`"
          class="text-primary-400 hover:underline"
          @click.stop
        >
          {{ displayValue }}
        </a>
        <span v-else>{{ displayValue || '—' }}</span>
      </div>
      <input
        v-else
        ref="inputRef"
        v-model="localValue"
        :type="property.type === 'email' ? 'email' : property.type === 'url' ? 'url' : 'text'"
        class="database-cell-editor w-full bg-dark-700 border border-primary-500 rounded px-2 py-1 text-sm text-white focus:outline-none"
        @keydown="handleKeydown"
        @blur="save"
      />
    </template>

    <!-- Number -->
    <template v-else-if="property.type === 'number'">
      <div v-if="!isEditing" class="text-sm text-gray-300">
        {{ displayValue || '—' }}
      </div>
      <input
        v-else
        ref="inputRef"
        v-model.number="localValue"
        type="number"
        step="any"
        class="database-cell-editor w-full bg-dark-700 border border-primary-500 rounded px-2 py-1 text-sm text-white focus:outline-none"
        @keydown="handleKeydown"
        @blur="save"
      />
    </template>

    <!-- Date -->
    <template v-else-if="property.type === 'date'">
      <div v-if="!isEditing" class="text-sm text-gray-300 flex items-center gap-1">
        <CalendarIcon v-if="displayValue" class="w-3 h-3 text-gray-500" />
        {{ displayValue || '—' }}
      </div>
      <input
        v-else
        ref="inputRef"
        :value="value?.start?.split('T')[0] || ''"
        type="date"
        class="database-cell-editor w-full bg-dark-700 border border-primary-500 rounded px-2 py-1 text-sm text-white focus:outline-none"
        @change="updateDate($event.target.value)"
        @keydown="handleKeydown"
      />
    </template>

    <!-- Select -->
    <template v-else-if="property.type === 'select'">
      <div v-if="!isEditing" class="w-full">
        <span
          v-if="displayValue"
          :class="[
            'inline-block px-2 py-0.5 rounded text-xs',
            databaseStore.getColorClass(selectOptions.find(o => o.name === displayValue)?.color || 'gray')
          ]"
        >
          {{ displayValue }}
        </span>
        <span v-else class="text-gray-500 text-sm">—</span>
      </div>
      <div v-else class="database-cell-editor absolute z-10 mt-1 w-48 bg-dark-700 border border-dark-600 rounded-lg shadow-xl overflow-hidden">
        <div class="max-h-48 overflow-y-auto p-1">
          <button
            v-for="option in selectOptions"
            :key="option.id"
            @click="selectOption(option)"
            :class="[
              'w-full flex items-center gap-2 px-2 py-1.5 rounded text-sm text-left',
              (value === option.id || value === option.name) ? 'bg-primary-600/20' : 'hover:bg-dark-600'
            ]"
          >
            <span :class="['w-3 h-3 rounded-full', `bg-${option.color}-500`]"></span>
            <span class="text-gray-300">{{ option.name }}</span>
          </button>
          <div v-if="selectOptions.length === 0" class="px-2 py-2 text-sm text-gray-500">
            Keine Optionen
          </div>
        </div>
      </div>
    </template>

    <!-- Multi-Select -->
    <template v-else-if="property.type === 'multi_select'">
      <div v-if="!isEditing" class="flex flex-wrap gap-1 w-full">
        <span
          v-for="opt in displayValue"
          :key="opt.name"
          :class="[
            'inline-block px-2 py-0.5 rounded text-xs',
            databaseStore.getColorClass(opt.color || 'gray')
          ]"
        >
          {{ opt.name }}
        </span>
        <span v-if="displayValue.length === 0" class="text-gray-500 text-sm">—</span>
      </div>
      <div v-else class="database-cell-editor absolute z-10 mt-1 w-48 bg-dark-700 border border-dark-600 rounded-lg shadow-xl overflow-hidden">
        <div class="max-h-48 overflow-y-auto p-1">
          <button
            v-for="option in selectOptions"
            :key="option.id"
            @click.stop="toggleMultiSelectOption(option)"
            :class="[
              'w-full flex items-center gap-2 px-2 py-1.5 rounded text-sm text-left',
              isMultiSelectSelected(option) ? 'bg-primary-600/20' : 'hover:bg-dark-600'
            ]"
          >
            <span :class="[
              'w-4 h-4 rounded border flex items-center justify-center',
              isMultiSelectSelected(option) ? 'bg-primary-600 border-primary-600' : 'border-dark-500'
            ]">
              <CheckIcon v-if="isMultiSelectSelected(option)" class="w-3 h-3 text-white" />
            </span>
            <span :class="['w-3 h-3 rounded-full', `bg-${option.color}-500`]"></span>
            <span class="text-gray-300">{{ option.name }}</span>
          </button>
        </div>
      </div>
    </template>

    <!-- Created/Updated Time -->
    <template v-else-if="['created_time', 'updated_time'].includes(property.type)">
      <div class="text-sm text-gray-500">
        {{ displayValue || '—' }}
      </div>
    </template>

    <!-- Fallback -->
    <template v-else>
      <div class="text-sm text-gray-300">
        {{ displayValue || '—' }}
      </div>
    </template>
  </div>
</template>

<style scoped>
.database-cell {
  position: relative;
}

.database-cell-editor {
  position: relative;
}
</style>
