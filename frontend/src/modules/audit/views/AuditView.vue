<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import {
  ArrowDownTrayIcon,
  FunnelIcon,
  XMarkIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  ClockIcon,
  UsersIcon,
  BoltIcon,
} from '@heroicons/vue/24/outline'
import { useAuditStore } from '@/modules/audit/stores/auditStore'
import AuditDetailModal from '@/modules/audit/components/AuditDetailModal.vue'

const auditStore = useAuditStore()

// Local state
const showDetailModal = ref(false)
const selectedEntry = ref(null)

// Action types for filter dropdown
const actionTypes = [
  { value: '', label: 'Alle Aktionen' },
  { value: 'login', label: 'Login' },
  { value: 'logout', label: 'Logout' },
  { value: 'create', label: 'Erstellen' },
  { value: 'update', label: 'Aktualisieren' },
  { value: 'delete', label: 'Löschen' },
  { value: 'export', label: 'Export' },
  { value: 'import', label: 'Import' },
]

// Computed
const paginationRange = computed(() => {
  const current = auditStore.pagination.page
  const total = auditStore.totalPages
  const range = []
  const delta = 2

  const start = Math.max(2, current - delta)
  const end = Math.min(total - 1, current + delta)

  range.push(1)

  if (start > 2) {
    range.push('...')
  }

  for (let i = start; i <= end; i++) {
    range.push(i)
  }

  if (end < total - 1) {
    range.push('...')
  }

  if (total > 1) {
    range.push(total)
  }

  return range
})

// Action badge color mapping
function getActionBadgeClass(action) {
  if (!action) return 'badge-neutral'
  const lower = action.toLowerCase()
  if (lower.includes('login') || lower.includes('auth') || lower.includes('logout')) return 'badge-primary'
  if (lower.includes('create') || lower.includes('register')) return 'badge-success'
  if (lower.includes('update') || lower.includes('edit')) return 'badge-warning'
  if (lower.includes('delete') || lower.includes('remove')) return 'badge-danger'
  return 'badge-neutral'
}

