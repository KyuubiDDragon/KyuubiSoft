<script setup>
import { formatDate } from './widgetUtils'

defineProps({ widget: Object, data: Object })
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-semibold text-white">{{ widget.title }}</h3>
      <router-link to="/backups" class="text-sm text-primary-400 hover:text-primary-300">Alle</router-link>
    </div>
    <div class="space-y-3">
      <div class="flex items-center justify-between">
        <span class="text-gray-400 text-sm">Erfolgreiche Backups</span>
        <span class="text-green-400 font-medium">{{ data?.successful_backups || 0 }}</span>
      </div>
      <div class="flex items-center justify-between">
        <span class="text-gray-400 text-sm">Fehlgeschlagen</span>
        <span class="text-red-400 font-medium">{{ data?.failed_backups || 0 }}</span>
      </div>
      <div class="flex items-center justify-between">
        <span class="text-gray-400 text-sm">Aktive Zeitpläne</span>
        <span class="text-white font-medium">{{ data?.active_schedules || 0 }}</span>
      </div>
      <div v-if="data?.last_successful" class="pt-2 border-t border-white/[0.06]">
        <p class="text-xs text-gray-500">Letztes erfolgreiches Backup:</p>
        <p class="text-sm text-white">{{ formatDate(data.last_successful) }}</p>
      </div>
    </div>
  </div>
</template>
