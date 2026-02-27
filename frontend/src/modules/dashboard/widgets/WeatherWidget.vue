<script setup>
import { MapPinIcon, Cog6ToothIcon } from '@heroicons/vue/24/outline'
import { getWeatherIcon } from './widgetUtils'

defineProps({ widget: Object, data: Object, loading: Boolean })
defineEmits(['open-config'])
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-semibold text-white">{{ widget.title }}</h3>
      <button @click="$emit('open-config')" class="text-gray-400 hover:text-white">
        <Cog6ToothIcon class="w-4 h-4" />
      </button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-8">
      <svg class="animate-spin h-8 w-8 text-primary-500" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" />
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
      </svg>
    </div>

    <!-- No location configured -->
    <div v-else-if="!widget.config?.location" class="text-center py-6">
      <MapPinIcon class="w-10 h-10 text-gray-600 mx-auto mb-2" />
      <p class="text-gray-500 text-sm mb-3">Ort nicht konfiguriert</p>
      <button @click="$emit('open-config')" class="btn-secondary text-xs">Ort festlegen</button>
    </div>

    <!-- Weather data -->
    <div v-else-if="data">
      <div class="flex items-center gap-2 text-xs text-gray-500 mb-3">
        <MapPinIcon class="w-3 h-3" />
        {{ widget.config.location }}
      </div>

      <!-- Current weather -->
      <div class="flex items-center justify-between mb-4">
        <div>
          <p class="text-4xl font-bold text-white">{{ data.current?.temperature }}°</p>
          <p class="text-sm text-gray-400">{{ data.current?.description }}</p>
        </div>
        <component :is="getWeatherIcon(data.current?.icon)" class="w-16 h-16 text-yellow-400" />
      </div>

      <!-- Details -->
      <div class="grid grid-cols-2 gap-2 text-sm mb-4">
        <div class="bg-white/[0.04] rounded p-2">
          <p class="text-gray-500 text-xs">Gefühlt</p>
          <p class="text-white">{{ data.current?.feels_like }}°</p>
        </div>
        <div class="bg-white/[0.04] rounded p-2">
          <p class="text-gray-500 text-xs">Wind</p>
          <p class="text-white">{{ data.current?.wind_speed }} km/h</p>
        </div>
      </div>

      <!-- Forecast -->
      <div class="flex gap-2 overflow-x-auto">
        <div
          v-for="day in (data.forecast || []).slice(1, 5)"
          :key="day.date"
          class="flex-shrink-0 text-center p-2 bg-white/[0.04] rounded"
        >
          <p class="text-xs text-gray-500">{{ day.day?.substring(0, 2) }}</p>
          <p class="text-sm font-medium text-white">{{ day.temp_max }}°</p>
          <p class="text-xs text-gray-500">{{ day.temp_min }}°</p>
        </div>
      </div>
    </div>

    <div v-else class="text-center py-6">
      <p class="text-gray-500 text-sm">Wetterdaten werden geladen...</p>
    </div>
  </div>
</template>
