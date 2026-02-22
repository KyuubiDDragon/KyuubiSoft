<script setup>
import { formatDate } from './widgetUtils.js'
import { ArrowPathIcon } from '@heroicons/vue/24/outline'
defineProps({ widget: Object, data: Array })
</script>

<template>
  <div>
    <h3 class="text-lg font-semibold text-white mb-4">{{ widget.title }}</h3>
    <div class="space-y-2 max-h-64 overflow-y-auto">
      <div
        v-for="activity in (data || []).slice(0, 10)"
        :key="activity.created_at"
        class="flex items-center gap-3 p-2 text-sm"
      >
        <ArrowPathIcon class="w-4 h-4 text-gray-500 flex-shrink-0" />
        <div class="flex-1 min-w-0">
          <span class="text-gray-400">{{ activity.action }}</span>
          <span class="text-white"> {{ activity.entity_type }}</span>
        </div>
        <span class="text-xs text-gray-500">{{ formatDate(activity.created_at) }}</span>
      </div>
      <p v-if="!data?.length" class="text-gray-500 text-sm text-center py-4">Keine Aktivität</p>
    </div>
  </div>
</template>
