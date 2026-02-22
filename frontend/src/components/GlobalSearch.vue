<script setup>
import { ref, watch, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  MagnifyingGlassIcon,
  XMarkIcon,
  DocumentTextIcon,
  ListBulletIcon,
  CodeBracketIcon,
  TableCellsIcon,
  HomeIcon,
  NewspaperIcon,
  ViewColumnsIcon,
  FolderIcon,
  CalendarIcon,
  ClockIcon,
  ArrowPathIcon,
  ServerIcon,
  BellIcon,
  SignalIcon,
  LockClosedIcon,
  WrenchScrewdriverIcon,
  BoltIcon,
  CubeIcon,
  TicketIcon,
  TagIcon,
  CurrencyDollarIcon,
  ChatBubbleLeftRightIcon,
  BookOpenIcon,
  Cog6ToothIcon,
  UsersIcon,
  ShieldCheckIcon,
  ArchiveBoxIcon,
  KeyIcon,
  CloudArrowUpIcon,
  LinkIcon,
  ClipboardDocumentListIcon,
  PhotoIcon,
  PencilSquareIcon,
  SwatchIcon,
  BookmarkIcon,
  CommandLineIcon,
  CloudIcon,
  ArrowRightIcon,
  SparklesIcon,
  PlusIcon,
} from '@heroicons/vue/24/outline'
import api from '@/core/api/axios'

// ─── Navigation items for Command Palette ────────────────────────────────────
const navigationItems = [
  { name: 'Dashboard', href: '/', icon: HomeIcon, group: null },
  { name: 'News', href: '/news', icon: NewspaperIcon, group: null },
  { name: 'Listen', href: '/lists', icon: ListBulletIcon, group: 'Inhalte' },
  { name: 'Dokumente', href: '/documents', icon: DocumentTextIcon, group: 'Inhalte' },
  { name: 'Notes', href: '/notes', icon: PencilSquareIcon, group: 'Inhalte' },
  { name: 'Snippets', href: '/snippets', icon: CodeBracketIcon, group: 'Inhalte' },
  { name: 'Bookmarks', href: '/bookmarks', icon: BookmarkIcon, group: 'Inhalte' },
  { name: 'Cloud Storage', href: '/storage', icon: CloudArrowUpIcon, group: 'KyuubiCloud' },
  { name: 'Freigaben', href: '/storage/shares', icon: LinkIcon, group: 'KyuubiCloud' },
  { name: 'Checklisten', href: '/checklists', icon: ClipboardDocumentListIcon, group: 'KyuubiCloud' },
  { name: 'Short Links', href: '/links', icon: LinkIcon, group: 'KyuubiCloud' },
  { name: 'Galerien', href: '/galleries', icon: PhotoIcon, group: 'KyuubiCloud' },
  { name: 'Mockup Editor', href: '/mockup-editor', icon: SwatchIcon, group: 'KyuubiCloud' },
  { name: 'Kanban', href: '/kanban', icon: ViewColumnsIcon, group: 'Projektmanagement' },
  { name: 'Projekte', href: '/projects', icon: FolderIcon, group: 'Projektmanagement' },
  { name: 'Kalender', href: '/calendar', icon: CalendarIcon, group: 'Projektmanagement' },
  { name: 'Zeiterfassung', href: '/time', icon: ClockIcon, group: 'Projektmanagement' },
  { name: 'Wiederkehrend', href: '/recurring-tasks', icon: ArrowPathIcon, group: 'Projektmanagement' },
  { name: 'Verbindungen', href: '/connections', icon: ServerIcon, group: 'Entwicklung & Tools' },
  { name: 'Server', href: '/server', icon: CommandLineIcon, group: 'Entwicklung & Tools' },
  { name: 'Git Repos', href: '/git', icon: CodeBracketIcon, group: 'Entwicklung & Tools' },
  { name: 'Webhooks', href: '/webhooks', icon: BellIcon, group: 'Entwicklung & Tools' },
  { name: 'Uptime Monitor', href: '/uptime', icon: SignalIcon, group: 'Entwicklung & Tools' },
  { name: 'SSL Zertifikate', href: '/ssl', icon: LockClosedIcon, group: 'Entwicklung & Tools' },
  { name: 'Toolbox', href: '/toolbox', icon: WrenchScrewdriverIcon, group: 'Entwicklung & Tools' },
  { name: 'Workflows', href: '/workflows', icon: BoltIcon, group: 'Entwicklung & Tools' },
  { name: 'Container Manager', href: '/docker', icon: CubeIcon, group: 'Docker' },
  { name: 'Docker Hosts', href: '/docker/hosts', icon: ServerIcon, group: 'Docker' },
  { name: 'Dockerfile Generator', href: '/docker/dockerfile', icon: DocumentTextIcon, group: 'Docker' },
  { name: 'Compose Builder', href: '/docker/compose', icon: ViewColumnsIcon, group: 'Docker' },
  { name: 'Command Builder', href: '/docker/command', icon: CommandLineIcon, group: 'Docker' },
  { name: '.dockerignore', href: '/docker/ignore', icon: ShieldCheckIcon, group: 'Docker' },
  { name: 'Tickets', href: '/tickets', icon: TicketIcon, group: 'Support' },
  { name: 'Ticket-Kategorien', href: '/tickets/categories', icon: TagIcon, group: 'Support' },
  { name: 'Rechnungen', href: '/invoices', icon: CurrencyDollarIcon, group: 'Business' },
  { name: 'Discord Manager', href: '/discord', icon: ChatBubbleLeftRightIcon, group: null },
  { name: 'Wiki', href: '/wiki', icon: BookOpenIcon, group: null },
  { name: 'Inbox', href: '/inbox', icon: CloudIcon, group: null },
  { name: 'Team Chat', href: '/chat', icon: ChatBubbleLeftRightIcon, group: null },
  { name: 'Passwörter', href: '/passwords', icon: KeyIcon, group: 'Administration' },
  { name: 'Backups', href: '/backups', icon: ArchiveBoxIcon, group: 'Administration' },
  { name: 'Einstellungen', href: '/settings', icon: Cog6ToothIcon, group: 'Administration' },
  { name: 'Benutzer', href: '/users', icon: UsersIcon, group: 'Administration' },
  { name: 'Rollen', href: '/roles', icon: ShieldCheckIcon, group: 'Administration' },
  { name: 'System', href: '/system', icon: ShieldCheckIcon, group: 'Administration' },
]

