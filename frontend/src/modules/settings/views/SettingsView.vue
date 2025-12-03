<script setup>
import { ref, reactive, watch, nextTick } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import api from '@/core/api/axios'
import QRCode from 'qrcode'
import {
  UserIcon,
  ShieldCheckIcon,
  PaintBrushIcon,
  BellIcon,
  CheckCircleIcon,
  XCircleIcon,
} from '@heroicons/vue/24/outline'

const authStore = useAuthStore()
const uiStore = useUiStore()

const activeTab = ref('profile')
const isSaving = ref(false)
const isChangingPassword = ref(false)

// 2FA state
const twoFactorEnabled = ref(false)
const twoFactorSetup = ref(null)
const qrCodeDataUrl = ref('')
const verificationCode = ref('')
const isEnabling2FA = ref(false)
const isDisabling2FA = ref(false)
const disableCode = ref('')

const tabs = [
  { id: 'profile', name: 'Profil', icon: UserIcon },
  { id: 'security', name: 'Sicherheit', icon: ShieldCheckIcon },
  { id: 'appearance', name: 'Darstellung', icon: PaintBrushIcon },
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

// Watch for auth store user changes
watch(() => authStore.user, (user) => {
  if (user) {
    profile.username = user.username || ''
    profile.email = user.email || ''
    twoFactorEnabled.value = !!user.two_factor_enabled
  }
}, { immediate: true })

async function saveProfile() {
  if (isSaving.value) return
  isSaving.value = true

  try {
    const response = await api.put('/api/v1/users/me/profile', {
      username: profile.username
    })
    // Update auth store with new user data
    if (response.data.data) {
      authStore.user = { ...authStore.user, ...response.data.data }
    }
    uiStore.showSuccess('Profil gespeichert!')
  } catch (error) {
    const message = error.response?.data?.error || 'Fehler beim Speichern'
    uiStore.showError(message)
  } finally {
    isSaving.value = false
  }
}

async function changePassword() {
  if (isChangingPassword.value) return

  if (security.newPassword !== security.confirmPassword) {
    uiStore.showError('Passwörter stimmen nicht überein')
    return
  }

  if (security.newPassword.length < 8) {
    uiStore.showError('Passwort muss mindestens 8 Zeichen lang sein')
    return
  }

  isChangingPassword.value = true

  try {
    await api.put('/api/v1/users/me/password', {
      current_password: security.currentPassword,
      new_password: security.newPassword,
      confirm_password: security.confirmPassword
    })
    uiStore.showSuccess('Passwort geändert!')
    security.currentPassword = ''
    security.newPassword = ''
    security.confirmPassword = ''
  } catch (error) {
    const message = error.response?.data?.error || 'Fehler beim Ändern des Passworts'
    uiStore.showError(message)
  } finally {
    isChangingPassword.value = false
  }
}

async function startEnable2FA() {
  isEnabling2FA.value = true
  try {
    const response = await api.post('/api/v1/auth/2fa/enable')
    twoFactorSetup.value = response.data.data

    // Generate QR code from otpauth URL
    if (twoFactorSetup.value.qr_code_url) {
      qrCodeDataUrl.value = await QRCode.toDataURL(twoFactorSetup.value.qr_code_url, {
        width: 200,
        margin: 2,
        color: {
          dark: '#000000',
          light: '#ffffff'
        }
      })
    }
  } catch (error) {
    const message = error.response?.data?.error || 'Fehler beim Aktivieren von 2FA'
    uiStore.showError(message)
    isEnabling2FA.value = false
  }
}

async function verify2FA() {
  if (!verificationCode.value || verificationCode.value.length !== 6) {
    uiStore.showError('Bitte gib einen gültigen 6-stelligen Code ein')
    return
  }

  try {
    await api.post('/api/v1/auth/2fa/verify', {
      code: verificationCode.value
    })

    // Refresh user data to get updated two_factor_enabled status
    await authStore.fetchUser()

    twoFactorEnabled.value = true
    twoFactorSetup.value = null
    qrCodeDataUrl.value = ''
    verificationCode.value = ''
    isEnabling2FA.value = false
    uiStore.showSuccess('2FA erfolgreich aktiviert!')
  } catch (error) {
    const message = error.response?.data?.error || 'Ungültiger Code'
    uiStore.showError(message)
  }
}

function cancelEnable2FA() {
  twoFactorSetup.value = null
  qrCodeDataUrl.value = ''
  verificationCode.value = ''
  isEnabling2FA.value = false
}

async function disable2FA() {
  if (!disableCode.value || disableCode.value.length !== 6) {
    uiStore.showError('Bitte gib deinen aktuellen 2FA-Code ein')
    return
  }

  try {
    await api.delete('/api/v1/auth/2fa/disable', {
      data: { code: disableCode.value }
    })
    twoFactorEnabled.value = false
    disableCode.value = ''
    isDisabling2FA.value = false
    uiStore.showSuccess('2FA deaktiviert')
  } catch (error) {
    const message = error.response?.data?.error || 'Ungültiger Code'
    uiStore.showError(message)
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div>
      <h1 class="text-2xl font-bold text-white">Einstellungen</h1>
      <p class="text-gray-400 mt-1">Verwalte dein Konto und deine Präferenzen</p>
    </div>

    <div class="flex flex-col lg:flex-row gap-6">
      <!-- Sidebar tabs -->
      <div class="lg:w-64 shrink-0">
        <nav class="flex lg:flex-col gap-1 overflow-x-auto lg:overflow-visible">
          <button
            v-for="tab in tabs"
            :key="tab.id"
            @click="activeTab = tab.id"
            class="flex items-center gap-3 px-4 py-2.5 rounded-lg transition-all duration-200 whitespace-nowrap"
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
                class="input bg-dark-700/50"
                disabled
              />
              <p class="mt-1 text-xs text-gray-500">E-Mail kann nicht geändert werden</p>
            </div>

            <button
              @click="saveProfile"
              :disabled="isSaving"
              class="btn-primary"
            >
              <span v-if="isSaving" class="flex items-center gap-2">
                <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" />
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
                Speichern...
              </span>
              <span v-else>Speichern</span>
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
                  autocomplete="current-password"
                />
              </div>

              <div>
                <label class="label">Neues Passwort</label>
                <input
                  v-model="security.newPassword"
                  type="password"
                  class="input"
                  autocomplete="new-password"
                />
              </div>

              <div>
                <label class="label">Passwort bestätigen</label>
                <input
                  v-model="security.confirmPassword"
                  type="password"
                  class="input"
                  autocomplete="new-password"
                />
              </div>

              <button
                @click="changePassword"
                :disabled="isChangingPassword"
                class="btn-primary"
              >
                <span v-if="isChangingPassword" class="flex items-center gap-2">
                  <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                  </svg>
                  Ändern...
                </span>
                <span v-else>Passwort ändern</span>
              </button>
            </div>
          </div>

          <!-- 2FA Section -->
          <div class="card p-6">
            <div class="flex items-center justify-between mb-4">
              <div>
                <h2 class="text-lg font-semibold text-white">Zwei-Faktor-Authentifizierung</h2>
                <p class="text-gray-400 text-sm mt-1">
                  Erhöhe die Sicherheit deines Kontos durch 2FA.
                </p>
              </div>
              <div v-if="twoFactorEnabled" class="flex items-center gap-2 text-green-400">
                <CheckCircleIcon class="w-5 h-5" />
                <span class="text-sm font-medium">Aktiv</span>
              </div>
              <div v-else class="flex items-center gap-2 text-gray-500">
                <XCircleIcon class="w-5 h-5" />
                <span class="text-sm">Inaktiv</span>
              </div>
            </div>

            <!-- 2FA Setup Flow -->
            <div v-if="twoFactorSetup" class="space-y-4 border-t border-dark-700 pt-4">
              <p class="text-gray-300">Scanne den QR-Code mit deiner Authenticator-App:</p>

              <div class="bg-white p-4 rounded-lg inline-block">
                <img v-if="qrCodeDataUrl" :src="qrCodeDataUrl" alt="2FA QR Code" class="w-48 h-48" />
                <div v-else class="w-48 h-48 flex items-center justify-center">
                  <svg class="animate-spin h-8 w-8 text-gray-400" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                  </svg>
                </div>
              </div>

              <div class="bg-dark-700/50 rounded-lg p-4">
                <p class="text-sm text-gray-400 mb-2">Manueller Schlüssel:</p>
                <code class="text-primary-400 text-sm break-all">{{ twoFactorSetup.secret }}</code>
              </div>

              <div>
                <label class="label">Bestätigungscode</label>
                <input
                  v-model="verificationCode"
                  type="text"
                  inputmode="numeric"
                  pattern="[0-9]*"
                  maxlength="6"
                  class="input w-48 text-center tracking-widest text-lg"
                  placeholder="000000"
                />
              </div>

              <div class="flex gap-3">
                <button @click="cancelEnable2FA" class="btn-secondary">
                  Abbrechen
                </button>
                <button @click="verify2FA" class="btn-primary">
                  Verifizieren & Aktivieren
                </button>
              </div>
            </div>

            <!-- 2FA Disable Flow -->
            <div v-else-if="isDisabling2FA" class="space-y-4 border-t border-dark-700 pt-4">
              <p class="text-gray-300">Gib deinen aktuellen 2FA-Code ein, um 2FA zu deaktivieren:</p>

              <div>
                <label class="label">2FA-Code</label>
                <input
                  v-model="disableCode"
                  type="text"
                  inputmode="numeric"
                  pattern="[0-9]*"
                  maxlength="6"
                  class="input w-48 text-center tracking-widest text-lg"
                  placeholder="000000"
                />
              </div>

              <div class="flex gap-3">
                <button @click="isDisabling2FA = false; disableCode = ''" class="btn-secondary">
                  Abbrechen
                </button>
                <button @click="disable2FA" class="btn-primary bg-red-600 hover:bg-red-700">
                  2FA Deaktivieren
                </button>
              </div>
            </div>

            <!-- 2FA Action Buttons -->
            <div v-else class="pt-2">
              <button
                v-if="!twoFactorEnabled"
                @click="startEnable2FA"
                :disabled="isEnabling2FA"
                class="btn-primary"
              >
                <span v-if="isEnabling2FA">Laden...</span>
                <span v-else>2FA aktivieren</span>
              </button>
              <button
                v-else
                @click="isDisabling2FA = true"
                class="btn-secondary text-red-400"
              >
                2FA deaktivieren
              </button>
            </div>
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
      </div>
    </div>
  </div>
</template>
