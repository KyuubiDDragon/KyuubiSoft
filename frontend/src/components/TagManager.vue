<script setup>
import { ref, computed, onMounted } from 'vue'
import { useTagsStore } from '@/stores/tags'
import { useUiStore } from '@/stores/ui'
import { useToast } from '@/composables/useToast'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
import {
  TagIcon,
  PlusIcon,
  PencilIcon,
  TrashIcon,
  MagnifyingGlassIcon,
  ArrowsRightLeftIcon,
  XMarkIcon,
  CheckIcon,
} from '@heroicons/vue/24/outline'

const tagsStore = useTagsStore()
const uiStore = useUiStore()
const toast = useToast()
const { confirm } = useConfirmDialog()

const searchQuery = ref('')
const isCreating = ref(false)
const editingTag = ref(null)
const mergeMode = ref(false)
const selectedForMerge = ref([])
const mergeTarget = ref(null)

const newTag = ref({
  name: '',
  color: '#6366f1',
  description: '',
  icon: ''
})

const editForm = ref({
  name: '',
  color: '',
  description: '',
  icon: ''
})

const filteredTags = computed(() => {
  const query = searchQuery.value.toLowerCase()
  if (!query) return tagsStore.tags
  return tagsStore.tags.filter(tag =>
    tag.name.toLowerCase().includes(query) ||
    (tag.description && tag.description.toLowerCase().includes(query))
  )
})

const predefinedColors = [
  '#6366f1', '#8b5cf6', '#ec4899', '#ef4444', '#f97316',
  '#eab308', '#22c55e', '#14b8a6', '#06b6d4', '#3b82f6'
]

const typeLabels = {
  list: 'Listen',
  document: 'Dokumente',
  snippet: 'Snippets',
  bookmark: 'Lesezeichen',
  connection: 'Verbindungen',
  password: 'Passwörter',
  checklist: 'Checklisten',
  kanban_board: 'Kanban Boards',
  kanban_card: 'Kanban Cards',
  project: 'Projekte',
  invoice: 'Rechnungen',
  calendar_event: 'Kalender'
}

onMounted(async () => {
  await tagsStore.loadTags()
})

async function createTag() {
  if (!newTag.value.name.trim()) {
    uiStore.showError('Name ist erforderlich')
    return
  }

  try {
    await tagsStore.createTag(newTag.value)
    uiStore.showSuccess('Tag erstellt')
    newTag.value = { name: '', color: '#6366f1', description: '', icon: '' }
    isCreating.value = false
  } catch (e) {
    uiStore.showError('Tag konnte nicht erstellt werden')
  }
}

function startEdit(tag) {
  editingTag.value = tag.id
  editForm.value = {
    name: tag.name,
    color: tag.color,
    description: tag.description || '',
    icon: tag.icon || ''
  }
}

async function saveEdit() {
  if (!editForm.value.name.trim()) {
    uiStore.showError('Name ist erforderlich')
    return
  }

  try {
    await tagsStore.updateTag(editingTag.value, editForm.value)
    uiStore.showSuccess('Tag aktualisiert')
    editingTag.value = null
  } catch (e) {
    uiStore.showError('Tag konnte nicht aktualisiert werden')
  }
}

function cancelEdit() {
  editingTag.value = null
}

async function deleteTag(tag) {
  if (!await confirm({ message: `Tag "${tag.name}" wirklich löschen? Er wird von ${tag.usage_count || 0} Elementen entfernt.`, type: 'danger', confirmText: 'Löschen' })) return

  try {
    await tagsStore.deleteTag(tag.id)
    uiStore.showSuccess('Tag gelöscht')
  } catch (e) {
    uiStore.showError('Tag konnte nicht gelöscht werden')
  }
}

function toggleMergeSelection(tagId) {
  const idx = selectedForMerge.value.indexOf(tagId)
  if (idx >= 0) {
    selectedForMerge.value.splice(idx, 1)
  } else {
    selectedForMerge.value.push(tagId)
  }
}

