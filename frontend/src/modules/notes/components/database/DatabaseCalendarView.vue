<script setup>
import { ref, computed, watch } from 'vue'
import { ChevronLeftIcon, ChevronRightIcon, PlusIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  rows: {
    type: Array,
    default: () => [],
  },
  properties: {
    type: Array,
    default: () => [],
  },
  dateProperty: {
    type: String,
    default: null,
  },
})

const emit = defineEmits(['select-row', 'add-row', 'change-date-property'])

// Calendar state
const currentDate = ref(new Date())
const selectedDate = ref(null)

// Get title property
const titleProperty = computed(() => {
  return props.properties.find(p => p.type === 'title') || props.properties[0]
})

// Get date properties for selection
const dateProperties = computed(() => {
  return props.properties.filter(p => p.type === 'date')
})

// Active date property
const activeDateProperty = computed(() => {
  if (props.dateProperty) {
    return props.properties.find(p => p.id === props.dateProperty)
  }
  return dateProperties.value[0]
})

// Current month/year
const currentMonth = computed(() => currentDate.value.getMonth())
const currentYear = computed(() => currentDate.value.getFullYear())

// Month name
const monthName = computed(() => {
  return currentDate.value.toLocaleDateString('de-DE', { month: 'long', year: 'numeric' })
})

// Days of week
const weekDays = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So']

// Calendar days
const calendarDays = computed(() => {
  const year = currentYear.value
  const month = currentMonth.value

  // First day of month
  const firstDay = new Date(year, month, 1)
  // Last day of month
  const lastDay = new Date(year, month + 1, 0)

  // Get day of week (0 = Sunday, adjust to Monday start)
  let startDay = firstDay.getDay() - 1
  if (startDay < 0) startDay = 6

  const days = []

  // Previous month days
  const prevMonthLastDay = new Date(year, month, 0).getDate()
  for (let i = startDay - 1; i >= 0; i--) {
    days.push({
      date: new Date(year, month - 1, prevMonthLastDay - i),
      isCurrentMonth: false,
      isToday: false,
    })
  }

  // Current month days
  const today = new Date()
  for (let i = 1; i <= lastDay.getDate(); i++) {
    const date = new Date(year, month, i)
    days.push({
      date,
      isCurrentMonth: true,
      isToday:
        date.getDate() === today.getDate() &&
        date.getMonth() === today.getMonth() &&
        date.getFullYear() === today.getFullYear(),
    })
  }

  // Next month days (fill to 42 for 6 rows)
  const remaining = 42 - days.length
  for (let i = 1; i <= remaining; i++) {
    days.push({
      date: new Date(year, month + 1, i),
      isCurrentMonth: false,
      isToday: false,
    })
  }

  return days
})

// Get rows for a specific date
function getRowsForDate(date) {
  if (!activeDateProperty.value) return []

  return props.rows.filter(row => {
    const rowDate = row.values?.[activeDateProperty.value.id]
    if (!rowDate) return false

    const rd = new Date(rowDate)
    return (
      rd.getDate() === date.getDate() &&
      rd.getMonth() === date.getMonth() &&
      rd.getFullYear() === date.getFullYear()
    )
  })
}

// Navigation
function previousMonth() {
  currentDate.value = new Date(currentYear.value, currentMonth.value - 1, 1)
}

function nextMonth() {
  currentDate.value = new Date(currentYear.value, currentMonth.value + 1, 1)
}

function goToToday() {
  currentDate.value = new Date()
}

// Select date
function selectDate(day) {
  selectedDate.value = day.date
}

// Add row at date
function addRowAtDate(date) {
  emit('add-row', {
    datePropertyId: activeDateProperty.value?.id,
    date: date.toISOString(),
  })
}

// Get row display value
function getRowTitle(row) {
  if (!titleProperty.value) return 'Untitled'
  return row.values?.[titleProperty.value.id] || 'Untitled'
}

// Get row color (based on status or random)
function getRowColor(row, index) {
  const colors = [
    'bg-blue-500',
    'bg-green-500',
    'bg-purple-500',
    'bg-yellow-500',
    'bg-pink-500',
    'bg-cyan-500',
    'bg-orange-500',
  ]
  return colors[index % colors.length]
}

// Format date for display
function formatDate(date) {
  return date.getDate()
}
</script>

