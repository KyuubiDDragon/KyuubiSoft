<script setup>
import { ref, computed } from 'vue'
import api from '@/core/api/axios'

const url = ref('')
const isLoading = ref(false)
const result = ref(null)
const error = ref('')
const activeTab = ref('preview')

async function analyze() {
  if (!url.value.trim()) return

  isLoading.value = true
  error.value = ''
  result.value = null

  try {
    const response = await api.get('/api/v1/tools/open-graph', {
      params: { url: url.value.trim() },
    })

    console.log('Open Graph response:', response.data)

    // Handle the response - data is nested under response.data.data
    if (response.data && response.data.data) {
      result.value = response.data.data
    } else if (response.data && !response.data.success) {
      error.value = response.data.error || response.data.message || 'Unbekannter Fehler'
    } else {
      error.value = 'Keine Daten erhalten'
    }
  } catch (e) {
    console.error('Open Graph error:', e)
    error.value = e.response?.data?.error || e.response?.data?.message || e.message || 'Open Graph Analyse fehlgeschlagen'
  }

  isLoading.value = false
}

const ogTitle = computed(() => {
  if (!result.value) return ''
  return result.value.openGraph?.title || result.value.basic?.title || 'Kein Titel'
})

const ogDescription = computed(() => {
  if (!result.value) return ''
  return result.value.openGraph?.description || result.value.basic?.description || 'Keine Beschreibung'
})

const ogImage = computed(() => {
  if (!result.value) return null
  return result.value.openGraph?.image || result.value.twitter?.image || null
})

const twitterCardType = computed(() => {
  if (!result.value?.twitter?.card) return 'summary'
  return result.value.twitter.card
})

const hostname = computed(() => {
  if (!result.value?.url) return ''
  try {
    return new URL(result.value.url).hostname
  } catch {
    return result.value.url
  }
})

const tabs = [
  { id: 'preview', name: 'Vorschau' },
  { id: 'og', name: 'Open Graph' },
  { id: 'twitter', name: 'Twitter' },
  { id: 'meta', name: 'Meta Tags' },
]

const quickUrls = ['github.com', 'youtube.com', 'twitter.com']
</script>

