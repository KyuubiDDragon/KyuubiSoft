<script setup>
import { formatDate } from './widgetUtils'
defineProps({ widget: Object, data: Array })
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-semibold text-white">{{ widget.title }}</h3>
      <router-link to="/calendar" class="text-sm text-primary-400 hover:text-primary-300">{{ $t('widgets.calendar') }}</router-link>
    </div>
    <div class="space-y-2">
      <div
        v-for="event in (data || []).slice(0, 5)"
        :key="event.id"
        class="flex items-center gap-3 p-2 rounded-lg hover:bg-white/[0.04] transition-colors"
      >
        <div class="w-1 h-8 rounded-full flex-shrink-0" :style="{ backgroundColor: event.color }"></div>
        <div class="flex-1 min-w-0">
          <p class="text-sm text-white truncate">{{ event.title }}</p>
          <p class="text-xs text-gray-500">
            {{ event.source_type === 'kanban' ? $t('widgets.kanban') : event.source_type === 'task' ? $t('widgets.task') : $t('widgets.event') }}
          </p>
        </div>
        <span class="text-xs text-gray-400">{{ formatDate(event.start_date) }}</span>
      </div>
      <p v-if="!data?.length" class="text-gray-500 text-sm text-center py-4">{{ $t('widgets.noEventsThisWeek') }}</p>
    </div>
  </div>
</template>
