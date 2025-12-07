<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import {
  NewspaperIcon,
  RssIcon,
  BookmarkIcon,
  CheckIcon,
  ArrowPathIcon,
  PlusIcon,
  AdjustmentsHorizontalIcon,
  XMarkIcon,
  GlobeAltIcon,
  CodeBracketIcon,
  ShieldCheckIcon,
  CpuChipIcon,
  FolderIcon,
  ArrowTopRightOnSquareIcon,
  ClockIcon,
  FunnelIcon,
  Cog6ToothIcon,
  Squares2X2Icon,
  ListBulletIcon,
  ChevronDownIcon,
  ChevronUpIcon,
  ComputerDesktopIcon,
  DevicePhoneMobileIcon,
  SparklesIcon,
  BeakerIcon,
  FilmIcon,
  BriefcaseIcon,
  WrenchScrewdriverIcon,
  PlayIcon,
} from '@heroicons/vue/24/outline'
import { BookmarkIcon as BookmarkIconSolid } from '@heroicons/vue/24/solid'
import api from '@/core/api/axios'
import { useUiStore } from '@/stores/ui'

const uiStore = useUiStore()

// State
const feeds = ref([])
const groupedFeeds = ref({})
const categories = ref({})
const newsItems = ref([])
const isLoading = ref(true)
const isRefreshing = ref(false)
const totalItems = ref(0)

// Filters
const selectedCategory = ref(null)
const selectedFeed = ref(null)
const showOnlySaved = ref(false)
const showOnlyUnread = ref(false)

// Settings modal
const showSettingsModal = ref(false)
const showAddFeedModal = ref(false)
const newFeedForm = ref({
  url: '',
  name: '',
  category: 'other',
})

// Article modal
const selectedArticle = ref(null)

// View mode
const viewMode = ref('list') // 'grid' or 'list'

// Expanded articles (for list view)
const expandedArticles = ref(new Set())
const loadingFullContent = ref(new Set())
const fullContentCache = ref({})

// Category icons
const categoryIcons = {
  tech: CpuChipIcon,
  gaming: PlayIcon,
  general: GlobeAltIcon,
  dev: CodeBracketIcon,
  security: ShieldCheckIcon,
  hardware: WrenchScrewdriverIcon,
  software: ComputerDesktopIcon,
  mobile: DevicePhoneMobileIcon,
  ai: SparklesIcon,
  science: BeakerIcon,
  entertainment: FilmIcon,
  business: BriefcaseIcon,
  other: FolderIcon,
}

// Category names
const categoryNames = {
  tech: 'Technologie',
  gaming: 'Gaming',
  general: 'Allgemein',
  dev: 'Development',
  security: 'Security',
  hardware: 'Hardware',
  software: 'Software',
  mobile: 'Mobile',
  ai: 'KI / AI',
  science: 'Wissenschaft',
  entertainment: 'Entertainment',
  business: 'Business',
  other: 'Sonstiges',
}

// Computed
const subscribedFeeds = computed(() => feeds.value.filter(f => f.is_subscribed == 1))

// Methods
async function loadFeeds() {
  try {
    const response = await api.get('/api/v1/news/feeds')
    feeds.value = response.data.data.feeds || []
    groupedFeeds.value = response.data.data.grouped || {}
    categories.value = response.data.data.categories || {}
  } catch (error) {
    console.error('Error loading feeds:', error)
  }
}

async function loadNews() {
  isLoading.value = true
  try {
    const params = { limit: 50 }
    if (selectedCategory.value) params.category = selectedCategory.value
    if (selectedFeed.value) params.feed_id = selectedFeed.value
    if (showOnlySaved.value) params.saved = '1'
    if (showOnlyUnread.value) params.unread = '1'

    const response = await api.get('/api/v1/news', { params })
    newsItems.value = response.data.data.items || []
    totalItems.value = response.data.data.total || 0
  } catch (error) {
    uiStore.showError('Fehler beim Laden der News')
  } finally {
    isLoading.value = false
  }
}

