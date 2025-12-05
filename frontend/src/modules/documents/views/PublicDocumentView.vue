<script setup>
import { ref, onMounted, defineAsyncComponent } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/core/api/axios'
import {
  DocumentTextIcon,
  LockClosedIcon,
  ExclamationCircleIcon,
  EyeIcon,
  CalendarIcon,
  UserIcon,
} from '@heroicons/vue/24/outline'

// Lazy load heavy editors for code
const MonacoEditor = defineAsyncComponent(() => import('@/components/MonacoEditor.vue'))

const route = useRoute()

// State
const document = ref(null)
const loading = ref(true)
const error = ref('')
const requiresPassword = ref(false)
const passwordInput = ref('')
const passwordError = ref('')

// Simple Markdown to HTML conversion
function renderMarkdown(content) {
  if (!content) return ''

  let html = content
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/^### (.*$)/gim, '<h3 class="text-lg font-semibold text-white mt-4 mb-2">$1</h3>')
    .replace(/^## (.*$)/gim, '<h2 class="text-xl font-semibold text-white mt-6 mb-3">$1</h2>')
    .replace(/^# (.*$)/gim, '<h1 class="text-2xl font-bold text-white mt-6 mb-4">$1</h1>')
    .replace(/\*\*\*(.*?)\*\*\*/gim, '<strong><em>$1</em></strong>')
    .replace(/\*\*(.*?)\*\*/gim, '<strong class="font-semibold text-white">$1</strong>')
    .replace(/\*(.*?)\*/gim, '<em class="italic">$1</em>')
    .replace(/```([\s\S]*?)```/gim, '<pre class="bg-dark-700 rounded-lg p-4 my-4 overflow-x-auto"><code>$1</code></pre>')
    .replace(/`(.*?)`/gim, '<code class="bg-dark-700 px-1.5 py-0.5 rounded text-primary-400">$1</code>')
    .replace(/^\- (.*$)/gim, '<li class="ml-4">$1</li>')
    .replace(/^\* (.*$)/gim, '<li class="ml-4">$1</li>')
    .replace(/^\d+\. (.*$)/gim, '<li class="ml-4 list-decimal">$1</li>')
    .replace(/\[(.*?)\]\((.*?)\)/gim, '<a href="$2" class="text-primary-400 hover:underline" target="_blank">$1</a>')
    .replace(/\n\n/gim, '</p><p class="text-gray-300 mb-4">')
    .replace(/\n/gim, '<br>')

  return `<p class="text-gray-300 mb-4">${html}</p>`
}

// Fetch document
async function fetchDocument(password = null) {
  loading.value = true
  error.value = ''
  passwordError.value = ''

  try {
    const token = route.params.token
    let response

    if (password) {
      response = await api.post(`/api/v1/documents/public/${token}`, { password })
    } else {
      response = await api.get(`/api/v1/documents/public/${token}`)
    }

    document.value = response.data.data
    requiresPassword.value = false
  } catch (err) {
    if (err.response?.status === 401 && err.response?.data?.data?.requires_password) {
      requiresPassword.value = true
      if (password) {
        passwordError.value = 'Falsches Passwort'
      }
    } else if (err.response?.status === 403) {
      error.value = err.response?.data?.message || 'Dieser Link ist abgelaufen'
    } else if (err.response?.status === 404) {
      error.value = 'Dokument nicht gefunden oder nicht öffentlich freigegeben'
    } else {
      error.value = 'Fehler beim Laden des Dokuments'
    }
  } finally {
    loading.value = false
  }
}

// Submit password
function submitPassword() {
  if (!passwordInput.value.trim()) {
    passwordError.value = 'Bitte Passwort eingeben'
    return
  }
  fetchDocument(passwordInput.value)
}

// Format date
function formatDate(dateStr) {
  if (!dateStr) return ''
  return new Date(dateStr).toLocaleDateString('de-DE', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

// Get format label
function getFormatLabel(format) {
  const labels = {
    richtext: 'Rich Text',
    markdown: 'Markdown',
    code: 'Code',
    spreadsheet: 'Tabelle',
  }
  return labels[format] || format
}

onMounted(() => {
  fetchDocument()
})
</script>

<template>
  <div class="min-h-screen bg-dark-900 py-8 px-4">
    <div class="max-w-4xl mx-auto">
      <!-- Loading -->
      <div v-if="loading" class="flex items-center justify-center py-20">
        <div class="w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full animate-spin"></div>
      </div>

      <!-- Error -->
      <div v-else-if="error" class="text-center py-20">
        <ExclamationCircleIcon class="w-16 h-16 text-red-400 mx-auto mb-4" />
        <h1 class="text-xl font-bold text-white mb-2">Dokument nicht verfügbar</h1>
        <p class="text-gray-400">{{ error }}</p>
      </div>

      <!-- Password Required -->
      <div v-else-if="requiresPassword" class="max-w-md mx-auto">
        <div class="bg-dark-800 border border-dark-700 rounded-xl p-8 text-center">
          <LockClosedIcon class="w-16 h-16 text-primary-400 mx-auto mb-4" />
          <h1 class="text-xl font-bold text-white mb-2">Passwortgeschützt</h1>
          <p class="text-gray-400 mb-6">Dieses Dokument ist mit einem Passwort geschützt.</p>

          <div class="space-y-4">
            <div>
              <input
                v-model="passwordInput"
                type="password"
                class="w-full bg-dark-700 border border-dark-600 rounded-lg px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:border-primary-500"
                placeholder="Passwort eingeben..."
                @keydown.enter="submitPassword"
              />
              <p v-if="passwordError" class="text-red-400 text-sm mt-2">{{ passwordError }}</p>
            </div>

            <button
              @click="submitPassword"
              class="w-full px-4 py-3 bg-primary-600 text-white rounded-lg hover:bg-primary-500 transition-colors"
            >
              Entsperren
            </button>
          </div>
        </div>
      </div>

      <!-- Document View -->
      <div v-else-if="document">
        <!-- Header -->
        <div class="bg-dark-800 border border-dark-700 rounded-xl p-6 mb-6">
          <div class="flex items-start gap-4">
            <div class="w-12 h-12 rounded-lg bg-primary-600/20 flex items-center justify-center flex-shrink-0">
              <DocumentTextIcon class="w-6 h-6 text-primary-400" />
            </div>
            <div class="flex-1 min-w-0">
              <h1 class="text-2xl font-bold text-white">{{ document.title }}</h1>
              <div class="flex flex-wrap items-center gap-4 mt-2 text-sm text-gray-400">
                <span class="flex items-center gap-1">
                  <UserIcon class="w-4 h-4" />
                  {{ document.owner_name || 'Unbekannt' }}
                </span>
                <span class="flex items-center gap-1">
                  <CalendarIcon class="w-4 h-4" />
                  {{ formatDate(document.updated_at) }}
                </span>
                <span class="flex items-center gap-1">
                  <EyeIcon class="w-4 h-4" />
                  {{ document.public_view_count }} Aufrufe
                </span>
                <span class="px-2 py-0.5 bg-primary-600/20 text-primary-400 rounded text-xs">
                  {{ getFormatLabel(document.format) }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Content -->
        <div class="bg-dark-800 border border-dark-700 rounded-xl overflow-hidden">
          <!-- Rich Text -->
          <div
            v-if="document.format === 'richtext' || !document.format"
            class="p-8 prose prose-invert max-w-none"
            v-html="document.content"
          ></div>

          <!-- Markdown -->
          <div
            v-else-if="document.format === 'markdown'"
            class="p-8 prose prose-invert max-w-none"
            v-html="renderMarkdown(document.content)"
          ></div>

          <!-- Code -->
          <div v-else-if="document.format === 'code'">
            <MonacoEditor
              :model-value="document.content"
              :read-only="true"
              height="600px"
            />
          </div>

          <!-- Spreadsheet - simplified view -->
          <div v-else-if="document.format === 'spreadsheet'" class="p-8">
            <p class="text-gray-400 text-center">
              Tabellenansicht ist in der öffentlichen Ansicht nicht verfügbar.
            </p>
          </div>

          <!-- Empty -->
          <div v-if="!document.content" class="p-8 text-center text-gray-400">
            Dieses Dokument hat keinen Inhalt.
          </div>
        </div>

        <!-- Footer -->
        <div class="mt-6 text-center text-sm text-gray-500">
          Geteilt über KyuubiSoft
        </div>
      </div>
    </div>
  </div>
</template>
