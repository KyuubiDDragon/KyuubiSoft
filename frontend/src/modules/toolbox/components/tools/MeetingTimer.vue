<script setup>
import { ref, computed, onUnmounted } from 'vue'

const meetingDuration = ref(30) // minutes
const participants = ref([])
const newParticipant = ref('')
const currentSpeaker = ref(null)
const speakerTimes = ref({})
const meetingStartTime = ref(null)
const isRunning = ref(false)
const timerInterval = ref(null)
const elapsedSeconds = ref(0)

// Presets for meeting duration
const durationPresets = [15, 30, 45, 60, 90, 120]

const totalMeetingSeconds = computed(() => meetingDuration.value * 60)

const remainingSeconds = computed(() => {
  return Math.max(0, totalMeetingSeconds.value - elapsedSeconds.value)
})

const progress = computed(() => {
  return (elapsedSeconds.value / totalMeetingSeconds.value) * 100
})

const isOvertime = computed(() => elapsedSeconds.value > totalMeetingSeconds.value)

const overtimeSeconds = computed(() => {
  return Math.max(0, elapsedSeconds.value - totalMeetingSeconds.value)
})

function formatTime(seconds) {
  const h = Math.floor(seconds / 3600)
  const m = Math.floor((seconds % 3600) / 60)
  const s = seconds % 60
  if (h > 0) {
    return `${h}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
  }
  return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
}

function startMeeting() {
  if (isRunning.value) return

  isRunning.value = true
  meetingStartTime.value = Date.now()

  timerInterval.value = setInterval(() => {
    elapsedSeconds.value++

    // Update current speaker time
    if (currentSpeaker.value) {
      if (!speakerTimes.value[currentSpeaker.value]) {
        speakerTimes.value[currentSpeaker.value] = 0
      }
      speakerTimes.value[currentSpeaker.value]++
    }

    // Alert at 5 minutes remaining
    if (remainingSeconds.value === 300) {
      playSound('warning')
    }

    // Alert at 1 minute remaining
    if (remainingSeconds.value === 60) {
      playSound('warning')
    }

    // Alert when time is up
    if (remainingSeconds.value === 0) {
      playSound('alarm')
    }
  }, 1000)
}

function pauseMeeting() {
  isRunning.value = false
  if (timerInterval.value) {
    clearInterval(timerInterval.value)
    timerInterval.value = null
  }
}

function resetMeeting() {
  pauseMeeting()
  elapsedSeconds.value = 0
  meetingStartTime.value = null
  currentSpeaker.value = null
  speakerTimes.value = {}
}

function addParticipant() {
  const name = newParticipant.value.trim()
  if (name && !participants.value.includes(name)) {
    participants.value.push(name)
    speakerTimes.value[name] = 0
  }
  newParticipant.value = ''
}

function removeParticipant(name) {
  const index = participants.value.indexOf(name)
  if (index !== -1) {
    participants.value.splice(index, 1)
    delete speakerTimes.value[name]
    if (currentSpeaker.value === name) {
      currentSpeaker.value = null
    }
  }
}

function setSpeaker(name) {
  currentSpeaker.value = currentSpeaker.value === name ? null : name
}

function playSound(type) {
  try {
    const audio = new Audio()
    if (type === 'warning') {
      audio.src = 'data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2teleQYHd6TJ4XNEBgR6tM7VXy0GB4a+0MxOIQQKlMvUxD4XAw+d0dfFOxQDFKPW2sE2EQMZqNzbvjAPAx+t4N27LgwDJLLj3rcsCgIor+bitioBBCyr6OO1KgIGKqXq47IqAAcfn+3jtCoBBxid8OO0KgEJF5rx5LUqAQoTl/PmtioACxGU9Oi2KgAMD5H36rcrAA0Njvnttyv/Dgp7/O+4LAAPCHb/8bksABEFcQPyui0AEgJtBfO6LQATABAH77YrABQAHQjsuS0AEgAhCOq3LgASACIJ6LYuABMAIQnnti4AEwAhCea2LwASACEJ5bYvABIAIQnlti8AEQA='
    } else {
      audio.src = 'data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2teleQYHd6TJ4XNEBgR6tM7VXy0GB4a+0MxOIQQKlMvUxD4XAw+d0dfFOxQDFKPW2sE2EQMZqNzbvjAPAx+t4N27LgwDJLLj3rcsCgIor+bitioBBCyr6OO1KgIGKqXq47IqAAcfn+3jtCoBBxid8OO0KgEJF5rx5LUqAQoTl/PmtioACxGU9Oi2KgAMD5H36rcrAA0Njvnttyv/Dgp7/O+4LAAPCHb/8bksABEFcQPyui0AEgJtBfO6LQATABAH77YrABQAHQjsuS0AEgAhCOq3LgASACIJ6LYuABMAIQnnti4AEwAhCea2LwASACEJ5bYvABIAIQnlti8AEQA='
    }
    audio.volume = 0.5
    audio.play()
  } catch (e) {
    // Ignore audio errors
  }
}

const sortedParticipantsByTime = computed(() => {
  return [...participants.value].sort((a, b) => {
    return (speakerTimes.value[b] || 0) - (speakerTimes.value[a] || 0)
  })
})

const totalSpeakingTime = computed(() => {
  return Object.values(speakerTimes.value).reduce((a, b) => a + b, 0)
})

onUnmounted(() => {
  if (timerInterval.value) {
    clearInterval(timerInterval.value)
  }
})
</script>

<template>
  <div class="space-y-4">
    <!-- Timer Display -->
    <div class="text-center py-6">
      <div
        class="text-6xl font-mono font-bold mb-2"
        :class="isOvertime ? 'text-red-500' : 'text-white'"
      >
        {{ isOvertime ? '+' : '' }}{{ formatTime(isOvertime ? overtimeSeconds : remainingSeconds) }}
      </div>
      <div class="text-sm text-gray-400">
        {{ isOvertime ? 'Überzogen' : 'Verbleibend' }}
      </div>

      <!-- Progress Bar -->
      <div class="w-full h-3 bg-white/[0.04] rounded-full mt-4 overflow-hidden">
        <div
          class="h-full transition-all duration-1000"
          :class="isOvertime ? 'bg-red-500' : progress > 80 ? 'bg-yellow-500' : 'bg-primary-500'"
          :style="{ width: Math.min(progress, 100) + '%' }"
        ></div>
      </div>

      <div class="text-xs text-gray-500 mt-2">
        Vergangen: {{ formatTime(elapsedSeconds) }} / {{ meetingDuration }} min
      </div>
    </div>

    <!-- Controls -->
    <div class="flex justify-center gap-3">
      <button
        v-if="!isRunning"
        @click="startMeeting"
        class="btn-primary px-8 py-3"
      >
        {{ elapsedSeconds > 0 ? 'Fortsetzen' : 'Start' }}
      </button>
      <button
        v-else
        @click="pauseMeeting"
        class="btn-secondary px-8 py-3"
      >
        Pause
      </button>
      <button
        @click="resetMeeting"
        class="btn-secondary px-6 py-3"
      >
        Reset
      </button>
    </div>

    <!-- Duration Settings -->
    <div v-if="!isRunning && elapsedSeconds === 0">
      <label class="text-sm text-gray-400 mb-2 block">Meeting-Dauer (Minuten)</label>
      <div class="flex flex-wrap gap-2">
        <button
          v-for="preset in durationPresets"
          :key="preset"
          @click="meetingDuration = preset"
          class="px-4 py-2 rounded-lg text-sm transition-colors"
          :class="meetingDuration === preset ? 'bg-primary-600 text-white' : 'bg-white/[0.04] text-gray-400 hover:text-white'"
        >
          {{ preset }} min
        </button>
        <input
          v-model.number="meetingDuration"
          type="number"
          min="1"
          max="480"
          class="input w-20 text-center"
        />
      </div>
    </div>

    <!-- Participants -->
    <div class="space-y-3">
      <h4 class="text-sm text-gray-400">Teilnehmer & Redezeit</h4>

      <!-- Add participant -->
      <div class="flex gap-2">
        <input
          v-model="newParticipant"
          type="text"
          @keyup.enter="addParticipant"
          class="input flex-1"
          placeholder="Name eingeben..."
        />
        <button @click="addParticipant" class="btn-secondary px-4">
          Hinzufügen
        </button>
      </div>

      <!-- Participant list -->
      <div class="space-y-2">
        <div
          v-for="name in sortedParticipantsByTime"
          :key="name"
          @click="setSpeaker(name)"
          class="flex items-center gap-3 p-3 rounded-lg cursor-pointer transition-colors"
          :class="currentSpeaker === name ? 'bg-primary-600/30 ring-1 ring-primary-500' : 'bg-white/[0.04] hover:bg-white/[0.04]'"
        >
          <div
            class="w-3 h-3 rounded-full"
            :class="currentSpeaker === name ? 'bg-green-500 animate-pulse' : 'bg-gray-600'"
          ></div>
          <div class="flex-1">
            <div class="text-white">{{ name }}</div>
            <div class="text-xs text-gray-400">
              {{ formatTime(speakerTimes[name] || 0) }}
              <span v-if="totalSpeakingTime > 0">
                ({{ Math.round(((speakerTimes[name] || 0) / totalSpeakingTime) * 100) }}%)
              </span>
            </div>
          </div>
          <button
            @click.stop="removeParticipant(name)"
            class="text-gray-500 hover:text-red-400"
          >
            ✕
          </button>
        </div>

        <div v-if="participants.length === 0" class="text-center text-gray-500 text-sm py-4">
          Füge Teilnehmer hinzu, um die Redezeit zu tracken
        </div>
      </div>
    </div>

    <!-- Current Speaker Indicator -->
    <div v-if="currentSpeaker && isRunning" class="text-center p-4 bg-primary-900/30 rounded-lg">
      <div class="text-sm text-gray-400">Aktueller Sprecher</div>
      <div class="text-xl font-medium text-white">{{ currentSpeaker }}</div>
    </div>
  </div>
</template>
