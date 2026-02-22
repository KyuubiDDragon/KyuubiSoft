<script setup>
defineProps({ widget: Object, data: Array })
</script>

<template>
  <div>
    <h3 class="text-lg font-semibold text-white mb-4">{{ widget.title }}</h3>
    <div class="space-y-2">
      <div
        v-for="note in (data || []).slice(0, 4)"
        :key="note.id"
        class="p-2 rounded-lg bg-dark-700/50 text-sm text-gray-300 line-clamp-2"
      >
        {{ note.content }}
      </div>
      <p v-if="!data?.length" class="text-gray-500 text-sm text-center py-4">Keine Notizen</p>
    </div>
  </div>
</template>
