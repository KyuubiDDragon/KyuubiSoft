<script setup>
defineProps({ widget: Object, data: Object })
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-semibold text-white">{{ widget.title }}</h3>
      <router-link to="/storage" class="text-sm text-primary-400 hover:text-primary-300">Öffnen</router-link>
    </div>
    <div class="text-center">
      <!-- Progress Ring -->
      <div class="relative inline-flex items-center justify-center w-24 h-24 mb-3">
        <svg class="w-24 h-24 transform -rotate-90">
          <circle cx="48" cy="48" r="40" stroke="currentColor" stroke-width="8" fill="none" class="text-white/[0.08]" />
          <circle
            cx="48"
            cy="48"
            r="40"
            stroke="currentColor"
            stroke-width="8"
            fill="none"
            :stroke-dasharray="251.2"
            :stroke-dashoffset="251.2 - (251.2 * (data?.usage_percent || 0) / 100)"
            class="text-primary-500 transition-all duration-500"
            stroke-linecap="round"
          />
        </svg>
        <span class="absolute text-xl font-bold text-white">{{ data?.usage_percent || 0 }}%</span>
      </div>
      <p class="text-white font-medium">{{ data?.used_formatted || '0 B' }}</p>
      <p class="text-gray-500 text-sm">von {{ data?.limit_formatted || '10 GB' }}</p>
      <p class="text-gray-400 text-xs mt-2">{{ data?.file_count || 0 }} Dateien</p>
    </div>
  </div>
</template>
