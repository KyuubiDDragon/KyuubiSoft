<script setup>
import { ref, watch, computed } from 'vue'
import {
  XMarkIcon,
  DocumentTextIcon,
  ClipboardDocumentListIcon,
  ReceiptPercentIcon,
  ArrowPathIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  show: Boolean,
  editingInvoice: { type: Object, default: null },
  clients: { type: Array, default: () => [] },
  kleinunternehmerMode: Boolean,
  defaultPaymentTerms: { type: String, default: '' },
})

const emit = defineEmits(['close', 'save'])

const form = ref({
  client_id: null,
  document_type: 'invoice',
  language: 'de',
  issue_date: '',
  due_date: '',
  service_date: '',
  tax_rate: 19,
  notes: '',
  payment_terms: '',
  mahnung_level: 0,
  mahnung_fee: 0,
})

const languages = [
  { value: 'de', label: 'Deutsch' },
  { value: 'en', label: 'English' },
]

const documentTypes = [
  { value: 'invoice', label: 'Rechnung', description: 'Standard Rechnung', color: 'text-white', bg: 'bg-white/[0.08] border-white/[0.08]' },
  { value: 'quote', label: 'Angebot', description: 'Kostenvoranschlag', color: 'text-yellow-300', bg: 'bg-yellow-500/10 border-yellow-500/30' },
  { value: 'proforma', label: 'Proforma', description: 'Vorab-Rechnung', color: 'text-blue-300', bg: 'bg-blue-500/10 border-blue-500/30' },
  { value: 'credit_note', label: 'Gutschrift', description: 'Storno / Gutschrift', color: 'text-red-300', bg: 'bg-red-500/10 border-red-500/30' },
  { value: 'reminder', label: 'Mahnung', description: 'Zahlungserinnerung', color: 'text-orange-300', bg: 'bg-orange-500/10 border-orange-500/30' },
]

const mahnungLevels = [
  { value: 0, label: 'Zahlungserinnerung' },
  { value: 1, label: '1. Mahnung' },
  { value: 2, label: '2. Mahnung' },
  { value: 3, label: '3. Mahnung (Letzte Frist)' },
]

function initForm() {
  if (props.editingInvoice) {
    form.value = {
      client_id: props.editingInvoice.client_id,
      document_type: props.editingInvoice.document_type ?? 'invoice',
      language: props.editingInvoice.language ?? 'de',
      issue_date: props.editingInvoice.issue_date,
      due_date: props.editingInvoice.due_date ?? '',
      service_date: props.editingInvoice.service_date ?? '',
      tax_rate: props.editingInvoice.tax_rate,
      notes: props.editingInvoice.notes ?? '',
      payment_terms: props.editingInvoice.payment_terms ?? '',
      mahnung_level: props.editingInvoice.mahnung_level ?? 0,
      mahnung_fee: props.editingInvoice.mahnung_fee ?? 0,
    }
  } else {
    const today = new Date().toISOString().split('T')[0]
    const dueDate = new Date(Date.now() + 14 * 24 * 60 * 60 * 1000).toISOString().split('T')[0]
    form.value = {
      client_id: null,
      document_type: 'invoice',
      language: 'de',
      issue_date: today,
      due_date: dueDate,
      service_date: today,
      tax_rate: props.kleinunternehmerMode ? 0 : 19,
      notes: '',
      payment_terms: props.defaultPaymentTerms ?? 'Zahlbar innerhalb von 30 Tagen nach Rechnungsdatum.',
      mahnung_level: 0,
      mahnung_fee: 0,
    }
  }
}

watch(() => props.show, (val) => {
  if (val) initForm()
})

function handleSubmit() {
  emit('save', { ...form.value }, props.editingInvoice?.id ?? null)
}

