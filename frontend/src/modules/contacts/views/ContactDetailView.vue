<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useContactsStore } from '@/stores/contacts'
import { useUiStore } from '@/stores/ui'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
import {
  ArrowLeftIcon,
  PencilIcon,
  TrashIcon,
  StarIcon,
  PhoneIcon,
  DevicePhoneMobileIcon,
  EnvelopeIcon,
  BuildingOfficeIcon,
  MapPinIcon,
  GlobeAltIcon,
  PlusIcon,
  XMarkIcon,
  ChatBubbleLeftIcon,
  PhoneArrowUpRightIcon,
  CalendarDaysIcon,
  DocumentTextIcon,
  ClockIcon,
} from '@heroicons/vue/24/outline'
import { StarIcon as StarIconSolid } from '@heroicons/vue/24/solid'

const route = useRoute()
const router = useRouter()
const contactsStore = useContactsStore()
const uiStore = useUiStore()
const { confirm } = useConfirmDialog()

// State
const isEditing = ref(false)
const showActivityForm = ref(false)

// Edit form
const editForm = ref({
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
  country: '',
  website: '',
  notes: '',
  tags: [],
  avatar_color: '#6366f1',
})

const tagInput = ref('')

// Activity form
const activityForm = ref({
  type: 'note',
  subject: '',
  description: '',
  activity_date: new Date().toISOString().slice(0, 16),
})

const activityTypes = [
  { value: 'note', label: 'Notiz', icon: DocumentTextIcon },
  { value: 'email', label: 'E-Mail', icon: EnvelopeIcon },
  { value: 'call', label: 'Anruf', icon: PhoneArrowUpRightIcon },
  { value: 'meeting', label: 'Meeting', icon: CalendarDaysIcon },
]

const avatarColors = [
  '#6366f1', '#ec4899', '#10b981', '#f59e0b', '#ef4444',
  '#8b5cf6', '#06b6d4', '#84cc16', '#f97316', '#14b8a6',
]

const contact = computed(() => contactsStore.currentContact)

onMounted(async () => {
  const id = route.params.id
  try {
    await contactsStore.loadContact(id)
    await contactsStore.loadActivities(id)
  } catch (error) {
    uiStore.showError('Kontakt nicht gefunden')
    router.push('/contacts')
  }
})

function getInitials(c) {
  if (!c) return ''
  const first = c.first_name?.[0] || ''
  const last = c.last_name?.[0] || ''
  return (first + last).toUpperCase()
}

function startEditing() {
  if (!contact.value) return
  editForm.value = {
    first_name: contact.value.first_name || '',
    last_name: contact.value.last_name || '',
    email: contact.value.email || '',
    phone: contact.value.phone || '',
    mobile: contact.value.mobile || '',
    company: contact.value.company || '',
    position: contact.value.position || '',
    address: contact.value.address || '',
    city: contact.value.city || '',
    postal_code: contact.value.postal_code || '',
    country: contact.value.country || 'Deutschland',
    website: contact.value.website || '',
    notes: contact.value.notes || '',
    tags: contact.value.tags ? [...contact.value.tags] : [],
    avatar_color: contact.value.avatar_color || '#6366f1',
  }
  tagInput.value = ''
  isEditing.value = true
}

function cancelEditing() {
  isEditing.value = false
}

async function saveEdit() {
  if (!editForm.value.first_name || !editForm.value.last_name) {
    uiStore.showError('Vorname und Nachname sind erforderlich')
    return
  }

  try {
    await contactsStore.updateContact(contact.value.id, editForm.value)
    await contactsStore.loadContact(contact.value.id)
    isEditing.value = false
    uiStore.showSuccess('Kontakt aktualisiert')
  } catch (error) {
    uiStore.showError('Fehler beim Speichern')
  }
}

async function toggleFavorite() {
  try {
    await contactsStore.toggleFavorite(contact.value.id)
  } catch (error) {
    uiStore.showError('Fehler beim Aktualisieren')
  }
}

async function deleteContact() {
  if (!contact.value) return
  if (!await confirm({
    message: `"${contact.value.first_name} ${contact.value.last_name}" wirklich löschen? Alle Aktivitäten werden ebenfalls gelöscht.`,
    type: 'danger',
    confirmText: 'Löschen',
  })) return

  try {
    await contactsStore.deleteContact(contact.value.id)
    uiStore.showSuccess('Kontakt gelöscht')
    router.push('/contacts')
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
  }
}

