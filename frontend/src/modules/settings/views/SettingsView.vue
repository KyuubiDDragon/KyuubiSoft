<script setup>
import { ref, reactive } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import {
  UserIcon,
  ShieldCheckIcon,
  PaintBrushIcon,
  BellIcon,
} from '@heroicons/vue/24/outline'

const authStore = useAuthStore()
const uiStore = useUiStore()

const activeTab = ref('profile')

const tabs = [
  { id: 'profile', name: 'Profil', icon: UserIcon },
  { id: 'security', name: 'Sicherheit', icon: ShieldCheckIcon },
  { id: 'appearance', name: 'Darstellung', icon: PaintBrushIcon },
  { id: 'notifications', name: 'Benachrichtigungen', icon: BellIcon },
]

const profile = reactive({
  username: authStore.user?.username || '',
  email: authStore.user?.email || '',
})

const security = reactive({
  currentPassword: '',
  newPassword: '',
  confirmPassword: '',
})

function saveProfile() {
  uiStore.showSuccess('Profil gespeichert!')
}

function changePassword() {
  if (security.newPassword !== security.confirmPassword) {
    uiStore.showError('Passwörter stimmen nicht überein')
    return
  }
  uiStore.showSuccess('Passwort geändert!')
  security.currentPassword = ''
  security.newPassword = ''
  security.confirmPassword = ''
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div>
      <h1 class="text-2xl font-bold text-white">Einstellungen</h1>
      <p class="text-gray-400 mt-1">Verwalte dein Konto und deine Präferenzen</p>
    </div>

    <div class="flex gap-6">
      <!-- Sidebar tabs -->
      <div class="w-64 shrink-0">
        <nav class="space-y-1">
          <button
            v-for="tab in tabs"
            :key="tab.id"
            @click="activeTab = tab.id"
            class="w-full flex items-center gap-3 px-4 py-2.5 rounded-lg transition-all duration-200"
            :class="[
              activeTab === tab.id
                ? 'bg-primary-600 text-white'
                : 'text-gray-400 hover:bg-dark-700 hover:text-white'
            ]"
          >
            <component :is="tab.icon" class="w-5 h-5" />
            {{ tab.name }}
          </button>
        </nav>
      </div>

      <!-- Content -->
      <div class="flex-1">
        <!-- Profile -->
        <div v-if="activeTab === 'profile'" class="card p-6">
          <h2 class="text-lg font-semibold text-white mb-6">Profil</h2>

          <div class="space-y-4">
            <div>
              <label class="label">Benutzername</label>
              <input
                v-model="profile.username"
                type="text"
                class="input"
              />
            </div>

            <div>
              <label class="label">E-Mail</label>
              <input
                v-model="profile.email"
                type="email"
                class="input"
                disabled
              />
              <p class="mt-1 text-xs text-gray-500">E-Mail kann nicht geändert werden</p>
            </div>

            <button @click="saveProfile" class="btn-primary">
              Speichern
            </button>
          </div>
        </div>

        <!-- Security -->
        <div v-if="activeTab === 'security'" class="space-y-6">
          <div class="card p-6">
            <h2 class="text-lg font-semibold text-white mb-6">Passwort ändern</h2>

            <div class="space-y-4">
              <div>
                <label class="label">Aktuelles Passwort</label>
                <input
                  v-model="security.currentPassword"
                  type="password"
                  class="input"
                />
              </div>

              <div>
                <label class="label">Neues Passwort</label>
                <input
                  v-model="security.newPassword"
                  type="password"
                  class="input"
                />
              </div>

              <div>
                <label class="label">Passwort bestätigen</label>
                <input
                  v-model="security.confirmPassword"
                  type="password"
                  class="input"
                />
              </div>

              <button @click="changePassword" class="btn-primary">
                Passwort ändern
              </button>
            </div>
          </div>

          <div class="card p-6">
            <h2 class="text-lg font-semibold text-white mb-4">Zwei-Faktor-Authentifizierung</h2>
            <p class="text-gray-400 mb-4">
              Erhöhe die Sicherheit deines Kontos durch 2FA.
            </p>
            <button class="btn-secondary">
              2FA aktivieren
            </button>
          </div>
        </div>

        <!-- Appearance -->
        <div v-if="activeTab === 'appearance'" class="card p-6">
          <h2 class="text-lg font-semibold text-white mb-6">Darstellung</h2>

          <div class="space-y-4">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-white font-medium">Dark Mode</p>
                <p class="text-gray-400 text-sm">Dunkles Farbschema verwenden</p>
              </div>
              <button
                @click="uiStore.toggleDarkMode"
                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                :class="uiStore.isDarkMode ? 'bg-primary-600' : 'bg-dark-600'"
              >
                <span
                  class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                  :class="uiStore.isDarkMode ? 'translate-x-6' : 'translate-x-1'"
                />
              </button>
            </div>

            <div class="flex items-center justify-between">
              <div>
                <p class="text-white font-medium">Kompakte Sidebar</p>
                <p class="text-gray-400 text-sm">Sidebar standardmäßig einklappen</p>
              </div>
              <button
                @click="uiStore.toggleSidebarCollapse"
                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                :class="uiStore.sidebarCollapsed ? 'bg-primary-600' : 'bg-dark-600'"
              >
                <span
                  class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                  :class="uiStore.sidebarCollapsed ? 'translate-x-6' : 'translate-x-1'"
                />
              </button>
            </div>
          </div>
        </div>

        <!-- Notifications -->
        <div v-if="activeTab === 'notifications'" class="card p-6">
          <h2 class="text-lg font-semibold text-white mb-6">Benachrichtigungen</h2>
          <p class="text-gray-400">Benachrichtigungseinstellungen werden bald verfügbar sein.</p>
        </div>
      </div>
    </div>
  </div>
</template>
