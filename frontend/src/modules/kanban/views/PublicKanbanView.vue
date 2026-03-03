<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'
import {
  LockClosedIcon,
  ViewColumnsIcon,
  UserIcon,
} from '@heroicons/vue/24/outline'

const route = useRoute()
const token = computed(() => route.params.token)

// State
const state = ref('loading') // loading | auth | view | error
const board = ref(null)
const username = ref('')
const password = ref('')
const authError = ref('')
const errorMessage = ref('')
const isSubmitting = ref(false)

// API base
const apiBase = window.location.origin + '/api/v1'

onMounted(async () => {
  await loadBoard()
})

async function loadBoard(user, pw) {
  state.value = 'loading'
  authError.value = ''
  try {
    const config = {}
    if (user && pw) {
      config.method = 'post'
      config.url = `${apiBase}/kanban/public/${token.value}`
      config.data = { username: user, password: pw }
    } else {
      config.method = 'get'
      config.url = `${apiBase}/kanban/public/${token.value}`
    }
    const res = await axios(config)
    const resData = res.data.data

    if (resData.requires_auth) {
      state.value = 'auth'
      return
    }

    board.value = resData
    state.value = 'view'
  } catch (e) {
    const status = e.response?.status
    const msg = e.response?.data?.message || ''
    if (status === 401) {
      if (user) {
        authError.value = 'Ungültiger Benutzername oder Passwort'
      }
      state.value = 'auth'
    } else if (status === 403) {
      state.value = 'error'
      errorMessage.value = msg || 'Dieser Link ist abgelaufen'
    } else if (status === 404) {
      state.value = 'error'
      errorMessage.value = 'Board nicht gefunden'
    } else {
      state.value = 'error'
      errorMessage.value = msg || 'Ein Fehler ist aufgetreten'
    }
  }
}

async function submitAuth() {
  if (!username.value || !password.value) return
  isSubmitting.value = true
  await loadBoard(username.value, password.value)
  isSubmitting.value = false
}

function getAttachmentUrl(filename) {
  return `/api/v1/kanban/attachments/${filename}`
}

function getPriorityColor(priority) {
  const colors = {
    low: 'bg-gray-500',
    medium: 'bg-blue-500',
    high: 'bg-orange-500',
    urgent: 'bg-red-500',
  }
  return colors[priority] || 'bg-gray-500'
}

function getPriorityLabel(priority) {
  const labels = {
    low: 'Niedrig',
    medium: 'Mittel',
    high: 'Hoch',
    urgent: 'Dringend',
  }
  return labels[priority] || priority
}

function getLabelColorClass(label) {
  const colors = {
    red: 'bg-red-500',
    orange: 'bg-orange-500',
    yellow: 'bg-yellow-500',
    green: 'bg-green-500',
    blue: 'bg-blue-500',
    purple: 'bg-purple-500',
    pink: 'bg-pink-500',
  }
  return colors[label] || 'bg-gray-500'
}

function formatDate(dateStr) {
  if (!dateStr) return ''
  return new Date(dateStr).toLocaleDateString('de-DE', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  })
}
</script>

