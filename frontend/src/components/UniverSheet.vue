<script setup>
import { TableCellsIcon } from '@heroicons/vue/24/outline'

defineProps({
  modelValue: String,
  readOnly: Boolean,
})
defineEmits(['update:modelValue', 'change'])

// UniverJS was removed (bundled React + heavy dependencies ~5MB+).
// Spreadsheet documents can still be viewed as JSON data.
// A lightweight replacement can be integrated when needed.
</script>

<template>
  <div class="flex flex-col items-center justify-center h-96 text-center border border-dashed border-dark-600 rounded-xl p-8">
    <TableCellsIcon class="w-16 h-16 text-gray-600 mb-4" />
    <h3 class="text-white font-semibold mb-2">Tabellenkalkulation</h3>
    <p class="text-gray-500 text-sm max-w-sm">
      Der integrierte Tabellen-Editor wurde entfernt. Das Dokument kann als JSON heruntergeladen werden.
    </p>
  </div>
</template>