function formatTimestamp(dateString) {
  if (!dateString) return '-'
  return new Date(dateString).toLocaleString('de-DE', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function openDetail(entry) {
  selectedEntry.value = entry
  showDetailModal.value = true
}

function closeDetail() {
  showDetailModal.value = false
  selectedEntry.value = null
}

function goToPage(page) {
  if (page === '...' || page < 1 || page > auditStore.totalPages) return
  auditStore.setPage(page)
  auditStore.fetchEntries()
}

function applyFilters() {
  auditStore.pagination.page = 1
  auditStore.fetchEntries()
}

function clearFilters() {
  auditStore.resetFilters()
  auditStore.fetchEntries()
}

function handleExport() {
  auditStore.exportCsv()
}

// Watch for filter changes on select/input fields
watch(
  () => auditStore.filters.action,
  () => applyFilters()
)

// Lifecycle
onMounted(async () => {
  await Promise.all([
    auditStore.fetchEntries(),
    auditStore.fetchStats(),
  ])
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-white">Audit Log</h1>
        <p class="text-gray-400 mt-1">Alle Systemaktivitäten überwachen</p>
      </div>
      <button @click="handleExport" class="btn-primary">
        <ArrowDownTrayIcon class="w-5 h-5 mr-2" />
        Exportieren
      </button>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <!-- Events Today -->
      <div class="card-glass p-5">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-primary-500/15 flex items-center justify-center">
            <BoltIcon class="w-5 h-5 text-primary-400" />
          </div>
          <div>
            <p class="text-xs text-gray-500 uppercase tracking-wider">Ereignisse heute</p>
            <p class="text-xl font-bold text-white mt-0.5">{{ auditStore.stats.total_today }}</p>
          </div>
        </div>
      </div>

      <!-- Unique Users -->
      <div class="card-glass p-5">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-emerald-500/15 flex items-center justify-center">
            <UsersIcon class="w-5 h-5 text-emerald-400" />
          </div>
          <div>
            <p class="text-xs text-gray-500 uppercase tracking-wider">Aktive Benutzer</p>
            <p class="text-xl font-bold text-white mt-0.5">{{ auditStore.stats.unique_users }}</p>
          </div>
        </div>
      </div>

      <!-- Most Common Action -->
      <div class="card-glass p-5">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-amber-500/15 flex items-center justify-center">
            <ClockIcon class="w-5 h-5 text-amber-400" />
          </div>
          <div>
            <p class="text-xs text-gray-500 uppercase tracking-wider">Häufigste Aktion</p>
            <p class="text-xl font-bold text-white mt-0.5">{{ auditStore.stats.most_common_action }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="card-glass p-4">
      <div class="flex flex-wrap items-center gap-3">
        <FunnelIcon class="w-5 h-5 text-gray-500 shrink-0" />

        <!-- Action Type -->
        <select
          :value="auditStore.filters.action"
          @change="auditStore.setFilter('action', $event.target.value)"
          class="select w-auto min-w-[160px]"
        >
          <option v-for="type in actionTypes" :key="type.value" :value="type.value">
            {{ type.label }}
          </option>
        </select>

        <!-- User Search -->
        <input
          :value="auditStore.filters.search"
          @input="auditStore.setFilter('search', $event.target.value)"
          @keyup.enter="applyFilters"
          type="text"
          placeholder="Benutzer suchen..."
          class="input w-auto min-w-[180px]"
        />

        <!-- Date From -->
        <input
          :value="auditStore.filters.date_from"
          @change="auditStore.setFilter('date_from', $event.target.value); applyFilters()"
          type="date"
          class="input w-auto"
          title="Von Datum"
        />

        <!-- Date To -->
        <input
          :value="auditStore.filters.date_to"
          @change="auditStore.setFilter('date_to', $event.target.value); applyFilters()"
          type="date"
          class="input w-auto"
          title="Bis Datum"
        />

        <!-- IP Address -->
        <input
          :value="auditStore.filters.ip_address"
          @input="auditStore.setFilter('ip_address', $event.target.value)"
          @keyup.enter="applyFilters"
          type="text"
          placeholder="IP-Adresse..."
          class="input w-auto min-w-[140px]"
        />

        <!-- Search button -->
        <button @click="applyFilters" class="btn-secondary">
          Suchen
        </button>

        <!-- Clear Filters -->
        <button
          v-if="auditStore.hasActiveFilters"
          @click="clearFilters"
          class="btn-ghost text-gray-400 hover:text-white"
          title="Filter zurücksetzen"
        >
          <XMarkIcon class="w-4 h-4 mr-1" />
          Zurücksetzen
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="auditStore.loading" class="flex justify-center py-12">
      <div class="w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full animate-spin"></div>
    </div>

    <!-- Table -->
    <div v-else class="card-glass overflow-hidden">
      <table class="table-glass">
        <thead>
          <tr>
            <th>Zeitpunkt</th>
            <th>Benutzer</th>
            <th>Aktion</th>
            <th>Entität</th>
            <th>IP-Adresse</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="entry in auditStore.entries"
            :key="entry.id"
            @click="openDetail(entry)"
            class="cursor-pointer hover:bg-white/[0.04] transition-colors"
          >
            <td class="text-gray-400 text-xs font-mono whitespace-nowrap">
              {{ formatTimestamp(entry.created_at) }}
            </td>
            <td>
              <span class="text-white text-sm">
                {{ entry.user_name || entry.user_email || '-' }}
              </span>
            </td>
            <td>
              <span :class="getActionBadgeClass(entry.action)">
                {{ entry.action }}
              </span>
            </td>
            <td>
              <span class="text-gray-300 text-sm">{{ entry.entity_type || '-' }}</span>
              <span v-if="entry.entity_name" class="text-gray-500 text-xs ml-1">
                ({{ entry.entity_name }})
              </span>
            </td>
            <td class="text-gray-400 text-xs font-mono">
              {{ entry.ip_address || '-' }}
            </td>
          </tr>
          <tr v-if="auditStore.entries.length === 0">
            <td colspan="5" class="text-center py-12 text-gray-500">
              Keine Audit-Einträge gefunden
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div
      v-if="auditStore.totalPages > 1"
      class="flex items-center justify-between"
    >
      <p class="text-sm text-gray-500">
        {{ auditStore.pagination.total }} Einträge gesamt
      </p>
      <div class="flex items-center gap-1">
        <button
          @click="goToPage(auditStore.pagination.page - 1)"
          :disabled="auditStore.pagination.page <= 1"
          class="btn-icon-sm"
        >
          <ChevronLeftIcon class="w-4 h-4" />
        </button>

        <button
          v-for="(page, idx) in paginationRange"
          :key="idx"
          @click="goToPage(page)"
          class="min-w-[2rem] h-8 px-2 rounded-lg text-sm font-medium transition-colors"
          :class="page === auditStore.pagination.page
            ? 'bg-primary-600 text-white'
            : page === '...'
              ? 'text-gray-500 cursor-default'
              : 'text-gray-400 hover:bg-white/[0.06] hover:text-white'"
        >
          {{ page }}
        </button>

        <button
          @click="goToPage(auditStore.pagination.page + 1)"
          :disabled="auditStore.pagination.page >= auditStore.totalPages"
          class="btn-icon-sm"
        >
          <ChevronRightIcon class="w-4 h-4" />
        </button>
      </div>
    </div>

    <!-- Detail Modal -->
    <AuditDetailModal
      v-if="showDetailModal && selectedEntry"
      :entry="selectedEntry"
      @close="closeDetail"
    />
  </div>
</template>