function openActivityForm() {
  activityForm.value = {
    type: 'note',
    subject: '',
    description: '',
    activity_date: new Date().toISOString().slice(0, 16),
  }
  showActivityForm.value = true
}

async function saveActivity() {
  if (!activityForm.value.subject && !activityForm.value.description) {
    uiStore.showError('Betreff oder Beschreibung erforderlich')
    return
  }

  try {
    await contactsStore.createActivity(contact.value.id, activityForm.value)
    showActivityForm.value = false
    uiStore.showSuccess('Aktivität erstellt')
  } catch (error) {
    uiStore.showError('Fehler beim Erstellen')
  }
}

async function deleteActivity(activity) {
  if (!await confirm({ message: 'Aktivität wirklich löschen?', type: 'danger', confirmText: 'Löschen' })) return

  try {
    await contactsStore.deleteActivity(contact.value.id, activity.id)
    uiStore.showSuccess('Aktivität gelöscht')
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
  }
}

function addTag() {
  const tag = tagInput.value.trim()
  if (tag && !editForm.value.tags.includes(tag)) {
    editForm.value.tags.push(tag)
  }
  tagInput.value = ''
}

function removeTag(index) {
  editForm.value.tags.splice(index, 1)
}

function getActivityIcon(type) {
  const found = activityTypes.find(t => t.value === type)
  return found?.icon || DocumentTextIcon
}

function getActivityLabel(type) {
  const found = activityTypes.find(t => t.value === type)
  return found?.label || 'Notiz'
}

function getActivityColor(type) {
  switch (type) {
    case 'email': return 'text-blue-400 bg-blue-400/10'
    case 'call': return 'text-green-400 bg-green-400/10'
    case 'meeting': return 'text-purple-400 bg-purple-400/10'
    default: return 'text-gray-400 bg-gray-400/10'
  }
}

function formatDateTime(dateStr) {
  if (!dateStr) return '-'
  const d = new Date(dateStr)
  return d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function formatDate(dateStr) {
  if (!dateStr) return '-'
  const d = new Date(dateStr)
  return d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })
}
</script>

