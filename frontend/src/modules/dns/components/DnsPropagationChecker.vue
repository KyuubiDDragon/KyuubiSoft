<script setup>
import { ref } from 'vue'
import { XMarkIcon, ArrowPathIcon, CheckCircleIcon, XCircleIcon } from '@heroicons/vue/24/outline'
import { useDnsStore } from '@/modules/dns/stores/dnsStore'

const props = defineProps({
  show: { type: Boolean, required: true },
  record: { type: Object, default: null },
  domainName: { type: String, default: '' },
})

const emit = defineEmits(['close'])

const dnsStore = useDnsStore()
const checking = ref(false)
const result = ref(null)

async function checkPropagation() {
  if (!props.record) return
  checking.value = true
  result.value = null
  try {
    result.value = await dnsStore.checkPropagation(props.record.id)
  } finally {
    checking.value = false
  }
}

function close() {
  result.value = null
  checking.value = false
  emit('close')
}

function getQueryName() {
  if (!props.record || !props.domainName) return ''
  const name = props.record.name
  if (name === '@' || name === '') return props.domainName
  return name + '.' + props.domainName
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="show && record"
      class="fixed inset-0 z-50 flex items-center justify-center p-4"
    >
      <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="close"></div>
      <div class="relative card-glass w-full max-w-lg flex flex-col">
        <!-- Header -->
        <div class="flex items-center justify-between p-5 border-b border-white/[0.06]">
          <h3 class="text-lg font-semibold text-white">DNS-Propagation pruefen</h3>
          <button @click="close" class="btn-icon-sm">
            <XMarkIcon class="w-5 h-5" />
          </button>
        </div>

        <!-- Content -->
        <div class="p-5 space-y-4">
          <!-- Record Info -->
          <div class="bg-black/20 rounded-lg p-4 space-y-2">
            <div class="flex items-center justify-between">
              <span class="text-xs text-gray-500 uppercase tracking-wider">Abfrage</span>
              <code class="text-sm text-primary-400 font-mono">{{ getQueryName() }}</code>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-xs text-gray-500 uppercase tracking-wider">Typ</span>
              <span class="text-sm text-gray-300">{{ record.type }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-xs text-gray-500 uppercase tracking-wider">Erwarteter Wert</span>
              <code class="text-sm text-gray-300 font-mono max-w-[250px] truncate">{{ record.value }}</code>
            </div>
          </div>

          <!-- Check Button -->
          <button
            @click="checkPropagation"
            :disabled="checking"
            class="btn-primary w-full"
          >
            <ArrowPathIcon class="w-4 h-4 mr-2" :class="{ 'animate-spin': checking }" />
            {{ checking ? 'Pruefe...' : 'Propagation pruefen' }}
          </button>

          <!-- Result -->
          <div v-if="result" class="space-y-3">
            <!-- Status Badge -->
            <div class="flex items-center gap-2">
              <component
                :is="result.propagated ? CheckCircleIcon : XCircleIcon"
                class="w-5 h-5"
                :class="result.propagated ? 'text-emerald-400' : 'text-red-400'"
              />
              <span
                class="text-sm font-medium"
                :class="result.propagated ? 'text-emerald-400' : 'text-red-400'"
              >
                {{ result.propagated ? 'Propagiert' : 'Nicht propagiert' }}
              </span>
              <span class="text-xs text-gray-500 ml-auto">
                {{ new Date(result.checked_at).toLocaleTimeString('de-DE') }}
              </span>
            </div>

            <!-- DNS Results Table -->
            <div v-if="result.dns_results.length > 0" class="bg-black/20 rounded-lg overflow-hidden">
              <table class="w-full">
                <thead>
                  <tr class="border-b border-white/[0.06]">
                    <th class="text-left text-xs text-gray-500 font-medium p-3">Host</th>
                    <th class="text-left text-xs text-gray-500 font-medium p-3">Typ</th>
                    <th class="text-left text-xs text-gray-500 font-medium p-3">Wert</th>
                    <th class="text-right text-xs text-gray-500 font-medium p-3">TTL</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="(entry, idx) in result.dns_results"
                    :key="idx"
                    class="border-t border-white/[0.04]"
                  >
                    <td class="p-3 text-xs text-gray-400 font-mono">{{ entry.host }}</td>
                    <td class="p-3 text-xs text-gray-400">{{ entry.type }}</td>
                    <td class="p-3 text-xs text-gray-300 font-mono max-w-[200px] truncate">{{ entry.value }}</td>
                    <td class="p-3 text-xs text-gray-500 text-right">{{ entry.ttl ?? '-' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- No Results -->
            <div
              v-else
              class="bg-black/20 rounded-lg p-4 text-center text-sm text-gray-500"
            >
              Keine DNS-Eintraege gefunden.
            </div>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>
