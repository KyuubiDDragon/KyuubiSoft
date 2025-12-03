<script setup>
import { ref, computed, onUnmounted } from 'vue'

// Timer settings (in minutes)
const settings = ref({
  work: 25,
  shortBreak: 5,
  longBreak: 15,
  sessionsBeforeLong: 4,
})

// Timer state
const mode = ref('work') // 'work', 'shortBreak', 'longBreak'
const timeLeft = ref(settings.value.work * 60)
const isRunning = ref(false)
const completedSessions = ref(0)
const timerInterval = ref(null)

// Computed
const minutes = computed(() => Math.floor(timeLeft.value / 60))
const seconds = computed(() => timeLeft.value % 60)

const displayTime = computed(() => {
  return `${String(minutes.value).padStart(2, '0')}:${String(seconds.value).padStart(2, '0')}`
})

const progress = computed(() => {
  let total
  switch (mode.value) {
    case 'work':
      total = settings.value.work * 60
      break
    case 'shortBreak':
      total = settings.value.shortBreak * 60
      break
    case 'longBreak':
      total = settings.value.longBreak * 60
      break
    default:
      total = settings.value.work * 60
  }
  return ((total - timeLeft.value) / total) * 100
})

const modeLabel = computed(() => {
  switch (mode.value) {
    case 'work':
      return 'Fokuszeit'
    case 'shortBreak':
      return 'Kurze Pause'
    case 'longBreak':
      return 'Lange Pause'
    default:
      return ''
  }
})

const modeColor = computed(() => {
  switch (mode.value) {
    case 'work':
      return 'text-primary-500'
    case 'shortBreak':
      return 'text-green-500'
    case 'longBreak':
      return 'text-blue-500'
    default:
      return 'text-gray-500'
  }
})

const progressBarColor = computed(() => {
  switch (mode.value) {
    case 'work':
      return 'bg-primary-500'
    case 'shortBreak':
      return 'bg-green-500'
    case 'longBreak':
      return 'bg-blue-500'
    default:
      return 'bg-gray-500'
  }
})

// Methods
function start() {
  if (isRunning.value) return

  isRunning.value = true
  timerInterval.value = setInterval(() => {
    if (timeLeft.value > 0) {
      timeLeft.value--
    } else {
      completeSession()
    }
  }, 1000)
}

function pause() {
  isRunning.value = false
  if (timerInterval.value) {
    clearInterval(timerInterval.value)
    timerInterval.value = null
  }
}

function reset() {
  pause()
  setModeTime()
}

function completeSession() {
  pause()
  playNotification()

  if (mode.value === 'work') {
    completedSessions.value++

    // Check if we need a long break
    if (completedSessions.value % settings.value.sessionsBeforeLong === 0) {
      setMode('longBreak')
    } else {
      setMode('shortBreak')
    }
  } else {
    // After break, go back to work
    setMode('work')
  }
}

function setMode(newMode) {
  mode.value = newMode
  setModeTime()
}

function setModeTime() {
  switch (mode.value) {
    case 'work':
      timeLeft.value = settings.value.work * 60
      break
    case 'shortBreak':
      timeLeft.value = settings.value.shortBreak * 60
      break
    case 'longBreak':
      timeLeft.value = settings.value.longBreak * 60
      break
  }
}

function skip() {
  pause()
  if (mode.value === 'work') {
    completedSessions.value++
    if (completedSessions.value % settings.value.sessionsBeforeLong === 0) {
      setMode('longBreak')
    } else {
      setMode('shortBreak')
    }
  } else {
    setMode('work')
  }
}

function playNotification() {
  // Try to play a notification sound
  try {
    const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2teleQYHd6TJ4XNEBgR6tM7VXy0GB4a+0MxOIQQKlMvUxD4XAw+d0dfFOxQDFKPW2sE2EQMZqNzbvjAPAx+t4N27LgwDJLLj3rcsCgIor+bitioBBCyr6OO1KgIGKqXq47IqAAcfn+3jtCoBBxid8OO0KgEJF5rx5LUqAQoTl/PmtioACxGU9Oi2KgAMD5H36rcrAA0Njvnttyv/Dgp7/O+4LAAPCHb/8bksABEFcQPyui0AEgJtBfO6LQATABAH77YrABQAHQjsuS0AEgAhCOq3LgASACIJ6LYuABMAIQnnti4AEwAhCea2LwASACEJ5bYvABIAIQnlti8AEQA=')
    audio.volume = 0.5
    audio.play()
  } catch (e) {
    // Ignore audio errors
  }

  // Also try browser notification
  if ('Notification' in window && Notification.permission === 'granted') {
    new Notification('Pomodoro Timer', {
      body: mode.value === 'work' ? 'Zeit für eine Pause!' : 'Zurück an die Arbeit!',
      icon: '🍅',
    })
  }
}

function requestNotificationPermission() {
  if ('Notification' in window) {
    Notification.requestPermission()
  }
}

function updateSetting(key, value) {
  settings.value[key] = parseInt(value) || 1
  if (!isRunning.value) {
    setModeTime()
  }
}

