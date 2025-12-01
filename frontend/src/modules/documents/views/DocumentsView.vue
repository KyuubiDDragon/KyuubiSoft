<script setup>
import { ref, onMounted } from 'vue'
import { PlusIcon, DocumentTextIcon } from '@heroicons/vue/24/outline'
import api from '@/core/api/axios'

const documents = ref([])
const isLoading = ref(true)
const showCreateModal = ref(false)

onMounted(async () => {
  try {
    const response = await api.get('/api/v1/documents')
    documents.value = response.data.data?.items || []
  } catch (error) {
    console.error('Failed to load documents:', error)
  } finally {
    isLoading.value = false
  }
})

function formatDate(dateString) {
  if (!dateString) return '-'
  return new Date(dateString).toLocaleDateString('de-DE', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  })
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-white">Dokumente</h1>
        <p class="text-gray-400 mt-1">Verwalte deine Markdown-Dokumente</p>
      </div>
      <button
        @click="showCreateModal = true"
        class="btn-primary"
      >
        <PlusIcon class="w-5 h-5 mr-2" />
        Neues Dokument
      </button>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="flex justify-center py-12">
      <div class="w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full animate-spin"></div>
    </div>

    <!-- Empty state -->
    <div
      v-else-if="documents.length === 0"
      class="card p-12 text-center"
    >
      <DocumentTextIcon class="w-16 h-16 mx-auto text-gray-600 mb-4" />
      <h3 class="text-lg font-medium text-white mb-2">Keine Dokumente vorhanden</h3>
      <p class="text-gray-400 mb-6">Erstelle dein erstes Dokument mit dem Markdown-Editor.</p>
      <button
        @click="showCreateModal = true"
        class="btn-primary"
      >
        <PlusIcon class="w-5 h-5 mr-2" />
        Erstes Dokument erstellen
      </button>
    </div>

    <!-- Documents list -->
    <div v-else class="card overflow-hidden">
      <table class="w-full">
        <thead class="bg-dark-700/50">
          <tr>
            <th class="text-left px-6 py-3 text-sm font-medium text-gray-400">Titel</th>
            <th class="text-left px-6 py-3 text-sm font-medium text-gray-400">Format</th>
            <th class="text-left px-6 py-3 text-sm font-medium text-gray-400">Geändert</th>
            <th class="text-left px-6 py-3 text-sm font-medium text-gray-400">Erstellt</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-dark-700">
          <tr
            v-for="doc in documents"
            :key="doc.id"
            class="hover:bg-dark-700/30 cursor-pointer transition-colors"
          >
            <td class="px-6 py-4">
              <div class="flex items-center gap-3">
                <DocumentTextIcon class="w-5 h-5 text-gray-500" />
                <span class="font-medium text-white">{{ doc.title }}</span>
              </div>
            </td>
            <td class="px-6 py-4">
              <span class="badge badge-primary">{{ doc.format }}</span>
            </td>
            <td class="px-6 py-4 text-gray-400">{{ formatDate(doc.updated_at) }}</td>
            <td class="px-6 py-4 text-gray-400">{{ formatDate(doc.created_at) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
