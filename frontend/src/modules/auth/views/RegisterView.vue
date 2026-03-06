<script setup>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { EyeIcon, EyeSlashIcon, CheckIcon, XMarkIcon, ClockIcon } from '@heroicons/vue/24/outline'

const { t } = useI18n()
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
const pendingApproval = ref(false)

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
    errors.value.email = t('authModule.emailRequired')
    return
  }

  if (!isPasswordStrong()) {
    errors.value.password = t('authModule.passwordRequirementsNotMet')
    return
  }

  if (form.password !== form.passwordConfirmation) {
    errors.value.passwordConfirmation = t('authModule.passwordsDoNotMatch')
    return
  }

  isLoading.value = true

  try {
    const result = await authStore.register({
      email: form.email,
      username: form.username || undefined,
      password: form.password,
      password_confirmation: form.passwordConfirmation,
    })

    if (result.pendingApproval) {
      pendingApproval.value = true
      uiStore.showSuccess(t('authModule.registrationSuccess'))
    } else {
      uiStore.showSuccess(t('authModule.registrationSuccess'))
      router.push('/')
    }
  } catch (error) {
    const message = error.response?.data?.error || t('authModule.registrationFailed')
    errors.value.general = message
    uiStore.showError(message)
  } finally {
    isLoading.value = false
  }
}
</script>

<template>
  <div>
    <!-- Pending Approval Message -->
    <div v-if="pendingApproval" class="text-center">
      <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-yellow-500/10 mb-4">
        <ClockIcon class="w-8 h-8 text-yellow-400" />
      </div>
      <h2 class="text-2xl font-bold text-white mb-2">{{ $t('authModule.registrationSuccess') }}</h2>
      <p class="text-gray-400 mb-6">
        {{ $t('authModule.pendingApprovalMessage') }}
      </p>
      <div class="bg-white/[0.02] rounded-lg p-4 text-left">
        <p class="text-sm text-gray-400">
          <span class="font-medium text-gray-300">{{ $t('authModule.whatHappensNext') }}</span><br>
          {{ $t('authModule.adminWillReview') }}
        </p>
      </div>
      <RouterLink to="/login" class="btn-primary w-full mt-6 inline-block text-center">
        {{ $t('authModule.backToLogin') }}
      </RouterLink>
    </div>

    <!-- Registration Form -->
    <template v-else>
      <h2 class="text-2xl font-bold text-white mb-2">{{ $t('auth.register') }}</h2>
      <p class="text-gray-400 mb-6">{{ $t('authModule.createAccount') }}</p>

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
        <label for="email" class="label">{{ $t('auth.email') }} *</label>
        <input
          id="email"
          v-model="form.email"
          type="email"
          autocomplete="email"
          required
          class="input"
          :class="{ 'input-error': errors.email }"
          :placeholder="$t('authModule.emailPlaceholder')"
        />
        <p v-if="errors.email" class="mt-1 text-sm text-red-400">{{ errors.email }}</p>
      </div>

      <!-- Username -->
      <div>
        <label for="username" class="label">{{ $t('auth.username') }} ({{ $t('common.optional') }})</label>
        <input
          id="username"
          v-model="form.username"
          type="text"
          autocomplete="username"
          class="input"
          :placeholder="$t('authModule.usernamePlaceholder')"
        />
      </div>

      <!-- Password -->
      <div>
        <label for="password" class="label">{{ $t('auth.password') }} *</label>
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
          <p class="text-xs text-gray-500 font-medium">{{ $t('authModule.passwordRequirements') }}</p>
          <div class="grid grid-cols-2 gap-1">
            <div
              v-for="(check, key) in {
                length: $t('authModule.pwMinLength'),
                uppercase: $t('authModule.pwUppercase'),
                lowercase: $t('authModule.pwLowercase'),
                number: $t('authModule.pwNumber'),
                special: $t('authModule.pwSpecial'),
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
        <label for="passwordConfirmation" class="label">{{ $t('auth.confirmPassword') }} *</label>
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
          {{ $t('authModule.registering') }}
        </span>
        <span v-else>{{ $t('auth.register') }}</span>
      </button>
    </form>

    <!-- Login link -->
    <p class="mt-6 text-center text-gray-400">
      {{ $t('authModule.alreadyHaveAccount') }}
      <RouterLink to="/login" class="link font-medium">
        {{ $t('auth.login') }}
      </RouterLink>
    </p>
    </template>
  </div>
</template>
