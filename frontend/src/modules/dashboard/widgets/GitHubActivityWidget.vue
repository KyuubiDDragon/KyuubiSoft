<script setup>
defineProps({ widget: Object, data: Object })

function timeAgo(dateString) {
  if (!dateString) return '-'
  const now = new Date()
  const date = new Date(dateString)
  const diffMs = now - date
  const diffMin = Math.floor(diffMs / 60000)
  const diffH = Math.floor(diffMs / 3600000)
  const diffD = Math.floor(diffMs / 86400000)

  if (diffMin < 1) return 'gerade eben'
  if (diffMin < 60) return `vor ${diffMin} Min`
  if (diffH < 24) return `vor ${diffH} Std`
  return `vor ${diffD} Tag${diffD > 1 ? 'en' : ''}`
}
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-semibold text-white">{{ widget.title }}</h3>
      <router-link to="/git" class="text-sm text-primary-400 hover:text-primary-300">Öffnen</router-link>
    </div>

    <!-- Stat boxes -->
    <div class="grid grid-cols-2 gap-2 mb-4">
      <div class="bg-white/[0.04] rounded-lg p-3 text-center">
        <p class="text-2xl font-bold text-white">{{ data?.commits_today || 0 }}</p>
        <p class="text-xs text-gray-500">Heute</p>
      </div>
      <div class="bg-white/[0.04] rounded-lg p-3 text-center">
        <p class="text-2xl font-bold text-white">{{ data?.commits_week || 0 }}</p>
        <p class="text-xs text-gray-500">Woche</p>
      </div>
    </div>

    <!-- Recent commits -->
    <div class="space-y-2 max-h-48 overflow-y-auto">
      <div
        v-for="commit in (data?.recent_commits || []).slice(0, 5)"
        :key="commit.sha"
        class="flex items-center gap-3 p-2 rounded-lg hover:bg-white/[0.04] transition-colors"
      >
        <div class="flex-1 min-w-0">
          <p class="text-sm text-white truncate">{{ commit.message }}</p>
          <p class="text-xs text-gray-500">{{ commit.repo }}</p>
        </div>
        <span class="text-xs text-gray-500 flex-shrink-0">{{ timeAgo(commit.date) }}</span>
      </div>
      <p v-if="!data?.recent_commits?.length" class="text-gray-500 text-sm text-center py-4">Keine Commits</p>
    </div>
  </div>
</template>
