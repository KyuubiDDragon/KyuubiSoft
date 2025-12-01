<script setup>
import { ref, onMounted } from 'vue'
import api from '@/core/api/axios'

const systemInfo = ref({
  version: '1.0.0',
  environment: 'production',
  phpVersion: '-',
  mysqlVersion: '-',
  redisStatus: '-',
})
const isLoading = ref(true)

onMounted(async () => {
  try {
    const response = await api.get('/api/v1/settings/system')
    if (response.data.data) {
      systemInfo.value = { ...systemInfo.value, ...response.data.data }
    }
  } catch (err) {
    console.error('Could not load system info:', err)
  } finally {
    isLoading.value = false
  }
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div>
      <h1 class="text-2xl font-bold text-white">System</h1>
      <p class="text-gray-400 mt-1">Systemeinstellungen und -informationen (nur Owner)</p>
    </div>

    <!-- System Info Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <!-- Version -->
      <div class="bg-dark-800 rounded-lg border border-dark-700 p-6">
        <h3 class="text-sm font-medium text-gray-400 uppercase tracking-wider">Version</h3>
        <p class="mt-2 text-2xl font-bold text-white">{{ systemInfo.version }}</p>
      </div>

      <!-- Environment -->
      <div class="bg-dark-800 rounded-lg border border-dark-700 p-6">
        <h3 class="text-sm font-medium text-gray-400 uppercase tracking-wider">Umgebung</h3>
        <p class="mt-2 text-2xl font-bold text-white capitalize">{{ systemInfo.environment }}</p>
      </div>

      <!-- PHP Version -->
      <div class="bg-dark-800 rounded-lg border border-dark-700 p-6">
        <h3 class="text-sm font-medium text-gray-400 uppercase tracking-wider">PHP Version</h3>
        <p class="mt-2 text-2xl font-bold text-white">{{ systemInfo.phpVersion }}</p>
      </div>
    </div>

    <!-- Danger Zone -->
    <div class="bg-dark-800 rounded-lg border border-red-900 p-6">
      <h2 class="text-lg font-semibold text-red-400 mb-4">Gefahrenzone</h2>
      <div class="space-y-4">
        <div class="flex items-center justify-between">
          <div>
            <h3 class="text-white font-medium">Cache leeren</h3>
            <p class="text-sm text-gray-400">Leert alle zwischengespeicherten Daten</p>
          </div>
          <button class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
            Cache leeren
          </button>
        </div>
        <div class="border-t border-dark-700"></div>
        <div class="flex items-center justify-between">
          <div>
            <h3 class="text-white font-medium">Alle Sessions beenden</h3>
            <p class="text-sm text-gray-400">Meldet alle Benutzer ab</p>
          </div>
          <button class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
            Sessions beenden
          </button>
        </div>
      </div>
    </div>

    <!-- Audit Log -->
    <div class="bg-dark-800 rounded-lg border border-dark-700 p-6">
      <h2 class="text-lg font-semibold text-white mb-4">Letzte Aktivitäten</h2>
      <p class="text-gray-400">Audit-Log wird hier angezeigt...</p>
    </div>
  </div>
</template>
