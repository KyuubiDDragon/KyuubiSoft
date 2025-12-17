<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { usePasswordsStore } from '@/stores/passwords'
import { useUiStore } from '@/stores/ui'
import { useToast } from '@/composables/useToast'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
import {
  MagnifyingGlassIcon,
  PlusIcon,
  FolderIcon,
  KeyIcon,
  EyeIcon,
  EyeSlashIcon,
  ClipboardIcon,
  PencilIcon,
  TrashIcon,
  StarIcon,
  XMarkIcon,
  ArrowPathIcon,
  GlobeAltIcon,
  UserIcon,
  LockClosedIcon,
  QrCodeIcon,
} from '@heroicons/vue/24/outline'
import { StarIcon as StarIconSolid } from '@heroicons/vue/24/solid'

const passwordsStore = usePasswordsStore()
const uiStore = useUiStore()
const toast = useToast()
const { confirm } = useConfirmDialog()

// Modal states
const showPasswordModal = ref(false)
const showCategoryModal = ref(false)
const showTOTPModal = ref(false)
const editingPassword = ref(null)
const editingCategory = ref(null)

// Form state
const passwordForm = ref({
  name: '',
  username: '',
  password: '',
  url: '',
  notes: '',
  category_id: null,
  totp_secret: '',
})

const categoryForm = ref({
  name: '',
  icon: 'folder',
  color: '#6366f1',
})

// Password visibility
const showPassword = ref(false)
const copiedField = ref(null)

// TOTP state
const totpData = ref(null)
const totpInterval = ref(null)

// Password generator options
const generatorOptions = ref({
  length: 16,
  uppercase: true,
  lowercase: true,
  numbers: true,
  symbols: true,
})

// Load data
onMounted(async () => {
  await Promise.all([
    passwordsStore.loadPasswords(),
    passwordsStore.loadCategories(),
  ])
})

// Functions
function openCreatePassword() {
  editingPassword.value = null
  passwordForm.value = {
    name: '',
    username: '',
    password: '',
    url: '',
    notes: '',
    category_id: passwordsStore.selectedCategory,
    totp_secret: '',
  }
  showPassword.value = false
  showPasswordModal.value = true
}

async function openEditPassword(pwd) {
  editingPassword.value = pwd
  try {
    const fullPassword = await passwordsStore.getPassword(pwd.id)
    passwordForm.value = {
      name: fullPassword.name,
      username: fullPassword.username || '',
      password: fullPassword.password || '',
      url: fullPassword.url || '',
      notes: fullPassword.notes || '',
      category_id: fullPassword.category_id,
      totp_secret: fullPassword.totp_secret || '',
    }
    showPassword.value = false
    showPasswordModal.value = true
  } catch (error) {
    uiStore.showError('Fehler beim Laden')
  }
}

async function savePassword() {
  if (!passwordForm.value.name || !passwordForm.value.password) {
    uiStore.showError('Name und Passwort sind erforderlich')
    return
  }

  try {
    if (editingPassword.value) {
      await passwordsStore.updatePassword(editingPassword.value.id, passwordForm.value)
      uiStore.showSuccess('Passwort aktualisiert')
    } else {
      await passwordsStore.createPassword(passwordForm.value)
      uiStore.showSuccess('Passwort erstellt')
    }
    showPasswordModal.value = false
  } catch (error) {
    uiStore.showError('Fehler beim Speichern')
  }
}

async function deletePassword(pwd) {
  if (!await confirm({ message: `"${pwd.name}" wirklich löschen?`, type: 'danger', confirmText: 'Löschen' })) return

  try {
    await passwordsStore.deletePassword(pwd.id)
    uiStore.showSuccess('Passwort gelöscht')
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
  }
}

async function toggleFavorite(pwd) {
  try {
    await passwordsStore.toggleFavorite(pwd.id)
  } catch (error) {
    uiStore.showError('Fehler')
  }
}

async function generatePassword() {
  try {
    const password = await passwordsStore.generatePassword(generatorOptions.value)
    passwordForm.value.password = password
    showPassword.value = true
  } catch (error) {
    uiStore.showError('Fehler beim Generieren')
  }
}

