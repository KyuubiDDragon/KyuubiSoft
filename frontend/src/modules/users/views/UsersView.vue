<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '@/core/api/axios'
import { useAuthStore } from '@/stores/auth'
import { XMarkIcon, PlusIcon, TrashIcon, PencilIcon } from '@heroicons/vue/24/outline'

const authStore = useAuthStore()
const users = ref([])
const roles = ref([])
const isLoading = ref(true)
const error = ref(null)

// Modal states
const showUserModal = ref(false)
const showDeleteModal = ref(false)
const isEditing = ref(false)
const isSaving = ref(false)
const selectedUser = ref(null)

// Form data
const formData = ref({
  email: '',
  username: '',
  password: '',
  is_active: true,
  restricted_to_projects: false,
  selectedRoles: []
})
const formErrors = ref({})

onMounted(async () => {
  await Promise.all([loadUsers(), loadRoles()])
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

async function loadRoles() {
  try {
    const response = await api.get('/api/v1/admin/roles')
    roles.value = response.data.data || []
  } catch (err) {
    console.error('Failed to load roles:', err)
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
    restricted_to_projects: false,
    selectedRoles: ['user']
  }
  formErrors.value = {}
  showUserModal.value = true
}

function openEditModal(user) {
  isEditing.value = true
  selectedUser.value = user
  formData.value = {
    email: user.email,
    username: user.username,
    password: '',
    is_active: user.is_active,
    restricted_to_projects: user.restricted_to_projects || false,
    selectedRoles: [...(user.roles || [])]
  }
  formErrors.value = {}
  showUserModal.value = true
}

function openDeleteModal(user) {
  selectedUser.value = user
  showDeleteModal.value = true
}

function closeModals() {
  showUserModal.value = false
  showDeleteModal.value = false
  selectedUser.value = null
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
        restricted_to_projects: formData.value.restricted_to_projects
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
    if (err.response?.data?.message) {
      formErrors.value.general = err.response.data.message
    } else {
      formErrors.value.general = 'Fehler beim Speichern'
    }
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
    formErrors.value.general = err.response?.data?.message || 'Fehler beim Löschen'
  } finally {
    isSaving.value = false
  }
}

async function toggleProjectRestriction(user) {
  try {
    const newValue = !user.restricted_to_projects
    await api.put(`/api/v1/users/${user.id}`, {
      restricted_to_projects: newValue
    })
    user.restricted_to_projects = newValue
  } catch (err) {
    console.error('Failed to toggle restriction:', err)
  }
}

function getRoleBadgeClass(role) {
  const classes = {
    owner: 'bg-purple-600 text-white',
    admin: 'bg-red-600 text-white',
    editor: 'bg-blue-600 text-white',
    user: 'bg-green-600 text-white',
    viewer: 'bg-gray-600 text-white',
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

    <!-- Users Table -->
    <div v-else class="bg-dark-800 rounded-lg border border-dark-700 overflow-hidden">
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
              Projektzugriff
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
                :class="user.is_active ? 'bg-green-900 text-green-300' : 'bg-red-900 text-red-300'"
              >
                {{ user.is_active ? 'Aktiv' : 'Inaktiv' }}
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <button
                @click="toggleProjectRestriction(user)"
                class="px-2 py-1 text-xs font-medium rounded-full transition-colors"
                :class="user.restricted_to_projects
                  ? 'bg-orange-900 text-orange-300 hover:bg-orange-800'
                  : 'bg-gray-700 text-gray-400 hover:bg-gray-600'"
                :title="user.restricted_to_projects ? 'Nur geteilte Projekte' : 'Voller Zugriff'"
              >
                {{ user.restricted_to_projects ? 'Eingeschränkt' : 'Voller Zugriff' }}
              </button>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-400">
              {{ new Date(user.created_at).toLocaleDateString('de-DE') }}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
              <button
                @click="openEditModal(user)"
                class="text-primary-400 hover:text-primary-300 mr-3"
              >
                <PencilIcon class="w-5 h-5 inline" />
              </button>
              <button
                v-if="canDeleteUser(user)"
                @click="openDeleteModal(user)"
                class="text-red-400 hover:text-red-300"
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
        @click.self="closeModals"
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
                placeholder="Mindestens 8 Zeichen"
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

            <!-- Status -->
            <div class="flex items-center gap-4">
              <label class="flex items-center gap-2 cursor-pointer">
                <input
                  v-model="formData.is_active"
                  type="checkbox"
                  class="w-4 h-4 rounded border-dark-600 bg-dark-700 text-primary-600 focus:ring-primary-500"
                />
                <span class="text-sm text-gray-300">Aktiv</span>
              </label>

              <label class="flex items-center gap-2 cursor-pointer">
                <input
                  v-model="formData.restricted_to_projects"
                  type="checkbox"
                  class="w-4 h-4 rounded border-dark-600 bg-dark-700 text-primary-600 focus:ring-primary-500"
                />
                <span class="text-sm text-gray-300">Auf Projekte einschränken</span>
              </label>
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
        @click.self="closeModals"
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
  </div>
</template>
