<script setup>
import { ref, computed, nextTick } from 'vue'
import draggable from 'vuedraggable'
import api from '@/core/api/axios'
import { useToast } from '@/composables/useToast'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
import {
  PlusIcon,
  TrashIcon,
  XMarkIcon,
  CheckIcon,
  DocumentTextIcon,
  MagnifyingGlassIcon,
  Bars3Icon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  invoice: { type: Object, required: true },
})

const emit = defineEmits(['items-changed'])

const toast = useToast()
const { confirm } = useConfirmDialog()

const items = ref([...(props.invoice.items || [])])
const editingItemId = ref(null)
const editingField = ref(null)
const editValues = ref({})
const savingItem = ref(false)
const deletingItemId = ref(null)

// New item form
const showNewItemForm = ref(false)
const newItemForm = ref({ description: '', quantity: 1, unit: 'Stunde', unit_price: 0 })

// Catalog
const showCatalogPicker = ref(false)
const catalogSearch = ref('')
const serviceCatalog = ref([])
const catalogLoaded = ref(false)

const itemUnits = ['Stunde', 'Stück', 'Pauschal', 'Tag', 'Monat', 'km']

// Watch invoice changes
import { watch } from 'vue'
watch(() => props.invoice.items, (val) => {
  items.value = [...(val || [])]
}, { deep: true })

const filteredCatalog = computed(() => {
  if (!catalogSearch.value.trim()) return serviceCatalog.value
  const q = catalogSearch.value.toLowerCase()
  return serviceCatalog.value.filter(i =>
    i.name.toLowerCase().includes(q) ||
    (i.description || '').toLowerCase().includes(q)
  )
})

// Live totals computed from local items (optimistic)
const localSubtotal = computed(() => items.value.reduce((sum, i) => sum + parseFloat(i.total || 0), 0))
const localTax = computed(() => localSubtotal.value * (parseFloat(props.invoice.tax_rate) / 100))
const localTotal = computed(() => localSubtotal.value + localTax.value)

function formatCurrency(amount) {
  return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(amount || 0)
}

// Start editing a specific field of an item
function startEdit(item, field) {
  editingItemId.value = item.id
  editingField.value = field
  editValues.value = {
    description: item.description,
    quantity: item.quantity,
    unit: item.unit,
    unit_price: item.unit_price,
  }
  nextTick(() => {
    const el = document.getElementById(`edit-${field}-${item.id}`)
    if (el) { el.focus(); el.select?.() }
  })
}

function cancelEdit() {
  editingItemId.value = null
  editingField.value = null
  editValues.value = {}
}

async function saveEdit(item) {
  if (editingItemId.value !== item.id) return
  const payload = {
    description: editValues.value.description?.trim() || item.description,
    quantity: parseFloat(editValues.value.quantity) || item.quantity,
    unit: editValues.value.unit || item.unit,
    unit_price: parseFloat(editValues.value.unit_price) ?? item.unit_price,
  }
  // Optimistic update
  const idx = items.value.findIndex(i => i.id === item.id)
  if (idx !== -1) {
    items.value[idx] = {
      ...items.value[idx],
      ...payload,
      total: payload.quantity * payload.unit_price,
    }
  }
  cancelEdit()
  try {
    await api.put(`/api/v1/invoices/${props.invoice.id}/items/${item.id}`, payload)
    emit('items-changed')
  } catch {
    toast.error('Position konnte nicht gespeichert werden')
    emit('items-changed') // reload from server
  }
}

async function deleteItem(item) {
  if (!await confirm({ message: 'Position wirklich löschen?', type: 'danger', confirmText: 'Löschen' })) return
  deletingItemId.value = item.id
  items.value = items.value.filter(i => i.id !== item.id)
  try {
    await api.delete(`/api/v1/invoices/${props.invoice.id}/items/${item.id}`)
    emit('items-changed')
  } catch {
    toast.error('Position konnte nicht gelöscht werden')
    emit('items-changed')
  } finally {
    deletingItemId.value = null
  }
}

