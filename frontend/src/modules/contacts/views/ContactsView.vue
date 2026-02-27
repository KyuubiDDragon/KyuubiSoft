<script setup>
import { ref, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useContactsStore } from '@/stores/contacts'
import { useUiStore } from '@/stores/ui'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
import {
  MagnifyingGlassIcon,
  PlusIcon,
  StarIcon,
  PhoneIcon,
  EnvelopeIcon,
  BuildingOfficeIcon,
  UserGroupIcon,
  HeartIcon,
  ClockIcon,
  XMarkIcon,
  GlobeAltIcon,
} from '@heroicons/vue/24/outline'
import { StarIcon as StarIconSolid } from '@heroicons/vue/24/solid'

const router = useRouter()
const contactsStore = useContactsStore()
const uiStore = useUiStore()
const { confirm } = useConfirmDialog()

// Modal state
const showCreateModal = ref(false)
const editingContact = ref(null)

// Form state
const contactForm = ref({
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
  mobile: '',
  company: '',
  position: '',
  address: '',
  city: '',
  postal_code: '',
  country: 'Deutschland',
  website: '',
  notes: '',
  tags: [],
  avatar_color: '#6366f1',
})

const tagInput = ref('')

const avatarColors = [
  '#6366f1', '#ec4899', '#10b981', '#f59e0b', '#ef4444',
  '#8b5cf6', '#06b6d4', '#84cc16', '#f97316', '#14b8a6',
]

const sortOptions = [
  { value: 'name_asc', label: 'Name A-Z' },
  { value: 'name_desc', label: 'Name Z-A' },
  { value: 'company_asc', label: 'Firma A-Z' },
  { value: 'company_desc', label: 'Firma Z-A' },
  { value: 'created_desc', label: 'Neueste zuerst' },
  { value: 'created_asc', label: 'Älteste zuerst' },
  { value: 'last_contact', label: 'Letzter Kontakt' },
]

// Load data on mount
onMounted(async () => {
  await Promise.all([
    contactsStore.loadContacts(),
    contactsStore.loadStats(),
  ])
})

// Watch filters
let searchTimeout = null
watch(() => contactsStore.filters.search, () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    contactsStore.loadContacts()
  }, 300)
})

watch(() => contactsStore.filters.sort, () => {
  contactsStore.loadContacts()
})

watch(() => contactsStore.filters.favorite, () => {
  contactsStore.loadContacts()
})

function getInitials(contact) {
  const first = contact.first_name?.[0] || ''
  const last = contact.last_name?.[0] || ''
  return (first + last).toUpperCase()
}

function openCreateModal() {
  editingContact.value = null
  contactForm.value = {
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    mobile: '',
    company: '',
    position: '',
    address: '',
    city: '',
    postal_code: '',
    country: 'Deutschland',
    website: '',
    notes: '',
    tags: [],
    avatar_color: avatarColors[Math.floor(Math.random() * avatarColors.length)],
  }
  tagInput.value = ''
  showCreateModal.value = true
}

function openEditModal(contact) {
  editingContact.value = contact
  contactForm.value = {
    first_name: contact.first_name || '',
    last_name: contact.last_name || '',
    email: contact.email || '',
    phone: contact.phone || '',
    mobile: contact.mobile || '',
    company: contact.company || '',
    position: contact.position || '',
    address: contact.address || '',
    city: contact.city || '',
    postal_code: contact.postal_code || '',
    country: contact.country || 'Deutschland',
    website: contact.website || '',
    notes: contact.notes || '',
    tags: contact.tags ? [...contact.tags] : [],
    avatar_color: contact.avatar_color || '#6366f1',
  }
  tagInput.value = ''
  showCreateModal.value = true
}

function closeModal() {
  showCreateModal.value = false
  editingContact.value = null
}