async function refreshFeeds(silent = false) {
  isRefreshing.value = true
  try {
    const response = await api.post('/api/v1/news/refresh')
    const results = response.data.data.results || {}
    await loadNews()

    if (!silent) {
      // Count successes and failures
      const entries = Object.entries(results)
      const successes = entries.filter(([_, r]) => r.success).length
      const failures = entries.filter(([_, r]) => !r.success)
      const totalNew = entries.reduce((sum, [_, r]) => sum + (r.new_items || 0), 0)

      if (failures.length > 0) {
        const failedNames = failures.map(([name, r]) => `${name}: ${r.error}`).join(', ')
        uiStore.showError(`Fehler bei: ${failedNames}`)
      } else if (totalNew > 0) {
        uiStore.showSuccess(`${totalNew} neue Artikel geladen`)
      } else if (successes > 0) {
        uiStore.showSuccess('Feeds aktualisiert, keine neuen Artikel')
      } else {
        uiStore.showSuccess('Keine Feeds zum Aktualisieren')
      }
    }
  } catch (error) {
    if (!silent) {
      uiStore.showError('Fehler beim Aktualisieren: ' + (error.response?.data?.message || error.message))
    }
  } finally {
    isRefreshing.value = false
  }
}

async function toggleSubscription(feed) {
  try {
    if (feed.is_subscribed == 1) {
      await api.delete(`/api/v1/news/feeds/${feed.id}/subscribe`)
      feed.is_subscribed = 0
    } else {
      await api.post(`/api/v1/news/feeds/${feed.id}/subscribe`)
      feed.is_subscribed = 1
    }
  } catch (error) {
    uiStore.showError('Fehler beim Ändern des Abonnements')
  }
}

async function toggleSaved(item) {
  try {
    const response = await api.post(`/api/v1/news/items/${item.id}/save`)
    item.is_saved = response.data.data.is_saved ? 1 : 0
  } catch (error) {
    uiStore.showError('Fehler beim Speichern')
  }
}

async function markAsRead(item) {
  if (item.is_read) return
  try {
    await api.post(`/api/v1/news/items/${item.id}/read`)
    item.is_read = 1
  } catch (error) {
    console.error('Error marking as read:', error)
  }
}

async function addFeed() {
  if (!newFeedForm.value.url) {
    uiStore.showError('URL ist erforderlich')
    return
  }

  try {
    await api.post('/api/v1/news/feeds', newFeedForm.value)
    uiStore.showSuccess('Feed hinzugefügt')
    showAddFeedModal.value = false
    newFeedForm.value = { url: '', name: '', category: 'other' }
    await loadFeeds()
    await loadNews()
  } catch (error) {
    uiStore.showError(error.response?.data?.message || 'Fehler beim Hinzufügen')
  }
}

function openArticle(item) {
  markAsRead(item)
  selectedArticle.value = item
}

function openExternalLink(url) {
  window.open(url, '_blank', 'noopener,noreferrer')
}

async function toggleExpanded(item) {
  markAsRead(item)
  if (expandedArticles.value.has(item.id)) {
    expandedArticles.value.delete(item.id)
  } else {
    expandedArticles.value.add(item.id)
    // Fetch full content if not cached
    if (!fullContentCache.value[item.id]) {
      await fetchFullContent(item)
    }
  }
  // Trigger reactivity
  expandedArticles.value = new Set(expandedArticles.value)
}

async function fetchFullContent(item) {
  if (loadingFullContent.value.has(item.id)) return

  loadingFullContent.value.add(item.id)
  loadingFullContent.value = new Set(loadingFullContent.value)

  try {
    const response = await api.get(`/api/v1/news/items/${item.id}/full-content`)
    fullContentCache.value[item.id] = response.data.data.content
  } catch (error) {
    console.error('Error fetching full content:', error)
    // Use existing content as fallback
    fullContentCache.value[item.id] = item.content || item.description
  } finally {
    loadingFullContent.value.delete(item.id)
    loadingFullContent.value = new Set(loadingFullContent.value)
  }
}

function isExpanded(item) {
  return expandedArticles.value.has(item.id)
}

function isLoadingContent(item) {
  return loadingFullContent.value.has(item.id)
}

function getFullContent(item) {
  return fullContentCache.value[item.id] || item.content || item.description || 'Kein Inhalt verfügbar'
}

