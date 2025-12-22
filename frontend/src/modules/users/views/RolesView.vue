<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/core/api/axios'
import { useUiStore } from '@/stores/ui'
import { useAuthStore } from '@/stores/auth'
import {
  XMarkIcon,
  PlusIcon,
  TrashIcon,
  PencilIcon,
  ShieldCheckIcon,
  UsersIcon,
  ChevronDownIcon,
  ChevronRightIcon,
} from '@heroicons/vue/24/outline'

const uiStore = useUiStore()
const authStore = useAuthStore()

const roles = ref([])
const allPermissions = ref([])
const groupedPermissions = ref({})
const isLoading = ref(true)
const error = ref(null)

// Modal states
const showRoleModal = ref(false)
const showDeleteModal = ref(false)
const showPermissionsModal = ref(false)
const isEditing = ref(false)
const isSaving = ref(false)
const selectedRole = ref(null)

// Permission modal state
const permissionSearch = ref('')
const expandedModules = ref([])

// Form data
const formData = ref({
  name: '',
  description: '',
  hierarchy_level: 50,
})
const formErrors = ref({})

onMounted(async () => {
  await Promise.all([loadRoles(), loadAllPermissions()])
})

async function loadRoles() {
  isLoading.value = true
  error.value = null

  try {
    const response = await api.get('/api/v1/roles')
    roles.value = response.data.data || []
  } catch (err) {
    error.value = 'Fehler beim Laden der Rollen'
    console.error(err)
  } finally {
    isLoading.value = false
  }
}

async function loadAllPermissions() {
  try {
    const response = await api.get('/api/v1/admin/permissions')
    const data = response.data.data || {}
    allPermissions.value = data.permissions || []
    groupedPermissions.value = data.grouped || {}
  } catch (err) {
    console.error('Failed to load permissions:', err)
  }
}

function openCreateModal() {
  isEditing.value = false
  selectedRole.value = null
  formData.value = {
    name: '',
    description: '',
    hierarchy_level: 50,
  }
  formErrors.value = {}
  showRoleModal.value = true
}

function openEditModal(role) {
  isEditing.value = true
  selectedRole.value = role
  formData.value = {
    name: role.name,
    description: role.description || '',
    hierarchy_level: role.hierarchy_level,
  }
  formErrors.value = {}
  showRoleModal.value = true
}

function openDeleteModal(role) {
  selectedRole.value = role
  formErrors.value = {}
  showDeleteModal.value = true
}

function openPermissionsModal(role) {
  selectedRole.value = role
  permissionSearch.value = ''
  expandedModules.value = []
  showPermissionsModal.value = true
}

function closeModals() {
  showRoleModal.value = false
  showDeleteModal.value = false
  showPermissionsModal.value = false
  selectedRole.value = null
}

async function saveRole() {
  formErrors.value = {}
  isSaving.value = true

  try {
    if (isEditing.value) {
      await api.put(`/api/v1/roles/${selectedRole.value.id}`, {
        name: formData.value.name,
        description: formData.value.description,
        hierarchy_level: formData.value.hierarchy_level,
      })
      uiStore.showSuccess('Rolle erfolgreich aktualisiert')
    } else {
      await api.post('/api/v1/roles', {
        name: formData.value.name,
        description: formData.value.description,
        hierarchy_level: formData.value.hierarchy_level,
      })
      uiStore.showSuccess('Rolle erfolgreich erstellt')
    }

    closeModals()
    await loadRoles()
  } catch (err) {
    console.error('Save error:', err)
    formErrors.value.general = err.response?.data?.error || err.response?.data?.message || 'Fehler beim Speichern'
  } finally {
    isSaving.value = false
  }
}

async function deleteRole() {
  if (!selectedRole.value) return

  isSaving.value = true
  try {
    await api.delete(`/api/v1/roles/${selectedRole.value.id}`)
    uiStore.showSuccess('Rolle erfolgreich gelöscht')
    closeModals()
    await loadRoles()
  } catch (err) {
    console.error('Delete error:', err)
    formErrors.value.general = err.response?.data?.error || err.response?.data?.message || 'Fehler beim Löschen'
  } finally {
    isSaving.value = false
  }
}

