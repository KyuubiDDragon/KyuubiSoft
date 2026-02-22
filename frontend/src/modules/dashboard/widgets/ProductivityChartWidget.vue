<script setup>
defineProps({ widget: Object, data: Object })
</script>

<template>
  <div>
    <h3 class="text-lg font-semibold text-white mb-4">{{ widget.title }}</h3>

    <div v-if="data?.productivity_score" class="mb-4">
      <div class="flex items-center justify-between mb-2">
        <span class="text-gray-400 text-sm">Produktivitäts-Score</span>
        <span class="text-2xl font-bold text-white">{{ data.productivity_score.score }}/100</span>
      </div>
      <div class="h-2 bg-dark-600 rounded-full overflow-hidden">
        <div
          class="h-full rounded-full transition-all duration-500"
          :class="data.productivity_score.score >= 70 ? 'bg-green-500' : data.productivity_score.score >= 40 ? 'bg-yellow-500' : 'bg-red-500'"
          :style="{ width: `${data.productivity_score.score}%` }"
        ></div>
      </div>
      <div class="grid grid-cols-3 gap-2 mt-3 text-xs">
        <div class="text-center">
          <p class="text-gray-500">Aufgaben</p>
          <p class="text-white font-medium">{{ data.productivity_score.factors.tasks }}%</p>
        </div>
        <div class="text-center">
          <p class="text-gray-500">Zeit</p>
          <p class="text-white font-medium">{{ data.productivity_score.factors.time }}%</p>
        </div>
        <div class="text-center">
          <p class="text-gray-500">Konsistenz</p>
          <p class="text-white font-medium">{{ data.productivity_score.factors.consistency }}%</p>
        </div>
      </div>
    </div>

    <div v-if="data?.tasks_completed?.daily?.length" class="mt-4">
      <p class="text-sm text-gray-400 mb-2">Erledigte Aufgaben (14 Tage)</p>
      <div class="flex items-end gap-1 h-20">
        <div
          v-for="(day, i) in data.tasks_completed.daily.slice(-14)"
          :key="i"
          class="flex-1 bg-primary-500/20 rounded-t relative group/bar"
          :style="{ height: `${Math.min(100, (day.count / Math.max(...data.tasks_completed.daily.map(d => d.count || 1))) * 100)}%` }"
        >
          <div class="absolute bottom-0 left-0 right-0 bg-primary-500 rounded-t" style="height: 100%"></div>
          <div class="absolute -top-6 left-1/2 -translate-x-1/2 bg-dark-600 px-1 py-0.5 rounded text-xs text-white opacity-0 group-hover/bar:opacity-100 whitespace-nowrap z-10">
            {{ day.count }}
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