<template>
  <div class="database-calendar-view bg-white/[0.04] rounded-xl overflow-hidden">
    <!-- Header -->
    <div class="flex items-center justify-between p-4 border-b border-white/[0.06]">
      <div class="flex items-center gap-2">
        <h3 class="text-lg font-medium text-white">{{ monthName }}</h3>
        <button
          @click="goToToday"
          class="px-2 py-1 text-xs text-gray-400 hover:text-white bg-white/[0.04] hover:bg-white/[0.04] rounded transition-colors"
        >
          Heute
        </button>
      </div>

      <div class="flex items-center gap-2">
        <!-- Date property selector -->
        <select
          v-if="dateProperties.length > 1"
          :value="activeDateProperty?.id"
          @change="emit('change-date-property', $event.target.value)"
          class="select"
        >
          <option v-for="prop in dateProperties" :key="prop.id" :value="prop.id">
            {{ prop.name }}
          </option>
        </select>

        <!-- Navigation -->
        <div class="flex items-center gap-1">
          <button
            @click="previousMonth"
            class="p-1 text-gray-400 hover:text-white hover:bg-white/[0.04] rounded transition-colors"
          >
            <ChevronLeftIcon class="w-5 h-5" />
          </button>
          <button
            @click="nextMonth"
            class="p-1 text-gray-400 hover:text-white hover:bg-white/[0.04] rounded transition-colors"
          >
            <ChevronRightIcon class="w-5 h-5" />
          </button>
        </div>
      </div>
    </div>

    <!-- No date property warning -->
    <div v-if="!activeDateProperty" class="p-8 text-center text-gray-500">
      <p class="mb-2">Kein Datumsfeld vorhanden</p>
      <p class="text-sm">Füge ein Datumsfeld hinzu, um die Kalenderansicht zu nutzen.</p>
    </div>

    <!-- Calendar Grid -->
    <div v-else class="p-2">
      <!-- Weekday headers -->
      <div class="grid grid-cols-7 gap-1 mb-1">
        <div
          v-for="day in weekDays"
          :key="day"
          class="text-center text-xs font-medium text-gray-500 py-2"
        >
          {{ day }}
        </div>
      </div>

      <!-- Days grid -->
      <div class="grid grid-cols-7 gap-1">
        <div
          v-for="(day, index) in calendarDays"
          :key="index"
          @click="selectDate(day)"
          :class="[
            'min-h-[100px] p-1 rounded-lg border transition-colors cursor-pointer',
            day.isCurrentMonth ? 'bg-white/[0.04] border-white/[0.06]' : 'bg-white/[0.02] border-transparent',
            day.isToday ? 'ring-2 ring-primary-500' : '',
            selectedDate?.getTime() === day.date.getTime() ? 'bg-white/[0.08]' : 'hover:bg-white/[0.04]',
          ]"
        >
          <!-- Date number -->
          <div class="flex items-center justify-between mb-1">
            <span
              :class="[
                'text-sm font-medium',
                day.isCurrentMonth ? 'text-gray-300' : 'text-gray-600',
                day.isToday ? 'text-primary-400' : '',
              ]"
            >
              {{ formatDate(day.date) }}
            </span>
            <button
              v-if="day.isCurrentMonth"
              @click.stop="addRowAtDate(day.date)"
              class="p-0.5 text-gray-500 hover:text-white opacity-0 group-hover:opacity-100 transition-opacity"
            >
              <PlusIcon class="w-3 h-3" />
            </button>
          </div>

          <!-- Events/Rows -->
          <div class="space-y-0.5">
            <div
              v-for="(row, rowIndex) in getRowsForDate(day.date).slice(0, 3)"
              :key="row.id"
              @click.stop="emit('select-row', row)"
              :class="[
                'px-1 py-0.5 rounded text-xs truncate cursor-pointer',
                getRowColor(row, rowIndex),
                'text-white hover:opacity-80'
              ]"
              :title="getRowTitle(row)"
            >
              {{ getRowTitle(row) }}
            </div>
            <div
              v-if="getRowsForDate(day.date).length > 3"
              class="text-xs text-gray-500 px-1"
            >
              +{{ getRowsForDate(day.date).length - 3 }} mehr
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.database-calendar-view {
  /* Group hover for add button */
}

.database-calendar-view > div > div > div:hover button {
  opacity: 1;
}
</style>
