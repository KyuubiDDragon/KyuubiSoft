<template>
  <div class="short-link-redirect">
    <!-- Loading State -->
    <div v-if="loading" class="redirect-container">
      <div class="loading-spinner"></div>
      <p class="loading-text">Redirecting...</p>
    </div>

    <!-- Password Required -->
    <div v-else-if="requiresPassword" class="redirect-container">
      <div class="password-form">
        <h2>Password Protected Link</h2>
        <p v-if="linkTitle" class="link-title">{{ linkTitle }}</p>
        <form @submit.prevent="submitPassword">
          <div class="form-group">
            <label for="password">Enter Password</label>
            <input
              type="password"
              id="password"
              v-model="password"
              placeholder="Password"
              autofocus
            />
          </div>
          <p v-if="error" class="error-message">{{ error }}</p>
          <button type="submit" :disabled="submitting">
            {{ submitting ? 'Verifying...' : 'Continue' }}
          </button>
        </form>
      </div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="redirect-container">
      <div class="error-container">
        <h2>Link Error</h2>
        <p class="error-message">{{ error }}</p>
        <a href="/" class="back-link">Go to Homepage</a>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/core/api/axios'

const route = useRoute()

const loading = ref(true)
const requiresPassword = ref(false)
const linkTitle = ref('')
const password = ref('')
const error = ref('')
const submitting = ref(false)

const code = route.params.code

onMounted(async () => {
  await checkLink()
})

async function checkLink() {
  try {
    // First check if link exists and if password is required
    const response = await api.get(`/api/v1/s/${code}/info`)
    const data = response.data.data

    linkTitle.value = data.title || ''

    if (data.requires_password) {
      requiresPassword.value = true
      loading.value = false
    } else if (data.original_url) {
      // No password required - redirect directly to target URL
      window.location.href = data.original_url
    } else {
      // Fallback: redirect via backend (shouldn't happen)
      window.location.href = `/api/v1/s/${code}`
    }
  } catch (err) {
    loading.value = false
    if (err.response?.status === 404) {
      error.value = 'Link not found'
    } else if (err.response?.status === 410) {
      error.value = err.response.data.message || 'Link has expired or reached its limit'
    } else {
      error.value = 'An error occurred while loading the link'
    }
  }
}

async function submitPassword() {
  if (!password.value) {
    error.value = 'Please enter the password'
    return
  }

  submitting.value = true
  error.value = ''

  try {
    // Verify password and get original URL
    const response = await api.post(`/api/v1/s/${code}`, {
      password: password.value
    })

    // Redirect to the original URL
    const originalUrl = response.data.data?.original_url
    if (originalUrl) {
      window.location.href = originalUrl
    } else {
      error.value = 'Could not get redirect URL'
      submitting.value = false
    }
  } catch (err) {
    submitting.value = false
    if (err.response?.status === 401) {
      error.value = 'Invalid password'
    } else if (err.response?.status === 404) {
      error.value = 'Link not found'
    } else if (err.response?.status === 410) {
      error.value = err.response.data.message || 'Link has expired'
    } else {
      error.value = 'An error occurred'
    }
  }
}
</script>

<style scoped>
.short-link-redirect {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.redirect-container {
  text-align: center;
  padding: 2rem;
}

.loading-spinner {
  width: 50px;
  height: 50px;
  border: 4px solid rgba(255, 255, 255, 0.1);
  border-top-color: #4f46e5;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin: 0 auto 1rem;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.loading-text {
  color: #94a3b8;
  font-size: 1.1rem;
}

.password-form {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 12px;
  padding: 2rem;
  max-width: 400px;
  width: 100%;
}

.password-form h2 {
  color: #fff;
  margin: 0 0 0.5rem;
  font-size: 1.5rem;
}

.link-title {
  color: #94a3b8;
  margin-bottom: 1.5rem;
}

.form-group {
  margin-bottom: 1rem;
  text-align: left;
}

.form-group label {
  display: block;
  color: #94a3b8;
  margin-bottom: 0.5rem;
  font-size: 0.9rem;
}

.form-group input {
  width: 100%;
  padding: 0.75rem 1rem;
  background: rgba(0, 0, 0, 0.3);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 8px;
  color: #fff;
  font-size: 1rem;
  box-sizing: border-box;
}

.form-group input:focus {
  outline: none;
  border-color: #4f46e5;
}

.password-form button {
  width: 100%;
  padding: 0.75rem 1.5rem;
  background: #4f46e5;
  color: #fff;
  border: none;
  border-radius: 8px;
  font-size: 1rem;
  cursor: pointer;
  transition: background 0.2s;
  margin-top: 0.5rem;
}

.password-form button:hover:not(:disabled) {
  background: #4338ca;
}

.password-form button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.error-container {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(239, 68, 68, 0.3);
  border-radius: 12px;
  padding: 2rem;
  max-width: 400px;
}

.error-container h2 {
  color: #fff;
  margin: 0 0 1rem;
}

.error-message {
  color: #f87171;
  margin: 0.5rem 0;
  font-size: 0.9rem;
}

.back-link {
  display: inline-block;
  margin-top: 1rem;
  color: #4f46e5;
  text-decoration: none;
}

.back-link:hover {
  text-decoration: underline;
}
</style>
