<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import api from '@/core/api/axios'
import { useUiStore } from '@/stores/ui'
import { useToast } from '@/composables/useToast'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
import {
  ChevronLeftIcon,
  ChevronRightIcon,
  PlusIcon,
  XMarkIcon,
  CalendarIcon,
  ViewColumnsIcon,
  ListBulletIcon,
  ClockIcon,
  Cog6ToothIcon,
  ArrowPathIcon,
  BellIcon,
  TrashIcon,
  LinkIcon,
  CheckCircleIcon,
  ExclamationCircleIcon,
} from '@heroicons/vue/24/outline'

const uiStore = useUiStore()
const toast = useToast()
const { confirm } = useConfirmDialog()
const currentDate = ref(new Date())
const events = ref([])
const isLoading = ref(true)
const showEventModal = ref(false)
const showSettingsModal = ref(false)
const selectedEvent = ref(null)
const viewMode = ref('month') // month, week, list

// External calendars
const externalCalendars = ref([])
const isLoadingCalendars = ref(false)
const showAddCalendarModal = ref(false)
const calendarForm = ref({
  name: '',
  type: 'ical', // ical, caldav, google, microsoft
  url: '',
  username: '',
  password: '',
  color: 'blue',
})
const isSyncingCalendar = ref(null)

// Event form
const eventForm = ref({
  title: '',
  description: '',
  start_date: '',
  start_time: '09:00',
  end_date: '',
  end_time: '10:00',
  all_day: true,
  color: 'primary',
  recurrence: '',
  reminder_minutes: null,
})

// Recurrence options
const recurrenceOptions = [
  { value: '', label: 'Keine Wiederholung' },
  { value: 'daily', label: 'Täglich' },
  { value: 'weekly', label: 'Wöchentlich' },
  { value: 'biweekly', label: 'Alle 2 Wochen' },
  { value: 'monthly', label: 'Monatlich' },
  { value: 'yearly', label: 'Jährlich' },
]

// Reminder options
const reminderOptions = [
  { value: null, label: 'Keine Erinnerung' },
  { value: 0, label: 'Zum Zeitpunkt' },
  { value: 5, label: '5 Minuten vorher' },
  { value: 15, label: '15 Minuten vorher' },
  { value: 30, label: '30 Minuten vorher' },
  { value: 60, label: '1 Stunde vorher' },
  { value: 120, label: '2 Stunden vorher' },
  { value: 1440, label: '1 Tag vorher' },
  { value: 10080, label: '1 Woche vorher' },
]

// Calendar type options
const calendarTypes = [
  { value: 'ical', label: 'iCal URL', description: 'Öffentliche iCal-URL (z.B. Google, Apple)' },
  { value: 'caldav', label: 'CalDAV', description: 'CalDAV-Server mit Authentifizierung' },
]

