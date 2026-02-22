<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue'
import { useRouter } from 'vue-router'
import {
  HomeIcon,
  ListBulletIcon,
  DocumentTextIcon,
  ViewColumnsIcon,
  FolderIcon,
  CodeBracketIcon,
  BookmarkIcon,
  ClockIcon,
  CalendarIcon,
  NewspaperIcon,
  Cog6ToothIcon,
  LinkIcon,
  ServerIcon,
  ArchiveBoxIcon,
  ShieldCheckIcon,
  UserGroupIcon,
  TicketIcon,
  MagnifyingGlassIcon,
  CommandLineIcon,
  PhotoIcon,
  ChatBubbleLeftRightIcon,
  BoltIcon,
  CloudArrowUpIcon,
  KeyIcon,
  SignalIcon,
  ArrowRightIcon,
} from '@heroicons/vue/24/outline'

const router = useRouter()

const isOpen = ref(false)
const query = ref('')
const selectedIndex = ref(0)
const inputRef = ref(null)

const allItems = [
  // Navigation
  { id: 'dashboard', label: 'Dashboard', path: '/', category: 'Navigation', icon: HomeIcon },
  { id: 'lists', label: 'Listen', path: '/lists', category: 'Navigation', icon: ListBulletIcon },
  { id: 'documents', label: 'Dokumente', path: '/documents', category: 'Navigation', icon: DocumentTextIcon },
  { id: 'kanban', label: 'Kanban', path: '/kanban', category: 'Navigation', icon: ViewColumnsIcon },
  { id: 'projects', label: 'Projekte', path: '/projects', category: 'Navigation', icon: FolderIcon },
  { id: 'snippets', label: 'Snippets', path: '/snippets', category: 'Navigation', icon: CodeBracketIcon },
  { id: 'bookmarks', label: 'Bookmarks', path: '/bookmarks', category: 'Navigation', icon: BookmarkIcon },
  { id: 'time', label: 'Zeiterfassung', path: '/time', category: 'Navigation', icon: ClockIcon },
  { id: 'calendar', label: 'Kalender', path: '/calendar', category: 'Navigation', icon: CalendarIcon },
  { id: 'news', label: 'News', path: '/news', category: 'Navigation', icon: NewspaperIcon },
  { id: 'chat', label: 'Chat', path: '/chat', category: 'Navigation', icon: ChatBubbleLeftRightIcon },
  { id: 'links', label: 'Kurzlinks', path: '/links', category: 'Navigation', icon: LinkIcon },
  { id: 'passwords', label: 'Passwörter', path: '/passwords', category: 'Navigation', icon: KeyIcon },
  { id: 'uptime', label: 'Uptime Monitor', path: '/uptime', category: 'Navigation', icon: SignalIcon },
  { id: 'storage', label: 'Dateispeicher', path: '/storage', category: 'Navigation', icon: ArchiveBoxIcon },
  { id: 'docker', label: 'Docker', path: '/docker', category: 'Navigation', icon: ServerIcon },
  { id: 'server', label: 'Server', path: '/server', category: 'Navigation', icon: ServerIcon },
  { id: 'workflows', label: 'Workflows', path: '/workflows', category: 'Navigation', icon: BoltIcon },
  { id: 'git', label: 'Git Repositories', path: '/git', category: 'Navigation', icon: CodeBracketIcon },
  { id: 'ssl', label: 'SSL-Zertifikate', path: '/ssl', category: 'Navigation', icon: ShieldCheckIcon },
  { id: 'galleries', label: 'Galerien', path: '/galleries', category: 'Navigation', icon: PhotoIcon },
  { id: 'invoices', label: 'Rechnungen', path: '/invoices', category: 'Navigation', icon: DocumentTextIcon },
  { id: 'backups', label: 'Backups', path: '/backups', category: 'Navigation', icon: CloudArrowUpIcon },
  { id: 'tickets', label: 'Tickets', path: '/tickets', category: 'Navigation', icon: TicketIcon },
  { id: 'discord', label: 'Discord', path: '/discord', category: 'Navigation', icon: ChatBubbleLeftRightIcon },
  { id: 'connections', label: 'SSH-Verbindungen', path: '/connections', category: 'Navigation', icon: CommandLineIcon },
  { id: 'inbox', label: 'Posteingang', path: '/inbox', category: 'Navigation', icon: DocumentTextIcon },
  { id: 'webhooks', label: 'Webhooks', path: '/webhooks', category: 'Navigation', icon: BoltIcon },
  { id: 'checklists', label: 'Checklisten', path: '/checklists', category: 'Navigation', icon: ListBulletIcon },
  { id: 'api-tester', label: 'API Tester', path: '/api-tester', category: 'Navigation', icon: CodeBracketIcon },
  { id: 'users', label: 'Benutzerverwaltung', path: '/users', category: 'Administration', icon: UserGroupIcon },
  { id: 'settings', label: 'Einstellungen', path: '/settings', category: 'Navigation', icon: Cog6ToothIcon },
]

