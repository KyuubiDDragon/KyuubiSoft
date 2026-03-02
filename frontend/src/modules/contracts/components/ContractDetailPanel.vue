<script setup>
import { ref, watch, computed } from 'vue'
import api from '@/core/api/axios'
import {
  XMarkIcon,
  ArrowDownTrayIcon,
  EyeIcon,
  DocumentDuplicateIcon,
  TrashIcon,
  ClockIcon,
  LinkIcon,
  PencilSquareIcon,
  ChevronDownIcon,
  DocumentTextIcon,
  CheckIcon,
  PaperAirplaneIcon,
  NoSymbolIcon,
  ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline'
import { useContracts } from '../composables/useContracts'
import ContractInvoiceLinker from './ContractInvoiceLinker.vue'
import ContractPdfPreview from './ContractPdfPreview.vue'

const props = defineProps({
  show: Boolean,
  contract: { type: Object, default: null },
  clients: { type: Array, default: () => [] },
})

const emit = defineEmits(['close', 'status-change', 'sign', 'download-pdf', 'preview', 'delete', 'duplicate', 'link-invoice', 'unlink-invoice', 'reload', 'share'])

const { statusOptions, contractTypeLabels, formatCurrency, formatDate, getStatusInfo, getHistory } = useContracts()

const activeTab = ref('overview')
const history = ref([])
const showInvoiceLinker = ref(false)
const statusMenuOpen = ref(false)

const statusIcons = {
  draft: DocumentTextIcon,
  sent: PaperAirplaneIcon,
  signed: PencilSquareIcon,
  active: CheckIcon,
  expired: ClockIcon,
  cancelled: NoSymbolIcon,
  terminated: ExclamationTriangleIcon,
}

watch(() => props.contract?.id, async (newId) => {
  if (newId) {
    activeTab.value = 'overview'
    history.value = await getHistory(newId)
  }
})

const typeColors = {
  license: 'bg-blue-500/20 text-blue-300',
  development: 'bg-purple-500/20 text-purple-300',
  saas: 'bg-cyan-500/20 text-cyan-300',
  maintenance: 'bg-amber-500/20 text-amber-300',
  nda: 'bg-emerald-500/20 text-emerald-300',
  source_code_purchase: 'bg-orange-500/20 text-orange-300',
  custom: 'bg-rose-500/20 text-rose-300',
}

const currentStatus = computed(() => {
  if (!props.contract) return statusOptions[0]
  return statusOptions.find(s => s.value === props.contract.status) || statusOptions[0]
})

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

function handleStatusChange(status) {
  statusMenuOpen.value = false
  emit('status-change', props.contract, status)
}

function handleKeydown(e) {
  if (e.key === 'Escape') emit('close')
}

async function handleLinkInvoice(invoiceId) {
  emit('link-invoice', props.contract.id, invoiceId)
  showInvoiceLinker.value = false
  setTimeout(() => emit('reload', props.contract), 500)
}

async function handleUnlinkInvoice(invoiceId) {
  emit('unlink-invoice', props.contract.id, invoiceId)
  setTimeout(() => emit('reload', props.contract), 500)
}

watch(() => props.contract, (val) => {
  if (val) activeTab.value = 'overview'
})
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition-opacity duration-300"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-200"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="show && contract"
        class="fixed inset-0 bg-black/60 backdrop-blur-md z-50"
        @click.self="$emit('close')"
        @keydown="handleKeydown"
        tabindex="-1"
      >
        <Transition
          enter-active-class="transition-transform duration-300 ease-out"
          enter-from-class="translate-x-full"
          enter-to-class="translate-x-0"
          leave-active-class="transition-transform duration-200 ease-in"
          leave-from-class="translate-x-0"
          leave-to-class="translate-x-full"
        >
          <div
            v-if="show && contract"
            class="absolute top-0 right-0 h-full w-full max-w-4xl bg-white/[0.04] border-l border-white/[0.06] flex flex-col shadow-float"
          >
            <!-- Header -->
            <div class="flex-none px-6 py-4 border-b border-white/[0.06] bg-white/[0.04]">
              <div class="flex items-start justify-between gap-4">
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-3 flex-wrap">
                    <h2 class="text-xl font-bold text-white tracking-tight">{{ contract.contract_number }}</h2>
                    <!-- Contract type badge -->
                    <span
                      class="text-xs px-2 py-0.5 rounded-full font-medium"
                      :class="typeColors[contract.contract_type] || 'bg-gray-500/20 text-gray-300'"
                    >
                      {{ contractTypeLabels[contract.contract_type] || contract.contract_type }}
                    </span>

                    <!-- Status dropdown -->
                    <div class="relative">
                      <button
                        @click="statusMenuOpen = !statusMenuOpen"
                        class="flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium transition-all hover:ring-2 ring-white/20"
                        :class="getStatusInfo(contract.status).color + '/20 ' + getStatusInfo(contract.status).textColor"
                      >
                        <component :is="statusIcons[contract.status] || DocumentTextIcon" class="w-3 h-3" />
                        {{ currentStatus.label }}
                        <ChevronDownIcon v-if="statusActions.length" class="w-3 h-3" />
                      </button>
                      <Transition
                        enter-active-class="transition ease-out duration-100"
                        enter-from-class="opacity-0 scale-95"
                        enter-to-class="opacity-100 scale-100"
                        leave-active-class="transition ease-in duration-75"
                        leave-from-class="opacity-100 scale-100"
                        leave-to-class="opacity-0 scale-95"
                      >
                        <div
                          v-if="statusMenuOpen && statusActions.length"
                          class="absolute top-full left-0 mt-1 w-44 bg-white/[0.04] border border-white/[0.06] rounded-xl shadow-float z-10 overflow-hidden"
                          v-click-outside="() => statusMenuOpen = false"
                        >
                          <button
                            v-for="opt in statusActions"
                            :key="opt.value"
                            @click="handleStatusChange(opt.value)"
                            class="w-full flex items-center gap-2.5 px-3 py-2.5 text-sm text-left hover:bg-white/[0.04] transition-colors"
                            :class="getStatusInfo(opt.value).textColor"
                          >
                            <component :is="statusIcons[opt.value] || DocumentTextIcon" class="w-4 h-4 shrink-0" />
                            {{ opt.label }}
                          </button>
                        </div>
                      </Transition>
                    </div>
                  </div>
                  <p class="text-sm text-gray-400 mt-1">
                    {{ contract.title || '–' }}
                    <span v-if="contract.party_b_company || contract.party_b_name" class="ml-2">· {{ contract.party_b_company || contract.party_b_name }}</span>
                  </p>
                </div>

                <!-- Action buttons -->
                <div class="flex items-center gap-1 shrink-0">
                  <button
                    @click="$emit('download-pdf', contract)"
                    class="p-2 rounded-lg text-gray-400 hover:text-white hover:bg-white/[0.04] transition-colors"
                    title="Als PDF herunterladen"
                  >
                    <ArrowDownTrayIcon class="w-5 h-5" />
                  </button>
                  <button
                    @click="$emit('share', contract)"
                    class="p-2 rounded-lg text-gray-400 hover:text-primary-400 hover:bg-white/[0.04] transition-colors"
                    title="Vertrag teilen"
                  >
                    <LinkIcon class="w-5 h-5" />
                  </button>
                  <button
                    @click="$emit('duplicate', contract)"
                    class="p-2 rounded-lg text-gray-400 hover:text-purple-400 hover:bg-white/[0.04] transition-colors"
                    title="Vertrag duplizieren"
                  >
                    <DocumentDuplicateIcon class="w-5 h-5" />
                  </button>
                  <button
                    @click="$emit('delete', contract)"
                    class="p-2 rounded-lg text-gray-400 hover:text-red-400 hover:bg-white/[0.04] transition-colors"
                    title="Vertrag löschen"
                  >
                    <TrashIcon class="w-5 h-5" />
                  </button>
                  <div class="w-px h-6 bg-white/[0.08] mx-1"></div>
                  <button
                    @click="$emit('close')"
                    class="p-2 rounded-lg text-gray-400 hover:text-white hover:bg-white/[0.04] transition-colors"
                    title="Schließen (Esc)"
                  >
                    <XMarkIcon class="w-5 h-5" />
                  </button>
                </div>
              </div>

              <!-- Signature actions -->
              <div v-if="!contract.party_a_signed_at || !contract.party_b_signed_at" class="flex gap-2 mt-3">
                <button
                  v-if="!contract.party_a_signed_at"
                  @click="$emit('sign', contract.id, 'a')"
                  class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-emerald-400 hover:text-emerald-300 bg-emerald-500/10 hover:bg-emerald-500/20 transition-colors"
                >
                  <PencilSquareIcon class="w-3.5 h-3.5" /> Unterschreiben (Sie)
                </button>
                <button
                  v-if="!contract.party_b_signed_at"
                  @click="$emit('sign', contract.id, 'b')"
                  class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-blue-400 hover:text-blue-300 bg-blue-500/10 hover:bg-blue-500/20 transition-colors"
                >
                  <PencilSquareIcon class="w-3.5 h-3.5" /> Unterschreiben (Kunde)
                </button>
              </div>

              <!-- Tabs -->
              <div class="flex gap-1 mt-4">
                <button
                  v-for="tab in [
                    { id: 'overview', label: 'Übersicht', icon: DocumentTextIcon },
                    { id: 'invoices', label: 'Rechnungen', icon: null },
                    { id: 'history', label: 'Verlauf', icon: null },
                    { id: 'preview', label: 'Vorschau', icon: null },
                  ]"
                  :key="tab.id"
                  @click="activeTab = tab.id"
                  class="px-4 py-2 text-sm font-medium rounded-lg transition-colors"
                  :class="activeTab === tab.id
                    ? 'bg-white/[0.08] text-white'
                    : 'text-gray-400 hover:text-white hover:bg-white/[0.04]'"
                >
                  {{ tab.label }}
                  <span
                    v-if="tab.id === 'invoices' && contract.invoices?.length"
                    class="ml-1.5 text-xs bg-white/[0.06] text-gray-300 px-1.5 py-0.5 rounded-full"
                  >{{ contract.invoices.length }}</span>
                </button>
              </div>
            </div>

            <!-- Scrollable content -->
            <div class="flex-1 overflow-y-auto">

              <!-- OVERVIEW TAB -->
              <div v-if="activeTab === 'overview'" class="p-6 space-y-6">

                <!-- Parties cards -->
                <div class="grid grid-cols-2 gap-4">
                  <div class="bg-white/[0.03] rounded-xl p-4 border border-white/[0.06]">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Auftragnehmer</p>
                    <p class="font-semibold text-white">{{ contract.party_a_name || '–' }}</p>
                    <p v-if="contract.party_a_company" class="text-gray-400 text-sm">{{ contract.party_a_company }}</p>
                    <p v-if="contract.party_a_address" class="text-gray-400 text-sm mt-1 whitespace-pre-line">{{ contract.party_a_address }}</p>
                    <p v-if="contract.party_a_vat_id" class="text-gray-500 text-xs mt-1">USt-IdNr.: {{ contract.party_a_vat_id }}</p>
                    <p v-if="contract.party_a_email" class="text-gray-400 text-sm mt-1">{{ contract.party_a_email }}</p>
                    <p v-if="partyASignedDisplay" class="text-emerald-400 text-xs mt-2 font-medium">Unterschrieben: {{ partyASignedDisplay }}</p>
                  </div>
                  <div class="bg-white/[0.03] rounded-xl p-4 border border-white/[0.06]">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Auftraggeber</p>
                    <p class="font-semibold text-white">{{ contract.party_b_name || '–' }}</p>
                    <p v-if="contract.party_b_company" class="text-gray-400 text-sm">{{ contract.party_b_company }}</p>
                    <p v-if="contract.party_b_address" class="text-gray-400 text-sm mt-1 whitespace-pre-line">{{ contract.party_b_address }}</p>
                    <p v-if="contract.party_b_vat_id" class="text-gray-500 text-xs mt-1">USt-IdNr.: {{ contract.party_b_vat_id }}</p>
                    <p v-if="contract.party_b_email" class="text-gray-400 text-sm mt-1">{{ contract.party_b_email }}</p>
                    <p v-if="partyBSignedDisplay" class="text-emerald-400 text-xs mt-2 font-medium">Unterschrieben: {{ partyBSignedDisplay }}</p>
                  </div>
                </div>

                <!-- Contract Terms -->
                <div class="bg-white/[0.03] rounded-xl p-4 border border-white/[0.06]">
                  <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Vertragsdaten</p>
                  <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div>
                      <p class="text-xs text-gray-500 mb-1">Vertragsbeginn</p>
                      <p class="text-white font-medium">{{ formatDate(contract.start_date) }}</p>
                    </div>
                    <div>
                      <p class="text-xs text-gray-500 mb-1">Vertragsende</p>
                      <p class="text-white font-medium">{{ contract.end_date ? formatDate(contract.end_date) : 'Unbefristet' }}</p>
                    </div>
                    <div>
                      <p class="text-xs text-gray-500 mb-1">Kündigungsfrist</p>
                      <p class="text-white font-medium">{{ contract.notice_period_days || 30 }} Tage</p>
                    </div>
                    <div>
                      <p class="text-xs text-gray-500 mb-1">Auto-Verlängerung</p>
                      <p class="text-white font-medium">{{ contract.auto_renewal ? `Ja (${contract.renewal_period})` : 'Nein' }}</p>
                    </div>
                  </div>
                </div>

                <!-- Value -->
                <div class="bg-white/[0.03] rounded-xl p-4 border border-white/[0.06]">
                  <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Finanzen</p>
                  <div class="space-y-2">
                    <div class="flex justify-between text-sm">
                      <span class="text-gray-400">Zahlungsplan</span>
                      <span class="text-white">{{ contract.payment_schedule || '-' }}</span>
                    </div>
                    <div class="flex justify-between pt-2 border-t border-white/[0.08]">
                      <span class="text-white font-bold text-lg">Vertragswert</span>
                      <span class="text-white font-bold text-2xl">{{ formatCurrency(contract.total_value, contract.currency) }}</span>
                    </div>
                  </div>
                </div>

                <!-- Legal -->
                <div class="bg-white/[0.03] rounded-xl p-4 border border-white/[0.06]">
                  <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Rechtliches</p>
                  <div class="grid grid-cols-2 gap-4">
                    <div>
                      <p class="text-xs text-gray-500 mb-1">Anwendbares Recht</p>
                      <p class="text-white font-medium">{{ contract.governing_law || 'DE' }}</p>
                    </div>
                    <div>
                      <p class="text-xs text-gray-500 mb-1">Gerichtsstand</p>
                      <p class="text-white font-medium">{{ contract.jurisdiction || '-' }}</p>
                    </div>
                  </div>
                </div>

                <!-- Notes -->
                <div v-if="contract.notes" class="bg-white/[0.03] rounded-xl p-4 border border-white/[0.06]">
                  <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Anmerkungen</p>
                  <p class="text-gray-300 text-sm whitespace-pre-line">{{ contract.notes }}</p>
                </div>
              </div>

              <!-- INVOICES TAB -->
              <div v-else-if="activeTab === 'invoices'" class="p-6">
                <div class="flex items-center justify-between mb-4">
                  <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Verknüpfte Rechnungen</p>
                  <button @click="showInvoiceLinker = true" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-primary-600 text-white hover:bg-primary-500 transition-colors">
                    <LinkIcon class="w-3.5 h-3.5" /> Rechnung verknüpfen
                  </button>
                </div>

                <div v-if="!contract.invoices?.length" class="bg-white/[0.02] rounded-xl p-6 border border-dashed border-white/[0.06] text-center">
                  <p class="text-gray-500 text-sm">Keine Rechnungen verknüpft</p>
                  <button
                    @click="showInvoiceLinker = true"
                    class="mt-2 text-sm text-primary-400 hover:text-primary-300 transition-colors"
                  >
                    Rechnung verknüpfen →
                  </button>
                </div>
                <div v-else class="space-y-2">
                  <div v-for="inv in contract.invoices" :key="inv.id"
                    class="flex items-center justify-between p-3 rounded-lg bg-white/[0.03] hover:bg-white/[0.05] transition-colors">
                    <div>
                      <div class="text-sm text-white font-medium">{{ inv.invoice_number }}</div>
                      <div class="text-xs text-gray-400">{{ formatDate(inv.issue_date) }}</div>
                    </div>
                    <div class="flex items-center gap-3">
                      <div class="text-sm font-semibold text-white">{{ formatCurrency(inv.total, inv.currency) }}</div>
                      <button @click="handleUnlinkInvoice(inv.id)" class="p-1 text-gray-500 hover:text-red-400 transition-colors" title="Verknüpfung entfernen">
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

              <!-- HISTORY TAB -->
              <div v-else-if="activeTab === 'history'" class="p-6">
                <div v-if="!history.length" class="bg-white/[0.02] rounded-xl p-6 border border-dashed border-white/[0.06] text-center">
                  <p class="text-gray-500 text-sm">Kein Verlauf vorhanden</p>
                </div>
                <div v-else class="space-y-3">
                  <div v-for="entry in history" :key="entry.id" class="flex gap-3 items-start p-3 rounded-lg bg-white/[0.03] hover:bg-white/[0.05] transition-colors">
                    <div class="mt-0.5 w-8 h-8 bg-white/[0.06] rounded-lg flex items-center justify-center shrink-0">
                      <ClockIcon class="w-4 h-4 text-gray-400" />
                    </div>
                    <div>
                      <div class="text-sm text-white">{{ entry.details }}</div>
                      <div class="text-xs text-gray-500 mt-0.5">
                        {{ formatDate(entry.created_at) }}
                        <span v-if="entry.performed_by_name"> — {{ entry.performed_by_name }}</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- PREVIEW TAB -->
              <div v-else-if="activeTab === 'preview'" class="h-full">
                <ContractPdfPreview
                  :show="true"
                  :contract="contract"
                  :inline="true"
                  @download="(c, editedHtml) => $emit('download-pdf', c, editedHtml)"
                />
              </div>

            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>