<template>
  <div class="space-y-4">
    <!-- Input -->
    <div class="flex gap-2">
      <input
        v-model="url"
        type="text"
        @keyup.enter="analyze"
        class="input flex-1"
        placeholder="https://example.com"
      />
      <button
        @click="analyze"
        :disabled="isLoading || !url.trim()"
        class="btn-primary px-6"
      >
        {{ isLoading ? 'Analysiere...' : 'Analysieren' }}
      </button>
    </div>

    <!-- Quick URLs -->
    <div class="flex gap-2 flex-wrap">
      <span class="text-xs text-gray-500">Beispiele:</span>
      <button
        v-for="u in quickUrls"
        :key="u"
        @click="url = u"
        class="text-xs text-primary-400 hover:text-primary-300"
      >
        {{ u }}
      </button>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="text-center py-8 text-gray-400">
      Analysiere Meta-Tags...
    </div>

    <!-- Error -->
    <div v-if="error" class="p-4 bg-red-900/30 border border-red-500/30 rounded-lg text-red-400 text-sm">
      {{ error }}
    </div>

    <!-- Results -->
    <div v-if="result" class="space-y-4">
      <!-- Tabs -->
      <div class="flex gap-1 bg-dark-800 rounded-lg p-1">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          @click="activeTab = tab.id"
          class="flex-1 px-3 py-2 rounded-md text-sm transition-colors"
          :class="activeTab === tab.id ? 'bg-primary-600 text-white' : 'text-gray-400 hover:text-white'"
        >
          {{ tab.name }}
        </button>
      </div>

      <!-- Preview Tab -->
      <div v-if="activeTab === 'preview'" class="space-y-4">
        <!-- Facebook/LinkedIn Style Preview -->
        <div class="bg-white rounded-lg overflow-hidden shadow-lg">
          <div v-if="ogImage" class="aspect-video bg-gray-200 relative">
            <img
              :src="ogImage"
              :alt="ogTitle"
              class="w-full h-full object-cover"
              @error="$event.target.style.display = 'none'"
            />
          </div>
          <div class="p-4 bg-gray-100">
            <div class="text-xs text-gray-500 uppercase tracking-wide">
              {{ hostname }}
            </div>
            <h3 class="text-gray-900 font-semibold mt-1 line-clamp-2">
              {{ ogTitle }}
            </h3>
            <p class="text-gray-600 text-sm mt-1 line-clamp-2">
              {{ ogDescription }}
            </p>
          </div>
        </div>

        <!-- Twitter Card Preview -->
        <div class="space-y-2">
          <h4 class="text-sm text-gray-400">Twitter Card ({{ twitterCardType }})</h4>
          <div class="bg-[#15202b] rounded-xl overflow-hidden border border-gray-700">
            <div
              v-if="ogImage && (twitterCardType === 'summary_large_image' || twitterCardType === 'player')"
              class="aspect-video bg-gray-800"
            >
              <img
                :src="ogImage"
                :alt="ogTitle"
                class="w-full h-full object-cover"
                @error="$event.target.style.display = 'none'"
              />
            </div>
            <div class="p-3 flex gap-3">
              <div
                v-if="ogImage && twitterCardType === 'summary'"
                class="w-24 h-24 bg-gray-800 rounded-lg shrink-0 overflow-hidden"
              >
                <img
                  :src="ogImage"
                  :alt="ogTitle"
                  class="w-full h-full object-cover"
                  @error="$event.target.style.display = 'none'"
                />
              </div>
              <div class="flex-1 min-w-0">
                <div class="text-gray-500 text-xs">
                  {{ hostname }}
                </div>
                <h3 class="text-white font-medium line-clamp-1">
                  {{ result.twitter?.title || ogTitle }}
                </h3>
                <p class="text-gray-400 text-sm line-clamp-2">
                  {{ result.twitter?.description || ogDescription }}
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Basic Info -->
        <div class="grid grid-cols-2 gap-3">
          <div class="p-3 bg-dark-700 rounded-lg">
            <span class="text-xs text-gray-500">Titel</span>
            <div class="text-white text-sm truncate">{{ result.basic?.title || '-' }}</div>
          </div>
          <div class="p-3 bg-dark-700 rounded-lg">
            <span class="text-xs text-gray-500">Sprache</span>
            <div class="text-white text-sm">{{ result.basic?.language || '-' }}</div>
          </div>
          <div class="p-3 bg-dark-700 rounded-lg col-span-2">
            <span class="text-xs text-gray-500">Beschreibung</span>
            <div class="text-white text-sm line-clamp-3">{{ result.basic?.description || '-' }}</div>
          </div>
          <div v-if="result.favicon" class="p-3 bg-dark-700 rounded-lg col-span-2 flex items-center gap-3">
            <img :src="result.favicon" class="w-6 h-6" @error="$event.target.style.display = 'none'" />
            <div>
              <span class="text-xs text-gray-500">Favicon</span>
              <div class="text-white text-xs truncate">{{ result.favicon }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Open Graph Tab -->
      <div v-if="activeTab === 'og'" class="space-y-2">
        <div v-if="Object.keys(result.openGraph).length === 0" class="text-center py-8 text-gray-500">
          Keine Open Graph Tags gefunden
        </div>
        <div
          v-for="(value, key) in result.openGraph"
          :key="key"
          class="p-3 bg-dark-700 rounded-lg"
        >
          <span class="text-xs text-primary-400 font-mono">og:{{ key }}</span>
          <div class="text-white text-sm mt-1 break-all">
            <template v-if="key === 'image' || key.includes('image')">
              <img :src="value" class="max-h-32 rounded mt-1" @error="$event.target.style.display = 'none'" />
              <div class="text-xs text-gray-400 mt-1">{{ value }}</div>
            </template>
            <template v-else>
              {{ value }}
            </template>
          </div>
        </div>
      </div>

      <!-- Twitter Tab -->
      <div v-if="activeTab === 'twitter'" class="space-y-2">
        <div v-if="Object.keys(result.twitter).length === 0" class="text-center py-8 text-gray-500">
          Keine Twitter Card Tags gefunden
        </div>
        <div
          v-for="(value, key) in result.twitter"
          :key="key"
          class="p-3 bg-dark-700 rounded-lg"
        >
          <span class="text-xs text-blue-400 font-mono">twitter:{{ key }}</span>
          <div class="text-white text-sm mt-1 break-all">
            <template v-if="key === 'image' || key.includes('image')">
              <img :src="value" class="max-h-32 rounded mt-1" @error="$event.target.style.display = 'none'" />
              <div class="text-xs text-gray-400 mt-1">{{ value }}</div>
            </template>
            <template v-else>
              {{ value }}
            </template>
          </div>
        </div>
      </div>

      <!-- Meta Tags Tab -->
      <div v-if="activeTab === 'meta'" class="space-y-2">
        <div class="max-h-96 overflow-y-auto space-y-2">
          <div
            v-for="(tag, i) in result.allMeta"
            :key="i"
            class="p-2 bg-dark-700 rounded-lg text-xs"
          >
            <template v-if="tag.name">
              <span class="text-gray-500">{{ tag.name }}:</span>
              <span class="text-white ml-2">{{ tag.content }}</span>
            </template>
            <template v-else-if="tag.charset">
              <span class="text-gray-500">charset:</span>
              <span class="text-white ml-2">{{ tag.charset }}</span>
            </template>
            <template v-else-if="tag['http-equiv']">
              <span class="text-gray-500">http-equiv ({{ tag['http-equiv'] }}):</span>
              <span class="text-white ml-2">{{ tag.content }}</span>
            </template>
          </div>
        </div>
      </div>
    </div>

    <!-- Info -->
    <div class="text-xs text-gray-500 space-y-1">
      <p><strong>Open Graph</strong> Tags bestimmen, wie Links in sozialen Medien angezeigt werden.</p>
      <p><strong>Twitter Cards</strong> ermöglichen reichhaltige Vorschauen auf Twitter.</p>
    </div>
  </div>
</template>