async function saveNewItem() {
  if (!newItemForm.value.description.trim()) {
    toast.warning('Beschreibung ist erforderlich')
    return
  }
  savingItem.value = true
  try {
    const payload = {
      description: newItemForm.value.description.trim(),
      quantity: parseFloat(newItemForm.value.quantity) || 1,
      unit: newItemForm.value.unit || 'Stück',
      unit_price: parseFloat(newItemForm.value.unit_price) || 0,
    }
    const res = await api.post(`/api/v1/invoices/${props.invoice.id}/items`, payload)
    const created = res.data.data
    if (created) items.value.push(created)
    showNewItemForm.value = false
    newItemForm.value = { description: '', quantity: 1, unit: 'Stunde', unit_price: 0 }
    emit('items-changed')
  } catch {
    toast.error('Position konnte nicht gespeichert werden')
  } finally {
    savingItem.value = false
  }
}

function openNewItemForm() {
  showCatalogPicker.value = false
  newItemForm.value = { description: '', quantity: 1, unit: 'Stunde', unit_price: 0 }
  showNewItemForm.value = true
  nextTick(() => {
    document.getElementById('new-item-description')?.focus()
  })
}

async function openCatalogPicker() {
  showNewItemForm.value = false
  if (!catalogLoaded.value) {
    try {
      const res = await api.get('/api/v1/service-catalog')
      serviceCatalog.value = res.data.data?.items ?? []
      catalogLoaded.value = true
    } catch {
      toast.warning('Leistungskatalog konnte nicht geladen werden')
    }
  }
  catalogSearch.value = ''
  showCatalogPicker.value = true
  nextTick(() => document.getElementById('catalog-search')?.focus())
}

function selectCatalogItem(item) {
  newItemForm.value = {
    description: item.name + (item.description ? '\n' + item.description : ''),
    quantity: 1,
    unit: item.unit,
    unit_price: parseFloat(item.unit_price),
  }
  showCatalogPicker.value = false
  showNewItemForm.value = true
  nextTick(() => document.getElementById('new-item-description')?.focus())
}

async function onDragEnd() {
  // Update sort_order after drag
  try {
    await Promise.all(
      items.value.map((item, idx) =>
        api.put(`/api/v1/invoices/${props.invoice.id}/items/${item.id}`, {
          sort_order: idx,
        })
      )
    )
    emit('items-changed')
  } catch {
    // non-critical
  }
}
</script>

