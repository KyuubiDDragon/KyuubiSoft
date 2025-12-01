<script setup>
import { ref, onMounted } from 'vue'
import api from '@/core/api/axios'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const users = ref([])
const isLoading = ref(true)
const error = ref(null)

onMounted(async () => {
  await loadUsers()
})

async function loadUsers() {
  isLoading.value = true
  error.value = null

  try {
    const response = await api.get('/api/v1/users')
    users.value = response.data.data || []
  } catch (err) {
    error.value = 'Fehler beim Laden der Benutzer'
    console.error(err)
  } finally {
    isLoading.value = false
  }
}

function getRoleBadgeClass(role) {
  const classes = {
    owner: 'bg-purple-600 text-white',
    admin: 'bg-red-600 text-white',
    editor: 'bg-blue-600 text-white',
    user: 'bg-green-600 text-white',
    viewer: 'bg-gray-600 text-white',
  }
  return classes[role] || 'bg-gray-500 text-white'
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-white">Benutzer</h1>
        <p class="text-gray-400 mt-1">Verwalte alle Benutzer des Systems</p>
      </div>
      <button
        class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors"
      >
        + Neuer Benutzer
      </button>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="flex justify-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-500"></div>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="bg-red-900/50 border border-red-700 rounded-lg p-4">
      <p class="text-red-400">{{ error }}</p>
    </div>

    <!-- Users Table -->
    <div v-else class="bg-dark-800 rounded-lg border border-dark-700 overflow-hidden">
      <table class="w-full">
        <thead class="bg-dark-700">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
              Benutzer
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
              E-Mail
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
              Rollen
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
              Status
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
              Registriert
            </th>
            <th class="px-6 py-3 text-right text-xs font-medium text-gray-400 uppercase tracking-wider">
              Aktionen
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-dark-700">
          <tr v-for="user in users" :key="user.id" class="hover:bg-dark-700/50">
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="flex items-center">
                <div class="w-10 h-10 rounded-full bg-primary-600 flex items-center justify-center">
                  <span class="text-sm font-semibold text-white">
                    {{ user.username?.[0]?.toUpperCase() || 'U' }}
                  </span>
                </div>
                <div class="ml-4">
                  <div class="text-sm font-medium text-white">{{ user.username }}</div>
                </div>
              </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm text-gray-300">{{ user.email }}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="flex gap-1 flex-wrap">
                <span
                  v-for="role in (user.roles || [])"
                  :key="role"
                  class="px-2 py-1 text-xs font-medium rounded-full"
                  :class="getRoleBadgeClass(role)"
                >
                  {{ role }}
                </span>
                <span v-if="!user.roles?.length" class="text-gray-500 text-sm">-</span>
              </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span
                class="px-2 py-1 text-xs font-medium rounded-full"
                :class="user.is_active ? 'bg-green-900 text-green-300' : 'bg-red-900 text-red-300'"
              >
                {{ user.is_active ? 'Aktiv' : 'Inaktiv' }}
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-400">
              {{ new Date(user.created_at).toLocaleDateString('de-DE') }}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
              <button class="text-primary-400 hover:text-primary-300 mr-3">
                Bearbeiten
              </button>
              <button class="text-red-400 hover:text-red-300">
                Löschen
              </button>
            </td>
          </tr>
          <tr v-if="users.length === 0">
            <td colspan="6" class="px-6 py-12 text-center text-gray-400">
              Keine Benutzer gefunden
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
