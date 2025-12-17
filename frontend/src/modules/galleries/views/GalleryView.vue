<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/core/api/axios'
import { useToast } from '@/composables/useToast'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
import {
  PlusIcon,
  TrashIcon,
  PencilIcon,
  PhotoIcon,
  EyeIcon,
  LinkIcon,
  LockClosedIcon,
  GlobeAltIcon,
  ClipboardDocumentIcon,
  ChartBarIcon,
  Squares2X2Icon,
  ListBulletIcon,
  ArrowsUpDownIcon,
} from '@heroicons/vue/24/outline'

const toast = useToast()
const { confirm } = useConfirmDialog()

// State
const galleries = ref([])
const categories = ref([])
const isLoading = ref(true)
const showModal = ref(false)
const showItemModal = ref(false)
const showDetailModal = ref(false)
const editingGallery = ref(null)
const selectedGallery = ref(null)

// Form
const form = ref({
  name: '',
  description: '',
  slug: '',
  layout: 'grid',
  theme: 'auto',
  items_per_row: 3,
  is_public: true,
  password: '',
  show_header: true,
  show_description: true,
  show_item_titles: true,
  show_download_button: false,
  allow_indexing: false,
  expires_at: '',
  max_views: null,
  category_id: '',
  project_id: '',
})

const itemForm = ref({
  item_type: 'custom',
  title: '',
  description: '',
  url: '',
  thumbnail_url: '',
  is_featured: false,
  allow_download: true,
})

const layouts = [
  { value: 'grid', label: 'Grid', icon: Squares2X2Icon },
  { value: 'list', label: 'Liste', icon: ListBulletIcon },
  { value: 'masonry', label: 'Masonry', icon: Squares2X2Icon },
]

const themes = [
  { value: 'auto', label: 'Auto' },
  { value: 'light', label: 'Hell' },
  { value: 'dark', label: 'Dunkel' },
]

const itemTypes = [
  { value: 'custom', label: 'Benutzerdefiniert' },
  { value: 'image', label: 'Bild' },
  { value: 'video', label: 'Video' },
  { value: 'link', label: 'Link' },
  { value: 'embed', label: 'Embed' },
]

// Methods
const loadGalleries = async () => {
  try {
    isLoading.value = true
    const response = await api.get('/api/v1/galleries')
    galleries.value = response.data.data.items
    categories.value = response.data.data.categories
  } catch (error) {
    console.error('Failed to load galleries:', error)
  } finally {
    isLoading.value = false
  }
}

const loadGalleryDetails = async (id) => {
  try {
    const response = await api.get(`/api/v1/galleries/${id}`)
    selectedGallery.value = response.data.data
    showDetailModal.value = true
  } catch (error) {
    console.error('Failed to load gallery details:', error)
  }
}

const saveGallery = async () => {
  try {
    if (editingGallery.value) {
      await api.put(`/api/v1/galleries/${editingGallery.value.id}`, form.value)
    } else {
      await api.post('/api/v1/galleries', form.value)
    }
    showModal.value = false
    resetForm()
    await loadGalleries()
  } catch (error) {
    console.error('Failed to save gallery:', error)
  }
}

const deleteGallery = async (id) => {
  if (!await confirm({ message: 'Galerie wirklich löschen?', type: 'danger', confirmText: 'Löschen' })) return
  try {
    await api.delete(`/api/v1/galleries/${id}`)
    await loadGalleries()
  } catch (error) {
    console.error('Failed to delete gallery:', error)
  }
}

const addItem = async () => {
  if (!selectedGallery.value) return
  try {
    await api.post(`/api/v1/galleries/${selectedGallery.value.gallery.id}/items`, itemForm.value)
    showItemModal.value = false
    resetItemForm()
    await loadGalleryDetails(selectedGallery.value.gallery.id)
  } catch (error) {
    console.error('Failed to add item:', error)
  }
}

const removeItem = async (itemId) => {
  if (!selectedGallery.value) return
  if (!await confirm({ message: 'Element entfernen?', type: 'danger', confirmText: 'Löschen' })) return
  try {
    await api.delete(`/api/v1/galleries/${selectedGallery.value.gallery.id}/items/${itemId}`)
    await loadGalleryDetails(selectedGallery.value.gallery.id)
  } catch (error) {
    console.error('Failed to remove item:', error)
  }
}

