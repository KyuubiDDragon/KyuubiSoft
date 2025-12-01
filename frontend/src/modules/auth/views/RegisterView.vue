<script setup>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { EyeIcon, EyeSlashIcon, CheckIcon, XMarkIcon } from '@heroicons/vue/24/outline'

const router = useRouter()
const authStore = useAuthStore()
const uiStore = useUiStore()

const form = reactive({
  email: '',
  username: '',
  password: '',
  passwordConfirmation: '',
})

const showPassword = ref(false)
const showPasswordConfirm = ref(false)
const errors = ref({})
const isLoading = ref(false)

// Password strength checks
const passwordChecks = ref({
  length: false,
  uppercase: false,
  lowercase: false,
  number: false,
  special: false,
})

function checkPassword(password) {
  passwordChecks.value = {
    length: password.length >= 12,
    uppercase: /[A-Z]/.test(password),
    lowercase: /[a-z]/.test(password),
    number: /[0-9]/.test(password),
    special: /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password),
  }
}

function onPasswordInput(e) {
  form.password = e.target.value
  checkPassword(form.password)
}

const isPasswordStrong = () => {
  return Object.values(passwordChecks.value).every(Boolean)
}

async function handleSubmit() {
  errors.value = {}

  // Validate
  if (!form.email) {
    errors.value.email = 'E-Mail ist erforderlich'
    return
  }

  if (!isPasswordStrong()) {
    errors.value.password = 'Passwort erfüllt nicht alle Anforderungen'
    return
  }

  if (form.password !== form.passwordConfirmation) {
    errors.value.passwordConfirmation = 'Passwörter stimmen nicht überein'
    return
  }

  isLoading.value = true

  try {
    await authStore.register({
      email: form.email,
      username: form.username || undefined,
      password: form.password,
      password_confirmation: form.passwordConfirmation,
    })

    uiStore.showSuccess('Registrierung erfolgreich!')
    router.push('/')
  } catch (error) {
    const message = error.response?.data?.error || 'Registrierung fehlgeschlagen'
    errors.value.general = message
    uiStore.showError(message)
  } finally {
    isLoading.value = false
  }
}
</script>

<template>
  <div>
    <h2 class="text-2xl font-bold text-white mb-2">Registrieren</h2>
    <p class="text-gray-400 mb-6">Erstelle ein neues Konto</p>

    <form @submit.prevent="handleSubmit" class="space-y-5">
      <!-- General error -->
      <div
        v-if="errors.general"
        class="bg-red-500/10 border border-red-500/50 text-red-400 px-4 py-3 rounded-lg text-sm"
      >
        {{ errors.general }}
      </div>

      <!-- Email -->
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
          placeholder="deine@email.de"
        />
        <p v-if="errors.email" class="mt-1 text-sm text-red-400">{{ errors.email }}</p>
      </div>

      <!-- Username -->
      <div>
        <label for="username" class="label">Benutzername (optional)</label>
        <input
          id="username"
          v-model="form.username"
          type="text"
          autocomplete="username"
          class="input"
          placeholder="DeinName"
        />
      </div>

      <!-- Password -->
      <div>
        <label for="password" class="label">Passwort *</label>
        <div class="relative">
          <input
            id="password"
            :value="form.password"
            @input="onPasswordInput"
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

        <!-- Password requirements -->
        <div class="mt-3 space-y-1.5">
          <p class="text-xs text-gray-500 font-medium">Passwort-Anforderungen:</p>
          <div class="grid grid-cols-2 gap-1">
            <div
              v-for="(check, key) in {
                length: 'Mind. 12 Zeichen',
                uppercase: 'Großbuchstabe',
                lowercase: 'Kleinbuchstabe',
                number: 'Zahl',
                special: 'Sonderzeichen',
              }"
              :key="key"
              class="flex items-center gap-1.5 text-xs"
              :class="passwordChecks[key] ? 'text-green-400' : 'text-gray-500'"
            >
              <CheckIcon v-if="passwordChecks[key]" class="w-3.5 h-3.5" />
              <XMarkIcon v-else class="w-3.5 h-3.5" />
              {{ check }}
            </div>
          </div>
        </div>
      </div>

      <!-- Password Confirmation -->
      <div>
        <label for="passwordConfirmation" class="label">Passwort bestätigen *</label>
        <div class="relative">
          <input
            id="passwordConfirmation"
            v-model="form.passwordConfirmation"
            :type="showPasswordConfirm ? 'text' : 'password'"
            autocomplete="new-password"
            required
            class="input pr-10"
            :class="{ 'input-error': errors.passwordConfirmation }"
            placeholder="••••••••••••"
          />
          <button
            type="button"
            @click="showPasswordConfirm = !showPasswordConfirm"
            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-300"
          >
            <EyeSlashIcon v-if="showPasswordConfirm" class="w-5 h-5" />
            <EyeIcon v-else class="w-5 h-5" />
          </button>
        </div>
        <p v-if="errors.passwordConfirmation" class="mt-1 text-sm text-red-400">{{ errors.passwordConfirmation }}</p>
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
          Registrieren...
        </span>
        <span v-else>Registrieren</span>
      </button>
    </form>

    <!-- Login link -->
    <p class="mt-6 text-center text-gray-400">
      Bereits ein Konto?
      <RouterLink to="/login" class="link font-medium">
        Anmelden
      </RouterLink>
    </p>
  </div>
</template>