async function togglePermission(permissionName) {
  if (!selectedRole.value) return

  const hasPermission = selectedRole.value.permissions?.includes(permissionName)

  try {
    if (hasPermission) {
      await api.delete(`/api/v1/roles/${selectedRole.value.id}/permissions/${encodeURIComponent(permissionName)}`)
    } else {
      await api.post(`/api/v1/roles/${selectedRole.value.id}/permissions`, {
        permission: permissionName,
      })
    }
    // Reload role to get updated permissions
    const response = await api.get(`/api/v1/roles/${selectedRole.value.id}`)
    selectedRole.value = response.data.data
    // Also update in the roles list
    const index = roles.value.findIndex(r => r.id === selectedRole.value.id)
    if (index !== -1) {
      roles.value[index] = selectedRole.value
    }
  } catch (err) {
    console.error('Toggle permission error:', err)
    uiStore.showError(err.response?.data?.error || 'Fehler beim Ändern der Berechtigung')
  }
}

function toggleModule(module) {
  const index = expandedModules.value.indexOf(module)
  if (index > -1) {
    expandedModules.value.splice(index, 1)
  } else {
    expandedModules.value.push(module)
  }
}

function isModuleExpanded(module) {
  return expandedModules.value.includes(module)
}

function hasPermission(permissionName) {
  return selectedRole.value?.permissions?.includes(permissionName)
}

const filteredPermissions = computed(() => {
  if (!permissionSearch.value.trim()) {
    return groupedPermissions.value
  }
  const search = permissionSearch.value.toLowerCase()
  const filtered = {}
  for (const [module, permissions] of Object.entries(groupedPermissions.value)) {
    const matchingPerms = permissions.filter(p =>
      p.name.toLowerCase().includes(search) ||
      p.description?.toLowerCase().includes(search) ||
      module.toLowerCase().includes(search)
    )
    if (matchingPerms.length > 0) {
      filtered[module] = matchingPerms
    }
  }
  return filtered
})

function getModuleLabel(module) {
  const labels = {
    users: 'Benutzer',
    docker: 'Docker',
    tickets: 'Tickets',
    backups: 'Backups',
    kanban: 'Kanban',
    storage: 'Speicher',
    passwords: 'Passwörter',
    wiki: 'Wiki',
    notes: 'Notizen',
    calendar: 'Kalender',
    logs: 'Logs',
    mails: 'Mails',
    projects: 'Projekte',
    settings: 'Einstellungen',
    features: 'Features',
    general: 'Allgemein',
  }
  return labels[module] || module
}

function getModulePermissionCount(module) {
  const perms = groupedPermissions.value[module] || []
  const assigned = perms.filter(p => hasPermission(p.name)).length
  return `${assigned}/${perms.length}`
}

function getRoleBadgeClass(role) {
  const classes = {
    owner: 'bg-purple-600 text-white',
    admin: 'bg-red-600 text-white',
    editor: 'bg-blue-600 text-white',
    user: 'bg-green-600 text-white',
    viewer: 'bg-gray-600 text-white',
    pending: 'bg-yellow-600 text-white',
  }
  return classes[role.name] || 'bg-primary-600 text-white'
}

function canEditRole(role) {
  return !role.is_system && authStore.hasPermission('users.write')
}

