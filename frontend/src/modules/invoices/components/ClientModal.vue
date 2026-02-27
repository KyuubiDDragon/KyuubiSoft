<script setup>
import { ref, watch } from 'vue'
import { XMarkIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  show: Boolean,
  editingClient: { type: Object, default: null },
})

const emit = defineEmits(['close', 'save'])

const colors = [
  '#6366f1', '#8B5CF6', '#EC4899', '#EF4444', '#F97316',
  '#EAB308', '#22C55E', '#14B8A6', '#06B6D4', '#3B82F6',
]

const form = ref({
  name: '',
  company: '',
  email: '',
  phone: '',
  address_line1: '',
  address_line2: '',
  city: '',
  postal_code: '',
  country: 'Deutschland',
  vat_id: '',
  default_hourly_rate: null,
  color: '#6366f1',
})

function initForm() {
  if (props.editingClient) {
    form.value = { ...props.editingClient }
  } else {
    form.value = {
      name: '',
      company: '',
      email: '',
      phone: '',
      address_line1: '',
      address_line2: '',
      city: '',
      postal_code: '',
      country: 'Deutschland',
      vat_id: '',
      default_hourly_rate: null,
      color: '#6366f1',
    }
  }
}

watch(() => props.show, (val) => {
  if (val) initForm()
})

function handleSubmit() {
  emit('save', { ...form.value }, props.editingClient?.id ?? null)
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
            class="modal w-full max-w-lg max-h-[90vh] overflow-y-auto"
          >
            <!-- Header -->
            <div class="sticky top-0 bg-white/[0.04] px-6 py-4 border-b border-white/[0.06] flex items-center justify-between z-10">
              <div class="flex items-center gap-3">
                <div
                  class="w-8 h-8 rounded-full shrink-0"
                  :style="{ backgroundColor: form.color }"
                ></div>
                <h2 class="text-lg font-bold text-white">
                  {{ editingClient ? 'Kunde bearbeiten' : 'Neuer Kunde' }}
                </h2>
              </div>
              <button @click="$emit('close')" class="p-2 rounded-lg text-gray-400 hover:text-white hover:bg-white/[0.04] transition-colors">
                <XMarkIcon class="w-5 h-5" />
              </button>
            </div>

            <form @submit.prevent="handleSubmit" class="p-6 space-y-4">

              <!-- Name + Company -->
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="label">Name *</label>
                  <input v-model="form.name" type="text" class="input" required placeholder="Max Mustermann" />
                </div>
                <div>
                  <label class="label">Firma</label>
                  <input v-model="form.company" type="text" class="input" placeholder="Muster GmbH" />
                </div>
              </div>

              <!-- Email + Phone -->
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="label">E-Mail</label>
                  <input v-model="form.email" type="email" class="input" placeholder="max@example.com" />
                </div>
                <div>
                  <label class="label">Telefon</label>
                  <input v-model="form.phone" type="tel" class="input" placeholder="+49 ..." />
                </div>
              </div>

              <!-- Address -->
              <div>
                <label class="label">Adresse</label>
                <input v-model="form.address_line1" type="text" class="input mb-2" placeholder="Straße und Hausnummer" />
                <input v-model="form.address_line2" type="text" class="input" placeholder="Adresszusatz (optional)" />
              </div>

              <!-- PLZ + Stadt -->
              <div class="grid grid-cols-3 gap-3">
                <div>
                  <label class="label">PLZ</label>
                  <input v-model="form.postal_code" type="text" class="input" placeholder="12345" />
                </div>
                <div class="col-span-2">
                  <label class="label">Stadt</label>
                  <input v-model="form.city" type="text" class="input" placeholder="Berlin" />
                </div>
              </div>

              <!-- VAT + Hourly Rate -->
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="label">USt-IdNr.</label>
                  <input v-model="form.vat_id" type="text" class="input" placeholder="DE123456789" />
                </div>
                <div>
                  <label class="label">Std.-Satz (EUR)</label>
                  <input v-model.number="form.default_hourly_rate" type="number" class="input" step="0.01" min="0" placeholder="0,00" />
                </div>
              </div>

              <!-- Color picker -->
              <div>
                <label class="label">Farbe</label>
                <div class="flex gap-2 mt-1">
                  <button
                    v-for="color in colors"
                    :key="color"
                    type="button"
                    @click="form.color = color"
                    class="w-8 h-8 rounded-full border-2 transition-all hover:scale-110"
                    :class="form.color === color ? 'border-white scale-110 ring-2 ring-offset-2 ring-offset-black ring-white/50' : 'border-transparent'"
                    :style="{ backgroundColor: color }"
                    :title="color"
                  ></button>
                </div>
              </div>

              <!-- Actions -->
              <div class="flex gap-3 pt-2">
                <button type="button" @click="$emit('close')" class="btn-secondary flex-1">
                  Abbrechen
                </button>
                <button type="submit" class="btn-primary flex-1">
                  {{ editingClient ? 'Speichern' : 'Erstellen' }}
                </button>
              </div>
            </form>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>
