<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import api from '@/core/api/axios'
import {
  ChevronLeftIcon,
  ChevronRightIcon,
  PlusIcon,
  XMarkIcon,
  CalendarIcon,
  ViewColumnsIcon,
  ListBulletIcon,
  ClockIcon,
} from '@heroicons/vue/24/outline'

const api = useApi()

const currentDate = ref(new Date())
const events = ref([])
const isLoading = ref(true)
const showEventModal = ref(false)
const selectedEvent = ref(null)
const viewMode = ref('month') // month, week, list

// Event form
const eventForm = ref({
  title: '',
  description: '',
  start_date: '',
  end_date: '',
  all_day: true,
  color: 'primary',
})

// Source filters
const sourceFilters = ref({
  events: true,
  kanban: true,
  tasks: true,
  time: false,
})

const colors = [
  { value: 'primary', class: 'bg-primary-500' },
  { value: 'red', class: 'bg-red-500' },
  { value: 'yellow', class: 'bg-yellow-500' },
  { value: 'green', class: 'bg-green-500' },
  { value: 'blue', class: 'bg-blue-500' },
  { value: 'purple', class: 'bg-purple-500' },
]

// Calendar calculations
const currentMonth = computed(() => currentDate.value.getMonth())
const currentYear = computed(() => currentDate.value.getFullYear())

const monthName = computed(() => {
  return currentDate.value.toLocaleDateString('de-DE', { month: 'long', year: 'numeric' })
})

const daysInMonth = computed(() => {
  const year = currentYear.value
  const month = currentMonth.value
  const firstDay = new Date(year, month, 1)
  const lastDay = new Date(year, month + 1, 0)

  const days = []

  // Add days from previous month
  const startDayOfWeek = (firstDay.getDay() + 6) % 7 // Monday = 0
  for (let i = startDayOfWeek - 1; i >= 0; i--) {
    const date = new Date(year, month, -i)
    days.push({
      date,
      isCurrentMonth: false,
      isToday: isSameDay(date, new Date()),
    })
  }

  // Add days of current month
  for (let day = 1; day <= lastDay.getDate(); day++) {
    const date = new Date(year, month, day)
    days.push({
      date,
      isCurrentMonth: true,
      isToday: isSameDay(date, new Date()),
    })
  }

  // Add days from next month
  const remaining = 42 - days.length // 6 weeks
  for (let i = 1; i <= remaining; i++) {
    const date = new Date(year, month + 1, i)
    days.push({
      date,
      isCurrentMonth: false,
      isToday: isSameDay(date, new Date()),
    })
  }

  return days
})

function isSameDay(date1, date2) {
  return date1.getDate() === date2.getDate() &&
         date1.getMonth() === date2.getMonth() &&
         date1.getFullYear() === date2.getFullYear()
}

function getEventsForDay(date) {
  return events.value.filter(event => {
    const eventDate = new Date(event.start_date)
    return isSameDay(eventDate, date)
  })
}

function previousMonth() {
  currentDate.value = new Date(currentYear.value, currentMonth.value - 1, 1)
}

function nextMonth() {
  currentDate.value = new Date(currentYear.value, currentMonth.value + 1, 1)
}

function goToToday() {
  currentDate.value = new Date()
}

async function fetchEvents() {
  isLoading.value = true
  try {
    const startOfMonth = new Date(currentYear.value, currentMonth.value, 1)
    const endOfMonth = new Date(currentYear.value, currentMonth.value + 1, 0)

    // Add buffer for previous/next month days shown
    startOfMonth.setDate(startOfMonth.getDate() - 7)
    endOfMonth.setDate(endOfMonth.getDate() + 7)

    const activeSources = Object.entries(sourceFilters.value)
      .filter(([_, active]) => active)
      .map(([source]) => source)

    const response = await api.get('/api/v1/calendar/events', {
      params: {
        start: startOfMonth.toISOString().split('T')[0],
        end: endOfMonth.toISOString().split('T')[0],
        sources: activeSources.join(','),
      }
    })
    events.value = response.data.data
  } catch (error) {
    console.error('Failed to fetch events:', error)
  } finally {
    isLoading.value = false
  }
}

async function createEvent() {
  try {
    await api.post('/api/v1/calendar/events', eventForm.value)
    showEventModal.value = false
    resetForm()
    fetchEvents()
  } catch (error) {
    console.error('Failed to create event:', error)
  }
}

async function updateEvent() {
  if (!selectedEvent.value) return
  try {
    await api.put(`/api/v1/calendar/events/${selectedEvent.value.id}`, eventForm.value)
    showEventModal.value = false
    resetForm()
    fetchEvents()
  } catch (error) {
    console.error('Failed to update event:', error)
  }
}

async function deleteEvent() {
  if (!selectedEvent.value) return
  try {
    await api.delete(`/api/v1/calendar/events/${selectedEvent.value.id}`)
    showEventModal.value = false
    resetForm()
    fetchEvents()
  } catch (error) {
    console.error('Failed to delete event:', error)
  }
}

