<script setup>
import { ref, onMounted, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useProjectStore } from '@/stores/project'
import api from '@/core/api/axios'
import {
  ListBulletIcon,
  DocumentTextIcon,
  ClockIcon,
  PlusIcon,
} from '@heroicons/vue/24/outline'

const authStore = useAuthStore()
const projectStore = useProjectStore()

const stats = ref({
  total_lists: 0,
  open_tasks: 0,
  total_documents: 0,
})

const recentLists = ref([])
const recentDocuments = ref([])
const isLoading = ref(true)

async function fetchDashboard() {
  isLoading.value = true
  try {
    const params = projectStore.selectedProjectId
      ? { project_id: projectStore.selectedProjectId }
      : {}
    const response = await api.get('/api/v1/dashboard', { params })
    if (response.data.data?.quick_stats) {
      stats.value = response.data.data.quick_stats
    }
    if (response.data.data?.recent_lists) {
      recentLists.value = response.data.data.recent_lists
    }
    if (response.data.data?.recent_documents) {
      recentDocuments.value = response.data.data.recent_documents
    }
  } catch (error) {
    console.error('Failed to load dashboard:', error)
  } finally {
    isLoading.value = false
  }
}

// Watch for project changes
watch(() => projectStore.selectedProjectId, () => {
  fetchDashboard()
})

onMounted(() => {
  fetchDashboard()
})

function formatDate(dateString) {
  if (!dateString) return '-'
  return new Date(dateString).toLocaleDateString('de-DE', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

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

        <!-- Empty state -->
        <div v-if="recentLists.length === 0" class="text-center py-8 text-gray-500">
          <ListBulletIcon class="w-12 h-12 mx-auto mb-2 opacity-50" />
          <p>Noch keine Listen erstellt</p>
          <RouterLink to="/lists" class="btn-primary mt-4 inline-block">
            <PlusIcon class="w-4 h-4 mr-1 inline" />
            Liste erstellen
          </RouterLink>
        </div>

        <!-- List items -->
        <div v-else class="space-y-3">
          <RouterLink
            v-for="list in recentLists"
            :key="list.id"
            :to="`/lists?open=${list.id}`"
            class="flex items-center gap-3 p-3 rounded-lg bg-dark-700/50 hover:bg-dark-700 transition-colors group"
          >
            <div
              class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
              :style="{ backgroundColor: list.color || '#3B82F6' }"
            >
              <ListBulletIcon class="w-4 h-4 text-white" />
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-white font-medium truncate">{{ list.title }}</p>
              <p class="text-sm text-gray-500">
                {{ list.open_count }} offen von {{ list.item_count }}
              </p>
            </div>
            <span class="text-xs text-gray-500 hidden sm:block">
              {{ formatDate(list.updated_at) }}
            </span>
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

        <!-- Empty state -->
        <div v-if="recentDocuments.length === 0" class="text-center py-8 text-gray-500">
          <DocumentTextIcon class="w-12 h-12 mx-auto mb-2 opacity-50" />
          <p>Noch keine Dokumente erstellt</p>
          <RouterLink to="/documents" class="btn-primary mt-4 inline-block">
            <PlusIcon class="w-4 h-4 mr-1 inline" />
            Dokument erstellen
          </RouterLink>
        </div>

        <!-- Document items -->
        <div v-else class="space-y-3">
          <RouterLink
            v-for="doc in recentDocuments"
            :key="doc.id"
            :to="`/documents?open=${doc.id}`"
            class="flex items-center gap-3 p-3 rounded-lg bg-dark-700/50 hover:bg-dark-700 transition-colors group"
          >
            <div class="w-8 h-8 rounded-lg bg-green-500/20 flex items-center justify-center flex-shrink-0">
              <DocumentTextIcon class="w-4 h-4 text-green-400" />
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-white font-medium truncate">{{ doc.title }}</p>
              <p class="text-sm text-gray-500">{{ doc.format }}</p>
            </div>
            <span class="text-xs text-gray-500 hidden sm:block">
              {{ formatDate(doc.updated_at) }}
            </span>
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