function canDeleteRole(role) {
  return !role.is_system && role.user_count === 0 && authStore.hasPermission('users.write')
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-white">Rollen & Gruppen</h1>
        <p class="text-gray-400 mt-1">Verwalte Rollen und deren Berechtigungen</p>
      </div>
      <button
        @click="openCreateModal"
        class="flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors"
      >
        <PlusIcon class="w-5 h-5" />
        Neue Rolle
      </button>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="flex justify-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-500"></div>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="bg-red-900/50 border border-red-700 rounded-lg p-4">
      <p class="text-red-400">{{ error }}</p>
    </div>

    <!-- Roles Grid -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div
        v-for="role in roles"
        :key="role.id"
        class="bg-dark-800 rounded-lg border border-dark-700 p-4 hover:border-dark-600 transition-colors"
      >
        <div class="flex items-start justify-between mb-3">
          <div class="flex items-center gap-3">
            <div
              class="w-10 h-10 rounded-lg flex items-center justify-center"
              :class="getRoleBadgeClass(role)"
            >
              <ShieldCheckIcon class="w-5 h-5" />
            </div>
            <div>
              <h3 class="font-semibold text-white">{{ role.name }}</h3>
              <p v-if="role.description" class="text-sm text-gray-400">{{ role.description }}</p>
            </div>
          </div>
          <span
            v-if="role.is_system"
            class="px-2 py-0.5 text-xs bg-gray-700 text-gray-300 rounded"
          >
            System
          </span>
        </div>

        <div class="flex items-center gap-4 text-sm text-gray-400 mb-4">
          <div class="flex items-center gap-1">
            <UsersIcon class="w-4 h-4" />
            <span>{{ role.user_count || 0 }} Benutzer</span>
          </div>
          <div class="flex items-center gap-1">
            <ShieldCheckIcon class="w-4 h-4" />
            <span>{{ role.permissions?.length || 0 }} Berechtigungen</span>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <button
            @click="openPermissionsModal(role)"
            class="flex-1 px-3 py-2 bg-dark-700 text-white text-sm rounded-lg hover:bg-dark-600 transition-colors"
          >
            Berechtigungen
          </button>
          <button
            v-if="canEditRole(role)"
            @click="openEditModal(role)"
            class="p-2 text-primary-400 hover:text-primary-300 hover:bg-dark-700 rounded-lg transition-colors"
            title="Bearbeiten"
          >
            <PencilIcon class="w-5 h-5" />
          </button>
          <button
            v-if="canDeleteRole(role)"
            @click="openDeleteModal(role)"
            class="p-2 text-red-400 hover:text-red-300 hover:bg-dark-700 rounded-lg transition-colors"
            title="Löschen"
          >
            <TrashIcon class="w-5 h-5" />
          </button>
        </div>
      </div>

      <div v-if="roles.length === 0" class="col-span-full text-center py-12 text-gray-400">
        Keine Rollen gefunden
      </div>
    </div>

    <!-- Create/Edit Role Modal -->
    <Teleport to="body">
      <div
        v-if="showRoleModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/70"
      >
        <div class="bg-dark-800 rounded-lg border border-dark-700 w-full max-w-md mx-4">
          <!-- Modal Header -->
          <div class="flex items-center justify-between p-4 border-b border-dark-700">
            <h3 class="text-lg font-semibold text-white">
              {{ isEditing ? 'Rolle bearbeiten' : 'Neue Rolle erstellen' }}
            </h3>
            <button @click="closeModals" class="text-gray-400 hover:text-white">
              <XMarkIcon class="w-6 h-6" />
            </button>
          </div>

          <!-- Modal Body -->
          <div class="p-4 space-y-4">
            <!-- Error Message -->
            <div v-if="formErrors.general" class="bg-red-900/50 border border-red-700 rounded-lg p-3">
              <p class="text-red-400 text-sm">{{ formErrors.general }}</p>
            </div>

            <!-- Name -->
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Name</label>
              <input
                v-model="formData.name"
                type="text"
                :disabled="isEditing && selectedRole?.is_system"
                class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:border-primary-500 disabled:opacity-50"
                placeholder="z.B. moderator"
              />
              <p class="mt-1 text-xs text-gray-500">Nur Kleinbuchstaben, Zahlen, Unterstriche und Bindestriche</p>
            </div>

            <!-- Description -->
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Beschreibung</label>
              <textarea
                v-model="formData.description"
                rows="2"
                class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:border-primary-500"
                placeholder="Beschreibung der Rolle..."
              ></textarea>
            </div>

            <!-- Hierarchy Level -->
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">
                Hierarchie-Ebene: {{ formData.hierarchy_level }}
              </label>
              <input
                v-model.number="formData.hierarchy_level"
                type="range"
                min="1"
                max="89"
                class="w-full"
              />
              <div class="flex justify-between text-xs text-gray-500">
                <span>Niedrig (1)</span>
                <span>Hoch (89)</span>
              </div>
              <p class="mt-1 text-xs text-gray-500">Höhere Ebene = mehr Autorität. Owner (100) und Admin (90) sind reserviert.</p>
            </div>
          </div>

          <!-- Modal Footer -->
          <div class="flex justify-end gap-3 p-4 border-t border-dark-700">
            <button
              @click="closeModals"
              class="px-4 py-2 text-gray-400 hover:text-white transition-colors"
            >
              Abbrechen
            </button>
            <button
              @click="saveRole"
              :disabled="isSaving"
              class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors disabled:opacity-50"
            >
              {{ isSaving ? 'Speichern...' : (isEditing ? 'Speichern' : 'Erstellen') }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Delete Confirmation Modal -->
    <Teleport to="body">
      <div
        v-if="showDeleteModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/70"
      >
        <div class="bg-dark-800 rounded-lg border border-dark-700 w-full max-w-md mx-4">
          <div class="p-6">
            <h3 class="text-lg font-semibold text-white mb-2">Rolle löschen</h3>
            <p class="text-gray-400">
              Bist du sicher, dass du die Rolle
              <span class="text-white font-medium">{{ selectedRole?.name }}</span>
              löschen möchtest?
            </p>

            <div v-if="formErrors.general" class="mt-4 bg-red-900/50 border border-red-700 rounded-lg p-3">
              <p class="text-red-400 text-sm">{{ formErrors.general }}</p>
            </div>
          </div>
          <div class="flex justify-end gap-3 p-4 border-t border-dark-700">
            <button
              @click="closeModals"
              class="px-4 py-2 text-gray-400 hover:text-white transition-colors"
            >
              Abbrechen
            </button>
            <button
              @click="deleteRole"
              :disabled="isSaving"
              class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors disabled:opacity-50"
            >
              {{ isSaving ? 'Löschen...' : 'Löschen' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Permissions Modal -->
    <Teleport to="body">
      <div
        v-if="showPermissionsModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/70"
      >
        <div class="bg-dark-800 rounded-lg border border-dark-700 w-full max-w-3xl mx-4 max-h-[90vh] flex flex-col">
          <!-- Modal Header -->
          <div class="flex items-center justify-between p-4 border-b border-dark-700 flex-shrink-0">
            <div>
              <h3 class="text-lg font-semibold text-white">
                Berechtigungen für {{ selectedRole?.name }}
              </h3>
              <p v-if="selectedRole?.description" class="text-sm text-gray-400">
                {{ selectedRole?.description }}
              </p>
            </div>
            <button @click="closeModals" class="text-gray-400 hover:text-white">
              <XMarkIcon class="w-6 h-6" />
            </button>
          </div>

          <!-- Modal Body -->
          <div class="p-4 flex-1 overflow-hidden flex flex-col">
            <!-- System role warning -->
            <div v-if="selectedRole?.is_system" class="mb-4 p-3 bg-yellow-900/30 border border-yellow-700/50 rounded-lg">
              <p class="text-sm text-yellow-400">
                Dies ist eine Systemrolle. Berechtigungen können nicht geändert werden.
              </p>
            </div>

            <!-- Permission count -->
            <div class="mb-4 p-3 bg-dark-700 rounded-lg">
              <div class="flex items-center gap-2 text-sm">
                <ShieldCheckIcon class="w-5 h-5 text-primary-400" />
                <span class="text-gray-300">
                  {{ selectedRole?.permissions?.length || 0 }} von {{ allPermissions.length }} Berechtigungen zugewiesen
                </span>
              </div>
            </div>

            <!-- Search -->
            <div class="mb-4 flex-shrink-0">
              <input
                v-model="permissionSearch"
                type="text"
                placeholder="Berechtigungen suchen..."
                class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:border-primary-500"
              />
            </div>

            <!-- Permission Groups -->
            <div class="flex-1 overflow-y-auto space-y-2">
              <div
                v-for="(permissions, module) in filteredPermissions"
                :key="module"
                class="bg-dark-700 rounded-lg overflow-hidden"
              >
                <!-- Module Header -->
                <button
                  @click="toggleModule(module)"
                  class="w-full px-4 py-3 flex items-center justify-between hover:bg-dark-600 transition-colors"
                >
                  <div class="flex items-center gap-2">
                    <component
                      :is="isModuleExpanded(module) ? ChevronDownIcon : ChevronRightIcon"
                      class="w-4 h-4 text-gray-400"
                    />
                    <span class="font-medium text-white">{{ getModuleLabel(module) }}</span>
                  </div>
                  <span class="text-sm text-gray-400">{{ getModulePermissionCount(module) }}</span>
                </button>

                <!-- Module Permissions -->
                <div v-if="isModuleExpanded(module)" class="border-t border-dark-600">
                  <div
                    v-for="permission in permissions"
                    :key="permission.name"
                    class="px-4 py-2 flex items-center justify-between hover:bg-dark-600"
                  >
                    <div class="flex items-center gap-3">
                      <input
                        type="checkbox"
                        :checked="hasPermission(permission.name)"
                        :disabled="selectedRole?.is_system"
                        @change="togglePermission(permission.name)"
                        class="w-4 h-4 rounded border-dark-500 bg-dark-600 text-primary-600 focus:ring-primary-500 disabled:opacity-50"
                      />
                      <div>
                        <div class="text-sm text-white font-mono">{{ permission.name }}</div>
                        <div v-if="permission.description" class="text-xs text-gray-500">
                          {{ permission.description }}
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div
                v-if="Object.keys(filteredPermissions).length === 0"
                class="text-center py-8 text-gray-500"
              >
                Keine Berechtigungen gefunden
              </div>
            </div>
          </div>

          <!-- Modal Footer -->
          <div class="flex justify-end p-4 border-t border-dark-700 flex-shrink-0">
            <button
              @click="closeModals"
              class="px-4 py-2 bg-dark-600 text-white rounded-lg hover:bg-dark-500 transition-colors"
            >
              Schließen
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
