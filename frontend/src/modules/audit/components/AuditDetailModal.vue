<script setup>
import { computed } from 'vue'
import { XMarkIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  entry: {
    type: Object,
    required: true,
  },
})

const emit = defineEmits(['close'])

const actionBadgeClass = computed(() => {
  const action = props.entry?.action?.toLowerCase() || ''
  if (action.includes('login') || action.includes('auth')) return 'badge-primary'
  if (action.includes('create') || action.includes('register')) return 'badge-success'
  if (action.includes('update') || action.includes('edit')) return 'badge-warning'
  if (action.includes('delete') || action.includes('remove')) return 'badge-danger'
  return 'badge-neutral'
})

const formattedTimestamp = computed(() => {
  if (!props.entry?.created_at) return '-'
  return new Date(props.entry.created_at).toLocaleString('de-DE', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  })
})

const hasChanges = computed(() => {
  return props.entry?.old_values || props.entry?.new_values
})

const changeKeys = computed(() => {
  const keys = new Set([
    ...Object.keys(props.entry?.old_values || {}),
    ...Object.keys(props.entry?.new_values || {}),
  ])
  return Array.from(keys)
})

const formattedDetails = computed(() => {
  if (!props.entry?.details) return null
  try {
    return JSON.stringify(props.entry.details, null, 2)
  } catch {
    return String(props.entry.details)
  }
})

function formatValue(value) {
  if (value === null || value === undefined) return 'null'
  if (typeof value === 'object') return JSON.stringify(value, null, 2)
  return String(value)
}
</script>

<template>
  <Teleport to="body">
    <div class="modal-overlay" @click.self="emit('close')">
      <div class="modal modal-lg">
        <!-- Header -->
        <div class="modal-header">
          <h2>Audit-Eintrag Details</h2>
          <button @click="emit('close')" class="btn-icon-sm">
            <XMarkIcon class="w-5 h-5" />
          </button>
        </div>

        <!-- Body -->
        <div class="modal-body space-y-6">
          <!-- Basic Info -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="text-xs text-gray-500 uppercase tracking-wider">Zeitpunkt</label>
              <p class="text-white text-sm mt-1">{{ formattedTimestamp }}</p>
            </div>
            <div>
              <label class="text-xs text-gray-500 uppercase tracking-wider">Aktion</label>
              <div class="mt-1">
                <span :class="actionBadgeClass">{{ entry.action }}</span>
              </div>
            </div>
            <div>
              <label class="text-xs text-gray-500 uppercase tracking-wider">Benutzer</label>
              <p class="text-white text-sm mt-1">
                {{ entry.user_name || entry.user_email || entry.user_id || '-' }}
              </p>
            </div>
            <div>
              <label class="text-xs text-gray-500 uppercase tracking-wider">IP-Adresse</label>
              <p class="text-white text-sm mt-1 font-mono">{{ entry.ip_address || '-' }}</p>
            </div>
            <div>
              <label class="text-xs text-gray-500 uppercase tracking-wider">Entität</label>
              <p class="text-white text-sm mt-1">
                {{ entry.entity_type || '-' }}
                <span v-if="entry.entity_id" class="text-gray-500 font-mono text-xs ml-1">#{{ entry.entity_id }}</span>
              </p>
            </div>
            <div>
              <label class="text-xs text-gray-500 uppercase tracking-wider">Entitätsname</label>
              <p class="text-white text-sm mt-1">{{ entry.entity_name || '-' }}</p>
            </div>
          </div>

          <!-- User Agent -->
          <div v-if="entry.user_agent">
            <label class="text-xs text-gray-500 uppercase tracking-wider">User Agent</label>
            <p class="text-gray-400 text-xs mt-1 font-mono break-all">{{ entry.user_agent }}</p>
          </div>

          <!-- Changes Diff -->
          <div v-if="hasChanges">
            <label class="text-xs text-gray-500 uppercase tracking-wider mb-2 block">Änderungen</label>
            <div class="card-inset overflow-hidden">
              <table class="w-full text-sm">
                <thead>
                  <tr class="border-b border-white/[0.06]">
                    <th class="px-4 py-2 text-left text-xs text-gray-500 uppercase">Feld</th>
                    <th class="px-4 py-2 text-left text-xs text-gray-500 uppercase">Vorher</th>
                    <th class="px-4 py-2 text-left text-xs text-gray-500 uppercase">Nachher</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="key in changeKeys"
                    :key="key"
                    class="border-b border-white/[0.04]"
                  >
                    <td class="px-4 py-2 text-gray-300 font-mono text-xs">{{ key }}</td>
                    <td class="px-4 py-2">
                      <span class="text-red-400 text-xs font-mono break-all">
                        {{ formatValue(entry.old_values?.[key]) }}
                      </span>
                    </td>
                    <td class="px-4 py-2">
                      <span class="text-green-400 text-xs font-mono break-all">
                        {{ formatValue(entry.new_values?.[key]) }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Details JSON -->
          <div v-if="formattedDetails">
            <label class="text-xs text-gray-500 uppercase tracking-wider mb-2 block">Details</label>
            <div class="card-inset p-4 overflow-x-auto">
              <pre class="text-xs text-gray-300 font-mono whitespace-pre-wrap">{{ formattedDetails }}</pre>
            </div>
          </div>

          <!-- Entry ID -->
          <div class="pt-2 border-t border-white/[0.06]">
            <span class="text-xs text-gray-600 font-mono">ID: {{ entry.id }}</span>
          </div>
        </div>

        <!-- Footer -->
        <div class="modal-footer">
          <button @click="emit('close')" class="btn-secondary">Schließen</button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