function resetAll() {
  pause()
  completedSessions.value = 0
  setMode('work')
}

// Cleanup on unmount
onUnmounted(() => {
  if (timerInterval.value) {
    clearInterval(timerInterval.value)
  }
})
</script>

<template>
  <div class="space-y-6">
    <!-- Mode Tabs -->
    <div class="flex gap-2">
      <button
        @click="setMode('work'); pause()"
        class="flex-1 py-2 px-4 rounded-lg text-sm font-medium transition-colors"
        :class="mode === 'work' ? 'bg-primary-600 text-white' : 'bg-dark-700 text-gray-400 hover:text-white'"
      >
        Fokus
      </button>
      <button
        @click="setMode('shortBreak'); pause()"
        class="flex-1 py-2 px-4 rounded-lg text-sm font-medium transition-colors"
        :class="mode === 'shortBreak' ? 'bg-green-600 text-white' : 'bg-dark-700 text-gray-400 hover:text-white'"
      >
        Kurze Pause
      </button>
      <button
        @click="setMode('longBreak'); pause()"
        class="flex-1 py-2 px-4 rounded-lg text-sm font-medium transition-colors"
        :class="mode === 'longBreak' ? 'bg-blue-600 text-white' : 'bg-dark-700 text-gray-400 hover:text-white'"
      >
        Lange Pause
      </button>
    </div>

    <!-- Timer Display -->
    <div class="text-center py-8">
      <div :class="modeColor" class="text-sm font-medium mb-2">
        {{ modeLabel }}
      </div>
      <div class="text-7xl font-mono font-bold text-white mb-4">
        {{ displayTime }}
      </div>

      <!-- Progress Bar -->
      <div class="w-full h-2 bg-dark-700 rounded-full overflow-hidden mb-6">
        <div
          class="h-full transition-all duration-300"
          :class="progressBarColor"
          :style="{ width: progress + '%' }"
        ></div>
      </div>

      <!-- Controls -->
      <div class="flex justify-center gap-4">
        <button
          v-if="!isRunning"
          @click="start"
          class="btn-primary px-8 py-3 text-lg"
        >
          Start
        </button>
        <button
          v-else
          @click="pause"
          class="btn-secondary px-8 py-3 text-lg"
        >
          Pause
        </button>
        <button
          @click="reset"
          class="btn-secondary px-6 py-3"
        >
          Reset
        </button>
        <button
          @click="skip"
          class="btn-secondary px-6 py-3"
          title="Session überspringen"
        >
          ⏭️
        </button>
      </div>
    </div>

    <!-- Session Counter -->
    <div class="flex justify-center items-center gap-4">
      <div class="text-center">
        <div class="text-3xl font-bold text-white">{{ completedSessions }}</div>
        <div class="text-sm text-gray-400">Sessions</div>
      </div>
      <button
        @click="resetAll"
        class="text-xs text-gray-500 hover:text-gray-300"
      >
        Zurücksetzen
      </button>
    </div>

    <!-- Session Indicators -->
    <div class="flex justify-center gap-2">
      <div
        v-for="i in settings.sessionsBeforeLong"
        :key="i"
        class="w-4 h-4 rounded-full transition-colors"
        :class="i <= (completedSessions % settings.sessionsBeforeLong || (completedSessions > 0 ? settings.sessionsBeforeLong : 0)) ? 'bg-primary-500' : 'bg-dark-700'"
      ></div>
    </div>

    <!-- Settings -->
    <div class="p-4 bg-dark-700 rounded-lg space-y-3">
      <div class="text-sm font-medium text-white mb-3">Einstellungen (Minuten)</div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="text-xs text-gray-400">Fokuszeit</label>
          <input
            type="number"
            min="1"
            max="120"
            :value="settings.work"
            @input="updateSetting('work', $event.target.value)"
            class="input w-full"
          />
        </div>
        <div>
          <label class="text-xs text-gray-400">Kurze Pause</label>
          <input
            type="number"
            min="1"
            max="60"
            :value="settings.shortBreak"
            @input="updateSetting('shortBreak', $event.target.value)"
            class="input w-full"
          />
        </div>
        <div>
          <label class="text-xs text-gray-400">Lange Pause</label>
          <input
            type="number"
            min="1"
            max="120"
            :value="settings.longBreak"
            @input="updateSetting('longBreak', $event.target.value)"
            class="input w-full"
          />
        </div>
        <div>
          <label class="text-xs text-gray-400">Sessions bis Lange Pause</label>
          <input
            type="number"
            min="1"
            max="10"
            :value="settings.sessionsBeforeLong"
            @input="updateSetting('sessionsBeforeLong', $event.target.value)"
            class="input w-full"
          />
        </div>
      </div>

      <button
        @click="requestNotificationPermission"
        class="w-full mt-2 text-xs text-primary-400 hover:text-primary-300"
      >
        🔔 Benachrichtigungen aktivieren
      </button>
    </div>
  </div>
</template>
