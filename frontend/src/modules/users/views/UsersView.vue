<script setup>
import { ref, onMounted, watch } from 'vue'
import api from '@/core/api/axios'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { XMarkIcon, PlusIcon, TrashIcon, PencilIcon, ShieldCheckIcon, ClockIcon, CheckIcon, KeyIcon } from '@heroicons/vue/24/outline'

const authStore = useAuthStore()
const uiStore = useUiStore()
const users = ref([])
const pendingUsers = ref([])
const roles = ref([])
const projects = ref([])
const allPermissions = ref([])
const groupedPermissions = ref({})
const isLoading = ref(true)
const error = ref(null)

// Modal states
const showUserModal = ref(false)
const showDeleteModal = ref(false)
const showPermissionsModal = ref(false)
const isEditing = ref(false)
const isSaving = ref(false)
const selectedUser = ref(null)

// Permissions modal state
const userDirectPermissions = ref([])
const userRolePermissions = ref([])
const permissionSearch = ref('')
const isLoadingPermissions = ref(false)

// Form data
const formData = ref({
  email: '',
  username: '',
  password: '',
  is_active: true,
  require_2fa: false,
  restricted_to_projects: false,
  allowed_project_ids: [],
  selectedRoles: []
})
const formErrors = ref({})

// Helper to convert DB value to boolean
function toBool(value) {
  if (typeof value === 'boolean') return value
  if (typeof value === 'number') return value === 1
  if (typeof value === 'string') return value === '1' || value === 'true'
  return false
}

onMounted(async () => {
  await Promise.all([loadUsers(), loadPendingUsers(), loadRoles(), loadProjects(), loadAllPermissions()])
})

async function loadUsers() {
  isLoading.value = true
  error.value = null

  try {
    const response = await api.get('/api/v1/users')
    users.value = response.data.data?.items || response.data.data || []
  } catch (err) {
    error.value = 'Fehler beim Laden der Benutzer'
    console.error(err)
  } finally {
    isLoading.value = false
  }
}

async function loadPendingUsers() {
  try {
    const response = await api.get('/api/v1/admin/users/pending')
    pendingUsers.value = response.data.data?.users || []
  } catch (err) {
    console.error('Failed to load pending users:', err)
  }
}

async function approveUser(user) {
  try {
    await api.post(`/api/v1/users/${user.id}/approve`)
    uiStore.showSuccess(`${user.username || user.email} wurde freigeschaltet`)
    await Promise.all([loadUsers(), loadPendingUsers()])
  } catch (err) {
    console.error('Approve error:', err)
    uiStore.showError(err.response?.data?.error || 'Fehler beim Freischalten')
  }
}

async function rejectUser(user) {
  if (!confirm(`Möchtest du die Registrierung von "${user.username || user.email}" wirklich ablehnen? Der Benutzer wird gelöscht.`)) {
    return
  }
  try {
    await api.post(`/api/v1/users/${user.id}/reject`)
    uiStore.showSuccess('Registrierung abgelehnt')
    await loadPendingUsers()
  } catch (err) {
    console.error('Reject error:', err)
    uiStore.showError(err.response?.data?.error || 'Fehler beim Ablehnen')
  }
}

async function loadRoles() {
  try {
    const response = await api.get('/api/v1/admin/roles')
    roles.value = response.data.data || []
  } catch (err) {
    console.error('Failed to load roles:', err)
  }
}