<template>
  <div class="min-h-screen bg-dark-900">
    <!-- Loading -->
    <div v-if="state === 'loading'" class="flex items-center justify-center min-h-screen">
      <div class="text-center">
        <div class="w-12 h-12 mx-auto border-4 border-primary-500 border-t-transparent rounded-full animate-spin"></div>
        <p class="mt-4 text-gray-400">Lädt...</p>
      </div>
    </div>

    <!-- Auth Form -->
    <div v-else-if="state === 'auth'" class="flex items-center justify-center min-h-screen px-4">
      <div class="bg-dark-800 border border-dark-700 rounded-xl p-8 w-full max-w-sm">
        <div class="text-center mb-6">
          <div class="w-16 h-16 mx-auto bg-primary-600/20 rounded-full flex items-center justify-center mb-4">
            <LockClosedIcon class="w-8 h-8 text-primary-400" />
          </div>
          <h1 class="text-xl font-bold text-white">Kanban Board</h1>
          <p class="text-gray-400 text-sm mt-1">Bitte anmelden um das Board zu sehen</p>
        </div>

        <form @submit.prevent="submitAuth" class="space-y-4">
          <div>
            <label class="block text-sm text-gray-400 mb-1">Benutzername</label>
            <div class="relative">
              <UserIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-500" />
              <input
                v-model="username"
                type="text"
                placeholder="Benutzername"
                class="w-full bg-dark-700 border border-dark-600 rounded-lg pl-10 pr-3 py-2.5 text-white placeholder-gray-500 focus:outline-none focus:border-primary-500"
                autocomplete="username"
              />
            </div>
          </div>

          <div>
            <label class="block text-sm text-gray-400 mb-1">Passwort</label>
            <div class="relative">
              <LockClosedIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-500" />
              <input
                v-model="password"
                type="password"
                placeholder="Passwort"
                class="w-full bg-dark-700 border border-dark-600 rounded-lg pl-10 pr-3 py-2.5 text-white placeholder-gray-500 focus:outline-none focus:border-primary-500"
                autocomplete="current-password"
              />
            </div>
          </div>

          <p v-if="authError" class="text-red-400 text-sm">{{ authError }}</p>

          <button
            type="submit"
            :disabled="isSubmitting || !username || !password"
            class="w-full py-2.5 bg-primary-600 text-white rounded-lg hover:bg-primary-500 transition-colors disabled:opacity-50 disabled:cursor-not-allowed font-medium"
          >
            {{ isSubmitting ? 'Prüfe...' : 'Anmelden' }}
          </button>
        </form>
      </div>
    </div>

    <!-- Error -->
    <div v-else-if="state === 'error'" class="flex items-center justify-center min-h-screen px-4">
      <div class="text-center max-w-md">
        <div class="w-16 h-16 mx-auto bg-red-500/20 rounded-full flex items-center justify-center mb-4">
          <svg class="w-8 h-8 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
          </svg>
        </div>
        <h1 class="text-2xl font-bold text-white mb-2">Zugriff nicht möglich</h1>
        <p class="text-gray-400">{{ errorMessage }}</p>
      </div>
    </div>

    <!-- Board View -->
    <div v-else-if="state === 'view' && board" class="p-4 sm:p-6">
      <!-- Header -->
      <div class="mb-6">
        <div class="flex items-center gap-3 mb-2">
          <div
            class="w-10 h-10 rounded-lg flex items-center justify-center"
            :style="{ backgroundColor: board.color || '#6366f1' }"
          >
            <ViewColumnsIcon class="w-6 h-6 text-white" />
          </div>
          <div>
            <h1 class="text-2xl font-bold text-white">{{ board.title }}</h1>
            <p v-if="board.description" class="text-gray-400 text-sm">{{ board.description }}</p>
          </div>
        </div>
      </div>

      <!-- Columns -->
      <div class="flex gap-4 overflow-x-auto pb-4" style="min-height: calc(100vh - 150px)">
        <div
          v-for="column in board.columns"
          :key="column.id"
          class="flex-shrink-0 w-80 bg-white/[0.04] rounded-xl border border-white/[0.06]"
        >
          <!-- Column Header -->
          <div class="p-3 border-b border-white/[0.06]">
            <div class="flex items-center gap-2">
              <div
                class="w-3 h-3 rounded-full"
                :style="{ backgroundColor: column.color || '#3B82F6' }"
              ></div>
              <h3 class="font-semibold text-white">{{ column.title }}</h3>
              <span class="text-xs text-gray-500 bg-white/[0.04] px-2 py-0.5 rounded-full">
                {{ column.cards?.length || 0 }}
              </span>
            </div>
          </div>

          <!-- Cards -->
          <div class="p-2 space-y-2 max-h-[calc(100vh-250px)] overflow-y-auto">
            <div
              v-for="card in column.cards"
              :key="card.id"
              class="bg-white/[0.04] rounded-lg p-3"
              :class="{ 'border-l-4': card.color }"
              :style="card.color ? { borderLeftColor: card.color } : {}"
            >
              <!-- Labels -->
              <div v-if="card.labels?.length" class="flex flex-wrap gap-1 mb-2">
                <span
                  v-for="label in card.labels"
                  :key="label"
                  class="w-8 h-1.5 rounded-full"
                  :class="getLabelColorClass(label)"
                ></span>
              </div>

              <!-- Tags -->
              <div v-if="card.tags?.length" class="flex flex-wrap gap-1 mb-2">
                <span
                  v-for="tag in card.tags"
                  :key="tag.id"
                  class="px-2 py-0.5 rounded-full text-xs font-medium"
                  :style="{
                    backgroundColor: tag.color + '20',
                    color: tag.color,
                    border: '1px solid ' + tag.color + '40'
                  }"
                >
                  {{ tag.name }}
                </span>
              </div>

              <!-- Title -->
              <p class="text-white text-sm font-medium mb-1">{{ card.title }}</p>

              <!-- Description preview -->
              <p v-if="card.description" class="text-gray-500 text-xs line-clamp-2 mb-2">
                {{ card.description }}
              </p>

              <!-- Attachment thumbnails -->
              <div v-if="card.attachments?.length" class="flex gap-1 mb-2">
                <img
                  v-for="(att, i) in card.attachments.slice(0, 3)"
                  :key="att.id"
                  :src="getAttachmentUrl(att.filename)"
                  class="w-12 h-12 rounded object-cover"
                />
                <span
                  v-if="card.attachments.length > 3"
                  class="w-12 h-12 rounded bg-white/[0.04] flex items-center justify-center text-xs text-gray-400"
                >
                  +{{ card.attachments.length - 3 }}
                </span>
              </div>

              <!-- Meta -->
              <div class="flex items-center gap-2 flex-wrap">
                <span
                  v-if="card.priority && card.priority !== 'medium'"
                  class="text-xs px-1.5 py-0.5 rounded text-white"
                  :class="getPriorityColor(card.priority)"
                >
                  {{ getPriorityLabel(card.priority) }}
                </span>
                <span v-if="card.due_date" class="text-xs text-gray-500">
                  {{ formatDate(card.due_date) }}
                </span>
                <span v-if="card.assignee_name" class="text-xs text-gray-500">
                  {{ card.assignee_name }}
                </span>
              </div>
            </div>

            <!-- Empty column -->
            <div v-if="!column.cards?.length" class="text-center py-8 text-gray-600 text-sm">
              Keine Karten
            </div>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="mt-4 text-center text-sm text-gray-600">
        Erstellt mit KyuubiSoft
      </div>
    </div>
  </div>
</template>
