<script setup>
import { formatDate } from './widgetUtils.js'
defineProps({ widget: Object, data: Object })
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-semibold text-white">{{ widget.title }}</h3>
      <router-link to="/kanban" class="text-sm text-primary-400 hover:text-primary-300">Boards</router-link>
    </div>
    <div class="grid grid-cols-2 gap-4">
      <div>
        <p class="text-sm text-gray-400 mb-2">Boards</p>
        <div class="space-y-1">
          <router-link
            v-for="board in (data?.boards || []).slice(0, 3)"
            :key="board.id"
            :to="`/kanban/${board.id}`"
            class="flex items-center justify-between p-2 rounded-lg hover:bg-dark-700/50 transition-colors"
          >
            <span class="text-sm text-white truncate">{{ board.title }}</span>
            <span class="text-xs text-gray-500">{{ board.card_count }}</span>
          </router-link>
        </div>
      </div>
      <div>
        <p class="text-sm text-gray-400 mb-2">Bald fällig</p>
        <div class="space-y-1">
          <div v-for="card in (data?.due_soon || []).slice(0, 3)" :key="card.id" class="p-2 rounded-lg bg-yellow-500/10">
            <p class="text-sm text-white truncate">{{ card.title }}</p>
            <p class="text-xs text-yellow-400">{{ formatDate(card.due_date) }}</p>
          </div>
          <p v-if="!data?.due_soon?.length" class="text-xs text-gray-500">Keine fälligen Karten</p>
        </div>
      </div>
    </div>
  </div>
</template>
