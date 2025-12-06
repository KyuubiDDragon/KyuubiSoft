<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useUiStore } from '@/stores/ui'
import { markSetupComplete } from '@/router'
import api from '@/core/api/axios'
import { EyeIcon, EyeSlashIcon, CheckCircleIcon, ServerIcon } from '@heroicons/vue/24/outline'

const router = useRouter()
const uiStore = useUiStore()

const form = reactive({
  email: '',
  username: '',
  password: '',
  confirm_password: '',
  instance_name: 'KyuubiSoft',
})

const showPassword = ref(false)
const showConfirmPassword = ref(false)
const errors = ref({})
const isLoading = ref(false)
const isChecking = ref(true)
const setupRequired = ref(false)
const setupComplete = ref(false)

// Password strength indicators
const passwordStrength = reactive({
  hasMinLength: false,
  hasUppercase: false,
  hasLowercase: false,
  hasNumber: false,
  hasSpecial: false,
})

function checkPasswordStrength(password) {
  passwordStrength.hasMinLength = password.length >= 12
  passwordStrength.hasUppercase = /[A-Z]/.test(password)
  passwordStrength.hasLowercase = /[a-z]/.test(password)
  passwordStrength.hasNumber = /[0-9]/.test(password)
  passwordStrength.hasSpecial = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)
}

function watchPassword() {
  checkPasswordStrength(form.password)
}

onMounted(async () => {
  try {
    const response = await api.get('/api/v1/setup/status')
    setupRequired.value = response.data.data.setup_required

    if (!setupRequired.value) {
      // Setup already completed, redirect to login
      router.push('/login')
    }
  } catch (error) {
    console.error('Failed to check setup status:', error)
    uiStore.showError('Verbindung zum Server fehlgeschlagen')
  } finally {
    isChecking.value = false
  }
})

async function handleSubmit() {
  errors.value = {}

  // Validate
  if (!form.email) {
    errors.value.email = 'E-Mail ist erforderlich'
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) {
    errors.value.email = 'Ungültige E-Mail-Adresse'
  }

  if (!form.username) {
    errors.value.username = 'Benutzername ist erforderlich'
  } else if (form.username.length < 3) {
    errors.value.username = 'Benutzername muss mindestens 3 Zeichen lang sein'
  }

  if (!form.password) {
    errors.value.password = 'Passwort ist erforderlich'
  } else if (form.password.length < 12) {
    errors.value.password = 'Passwort muss mindestens 12 Zeichen lang sein'
  } else if (!/[A-Z]/.test(form.password)) {
    errors.value.password = 'Passwort muss mindestens einen Großbuchstaben enthalten'
  } else if (!/[a-z]/.test(form.password)) {
    errors.value.password = 'Passwort muss mindestens einen Kleinbuchstaben enthalten'
  } else if (!/[0-9]/.test(form.password)) {
    errors.value.password = 'Passwort muss mindestens eine Zahl enthalten'
  } else if (!/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(form.password)) {
    errors.value.password = 'Passwort muss mindestens ein Sonderzeichen enthalten'
  }

  if (form.password !== form.confirm_password) {
    errors.value.confirm_password = 'Passwörter stimmen nicht überein'
  }

  if (Object.keys(errors.value).length > 0) {
    return
  }

  isLoading.value = true

  try {
    await api.post('/api/v1/setup/complete', form)

    // Mark setup as complete in router
    markSetupComplete()

    setupComplete.value = true
    uiStore.showSuccess('Einrichtung abgeschlossen! Du kannst dich jetzt anmelden.')

    // Redirect to login after 2 seconds
    setTimeout(() => {
      router.push('/login')
    }, 2000)
  } catch (error) {
    const message = error.response?.data?.error || 'Einrichtung fehlgeschlagen'
    errors.value.general = message
    uiStore.showError(message)
  } finally {
    isLoading.value = false
  }
}
</script>

