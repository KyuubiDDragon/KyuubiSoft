<script setup>
import { formatDate } from './widgetUtils.js'
import { DocumentTextIcon } from '@heroicons/vue/24/outline'
defineProps({ widget: Object, data: Array })
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-semibold text-white">{{ widget.title }}</h3>
      <router-link to="/documents" class="text-sm text-primary-400 hover:text-primary-300">Alle</router-link>
    </div>
    <div class="space-y-2">
      <router-link
        v-for="doc in (data || []).slice(0, 5)"
        :key="doc.id"
        :to="`/documents?open=${doc.id}`"
        class="flex items-center gap-3 p-2 rounded-lg hover:bg-dark-700/50 transition-colors"
      >
        <DocumentTextIcon class="w-5 h-5 text-green-400 flex-shrink-0" />
        <div class="flex-1 min-w-0">
          <p class="text-sm text-white truncate">{{ doc.title }}</p>
          <p class="text-xs text-gray-500">{{ doc.format }}</p>
        </div>
        <span class="text-xs text-gray-500">{{ formatDate(doc.updated_at) }}</span>
      </router-link>
      <p v-if="!data?.length" class="text-gray-500 text-sm text-center py-4">Keine Dokumente</p>
    </div>
  </div>
</template>