const isReminder = computed(() => form.value.document_type === 'reminder')
const isQuoteOrProforma = computed(() => ['quote', 'proforma'].includes(form.value.document_type))
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
        <Transition
          enter-active-class="transition ease-out duration-200"
          enter-from-class="opacity-0 scale-95"
          enter-to-class="opacity-100 scale-100"
          leave-active-class="transition ease-in duration-150"
          leave-from-class="opacity-100 scale-100"
          leave-to-class="opacity-0 scale-95"
        >
          <div
            v-if="show"
            class="modal w-full max-w-xl max-h-[90vh] overflow-y-auto"
          >
            <!-- Header -->
            <div class="sticky top-0 bg-white/[0.04] px-6 py-4 border-b border-white/[0.06] flex items-center justify-between z-10">
              <h2 class="text-lg font-bold text-white">
                {{ editingInvoice ? 'Rechnung bearbeiten' : 'Neues Dokument' }}
              </h2>
              <button @click="$emit('close')" class="p-2 rounded-lg text-gray-400 hover:text-white hover:bg-white/[0.04] transition-colors">
                <XMarkIcon class="w-5 h-5" />
              </button>
            </div>

            <form @submit.prevent="handleSubmit" class="p-6 space-y-5">

              <!-- Document Type selection -->
              <div>
                <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Dokumenttyp</label>
                <div class="grid grid-cols-5 gap-2">
                  <button
                    v-for="dt in documentTypes"
                    :key="dt.value"
                    type="button"
                    @click="form.document_type = dt.value"
                    class="flex flex-col items-center gap-1 p-2.5 rounded-xl border text-center transition-all"
                    :class="form.document_type === dt.value
                      ? dt.bg + ' ' + dt.color + ' ring-2 ring-offset-1 ring-offset-black ' + (dt.value === 'invoice' ? 'ring-white/40' : dt.color.replace('text-', 'ring-').replace('-300', '-400'))
                      : 'bg-white/[0.04] border-white/[0.06] text-gray-500 hover:text-gray-300 hover:border-white/[0.08]'"
                  >
                    <span class="text-xs font-semibold leading-tight">{{ dt.label }}</span>
                  </button>
                </div>
              </div>

              <!-- Language -->
              <div>
                <label class="label">Sprache / Language</label>
                <div class="flex gap-2">
                  <button
                    v-for="l in languages"
                    :key="l.value"
                    type="button"
                    @click="form.language = l.value"
                    class="px-4 py-2 rounded-lg border text-sm font-medium transition-all"
                    :class="form.language === l.value
                      ? 'bg-indigo-500/20 border-indigo-500/40 text-indigo-300 ring-1 ring-indigo-500/40'
                      : 'bg-white/[0.04] border-white/[0.06] text-gray-400 hover:text-gray-300'"
                  >
                    {{ l.label }}
                  </button>
                </div>
              </div>

              <!-- Client -->
              <div>
                <label class="label">Kunde</label>
                <select v-model="form.client_id" class="input">
                  <option :value="null">Kein Kunde ausgewählt</option>
                  <option v-for="c in clients" :key="c.id" :value="c.id">
                    {{ c.name }}{{ c.company ? ' — ' + c.company : '' }}
                  </option>
                </select>
              </div>

              <!-- Dates grid -->
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="label">{{ isQuoteOrProforma ? 'Ausstellungsdatum' : isReminder ? 'Mahndatum' : 'Rechnungsdatum' }} *</label>
                  <input v-model="form.issue_date" type="date" class="input" required />
                </div>
                <div>
                  <label class="label">{{ isQuoteOrProforma ? 'Gültig bis' : isReminder ? 'Neue Zahlungsfrist' : 'Fällig bis' }}</label>
                  <input v-model="form.due_date" type="date" class="input" />
                </div>
              </div>

              <!-- Service date (only for invoice/credit_note/proforma) -->
              <div v-if="!isQuoteOrProforma && !isReminder">
                <label class="label">Leistungsdatum <span class="text-gray-500 font-normal">(§ 14 UStG)</span></label>
                <input v-model="form.service_date" type="date" class="input" />
              </div>

              <!-- Tax rate (not for quotes/proforma) -->
              <div v-if="!isQuoteOrProforma" class="grid grid-cols-2 gap-4">
                <div>
                  <label class="label">MwSt.-Satz (%)</label>
                  <div class="flex gap-2">
                    <button
                      v-for="rate in [0, 7, 19]"
                      :key="rate"
                      type="button"
                      @click="form.tax_rate = rate"
                      class="flex-1 py-2 rounded-lg text-sm font-medium transition-colors border"
                      :class="form.tax_rate === rate
                        ? 'bg-primary-600 border-primary-500 text-white'
                        : 'bg-white/[0.04] border-white/[0.06] text-gray-400 hover:text-white hover:border-white/[0.08]'"
                    >
                      {{ rate }}%
                    </button>
                    <input
                      v-model.number="form.tax_rate"
                      type="number" min="0" max="100" step="0.1"
                      class="input w-20 text-sm text-center"
                      title="Individueller Steuersatz"
                    />
                  </div>
                </div>
                <!-- Mahnung level -->
                <div v-if="isReminder">
                  <label class="label">Mahnstufe</label>
                  <select v-model.number="form.mahnung_level" class="input">
                    <option v-for="ml in mahnungLevels" :key="ml.value" :value="ml.value">{{ ml.label }}</option>
                  </select>
                </div>
              </div>

              <!-- Mahnung fee -->
              <div v-if="isReminder">
                <label class="label">Mahngebühr (€)</label>
                <input v-model.number="form.mahnung_fee" type="number" min="0" step="0.01" class="input" placeholder="0,00" />
              </div>

              <!-- Payment terms -->
              <div v-if="!isQuoteOrProforma">
                <label class="label">Zahlungsbedingungen</label>
                <input v-model="form.payment_terms" type="text" class="input" placeholder="Zahlbar innerhalb von 30 Tagen nach Rechnungsdatum." />
              </div>

              <!-- Notes -->
              <div>
                <label class="label">Anmerkungen <span class="text-gray-500 font-normal">(intern)</span></label>
                <textarea v-model="form.notes" class="input" rows="2" placeholder="Optionale Anmerkungen..."></textarea>
              </div>

              <!-- Actions -->
              <div class="flex gap-3 pt-2">
                <button type="button" @click="$emit('close')" class="btn-secondary flex-1">
                  Abbrechen
                </button>
                <button type="submit" class="btn-primary flex-1">
                  {{ editingInvoice ? 'Speichern' : 'Erstellen' }}
                </button>
              </div>
            </form>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>
