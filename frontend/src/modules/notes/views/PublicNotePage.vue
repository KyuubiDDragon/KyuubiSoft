<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/core/api/axios'

const route = useRoute()

const loading = ref(true)
const error = ref(null)
const note = ref(null)

// Get token from route
const token = computed(() => route.params.token)

// Load public note
async function loadNote() {
  if (!token.value) {
    error.value = 'Ungültiger Link'
    loading.value = false
    return
  }

  try {
    const response = await api.get(`/api/v1/public/notes/${token.value}`)
    note.value = response.data.data
  } catch (err) {
    console.error('Failed to load public note:', err)
    if (err.response?.status === 404) {
      error.value = 'Diese Seite existiert nicht oder wurde entfernt.'
    } else {
      error.value = 'Fehler beim Laden der Seite.'
    }
  } finally {
    loading.value = false
  }
}

// Format date
function formatDate(dateString) {
  if (!dateString) return ''
  return new Date(dateString).toLocaleDateString('de-DE', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  })
}

onMounted(() => {
  loadNote()
})
</script>

<template>
  <div class="min-h-screen bg-dark-900">
    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center min-h-screen">
      <div class="text-center">
        <svg class="w-12 h-12 mx-auto animate-spin text-primary-500" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
        </svg>
        <p class="mt-4 text-gray-400">Lädt...</p>
      </div>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="flex items-center justify-center min-h-screen">
      <div class="text-center max-w-md mx-auto px-4">
        <svg class="w-16 h-16 mx-auto text-gray-600 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <h1 class="text-2xl font-bold text-white mb-2">Seite nicht gefunden</h1>
        <p class="text-gray-400">{{ error }}</p>
      </div>
    </div>

    <!-- Note Content -->
    <article v-else-if="note" class="max-w-3xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
      <!-- Cover Image -->
      <div v-if="note.cover_image" class="mb-8 -mx-4 sm:mx-0">
        <img
          :src="note.cover_image"
          :alt="note.title"
          class="w-full h-48 sm:h-64 object-cover sm:rounded-xl"
        />
      </div>

      <!-- Header -->
      <header class="mb-8">
        <!-- Icon -->
        <div v-if="note.icon" class="text-5xl mb-4">
          {{ note.icon }}
        </div>

        <!-- Title -->
        <h1 class="text-4xl font-bold text-white mb-4">
          {{ note.title || 'Untitled' }}
        </h1>

        <!-- Meta -->
        <div v-if="note.show_date" class="flex items-center gap-4 text-sm text-gray-500">
          <span v-if="note.author">
            Von {{ note.author }}
          </span>
          <span v-if="note.updated_at">
            Aktualisiert am {{ formatDate(note.updated_at) }}
          </span>
        </div>
      </header>

      <!-- Content -->
      <div
        class="public-note-content prose prose-invert prose-lg max-w-none"
        v-html="note.content"
      />

      <!-- Footer -->
      <footer class="mt-16 pt-8 border-t border-dark-700">
        <div class="flex items-center justify-between text-sm text-gray-500">
          <span>
            {{ note.word_count || 0 }} Wörter
          </span>
          <a
            href="/"
            class="text-primary-400 hover:text-primary-300 transition-colors"
          >
            Erstellt mit KyuubiSoft
          </a>
        </div>
      </footer>
    </article>
  </div>
</template>

<style>
/* Public Note Content Styles */
.public-note-content {
  @apply text-gray-300 leading-relaxed;
}

.public-note-content h1 {
  @apply text-3xl font-bold text-white mt-8 mb-4;
}

.public-note-content h2 {
  @apply text-2xl font-semibold text-white mt-6 mb-3;
}

.public-note-content h3 {
  @apply text-xl font-semibold text-white mt-5 mb-2;
}

.public-note-content p {
  @apply mb-4;
}

.public-note-content a {
  @apply text-primary-400 hover:text-primary-300 underline;
}

.public-note-content ul {
  @apply list-disc ml-6 mb-4;
}

.public-note-content ol {
  @apply list-decimal ml-6 mb-4;
}

.public-note-content li {
  @apply mb-1;
}

.public-note-content blockquote {
  @apply border-l-4 border-primary-500 pl-4 italic text-gray-400 my-4;
}

.public-note-content code {
  @apply bg-dark-700 px-1.5 py-0.5 rounded text-primary-400 text-sm;
}

.public-note-content pre {
  @apply bg-dark-700 rounded-lg p-4 my-4 overflow-x-auto;
}

.public-note-content pre code {
  @apply bg-transparent p-0;
}

.public-note-content img {
  @apply max-w-full rounded-lg my-4;
}

.public-note-content hr {
  @apply border-dark-600 my-8;
}

.public-note-content table {
  @apply border-collapse w-full my-4;
}

.public-note-content th,
.public-note-content td {
  @apply border border-dark-600 p-3 text-left;
}

.public-note-content th {
  @apply bg-dark-700 font-semibold;
}

/* Callouts */
.public-note-content div[data-callout] {
  @apply rounded-lg p-4 my-4 border-l-4;
}

.public-note-content div[data-callout="info"] {
  @apply bg-blue-500/10 border-blue-500;
}

.public-note-content div[data-callout="warning"] {
  @apply bg-yellow-500/10 border-yellow-500;
}

.public-note-content div[data-callout="tip"] {
  @apply bg-green-500/10 border-green-500;
}

.public-note-content div[data-callout="danger"] {
  @apply bg-red-500/10 border-red-500;
}

/* Task Lists */
.public-note-content ul[data-type="taskList"] {
  @apply list-none ml-0;
}

.public-note-content ul[data-type="taskList"] li {
  @apply flex items-start gap-2;
}

.public-note-content ul[data-type="taskList"] li[data-checked="true"] {
  @apply line-through text-gray-500;
}

/* Toggle/Collapsible */
.public-note-content div[data-toggle] {
  @apply border border-dark-600 rounded-lg my-4 overflow-hidden;
}

.public-note-content div[data-toggle-title] {
  @apply px-4 py-3 bg-dark-700 font-medium cursor-pointer;
}

.public-note-content div[data-toggle-content] {
  @apply px-4 py-3;
}

/* Embeds */
.public-note-content div[data-embed] {
  @apply my-6;
}

.public-note-content div[data-embed] iframe {
  @apply w-full rounded-lg;
}
</style>
