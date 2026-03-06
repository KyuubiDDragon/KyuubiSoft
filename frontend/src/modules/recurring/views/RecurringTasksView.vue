<script setup>
import RecurringTasksManager from '@/components/RecurringTasksManager.vue'
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div>
      <h1 class="text-2xl font-bold text-white">{{ $t('navigation.recurringTasks') }}</h1>
      <p class="text-gray-400 mt-1">{{ $t('recurringTasks.manageRecurringTasks') }}</p>
    </div>

    <!-- Manager Component -->
    <RecurringTasksManager />
  </div>
</template>
