<script setup>
import { computed } from 'vue'
import { ClockIcon } from '@heroicons/vue/24/outline'

const props = defineProps({ widget: Object, data: Array })

function formatRelativeTime(dateString) {
  if (!dateString) return '-'
  const now = new Date()
  const date = new Date(dateString)
  const diffMs = now.getTime() - date.getTime()
  const diffSec = Math.floor(diffMs / 1000)
  const diffMin = Math.floor(diffSec / 60)
  const diffHour = Math.floor(diffMin / 60)
  const diffDay = Math.floor(diffHour / 24)

  if (diffSec < 60) return 'gerade eben'
  if (diffMin < 60) return `vor ${diffMin} Min.`
  if (diffHour < 24) return `vor ${diffHour} Std.`
  if (diffDay === 1) return 'gestern'
  if (diffDay < 7) return `vor ${diffDay} Tagen`

  return date.toLocaleDateString('de-DE', {
    day: '2-digit',
    month: '2-digit',
  })
}

const recentExecutions = computed(() => {
  return (props.data || []).slice(0, 5)
})
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-semibold text-white">{{ widget.title }}</h3>
      <router-link to="/cron" class="text-sm text-primary-400 hover:text-primary-300">Alle anzeigen</router-link>
    </div>
    <div class="space-y-2 max-h-64 overflow-y-auto">
      <div
        v-for="entry in recentExecutions"
        :key="entry.id || entry.started_at"
        class="flex items-start gap-3 p-2 rounded-lg hover:bg-white/[0.03] transition-colors"
      >
        <!-- Status dot -->
        <span
          class="w-2 h-2 rounded-full mt-1.5 flex-shrink-0"
          :class="entry.exit_code === 0
            ? 'bg-emerald-400'
            : entry.exit_code !== null
              ? 'bg-red-400'
              : 'bg-gray-500'"
        ></span>
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2">
            <span class="text-sm text-white truncate">
              {{ entry.job_name || entry.command || 'Cron Job' }}
            </span>
            <code v-if="entry.expression" class="text-2xs text-gray-500 font-mono">{{ entry.expression }}</code>
          </div>
          <p class="text-xs text-gray-500 mt-0.5">
            {{ formatRelativeTime(entry.started_at || entry.last_run_at) }}
          </p>
        </div>
        <span
          v-if="entry.exit_code !== null && entry.exit_code !== undefined"
          class="text-2xs font-mono px-1.5 py-0.5 rounded"
          :class="entry.exit_code === 0
            ? 'bg-emerald-500/15 text-emerald-400'
            : 'bg-red-500/15 text-red-400'"
        >
          {{ entry.exit_code }}
        </span>
      </div>
      <p v-if="!data?.length" class="text-gray-500 text-sm text-center py-4">
        <ClockIcon class="w-5 h-5 mx-auto mb-1 text-gray-600" />
        Keine Cron-Ausfuehrungen
      </p>
    </div>
  </div>
</template>
