<script setup>
import { ref, reactive } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { EyeIcon, EyeSlashIcon } from '@heroicons/vue/24/outline'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const uiStore = useUiStore()

const form = reactive({
  login: '',
  password: '',
  twoFactorCode: '',
})

const showPassword = ref(false)
const requires2FA = ref(false)
const errors = ref({})
const isLoading = ref(false)

async function handleSubmit() {
  errors.value = {}
  isLoading.value = true

  try {
    const result = await authStore.login({
      login: form.login,
      password: form.password,
      two_factor_code: form.twoFactorCode || undefined,
    })

    if (result.requires2FA) {
      requires2FA.value = true
      return
    }

    uiStore.showSuccess('Erfolgreich angemeldet!')

    // Redirect to intended page or dashboard
    const redirectTo = route.query.redirect || '/'
    router.push(redirectTo)
  } catch (error) {
    const message = error.response?.data?.error || 'Login fehlgeschlagen'
    errors.value.general = message
    uiStore.showError(message)
  } finally {
    isLoading.value = false
  }
}
</script>

<template>
  <div>
    <h2 class="text-2xl font-bold text-white mb-1.5">Anmelden</h2>
    <p class="text-sm text-gray-500 mb-6">Melde dich an, um fortzufahren</p>

    <form @submit.prevent="handleSubmit" class="space-y-5">
      <!-- General error -->
      <div
        v-if="errors.general"
        class="bg-red-500/10 border border-red-500/30 text-red-400 px-4 py-3 rounded-xl text-sm"
      >
        {{ errors.general }}
      </div>

      <!-- 2FA Notice -->
      <div
        v-if="requires2FA"
        class="bg-primary-500/10 border border-primary-500/30 text-primary-400 px-4 py-3 rounded-xl text-sm"
      >
        Bitte gib deinen 2FA-Code ein.
      </div>

      <!-- Login (E-Mail oder Benutzername) -->
      <div v-if="!requires2FA">
        <label for="login" class="label">E-Mail oder Benutzername</label>
        <input
          id="login"
          v-model="form.login"
          type="text"
          autocomplete="username"
          required
          class="input"
          :class="{ 'input-error': errors.login }"
          placeholder="E-Mail oder Benutzername"
        />
        <p v-if="errors.login" class="mt-1 text-sm text-red-400">{{ errors.login }}</p>
      </div>

      <!-- Password -->
      <div v-if="!requires2FA">
        <label for="password" class="label">Passwort</label>
        <div class="relative">
          <input
            id="password"
            v-model="form.password"
            :type="showPassword ? 'text' : 'password'"
            autocomplete="current-password"
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
      </div>

      <!-- 2FA Code -->
      <div v-if="requires2FA">
        <label for="twoFactorCode" class="label">2FA-Code</label>
        <input
          id="twoFactorCode"
          v-model="form.twoFactorCode"
          type="text"
          inputmode="numeric"
          pattern="[0-9]*"
          autocomplete="one-time-code"
          required
          class="input text-center tracking-widest text-lg"
          placeholder="000000"
          maxlength="6"
        />
      </div>

      <!-- Forgot password link -->
      <div v-if="!requires2FA" class="flex justify-end">
        <a href="#" class="text-sm link">Passwort vergessen?</a>
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
          Anmelden...
        </span>
        <span v-else>{{ requires2FA ? 'Bestätigen' : 'Anmelden' }}</span>
      </button>
    </form>

    <!-- Register link -->
    <p class="mt-6 text-center text-gray-400">
      Noch kein Konto?
      <RouterLink to="/register" class="link font-medium">
        Registrieren
      </RouterLink>
    </p>
  </div>
</template>
