<script setup>
import { LinkIcon } from '@heroicons/vue/24/outline'

defineProps({ widget: Object, data: Object })
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-semibold text-white">{{ widget.title }}</h3>
      <router-link to="/links" class="text-sm text-primary-400 hover:text-primary-300">Alle</router-link>
    </div>
    <div class="text-center">
      <LinkIcon class="w-8 h-8 text-primary-400 mx-auto mb-2" />
      <p class="text-2xl font-bold text-white">{{ data?.total_links || 0 }}</p>
      <p class="text-gray-500 text-sm">Kurzlinks</p>
      <p class="text-xl font-bold text-green-400 mt-2">{{ data?.total_clicks || 0 }}</p>
      <p class="text-gray-500 text-sm">Klicks gesamt</p>
    </div>
  </div>
</template>
