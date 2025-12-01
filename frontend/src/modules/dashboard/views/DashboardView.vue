<script setup>
import { ref, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import api from '@/core/api/axios'
import {
  ListBulletIcon,
  DocumentTextIcon,
  CheckCircleIcon,
  ClockIcon,
} from '@heroicons/vue/24/outline'

const authStore = useAuthStore()

const stats = ref({
  total_lists: 0,
  open_tasks: 0,
  total_documents: 0,
})

const isLoading = ref(true)

onMounted(async () => {
  try {
    const response = await api.get('/api/v1/dashboard')
    if (response.data.data?.quick_stats) {
      stats.value = response.data.data.quick_stats
    }
  } catch (error) {
    console.error('Failed to load dashboard:', error)
  } finally {
    isLoading.value = false
  }
})

const statCards = [
  {
    name: 'Listen',
    key: 'total_lists',
    icon: ListBulletIcon,
    color: 'bg-blue-500',
    href: '/lists',
  },
  {
    name: 'Offene Aufgaben',
    key: 'open_tasks',
    icon: ClockIcon,
    color: 'bg-yellow-500',
    href: '/lists',
  },
  {
    name: 'Dokumente',
    key: 'total_documents',
    icon: DocumentTextIcon,
    color: 'bg-green-500',
    href: '/documents',
  },
]
</script>

<template>
  <div class="space-y-6">
    <!-- Welcome header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-white">
          Willkommen zurück, {{ authStore.user?.username || 'User' }}!
        </h1>
        <p class="text-gray-400 mt-1">
          Hier ist ein Überblick über deine Aktivitäten.
        </p>
      </div>
    </div>

    <!-- Stats cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <RouterLink
        v-for="stat in statCards"
        :key="stat.key"
        :to="stat.href"
        class="card-hover p-6 group"
      >
        <div class="flex items-center justify-between">
          <div>
            <p class="text-gray-400 text-sm">{{ stat.name }}</p>
            <p class="text-3xl font-bold text-white mt-1">
              <span v-if="isLoading" class="animate-pulse">--</span>
              <span v-else>{{ stats[stat.key] }}</span>
            </p>
          </div>
          <div
            :class="stat.color"
            class="w-12 h-12 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform"
          >
            <component :is="stat.icon" class="w-6 h-6 text-white" />
          </div>
        </div>
      </RouterLink>
    </div>

    <!-- Quick actions -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Recent lists -->
      <div class="card p-6">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-semibold text-white">Letzte Listen</h2>
          <RouterLink to="/lists" class="text-sm link">
            Alle anzeigen
          </RouterLink>
        </div>
        <div class="text-center py-8 text-gray-500">
          <ListBulletIcon class="w-12 h-12 mx-auto mb-2 opacity-50" />
          <p>Noch keine Listen erstellt</p>
          <RouterLink to="/lists" class="btn-primary mt-4 inline-block">
            Liste erstellen
          </RouterLink>
        </div>
      </div>

      <!-- Recent documents -->
      <div class="card p-6">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-semibold text-white">Letzte Dokumente</h2>
          <RouterLink to="/documents" class="text-sm link">
            Alle anzeigen
          </RouterLink>
        </div>
        <div class="text-center py-8 text-gray-500">
          <DocumentTextIcon class="w-12 h-12 mx-auto mb-2 opacity-50" />
          <p>Noch keine Dokumente erstellt</p>
          <RouterLink to="/documents" class="btn-primary mt-4 inline-block">
            Dokument erstellen
          </RouterLink>
        </div>
      </div>
    </div>

    <!-- System info (for owners/admins) -->
    <div
      v-if="authStore.hasRole('owner') || authStore.hasRole('admin')"
      class="card p-6"
    >
      <h2 class="text-lg font-semibold text-white mb-4">System-Status</h2>
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-dark-700/50 rounded-lg p-4">
          <p class="text-gray-400 text-sm">API Status</p>
          <p class="text-green-400 font-semibold flex items-center gap-2 mt-1">
            <span class="w-2 h-2 bg-green-500 rounded-full"></span>
            Online
          </p>
        </div>
        <div class="bg-dark-700/50 rounded-lg p-4">
          <p class="text-gray-400 text-sm">Datenbank</p>
          <p class="text-green-400 font-semibold flex items-center gap-2 mt-1">
            <span class="w-2 h-2 bg-green-500 rounded-full"></span>
            Verbunden
          </p>
        </div>
        <div class="bg-dark-700/50 rounded-lg p-4">
          <p class="text-gray-400 text-sm">Cache</p>
          <p class="text-green-400 font-semibold flex items-center gap-2 mt-1">
            <span class="w-2 h-2 bg-green-500 rounded-full"></span>
            Aktiv
          </p>
        </div>
        <div class="bg-dark-700/50 rounded-lg p-4">
          <p class="text-gray-400 text-sm">Version</p>
          <p class="text-white font-semibold mt-1">1.0.0</p>
        </div>
      </div>
    </div>
  </div>
</template>
