<script setup>
import RecurringTasksManager from '@/components/RecurringTasksManager.vue'
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div>
      <h1 class="text-2xl font-bold text-white">Wiederkehrende Aufgaben</h1>
      <p class="text-gray-400 mt-1">Verwalte automatisch wiederkehrende Aufgaben und Erinnerungen</p>
    </div>

    <!-- Manager Component -->
    <RecurringTasksManager />
  </div>
</template>
