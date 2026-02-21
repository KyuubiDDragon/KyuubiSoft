<script setup>
import { ClockIcon, Cog6ToothIcon } from '@heroicons/vue/24/outline'

defineProps({ widget: Object, data: Object })
defineEmits(['open-config'])
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-semibold text-white">{{ widget.config?.title || widget.title }}</h3>
      <button @click="$emit('open-config')" class="text-gray-400 hover:text-white">
        <Cog6ToothIcon class="w-4 h-4" />
      </button>
    </div>

    <!-- No date configured -->
    <div v-if="!widget.config?.date" class="text-center py-6">
      <ClockIcon class="w-10 h-10 text-gray-600 mx-auto mb-2" />
      <p class="text-gray-500 text-sm mb-3">Kein Datum festgelegt</p>
      <button @click="$emit('open-config')" class="btn-secondary text-xs">Countdown einrichten</button>
    </div>

    <!-- Countdown display -->
    <div v-else-if="data" class="text-center">
      <div v-if="data.expired" class="py-4">
        <p class="text-2xl font-bold text-green-400">Erreicht!</p>
      </div>
      <div v-else class="grid grid-cols-4 gap-2">
        <div class="bg-dark-700/50 rounded-lg p-2">
          <p class="text-2xl font-bold text-white">{{ data.days }}</p>
          <p class="text-xs text-gray-500">Tage</p>
        </div>
        <div class="bg-dark-700/50 rounded-lg p-2">
          <p class="text-2xl font-bold text-white">{{ data.hours }}</p>
          <p class="text-xs text-gray-500">Std</p>
        </div>
        <div class="bg-dark-700/50 rounded-lg p-2">
          <p class="text-2xl font-bold text-white">{{ data.minutes }}</p>
          <p class="text-xs text-gray-500">Min</p>
        </div>
        <div class="bg-dark-700/50 rounded-lg p-2">
          <p class="text-2xl font-bold text-white">{{ data.seconds }}</p>
          <p class="text-xs text-gray-500">Sek</p>
        </div>
      </div>
      <p class="text-xs text-gray-500 mt-3">{{ new Date(widget.config.date).toLocaleDateString('de-DE') }}</p>
    </div>
  </div>
</template>