async function copyToClipboard(text, fieldName) {
  try {
    await navigator.clipboard.writeText(text)
    copiedField.value = fieldName
    setTimeout(() => copiedField.value = null, 2000)
  } catch (error) {
    uiStore.showError('Kopieren fehlgeschlagen')
  }
}

async function copyPassword(pwd) {
  try {
    const fullPassword = await passwordsStore.getPassword(pwd.id)
    await copyToClipboard(fullPassword.password, pwd.id)
    uiStore.showSuccess('Passwort kopiert')
  } catch (error) {
    uiStore.showError('Fehler beim Kopieren')
  }
}

async function showTOTP(pwd) {
  try {
    totpData.value = await passwordsStore.generateTOTP(pwd.id)
    showTOTPModal.value = true

    // Auto-refresh TOTP
    if (totpInterval.value) clearInterval(totpInterval.value)
    totpInterval.value = setInterval(async () => {
      if (totpData.value.seconds_remaining <= 1) {
        totpData.value = await passwordsStore.generateTOTP(pwd.id)
      } else {
        totpData.value.seconds_remaining--
      }
    }, 1000)
  } catch (error) {
    uiStore.showError('TOTP nicht verfügbar')
  }
}

function closeTOTPModal() {
  showTOTPModal.value = false
  if (totpInterval.value) {
    clearInterval(totpInterval.value)
    totpInterval.value = null
  }
}

// Category functions
function openCreateCategory() {
  editingCategory.value = null
  categoryForm.value = { name: '', icon: 'folder', color: '#6366f1' }
  showCategoryModal.value = true
}

function openEditCategory(cat) {
  editingCategory.value = cat
  categoryForm.value = { name: cat.name, icon: cat.icon, color: cat.color }
  showCategoryModal.value = true
}

async function saveCategory() {
  if (!categoryForm.value.name) {
    uiStore.showError('Name ist erforderlich')
    return
  }

  try {
    if (editingCategory.value) {
      await passwordsStore.updateCategory(editingCategory.value.id, categoryForm.value)
    } else {
      await passwordsStore.createCategory(categoryForm.value)
    }
    showCategoryModal.value = false
  } catch (error) {
    uiStore.showError('Fehler beim Speichern')
  }
}

async function deleteCategory(cat) {
  if (!await confirm({ message: `Kategorie "${cat.name}" löschen? Passwörter bleiben erhalten.`, type: 'danger', confirmText: 'Löschen' })) return

  try {
    await passwordsStore.deleteCategory(cat.id)
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
  }
}

function getFaviconUrl(url) {
  if (!url) return null
  try {
    const domain = new URL(url).hostname
    return `https://www.google.com/s2/favicons?domain=${domain}&sz=32`
  } catch {
    return null
  }
}