async function saveContact() {
  if (!contactForm.value.first_name || !contactForm.value.last_name) {
    uiStore.showError('Vorname und Nachname sind erforderlich')
    return
  }

  try {
    if (editingContact.value) {
      await contactsStore.updateContact(editingContact.value.id, contactForm.value)
      uiStore.showSuccess('Kontakt aktualisiert')
    } else {
      await contactsStore.createContact(contactForm.value)
      uiStore.showSuccess('Kontakt erstellt')
    }
    closeModal()
  } catch (error) {
    uiStore.showError('Fehler beim Speichern')
  }
}

async function deleteContact(contact, event) {
  event.stopPropagation()
  if (!await confirm({ message: `"${contact.first_name} ${contact.last_name}" wirklich löschen?`, type: 'danger', confirmText: 'Löschen' })) return

  try {
    await contactsStore.deleteContact(contact.id)
    uiStore.showSuccess('Kontakt gelöscht')
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
  }
}

async function toggleFavorite(contact, event) {
  event.stopPropagation()
  try {
    await contactsStore.toggleFavorite(contact.id)
  } catch (error) {
    uiStore.showError('Fehler beim Aktualisieren')
  }
}

function navigateToContact(contact) {
  router.push(`/contacts/${contact.id}`)
}

function addTag() {
  const tag = tagInput.value.trim()
  if (tag && !contactForm.value.tags.includes(tag)) {
    contactForm.value.tags.push(tag)
  }
  tagInput.value = ''
}

function removeTag(index) {
  contactForm.value.tags.splice(index, 1)
}

function setFilter(type) {
  if (type === 'all') {
    contactsStore.filters.favorite = false
  } else if (type === 'favorites') {
    contactsStore.filters.favorite = true
  }
}

function formatDate(dateStr) {
  if (!dateStr) return '-'
  const d = new Date(dateStr)
  return d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })
}
</script>

