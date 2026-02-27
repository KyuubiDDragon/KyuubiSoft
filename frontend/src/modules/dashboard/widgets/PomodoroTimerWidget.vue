<script setup>
import { ref, computed, onUnmounted } from 'vue'
import { PlayIcon, PauseIcon, ArrowPathIcon } from '@heroicons/vue/24/solid'

defineProps({ widget: Object, data: Object })

const WORK_DURATION = 25 * 60
const BREAK_DURATION = 5 * 60
const TOTAL_SESSIONS = 4

const timeLeft = ref(WORK_DURATION)
const isRunning = ref(false)
const isBreak = ref(false)
const sessions = ref(0)
let intervalId = null

const totalDuration = computed(() => isBreak.value ? BREAK_DURATION : WORK_DURATION)

const progress = computed(() => {
  return ((totalDuration.value - timeLeft.value) / totalDuration.value) * 100
})

const displayTime = computed(() => {
  const min = Math.floor(timeLeft.value / 60)
  const sec = timeLeft.value % 60
  return `${String(min).padStart(2, '0')}:${String(sec).padStart(2, '0')}`
})

const stateLabel = computed(() => isBreak.value ? 'Pause' : 'Arbeit')

const stateColor = computed(() => isBreak.value ? 'text-green-500' : 'text-primary-500')

const ringOffset = computed(() => {
  const circumference = 251.2
  return circumference - (circumference * progress.value / 100)
})

function tick() {
  if (timeLeft.value > 0) {
    timeLeft.value--
  } else {
    // Timer ended
    if (isBreak.value) {
      // Break ended, start new work session
      isBreak.value = false
      timeLeft.value = WORK_DURATION
    } else {
      // Work ended, increment sessions and start break
      sessions.value++
      if (sessions.value >= TOTAL_SESSIONS) {
        // All sessions done, reset
        reset()
        return
      }
      isBreak.value = true
      timeLeft.value = BREAK_DURATION
    }
  }
}

function start() {
  if (!isRunning.value) {
    isRunning.value = true
    intervalId = setInterval(tick, 1000)
  }
}

function pause() {
  isRunning.value = false
  if (intervalId) {
    clearInterval(intervalId)
    intervalId = null
  }
}

function reset() {
  pause()
  timeLeft.value = WORK_DURATION
  isBreak.value = false
  sessions.value = 0
}

onUnmounted(() => {
  if (intervalId) {
    clearInterval(intervalId)
  }
})
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-semibold text-white">{{ widget.title }}</h3>
      <span class="text-sm font-medium" :class="stateColor">{{ stateLabel }}</span>
    </div>

    <div class="text-center">
      <!-- Circular progress ring -->
      <div class="relative inline-flex items-center justify-center w-24 h-24 mb-3">
        <svg class="w-24 h-24 transform -rotate-90">
          <circle cx="48" cy="48" r="40" stroke="currentColor" stroke-width="8" fill="none" class="text-white/[0.08]" />
          <circle
            cx="48"
            cy="48"
            r="40"
            stroke="currentColor"
            stroke-width="8"
            fill="none"
            :stroke-dasharray="251.2"
            :stroke-dashoffset="ringOffset"
            :class="stateColor"
            class="transition-all duration-500"
            stroke-linecap="round"
          />
        </svg>
        <span class="absolute text-xl font-bold text-white">{{ displayTime }}</span>
      </div>

      <!-- Session counter -->
      <p class="text-gray-500 text-sm mb-4">{{ sessions }}/{{ TOTAL_SESSIONS }} Sitzungen</p>

      <!-- Controls -->
      <div class="flex items-center justify-center gap-3">
        <button
          v-if="!isRunning"
          @click="start"
          class="p-2 rounded-lg bg-white/[0.08] hover:bg-white/[0.12] text-white transition-colors"
        >
          <PlayIcon class="w-5 h-5" />
        </button>
        <button
          v-else
          @click="pause"
          class="p-2 rounded-lg bg-white/[0.08] hover:bg-white/[0.12] text-white transition-colors"
        >
          <PauseIcon class="w-5 h-5" />
        </button>
        <button
          @click="reset"
          class="p-2 rounded-lg bg-white/[0.08] hover:bg-white/[0.12] text-gray-400 hover:text-white transition-colors"
        >
          <ArrowPathIcon class="w-5 h-5" />
        </button>
      </div>
    </div>
  </div>
</template>
