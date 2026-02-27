<script setup>
import { ref, computed, watch } from 'vue'
import {
  XMarkIcon,
  ArrowDownTrayIcon,
  DocumentDuplicateIcon,
  PencilIcon,
  TrashIcon,
  ChevronDownIcon,
  DocumentTextIcon,
  CheckIcon,
  ClockIcon,
  PaperAirplaneIcon,
  ExclamationTriangleIcon,
  NoSymbolIcon,
} from '@heroicons/vue/24/outline'
import InvoiceItemsTable from './InvoiceItemsTable.vue'
import InvoicePdfPreview from './InvoicePdfPreview.vue'

const props = defineProps({
  invoice: { type: Object, default: null },
  senderSettings: { type: Object, default: () => ({}) },
  pdfGenerating: { type: [String, Boolean], default: null },
})

const emit = defineEmits([
  'close',
  'download-pdf',
  'duplicate',
  'edit',
  'delete',
  'status-change',
  'items-changed',
])

const activeTab = ref('overview')
const statusMenuOpen = ref(false)

const statusOptions = [
  { value: 'draft', label: 'Entwurf', color: 'bg-gray-500', textColor: 'text-gray-300', icon: PencilIcon },
  { value: 'sent', label: 'Gesendet', color: 'bg-blue-500', textColor: 'text-blue-300', icon: PaperAirplaneIcon },
  { value: 'paid', label: 'Bezahlt', color: 'bg-green-500', textColor: 'text-green-300', icon: CheckIcon },
  { value: 'overdue', label: 'Überfällig', color: 'bg-red-500', textColor: 'text-red-300', icon: ExclamationTriangleIcon },
  { value: 'cancelled', label: 'Storniert', color: 'bg-gray-600', textColor: 'text-gray-400', icon: NoSymbolIcon },
]

const docTypeLabels = {
  invoice: 'Rechnung',
  proforma: 'Proforma',
  quote: 'Angebot',
  credit_note: 'Gutschrift',
  reminder: 'Mahnung',
}

const docTypeBadgeClass = {
  invoice: '',
  proforma: 'bg-blue-500/20 text-blue-300',
  quote: 'bg-yellow-500/20 text-yellow-300',
  credit_note: 'bg-red-500/20 text-red-300',
  reminder: 'bg-orange-500/20 text-orange-300',
}

const currentStatus = computed(() => {
  if (!props.invoice) return statusOptions[0]
  return statusOptions.find(s => s.value === props.invoice.status) || statusOptions[0]
})

const allowedTransitions = computed(() => {
  if (!props.invoice) return []
  const current = props.invoice.status
  const transitions = {
    draft: ['sent', 'paid', 'cancelled'],
    sent: ['paid', 'overdue', 'cancelled'],
    overdue: ['paid', 'cancelled'],
    paid: [],
    cancelled: [],
  }
  return (transitions[current] || []).map(v => statusOptions.find(s => s.value === v)).filter(Boolean)
})

function formatCurrency(amount) {
  return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(amount || 0)
}