// Quick actions shown when no search query
const quickActions = [
  { name: 'Neue Liste', icon: PlusIcon, href: '/lists', description: 'Liste erstellen', accent: true },
  { name: 'Neues Dokument', icon: PlusIcon, href: '/documents', description: 'Dokument erstellen', accent: true },
  { name: 'Neues Snippet', icon: PlusIcon, href: '/snippets', description: 'Snippet erstellen', accent: true },
  { name: 'Einstellungen', icon: Cog6ToothIcon, href: '/settings', description: 'App-Einstellungen öffnen', accent: false },
]

const router = useRouter()

const isOpen = ref(false)
const searchQuery = ref('')
const results = ref({ documents: [], lists: [], total: 0 })
const isLoading = ref(false)
const selectedIndex = ref(-1)

let searchTimeout = null

// Content search results (flattened)
const allResults = computed(() => {
  const items = []
  results.value.documents.forEach(doc => items.push({ ...doc, category: 'document' }))
  results.value.lists.forEach(list => items.push({ ...list, category: 'list' }))
  return items
})

// Navigation matches when typing
const filteredNavItems = computed(() => {
  const q = searchQuery.value.toLowerCase().trim()
  if (!q) return []
  return navigationItems.filter(item =>
    item.name.toLowerCase().includes(q) ||
    (item.group && item.group.toLowerCase().includes(q))
  ).slice(0, 6)
})

// All keyboard-navigable items
const allSelectableItems = computed(() => {
  if (searchQuery.value.length >= 2) {
    return [
      ...filteredNavItems.value.map(n => ({ ...n, _type: 'nav' })),
      ...allResults.value.map(r => ({ ...r, _type: 'content' })),
    ]
  }
  return [
    ...quickActions.map(a => ({ ...a, _type: 'action' })),
    ...navigationItems.slice(0, 8).map(n => ({ ...n, _type: 'nav' })),
  ]
})

function getIcon(item) {
  if (item.category === 'list') return ListBulletIcon
  if (item.format === 'code') return CodeBracketIcon
  if (item.format === 'spreadsheet') return TableCellsIcon
  return DocumentTextIcon
}