function fuzzyMatch(text, search) {
  if (!search) return true
  const t = text.toLowerCase()
  const s = search.toLowerCase()
  let ti = 0
  for (let si = 0; si < s.length; si++) {
    const idx = t.indexOf(s[si], ti)
    if (idx === -1) return false
    ti = idx + 1
  }
  return true
}

const filteredItems = computed(() => {
  const q = query.value.trim()
  if (!q) return allItems.slice(0, 10)
  return allItems.filter(item =>
    fuzzyMatch(item.label, q) || fuzzyMatch(item.category, q)
  )
})

const groupedItems = computed(() => {
  const groups = {}
  filteredItems.value.forEach(item => {
    if (!groups[item.category]) groups[item.category] = []
    groups[item.category].push(item)
  })
  return groups
})

// Flat list for keyboard navigation
const flatItems = computed(() => filteredItems.value)

watch(filteredItems, () => {
  selectedIndex.value = 0
})

function open() {
  isOpen.value = true
  query.value = ''
  selectedIndex.value = 0
  nextTick(() => inputRef.value?.focus())
}

function close() {
  isOpen.value = false
  query.value = ''
}

function navigate(item) {
  router.push(item.path)
  close()
}

function handleKeyDown(e) {
  if (!isOpen.value) return

  if (e.key === 'Escape') {
    close()
    e.preventDefault()
  } else if (e.key === 'ArrowDown') {
    e.preventDefault()
    selectedIndex.value = Math.min(selectedIndex.value + 1, flatItems.value.length - 1)
  } else if (e.key === 'ArrowUp') {
    e.preventDefault()
    selectedIndex.value = Math.max(selectedIndex.value - 1, 0)
  } else if (e.key === 'Enter') {
    e.preventDefault()
    const item = flatItems.value[selectedIndex.value]
    if (item) navigate(item)
  }
}

function onToggle() {
  if (isOpen.value) close()
  else open()
}

onMounted(() => {
  document.addEventListener('toggle-command-palette', onToggle)
  window.addEventListener('keydown', handleKeyDown)
})

onUnmounted(() => {
  document.removeEventListener('toggle-command-palette', onToggle)
  window.removeEventListener('keydown', handleKeyDown)
})
</script>

<template>
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
        class="fixed inset-0 z-50 flex items-start justify-center pt-[15vh] px-4"
        @click.self="close"
      >
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="close"></div>

        <!-- Palette panel -->
        <div class="relative w-full max-w-xl bg-dark-800 border border-dark-600 rounded-xl shadow-2xl overflow-hidden">
          <!-- Search input -->
          <div class="flex items-center gap-3 px-4 py-3 border-b border-dark-700">
            <MagnifyingGlassIcon class="w-5 h-5 text-gray-400 flex-shrink-0" />
            <input
              ref="inputRef"
              v-model="query"
              type="text"
              class="flex-1 bg-transparent text-white placeholder-gray-500 outline-none text-sm"
              placeholder="Suchen oder navigieren..."
            />
            <span class="text-xs text-gray-600 flex-shrink-0">ESC</span>
          </div>

          <!-- Results -->
          <div class="max-h-80 overflow-y-auto py-2">
            <template v-if="flatItems.length === 0">
              <p class="text-center text-gray-500 text-sm py-8">Keine Ergebnisse</p>
            </template>

            <template v-for="(items, category) in groupedItems" :key="category">
              <p class="px-4 py-1 text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ category }}</p>
              <button
                v-for="item in items"
                :key="item.id"
                class="w-full flex items-center gap-3 px-4 py-2 text-left transition-colors"
                :class="flatItems.indexOf(item) === selectedIndex
                  ? 'bg-primary-500/20 text-white'
                  : 'text-gray-300 hover:bg-dark-700'"
                @click="navigate(item)"
                @mousemove="selectedIndex = flatItems.indexOf(item)"
              >
                <component :is="item.icon" class="w-4 h-4 flex-shrink-0 text-gray-400" />
                <span class="flex-1 text-sm">{{ item.label }}</span>
                <ArrowRightIcon
                  v-if="flatItems.indexOf(item) === selectedIndex"
                  class="w-3 h-3 text-primary-400"
                />
              </button>
            </template>
          </div>

          <!-- Footer hint -->
          <div class="px-4 py-2 border-t border-dark-700 flex items-center gap-4 text-xs text-gray-600">
            <span><kbd class="font-mono">↑↓</kbd> Navigieren</span>
            <span><kbd class="font-mono">↵</kbd> Öffnen</span>
            <span><kbd class="font-mono">ESC</kbd> Schließen</span>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