const categoryColors = ['#6366f1', '#ec4899', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#84cc16']
</script>

<template>
  <div class="flex h-[calc(100vh-8rem)]">
    <!-- Sidebar -->
    <div class="w-64 bg-dark-800 border-r border-dark-700 flex flex-col shrink-0">
      <!-- Search -->
      <div class="p-4 border-b border-dark-700">
        <div class="relative">
          <MagnifyingGlassIcon class="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
          <input
            v-model="passwordsStore.searchQuery"
            type="text"
            placeholder="Suchen..."
            class="input pl-10 w-full"
          />
        </div>
      </div>

      <!-- Categories -->
      <div class="flex-1 overflow-y-auto p-2">
        <button
          @click="passwordsStore.selectCategory(null)"
          class="w-full flex items-center gap-3 px-3 py-2 rounded-lg transition-colors"
          :class="!passwordsStore.selectedCategory ? 'bg-primary-600 text-white' : 'text-gray-300 hover:bg-dark-700'"
        >
          <KeyIcon class="w-5 h-5" />
          <span>Alle Passwörter</span>
          <span class="ml-auto text-xs opacity-70">{{ passwordsStore.passwords.length }}</span>
        </button>

        <button
          @click="passwordsStore.selectCategory('favorites')"
          class="w-full flex items-center gap-3 px-3 py-2 rounded-lg transition-colors mt-1"
          :class="passwordsStore.selectedCategory === 'favorites' ? 'bg-primary-600 text-white' : 'text-gray-300 hover:bg-dark-700'"
        >
          <StarIcon class="w-5 h-5 text-yellow-500" />
          <span>Favoriten</span>
          <span class="ml-auto text-xs opacity-70">{{ passwordsStore.favorites.length }}</span>
        </button>

        <div class="mt-4 mb-2 px-3 flex items-center justify-between">
          <span class="text-xs text-gray-500 uppercase font-semibold">Kategorien</span>
          <button @click="openCreateCategory" class="text-gray-400 hover:text-white">
            <PlusIcon class="w-4 h-4" />
          </button>
        </div>

        <div
          v-for="cat in passwordsStore.categories"
          :key="cat.id"
          class="group flex items-center gap-2 px-3 py-2 rounded-lg transition-colors"
          :class="passwordsStore.selectedCategory === cat.id ? 'bg-primary-600 text-white' : 'text-gray-300 hover:bg-dark-700'"
        >
          <button
            @click="passwordsStore.selectCategory(cat.id)"
            class="flex-1 flex items-center gap-3 text-left"
          >
            <span class="w-2 h-2 rounded-full" :style="{ backgroundColor: cat.color }"></span>
            <span class="truncate">{{ cat.name }}</span>
            <span class="ml-auto text-xs opacity-70">{{ cat.password_count || 0 }}</span>
          </button>
          <button
            @click.stop="openEditCategory(cat)"
            class="opacity-0 group-hover:opacity-100 text-gray-400 hover:text-white"
          >
            <PencilIcon class="w-4 h-4" />
          </button>
        </div>
      </div>
    </div>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col overflow-hidden">
      <!-- Header -->
      <div class="p-4 border-b border-dark-700 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-white">Passwort-Manager</h1>
        <button @click="openCreatePassword" class="btn-primary flex items-center gap-2">
          <PlusIcon class="w-5 h-5" />
          Neues Passwort
        </button>
      </div>

      <!-- Password List -->
      <div class="flex-1 overflow-y-auto p-4">
        <div v-if="passwordsStore.isLoading" class="flex items-center justify-center h-full">
          <div class="animate-spin w-8 h-8 border-4 border-primary-500 border-t-transparent rounded-full"></div>
        </div>

        <div v-else-if="passwordsStore.filteredPasswords.length === 0" class="flex flex-col items-center justify-center h-full text-gray-400">
          <KeyIcon class="w-16 h-16 mb-4 opacity-50" />
          <p>Keine Passwörter gefunden</p>
        </div>

        <div v-else class="space-y-2">
          <div
            v-for="pwd in passwordsStore.filteredPasswords"
            :key="pwd.id"
            class="bg-dark-800 border border-dark-700 rounded-lg p-4 hover:border-dark-600 transition-colors group"
          >
            <div class="flex items-center gap-4">
              <!-- Favicon -->
              <div class="w-10 h-10 bg-dark-700 rounded-lg flex items-center justify-center shrink-0">
                <img
                  v-if="getFaviconUrl(pwd.url)"
                  :src="getFaviconUrl(pwd.url)"
                  class="w-6 h-6"
                  @error="$event.target.style.display='none'"
                />
                <KeyIcon v-else class="w-5 h-5 text-gray-400" />
              </div>

              <!-- Info -->
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                  <h3 class="font-medium text-white truncate">{{ pwd.name }}</h3>
                  <button @click="toggleFavorite(pwd)" class="text-gray-400 hover:text-yellow-500">
                    <StarIconSolid v-if="pwd.is_favorite" class="w-4 h-4 text-yellow-500" />
                    <StarIcon v-else class="w-4 h-4" />
                  </button>
                </div>
                <p v-if="pwd.username" class="text-sm text-gray-400 truncate">{{ pwd.username }}</p>
                <p v-if="pwd.url" class="text-xs text-gray-500 truncate">{{ pwd.url }}</p>
              </div>

              <!-- Actions -->
              <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                <button
                  v-if="pwd.totp_secret_encrypted"
                  @click="showTOTP(pwd)"
                  class="p-2 text-gray-400 hover:text-primary-400 hover:bg-dark-700 rounded-lg"
                  title="TOTP Code"
                >
                  <QrCodeIcon class="w-5 h-5" />
                </button>
                <button
                  @click="copyPassword(pwd)"
                  class="p-2 text-gray-400 hover:text-primary-400 hover:bg-dark-700 rounded-lg"
                  :class="{ 'text-green-400': copiedField === pwd.id }"
                  title="Passwort kopieren"
                >
                  <ClipboardIcon class="w-5 h-5" />
                </button>
                <button
                  @click="openEditPassword(pwd)"
                  class="p-2 text-gray-400 hover:text-primary-400 hover:bg-dark-700 rounded-lg"
                  title="Bearbeiten"
                >
                  <PencilIcon class="w-5 h-5" />
                </button>
                <button
                  @click="deletePassword(pwd)"
                  class="p-2 text-gray-400 hover:text-red-400 hover:bg-dark-700 rounded-lg"
                  title="Löschen"
                >
                  <TrashIcon class="w-5 h-5" />
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Password Modal -->
    <Teleport to="body">
      <div v-if="showPasswordModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60" @click="showPasswordModal = false"></div>
        <div class="relative bg-dark-800 border border-dark-700 rounded-xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
          <div class="p-6">
            <div class="flex items-center justify-between mb-6">
              <h2 class="text-lg font-semibold text-white">
                {{ editingPassword ? 'Passwort bearbeiten' : 'Neues Passwort' }}
              </h2>
              <button @click="showPasswordModal = false" class="text-gray-400 hover:text-white">
                <XMarkIcon class="w-6 h-6" />
              </button>
            </div>

            <div class="space-y-4">
              <div>
                <label class="label">Name *</label>
                <input v-model="passwordForm.name" type="text" class="input" placeholder="z.B. Google Account" />
              </div>

              <div>
                <label class="label">Benutzername</label>
                <div class="relative">
                  <UserIcon class="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                  <input v-model="passwordForm.username" type="text" class="input pl-10" placeholder="E-Mail oder Benutzername" />
                </div>
              </div>

              <div>
                <label class="label">Passwort *</label>
                <div class="flex gap-2">
                  <div class="relative flex-1">
                    <LockClosedIcon class="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                    <input
                      v-model="passwordForm.password"
                      :type="showPassword ? 'text' : 'password'"
                      class="input pl-10 pr-10 font-mono"
                    />
                    <button
                      type="button"
                      @click="showPassword = !showPassword"
                      class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white"
                    >
                      <EyeIcon v-if="!showPassword" class="w-5 h-5" />
                      <EyeSlashIcon v-else class="w-5 h-5" />
                    </button>
                  </div>
                  <button @click="generatePassword" class="btn-secondary" title="Generieren">
                    <ArrowPathIcon class="w-5 h-5" />
                  </button>
                </div>

                <!-- Generator Options -->
                <div class="mt-2 p-3 bg-dark-700/50 rounded-lg">
                  <div class="flex items-center gap-4 text-sm">
                    <label class="flex items-center gap-2">
                      <input v-model="generatorOptions.length" type="number" min="8" max="128" class="w-16 input text-sm py-1" />
                      Zeichen
                    </label>
                  </div>
                  <div class="flex flex-wrap gap-3 mt-2">
                    <label class="flex items-center gap-1 text-sm text-gray-400">
                      <input v-model="generatorOptions.uppercase" type="checkbox" class="rounded" /> ABC
                    </label>
                    <label class="flex items-center gap-1 text-sm text-gray-400">
                      <input v-model="generatorOptions.lowercase" type="checkbox" class="rounded" /> abc
                    </label>
                    <label class="flex items-center gap-1 text-sm text-gray-400">
                      <input v-model="generatorOptions.numbers" type="checkbox" class="rounded" /> 123
                    </label>
                    <label class="flex items-center gap-1 text-sm text-gray-400">
                      <input v-model="generatorOptions.symbols" type="checkbox" class="rounded" /> !@#
                    </label>
                  </div>
                </div>
              </div>

              <div>
                <label class="label">URL</label>
                <div class="relative">
                  <GlobeAltIcon class="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                  <input v-model="passwordForm.url" type="url" class="input pl-10" placeholder="https://..." />
                </div>
              </div>

              <div>
                <label class="label">Kategorie</label>
                <select v-model="passwordForm.category_id" class="input">
                  <option :value="null">Keine Kategorie</option>
                  <option v-for="cat in passwordsStore.categories" :key="cat.id" :value="cat.id">
                    {{ cat.name }}
                  </option>
                </select>
              </div>

              <div>
                <label class="label">TOTP Secret (optional)</label>
                <input v-model="passwordForm.totp_secret" type="text" class="input font-mono" placeholder="BASE32 Secret für 2FA" />
              </div>

              <div>
                <label class="label">Notizen</label>
                <textarea v-model="passwordForm.notes" class="input" rows="3" placeholder="Zusätzliche Informationen..."></textarea>
              </div>
            </div>

            <div class="flex justify-end gap-3 mt-6">
              <button @click="showPasswordModal = false" class="btn-secondary">Abbrechen</button>
              <button @click="savePassword" class="btn-primary">Speichern</button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Category Modal -->
    <Teleport to="body">
      <div v-if="showCategoryModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60" @click="showCategoryModal = false"></div>
        <div class="relative bg-dark-800 border border-dark-700 rounded-xl shadow-2xl w-full max-w-sm">
          <div class="p-6">
            <h2 class="text-lg font-semibold text-white mb-4">
              {{ editingCategory ? 'Kategorie bearbeiten' : 'Neue Kategorie' }}
            </h2>

            <div class="space-y-4">
              <div>
                <label class="label">Name</label>
                <input v-model="categoryForm.name" type="text" class="input" placeholder="Kategoriename" />
              </div>

              <div>
                <label class="label">Farbe</label>
                <div class="flex gap-2 flex-wrap">
                  <button
                    v-for="color in categoryColors"
                    :key="color"
                    @click="categoryForm.color = color"
                    class="w-8 h-8 rounded-full transition-transform"
                    :class="{ 'ring-2 ring-white scale-110': categoryForm.color === color }"
                    :style="{ backgroundColor: color }"
                  ></button>
                </div>
              </div>
            </div>

            <div class="flex justify-between gap-3 mt-6">
              <button
                v-if="editingCategory"
                @click="deleteCategory(editingCategory)"
                class="btn-secondary text-red-400"
              >
                Löschen
              </button>
              <div class="flex gap-3 ml-auto">
                <button @click="showCategoryModal = false" class="btn-secondary">Abbrechen</button>
                <button @click="saveCategory" class="btn-primary">Speichern</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- TOTP Modal -->
    <Teleport to="body">
      <div v-if="showTOTPModal && totpData" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60" @click="closeTOTPModal"></div>
        <div class="relative bg-dark-800 border border-dark-700 rounded-xl shadow-2xl w-full max-w-xs text-center p-6">
          <h2 class="text-lg font-semibold text-white mb-4">2FA Code</h2>

          <div class="text-4xl font-mono font-bold text-primary-400 mb-4 tracking-widest">
            {{ totpData.code }}
          </div>

          <div class="w-full bg-dark-700 h-2 rounded-full overflow-hidden mb-4">
            <div
              class="h-full bg-primary-600 transition-all duration-1000"
              :style="{ width: `${(totpData.seconds_remaining / 30) * 100}%` }"
            ></div>
          </div>

          <p class="text-sm text-gray-400">Gültig noch {{ totpData.seconds_remaining }}s</p>

          <div class="flex gap-3 mt-6">
            <button @click="copyToClipboard(totpData.code, 'totp')" class="btn-secondary flex-1">
              <ClipboardIcon class="w-5 h-5 mx-auto" />
            </button>
            <button @click="closeTOTPModal" class="btn-primary flex-1">Schließen</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