function getTypeLabel(item) {
  if (item.category === 'list') return item.list_type || 'Liste'
  if (item.format === 'code') return 'Code'
  if (item.format === 'spreadsheet') return 'Tabelle'
  if (item.format === 'markdown') return 'Markdown'
  return 'Rich Text'
}

function open() {
  isOpen.value = true
  searchQuery.value = ''
  results.value = { documents: [], lists: [], total: 0 }
  selectedIndex.value = -1
}

function close() {
  isOpen.value = false
  searchQuery.value = ''
}

async function search() {
  if (searchQuery.value.length < 2) {
    results.value = { documents: [], lists: [], total: 0 }
    return
  }
  isLoading.value = true
  try {
    const response = await api.get('/api/v1/search', {
      params: { q: searchQuery.value, limit: 6 }
    })
    results.value = response.data.data
    selectedIndex.value = -1
  } catch (error) {
    console.error('Search error:', error)
  } finally {
    isLoading.value = false
  }
}

function navigateTo(item) {
  close()
  if (item.category === 'list') {
    router.push({ name: 'lists', query: { open: item.id } })
  } else if (item.category === 'document') {
    router.push({ name: 'documents', query: { open: item.id } })
  } else if (item.href) {
    router.push(item.href)
  }
}

function handleKeydown(e) {
  if (e.key === 'Escape') {
    close()
  } else if (e.key === 'ArrowDown') {
    e.preventDefault()
    selectedIndex.value = Math.min(selectedIndex.value + 1, allSelectableItems.value.length - 1)
  } else if (e.key === 'ArrowUp') {
    e.preventDefault()
    selectedIndex.value = Math.max(selectedIndex.value - 1, -1)
  } else if (e.key === 'Enter') {
    e.preventDefault()
    if (selectedIndex.value >= 0 && allSelectableItems.value[selectedIndex.value]) {
      navigateTo(allSelectableItems.value[selectedIndex.value])
    }
  }
}

function handleGlobalKeydown(e) {
  if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
    e.preventDefault()
    if (isOpen.value) close()
    else open()
  }
}

watch(searchQuery, () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(search, 280)
})

onMounted(() => {
  window.addEventListener('keydown', handleGlobalKeydown)
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleGlobalKeydown)
})

defineExpose({ open, close })
</script>