function formatDate(dateStr) {
  if (!dateStr) return ''
  const date = new Date(dateStr)
  const now = new Date()
  const diffMs = now - date
  const diffMins = Math.floor(diffMs / 60000)
  const diffHours = Math.floor(diffMs / 3600000)
  const diffDays = Math.floor(diffMs / 86400000)

  if (diffMins < 60) return `vor ${diffMins} Min.`
  if (diffHours < 24) return `vor ${diffHours} Std.`
  if (diffDays < 7) return `vor ${diffDays} Tagen`

  return date.toLocaleDateString('de-DE', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  })
}

function stripHtml(html, maxLength = 500) {
  if (!html) return ''
  // Remove HTML tags and decode common entities
  let text = html
    .replace(/<[^>]*>/g, '')
    .replace(/&nbsp;/g, ' ')
    .replace(/&amp;/g, '&')
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&quot;/g, '"')
    .replace(/&#39;/g, "'")
    .replace(/\s+/g, ' ')
    .trim()
  return text.length > maxLength ? text.substring(0, maxLength) + '...' : text
}

// Watch filters
watch([selectedCategory, selectedFeed, showOnlySaved, showOnlyUnread], () => {
  loadNews()
})

// Lifecycle
onMounted(async () => {
  await loadFeeds()
  await loadNews()

  // Auto-fetch if no items and there are subscribed feeds
  if (newsItems.value.length === 0 && subscribedFeeds.value.length > 0) {
    await refreshFeeds(false) // Show feedback for initial load
  }
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-white flex items-center gap-3">
          <NewspaperIcon class="w-8 h-8 text-primary-400" />
          News
        </h1>
        <p class="text-gray-400 mt-1">Bleib auf dem Laufenden mit deinen Lieblingsquellen</p>
      </div>
      <div class="flex items-center gap-3">
        <button
          @click="refreshFeeds"
          :disabled="isRefreshing"
          class="btn-secondary flex items-center gap-2"
        >
          <ArrowPathIcon class="w-5 h-5" :class="{ 'animate-spin': isRefreshing }" />
          Aktualisieren
        </button>
        <button @click="showSettingsModal = true" class="btn-secondary">
          <Cog6ToothIcon class="w-5 h-5" />
        </button>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap items-center gap-3">
      <!-- Category Filter -->
      <div class="flex items-center gap-2 bg-dark-800 rounded-lg p-1">
        <button
          @click="selectedCategory = null; selectedFeed = null"
          class="px-3 py-1.5 rounded-md text-sm transition-colors"
          :class="!selectedCategory ? 'bg-primary-600 text-white' : 'text-gray-400 hover:text-white'"
        >
          Alle
        </button>
        <button
          v-for="(name, key) in categoryNames"
          :key="key"
          @click="selectedCategory = key; selectedFeed = null"
          class="px-3 py-1.5 rounded-md text-sm transition-colors flex items-center gap-1"
          :class="selectedCategory === key ? 'bg-primary-600 text-white' : 'text-gray-400 hover:text-white'"
        >
          <component :is="categoryIcons[key]" class="w-4 h-4" />
          {{ name }}
        </button>
      </div>

      <!-- Feed Filter (if category selected) -->
      <select
        v-if="selectedCategory && groupedFeeds[selectedCategory]?.length"
        v-model="selectedFeed"
        class="bg-dark-800 border border-dark-700 rounded-lg px-3 py-2 text-sm text-white"
      >
        <option :value="null">Alle Feeds</option>
        <option
          v-for="feed in groupedFeeds[selectedCategory].filter(f => f.is_subscribed == 1)"
          :key="feed.id"
          :value="feed.id"
        >
          {{ feed.name }}
        </option>
      </select>

      <div class="flex-1"></div>

      <!-- Quick Filters -->
      <button
        @click="showOnlyUnread = !showOnlyUnread"
        class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm transition-colors"
        :class="showOnlyUnread ? 'bg-blue-600 text-white' : 'bg-dark-800 text-gray-400 hover:text-white'"
      >
        <FunnelIcon class="w-4 h-4" />
        Ungelesen
      </button>
      <button
        @click="showOnlySaved = !showOnlySaved"
        class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm transition-colors"
        :class="showOnlySaved ? 'bg-yellow-600 text-white' : 'bg-dark-800 text-gray-400 hover:text-white'"
      >
        <BookmarkIcon class="w-4 h-4" />
        Gespeichert
      </button>

      <!-- View Toggle -->
      <div class="flex items-center gap-1 ml-2 bg-dark-800 rounded-lg p-1">
        <button
          @click="viewMode = 'grid'"
          class="p-1.5 rounded transition-colors"
          :class="viewMode === 'grid' ? 'bg-dark-600 text-white' : 'text-gray-400 hover:text-white'"
        >
          <Squares2X2Icon class="w-4 h-4" />
        </button>
        <button
          @click="viewMode = 'list'"
          class="p-1.5 rounded transition-colors"
          :class="viewMode === 'list' ? 'bg-dark-600 text-white' : 'text-gray-400 hover:text-white'"
        >
          <ListBulletIcon class="w-4 h-4" />
        </button>
      </div>
    </div>

    <!-- No Subscriptions -->
    <div v-if="!isLoading && subscribedFeeds.length === 0" class="card p-12 text-center">
      <RssIcon class="w-16 h-16 mx-auto text-gray-600 mb-4" />
      <h3 class="text-lg font-medium text-white mb-2">Keine Feeds abonniert</h3>
      <p class="text-gray-400 mb-6">Wähle in den Einstellungen deine Lieblingsquellen aus.</p>
      <button @click="showSettingsModal = true" class="btn-primary">
        <AdjustmentsHorizontalIcon class="w-5 h-5 mr-2" />
        Feeds verwalten
      </button>
    </div>

    <!-- Loading -->
    <div v-else-if="isLoading" class="flex justify-center py-12">
      <div class="w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full animate-spin"></div>
    </div>

    <!-- News Grid View -->
    <div v-else-if="viewMode === 'grid'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div
        v-for="item in newsItems"
        :key="item.id"
        @click="openArticle(item)"
        class="card-hover p-4 cursor-pointer group"
        :class="{ 'opacity-60': item.is_read == 1 }"
      >
        <!-- Image -->
        <div v-if="item.image_url" class="relative h-40 -mx-4 -mt-4 mb-4 overflow-hidden rounded-t-lg">
          <img :src="item.image_url" :alt="item.title" class="w-full h-full object-cover">
          <div class="absolute inset-0 bg-gradient-to-t from-dark-900/80 to-transparent"></div>
        </div>

        <!-- Content -->
        <div class="space-y-2">
          <div class="flex items-start justify-between gap-2">
            <h3 class="font-semibold text-white group-hover:text-primary-400 transition-colors line-clamp-2">
              {{ item.title }}
            </h3>
            <button
              @click.stop="toggleSaved(item)"
              class="p-1 rounded hover:bg-dark-600"
              :class="item.is_saved == 1 ? 'text-yellow-400' : 'text-gray-500'"
            >
              <BookmarkIconSolid v-if="item.is_saved == 1" class="w-5 h-5" />
              <BookmarkIcon v-else class="w-5 h-5" />
            </button>
          </div>

          <p class="text-sm text-gray-400 line-clamp-3">
            {{ stripHtml(item.description) }}
          </p>

          <div class="flex items-center justify-between text-xs text-gray-500">
            <div class="flex items-center gap-2">
              <component :is="categoryIcons[item.article_category] || categoryIcons[item.feed_category] || categoryIcons.other" class="w-4 h-4" />
              <span class="px-1.5 py-0.5 bg-dark-700 rounded text-gray-400">{{ categoryNames[item.article_category] || categoryNames[item.feed_category] || 'Sonstiges' }}</span>
              <span>{{ item.feed_name }}</span>
            </div>
            <div class="flex items-center gap-1">
              <ClockIcon class="w-3.5 h-3.5" />
              {{ formatDate(item.published_at) }}
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- News List View -->
    <div v-else class="space-y-4">
      <div
        v-for="item in newsItems"
        :key="item.id"
        class="card p-5"
        :class="{ 'opacity-60': item.is_read == 1 && !isExpanded(item) }"
      >
        <!-- Header row -->
        <div class="flex gap-5">
          <!-- Image (optional) -->
          <div v-if="item.image_url && !isExpanded(item)" class="flex-shrink-0 w-48 h-32 rounded-lg overflow-hidden">
            <img :src="item.image_url" :alt="item.title" class="w-full h-full object-cover">
          </div>

          <!-- Content -->
          <div class="flex-1 min-w-0">
            <div class="flex items-start justify-between gap-4 mb-2">
              <h3
                @click="toggleExpanded(item)"
                class="text-lg font-semibold text-white hover:text-primary-400 transition-colors cursor-pointer"
              >
                {{ item.title }}
              </h3>
              <div class="flex items-center gap-1 flex-shrink-0">
                <button
                  @click="toggleSaved(item)"
                  class="p-1.5 rounded hover:bg-dark-600"
                  :class="item.is_saved == 1 ? 'text-yellow-400' : 'text-gray-500'"
                  title="Speichern"
                >
                  <BookmarkIconSolid v-if="item.is_saved == 1" class="w-5 h-5" />
                  <BookmarkIcon v-else class="w-5 h-5" />
                </button>
                <button
                  @click="openExternalLink(item.url)"
                  class="p-1.5 rounded hover:bg-dark-600 text-gray-500 hover:text-white"
                  title="Original öffnen"
                >
                  <ArrowTopRightOnSquareIcon class="w-5 h-5" />
                </button>
                <button
                  @click="toggleExpanded(item)"
                  class="p-1.5 rounded hover:bg-dark-600 text-gray-500 hover:text-white"
                  :title="isExpanded(item) ? 'Einklappen' : 'Aufklappen'"
                >
                  <ChevronUpIcon v-if="isExpanded(item)" class="w-5 h-5" />
                  <ChevronDownIcon v-else class="w-5 h-5" />
                </button>
              </div>
            </div>

            <!-- Collapsed: Short preview -->
            <p v-if="!isExpanded(item)" class="text-gray-400 mb-3 line-clamp-3">
              {{ stripHtml(item.description || item.content) }}
            </p>

            <div class="flex items-center gap-4 text-sm text-gray-500">
              <div class="flex items-center gap-2">
                <component :is="categoryIcons[item.article_category] || categoryIcons[item.feed_category] || categoryIcons.other" class="w-4 h-4" />
                <span class="px-1.5 py-0.5 bg-dark-700 rounded text-gray-400">{{ categoryNames[item.article_category] || categoryNames[item.feed_category] || 'Sonstiges' }}</span>
                <span>{{ item.feed_name }}</span>
              </div>
              <div class="flex items-center gap-1">
                <ClockIcon class="w-4 h-4" />
                {{ formatDate(item.published_at) }}
              </div>
              <span v-if="item.author" class="text-gray-600">{{ item.author }}</span>
            </div>
          </div>
        </div>

        <!-- Expanded content -->
        <div v-if="isExpanded(item)" class="mt-6 pt-6 border-t border-dark-700">
          <!-- Large image when expanded -->
          <img
            v-if="item.image_url"
            :src="item.image_url"
            :alt="item.title"
            class="w-full max-h-96 object-cover rounded-lg mb-6"
          />

          <!-- Loading indicator -->
          <div v-if="isLoadingContent(item)" class="flex items-center justify-center py-8">
            <div class="w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full animate-spin"></div>
            <span class="ml-3 text-gray-400">Lade vollständigen Artikel...</span>
          </div>

          <!-- Full content -->
          <div
            v-else
            class="prose prose-invert max-w-none text-gray-300
                   prose-headings:text-white prose-headings:font-semibold
                   prose-p:text-gray-300 prose-p:leading-relaxed prose-p:mb-4
                   prose-a:text-primary-400 prose-a:no-underline hover:prose-a:underline
                   prose-strong:text-white prose-em:text-gray-200
                   prose-ul:text-gray-300 prose-ol:text-gray-300
                   prose-li:mb-1 prose-blockquote:border-primary-500
                   prose-code:text-primary-300 prose-pre:bg-dark-900"
            v-html="getFullContent(item)"
          ></div>

          <!-- Action buttons -->
          <div class="mt-6 pt-4 border-t border-dark-700 flex justify-between items-center">
            <button
              @click="toggleExpanded(item)"
              class="text-gray-400 hover:text-white flex items-center gap-2"
            >
              <ChevronUpIcon class="w-4 h-4" />
              Einklappen
            </button>
            <button
              @click="openExternalLink(item.url)"
              class="btn-primary"
            >
              <ArrowTopRightOnSquareIcon class="w-5 h-5 mr-2" />
              Originalartikel öffnen
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-if="!isLoading && newsItems.length === 0 && subscribedFeeds.length > 0" class="card p-12 text-center">
      <NewspaperIcon class="w-16 h-16 mx-auto text-gray-600 mb-4" />
      <h3 class="text-lg font-medium text-white mb-2">Keine Artikel gefunden</h3>
      <p class="text-gray-400 mb-6">Versuche andere Filter oder aktualisiere die Feeds.</p>
      <button @click="refreshFeeds" class="btn-primary" :disabled="isRefreshing">
        <ArrowPathIcon class="w-5 h-5 mr-2" :class="{ 'animate-spin': isRefreshing }" />
        Feeds aktualisieren
      </button>
    </div>

    <!-- Settings Modal -->
    <Teleport to="body">
      <div
        v-if="showSettingsModal"
        class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
        @click.self="showSettingsModal = false"
      >
        <div class="bg-dark-800 border border-dark-700 rounded-xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">
          <div class="flex items-center justify-between p-4 border-b border-dark-700">
            <h2 class="text-lg font-semibold text-white">Feed-Einstellungen</h2>
            <button @click="showSettingsModal = false" class="p-1 text-gray-400 hover:text-white rounded">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <div class="p-4 overflow-y-auto flex-1">
            <div class="flex items-center justify-between mb-4">
              <p class="text-gray-400">Wähle die Quellen aus, die du abonnieren möchtest.</p>
              <button @click="showAddFeedModal = true" class="btn-secondary text-sm">
                <PlusIcon class="w-4 h-4 mr-1" />
                Eigenen Feed hinzufügen
              </button>
            </div>

            <div class="space-y-6">
              <div v-for="(feedList, category) in groupedFeeds" :key="category">
                <h3 class="text-white font-medium mb-3 flex items-center gap-2">
                  <component :is="categoryIcons[category]" class="w-5 h-5 text-primary-400" />
                  {{ categoryNames[category] || category }}
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                  <div
                    v-for="feed in feedList"
                    :key="feed.id"
                    class="flex items-center justify-between p-3 bg-dark-700/50 rounded-lg"
                  >
                    <div class="flex items-center gap-3">
                      <img v-if="feed.icon_url" :src="feed.icon_url" class="w-6 h-6 rounded" />
                      <RssIcon v-else class="w-6 h-6 text-orange-400" />
                      <div>
                        <p class="text-white font-medium">{{ feed.name }}</p>
                        <p class="text-xs text-gray-500">{{ feed.language?.toUpperCase() }}</p>
                      </div>
                    </div>
                    <button
                      @click="toggleSubscription(feed)"
                      class="px-3 py-1.5 rounded-lg text-sm transition-colors"
                      :class="feed.is_subscribed == 1 ? 'bg-primary-600 text-white' : 'bg-dark-600 text-gray-400 hover:text-white'"
                    >
                      <CheckIcon v-if="feed.is_subscribed == 1" class="w-4 h-4 inline mr-1" />
                      {{ feed.is_subscribed == 1 ? 'Abonniert' : 'Abonnieren' }}
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="p-4 border-t border-dark-700 flex justify-end">
            <button @click="showSettingsModal = false; loadNews()" class="btn-primary">
              Fertig
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Add Feed Modal -->
    <Teleport to="body">
      <div
        v-if="showAddFeedModal"
        class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
        @click.self="showAddFeedModal = false"
      >
        <div class="bg-dark-800 border border-dark-700 rounded-xl w-full max-w-md">
          <div class="flex items-center justify-between p-4 border-b border-dark-700">
            <h2 class="text-lg font-semibold text-white">Eigenen Feed hinzufügen</h2>
            <button @click="showAddFeedModal = false" class="p-1 text-gray-400 hover:text-white rounded">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <div class="p-4 space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Feed URL *</label>
              <input
                v-model="newFeedForm.url"
                type="url"
                class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white"
                placeholder="https://example.com/feed.xml"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Name (optional)</label>
              <input
                v-model="newFeedForm.name"
                type="text"
                class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white"
                placeholder="Wird aus Feed gelesen, wenn leer"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Kategorie</label>
              <select
                v-model="newFeedForm.category"
                class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white"
              >
                <option v-for="(name, key) in categoryNames" :key="key" :value="key">{{ name }}</option>
              </select>
            </div>
          </div>

          <div class="flex items-center justify-end gap-3 p-4 border-t border-dark-700">
            <button @click="showAddFeedModal = false" class="btn-secondary">Abbrechen</button>
            <button @click="addFeed" class="btn-primary">Hinzufügen</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Article Modal -->
    <Teleport to="body">
      <div
        v-if="selectedArticle"
        class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
        @click.self="selectedArticle = null"
      >
        <div class="bg-dark-800 border border-dark-700 rounded-xl w-full max-w-3xl max-h-[90vh] overflow-hidden flex flex-col">
          <div class="flex items-center justify-between p-4 border-b border-dark-700">
            <div class="flex items-center gap-3">
              <component :is="categoryIcons[selectedArticle.article_category] || categoryIcons[selectedArticle.feed_category] || categoryIcons.other" class="w-5 h-5 text-primary-400" />
              <span class="px-2 py-1 bg-dark-700 rounded text-sm text-gray-300">{{ categoryNames[selectedArticle.article_category] || categoryNames[selectedArticle.feed_category] || 'Sonstiges' }}</span>
              <span class="text-gray-400">{{ selectedArticle.feed_name }}</span>
            </div>
            <div class="flex items-center gap-2">
              <button
                @click="toggleSaved(selectedArticle)"
                class="p-2 rounded hover:bg-dark-600"
                :class="selectedArticle.is_saved == 1 ? 'text-yellow-400' : 'text-gray-400'"
              >
                <BookmarkIconSolid v-if="selectedArticle.is_saved == 1" class="w-5 h-5" />
                <BookmarkIcon v-else class="w-5 h-5" />
              </button>
              <button
                @click="openExternalLink(selectedArticle.url)"
                class="p-2 text-gray-400 hover:text-white rounded hover:bg-dark-600"
              >
                <ArrowTopRightOnSquareIcon class="w-5 h-5" />
              </button>
              <button @click="selectedArticle = null" class="p-2 text-gray-400 hover:text-white rounded hover:bg-dark-600">
                <XMarkIcon class="w-5 h-5" />
              </button>
            </div>
          </div>

          <div class="p-6 overflow-y-auto flex-1">
            <img
              v-if="selectedArticle.image_url"
              :src="selectedArticle.image_url"
              :alt="selectedArticle.title"
              class="w-full h-64 object-cover rounded-lg mb-6"
            />

            <h1 class="text-2xl font-bold text-white mb-4">{{ selectedArticle.title }}</h1>

            <div class="flex items-center gap-4 text-sm text-gray-400 mb-6">
              <span v-if="selectedArticle.author">{{ selectedArticle.author }}</span>
              <span class="flex items-center gap-1">
                <ClockIcon class="w-4 h-4" />
                {{ formatDate(selectedArticle.published_at) }}
              </span>
            </div>

            <div
              class="prose prose-invert max-w-none"
              v-html="selectedArticle.content || selectedArticle.description"
            ></div>
          </div>

          <div class="p-4 border-t border-dark-700 flex justify-between">
            <button @click="selectedArticle = null" class="btn-secondary">Schließen</button>
            <button @click="openExternalLink(selectedArticle.url)" class="btn-primary">
              <ArrowTopRightOnSquareIcon class="w-5 h-5 mr-2" />
              Originalartikel öffnen
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