<template>
  <div class="space-y-6">
    <!-- Stats Row -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <div class="card-glass p-4 text-center">
        <div class="text-2xl font-bold text-white">{{ contactsStore.stats.total }}</div>
        <div class="text-sm text-gray-400 flex items-center justify-center gap-1.5 mt-1">
          <UserGroupIcon class="w-4 h-4" />
          Kontakte gesamt
        </div>
      </div>
      <div class="card-glass p-4 text-center">
        <div class="text-2xl font-bold text-yellow-400">{{ contactsStore.stats.favorites }}</div>
        <div class="text-sm text-gray-400 flex items-center justify-center gap-1.5 mt-1">
          <StarIcon class="w-4 h-4" />
          Favoriten
        </div>
      </div>
      <div class="card-glass p-4 text-center">
        <div class="text-2xl font-bold text-green-400">{{ contactsStore.stats.recent }}</div>
        <div class="text-sm text-gray-400 flex items-center justify-center gap-1.5 mt-1">
          <ClockIcon class="w-4 h-4" />
          Letzte 30 Tage
        </div>
      </div>
    </div>

    <!-- Toolbar -->
    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
      <div class="relative flex-1">
        <MagnifyingGlassIcon class="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
        <input
          v-model="contactsStore.filters.search"
          type="text"
          placeholder="Kontakte suchen..."
          class="input pl-10 w-full"
        />
      </div>

      <!-- Filter Tabs -->
      <div class="flex items-center bg-white/[0.04] rounded-lg p-1">
        <button
          @click="setFilter('all')"
          class="px-3 py-1.5 rounded-md text-sm font-medium transition-colors"
          :class="!contactsStore.filters.favorite ? 'bg-primary-600 text-white' : 'text-gray-400 hover:text-white'"
        >
          Alle
        </button>
        <button
          @click="setFilter('favorites')"
          class="px-3 py-1.5 rounded-md text-sm font-medium transition-colors"
          :class="contactsStore.filters.favorite ? 'bg-primary-600 text-white' : 'text-gray-400 hover:text-white'"
        >
          Favoriten
        </button>
      </div>

      <select v-model="contactsStore.filters.sort" class="select">
        <option v-for="opt in sortOptions" :key="opt.value" :value="opt.value">
          {{ opt.label }}
        </option>
      </select>

      <button @click="openCreateModal" class="btn-primary flex items-center justify-center gap-2 whitespace-nowrap">
        <PlusIcon class="w-5 h-5" />
        Neuer Kontakt
      </button>
    </div>

    <!-- Loading State -->
    <div v-if="contactsStore.isLoading" class="flex items-center justify-center py-20">
      <div class="animate-spin w-8 h-8 border-4 border-primary-500 border-t-transparent rounded-full"></div>
    </div>

    <!-- Empty State -->
    <div v-else-if="contactsStore.contacts.length === 0" class="flex flex-col items-center justify-center py-20 text-gray-400">
      <UserGroupIcon class="w-16 h-16 mb-4 opacity-50" />
      <p class="text-lg font-medium text-gray-300">Keine Kontakte gefunden</p>
      <p class="mt-1 text-sm">Erstellen Sie Ihren ersten Kontakt, um loszulegen.</p>
      <button @click="openCreateModal" class="btn-primary mt-4 flex items-center gap-2">
        <PlusIcon class="w-5 h-5" />
        Kontakt erstellen
      </button>
    </div>

    <!-- Contact Cards Grid -->
    <div v-else class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-4">
      <div
        v-for="contact in contactsStore.contacts"
        :key="contact.id"
        @click="navigateToContact(contact)"
        class="card-glass p-5 hover:bg-white/[0.06] transition-colors cursor-pointer group"
      >
        <div class="flex items-start gap-4">
          <!-- Avatar -->
          <div
            class="w-12 h-12 rounded-full flex items-center justify-center text-white font-semibold text-lg shrink-0"
            :style="{ backgroundColor: contact.avatar_color || '#6366f1' }"
          >
            {{ getInitials(contact) }}
          </div>

          <!-- Info -->
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
              <h3 class="font-medium text-white truncate">
                {{ contact.first_name }} {{ contact.last_name }}
              </h3>
              <button
                @click="toggleFavorite(contact, $event)"
                class="shrink-0 text-gray-400 hover:text-yellow-500 transition-colors"
              >
                <StarIconSolid v-if="contact.is_favorite" class="w-4 h-4 text-yellow-500" />
                <StarIcon v-else class="w-4 h-4" />
              </button>
            </div>

            <p v-if="contact.company" class="text-sm text-gray-400 truncate flex items-center gap-1.5 mt-0.5">
              <BuildingOfficeIcon class="w-3.5 h-3.5 shrink-0" />
              {{ contact.company }}
              <span v-if="contact.position" class="text-gray-500">&middot; {{ contact.position }}</span>
            </p>

            <div class="mt-2 space-y-1">
              <p v-if="contact.email" class="text-xs text-gray-500 truncate flex items-center gap-1.5">
                <EnvelopeIcon class="w-3.5 h-3.5 shrink-0" />
                {{ contact.email }}
              </p>
              <p v-if="contact.phone" class="text-xs text-gray-500 truncate flex items-center gap-1.5">
                <PhoneIcon class="w-3.5 h-3.5 shrink-0" />
                {{ contact.phone }}
              </p>
            </div>

            <!-- Tags -->
            <div v-if="contact.tags && contact.tags.length > 0" class="flex flex-wrap gap-1 mt-2">
              <span
                v-for="tag in contact.tags.slice(0, 3)"
                :key="tag"
                class="px-2 py-0.5 text-xs rounded-full bg-white/[0.06] text-gray-300"
              >
                {{ tag }}
              </span>
              <span v-if="contact.tags.length > 3" class="px-2 py-0.5 text-xs rounded-full bg-white/[0.06] text-gray-400">
                +{{ contact.tags.length - 3 }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <Teleport to="body">
      <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-md" @click="closeModal" />
        <div class="relative modal max-w-lg w-full max-h-[85vh] overflow-y-auto">
          <!-- Header -->
          <div class="flex items-center justify-between p-6 border-b border-white/[0.06]">
            <h2 class="text-lg font-semibold text-white">
              {{ editingContact ? 'Kontakt bearbeiten' : 'Neuer Kontakt' }}
            </h2>
            <button @click="closeModal" class="text-gray-400 hover:text-white">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <!-- Form -->
          <div class="p-6 space-y-4">
            <!-- Avatar Color -->
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Farbe</label>
              <div class="flex gap-2">
                <button
                  v-for="color in avatarColors"
                  :key="color"
                  @click="contactForm.avatar_color = color"
                  class="w-8 h-8 rounded-full transition-transform"
                  :class="contactForm.avatar_color === color ? 'ring-2 ring-white ring-offset-2 ring-offset-gray-900 scale-110' : 'hover:scale-105'"
                  :style="{ backgroundColor: color }"
                />
              </div>
            </div>

            <!-- Name Row -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Vorname *</label>
                <input v-model="contactForm.first_name" type="text" class="input w-full" placeholder="Max" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Nachname *</label>
                <input v-model="contactForm.last_name" type="text" class="input w-full" placeholder="Mustermann" />
              </div>
            </div>

            <!-- Company Row -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Firma</label>
                <input v-model="contactForm.company" type="text" class="input w-full" placeholder="Firma GmbH" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Position</label>
                <input v-model="contactForm.position" type="text" class="input w-full" placeholder="Geschäftsführer" />
              </div>
            </div>

            <!-- Contact Info -->
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">E-Mail</label>
              <input v-model="contactForm.email" type="email" class="input w-full" placeholder="max@example.de" />
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Telefon</label>
                <input v-model="contactForm.phone" type="tel" class="input w-full" placeholder="+49 123 456789" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Mobil</label>
                <input v-model="contactForm.mobile" type="tel" class="input w-full" placeholder="+49 170 1234567" />
              </div>
            </div>

            <!-- Address -->
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Adresse</label>
              <input v-model="contactForm.address" type="text" class="input w-full" placeholder="Musterstraße 1" />
            </div>

            <div class="grid grid-cols-3 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">PLZ</label>
                <input v-model="contactForm.postal_code" type="text" class="input w-full" placeholder="12345" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Stadt</label>
                <input v-model="contactForm.city" type="text" class="input w-full" placeholder="Berlin" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Land</label>
                <input v-model="contactForm.country" type="text" class="input w-full" placeholder="Deutschland" />
              </div>
            </div>

            <!-- Website -->
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Website</label>
              <input v-model="contactForm.website" type="url" class="input w-full" placeholder="https://example.de" />
            </div>

            <!-- Tags -->
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Tags</label>
              <div class="flex flex-wrap gap-1 mb-2" v-if="contactForm.tags.length > 0">
                <span
                  v-for="(tag, index) in contactForm.tags"
                  :key="index"
                  class="inline-flex items-center gap-1 px-2 py-0.5 text-xs rounded-full bg-primary-600/20 text-primary-300"
                >
                  {{ tag }}
                  <button @click="removeTag(index)" class="hover:text-white">
                    <XMarkIcon class="w-3 h-3" />
                  </button>
                </span>
              </div>
              <div class="flex gap-2">
                <input
                  v-model="tagInput"
                  type="text"
                  class="input flex-1"
                  placeholder="Tag hinzufügen..."
                  @keydown.enter.prevent="addTag"
                />
                <button @click="addTag" class="btn-secondary px-3">+</button>
              </div>
            </div>

            <!-- Notes -->
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Notizen</label>
              <textarea
                v-model="contactForm.notes"
                rows="3"
                class="input w-full resize-none"
                placeholder="Anmerkungen..."
              ></textarea>
            </div>
          </div>

          <!-- Footer -->
          <div class="flex items-center justify-end gap-3 p-6 border-t border-white/[0.06]">
            <button @click="closeModal" class="btn-secondary">Abbrechen</button>
            <button @click="saveContact" class="btn-primary">
              {{ editingContact ? 'Speichern' : 'Erstellen' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