const copyPublicUrl = (gallery) => {
  const baseUrl = window.location.origin
  const url = `${baseUrl}/api/v1/gallery/${gallery.slug}`
  navigator.clipboard.writeText(url)
}

const editGallery = (gallery) => {
  editingGallery.value = gallery
  form.value = { ...gallery, password: '' }
  showModal.value = true
}

const resetForm = () => {
  editingGallery.value = null
  form.value = {
    name: '',
    description: '',
    slug: '',
    layout: 'grid',
    theme: 'auto',
    items_per_row: 3,
    is_public: true,
    password: '',
    show_header: true,
    show_description: true,
    show_item_titles: true,
    show_download_button: false,
    allow_indexing: false,
    expires_at: '',
    max_views: null,
    category_id: '',
    project_id: '',
  }
}

const resetItemForm = () => {
  itemForm.value = {
    item_type: 'custom',
    title: '',
    description: '',
    url: '',
    thumbnail_url: '',
    is_featured: false,
    allow_download: true,
  }
}

const formatDate = (date) => {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('de-DE')
}

onMounted(() => {
  loadGalleries()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex justify-between items-center">
      <div>
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Galerien</h1>
        <p class="text-gray-500 dark:text-gray-400">Erstelle und teile öffentliche Sammlungen</p>
      </div>
      <button @click="showModal = true" class="btn-primary">
        <PlusIcon class="w-5 h-5 mr-2" />
        Neue Galerie
      </button>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="flex justify-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
    </div>

    <!-- Gallery Grid -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <div v-for="gallery in galleries" :key="gallery.id"
           class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden hover:shadow-lg transition-shadow">
        <!-- Cover -->
        <div class="h-40 bg-gradient-to-br from-indigo-500 to-purple-600 relative"
             :style="gallery.cover_image_url ? { backgroundImage: `url(${gallery.cover_image_url})`, backgroundSize: 'cover' } : {}">
          <div class="absolute top-2 right-2 flex gap-1">
            <span v-if="gallery.is_password_protected"
                  class="p-1.5 bg-black/30 rounded-lg backdrop-blur">
              <LockClosedIcon class="w-4 h-4 text-white" />
            </span>
            <span v-if="gallery.is_public"
                  class="p-1.5 bg-black/30 rounded-lg backdrop-blur">
              <GlobeAltIcon class="w-4 h-4 text-white" />
            </span>
          </div>
          <div class="absolute bottom-2 left-2">
            <span class="px-2 py-1 bg-black/30 rounded-lg backdrop-blur text-white text-xs">
              {{ gallery.item_count || 0 }} Elemente
            </span>
          </div>
        </div>

        <!-- Content -->
        <div class="p-4">
          <div class="flex items-start justify-between mb-2">
            <div>
              <h3 class="font-semibold text-gray-900 dark:text-white">{{ gallery.name }}</h3>
              <p class="text-sm text-gray-500 dark:text-gray-400 line-clamp-2">
                {{ gallery.description || 'Keine Beschreibung' }}
              </p>
            </div>
          </div>

          <div class="flex items-center gap-4 text-sm text-gray-500 mb-3">
            <div class="flex items-center gap-1">
              <EyeIcon class="w-4 h-4" />
              {{ gallery.current_views || 0 }}
            </div>
            <div class="flex items-center gap-1">
              <component :is="layouts.find(l => l.value === gallery.layout)?.icon || Squares2X2Icon" class="w-4 h-4" />
              {{ layouts.find(l => l.value === gallery.layout)?.label || gallery.layout }}
            </div>
          </div>

          <!-- Actions -->
          <div class="flex items-center justify-between pt-3 border-t border-gray-200 dark:border-gray-700">
            <button @click="loadGalleryDetails(gallery.id)"
                    class="text-indigo-600 hover:text-indigo-700 text-sm font-medium">
              Öffnen
            </button>
            <div class="flex items-center gap-2">
              <button @click="copyPublicUrl(gallery)"
                      class="p-2 text-gray-400 hover:text-indigo-600"
                      title="Link kopieren">
                <LinkIcon class="w-4 h-4" />
              </button>
              <button @click="editGallery(gallery)"
                      class="p-2 text-gray-400 hover:text-indigo-600">
                <PencilIcon class="w-4 h-4" />
              </button>
              <button @click="deleteGallery(gallery.id)"
                      class="p-2 text-gray-400 hover:text-red-600">
                <TrashIcon class="w-4 h-4" />
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-if="galleries.length === 0" class="col-span-full">
        <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-lg shadow">
          <PhotoIcon class="w-12 h-12 mx-auto text-gray-400" />
          <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">Keine Galerien</h3>
          <p class="mt-2 text-gray-500">Erstelle deine erste öffentliche Galerie.</p>
          <button @click="showModal = true" class="mt-4 btn-primary">
            <PlusIcon class="w-5 h-5 mr-2" />
            Galerie erstellen
          </button>
        </div>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4">
          <div class="fixed inset-0 bg-black/50" @click="showModal = false"></div>
          <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto p-6">
            <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4">
              {{ editingGallery ? 'Galerie bearbeiten' : 'Neue Galerie' }}
            </h2>
            <form @submit.prevent="saveGallery" class="space-y-4">
              <div>
                <label class="label">Name</label>
                <input v-model="form.name" type="text" required class="input" />
              </div>
              <div>
                <label class="label">Beschreibung</label>
                <textarea v-model="form.description" rows="2" class="input"></textarea>
              </div>
              <div>
                <label class="label">URL-Slug</label>
                <div class="flex items-center">
                  <span class="text-gray-500 text-sm mr-2">/gallery/</span>
                  <input v-model="form.slug" type="text" placeholder="meine-galerie" class="input flex-1" />
                </div>
              </div>
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="label">Layout</label>
                  <select v-model="form.layout" class="input">
                    <option v-for="l in layouts" :key="l.value" :value="l.value">{{ l.label }}</option>
                  </select>
                </div>
                <div>
                  <label class="label">Theme</label>
                  <select v-model="form.theme" class="input">
                    <option v-for="t in themes" :key="t.value" :value="t.value">{{ t.label }}</option>
                  </select>
                </div>
              </div>
              <div>
                <label class="label">Passwort (optional)</label>
                <input v-model="form.password" type="password" placeholder="Leer lassen für keinen Schutz" class="input" />
              </div>
              <div class="flex flex-wrap items-center gap-4">
                <label class="flex items-center gap-2">
                  <input v-model="form.is_public" type="checkbox" class="rounded" />
                  <span class="text-sm text-gray-700 dark:text-gray-300">Öffentlich</span>
                </label>
                <label class="flex items-center gap-2">
                  <input v-model="form.show_header" type="checkbox" class="rounded" />
                  <span class="text-sm text-gray-700 dark:text-gray-300">Header</span>
                </label>
                <label class="flex items-center gap-2">
                  <input v-model="form.show_download_button" type="checkbox" class="rounded" />
                  <span class="text-sm text-gray-700 dark:text-gray-300">Downloads</span>
                </label>
              </div>
              <div class="flex justify-end gap-3 pt-4">
                <button type="button" @click="showModal = false; resetForm()" class="btn-secondary">Abbrechen</button>
                <button type="submit" class="btn-primary">Speichern</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Detail Modal -->
    <Teleport to="body">
      <div v-if="showDetailModal && selectedGallery" class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4">
          <div class="fixed inset-0 bg-black/50" @click="showDetailModal = false"></div>
          <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-4xl max-h-[90vh] overflow-y-auto p-6">
            <div class="flex justify-between items-start mb-6">
              <div>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">{{ selectedGallery.gallery.name }}</h2>
                <p class="text-gray-500">/gallery/{{ selectedGallery.gallery.slug }}</p>
              </div>
              <div class="flex items-center gap-2">
                <button @click="showItemModal = true" class="btn-primary">
                  <PlusIcon class="w-5 h-5 mr-2" />
                  Element
                </button>
                <button @click="showDetailModal = false" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
              </div>
            </div>

            <!-- Stats -->
            <div v-if="selectedGallery.stats" class="grid grid-cols-3 gap-4 mb-6">
              <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3 text-center">
                <div class="text-2xl font-bold">{{ selectedGallery.stats.total_views || 0 }}</div>
                <div class="text-sm text-gray-500">Aufrufe</div>
              </div>
              <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3 text-center">
                <div class="text-2xl font-bold">{{ selectedGallery.stats.unique_visitors || 0 }}</div>
                <div class="text-sm text-gray-500">Besucher</div>
              </div>
              <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3 text-center">
                <div class="text-2xl font-bold">{{ selectedGallery.stats.downloads || 0 }}</div>
                <div class="text-sm text-gray-500">Downloads</div>
              </div>
            </div>

            <!-- Items -->
            <div class="space-y-3">
              <h3 class="font-semibold text-gray-900 dark:text-white">Elemente ({{ selectedGallery.items?.length || 0 }})</h3>
              <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                <div v-for="item in selectedGallery.items" :key="item.id"
                     class="bg-gray-50 dark:bg-gray-700 rounded-lg overflow-hidden">
                  <div class="h-24 bg-gray-200 dark:bg-gray-600 flex items-center justify-center"
                       :style="item.thumbnail_url ? { backgroundImage: `url(${item.thumbnail_url})`, backgroundSize: 'cover' } : {}">
                    <PhotoIcon v-if="!item.thumbnail_url" class="w-8 h-8 text-gray-400" />
                  </div>
                  <div class="p-3">
                    <div class="font-medium text-sm text-gray-900 dark:text-white truncate">
                      {{ item.custom_title || item.title || 'Ohne Titel' }}
                    </div>
                    <div class="flex items-center justify-between mt-2">
                      <span class="text-xs text-gray-500">{{ item.item_type }}</span>
                      <button @click="removeItem(item.id)" class="text-red-500 hover:text-red-700">
                        <TrashIcon class="w-4 h-4" />
                      </button>
                    </div>
                  </div>
                </div>

                <!-- Add Item Card -->
                <button @click="showItemModal = true"
                        class="h-40 bg-gray-50 dark:bg-gray-700 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-600 flex flex-col items-center justify-center text-gray-400 hover:text-indigo-600 hover:border-indigo-600 transition-colors">
                  <PlusIcon class="w-8 h-8" />
                  <span class="text-sm mt-2">Element hinzufügen</span>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Add Item Modal -->
    <Teleport to="body">
      <div v-if="showItemModal" class="fixed inset-0 z-[60] overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4">
          <div class="fixed inset-0 bg-black/50" @click="showItemModal = false"></div>
          <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md p-6">
            <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4">Element hinzufügen</h2>
            <form @submit.prevent="addItem" class="space-y-4">
              <div>
                <label class="label">Typ</label>
                <select v-model="itemForm.item_type" class="input">
                  <option v-for="t in itemTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                </select>
              </div>
              <div>
                <label class="label">Titel</label>
                <input v-model="itemForm.title" type="text" class="input" />
              </div>
              <div>
                <label class="label">URL</label>
                <input v-model="itemForm.url" type="url" placeholder="https://..." class="input" />
              </div>
              <div>
                <label class="label">Thumbnail URL</label>
                <input v-model="itemForm.thumbnail_url" type="url" class="input" />
              </div>
              <div class="flex items-center gap-4">
                <label class="flex items-center gap-2">
                  <input v-model="itemForm.is_featured" type="checkbox" class="rounded" />
                  <span class="text-sm text-gray-700 dark:text-gray-300">Hervorgehoben</span>
                </label>
                <label class="flex items-center gap-2">
                  <input v-model="itemForm.allow_download" type="checkbox" class="rounded" />
                  <span class="text-sm text-gray-700 dark:text-gray-300">Download erlauben</span>
                </label>
              </div>
              <div class="flex justify-end gap-3 pt-4">
                <button type="button" @click="showItemModal = false; resetItemForm()" class="btn-secondary">Abbrechen</button>
                <button type="submit" class="btn-primary">Hinzufügen</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.btn-primary {
  @apply inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors;
}
.btn-secondary {
  @apply inline-flex items-center px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors;
}
</style>
