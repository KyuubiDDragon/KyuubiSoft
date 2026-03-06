<script setup>
import { useI18n } from 'vue-i18n'
const { t } = useI18n()
defineProps({ widget: Object, data: Object })
</script>

<template>
  <div>
    <h3 class="text-lg font-semibold text-white mb-4">{{ widget.title }}</h3>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <div class="bg-white/[0.04] rounded-lg p-4">
        <p class="text-gray-400 text-sm">{{ t('dashboard.lists') }}</p>
        <p class="text-2xl font-bold text-white">{{ data?.lists || 0 }}</p>
      </div>
      <div class="bg-white/[0.04] rounded-lg p-4">
        <p class="text-gray-400 text-sm">{{ $t('dashboardModule.openTasks') }}</p>
        <p class="text-2xl font-bold text-yellow-400">{{ data?.open_tasks || 0 }}</p>
      </div>
      <div class="bg-white/[0.04] rounded-lg p-4">
        <p class="text-gray-400 text-sm">{{ $t('documents.title') }}</p>
        <p class="text-2xl font-bold text-white">{{ data?.documents || 0 }}</p>
      </div>
      <div class="bg-white/[0.04] rounded-lg p-4">
        <p class="text-gray-400 text-sm">{{ t('dashboard.kanbanCards') }}</p>
        <p class="text-2xl font-bold text-white">{{ data?.kanban_cards || 0 }}</p>
      </div>
    </div>
  </div>
</template>
