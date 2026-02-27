<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useUiStore } from '@/stores/ui'
import { getAllNavItems, type NavItem } from '@/core/config/navigation'
import {
  MagnifyingGlassIcon,
  ArrowRightIcon,
} from '@heroicons/vue/24/outline'

interface PaletteItem {
  id: string
  label: string
  path: string
  category: string
  icon: any
}

const router = useRouter()
const uiStore = useUiStore()

const query = ref('')
const selectedIndex = ref(0)
const inputRef = ref<HTMLInputElement | null>(null)

// Build items from central navigation config
const allItems = computed<PaletteItem[]>(() => {
  return getAllNavItems().map(item => ({
    id: item.id,
    label: item.name,
    path: item.href,
    category: 'Navigation',
    icon: item.icon,
  }))
})

function fuzzyMatch(text: string, search: string): boolean {
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
  if (!q) return allItems.value.slice(0, 12)
  return allItems.value.filter(item =>
    fuzzyMatch(item.label, q) || fuzzyMatch(item.category, q)
  )
})

const groupedItems = computed(() => {
  const groups: Record<string, PaletteItem[]> = {}
  filteredItems.value.forEach(item => {
    if (!groups[item.category]) groups[item.category] = []
    groups[item.category].push(item)
  })
  return groups
})

const flatItems = computed(() => filteredItems.value)

watch(filteredItems, () => {
  selectedIndex.value = 0
})

// Watch the store's showCommandPalette
watch(() => uiStore.showCommandPalette, (val) => {
  if (val) open()
  else close()
})

function open() {
  uiStore.showCommandPalette = true
  query.value = ''
  selectedIndex.value = 0
  nextTick(() => inputRef.value?.focus())
}

function close() {
  uiStore.showCommandPalette = false
  query.value = ''
}

function navigate(item: PaletteItem) {
  router.push(item.path)
  close()
}

function handleKeyDown(e: KeyboardEvent) {
  if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
    e.preventDefault()
    if (uiStore.showCommandPalette) close()
    else open()
    return
  }

  if (!uiStore.showCommandPalette) return

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

onMounted(() => {
  window.addEventListener('keydown', handleKeyDown)
  // Legacy event support
  document.addEventListener('toggle-command-palette', () => {
    if (uiStore.showCommandPalette) close()
    else open()
  })
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeyDown)
})
</script>

<template>
  <Teleport to="body">
    <Transition name="fade">
      <div
        v-if="uiStore.showCommandPalette"
        class="fixed inset-0 z-50 flex items-start justify-center pt-[15vh] px-4"
        @click.self="close"
      >
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/60 backdrop-blur-md" @click="close" />

        <!-- Palette panel -->
        <div class="relative w-full max-w-xl bg-dark-900/95 backdrop-blur-2xl border border-white/[0.08] rounded-2xl shadow-float overflow-hidden animate-scale-in">
          <!-- Search input -->
          <div class="flex items-center gap-3 px-5 py-4 border-b border-white/[0.06]">
            <MagnifyingGlassIcon class="w-5 h-5 text-gray-400 shrink-0" />
            <input
              ref="inputRef"
              v-model="query"
              type="text"
              class="flex-1 bg-transparent text-white placeholder-gray-500 outline-none text-sm"
              placeholder="Suchen oder navigieren..."
            />
            <kbd class="kbd">ESC</kbd>
          </div>

          <!-- Results -->
          <div class="max-h-80 overflow-y-auto py-2">
            <template v-if="flatItems.length === 0">
              <div class="empty-state py-8">
                <p class="text-sm text-gray-500">Keine Ergebnisse</p>
              </div>
            </template>

            <template v-for="(items, category) in groupedItems" :key="category">
              <p class="section-label px-5 py-1.5 mb-0">{{ category }}</p>
              <button
                v-for="item in items"
                :key="item.id"
                class="w-full flex items-center gap-3 px-5 py-2.5 text-left transition-all duration-100"
                :class="flatItems.indexOf(item) === selectedIndex
                  ? 'bg-primary-500/15 text-white'
                  : 'text-gray-400 hover:bg-white/[0.04] hover:text-gray-200'"
                @click="navigate(item)"
                @mousemove="selectedIndex = flatItems.indexOf(item)"
              >
                <component :is="item.icon" class="w-4 h-4 shrink-0" />
                <span class="flex-1 text-sm">{{ item.label }}</span>
                <ArrowRightIcon
                  v-if="flatItems.indexOf(item) === selectedIndex"
                  class="w-3 h-3 text-primary-400"
                />
              </button>
            </template>
          </div>

          <!-- Footer -->
          <div class="px-5 py-3 border-t border-white/[0.06] flex items-center gap-4">
            <span class="flex items-center gap-1.5 text-2xs text-gray-500">
              <kbd class="kbd">&#8593;&#8595;</kbd> Navigieren
            </span>
            <span class="flex items-center gap-1.5 text-2xs text-gray-500">
              <kbd class="kbd">&#8629;</kbd> Öffnen
            </span>
            <span class="flex items-center gap-1.5 text-2xs text-gray-500">
              <kbd class="kbd">ESC</kbd> Schließen
            </span>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