async function loadProjects() {
  try {
    const response = await api.get('/api/v1/projects')
    projects.value = response.data.data?.items || response.data.data || []
  } catch (err) {
    console.error('Failed to load projects:', err)
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
  selectedUser.value = null
  formData.value = {
    email: '',
    username: '',
    password: '',
    is_active: true,
    require_2fa: false,
    restricted_to_projects: false,
    allowed_project_ids: [],
    selectedRoles: ['user']
  }
  formErrors.value = {}
  showUserModal.value = true
}

function openEditModal(user) {
  isEditing.value = true
  selectedUser.value = user

  // Parse allowed_project_ids from JSON if needed
  let allowedProjects = []
  if (user.allowed_project_ids) {
    try {
      allowedProjects = typeof user.allowed_project_ids === 'string'
        ? JSON.parse(user.allowed_project_ids)
        : user.allowed_project_ids
    } catch (e) {
      allowedProjects = []
    }
  }

  formData.value = {
    email: user.email,
    username: user.username,
    password: '',
    is_active: toBool(user.is_active),
    require_2fa: toBool(user.require_2fa),
    restricted_to_projects: toBool(user.restricted_to_projects),
    allowed_project_ids: allowedProjects || [],
    selectedRoles: [...(user.roles || [])]
  }
  formErrors.value = {}
  showUserModal.value = true
}

function openDeleteModal(user) {
  selectedUser.value = user
  formErrors.value = {}
  showDeleteModal.value = true
}

function closeModals() {
  showUserModal.value = false
  showDeleteModal.value = false
  showPermissionsModal.value = false
  selectedUser.value = null
  userDirectPermissions.value = []
  userRolePermissions.value = []
  permissionSearch.value = ''
}

// Permission Modal Functions
async function openPermissionsModal(user) {
  selectedUser.value = user
  showPermissionsModal.value = true
  await loadUserPermissions()
}

async function loadUserPermissions() {
  if (!selectedUser.value) return
  isLoadingPermissions.value = true
  try {
    const response = await api.get(`/api/v1/users/${selectedUser.value.id}/permissions`)
    const data = response.data.data || {}
    userDirectPermissions.value = data.direct_permissions || []
    userRolePermissions.value = data.role_permissions || []
  } catch (err) {
    console.error('Failed to load user permissions:', err)
    uiStore.showError('Fehler beim Laden der Berechtigungen')
  } finally {
    isLoadingPermissions.value = false
  }
}

async function assignPermissionToUser(permissionName) {
  if (!selectedUser.value) return
  try {
    await api.post(`/api/v1/users/${selectedUser.value.id}/permissions`, {
      permission: permissionName
    })
    uiStore.showSuccess('Berechtigung hinzugefügt')
    await loadUserPermissions()
  } catch (err) {
    console.error('Failed to assign permission:', err)
    uiStore.showError(err.response?.data?.error || 'Fehler beim Hinzufügen der Berechtigung')
  }
}

async function removePermissionFromUser(permissionName) {
  if (!selectedUser.value) return
  try {
    await api.delete(`/api/v1/users/${selectedUser.value.id}/permissions/${encodeURIComponent(permissionName)}`)
    uiStore.showSuccess('Berechtigung entfernt')
    await loadUserPermissions()
  } catch (err) {
    console.error('Failed to remove permission:', err)
    uiStore.showError(err.response?.data?.error || 'Fehler beim Entfernen der Berechtigung')
  }
}

function isPermissionDirectlyAssigned(permissionName) {
  return userDirectPermissions.value.includes(permissionName)
}

function isPermissionFromRole(permissionName) {
  return userRolePermissions.value.includes(permissionName)
}

function getFilteredPermissions() {
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
}

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

async function saveUser() {
  formErrors.value = {}
  isSaving.value = true

  try {
    if (isEditing.value) {
      // Update user
      await api.put(`/api/v1/users/${selectedUser.value.id}`, {
        email: formData.value.email,
        username: formData.value.username,
        is_active: formData.value.is_active,
        require_2fa: formData.value.require_2fa,
        restricted_to_projects: formData.value.restricted_to_projects,
        allowed_project_ids: formData.value.restricted_to_projects ? formData.value.allowed_project_ids : []
      })

      // Update roles - first get current roles, then add/remove as needed
      const currentRoles = selectedUser.value.roles || []
      const newRoles = formData.value.selectedRoles

      // Remove roles that are no longer selected
      for (const role of currentRoles) {
        if (!newRoles.includes(role)) {
          await api.delete(`/api/v1/users/${selectedUser.value.id}/roles/${role}`)
        }
      }

      // Add new roles
      for (const role of newRoles) {
        if (!currentRoles.includes(role)) {
          await api.post(`/api/v1/users/${selectedUser.value.id}/roles`, { role })
        }
      }
    } else {
      // Create new user
      await api.post('/api/v1/users', {
        email: formData.value.email,
        username: formData.value.username,
        password: formData.value.password,
        roles: formData.value.selectedRoles
      })
    }

    closeModals()
    await loadUsers()
  } catch (err) {
    console.error('Save error:', err)
    formErrors.value.general = err.response?.data?.error || err.response?.data?.message || 'Fehler beim Speichern'
  } finally {
    isSaving.value = false
  }
}

async function deleteUser() {
  if (!selectedUser.value) return

  isSaving.value = true
  try {
    await api.delete(`/api/v1/users/${selectedUser.value.id}`)
    closeModals()
    await loadUsers()
  } catch (err) {
    console.error('Delete error:', err)
    formErrors.value.general = err.response?.data?.error || err.response?.data?.message || 'Fehler beim Löschen'
  } finally {
    isSaving.value = false
  }
}

function toggleProjectSelection(projectId) {
  const index = formData.value.allowed_project_ids.indexOf(projectId)
  if (index > -1) {
    formData.value.allowed_project_ids.splice(index, 1)
  } else {
    formData.value.allowed_project_ids.push(projectId)
  }
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
  return classes[role] || 'bg-gray-500 text-white'
}

function toggleRole(roleName) {
  const index = formData.value.selectedRoles.indexOf(roleName)
  if (index > -1) {
    formData.value.selectedRoles.splice(index, 1)
  } else {
    formData.value.selectedRoles.push(roleName)
  }
}

// Check if current user can manage this role
function canManageRole(roleName) {
  if (authStore.hasRole('owner')) return true
  if (roleName === 'owner') return false
  return authStore.hasPermission('users.write')
}

// Check if user can be deleted
function canDeleteUser(user) {
  if (user.id === authStore.user?.id) return false
  if (user.roles?.includes('owner')) return false
  return authStore.hasPermission('users.delete')
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-white">Benutzer</h1>
        <p class="text-gray-400 mt-1">Verwalte alle Benutzer des Systems</p>
      </div>
      <button
        @click="openCreateModal"
        class="flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors"
      >
        <PlusIcon class="w-5 h-5" />
        Neuer Benutzer
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

    <!-- Pending Users Section -->
    <div v-if="pendingUsers.length > 0 && !isLoading" class="bg-yellow-900/20 border border-yellow-700/50 rounded-lg overflow-hidden">
      <div class="px-6 py-4 border-b border-yellow-700/50 flex items-center gap-3">
        <ClockIcon class="w-5 h-5 text-yellow-400" />
        <h2 class="text-lg font-semibold text-yellow-400">Wartende Registrierungen</h2>
        <span class="bg-yellow-600 text-white text-xs font-bold px-2 py-0.5 rounded-full">
          {{ pendingUsers.length }}
        </span>
      </div>
      <div class="divide-y divide-yellow-700/30">
        <div
          v-for="user in pendingUsers"
          :key="user.id"
          class="px-6 py-4 flex items-center justify-between hover:bg-yellow-900/10"
        >
          <div class="flex items-center gap-4">
            <div class="w-10 h-10 rounded-full bg-yellow-600 flex items-center justify-center">
              <span class="text-sm font-semibold text-white">
                {{ user.username?.[0]?.toUpperCase() || 'U' }}
              </span>
            </div>
            <div>
              <div class="text-sm font-medium text-white">{{ user.username }}</div>
              <div class="text-sm text-gray-400">{{ user.email }}</div>
            </div>
          </div>
          <div class="flex items-center gap-4">
            <div class="text-sm text-gray-500">
              Registriert am {{ new Date(user.created_at).toLocaleDateString('de-DE') }}
            </div>
            <div class="flex items-center gap-2">
              <button
                @click="approveUser(user)"
                class="flex items-center gap-1 px-3 py-1.5 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700 transition-colors"
              >
                <CheckIcon class="w-4 h-4" />
                Freischalten
              </button>
              <button
                @click="rejectUser(user)"
                class="flex items-center gap-1 px-3 py-1.5 bg-red-600 text-white text-sm rounded-lg hover:bg-red-700 transition-colors"
              >
                <XMarkIcon class="w-4 h-4" />
                Ablehnen
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Users Table -->
    <div v-if="!isLoading && !error" class="bg-dark-800 rounded-lg border border-dark-700 overflow-hidden">
      <table class="w-full">
        <thead class="bg-dark-700">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
              Benutzer
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
              E-Mail
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
              Rollen
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
              Status
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
              2FA
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
              Registriert
            </th>
            <th class="px-6 py-3 text-right text-xs font-medium text-gray-400 uppercase tracking-wider">
              Aktionen
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-dark-700">
          <tr v-for="user in users" :key="user.id" class="hover:bg-dark-700/50">
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="flex items-center">
                <div class="w-10 h-10 rounded-full bg-primary-600 flex items-center justify-center">
                  <span class="text-sm font-semibold text-white">
                    {{ user.username?.[0]?.toUpperCase() || 'U' }}
                  </span>
                </div>
                <div class="ml-4">
                  <div class="text-sm font-medium text-white">{{ user.username }}</div>
                  <div v-if="toBool(user.restricted_to_projects)" class="text-xs text-orange-400">
                    Eingeschränkter Zugriff
                  </div>
                </div>
              </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm text-gray-300">{{ user.email }}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="flex gap-1 flex-wrap">
                <span
                  v-for="role in (user.roles || [])"
                  :key="role"
                  class="px-2 py-1 text-xs font-medium rounded-full"
                  :class="getRoleBadgeClass(role)"
                >
                  {{ role }}
                </span>
                <span v-if="!user.roles?.length" class="text-gray-500 text-sm">-</span>
              </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span
                class="px-2 py-1 text-xs font-medium rounded-full"
                :class="toBool(user.is_active) ? 'bg-green-900 text-green-300' : 'bg-red-900 text-red-300'"
              >
                {{ toBool(user.is_active) ? 'Aktiv' : 'Inaktiv' }}
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="flex items-center gap-1">
                <ShieldCheckIcon
                  v-if="user.two_factor_secret"
                  class="w-5 h-5 text-green-400"
                  title="2FA aktiviert"
                />
                <span
                  v-if="toBool(user.require_2fa) && !user.two_factor_secret"
                  class="px-2 py-1 text-xs font-medium rounded-full bg-yellow-900 text-yellow-300"
                >
                  Erforderlich
                </span>
                <span v-else-if="!user.two_factor_secret" class="text-gray-500 text-sm">-</span>
              </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-400">
              {{ new Date(user.created_at).toLocaleDateString('de-DE') }}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
              <button
                @click="openPermissionsModal(user)"
                class="text-yellow-400 hover:text-yellow-300 mr-3"
                title="Berechtigungen verwalten"
              >
                <KeyIcon class="w-5 h-5 inline" />
              </button>
              <button
                @click="openEditModal(user)"
                class="text-primary-400 hover:text-primary-300 mr-3"
                title="Benutzer bearbeiten"
              >
                <PencilIcon class="w-5 h-5 inline" />
              </button>
              <button
                v-if="canDeleteUser(user)"
                @click="openDeleteModal(user)"
                class="text-red-400 hover:text-red-300"
                title="Benutzer löschen"
              >
                <TrashIcon class="w-5 h-5 inline" />
              </button>
            </td>
          </tr>
          <tr v-if="users.length === 0">
            <td colspan="7" class="px-6 py-12 text-center text-gray-400">
              Keine Benutzer gefunden
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- User Modal (Create/Edit) -->
    <Teleport to="body">
      <div
        v-if="showUserModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/70"
      >
        <div class="bg-dark-800 rounded-lg border border-dark-700 w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
          <!-- Modal Header -->
          <div class="flex items-center justify-between p-4 border-b border-dark-700">
            <h3 class="text-lg font-semibold text-white">
              {{ isEditing ? 'Benutzer bearbeiten' : 'Neuer Benutzer' }}
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

            <!-- Email -->
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">E-Mail</label>
              <input
                v-model="formData.email"
                type="email"
                class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:border-primary-500"
                placeholder="benutzer@beispiel.de"
              />
            </div>

            <!-- Username -->
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Benutzername</label>
              <input
                v-model="formData.username"
                type="text"
                class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:border-primary-500"
                placeholder="benutzername"
              />
            </div>

            <!-- Password (only for create) -->
            <div v-if="!isEditing">
              <label class="block text-sm font-medium text-gray-300 mb-1">Passwort</label>
              <input
                v-model="formData.password"
                type="password"
                class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:border-primary-500"
                placeholder="Mindestens 12 Zeichen"
              />
            </div>

            <!-- Roles -->
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Rollen</label>
              <div class="flex flex-wrap gap-2">
                <button
                  v-for="role in roles"
                  :key="role.name"
                  @click="toggleRole(role.name)"
                  :disabled="!canManageRole(role.name)"
                  class="px-3 py-1.5 rounded-full text-sm font-medium transition-colors"
                  :class="[
                    formData.selectedRoles.includes(role.name)
                      ? getRoleBadgeClass(role.name)
                      : 'bg-dark-700 text-gray-400 hover:bg-dark-600',
                    !canManageRole(role.name) ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
                  ]"
                >
                  {{ role.name }}
                </button>
              </div>
            </div>

            <!-- Divider -->
            <hr class="border-dark-600" />

            <!-- Status Checkboxes -->
            <div class="space-y-3">
              <label class="flex items-center gap-3 cursor-pointer">
                <input
                  v-model="formData.is_active"
                  type="checkbox"
                  class="w-5 h-5 rounded border-dark-600 bg-dark-700 text-primary-600 focus:ring-primary-500"
                />
                <div>
                  <span class="text-sm font-medium text-gray-300">Aktiv</span>
                  <p class="text-xs text-gray-500">Benutzer kann sich einloggen</p>
                </div>
              </label>

              <label class="flex items-center gap-3 cursor-pointer">
                <input
                  v-model="formData.require_2fa"
                  type="checkbox"
                  class="w-5 h-5 rounded border-dark-600 bg-dark-700 text-primary-600 focus:ring-primary-500"
                />
                <div>
                  <span class="text-sm font-medium text-gray-300">2FA erforderlich</span>
                  <p class="text-xs text-gray-500">Benutzer muss 2FA beim nächsten Login einrichten</p>
                </div>
              </label>

              <label class="flex items-center gap-3 cursor-pointer">
                <input
                  v-model="formData.restricted_to_projects"
                  type="checkbox"
                  class="w-5 h-5 rounded border-dark-600 bg-dark-700 text-primary-600 focus:ring-primary-500"
                />
                <div>
                  <span class="text-sm font-medium text-gray-300">Auf Projekte einschränken</span>
                  <p class="text-xs text-gray-500">Benutzer sieht nur ausgewählte Projekte</p>
                </div>
              </label>
            </div>

            <!-- Project Selection (only when restricted) -->
            <div v-if="formData.restricted_to_projects" class="space-y-2">
              <label class="block text-sm font-medium text-gray-300">Erlaubte Projekte</label>
              <div v-if="projects.length === 0" class="text-sm text-gray-500">
                Keine Projekte vorhanden
              </div>
              <div v-else class="max-h-48 overflow-y-auto bg-dark-700 rounded-lg p-2 space-y-1">
                <label
                  v-for="project in projects"
                  :key="project.id"
                  class="flex items-center gap-2 p-2 rounded hover:bg-dark-600 cursor-pointer"
                >
                  <input
                    type="checkbox"
                    :checked="formData.allowed_project_ids.includes(project.id)"
                    @change="toggleProjectSelection(project.id)"
                    class="w-4 h-4 rounded border-dark-600 bg-dark-700 text-primary-600 focus:ring-primary-500"
                  />
                  <span class="text-sm text-gray-300">{{ project.name }}</span>
                </label>
              </div>
              <p v-if="formData.allowed_project_ids.length === 0" class="text-xs text-orange-400">
                Keine Projekte ausgewählt - Benutzer hat keinen Zugriff
              </p>
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
              @click="saveUser"
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
            <h3 class="text-lg font-semibold text-white mb-2">Benutzer löschen</h3>
            <p class="text-gray-400">
              Bist du sicher, dass du den Benutzer
              <span class="text-white font-medium">{{ selectedUser?.username }}</span>
              löschen möchtest? Diese Aktion kann nicht rückgängig gemacht werden.
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
              @click="deleteUser"
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
                Berechtigungen verwalten
              </h3>
              <p class="text-sm text-gray-400">
                {{ selectedUser?.username }} ({{ selectedUser?.email }})
              </p>
            </div>
            <button @click="closeModals" class="text-gray-400 hover:text-white">
              <XMarkIcon class="w-6 h-6" />
            </button>
          </div>

          <!-- Modal Body -->
          <div class="p-4 flex-1 overflow-hidden flex flex-col">
            <!-- Loading -->
            <div v-if="isLoadingPermissions" class="flex justify-center py-8">
              <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-500"></div>
            </div>

            <template v-else>
              <!-- Currently Assigned Permissions Info -->
              <div class="mb-4 p-3 bg-dark-700 rounded-lg">
                <div class="flex items-center gap-4 text-sm">
                  <div class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-yellow-500"></span>
                    <span class="text-gray-300">Direkt zugewiesen: {{ userDirectPermissions.length }}</span>
                  </div>
                  <div class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-blue-500"></span>
                    <span class="text-gray-300">Von Rollen: {{ userRolePermissions.length }}</span>
                  </div>
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
              <div class="flex-1 overflow-y-auto space-y-4">
                <div
                  v-for="(permissions, module) in getFilteredPermissions()"
                  :key="module"
                  class="bg-dark-700 rounded-lg overflow-hidden"
                >
                  <div class="px-4 py-2 bg-dark-600 border-b border-dark-500">
                    <h4 class="text-sm font-medium text-white">{{ getModuleLabel(module) }}</h4>
                  </div>
                  <div class="p-2 space-y-1">
                    <div
                      v-for="permission in permissions"
                      :key="permission.name"
                      class="flex items-center justify-between p-2 rounded hover:bg-dark-600"
                    >
                      <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                          <span class="text-sm text-white font-mono">{{ permission.name }}</span>
                          <!-- Status indicators -->
                          <span
                            v-if="isPermissionDirectlyAssigned(permission.name)"
                            class="px-1.5 py-0.5 text-xs rounded bg-yellow-600 text-white"
                          >
                            Direkt
                          </span>
                          <span
                            v-else-if="isPermissionFromRole(permission.name)"
                            class="px-1.5 py-0.5 text-xs rounded bg-blue-600 text-white"
                          >
                            Rolle
                          </span>
                        </div>
                        <p v-if="permission.description" class="text-xs text-gray-500 truncate">
                          {{ permission.description }}
                        </p>
                      </div>
                      <div class="flex-shrink-0 ml-4">
                        <!-- If directly assigned, show remove button -->
                        <button
                          v-if="isPermissionDirectlyAssigned(permission.name)"
                          @click="removePermissionFromUser(permission.name)"
                          class="px-3 py-1 text-sm bg-red-600 text-white rounded hover:bg-red-700 transition-colors"
                        >
                          Entfernen
                        </button>
                        <!-- If from role, show disabled info -->
                        <span
                          v-else-if="isPermissionFromRole(permission.name)"
                          class="px-3 py-1 text-sm text-gray-500"
                        >
                          Von Rolle
                        </span>
                        <!-- If not assigned, show add button -->
                        <button
                          v-else
                          @click="assignPermissionToUser(permission.name)"
                          class="px-3 py-1 text-sm bg-primary-600 text-white rounded hover:bg-primary-700 transition-colors"
                        >
                          Hinzufügen
                        </button>
                      </div>
                    </div>
                  </div>
                </div>

                <div
                  v-if="Object.keys(getFilteredPermissions()).length === 0"
                  class="text-center py-8 text-gray-500"
                >
                  Keine Berechtigungen gefunden
                </div>
              </div>
            </template>
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
