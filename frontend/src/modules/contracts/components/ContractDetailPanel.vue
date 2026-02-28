<script setup>
import { ref, watch, computed } from 'vue'
import api from '@/core/api/axios'
import {
  XMarkIcon,
  PencilIcon,
  ArrowDownTrayIcon,
  EyeIcon,
  DocumentDuplicateIcon,
  TrashIcon,
  ClockIcon,
  LinkIcon,
  PencilSquareIcon,
} from '@heroicons/vue/24/outline'
import { useContracts } from '../composables/useContracts'
import ContractInvoiceLinker from './ContractInvoiceLinker.vue'

const props = defineProps({
  show: Boolean,
  contract: { type: Object, default: null },
  clients: { type: Array, default: () => [] },
})

const emit = defineEmits(['close', 'status-change', 'sign', 'download-pdf', 'preview', 'delete', 'duplicate', 'link-invoice', 'unlink-invoice', 'reload'])

const { statusOptions, contractTypeLabels, formatCurrency, formatDate, getStatusInfo, getHistory } = useContracts()

const activeTab = ref('details')
const history = ref([])
const showInvoiceLinker = ref(false)

watch(() => props.contract?.id, async (newId) => {
  if (newId) {
    activeTab.value = 'details'
    history.value = await getHistory(newId)
  }
})

const tabs = [
  { id: 'details', label: 'Details' },
  { id: 'invoices', label: 'Rechnungen' },
  { id: 'history', label: 'Verlauf' },
]

const typeColors = {
  license: 'bg-blue-500/20 text-blue-300',
  development: 'bg-purple-500/20 text-purple-300',
  saas: 'bg-cyan-500/20 text-cyan-300',
  maintenance: 'bg-amber-500/20 text-amber-300',
  nda: 'bg-emerald-500/20 text-emerald-300',
}

const statusActions = computed(() => {
  if (!props.contract) return []
  const current = props.contract.status
  const transitions = {
    draft: ['sent'],
    sent: ['signed', 'cancelled'],
    signed: ['active'],
    active: ['expired', 'terminated'],
    expired: ['active'],
    cancelled: ['draft'],
    terminated: [],
  }
  return (transitions[current] || []).map(s => statusOptions.find(o => o.value === s)).filter(Boolean)
})

const partyASignedDisplay = computed(() => props.contract?.party_a_signed_at ? formatDate(props.contract.party_a_signed_at) : null)
const partyBSignedDisplay = computed(() => props.contract?.party_b_signed_at ? formatDate(props.contract.party_b_signed_at) : null)

async function handleLinkInvoice(invoiceId) {
  emit('link-invoice', props.contract.id, invoiceId)
  showInvoiceLinker.value = false
  setTimeout(() => emit('reload', props.contract), 500)
}

async function handleUnlinkInvoice(invoiceId) {
  emit('unlink-invoice', props.contract.id, invoiceId)
  setTimeout(() => emit('reload', props.contract), 500)
}
</script>