// Source filters
const sourceFilters = ref({
  events: true,
  kanban: true,
  tasks: true,
  time: false,
  external: true,
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

function buildEventPayload() {
  const payload = {
    title: eventForm.value.title,
    description: eventForm.value.description,
    all_day: eventForm.value.all_day,
    color: eventForm.value.color,
    recurrence: eventForm.value.recurrence || null,
    reminder_minutes: eventForm.value.reminder_minutes,
  }

  if (eventForm.value.all_day) {
    payload.start_date = eventForm.value.start_date
    payload.end_date = eventForm.value.end_date || eventForm.value.start_date
  } else {
    payload.start_date = `${eventForm.value.start_date}T${eventForm.value.start_time}`
    payload.end_date = eventForm.value.end_date
      ? `${eventForm.value.end_date}T${eventForm.value.end_time}`
      : `${eventForm.value.start_date}T${eventForm.value.end_time}`
  }

  return payload
}

async function createEvent() {
  try {
    const payload = buildEventPayload()
    await api.post('/api/v1/calendar/events', payload)
    showEventModal.value = false
    resetForm()
    fetchEvents()
    uiStore.showSuccess('Termin erstellt')
  } catch (error) {
    console.error('Failed to create event:', error)
    uiStore.showError('Fehler beim Erstellen des Termins')
  }
}

async function updateEvent() {
  if (!selectedEvent.value) return
  try {
    const payload = buildEventPayload()
    await api.put(`/api/v1/calendar/events/${selectedEvent.value.id}`, payload)
    showEventModal.value = false
    resetForm()
    fetchEvents()
    uiStore.showSuccess('Termin aktualisiert')
  } catch (error) {
    console.error('Failed to update event:', error)
    uiStore.showError('Fehler beim Aktualisieren des Termins')
  }
}

async function deleteEvent() {
  if (!selectedEvent.value) return
  try {
    await api.delete(`/api/v1/calendar/events/${selectedEvent.value.id}`)
    showEventModal.value = false
    resetForm()
    fetchEvents()
    uiStore.showSuccess('Termin gelöscht')
  } catch (error) {
    console.error('Failed to delete event:', error)
    uiStore.showError('Fehler beim Löschen des Termins')
  }
}

function formatDateForInput(date) {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

function openNewEvent(date = new Date()) {
  selectedEvent.value = null
  eventForm.value = {
    title: '',
    description: '',
    start_date: formatDateForInput(date),
    start_time: '09:00',
    end_date: '',
    end_time: '10:00',
    all_day: true,
    color: 'primary',
    recurrence: '',
    reminder_minutes: null,
  }
  showEventModal.value = true
}

function openEvent(event) {
  if (event.source_type !== 'event') {
    // Can't edit kanban/task/external events
    return
  }
  selectedEvent.value = event

  // Parse date and time from start_date
  let startDate = ''
  let startTime = '09:00'
  let endDate = ''
  let endTime = '10:00'

  if (event.start_date) {
    const startDateTime = new Date(event.start_date)
    startDate = formatDateForInput(startDateTime)
    if (!event.all_day) {
      startTime = startDateTime.toTimeString().slice(0, 5)
    }
  }

  if (event.end_date) {
    const endDateTime = new Date(event.end_date)
    endDate = formatDateForInput(endDateTime)
    if (!event.all_day) {
      endTime = endDateTime.toTimeString().slice(0, 5)
    }
  }

  eventForm.value = {
    title: event.title,
    description: event.description || '',
    start_date: startDate,
    start_time: startTime,
    end_date: endDate,
    end_time: endTime,
    all_day: event.all_day == 1 || event.all_day === true,
    color: event.color || 'primary',
    recurrence: event.recurrence || '',
    reminder_minutes: event.reminder_minutes ?? null,
  }
  showEventModal.value = true
}

function resetForm() {
  selectedEvent.value = null
  eventForm.value = {
    title: '',
    description: '',
    start_date: '',
    start_time: '09:00',
    end_date: '',
    end_time: '10:00',
    all_day: true,
    color: 'primary',
    recurrence: '',
    reminder_minutes: null,
  }
}

// External calendar functions
async function loadExternalCalendars() {
  isLoadingCalendars.value = true
  try {
    const response = await api.get('/api/v1/calendar/external')
    externalCalendars.value = response.data.data || []
  } catch (error) {
    console.error('Failed to load external calendars:', error)
  } finally {
    isLoadingCalendars.value = false
  }
}

async function addExternalCalendar() {
  try {
    await api.post('/api/v1/calendar/external', calendarForm.value)
    showAddCalendarModal.value = false
    resetCalendarForm()
    await loadExternalCalendars()
    await fetchEvents()
    uiStore.showSuccess('Kalender hinzugefügt')
  } catch (error) {
    console.error('Failed to add calendar:', error)
    uiStore.showError(error.response?.data?.message || 'Fehler beim Hinzufügen des Kalenders')
  }
}

async function deleteExternalCalendar(calendarId) {
  if (!await confirm({ message: 'Kalender wirklich entfernen?', type: 'danger', confirmText: 'Löschen' })) return
  try {
    await api.delete(`/api/v1/calendar/external/${calendarId}`)
    await loadExternalCalendars()
    await fetchEvents()
    uiStore.showSuccess('Kalender entfernt')
  } catch (error) {
    console.error('Failed to delete calendar:', error)
    uiStore.showError('Fehler beim Entfernen des Kalenders')
  }
}

async function syncExternalCalendar(calendarId) {
  isSyncingCalendar.value = calendarId
  try {
    await api.post(`/api/v1/calendar/external/${calendarId}/sync`)
    await fetchEvents()
    uiStore.showSuccess('Kalender synchronisiert')
  } catch (error) {
    console.error('Failed to sync calendar:', error)
    uiStore.showError('Fehler beim Synchronisieren')
  } finally {
    isSyncingCalendar.value = null
  }
}

async function toggleCalendarVisibility(calendar) {
  try {
    await api.put(`/api/v1/calendar/external/${calendar.id}`, {
      is_visible: !calendar.is_visible
    })
    calendar.is_visible = !calendar.is_visible
    await fetchEvents()
  } catch (error) {
    console.error('Failed to toggle calendar:', error)
  }
}

function resetCalendarForm() {
  calendarForm.value = {
    name: '',
    type: 'ical',
    url: '',
    username: '',
    password: '',
    color: 'blue',
  }
}

function openSettingsModal() {
  loadExternalCalendars()
  showSettingsModal.value = true
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
    cyan: '#06B6D4',
  }
  return colorMap[event.color] || colorMap.primary
}

function getSourceIcon(sourceType) {
  switch (sourceType) {
    case 'kanban': return ViewColumnsIcon
    case 'task': return ListBulletIcon
    case 'time': return ClockIcon
    case 'external': return LinkIcon
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
      <div class="flex gap-2">
        <button @click="openSettingsModal" class="btn-secondary">
          <Cog6ToothIcon class="w-4 h-4 mr-2" />
          Einstellungen
        </button>
        <button @click="openNewEvent()" class="btn-primary">
          <PlusIcon class="w-4 h-4 mr-2" />
          Neuer Termin
        </button>
      </div>
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
          <label class="flex items-center gap-1.5 px-2 py-1 rounded-lg cursor-pointer" :class="sourceFilters.external ? 'bg-cyan-500/20 text-cyan-400' : 'text-gray-500'">
            <input type="checkbox" v-model="sourceFilters.external" class="hidden" />
            <LinkIcon class="w-4 h-4" />
            <span class="text-xs">Extern</span>
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

              <!-- All-day toggle -->
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

              <!-- Date inputs -->
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-400 mb-1">Startdatum</label>
                  <input
                    v-model="eventForm.start_date"
                    type="date"
                    required
                    class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white focus:outline-none focus:border-primary-500"
                  />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-400 mb-1">Enddatum</label>
                  <input
                    v-model="eventForm.end_date"
                    type="date"
                    class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white focus:outline-none focus:border-primary-500"
                    :placeholder="eventForm.start_date"
                  />
                </div>
              </div>

              <!-- Time inputs (only if not all-day) -->
              <div v-if="!eventForm.all_day" class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-400 mb-1">Startzeit</label>
                  <input
                    v-model="eventForm.start_time"
                    type="time"
                    required
                    class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white focus:outline-none focus:border-primary-500"
                  />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-400 mb-1">Endzeit</label>
                  <input
                    v-model="eventForm.end_time"
                    type="time"
                    class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white focus:outline-none focus:border-primary-500"
                  />
                </div>
              </div>

              <!-- Recurrence -->
              <div>
                <label class="block text-sm font-medium text-gray-400 mb-1">
                  <ArrowPathIcon class="w-4 h-4 inline mr-1" />
                  Wiederholung
                </label>
                <select
                  v-model="eventForm.recurrence"
                  class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white focus:outline-none focus:border-primary-500"
                >
                  <option v-for="opt in recurrenceOptions" :key="opt.value" :value="opt.value">
                    {{ opt.label }}
                  </option>
                </select>
              </div>

              <!-- Reminder -->
              <div>
                <label class="block text-sm font-medium text-gray-400 mb-1">
                  <BellIcon class="w-4 h-4 inline mr-1" />
                  Erinnerung
                </label>
                <select
                  v-model="eventForm.reminder_minutes"
                  class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white focus:outline-none focus:border-primary-500"
                >
                  <option v-for="opt in reminderOptions" :key="opt.value" :value="opt.value">
                    {{ opt.label }}
                  </option>
                </select>
              </div>

              <!-- Color -->
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

    <!-- Settings Modal -->
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
          v-if="showSettingsModal"
          class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
        >
          <div class="bg-dark-800 border border-dark-700 rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col">
            <div class="flex items-center justify-between px-6 py-4 border-b border-dark-700">
              <h3 class="text-lg font-semibold text-white">Kalender-Einstellungen</h3>
              <button @click="showSettingsModal = false" class="text-gray-400 hover:text-white">
                <XMarkIcon class="w-5 h-5" />
              </button>
            </div>

            <div class="p-6 overflow-y-auto flex-1">
              <!-- External Calendars Section -->
              <div class="space-y-4">
                <div class="flex items-center justify-between">
                  <h4 class="text-md font-medium text-white">Externe Kalender</h4>
                  <button @click="showAddCalendarModal = true" class="btn-primary text-sm py-1.5">
                    <PlusIcon class="w-4 h-4 mr-1" />
                    Kalender hinzufügen
                  </button>
                </div>

                <p class="text-sm text-gray-400">
                  Verbinde externe Kalender (Google, Outlook, Apple, etc.) um deren Termine hier anzuzeigen.
                </p>

                <!-- Loading -->
                <div v-if="isLoadingCalendars" class="flex justify-center py-8">
                  <div class="w-6 h-6 border-2 border-primary-500 border-t-transparent rounded-full animate-spin"></div>
                </div>

                <!-- Empty state -->
                <div v-else-if="externalCalendars.length === 0" class="bg-dark-700/50 rounded-lg p-6 text-center">
                  <CalendarIcon class="w-12 h-12 mx-auto text-gray-600 mb-3" />
                  <p class="text-gray-400">Keine externen Kalender verbunden</p>
                  <p class="text-sm text-gray-500 mt-1">Füge einen iCal-Link oder CalDAV-Server hinzu</p>
                </div>

                <!-- Calendar list -->
                <div v-else class="space-y-3">
                  <div
                    v-for="calendar in externalCalendars"
                    :key="calendar.id"
                    class="bg-dark-700/50 rounded-lg p-4 flex items-center justify-between"
                  >
                    <div class="flex items-center gap-3">
                      <div
                        class="w-4 h-4 rounded-full"
                        :class="`bg-${calendar.color}-500`"
                      ></div>
                      <div>
                        <p class="text-white font-medium">{{ calendar.name }}</p>
                        <p class="text-xs text-gray-500">
                          {{ calendar.type === 'ical' ? 'iCal' : 'CalDAV' }}
                          <span v-if="calendar.last_synced_at" class="ml-2">
                            Zuletzt synchronisiert: {{ new Date(calendar.last_synced_at).toLocaleString('de-DE') }}
                          </span>
                        </p>
                      </div>
                    </div>
                    <div class="flex items-center gap-2">
                      <!-- Visibility toggle -->
                      <button
                        @click="toggleCalendarVisibility(calendar)"
                        class="p-2 rounded-lg transition-colors"
                        :class="calendar.is_visible ? 'text-green-400 hover:bg-green-500/10' : 'text-gray-500 hover:bg-dark-600'"
                        :title="calendar.is_visible ? 'Sichtbar' : 'Ausgeblendet'"
                      >
                        <CheckCircleIcon v-if="calendar.is_visible" class="w-5 h-5" />
                        <ExclamationCircleIcon v-else class="w-5 h-5" />
                      </button>
                      <!-- Sync button -->
                      <button
                        @click="syncExternalCalendar(calendar.id)"
                        class="p-2 text-gray-400 hover:text-white hover:bg-dark-600 rounded-lg transition-colors"
                        :disabled="isSyncingCalendar === calendar.id"
                        title="Synchronisieren"
                      >
                        <ArrowPathIcon
                          class="w-5 h-5"
                          :class="{ 'animate-spin': isSyncingCalendar === calendar.id }"
                        />
                      </button>
                      <!-- Delete button -->
                      <button
                        @click="deleteExternalCalendar(calendar.id)"
                        class="p-2 text-red-400 hover:bg-red-500/10 rounded-lg transition-colors"
                        title="Entfernen"
                      >
                        <TrashIcon class="w-5 h-5" />
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="px-6 py-4 border-t border-dark-700">
              <button @click="showSettingsModal = false" class="btn-secondary w-full">
                Schließen
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Add Calendar Modal -->
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
          v-if="showAddCalendarModal"
          class="fixed inset-0 bg-black/50 backdrop-blur-sm z-[60] flex items-center justify-center p-4"
        >
          <div class="bg-dark-800 border border-dark-700 rounded-xl shadow-2xl w-full max-w-md">
            <div class="flex items-center justify-between px-6 py-4 border-b border-dark-700">
              <h3 class="text-lg font-semibold text-white">Kalender hinzufügen</h3>
              <button @click="showAddCalendarModal = false" class="text-gray-400 hover:text-white">
                <XMarkIcon class="w-5 h-5" />
              </button>
            </div>

            <form @submit.prevent="addExternalCalendar" class="p-6 space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-400 mb-1">Name</label>
                <input
                  v-model="calendarForm.name"
                  type="text"
                  required
                  class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white focus:outline-none focus:border-primary-500"
                  placeholder="z.B. Google Kalender"
                />
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-400 mb-2">Typ</label>
                <div class="grid grid-cols-2 gap-3">
                  <button
                    v-for="type in calendarTypes"
                    :key="type.value"
                    type="button"
                    @click="calendarForm.type = type.value"
                    class="p-3 rounded-lg border-2 text-left transition-all"
                    :class="calendarForm.type === type.value
                      ? 'border-primary-500 bg-primary-500/10'
                      : 'border-dark-600 hover:border-dark-500'"
                  >
                    <p class="font-medium text-white text-sm">{{ type.label }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">{{ type.description }}</p>
                  </button>
                </div>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-400 mb-1">
                  {{ calendarForm.type === 'ical' ? 'iCal URL' : 'CalDAV URL' }}
                </label>
                <input
                  v-model="calendarForm.url"
                  type="url"
                  required
                  class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white focus:outline-none focus:border-primary-500"
                  :placeholder="calendarForm.type === 'ical' ? 'https://calendar.google.com/calendar/ical/...' : 'https://caldav.example.com/...'"
                />
              </div>

              <!-- CalDAV credentials -->
              <template v-if="calendarForm.type === 'caldav'">
                <div>
                  <label class="block text-sm font-medium text-gray-400 mb-1">Benutzername</label>
                  <input
                    v-model="calendarForm.username"
                    type="text"
                    class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white focus:outline-none focus:border-primary-500"
                    placeholder="Optional"
                  />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-400 mb-1">Passwort</label>
                  <input
                    v-model="calendarForm.password"
                    type="password"
                    class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white focus:outline-none focus:border-primary-500"
                    placeholder="Optional"
                  />
                </div>
              </template>

              <div>
                <label class="block text-sm font-medium text-gray-400 mb-2">Farbe</label>
                <div class="flex gap-2">
                  <button
                    v-for="color in colors"
                    :key="color.value"
                    type="button"
                    @click="calendarForm.color = color.value"
                    class="w-8 h-8 rounded-full transition-transform hover:scale-110"
                    :class="[color.class, calendarForm.color === color.value ? 'ring-2 ring-white ring-offset-2 ring-offset-dark-800' : '']"
                  ></button>
                </div>
              </div>

              <!-- Help text -->
              <div class="bg-dark-700/50 rounded-lg p-3 text-sm">
                <p class="text-gray-400 mb-2">
                  <strong class="text-white">So findest du die URL:</strong>
                </p>
                <ul class="text-gray-500 space-y-1 text-xs">
                  <li><strong>Google:</strong> Kalendereinstellungen → "Geheime Adresse im iCal-Format"</li>
                  <li><strong>Outlook:</strong> Kalendereinstellungen → Freigeben → ICS-Link</li>
                  <li><strong>Apple:</strong> iCloud → Kalender → Öffentlichen Kalender teilen</li>
                </ul>
              </div>

              <div class="flex gap-2 pt-2">
                <button
                  type="button"
                  @click="showAddCalendarModal = false; resetCalendarForm()"
                  class="btn-secondary flex-1"
                >
                  Abbrechen
                </button>
                <button type="submit" class="btn-primary flex-1">
                  <LinkIcon class="w-4 h-4 mr-2" />
                  Verbinden
                </button>
              </div>
            </form>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>
