<script setup>
import { PlayIcon } from '@heroicons/vue/24/outline'
defineProps({ widget: Object, data: Object })
</script>

<template>
  <div>
    <h3 class="text-lg font-semibold text-white mb-4">{{ widget.title }}</h3>
    <div class="text-center">
      <p class="text-4xl font-bold text-white">{{ data?.total_hours || 0 }}h</p>
      <p class="text-gray-500 text-sm mt-1">{{ data?.entry_count || 0 }} Einträge</p>
      <div v-if="data?.running" class="mt-4 p-3 bg-green-500/10 border border-green-500/30 rounded-lg">
        <div class="flex items-center gap-2 text-green-400">
          <PlayIcon class="w-4 h-4 animate-pulse" />
          <span class="text-sm font-medium">Läuft</span>
        </div>
        <p class="text-xs text-gray-400 mt-1 truncate">{{ data.running.project_name || 'Kein Projekt' }}</p>
      </div>
    </div>
  </div>
</template>