async function performMerge() {
  if (selectedForMerge.value.length < 2) {
    uiStore.showError('Mindestens 2 Tags zum Zusammenführen auswählen')
    return
  }
  if (!mergeTarget.value) {
    uiStore.showError('Ziel-Tag auswählen')
    return
  }

  const sourceIds = selectedForMerge.value.filter(id => id !== mergeTarget.value)

  try {
    await tagsStore.mergeTags(sourceIds, mergeTarget.value)
    uiStore.showSuccess('Tags zusammengeführt')
    cancelMerge()
  } catch (e) {
    uiStore.showError('Zusammenführung fehlgeschlagen')
  }
}

function cancelMerge() {
  mergeMode.value = false
  selectedForMerge.value = []
  mergeTarget.value = null
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-lg font-semibold text-white flex items-center gap-2">
          <TagIcon class="w-5 h-5 text-primary-400" />
          Globale Tags
        </h2>
        <p class="text-sm text-gray-400 mt-1">
          Tags können auf verschiedene Module angewendet werden
        </p>
      </div>
      <div class="flex gap-2">
        <button
          v-if="!mergeMode"
          @click="mergeMode = true"
          class="btn-secondary text-sm"
          :disabled="tagsStore.tags.length < 2"
        >
          <ArrowsRightLeftIcon class="w-4 h-4" />
          Zusammenführen
        </button>
        <button
          v-if="mergeMode"
          @click="cancelMerge"
          class="btn-secondary text-sm"
        >
          Abbrechen
        </button>
        <button
          @click="isCreating = !isCreating"
          class="btn-primary text-sm"
        >
          <PlusIcon class="w-4 h-4" />
          Neuer Tag
        </button>
      </div>
    </div>

    <!-- Merge Info Banner -->
    <div v-if="mergeMode" class="bg-yellow-500/[0.06] border border-yellow-500/20 rounded-xl p-4">
      <h3 class="text-yellow-400 font-medium mb-2">Tags zusammenführen</h3>
      <p class="text-sm text-gray-400 mb-3">
        Wähle die Tags aus, die zusammengeführt werden sollen, und bestimme dann den Ziel-Tag.
        Alle Elemente werden auf den Ziel-Tag übertragen, die anderen Tags werden gelöscht.
      </p>
      <div class="flex items-center gap-4">
        <span class="text-sm text-gray-300">
          {{ selectedForMerge.length }} ausgewählt
        </span>
        <select
          v-model="mergeTarget"
          class="input text-sm py-1"
          :disabled="selectedForMerge.length < 2"
        >
          <option :value="null">Ziel-Tag wählen...</option>
          <option
            v-for="tagId in selectedForMerge"
            :key="tagId"
            :value="tagId"
          >
            {{ tagsStore.tagById[tagId]?.name }}
          </option>
        </select>
        <button
          @click="performMerge"
          class="btn-primary text-sm"
          :disabled="selectedForMerge.length < 2 || !mergeTarget"
        >
          Zusammenführen
        </button>
      </div>
    </div>

    <!-- Create Form -->
    <div v-if="isCreating" class="card p-4">
      <h3 class="font-medium text-white mb-4">Neuen Tag erstellen</h3>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="label">Name</label>
          <input
            v-model="newTag.name"
            type="text"
            class="input"
            placeholder="Tag-Name"
          />
        </div>
        <div>
          <label class="label">Farbe</label>
          <div class="flex gap-2">
            <button
              v-for="color in predefinedColors"
              :key="color"
              @click="newTag.color = color"
              class="w-6 h-6 rounded-full transition-transform"
              :class="newTag.color === color ? 'ring-2 ring-white scale-110' : ''"
              :style="{ backgroundColor: color }"
            />
          </div>
        </div>
        <div class="md:col-span-2">
          <label class="label">Beschreibung (optional)</label>
          <input
            v-model="newTag.description"
            type="text"
            class="input"
            placeholder="Beschreibung"
          />
        </div>
      </div>
      <div class="flex justify-end gap-2 mt-4">
        <button @click="isCreating = false" class="btn-secondary">
          Abbrechen
        </button>
        <button @click="createTag" class="btn-primary">
          Erstellen
        </button>
      </div>
    </div>

    <!-- Search -->
    <div class="relative">
      <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-500" />
      <input
        v-model="searchQuery"
        type="text"
        class="input pl-10"
        placeholder="Tags durchsuchen..."
      />
    </div>

    <!-- Tags List -->
    <div v-if="tagsStore.isLoading" class="text-center py-8">
      <div class="animate-spin w-8 h-8 border-2 border-primary-500 border-t-transparent rounded-full mx-auto" />
    </div>

    <div v-else-if="filteredTags.length === 0" class="text-center py-12">
      <TagIcon class="w-12 h-12 text-gray-600 mx-auto mb-3" />
      <p class="text-gray-400">Keine Tags gefunden</p>
      <button @click="isCreating = true" class="mt-4 btn-primary">
        <PlusIcon class="w-4 h-4" />
        Ersten Tag erstellen
      </button>
    </div>

    <div v-else class="grid gap-3">
      <div
        v-for="tag in filteredTags"
        :key="tag.id"
        class="card p-4"
        :class="{ 'ring-2 ring-yellow-500': mergeMode && selectedForMerge.includes(tag.id) }"
      >
        <div v-if="editingTag !== tag.id" class="flex items-start gap-4">
          <!-- Merge Checkbox -->
          <label v-if="mergeMode" class="flex items-center cursor-pointer">
            <input
              type="checkbox"
              :checked="selectedForMerge.includes(tag.id)"
              @change="toggleMergeSelection(tag.id)"
              class="rounded border-white/[0.08] bg-white/[0.04] text-yellow-600 focus:ring-yellow-600"
            />
          </label>

          <!-- Color Dot -->
          <span
            class="w-4 h-4 rounded-full shrink-0 mt-1"
            :style="{ backgroundColor: tag.color }"
          />

          <!-- Info -->
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
              <h4 class="font-medium text-white">{{ tag.name }}</h4>
              <span class="text-xs text-gray-500 bg-white/[0.06] px-2 py-0.5 rounded-full">
                {{ tag.usage_count || 0 }} Verwendungen
              </span>
            </div>
            <p v-if="tag.description" class="text-sm text-gray-400 mt-1">
              {{ tag.description }}
            </p>
            <!-- Usage by type -->
            <div v-if="tag.usage_by_type && Object.keys(tag.usage_by_type).length > 0" class="flex flex-wrap gap-2 mt-2">
              <span
                v-for="(count, type) in tag.usage_by_type"
                :key="type"
                class="text-xs text-gray-500"
              >
                {{ typeLabels[type] || type }}: {{ count }}
              </span>
            </div>
          </div>

          <!-- Actions -->
          <div v-if="!mergeMode" class="flex gap-1">
            <button
              @click="startEdit(tag)"
              class="p-2 text-gray-400 hover:text-white hover:bg-white/[0.04] rounded-lg"
            >
              <PencilIcon class="w-4 h-4" />
            </button>
            <button
              @click="deleteTag(tag)"
              class="p-2 text-gray-400 hover:text-red-400 hover:bg-red-500/10 rounded-lg"
            >
              <TrashIcon class="w-4 h-4" />
            </button>
          </div>
        </div>

        <!-- Edit Form -->
        <div v-else class="space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="label">Name</label>
              <input
                v-model="editForm.name"
                type="text"
                class="input"
              />
            </div>
            <div>
              <label class="label">Farbe</label>
              <div class="flex gap-2">
                <button
                  v-for="color in predefinedColors"
                  :key="color"
                  @click="editForm.color = color"
                  class="w-6 h-6 rounded-full transition-transform"
                  :class="editForm.color === color ? 'ring-2 ring-white scale-110' : ''"
                  :style="{ backgroundColor: color }"
                />
              </div>
            </div>
            <div class="md:col-span-2">
              <label class="label">Beschreibung</label>
              <input
                v-model="editForm.description"
                type="text"
                class="input"
              />
            </div>
          </div>
          <div class="flex justify-end gap-2">
            <button @click="cancelEdit" class="btn-secondary">
              <XMarkIcon class="w-4 h-4" />
              Abbrechen
            </button>
            <button @click="saveEdit" class="btn-primary">
              <CheckIcon class="w-4 h-4" />
              Speichern
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