function openNewEvent(date = new Date()) {
  selectedEvent.value = null
  eventForm.value = {
    title: '',
    description: '',
    start_date: date.toISOString().slice(0, 16),
    end_date: '',
    all_day: true,
    color: 'primary',
  }
  showEventModal.value = true
}

function openEvent(event) {
  if (event.source_type !== 'event') {
    // Can't edit kanban/task events
    return
  }
  selectedEvent.value = event
  eventForm.value = {
    title: event.title,
    description: event.description || '',
    start_date: event.start_date?.slice(0, 16) || '',
    end_date: event.end_date?.slice(0, 16) || '',
    all_day: event.all_day,
    color: event.color || 'primary',
  }
  showEventModal.value = true
}

function resetForm() {
  selectedEvent.value = null
  eventForm.value = {
    title: '',
    description: '',
    start_date: '',
    end_date: '',
    all_day: true,
    color: 'primary',
  }
}

function getEventColor(event) {
  if (event.color && event.color.startsWith('#')) {
    return event.color
  }
  const colorMap = {
    primary: '#8B5CF6',
    red: '#EF4444',
    orange: '#F97316',
    yellow: '#EAB308',
    green: '#22C55E',
    blue: '#3B82F6',
    purple: '#A855F7',
  }
  return colorMap[event.color] || colorMap.primary
}

function getSourceIcon(sourceType) {
  switch (sourceType) {
    case 'kanban': return ViewColumnsIcon
    case 'task': return ListBulletIcon
    case 'time': return ClockIcon
    default: return CalendarIcon
  }
}

function formatTime(dateStr) {
  if (!dateStr) return ''
  const date = new Date(dateStr)
  return date.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' })
}

// Watch for month changes
watch([currentMonth, currentYear], () => {
  fetchEvents()
})

// Watch for filter changes
watch(sourceFilters, () => {
  fetchEvents()
}, { deep: true })

onMounted(() => {
  fetchEvents()
})

