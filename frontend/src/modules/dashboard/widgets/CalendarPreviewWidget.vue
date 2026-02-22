<script setup>
import { formatDate } from './widgetUtils.js'
defineProps({ widget: Object, data: Array })
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-semibold text-white">{{ widget.title }}</h3>
      <router-link to="/calendar" class="text-sm text-primary-400 hover:text-primary-300">Kalender</router-link>
    </div>
    <div class="space-y-2">
      <div
        v-for="event in (data || []).slice(0, 5)"
        :key="event.id"
        class="flex items-center gap-3 p-2 rounded-lg hover:bg-dark-700/50 transition-colors"
      >
        <div class="w-1 h-8 rounded-full flex-shrink-0" :style="{ backgroundColor: event.color }"></div>
        <div class="flex-1 min-w-0">
          <p class="text-sm text-white truncate">{{ event.title }}</p>
          <p class="text-xs text-gray-500">
            {{ event.source_type === 'kanban' ? 'Kanban' : event.source_type === 'task' ? 'Aufgabe' : 'Event' }}
          </p>
        </div>
        <span class="text-xs text-gray-400">{{ formatDate(event.start_date) }}</span>
      </div>
      <p v-if="!data?.length" class="text-gray-500 text-sm text-center py-4">Keine Termine diese Woche</p>
    </div>
  </div>
</template>
