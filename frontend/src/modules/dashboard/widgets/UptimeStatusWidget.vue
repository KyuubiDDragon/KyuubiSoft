<script setup>
import { getStatusBg, getStatusColor } from './widgetUtils'
defineProps({ widget: Object, data: Array })
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-semibold text-white">{{ widget.title }}</h3>
      <router-link to="/uptime" class="text-sm text-primary-400 hover:text-primary-300">Alle</router-link>
    </div>
    <div class="space-y-2">
      <div
        v-for="monitor in (data || []).slice(0, 5)"
        :key="monitor.id"
        class="flex items-center gap-3 p-2 rounded-lg hover:bg-white/[0.04] transition-colors"
      >
        <span class="w-2 h-2 rounded-full flex-shrink-0" :class="getStatusBg(monitor.status)"></span>
        <div class="flex-1 min-w-0">
          <p class="text-sm text-white truncate">{{ monitor.name }}</p>
          <p class="text-xs text-gray-500">{{ monitor.response_time }}ms</p>
        </div>
        <span class="text-xs" :class="getStatusColor(monitor.status)">
          {{ monitor.status === 'up' ? 'Online' : 'Offline' }}
        </span>
      </div>
      <p v-if="!data?.length" class="text-gray-500 text-sm text-center py-4">Keine Monitore</p>
    </div>
  </div>
</template>
