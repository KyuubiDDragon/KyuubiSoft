<script setup>
import { ref, onMounted } from 'vue'
import { PlusIcon, ListBulletIcon } from '@heroicons/vue/24/outline'
import api from '@/core/api/axios'

const lists = ref([])
const isLoading = ref(true)
const showCreateModal = ref(false)

onMounted(async () => {
  try {
    const response = await api.get('/api/v1/lists')
    lists.value = response.data.data?.items || []
  } catch (error) {
    console.error('Failed to load lists:', error)
  } finally {
    isLoading.value = false
  }
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-white">Listen</h1>
        <p class="text-gray-400 mt-1">Verwalte deine Listen und Aufgaben</p>
      </div>
      <button
        @click="showCreateModal = true"
        class="btn-primary"
      >
        <PlusIcon class="w-5 h-5 mr-2" />
        Neue Liste
      </button>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="flex justify-center py-12">
      <div class="w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full animate-spin"></div>
    </div>

    <!-- Empty state -->
    <div
      v-else-if="lists.length === 0"
      class="card p-12 text-center"
    >
      <ListBulletIcon class="w-16 h-16 mx-auto text-gray-600 mb-4" />
      <h3 class="text-lg font-medium text-white mb-2">Keine Listen vorhanden</h3>
      <p class="text-gray-400 mb-6">Erstelle deine erste Liste, um loszulegen.</p>
      <button
        @click="showCreateModal = true"
        class="btn-primary"
      >
        <PlusIcon class="w-5 h-5 mr-2" />
        Erste Liste erstellen
      </button>
    </div>

    <!-- Lists grid -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <div
        v-for="list in lists"
        :key="list.id"
        class="card-hover p-6 cursor-pointer"
      >
        <div class="flex items-start justify-between">
          <div
            class="w-10 h-10 rounded-lg flex items-center justify-center"
            :style="{ backgroundColor: list.color || '#3B82F6' }"
          >
            <ListBulletIcon class="w-5 h-5 text-white" />
          </div>
          <span class="badge badge-primary">{{ list.type }}</span>
        </div>
        <h3 class="text-lg font-medium text-white mt-4">{{ list.title }}</h3>
        <p class="text-gray-400 text-sm mt-1 line-clamp-2">
          {{ list.description || 'Keine Beschreibung' }}
        </p>
        <div class="flex items-center gap-4 mt-4 text-sm text-gray-500">
          <span>{{ list.item_count || 0 }} Einträge</span>
        </div>
      </div>
    </div>
  </div>
</template>
