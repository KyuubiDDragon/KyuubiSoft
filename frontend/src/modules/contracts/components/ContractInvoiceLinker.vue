<script setup>
import { ref, watch } from 'vue'
import api from '@/core/api/axios'
import { XMarkIcon, LinkIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  show: Boolean,
  contractId: { type: String, default: null },
  existingInvoiceIds: { type: Array, default: () => [] },
})

const emit = defineEmits(['close', 'link'])

const invoices = ref([])
const searchQuery = ref('')
const isLoading = ref(false)

watch(() => props.show, async (val) => {
  if (val) {
    searchQuery.value = ''
    await loadInvoices()
  }
})

async function loadInvoices() {
  isLoading.value = true
  try {
    const res = await api.get('/api/v1/invoices')
    invoices.value = (res.data.data?.items || []).filter(
      inv => !props.existingInvoiceIds.includes(inv.id)
    )
  } catch {
    invoices.value = []
  } finally {
    isLoading.value = false
  }
}

function filteredInvoices() {
  if (!searchQuery.value.trim()) return invoices.value
  const q = searchQuery.value.toLowerCase().trim()
  return invoices.value.filter(inv =>
    (inv.invoice_number || '').toLowerCase().includes(q) ||
    (inv.client_name || '').toLowerCase().includes(q)
  )
}

function formatCurrency(amount) {
  return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(amount || 0)
}

function formatDate(dateStr) {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString('de-DE')
}
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition ease-out duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition ease-in duration-150"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="show"
        class="fixed inset-0 bg-black/60 backdrop-blur-md flex items-center justify-center z-50 p-4"
        @click.self="$emit('close')"
      >
        <div class="modal w-full max-w-md max-h-[70vh] flex flex-col">
          <div class="px-6 py-4 border-b border-white/[0.06] flex items-center justify-between">
            <h2 class="text-lg font-bold text-white">Rechnung verknuepfen</h2>
            <button @click="$emit('close')" class="p-2 rounded-lg text-gray-400 hover:text-white hover:bg-white/[0.04]">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <div class="px-6 py-3">
            <div class="relative">
              <MagnifyingGlassIcon class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-500" />
              <input v-model="searchQuery" type="text" placeholder="Rechnung suchen..." class="input pl-9 text-sm" />
            </div>
          </div>

          <div class="flex-1 overflow-y-auto px-6 pb-4">
            <div v-if="isLoading" class="text-center py-8 text-gray-500 text-sm">Lade Rechnungen...</div>
            <div v-else-if="filteredInvoices().length === 0" class="text-center py-8 text-gray-500 text-sm">
              Keine verfuegbaren Rechnungen
            </div>
            <div v-else class="space-y-1.5">
              <button
                v-for="inv in filteredInvoices()"
                :key="inv.id"
                @click="$emit('link', inv.id)"
                class="w-full flex items-center justify-between p-3 rounded-lg bg-white/[0.03] hover:bg-white/[0.06] transition-colors text-left"
              >
                <div>
                  <div class="text-sm text-white font-medium">{{ inv.invoice_number }}</div>
                  <div class="text-xs text-gray-400">{{ inv.client_name || '-' }} &middot; {{ formatDate(inv.issue_date) }}</div>
                </div>
                <div class="flex items-center gap-2">
                  <span class="text-sm font-semibold text-white">{{ formatCurrency(inv.total) }}</span>
                  <LinkIcon class="w-4 h-4 text-gray-500" />
                </div>
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