function formatDate(dateStr) {
  if (!dateStr) return '–'
  return new Date(dateStr).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

function handleStatusChange(status) {
  statusMenuOpen.value = false
  emit('status-change', props.invoice, status)
}

function handleKeydown(e) {
  if (e.key === 'Escape') emit('close')
}

watch(() => props.invoice, (val) => {
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
        v-if="invoice"
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
            v-if="invoice"
            class="absolute top-0 right-0 h-full w-full max-w-4xl bg-white/[0.04] border-l border-white/[0.06] flex flex-col shadow-float"
          >
            <!-- Header -->
            <div class="flex-none px-6 py-4 border-b border-white/[0.06] bg-white/[0.04]">
              <div class="flex items-start justify-between gap-4">
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-3 flex-wrap">
                    <h2 class="text-xl font-bold text-white font-mono tracking-tight">{{ invoice.invoice_number }}</h2>
                    <!-- Document type badge -->
                    <span
                      v-if="invoice.document_type && invoice.document_type !== 'invoice'"
                      class="text-xs px-2 py-0.5 rounded-full font-medium"
                      :class="docTypeBadgeClass[invoice.document_type] || 'bg-gray-500/20 text-gray-300'"
                    >
                      {{ docTypeLabels[invoice.document_type] || invoice.document_type }}
                    </span>

                    <!-- Status dropdown -->
                    <div class="relative">
                      <button
                        @click="statusMenuOpen = !statusMenuOpen"
                        class="flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium transition-all hover:ring-2 ring-white/20"
                        :class="currentStatus.color + '/20 ' + currentStatus.textColor"
                      >
                        <component :is="currentStatus.icon" class="w-3 h-3" />
                        {{ currentStatus.label }}
                        <ChevronDownIcon v-if="allowedTransitions.length" class="w-3 h-3" />
                      </button>
                      <!-- Status dropdown menu -->
                      <Transition
                        enter-active-class="transition ease-out duration-100"
                        enter-from-class="opacity-0 scale-95"
                        enter-to-class="opacity-100 scale-100"
                        leave-active-class="transition ease-in duration-75"
                        leave-from-class="opacity-100 scale-100"
                        leave-to-class="opacity-0 scale-95"
                      >
                        <div
                          v-if="statusMenuOpen && allowedTransitions.length"
                          class="absolute top-full left-0 mt-1 w-44 bg-white/[0.04] border border-white/[0.06] rounded-xl shadow-float z-10 overflow-hidden"
                          v-click-outside="() => statusMenuOpen = false"
                        >
                          <button
                            v-for="opt in allowedTransitions"
                            :key="opt.value"
                            @click="handleStatusChange(opt.value)"
                            class="w-full flex items-center gap-2.5 px-3 py-2.5 text-sm text-left hover:bg-white/[0.04] transition-colors"
                            :class="opt.textColor"
                          >
                            <component :is="opt.icon" class="w-4 h-4 shrink-0" />
                            {{ opt.label }}
                          </button>
                        </div>
                      </Transition>
                    </div>
                  </div>
                  <p class="text-sm text-gray-400 mt-1">
                    {{ invoice.client_name || invoice.client_company || '–' }}
                    <span v-if="invoice.issue_date" class="ml-2">· {{ formatDate(invoice.issue_date) }}</span>
                  </p>
                </div>

                <!-- Action buttons -->
                <div class="flex items-center gap-1 shrink-0">
                  <button
                    @click="$emit('download-pdf', invoice)"
                    class="p-2 rounded-lg text-gray-400 hover:text-white hover:bg-white/[0.04] transition-colors"
                    :class="{ 'text-primary-400 animate-pulse': pdfGenerating === invoice.id }"
                    :disabled="pdfGenerating === invoice.id"
                    title="Als PDF herunterladen"
                  >
                    <ArrowDownTrayIcon class="w-5 h-5" />
                  </button>
                  <button
                    @click="$emit('duplicate', invoice)"
                    class="p-2 rounded-lg text-gray-400 hover:text-purple-400 hover:bg-white/[0.04] transition-colors"
                    title="Rechnung duplizieren"
                  >
                    <DocumentDuplicateIcon class="w-5 h-5" />
                  </button>
                  <button
                    @click="$emit('edit', invoice)"
                    class="p-2 rounded-lg text-gray-400 hover:text-yellow-400 hover:bg-white/[0.04] transition-colors"
                    title="Rechnung bearbeiten"
                  >
                    <PencilIcon class="w-5 h-5" />
                  </button>
                  <button
                    @click="$emit('delete', invoice)"
                    class="p-2 rounded-lg text-gray-400 hover:text-red-400 hover:bg-white/[0.04] transition-colors"
                    title="Rechnung löschen"
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

              <!-- Tabs -->
              <div class="flex gap-1 mt-4">
                <button
                  v-for="tab in [
                    { id: 'overview', label: 'Übersicht', icon: DocumentTextIcon },
                    { id: 'items', label: 'Positionen', icon: null },
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
                    v-if="tab.id === 'items' && invoice.items?.length"
                    class="ml-1.5 text-xs bg-white/[0.06] text-gray-300 px-1.5 py-0.5 rounded-full"
                  >{{ invoice.items.length }}</span>
                </button>
              </div>
            </div>

            <!-- Scrollable content -->
            <div class="flex-1 overflow-y-auto">

              <!-- OVERVIEW TAB -->
              <div v-if="activeTab === 'overview'" class="p-6 space-y-6">

                <!-- Sender / Recipient cards -->
                <div class="grid grid-cols-2 gap-4">
                  <div class="bg-white/[0.03] rounded-xl p-4 border border-white/[0.06]">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Von</p>
                    <p class="font-semibold text-white">{{ invoice.sender_name || '–' }}</p>
                    <p v-if="invoice.sender_company" class="text-gray-400 text-sm">{{ invoice.sender_company }}</p>
                    <p class="text-gray-400 text-sm mt-1 whitespace-pre-line">{{ invoice.sender_address }}</p>
                    <p v-if="invoice.sender_vat_id" class="text-gray-500 text-xs mt-1">USt-IdNr.: {{ invoice.sender_vat_id }}</p>
                    <p v-if="invoice.sender_email" class="text-gray-400 text-sm mt-1">{{ invoice.sender_email }}</p>
                    <p v-if="invoice.sender_phone" class="text-gray-400 text-sm">{{ invoice.sender_phone }}</p>
                  </div>
                  <div class="bg-white/[0.03] rounded-xl p-4 border border-white/[0.06]">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">
                      {{ invoice.document_type === 'quote' ? 'Angeboten für' : invoice.document_type === 'credit_note' ? 'Gutschrift für' : 'Rechnungsempfänger' }}
                    </p>
                    <p class="font-semibold text-white">{{ invoice.client_name || '–' }}</p>
                    <p v-if="invoice.client_company" class="text-gray-400 text-sm">{{ invoice.client_company }}</p>
                    <p class="text-gray-400 text-sm mt-1 whitespace-pre-line">{{ invoice.client_address }}</p>
                    <p v-if="invoice.client_vat_id" class="text-gray-500 text-xs mt-1">USt-IdNr.: {{ invoice.client_vat_id }}</p>
                    <p v-if="invoice.client_email" class="text-gray-400 text-sm mt-1">{{ invoice.client_email }}</p>
                  </div>
                </div>

                <!-- Dates grid -->
                <div class="bg-white/[0.03] rounded-xl p-4 border border-white/[0.06]">
                  <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Daten</p>
                  <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div>
                      <p class="text-xs text-gray-500 mb-1">Rechnungsdatum</p>
                      <p class="text-white font-medium">{{ formatDate(invoice.issue_date) }}</p>
                    </div>
                    <div v-if="invoice.service_date">
                      <p class="text-xs text-gray-500 mb-1">Leistungsdatum</p>
                      <p class="text-white font-medium">{{ formatDate(invoice.service_date) }}</p>
                    </div>
                    <div v-if="invoice.due_date">
                      <p class="text-xs text-gray-500 mb-1">Fällig bis</p>
                      <p class="font-medium" :class="invoice.status === 'overdue' ? 'text-red-400' : 'text-white'">
                        {{ formatDate(invoice.due_date) }}
                      </p>
                    </div>
                    <div v-if="invoice.paid_date">
                      <p class="text-xs text-gray-500 mb-1">Bezahlt am</p>
                      <p class="text-green-400 font-medium">{{ formatDate(invoice.paid_date) }}</p>
                    </div>
                  </div>
                </div>

                <!-- Totals -->
                <div class="bg-white/[0.03] rounded-xl p-4 border border-white/[0.06]">
                  <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Beträge</p>
                  <div class="space-y-2">
                    <div class="flex justify-between text-sm">
                      <span class="text-gray-400">Netto</span>
                      <span class="text-white">{{ formatCurrency(invoice.subtotal) }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                      <span class="text-gray-400">MwSt. ({{ invoice.tax_rate }}%)</span>
                      <span class="text-white">{{ formatCurrency(invoice.tax_amount) }}</span>
                    </div>
                    <div class="flex justify-between pt-2 border-t border-white/[0.08]">
                      <span class="text-white font-bold text-lg">Gesamt</span>
                      <span class="text-white font-bold text-2xl">{{ formatCurrency(invoice.total) }}</span>
                    </div>
                  </div>
                </div>

                <!-- Positions summary -->
                <div v-if="invoice.items?.length" class="bg-white/[0.03] rounded-xl p-4 border border-white/[0.06]">
                  <div class="flex items-center justify-between mb-3">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">
                      Positionen ({{ invoice.items.length }})
                    </p>
                    <button
                      @click="activeTab = 'items'"
                      class="text-xs text-primary-400 hover:text-primary-300 transition-colors"
                    >
                      Alle bearbeiten →
                    </button>
                  </div>
                  <div class="space-y-1">
                    <div
                      v-for="(item, i) in invoice.items.slice(0, 4)"
                      :key="item.id"
                      class="flex justify-between text-sm"
                    >
                      <span class="text-gray-300 truncate mr-4">{{ item.description?.split('\n')[0] || '–' }}</span>
                      <span class="text-gray-400 shrink-0">{{ formatCurrency(item.total) }}</span>
                    </div>
                    <p v-if="invoice.items.length > 4" class="text-xs text-gray-600 mt-1">
                      + {{ invoice.items.length - 4 }} weitere Positionen
                    </p>
                  </div>
                </div>
                <div v-else class="bg-white/[0.02] rounded-xl p-6 border border-dashed border-white/[0.06] text-center">
                  <p class="text-gray-500 text-sm">Noch keine Positionen</p>
                  <button
                    @click="activeTab = 'items'"
                    class="mt-2 text-sm text-primary-400 hover:text-primary-300 transition-colors"
                  >
                    Positionen hinzufügen →
                  </button>
                </div>

                <!-- Notes / Payment Terms -->
                <div v-if="invoice.payment_terms || invoice.notes" class="space-y-3">
                  <div v-if="invoice.payment_terms" class="bg-white/[0.03] rounded-xl p-4 border border-white/[0.06]">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Zahlungsbedingungen</p>
                    <p class="text-gray-300 text-sm">{{ invoice.payment_terms }}</p>
                    <p v-if="invoice.sender_bank_details" class="text-gray-500 text-sm mt-2 whitespace-pre-line">{{ invoice.sender_bank_details }}</p>
                  </div>
                  <div v-if="invoice.notes" class="bg-white/[0.03] rounded-xl p-4 border border-white/[0.06]">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Anmerkungen</p>
                    <p class="text-gray-300 text-sm whitespace-pre-line">{{ invoice.notes }}</p>
                  </div>
                </div>
              </div>

              <!-- ITEMS TAB -->
              <div v-else-if="activeTab === 'items'" class="p-6">
                <InvoiceItemsTable
                  :invoice="invoice"
                  @items-changed="$emit('items-changed')"
                />
              </div>

              <!-- PREVIEW TAB -->
              <div v-else-if="activeTab === 'preview'" class="h-full">
                <InvoicePdfPreview
                  :invoice="invoice"
                  :sender-settings="senderSettings"
                  @download="$emit('download-pdf', invoice)"
                />
              </div>

            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>