const weekDays = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So']
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-white">Kalender</h1>
        <p class="text-gray-400 mt-1">Termine, Deadlines und Zeiterfassung im Überblick</p>
      </div>
      <button @click="openNewEvent()" class="btn-primary">
        <PlusIcon class="w-4 h-4 mr-2" />
        Neuer Termin
      </button>
    </div>

    <!-- Filters & Navigation -->
    <div class="card p-4">
      <div class="flex items-center justify-between flex-wrap gap-4">
        <!-- Month Navigation -->
        <div class="flex items-center gap-4">
          <button
            @click="previousMonth"
            class="p-2 text-gray-400 hover:text-white hover:bg-dark-700 rounded-lg transition-colors"
          >
            <ChevronLeftIcon class="w-5 h-5" />
          </button>
          <h2 class="text-lg font-semibold text-white min-w-40 text-center">{{ monthName }}</h2>
          <button
            @click="nextMonth"
            class="p-2 text-gray-400 hover:text-white hover:bg-dark-700 rounded-lg transition-colors"
          >
            <ChevronRightIcon class="w-5 h-5" />
          </button>
          <button
            @click="goToToday"
            class="px-3 py-1.5 text-sm text-gray-400 hover:text-white hover:bg-dark-700 rounded-lg transition-colors"
          >
            Heute
          </button>
        </div>

        <!-- Source Filters -->
        <div class="flex items-center gap-2">
          <span class="text-sm text-gray-500">Quellen:</span>
          <label class="flex items-center gap-1.5 px-2 py-1 rounded-lg cursor-pointer" :class="sourceFilters.events ? 'bg-purple-500/20 text-purple-400' : 'text-gray-500'">
            <input type="checkbox" v-model="sourceFilters.events" class="hidden" />
            <CalendarIcon class="w-4 h-4" />
            <span class="text-xs">Events</span>
          </label>
          <label class="flex items-center gap-1.5 px-2 py-1 rounded-lg cursor-pointer" :class="sourceFilters.kanban ? 'bg-orange-500/20 text-orange-400' : 'text-gray-500'">
            <input type="checkbox" v-model="sourceFilters.kanban" class="hidden" />
            <ViewColumnsIcon class="w-4 h-4" />
            <span class="text-xs">Kanban</span>
          </label>
          <label class="flex items-center gap-1.5 px-2 py-1 rounded-lg cursor-pointer" :class="sourceFilters.tasks ? 'bg-blue-500/20 text-blue-400' : 'text-gray-500'">
            <input type="checkbox" v-model="sourceFilters.tasks" class="hidden" />
            <ListBulletIcon class="w-4 h-4" />
            <span class="text-xs">Aufgaben</span>
          </label>
          <label class="flex items-center gap-1.5 px-2 py-1 rounded-lg cursor-pointer" :class="sourceFilters.time ? 'bg-green-500/20 text-green-400' : 'text-gray-500'">
            <input type="checkbox" v-model="sourceFilters.time" class="hidden" />
            <ClockIcon class="w-4 h-4" />
            <span class="text-xs">Zeit</span>
          </label>
        </div>
      </div>
    </div>

    <!-- Calendar Grid -->
    <div class="card overflow-hidden">
      <!-- Week days header -->
      <div class="grid grid-cols-7 bg-dark-700">
        <div
          v-for="day in weekDays"
          :key="day"
          class="py-3 text-center text-sm font-medium text-gray-400"
        >
          {{ day }}
        </div>
      </div>

      <!-- Days grid -->
      <div class="grid grid-cols-7">
        <div
          v-for="(day, index) in daysInMonth"
          :key="index"
          @click="openNewEvent(day.date)"
          class="min-h-28 p-2 border-t border-l border-dark-700 cursor-pointer hover:bg-dark-700/50 transition-colors"
          :class="{
            'bg-dark-800/50': !day.isCurrentMonth,
            'border-l-0': index % 7 === 0,
          }"
        >
          <!-- Day number -->
          <div
            class="w-7 h-7 rounded-full flex items-center justify-center text-sm mb-1"
            :class="{
              'bg-primary-500 text-white': day.isToday,
              'text-white': day.isCurrentMonth && !day.isToday,
              'text-gray-600': !day.isCurrentMonth,
            }"
          >
            {{ day.date.getDate() }}
          </div>

          <!-- Events -->
          <div class="space-y-1">
            <div
              v-for="event in getEventsForDay(day.date).slice(0, 3)"
              :key="event.id"
              @click.stop="openEvent(event)"
              class="text-xs px-1.5 py-0.5 rounded truncate flex items-center gap-1"
              :style="{ backgroundColor: getEventColor(event) + '30', color: getEventColor(event) }"
            >
              <component :is="getSourceIcon(event.source_type)" class="w-3 h-3 flex-shrink-0" />
              <span class="truncate">{{ event.title }}</span>
            </div>
            <div
              v-if="getEventsForDay(day.date).length > 3"
              class="text-xs text-gray-500 px-1.5"
            >
              +{{ getEventsForDay(day.date).length - 3 }} mehr
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Event Modal -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition ease-out duration-200"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition ease-in duration-150"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="showEventModal"
          class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
          @click.self="showEventModal = false"
        >
          <div class="bg-dark-800 border border-dark-700 rounded-xl shadow-2xl w-full max-w-md">
            <div class="flex items-center justify-between px-6 py-4 border-b border-dark-700">
              <h3 class="text-lg font-semibold text-white">
                {{ selectedEvent ? 'Termin bearbeiten' : 'Neuer Termin' }}
              </h3>
              <button @click="showEventModal = false" class="text-gray-400 hover:text-white">
                <XMarkIcon class="w-5 h-5" />
              </button>
            </div>

            <form @submit.prevent="selectedEvent ? updateEvent() : createEvent()" class="p-6 space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-400 mb-1">Titel</label>
                <input
                  v-model="eventForm.title"
                  type="text"
                  required
                  class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white focus:outline-none focus:border-primary-500"
                  placeholder="Terminname"
                />
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-400 mb-1">Beschreibung</label>
                <textarea
                  v-model="eventForm.description"
                  rows="2"
                  class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white focus:outline-none focus:border-primary-500 resize-none"
                  placeholder="Optional"
                ></textarea>
              </div>

              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-400 mb-1">Start</label>
                  <input
                    v-model="eventForm.start_date"
                    :type="eventForm.all_day ? 'date' : 'datetime-local'"
                    required
                    class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white focus:outline-none focus:border-primary-500"
                  />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-400 mb-1">Ende</label>
                  <input
                    v-model="eventForm.end_date"
                    :type="eventForm.all_day ? 'date' : 'datetime-local'"
                    class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white focus:outline-none focus:border-primary-500"
                  />
                </div>
              </div>

              <div class="flex items-center gap-4">
                <label class="flex items-center gap-2 cursor-pointer">
                  <input
                    type="checkbox"
                    v-model="eventForm.all_day"
                    class="w-4 h-4 rounded border-dark-600 bg-dark-700 text-primary-500 focus:ring-primary-500"
                  />
                  <span class="text-sm text-gray-400">Ganztägig</span>
                </label>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-400 mb-2">Farbe</label>
                <div class="flex gap-2">
                  <button
                    v-for="color in colors"
                    :key="color.value"
                    type="button"
                    @click="eventForm.color = color.value"
                    class="w-8 h-8 rounded-full transition-transform hover:scale-110"
                    :class="[color.class, eventForm.color === color.value ? 'ring-2 ring-white ring-offset-2 ring-offset-dark-800' : '']"
                  ></button>
                </div>
              </div>

              <div class="flex justify-between pt-4">
                <button
                  v-if="selectedEvent"
                  type="button"
                  @click="deleteEvent"
                  class="px-4 py-2 text-red-400 hover:bg-red-500/10 rounded-lg transition-colors"
                >
                  Löschen
                </button>
                <div v-else></div>
                <div class="flex gap-2">
                  <button
                    type="button"
                    @click="showEventModal = false"
                    class="btn-secondary"
                  >
                    Abbrechen
                  </button>
                  <button type="submit" class="btn-primary">
                    {{ selectedEvent ? 'Speichern' : 'Erstellen' }}
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>