<template>
  <div class="space-y-6">
    <!-- Loading State -->
    <div v-if="contactsStore.isLoadingContact" class="flex items-center justify-center py-20">
      <div class="animate-spin w-8 h-8 border-4 border-primary-500 border-t-transparent rounded-full"></div>
    </div>

    <template v-else-if="contact">
      <!-- Back Button + Actions -->
      <div class="flex items-center justify-between">
        <button @click="router.push('/contacts')" class="flex items-center gap-2 text-gray-400 hover:text-white transition-colors">
          <ArrowLeftIcon class="w-5 h-5" />
          <span>Zurück zu Kontakte</span>
        </button>

        <div class="flex items-center gap-2">
          <button
            @click="toggleFavorite"
            class="p-2 rounded-lg text-gray-400 hover:text-yellow-500 hover:bg-white/[0.04] transition-colors"
            :title="contact.is_favorite ? 'Von Favoriten entfernen' : 'Zu Favoriten hinzufügen'"
          >
            <StarIconSolid v-if="contact.is_favorite" class="w-5 h-5 text-yellow-500" />
            <StarIcon v-else class="w-5 h-5" />
          </button>
          <button
            v-if="!isEditing"
            @click="startEditing"
            class="btn-secondary flex items-center gap-2"
          >
            <PencilIcon class="w-4 h-4" />
            Bearbeiten
          </button>
          <button
            @click="deleteContact"
            class="p-2 rounded-lg text-gray-400 hover:text-red-400 hover:bg-red-400/10 transition-colors"
            title="Kontakt löschen"
          >
            <TrashIcon class="w-5 h-5" />
          </button>
        </div>
      </div>

      <!-- Contact Header Card -->
      <div class="card-glass p-6">
        <div class="flex items-start gap-6">
          <!-- Large Avatar -->
          <div
            class="w-20 h-20 rounded-full flex items-center justify-center text-white font-bold text-2xl shrink-0"
            :style="{ backgroundColor: contact.avatar_color || '#6366f1' }"
          >
            {{ getInitials(contact) }}
          </div>

          <!-- Contact Info -->
          <div v-if="!isEditing" class="flex-1 min-w-0">
            <h1 class="text-2xl font-bold text-white">
              {{ contact.first_name }} {{ contact.last_name }}
            </h1>
            <p v-if="contact.company || contact.position" class="text-gray-400 mt-1 flex items-center gap-1.5">
              <BuildingOfficeIcon class="w-4 h-4 shrink-0" />
              <span v-if="contact.position">{{ contact.position }}</span>
              <span v-if="contact.position && contact.company"> bei </span>
              <span v-if="contact.company">{{ contact.company }}</span>
            </p>

            <!-- Tags -->
            <div v-if="contact.tags && contact.tags.length > 0" class="flex flex-wrap gap-1.5 mt-3">
              <span
                v-for="tag in contact.tags"
                :key="tag"
                class="px-2.5 py-0.5 text-xs rounded-full bg-primary-600/20 text-primary-300"
              >
                {{ tag }}
              </span>
            </div>

            <!-- Contact Details Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-4">
              <div v-if="contact.email" class="flex items-center gap-2 text-sm">
                <EnvelopeIcon class="w-4 h-4 text-gray-500 shrink-0" />
                <a :href="`mailto:${contact.email}`" class="text-primary-400 hover:text-primary-300 truncate">
                  {{ contact.email }}
                </a>
              </div>
              <div v-if="contact.phone" class="flex items-center gap-2 text-sm">
                <PhoneIcon class="w-4 h-4 text-gray-500 shrink-0" />
                <a :href="`tel:${contact.phone}`" class="text-gray-300 hover:text-white truncate">
                  {{ contact.phone }}
                </a>
              </div>
              <div v-if="contact.mobile" class="flex items-center gap-2 text-sm">
                <DevicePhoneMobileIcon class="w-4 h-4 text-gray-500 shrink-0" />
                <a :href="`tel:${contact.mobile}`" class="text-gray-300 hover:text-white truncate">
                  {{ contact.mobile }}
                </a>
              </div>
              <div v-if="contact.website" class="flex items-center gap-2 text-sm">
                <GlobeAltIcon class="w-4 h-4 text-gray-500 shrink-0" />
                <a :href="contact.website" target="_blank" rel="noopener" class="text-primary-400 hover:text-primary-300 truncate">
                  {{ contact.website }}
                </a>
              </div>
              <div v-if="contact.address || contact.city" class="flex items-start gap-2 text-sm sm:col-span-2">
                <MapPinIcon class="w-4 h-4 text-gray-500 shrink-0 mt-0.5" />
                <span class="text-gray-300">
                  <span v-if="contact.address">{{ contact.address }}, </span>
                  <span v-if="contact.postal_code">{{ contact.postal_code }} </span>
                  <span v-if="contact.city">{{ contact.city }}</span>
                  <span v-if="contact.country"> ({{ contact.country }})</span>
                </span>
              </div>
            </div>

            <!-- Notes -->
            <div v-if="contact.notes" class="mt-4 p-3 bg-white/[0.04] rounded-lg">
              <p class="text-sm text-gray-400 whitespace-pre-wrap">{{ contact.notes }}</p>
            </div>

            <!-- Meta -->
            <div class="flex items-center gap-4 mt-4 text-xs text-gray-500">
              <span>Erstellt: {{ formatDate(contact.created_at) }}</span>
              <span v-if="contact.last_contact_at">Letzter Kontakt: {{ formatDate(contact.last_contact_at) }}</span>
            </div>
          </div>

          <!-- Edit Form -->
          <div v-else class="flex-1 space-y-4">
            <!-- Avatar Color -->
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Farbe</label>
              <div class="flex gap-2">
                <button
                  v-for="color in avatarColors"
                  :key="color"
                  @click="editForm.avatar_color = color"
                  class="w-7 h-7 rounded-full transition-transform"
                  :class="editForm.avatar_color === color ? 'ring-2 ring-white ring-offset-2 ring-offset-gray-900 scale-110' : 'hover:scale-105'"
                  :style="{ backgroundColor: color }"
                />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Vorname *</label>
                <input v-model="editForm.first_name" type="text" class="input w-full" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Nachname *</label>
                <input v-model="editForm.last_name" type="text" class="input w-full" />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Firma</label>
                <input v-model="editForm.company" type="text" class="input w-full" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Position</label>
                <input v-model="editForm.position" type="text" class="input w-full" />
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">E-Mail</label>
              <input v-model="editForm.email" type="email" class="input w-full" />
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Telefon</label>
                <input v-model="editForm.phone" type="tel" class="input w-full" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Mobil</label>
                <input v-model="editForm.mobile" type="tel" class="input w-full" />
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Adresse</label>
              <input v-model="editForm.address" type="text" class="input w-full" />
            </div>

            <div class="grid grid-cols-3 gap-3">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">PLZ</label>
                <input v-model="editForm.postal_code" type="text" class="input w-full" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Stadt</label>
                <input v-model="editForm.city" type="text" class="input w-full" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Land</label>
                <input v-model="editForm.country" type="text" class="input w-full" />
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Website</label>
              <input v-model="editForm.website" type="url" class="input w-full" />
            </div>

            <!-- Tags -->
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Tags</label>
              <div class="flex flex-wrap gap-1 mb-2" v-if="editForm.tags.length > 0">
                <span
                  v-for="(tag, index) in editForm.tags"
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

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Notizen</label>
              <textarea v-model="editForm.notes" rows="3" class="input w-full resize-none"></textarea>
            </div>

            <div class="flex items-center justify-end gap-3">
              <button @click="cancelEditing" class="btn-secondary">Abbrechen</button>
              <button @click="saveEdit" class="btn-primary">Speichern</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Activity Timeline -->
      <div class="card-glass p-6">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-semibold text-white flex items-center gap-2">
            <ClockIcon class="w-5 h-5 text-gray-400" />
            Aktivitäten
          </h2>
          <button @click="openActivityForm" class="btn-primary flex items-center gap-2 text-sm">
            <PlusIcon class="w-4 h-4" />
            Aktivität hinzufügen
          </button>
        </div>

        <!-- Activity Form -->
        <div v-if="showActivityForm" class="mb-6 p-4 bg-white/[0.04] rounded-lg border border-white/[0.06]">
          <div class="space-y-3">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Typ</label>
              <div class="flex gap-2">
                <button
                  v-for="at in activityTypes"
                  :key="at.value"
                  @click="activityForm.type = at.value"
                  class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm transition-colors"
                  :class="activityForm.type === at.value ? 'bg-primary-600 text-white' : 'bg-white/[0.04] text-gray-400 hover:text-white'"
                >
                  <component :is="at.icon" class="w-4 h-4" />
                  {{ at.label }}
                </button>
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Betreff</label>
              <input v-model="activityForm.subject" type="text" class="input w-full" placeholder="Betreff der Aktivität..." />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Beschreibung</label>
              <textarea v-model="activityForm.description" rows="3" class="input w-full resize-none" placeholder="Beschreibung..."></textarea>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Datum</label>
              <input v-model="activityForm.activity_date" type="datetime-local" class="input w-full" />
            </div>

            <div class="flex items-center justify-end gap-3">
              <button @click="showActivityForm = false" class="btn-secondary">Abbrechen</button>
              <button @click="saveActivity" class="btn-primary">Speichern</button>
            </div>
          </div>
        </div>

        <!-- Activities List -->
        <div v-if="contactsStore.isLoadingActivities" class="flex items-center justify-center py-8">
          <div class="animate-spin w-6 h-6 border-4 border-primary-500 border-t-transparent rounded-full"></div>
        </div>

        <div v-else-if="contactsStore.activities.length === 0" class="text-center py-8 text-gray-500">
          <ChatBubbleLeftIcon class="w-12 h-12 mx-auto mb-3 opacity-50" />
          <p>Noch keine Aktivitäten</p>
          <p class="text-sm mt-1">Fügen Sie die erste Aktivität für diesen Kontakt hinzu.</p>
        </div>

        <div v-else class="space-y-3">
          <div
            v-for="activity in contactsStore.activities"
            :key="activity.id"
            class="flex items-start gap-3 p-3 bg-white/[0.02] rounded-lg hover:bg-white/[0.04] transition-colors group"
          >
            <!-- Icon -->
            <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0" :class="getActivityColor(activity.type)">
              <component :is="getActivityIcon(activity.type)" class="w-4 h-4" />
            </div>

            <!-- Content -->
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2">
                <span class="text-xs font-medium uppercase tracking-wider" :class="getActivityColor(activity.type).split(' ')[0]">
                  {{ getActivityLabel(activity.type) }}
                </span>
                <span class="text-xs text-gray-500">{{ formatDateTime(activity.activity_date) }}</span>
              </div>
              <h4 v-if="activity.subject" class="text-sm font-medium text-white mt-0.5">{{ activity.subject }}</h4>
              <p v-if="activity.description" class="text-sm text-gray-400 mt-0.5 whitespace-pre-wrap">{{ activity.description }}</p>
            </div>

            <!-- Delete -->
            <button
              @click="deleteActivity(activity)"
              class="p-1 text-gray-500 hover:text-red-400 opacity-0 group-hover:opacity-100 transition-all shrink-0"
              title="Löschen"
            >
              <TrashIcon class="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