<template>
  <div class="space-y-4">
    <!-- Toolbar -->
    <div class="flex items-center justify-between gap-3">
      <h3 class="text-sm font-semibold text-gray-300">
        {{ items.length }} Position{{ items.length !== 1 ? 'en' : '' }}
      </h3>
      <div class="flex gap-2">
        <button
          @click="openCatalogPicker"
          class="flex items-center gap-1.5 text-sm px-3 py-1.5 bg-white/[0.08] hover:bg-white/[0.06] text-gray-300 hover:text-white rounded-lg transition-colors border border-white/[0.08]"
        >
          <DocumentTextIcon class="w-4 h-4" />
          Aus Katalog
        </button>
        <button
          @click="openNewItemForm"
          class="flex items-center gap-1.5 text-sm px-3 py-1.5 bg-primary-600 hover:bg-primary-500 text-white rounded-lg transition-colors"
        >
          <PlusIcon class="w-4 h-4" />
          Position hinzufügen
        </button>
      </div>
    </div>

    <!-- Catalog picker dropdown -->
    <Transition
      enter-active-class="transition ease-out duration-150"
      enter-from-class="opacity-0 -translate-y-1"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition ease-in duration-100"
      leave-from-class="opacity-100 translate-y-0"
      leave-to-class="opacity-0 -translate-y-1"
    >
      <div v-if="showCatalogPicker" class="bg-white/[0.04] border border-white/[0.08] rounded-xl overflow-hidden shadow-float">
        <div class="p-3 border-b border-white/[0.06] flex items-center gap-2">
          <MagnifyingGlassIcon class="w-4 h-4 text-gray-400 shrink-0" />
          <input
            id="catalog-search"
            v-model="catalogSearch"
            type="text"
            placeholder="Leistung suchen..."
            class="flex-1 bg-transparent text-sm text-white placeholder-gray-500 outline-none"
          />
          <button @click="showCatalogPicker = false" class="text-gray-500 hover:text-white">
            <XMarkIcon class="w-4 h-4" />
          </button>
        </div>
        <div v-if="filteredCatalog.length === 0" class="px-4 py-6 text-center text-gray-500 text-sm">
          {{ serviceCatalog.length === 0
            ? 'Kein Leistungskatalog vorhanden. Lege einen an unter Einstellungen → Rechnungen.'
            : 'Keine Leistungen gefunden.' }}
        </div>
        <div v-else class="max-h-56 overflow-y-auto divide-y divide-white/[0.06]">
          <button
            v-for="item in filteredCatalog"
            :key="item.id"
            @click="selectCatalogItem(item)"
            class="w-full flex items-center justify-between px-4 py-3 hover:bg-white/[0.04] transition-colors text-left group"
          >
            <div class="min-w-0 mr-4">
              <p class="text-sm text-white font-medium group-hover:text-primary-300 transition-colors">{{ item.name }}</p>
              <p v-if="item.description" class="text-xs text-gray-500 mt-0.5 truncate">{{ item.description }}</p>
            </div>
            <span class="text-sm text-gray-300 font-mono shrink-0">
              {{ parseFloat(item.unit_price).toFixed(2) }} €/{{ item.unit }}
            </span>
          </button>
        </div>
      </div>
    </Transition>

    <!-- Items table -->
    <div class="bg-white/[0.04] rounded-xl overflow-hidden border border-white/[0.06]">
      <table class="w-full">
        <thead class="bg-white/[0.08]">
          <tr>
            <th class="w-8 px-3 py-2.5"></th>
            <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">Beschreibung</th>
            <th class="px-3 py-2.5 text-right text-xs font-semibold text-gray-400 uppercase tracking-wider w-28">Menge</th>
            <th class="px-3 py-2.5 text-right text-xs font-semibold text-gray-400 uppercase tracking-wider w-28">Preis</th>
            <th class="px-3 py-2.5 text-right text-xs font-semibold text-gray-400 uppercase tracking-wider w-28">Gesamt</th>
            <th class="px-3 py-2.5 w-10"></th>
          </tr>
        </thead>
        <draggable
          v-model="items"
          tag="tbody"
          item-key="id"
          handle=".drag-handle"
          ghost-class="opacity-30"
          animation="150"
          @end="onDragEnd"
          class="divide-y divide-white/[0.06]"
        >
          <template #item="{ element: item }">
            <tr class="group hover:bg-white/[0.02] transition-colors">
              <!-- Drag handle -->
              <td class="px-2 py-2">
                <div class="drag-handle cursor-grab active:cursor-grabbing flex items-center justify-center text-gray-600 group-hover:text-gray-500 transition-colors">
                  <Bars3Icon class="w-4 h-4" />
                </div>
              </td>

              <!-- Description -->
              <td class="px-3 py-2 text-sm">
                <div
                  v-if="editingItemId === item.id && editingField === 'description'"
                  class="flex items-start gap-2"
                >
                  <textarea
                    :id="`edit-description-${item.id}`"
                    v-model="editValues.description"
                    rows="2"
                    class="flex-1 bg-white/[0.06] border border-primary-500 rounded-lg px-2 py-1.5 text-sm text-white resize-none outline-none focus:ring-1 ring-primary-400"
                    @keydown.enter.exact.prevent="saveEdit(item)"
                    @keydown.esc="cancelEdit"
                  ></textarea>
                  <div class="flex flex-col gap-1 shrink-0">
                    <button @click="saveEdit(item)" class="p-1 bg-primary-600 hover:bg-primary-500 rounded text-white">
                      <CheckIcon class="w-3 h-3" />
                    </button>
                    <button @click="cancelEdit" class="p-1 bg-white/[0.06] hover:bg-white/[0.06] rounded text-gray-400">
                      <XMarkIcon class="w-3 h-3" />
                    </button>
                  </div>
                </div>
                <div
                  v-else
                  @click="startEdit(item, 'description')"
                  class="cursor-text text-white whitespace-pre-line hover:text-primary-300 transition-colors min-h-[1.5rem] rounded px-1 -mx-1 hover:bg-white/[0.06]/50 py-0.5"
                  title="Klicken zum Bearbeiten"
                >
                  {{ item.description || '–' }}
                </div>
              </td>

              <!-- Quantity + Unit -->
              <td class="px-3 py-2 text-sm text-right">
                <div
                  v-if="editingItemId === item.id && editingField === 'quantity'"
                  class="flex gap-1 items-center justify-end"
                >
                  <input
                    :id="`edit-quantity-${item.id}`"
                    v-model.number="editValues.quantity"
                    type="number"
                    min="0"
                    step="0.01"
                    class="w-16 bg-white/[0.06] border border-primary-500 rounded px-2 py-1 text-sm text-white text-right outline-none focus:ring-1 ring-primary-400"
                    @keydown.enter="saveEdit(item)"
                    @keydown.esc="cancelEdit"
                    @blur="saveEdit(item)"
                  />
                  <select
                    v-model="editValues.unit"
                    class="w-20 bg-white/[0.06] border border-white/[0.06] rounded px-1 py-1 text-xs text-gray-300 outline-none"
                    @change="saveEdit(item)"
                  >
                    <option v-for="u in itemUnits" :key="u" :value="u">{{ u }}</option>
                  </select>
                </div>
                <div
                  v-else
                  @click="startEdit(item, 'quantity')"
                  class="cursor-text text-gray-300 hover:text-primary-300 transition-colors hover:bg-white/[0.06]/50 rounded px-1 py-0.5 inline-block"
                  title="Klicken zum Bearbeiten"
                >
                  {{ parseFloat(item.quantity).toLocaleString('de-DE') }} {{ item.unit }}
                </div>
              </td>

              <!-- Unit price -->
              <td class="px-3 py-2 text-sm text-right">
                <div
                  v-if="editingItemId === item.id && editingField === 'unit_price'"
                >
                  <input
                    :id="`edit-unit_price-${item.id}`"
                    v-model.number="editValues.unit_price"
                    type="number"
                    min="0"
                    step="0.01"
                    class="w-24 bg-white/[0.06] border border-primary-500 rounded px-2 py-1 text-sm text-white text-right outline-none focus:ring-1 ring-primary-400"
                    @keydown.enter="saveEdit(item)"
                    @keydown.esc="cancelEdit"
                    @blur="saveEdit(item)"
                  />
                </div>
                <div
                  v-else
                  @click="startEdit(item, 'unit_price')"
                  class="cursor-text text-gray-300 hover:text-primary-300 transition-colors hover:bg-white/[0.06]/50 rounded px-1 py-0.5 inline-block"
                  title="Klicken zum Bearbeiten"
                >
                  {{ formatCurrency(item.unit_price) }}
                </div>
              </td>

              <!-- Total -->
              <td class="px-3 py-2 text-sm text-right font-semibold text-white">
                {{ formatCurrency(item.total) }}
              </td>

              <!-- Delete -->
              <td class="px-3 py-2 text-right">
                <button
                  @click="deleteItem(item)"
                  :disabled="deletingItemId === item.id"
                  class="opacity-0 group-hover:opacity-100 transition-all p-1.5 rounded-lg text-gray-500 hover:text-red-400 hover:bg-red-500/10"
                  title="Position löschen"
                >
                  <TrashIcon class="w-4 h-4" />
                </button>
              </td>
            </tr>
          </template>

          <template #footer>
            <tr v-if="items.length === 0">
              <td colspan="6" class="px-4 py-8 text-center text-gray-500 text-sm">
                Noch keine Positionen – füge eine oben hinzu.
              </td>
            </tr>
          </template>
        </draggable>

        <!-- Totals row -->
        <tfoot class="border-t-2 border-white/[0.08] bg-white/[0.03]">
          <tr v-if="parseFloat(invoice.tax_rate) > 0">
            <td colspan="4" class="px-3 py-2 text-right text-sm text-gray-400">Netto</td>
            <td class="px-3 py-2 text-right text-sm text-gray-300">{{ formatCurrency(localSubtotal) }}</td>
            <td></td>
          </tr>
          <tr v-if="parseFloat(invoice.tax_rate) > 0">
            <td colspan="4" class="px-3 py-2 text-right text-sm text-gray-400">MwSt. ({{ invoice.tax_rate }}%)</td>
            <td class="px-3 py-2 text-right text-sm text-gray-300">{{ formatCurrency(localTax) }}</td>
            <td></td>
          </tr>
          <tr>
            <td colspan="4" class="px-3 py-3 text-right text-base font-bold text-white">Gesamt</td>
            <td class="px-3 py-3 text-right text-lg font-bold text-white">{{ formatCurrency(localTotal) }}</td>
            <td></td>
          </tr>
        </tfoot>
      </table>
    </div>

    <!-- New item form -->
    <Transition
      enter-active-class="transition ease-out duration-200"
      enter-from-class="opacity-0 -translate-y-2"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition ease-in duration-150"
      leave-from-class="opacity-100 translate-y-0"
      leave-to-class="opacity-0 -translate-y-2"
    >
      <div v-if="showNewItemForm" class="bg-white/[0.04] border border-primary-500/30 rounded-xl p-4 space-y-3 shadow-glass">
        <div class="flex items-center justify-between">
          <h4 class="text-sm font-semibold text-white">Neue Position</h4>
          <button @click="showNewItemForm = false" class="text-gray-500 hover:text-white">
            <XMarkIcon class="w-4 h-4" />
          </button>
        </div>
        <div>
          <label class="block text-xs text-gray-400 mb-1">Beschreibung *</label>
          <textarea
            id="new-item-description"
            v-model="newItemForm.description"
            rows="2"
            placeholder="Leistungsbeschreibung..."
            class="input text-sm w-full resize-none"
            @keydown.esc="showNewItemForm = false"
          ></textarea>
        </div>
        <div class="grid grid-cols-3 gap-3">
          <div>
            <label class="block text-xs text-gray-400 mb-1">Menge</label>
            <input
              v-model.number="newItemForm.quantity"
              type="number" min="0" step="0.01"
              class="input text-sm w-full"
            />
          </div>
          <div>
            <label class="block text-xs text-gray-400 mb-1">Einheit</label>
            <select v-model="newItemForm.unit" class="input text-sm w-full">
              <option v-for="u in itemUnits" :key="u" :value="u">{{ u }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs text-gray-400 mb-1">Einzelpreis (€)</label>
            <input
              v-model.number="newItemForm.unit_price"
              type="number" min="0" step="0.01"
              class="input text-sm w-full"
            />
          </div>
        </div>
        <!-- Live preview of total -->
        <div class="flex items-center justify-between text-sm border-t border-white/[0.06] pt-2">
          <span class="text-gray-500">Positionsbetrag:</span>
          <span class="text-white font-semibold">
            {{ formatCurrency((parseFloat(newItemForm.quantity) || 0) * (parseFloat(newItemForm.unit_price) || 0)) }}
          </span>
        </div>
        <div class="flex gap-2 justify-end">
          <button @click="showNewItemForm = false" class="btn-secondary text-sm px-4 py-1.5">
            Abbrechen
          </button>
          <button
            @click="saveNewItem"
            :disabled="savingItem"
            class="btn-primary text-sm px-4 py-1.5 flex items-center gap-1.5"
          >
            <CheckIcon class="w-4 h-4" />
            {{ savingItem ? 'Speichern...' : 'Hinzufügen' }}
          </button>
        </div>
      </div>
    </Transition>
  </div>
</template>
