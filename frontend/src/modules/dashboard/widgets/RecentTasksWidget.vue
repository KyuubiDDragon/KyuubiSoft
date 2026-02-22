<script setup>
import { formatDate } from './widgetUtils.js'
defineProps({ widget: Object, data: Array })
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-semibold text-white">{{ widget.title }}</h3>
      <router-link to="/lists" class="text-sm text-primary-400 hover:text-primary-300">Alle</router-link>
    </div>
    <div class="space-y-2">
      <div
        v-for="task in (data || []).slice(0, 5)"
        :key="task.id"
        class="flex items-center gap-3 p-2 rounded-lg hover:bg-dark-700/50 transition-colors"
      >
        <div class="w-3 h-3 rounded-full flex-shrink-0" :style="{ backgroundColor: task.color || '#3B82F6' }"></div>
        <div class="flex-1 min-w-0">
          <p class="text-sm text-white truncate">{{ task.content }}</p>
          <p class="text-xs text-gray-500">{{ task.list_title }}</p>
        </div>
        <span
          v-if="task.due_date"
          class="text-xs px-2 py-0.5 rounded"
          :class="new Date(task.due_date) < new Date() ? 'bg-red-500/20 text-red-400' : 'bg-yellow-500/20 text-yellow-400'"
        >
          {{ formatDate(task.due_date) }}
        </span>
      </div>
      <p v-if="!data?.length" class="text-gray-500 text-sm text-center py-4">Keine offenen Aufgaben</p>
    </div>
  </div>
</template>