<template>
  <Transition
    enter-active-class="transition ease-out duration-300"
    enter-from-class="translate-x-full"
    enter-to-class="translate-x-0"
    leave-active-class="transition ease-in duration-200"
    leave-from-class="translate-x-0"
    leave-to-class="translate-x-full"
  >
    <div
      v-if="show && contract"
      class="fixed inset-y-0 right-0 w-full max-w-lg bg-[#0f1117]/95 backdrop-blur-2xl border-l border-white/[0.06] z-40 overflow-y-auto shadow-2xl"
    >
      <!-- Header -->
      <div class="sticky top-0 bg-[#0f1117]/90 backdrop-blur-xl z-10 px-6 py-4 border-b border-white/[0.06]">
        <div class="flex items-center justify-between">
          <div>
            <div class="flex items-center gap-2">
              <h2 class="text-lg font-bold text-white">{{ contract.contract_number }}</h2>
              <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium"
                :class="typeColors[contract.contract_type] || 'bg-gray-500/20 text-gray-300'">
                {{ contractTypeLabels[contract.contract_type] || contract.contract_type }}
              </span>
            </div>
            <p class="text-sm text-gray-400 mt-0.5">{{ contract.title }}</p>
          </div>
          <button @click="$emit('close')" class="p-2 rounded-lg text-gray-400 hover:text-white hover:bg-white/[0.04]">
            <XMarkIcon class="w-5 h-5" />
          </button>
        </div>

        <!-- Status + Actions -->
        <div class="flex items-center gap-2 mt-3">
          <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold"
            :class="getStatusInfo(contract.status).color + '/20 ' + getStatusInfo(contract.status).textColor">
            {{ getStatusInfo(contract.status).label }}
          </span>
          <button
            v-for="action in statusActions"
            :key="action.value"
            @click="$emit('status-change', contract, action.value)"
            class="px-2.5 py-1 rounded-full text-xs font-medium bg-white/[0.06] text-gray-300 hover:bg-white/[0.1] hover:text-white transition-colors"
          >
            {{ action.label }}
          </button>
        </div>

        <!-- Quick Actions -->
        <div class="flex gap-1.5 mt-3">
          <button @click="$emit('preview', contract)" class="flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs text-gray-400 hover:text-white hover:bg-white/[0.04]">
            <EyeIcon class="w-3.5 h-3.5" /> Vorschau
          </button>
          <button @click="$emit('download-pdf', contract)" class="flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs text-gray-400 hover:text-white hover:bg-white/[0.04]">
            <ArrowDownTrayIcon class="w-3.5 h-3.5" /> PDF
          </button>
          <button v-if="!contract.party_a_signed_at" @click="$emit('sign', contract.id, 'a')" class="flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs text-emerald-400 hover:text-emerald-300 hover:bg-emerald-500/10">
            <PencilSquareIcon class="w-3.5 h-3.5" /> Unterschreiben (Sie)
          </button>
          <button v-if="!contract.party_b_signed_at" @click="$emit('sign', contract.id, 'b')" class="flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs text-blue-400 hover:text-blue-300 hover:bg-blue-500/10">
            <PencilSquareIcon class="w-3.5 h-3.5" /> Unterschreiben (Kunde)
          </button>
          <button @click="$emit('duplicate', contract)" class="flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs text-gray-400 hover:text-white hover:bg-white/[0.04]">
            <DocumentDuplicateIcon class="w-3.5 h-3.5" /> Duplizieren
          </button>
          <button @click="$emit('delete', contract)" class="flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs text-red-400 hover:text-red-300 hover:bg-red-500/10">
            <TrashIcon class="w-3.5 h-3.5" /> Löschen
          </button>
        </div>

        <!-- Tabs -->
        <div class="flex gap-4 mt-4 border-b border-white/[0.06] -mx-6 px-6">
          <button
            v-for="tab in tabs"
            :key="tab.id"
            @click="activeTab = tab.id"
            class="pb-2 text-sm font-medium transition-colors border-b-2"
            :class="activeTab === tab.id ? 'text-white border-primary-500' : 'text-gray-500 border-transparent hover:text-gray-300'"
          >
            {{ tab.label }}
            <span v-if="tab.id === 'invoices' && contract.invoices?.length" class="ml-1 text-xs text-gray-500">({{ contract.invoices.length }})</span>
          </button>
        </div>
      </div>

      <!-- Content -->
      <div class="p-6">
        <!-- Details Tab -->
        <div v-if="activeTab === 'details'" class="space-y-5">
          <!-- Parties -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <div class="text-xs text-gray-500 uppercase tracking-wider font-semibold mb-1">Auftragnehmer</div>
              <div class="text-sm text-white font-semibold">{{ contract.party_a_name }}</div>
              <div v-if="contract.party_a_company" class="text-sm text-gray-400">{{ contract.party_a_company }}</div>
              <div v-if="contract.party_a_address" class="text-xs text-gray-500 mt-1 whitespace-pre-line">{{ contract.party_a_address }}</div>
              <div v-if="contract.party_a_email" class="text-xs text-gray-500">{{ contract.party_a_email }}</div>
              <div v-if="partyASignedDisplay" class="text-xs text-emerald-400 mt-1">Unterschrieben: {{ partyASignedDisplay }}</div>
            </div>
            <div>
              <div class="text-xs text-gray-500 uppercase tracking-wider font-semibold mb-1">Auftraggeber</div>
              <div class="text-sm text-white font-semibold">{{ contract.party_b_name }}</div>
              <div v-if="contract.party_b_company" class="text-sm text-gray-400">{{ contract.party_b_company }}</div>
              <div v-if="contract.party_b_address" class="text-xs text-gray-500 mt-1 whitespace-pre-line">{{ contract.party_b_address }}</div>
              <div v-if="contract.party_b_email" class="text-xs text-gray-500">{{ contract.party_b_email }}</div>
              <div v-if="partyBSignedDisplay" class="text-xs text-emerald-400 mt-1">Unterschrieben: {{ partyBSignedDisplay }}</div>
            </div>
          </div>

          <hr class="border-white/[0.06]" />

          <!-- Contract Terms -->
          <div class="grid grid-cols-2 gap-x-6 gap-y-3">
            <div>
              <div class="text-xs text-gray-500 uppercase font-semibold">Vertragsbeginn</div>
              <div class="text-sm text-white">{{ formatDate(contract.start_date) }}</div>
            </div>
            <div>
              <div class="text-xs text-gray-500 uppercase font-semibold">Vertragsende</div>
              <div class="text-sm text-white">{{ contract.end_date ? formatDate(contract.end_date) : 'Unbefristet' }}</div>
            </div>
            <div>
              <div class="text-xs text-gray-500 uppercase font-semibold">Vertragswert</div>
              <div class="text-sm text-white font-semibold">{{ formatCurrency(contract.total_value, contract.currency) }}</div>
            </div>
            <div>
              <div class="text-xs text-gray-500 uppercase font-semibold">Zahlungsplan</div>
              <div class="text-sm text-white">{{ contract.payment_schedule || '-' }}</div>
            </div>
            <div>
              <div class="text-xs text-gray-500 uppercase font-semibold">Auto-Verlängerung</div>
              <div class="text-sm text-white">{{ contract.auto_renewal ? `Ja (${contract.renewal_period})` : 'Nein' }}</div>
            </div>
            <div>
              <div class="text-xs text-gray-500 uppercase font-semibold">Kündigungsfrist</div>
              <div class="text-sm text-white">{{ contract.notice_period_days || 30 }} Tage</div>
            </div>
            <div>
              <div class="text-xs text-gray-500 uppercase font-semibold">Anwendbares Recht</div>
              <div class="text-sm text-white">{{ contract.governing_law || 'DE' }}</div>
            </div>
            <div>
              <div class="text-xs text-gray-500 uppercase font-semibold">Gerichtsstand</div>
              <div class="text-sm text-white">{{ contract.jurisdiction || '-' }}</div>
            </div>
          </div>

          <div v-if="contract.notes" class="mt-4">
            <div class="text-xs text-gray-500 uppercase font-semibold mb-1">Anmerkungen</div>
            <div class="text-sm text-gray-300 whitespace-pre-line">{{ contract.notes }}</div>
          </div>
        </div>

        <!-- Invoices Tab -->
        <div v-if="activeTab === 'invoices'">
          <div class="flex items-center justify-between mb-4">
            <div class="text-xs text-gray-500 uppercase font-semibold">Verknüpfte Rechnungen</div>
            <button @click="showInvoiceLinker = true" class="flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-medium bg-primary-600 text-white hover:bg-primary-500">
              <LinkIcon class="w-3.5 h-3.5" /> Rechnung verknuepfen
            </button>
          </div>

          <div v-if="!contract.invoices?.length" class="text-center py-8 text-gray-500 text-sm">
            Keine Rechnungen verknuepft
          </div>
          <div v-else class="space-y-2">
            <div v-for="inv in contract.invoices" :key="inv.id"
              class="flex items-center justify-between p-3 rounded-lg bg-white/[0.03] hover:bg-white/[0.05]">
              <div>
                <div class="text-sm text-white font-medium">{{ inv.invoice_number }}</div>
                <div class="text-xs text-gray-400">{{ formatDate(inv.issue_date) }}</div>
              </div>
              <div class="flex items-center gap-3">
                <div class="text-sm font-semibold text-white">{{ formatCurrency(inv.total, inv.currency) }}</div>
                <button @click="handleUnlinkInvoice(inv.id)" class="p-1 text-gray-500 hover:text-red-400" title="Verknüpfung entfernen">
                  <XMarkIcon class="w-4 h-4" />
                </button>
              </div>
            </div>
          </div>

          <ContractInvoiceLinker
            :show="showInvoiceLinker"
            :contract-id="contract?.id"
            :existing-invoice-ids="(contract?.invoices || []).map(i => i.id)"
            @close="showInvoiceLinker = false"
            @link="handleLinkInvoice"
          />
        </div>

        <!-- History Tab -->
        <div v-if="activeTab === 'history'">
          <div v-if="!history.length" class="text-center py-8 text-gray-500 text-sm">
            Kein Verlauf vorhanden
          </div>
          <div v-else class="space-y-3">
            <div v-for="entry in history" :key="entry.id" class="flex gap-3 items-start">
              <div class="mt-1">
                <ClockIcon class="w-4 h-4 text-gray-500" />
              </div>
              <div>
                <div class="text-sm text-white">{{ entry.details }}</div>
                <div class="text-xs text-gray-500">
                  {{ formatDate(entry.created_at) }}
                  <span v-if="entry.performed_by_name"> — {{ entry.performed_by_name }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </Transition>
</template>
