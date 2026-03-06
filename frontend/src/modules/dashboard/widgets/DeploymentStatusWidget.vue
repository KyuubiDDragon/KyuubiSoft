<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RocketLaunchIcon } from '@heroicons/vue/24/outline'

const { t } = useI18n()
const props = defineProps({ widget: Object, data: Array })

const statusConfig = computed(() => ({
  pending: { label: t('widgets.statusPending'), dotClass: 'bg-gray-500' },
  running: { label: t('widgets.statusRunning'), dotClass: 'bg-amber-400' },
  success: { label: t('widgets.statusSuccess'), dotClass: 'bg-emerald-400' },
  failed: { label: t('widgets.statusFailed'), dotClass: 'bg-red-400' },
  cancelled: { label: t('widgets.statusCancelled'), dotClass: 'bg-gray-500' },
  rolled_back: { label: t('dashboardModule.statusRolledBack'), dotClass: 'bg-purple-400' },
}))

function getStatusDot(status) {
  return statusConfig.value[status]?.dotClass || 'bg-gray-500'
}

function getStatusLabel(status) {
  return statusConfig.value[status]?.label || status
}

function formatRelativeTime(dateString) {
  if (!dateString) return '-'
  const now = new Date()
  const date = new Date(dateString)
  const diffMs = now.getTime() - date.getTime()
  const diffSec = Math.floor(diffMs / 1000)
  const diffMin = Math.floor(diffSec / 60)
  const diffHour = Math.floor(diffMin / 60)
  const diffDay = Math.floor(diffHour / 24)

  if (diffSec < 60) return t('time.justNow')
  if (diffMin < 60) return t('time.minutesAgo', { n: diffMin })
  if (diffHour < 24) return t('time.hoursAgo', { n: diffHour })
  if (diffDay === 1) return t('time.yesterday')
  if (diffDay < 7) return t('time.daysAgo', { n: diffDay })

  return date.toLocaleDateString(undefined, {
    day: '2-digit',
    month: '2-digit',
  })
}

function shortHash(hash) {
  if (!hash) return ''
  return hash.substring(0, 7)
}

const recentDeployments = computed(() => {
  return (props.data || []).slice(0, 3)
})
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-semibold text-white">{{ widget.title }}</h3>
      <router-link to="/deployments" class="text-sm text-primary-400 hover:text-primary-300">{{ $t('common.showAll') }}</router-link>
    </div>
    <div class="space-y-2 max-h-64 overflow-y-auto">
      <div
        v-for="entry in recentDeployments"
        :key="entry.id"
        class="flex items-start gap-3 p-2 rounded-lg hover:bg-white/[0.03] transition-colors"
      >
        <!-- Status dot -->
        <span
          class="w-2 h-2 rounded-full mt-1.5 flex-shrink-0"
          :class="getStatusDot(entry.status)"
        ></span>
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2">
            <span class="text-sm text-white truncate">
              {{ entry.pipeline_name || 'Deployment' }}
            </span>
            <code v-if="entry.commit_hash" class="text-2xs text-gray-500 font-mono">{{ shortHash(entry.commit_hash) }}</code>
          </div>
          <p class="text-xs text-gray-500 mt-0.5">
            {{ formatRelativeTime(entry.created_at || entry.started_at) }}
          </p>
        </div>
        <span
          class="text-2xs px-1.5 py-0.5 rounded"
          :class="{
            'bg-emerald-500/15 text-emerald-400': entry.status === 'success',
            'bg-red-500/15 text-red-400': entry.status === 'failed',
            'bg-amber-500/15 text-amber-400': entry.status === 'running',
            'bg-gray-500/15 text-gray-400': entry.status === 'pending' || entry.status === 'cancelled',
            'bg-purple-500/15 text-purple-400': entry.status === 'rolled_back',
          }"
        >
          {{ getStatusLabel(entry.status) }}
        </span>
      </div>
      <p v-if="!data?.length" class="text-gray-500 text-sm text-center py-4">
        <RocketLaunchIcon class="w-5 h-5 mx-auto mb-1 text-gray-600" />
        {{ $t('widgets.noDeployments') }}
      </p>
    </div>
  </div>
</template>
