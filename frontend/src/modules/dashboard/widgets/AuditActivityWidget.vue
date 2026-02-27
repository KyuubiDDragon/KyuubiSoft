<script setup>
import { computed } from 'vue'
import { ClipboardDocumentListIcon } from '@heroicons/vue/24/outline'

const props = defineProps({ widget: Object, data: Array })

function getActionBadgeClass(action) {
  if (!action) return 'badge-neutral'
  const lower = action.toLowerCase()
  if (lower.includes('login') || lower.includes('auth') || lower.includes('logout')) return 'badge-primary'
  if (lower.includes('create') || lower.includes('register')) return 'badge-success'
  if (lower.includes('update') || lower.includes('edit')) return 'badge-warning'
  if (lower.includes('delete') || lower.includes('remove')) return 'badge-danger'
  return 'badge-neutral'
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

const recentEntries = computed(() => {
  return (props.data || []).slice(0, 5)
})
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-semibold text-white">{{ widget.title }}</h3>
      <router-link to="/audit" class="text-sm text-primary-400 hover:text-primary-300">Alle</router-link>
    </div>
    <div class="space-y-2 max-h-64 overflow-y-auto">
      <div
        v-for="entry in recentEntries"
        :key="entry.id || entry.created_at"
        class="flex items-start gap-3 p-2 rounded-lg hover:bg-white/[0.03] transition-colors"
      >
        <ClipboardDocumentListIcon class="w-4 h-4 text-gray-500 flex-shrink-0 mt-0.5" />
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2">
            <span :class="getActionBadgeClass(entry.action)" class="text-2xs">
              {{ entry.action }}
            </span>
            <span class="text-gray-400 text-xs truncate">
              {{ entry.entity_type || '' }}
              <span v-if="entry.entity_name" class="text-gray-500">({{ entry.entity_name }})</span>
            </span>
          </div>
          <p class="text-xs text-gray-500 mt-0.5">
            {{ entry.user_name || entry.user_email || 'System' }}
          </p>
        </div>
        <span class="text-2xs text-gray-600 whitespace-nowrap mt-0.5">
          {{ formatRelativeTime(entry.created_at) }}
        </span>
      </div>
      <p v-if="!data?.length" class="text-gray-500 text-sm text-center py-4">Keine Audit-Aktivität</p>
    </div>
  </div>
</template>
