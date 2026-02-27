<script setup>
defineProps({ widget: Object, data: Object })

function getBarColor(usage) {
  if (usage > 80) return 'bg-red-500'
  if (usage >= 60) return 'bg-yellow-500'
  return 'bg-green-500'
}
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-semibold text-white">{{ widget.title }}</h3>
      <router-link to="/system" class="text-sm text-primary-400 hover:text-primary-300">Öffnen</router-link>
    </div>

    <p class="text-xs text-gray-500 mb-4">Uptime: {{ data?.uptime || '-' }}</p>

    <!-- CPU -->
    <div class="mb-3">
      <div class="flex items-center justify-between mb-1">
        <span class="text-sm text-gray-400">CPU ({{ data?.cpu?.cores || 0 }} Kerne)</span>
        <span class="text-sm font-medium text-white">{{ data?.cpu?.usage || 0 }}%</span>
      </div>
      <div class="w-full h-2 rounded-full bg-white/[0.08]">
        <div
          class="h-2 rounded-full transition-all duration-500"
          :class="getBarColor(data?.cpu?.usage || 0)"
          :style="{ width: (data?.cpu?.usage || 0) + '%' }"
        ></div>
      </div>
    </div>

    <!-- RAM -->
    <div class="mb-3">
      <div class="flex items-center justify-between mb-1">
        <span class="text-sm text-gray-400">RAM ({{ data?.memory?.used || '0 GB' }} / {{ data?.memory?.total || '0 GB' }})</span>
        <span class="text-sm font-medium text-white">{{ data?.memory?.usage || 0 }}%</span>
      </div>
      <div class="w-full h-2 rounded-full bg-white/[0.08]">
        <div
          class="h-2 rounded-full transition-all duration-500"
          :class="getBarColor(data?.memory?.usage || 0)"
          :style="{ width: (data?.memory?.usage || 0) + '%' }"
        ></div>
      </div>
    </div>

    <!-- Disk -->
    <div>
      <div class="flex items-center justify-between mb-1">
        <span class="text-sm text-gray-400">Festplatte ({{ data?.disk?.used || '0 GB' }} / {{ data?.disk?.total || '0 GB' }})</span>
        <span class="text-sm font-medium text-white">{{ data?.disk?.usage || 0 }}%</span>
      </div>
      <div class="w-full h-2 rounded-full bg-white/[0.08]">
        <div
          class="h-2 rounded-full transition-all duration-500"
          :class="getBarColor(data?.disk?.usage || 0)"
          :style="{ width: (data?.disk?.usage || 0) + '%' }"
        ></div>
      </div>
    </div>
  </div>
</template>