<template>
  <div>
    <!-- Loading state -->
    <div v-if="isChecking" class="text-center py-8">
      <svg class="animate-spin h-8 w-8 mx-auto text-primary-500" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" />
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
      </svg>
      <p class="mt-4 text-gray-400">Prüfe Setup-Status...</p>
    </div>

    <!-- Setup Complete -->
    <div v-else-if="setupComplete" class="text-center py-8">
      <CheckCircleIcon class="h-16 w-16 mx-auto text-green-500 mb-4" />
      <h2 class="text-2xl font-bold text-white mb-2">Einrichtung abgeschlossen!</h2>
      <p class="text-gray-400 mb-6">Du wirst zum Login weitergeleitet...</p>
    </div>

    <!-- Setup Form -->
    <div v-else-if="setupRequired">
      <div class="flex items-center gap-3 mb-2">
        <ServerIcon class="h-8 w-8 text-primary-500" />
        <h2 class="text-2xl font-bold text-white">Ersteinrichtung</h2>
      </div>
      <p class="text-gray-400 mb-6">
        Willkommen! Erstelle deinen Administrator-Account, um zu starten.
      </p>

      <form @submit.prevent="handleSubmit" class="space-y-5">
        <!-- General error -->
        <div
          v-if="errors.general"
          class="bg-red-500/10 border border-red-500/50 text-red-400 px-4 py-3 rounded-lg text-sm"
        >
          {{ errors.general }}
        </div>

        <!-- Instance Name -->
        <div>
          <label for="instance_name" class="label">Instanz-Name</label>
          <input
            id="instance_name"
            v-model="form.instance_name"
            type="text"
            class="input"
            placeholder="Mein KyuubiSoft"
          />
          <p class="mt-1 text-xs text-gray-500">Der Name deiner Installation (wird im Titel angezeigt)</p>
        </div>

        <!-- E-Mail -->
        <div>
          <label for="email" class="label">E-Mail *</label>
          <input
            id="email"
            v-model="form.email"
            type="email"
            autocomplete="email"
            required
            class="input"
            :class="{ 'input-error': errors.email }"
            placeholder="admin@example.com"
          />
          <p v-if="errors.email" class="mt-1 text-sm text-red-400">{{ errors.email }}</p>
        </div>

        <!-- Username -->
        <div>
          <label for="username" class="label">Benutzername *</label>
          <input
            id="username"
            v-model="form.username"
            type="text"
            autocomplete="username"
            required
            class="input"
            :class="{ 'input-error': errors.username }"
            placeholder="admin"
          />
          <p v-if="errors.username" class="mt-1 text-sm text-red-400">{{ errors.username }}</p>
        </div>

        <!-- Password -->
        <div>
          <label for="password" class="label">Passwort *</label>
          <div class="relative">
            <input
              id="password"
              v-model="form.password"
              @input="watchPassword"
              :type="showPassword ? 'text' : 'password'"
              autocomplete="new-password"
              required
              class="input pr-10"
              :class="{ 'input-error': errors.password }"
              placeholder="••••••••••••"
            />
            <button
              type="button"
              @click="showPassword = !showPassword"
              class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-300"
            >
              <EyeSlashIcon v-if="showPassword" class="w-5 h-5" />
              <EyeIcon v-else class="w-5 h-5" />
            </button>
          </div>
          <p v-if="errors.password" class="mt-1 text-sm text-red-400">{{ errors.password }}</p>

          <!-- Password strength indicators -->
          <div v-if="form.password" class="mt-2 grid grid-cols-2 gap-1 text-xs">
            <div :class="passwordStrength.hasMinLength ? 'text-green-500' : 'text-gray-500'">
              {{ passwordStrength.hasMinLength ? '✓' : '○' }} Mind. 12 Zeichen
            </div>
            <div :class="passwordStrength.hasUppercase ? 'text-green-500' : 'text-gray-500'">
              {{ passwordStrength.hasUppercase ? '✓' : '○' }} Großbuchstabe
            </div>
            <div :class="passwordStrength.hasLowercase ? 'text-green-500' : 'text-gray-500'">
              {{ passwordStrength.hasLowercase ? '✓' : '○' }} Kleinbuchstabe
            </div>
            <div :class="passwordStrength.hasNumber ? 'text-green-500' : 'text-gray-500'">
              {{ passwordStrength.hasNumber ? '✓' : '○' }} Zahl
            </div>
            <div :class="passwordStrength.hasSpecial ? 'text-green-500' : 'text-gray-500'" class="col-span-2">
              {{ passwordStrength.hasSpecial ? '✓' : '○' }} Sonderzeichen (!@#$%^&*...)
            </div>
          </div>
        </div>

        <!-- Confirm Password -->
        <div>
          <label for="confirm_password" class="label">Passwort bestätigen *</label>
          <div class="relative">
            <input
              id="confirm_password"
              v-model="form.confirm_password"
              :type="showConfirmPassword ? 'text' : 'password'"
              autocomplete="new-password"
              required
              class="input pr-10"
              :class="{ 'input-error': errors.confirm_password }"
              placeholder="••••••••••••"
            />
            <button
              type="button"
              @click="showConfirmPassword = !showConfirmPassword"
              class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-300"
            >
              <EyeSlashIcon v-if="showConfirmPassword" class="w-5 h-5" />
              <EyeIcon v-else class="w-5 h-5" />
            </button>
          </div>
          <p v-if="errors.confirm_password" class="mt-1 text-sm text-red-400">{{ errors.confirm_password }}</p>
        </div>

        <!-- Info Box -->
        <div class="bg-primary-500/10 border border-primary-500/30 text-primary-300 px-4 py-3 rounded-lg text-sm">
          <strong>Hinweis:</strong> Dieser Account erhält automatisch volle Administrator-Rechte (Owner-Rolle).
        </div>

        <!-- Submit button -->
        <button
          type="submit"
          :disabled="isLoading"
          class="btn-primary w-full"
        >
          <span v-if="isLoading" class="flex items-center justify-center gap-2">
            <svg class="animate-spin h-5 w-5" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            Einrichtung...
          </span>
          <span v-else>Einrichtung abschließen</span>
        </button>
      </form>
    </div>
  </div>
</template>