<template>
  <!-- Trigger Button -->
  <button
    @click="open"
    class="flex items-center gap-2 px-3 py-1.5 bg-dark-700/60 hover:bg-dark-700 border border-dark-600/50 rounded-lg transition-all duration-150 text-gray-500 hover:text-gray-300 group"
  >
    <MagnifyingGlassIcon class="w-4 h-4" />
    <span class="hidden sm:inline text-sm">Suchen…</span>
    <kbd class="hidden md:inline-flex items-center gap-0.5 px-1.5 py-0.5 text-[11px] bg-dark-600/80 border border-dark-500/50 rounded-md font-mono">
      <span>⌘</span><span>K</span>
    </kbd>
  </button>

  <!-- Command Palette Modal -->
  <Teleport to="body">
    <Transition
      enter-active-class="transition ease-out duration-150"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition ease-in duration-100"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="isOpen"
        class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-start justify-center pt-[12vh] px-4"
        @click.self="close"
      >
        <div class="bg-dark-800 rounded-2xl w-full max-w-2xl border border-dark-700/80 shadow-2xl overflow-hidden animate-scale-in">

          <!-- Search Input -->
          <div class="flex items-center gap-3 px-4 py-3.5 border-b border-dark-700/60">
            <MagnifyingGlassIcon class="w-5 h-5 text-gray-500 shrink-0" />
            <input
              v-model="searchQuery"
              @keydown="handleKeydown"
              type="text"
              placeholder="Seite oder Inhalt suchen…"
              class="flex-1 bg-transparent text-white placeholder-gray-500 focus:outline-none text-base"
              autofocus
            />
            <button @click="close" class="btn-icon-sm shrink-0">
              <XMarkIcon class="w-4 h-4" />
            </button>
          </div>

          <!-- Body -->
          <div class="max-h-[60vh] overflow-y-auto">

            <!-- Loading spinner -->
            <div v-if="isLoading" class="p-6 flex justify-center">
              <div class="w-5 h-5 border-2 border-primary-500 border-t-transparent rounded-full animate-spin"></div>
            </div>

            <template v-else>

              <!-- ─── No query: Quick Actions + Nav shortcuts ─── -->
              <div v-if="searchQuery.length < 2">
                <!-- Quick Actions -->
                <div class="px-3 pt-3 pb-1">
                  <p class="section-label px-1">Schnellaktionen</p>
                  <div class="grid grid-cols-2 gap-1.5">
                    <button
                      v-for="(action, i) in quickActions"
                      :key="action.name"
                      @click="navigateTo(action)"
                      class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-left transition-all duration-100"
                      :class="[
                        selectedIndex === i
                          ? 'bg-primary-500/15 text-primary-300 ring-1 ring-primary-500/30'
                          : 'bg-dark-700/40 text-gray-400 hover:bg-dark-700/80 hover:text-gray-200'
                      ]"
                      @mouseenter="selectedIndex = i"
                    >
                      <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0"
                        :class="action.accent ? 'bg-primary-500/20' : 'bg-dark-600'">
                        <component :is="action.icon" class="w-4 h-4" :class="action.accent ? 'text-primary-400' : 'text-gray-400'" />
                      </div>
                      <div class="min-w-0">
                        <p class="text-sm font-medium truncate">{{ action.name }}</p>
                        <p class="text-[11px] text-gray-500 truncate">{{ action.description }}</p>
                      </div>
                    </button>
                  </div>
                </div>

                <!-- Navigation Shortcuts -->
                <div class="px-3 pt-3 pb-3">
                  <p class="section-label px-1">Navigation</p>
                  <div class="space-y-0.5">
                    <button
                      v-for="(item, i) in navigationItems.slice(0, 8)"
                      :key="item.href"
                      @click="navigateTo(item)"
                      class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-left transition-all duration-100"
                      :class="selectedIndex === quickActions.length + i
                        ? 'bg-dark-700 text-gray-200'
                        : 'text-gray-500 hover:bg-dark-700/60 hover:text-gray-300'"
                      @mouseenter="selectedIndex = quickActions.length + i"
                    >
                      <component :is="item.icon" class="w-4 h-4 shrink-0" />
                      <span class="text-sm flex-1">{{ item.name }}</span>
                      <span v-if="item.group" class="text-[11px] text-dark-500 shrink-0">{{ item.group }}</span>
                      <ArrowRightIcon class="w-3.5 h-3.5 text-dark-500 shrink-0" />
                    </button>
                  </div>
                </div>
              </div>

              <!-- ─── Has query: Nav matches + content results ─── -->
              <div v-else>
                <!-- Empty state -->
                <div v-if="filteredNavItems.length === 0 && allResults.length === 0" class="px-4 py-10 text-center">
                  <SparklesIcon class="w-10 h-10 text-dark-600 mx-auto mb-3" />
                  <p class="text-gray-500 text-sm">Keine Ergebnisse für <span class="text-gray-300">"{{ searchQuery }}"</span></p>
                </div>

                <!-- Nav matches -->
                <div v-if="filteredNavItems.length > 0" class="px-3 pt-3 pb-1">
                  <p class="section-label px-1">Navigation</p>
                  <div class="space-y-0.5">
                    <button
                      v-for="(item, i) in filteredNavItems"
                      :key="'nav-' + item.href"
                      @click="navigateTo(item)"
                      class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-left transition-all duration-100"
                      :class="selectedIndex === i
                        ? 'bg-primary-500/10 text-primary-300 ring-1 ring-primary-500/20'
                        : 'text-gray-400 hover:bg-dark-700/60 hover:text-gray-200'"
                      @mouseenter="selectedIndex = i"
                    >
                      <div class="w-7 h-7 rounded-lg bg-dark-700/60 flex items-center justify-center shrink-0">
                        <component :is="item.icon" class="w-4 h-4" />
                      </div>
                      <span class="text-sm flex-1">{{ item.name }}</span>
                      <span v-if="item.group" class="text-[11px] text-dark-500 shrink-0 hidden sm:inline">{{ item.group }}</span>
                      <ArrowRightIcon class="w-3.5 h-3.5 text-dark-500 shrink-0" />
                    </button>
                  </div>
                </div>

                <!-- Content results -->
                <div v-if="allResults.length > 0" class="px-3 pt-3 pb-3"
                  :class="filteredNavItems.length > 0 ? 'border-t border-dark-700/40 mt-2' : ''">

                  <!-- Documents -->
                  <template v-if="results.documents.length > 0">
                    <p class="section-label px-1">Dokumente</p>
                    <div class="space-y-0.5">
                      <button
                        v-for="(doc, i) in results.documents"
                        :key="'doc-' + doc.id"
                        @click="navigateTo({ ...doc, category: 'document' })"
                        class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-left transition-all duration-100"
                        :class="selectedIndex === filteredNavItems.length + i
                          ? 'bg-dark-700 text-gray-200'
                          : 'text-gray-400 hover:bg-dark-700/60 hover:text-gray-200'"
                        @mouseenter="selectedIndex = filteredNavItems.length + i"
                      >
                        <div class="w-8 h-8 rounded-lg bg-primary-500/15 flex items-center justify-center shrink-0">
                          <component :is="getIcon({ ...doc, category: 'document' })" class="w-4 h-4 text-primary-400" />
                        </div>
                        <div class="flex-1 min-w-0">
                          <div class="flex items-center gap-2">
                            <span class="text-sm font-medium text-gray-200 truncate">{{ doc.title }}</span>
                            <span class="badge badge-primary shrink-0">{{ getTypeLabel({ ...doc, category: 'document' }) }}</span>
                          </div>
                          <p v-if="doc.snippet" class="text-xs text-gray-500 mt-0.5 line-clamp-1">{{ doc.snippet }}</p>
                        </div>
                      </button>
                    </div>
                  </template>

                  <!-- Lists -->
                  <template v-if="results.lists.length > 0">
                    <p class="section-label px-1 mt-3">Listen</p>
                    <div class="space-y-0.5">
                      <button
                        v-for="(list, i) in results.lists"
                        :key="'list-' + list.id"
                        @click="navigateTo({ ...list, category: 'list' })"
                        class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-left transition-all duration-100"
                        :class="selectedIndex === filteredNavItems.length + results.documents.length + i
                          ? 'bg-dark-700 text-gray-200'
                          : 'text-gray-400 hover:bg-dark-700/60 hover:text-gray-200'"
                        @mouseenter="selectedIndex = filteredNavItems.length + results.documents.length + i"
                      >
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0"
                          :style="{ backgroundColor: (list.color || '#3B82F6') + '28' }">
                          <ListBulletIcon class="w-4 h-4" :style="{ color: list.color || '#60a5fa' }" />
                        </div>
                        <div class="flex-1 min-w-0">
                          <div class="flex items-center gap-2">
                            <span class="text-sm font-medium text-gray-200 truncate">{{ list.title }}</span>
                            <span v-if="list.item_count" class="text-[11px] text-gray-500 shrink-0">{{ list.item_count }} Einträge</span>
                          </div>
                          <p v-if="list.snippet" class="text-xs text-gray-500 mt-0.5 line-clamp-1">{{ list.snippet }}</p>
                        </div>
                      </button>
                    </div>
                  </template>
                </div>
              </div>

            </template>
          </div>

          <!-- Footer hints -->
          <div class="px-4 py-2.5 border-t border-dark-700/60 flex items-center justify-between text-[11px] text-gray-600">
            <div class="flex items-center gap-3">
              <span class="flex items-center gap-1">
                <kbd class="px-1.5 py-0.5 bg-dark-700/80 border border-dark-600/60 rounded font-mono">↑↓</kbd>
                Navigieren
              </span>
              <span class="flex items-center gap-1">
                <kbd class="px-1.5 py-0.5 bg-dark-700/80 border border-dark-600/60 rounded font-mono">↵</kbd>
                Öffnen
              </span>
              <span class="flex items-center gap-1">
                <kbd class="px-1.5 py-0.5 bg-dark-700/80 border border-dark-600/60 rounded font-mono">Esc</kbd>
                Schließen
              </span>
            </div>
            <span v-if="searchQuery.length >= 2 && results.total > 0">{{ results.total }} Inhaltstreffer</span>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
