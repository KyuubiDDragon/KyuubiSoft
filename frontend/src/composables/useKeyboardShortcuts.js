import { onMounted, onUnmounted, ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useUiStore } from '@/stores/ui'

// Global shortcut definitions
const globalShortcuts = [
  // Navigation
  { key: 'g h', description: 'Dashboard öffnen', route: '/' },
  { key: 'g l', description: 'Listen öffnen', route: '/lists' },
  { key: 'g d', description: 'Dokumente öffnen', route: '/documents' },
  { key: 'g k', description: 'Kanban öffnen', route: '/kanban' },
  { key: 'g p', description: 'Projekte öffnen', route: '/projects' },
  { key: 'g s', description: 'Snippets öffnen', route: '/snippets' },
  { key: 'g b', description: 'Bookmarks öffnen', route: '/bookmarks' },
  { key: 'g t', description: 'Zeiterfassung öffnen', route: '/time' },
  { key: 'g c', description: 'Kalender öffnen', route: '/calendar' },
  { key: 'g n', description: 'News öffnen', route: '/news' },
  { key: 'g e', description: 'Einstellungen öffnen', route: '/settings' },

  // Actions
  { key: '/', description: 'Globale Suche', action: 'search' },
  { key: 'Ctrl+k', description: 'Globale Suche (alternativ)', action: 'search' },
  { key: '?', description: 'Shortcuts anzeigen', action: 'showShortcuts' },
  { key: 'Escape', description: 'Modal/Dialog schließen', action: 'escape' },
  { key: 'n', description: 'Neu erstellen (kontextabhängig)', action: 'new' },
]

// Store for the keyboard shortcuts state
const isShortcutsModalOpen = ref(false)
const pendingKeys = ref('')
const pendingTimeout = ref(null)

export function useKeyboardShortcuts() {
  const router = useRouter()
  const uiStore = useUiStore()

  const shortcuts = computed(() => globalShortcuts)

  function isInputElement(element) {
    const tagName = element?.tagName?.toLowerCase()
    return tagName === 'input' ||
           tagName === 'textarea' ||
           tagName === 'select' ||
           element?.isContentEditable
  }

  function normalizeKey(e) {
    let key = e.key
    const parts = []

    if (e.ctrlKey || e.metaKey) parts.push('Ctrl')
    if (e.altKey) parts.push('Alt')
    if (e.shiftKey && key.length > 1) parts.push('Shift')

    // Normalize key
    if (key === ' ') key = 'Space'
    if (key.length === 1) key = key.toLowerCase()

    parts.push(key)
    return parts.join('+')
  }

  function executeAction(shortcut) {
    if (shortcut.route) {
      router.push(shortcut.route)
      return true
    }

    switch (shortcut.action) {
      case 'search':
        // Emit event or directly trigger global search
        document.dispatchEvent(new CustomEvent('toggle-global-search'))
        return true

      case 'showShortcuts':
        isShortcutsModalOpen.value = true
        return true

      case 'escape':
        // Let the default escape handling work
        return false

      case 'new':
        // Emit event for context-aware new item creation
        document.dispatchEvent(new CustomEvent('create-new-item'))
        return true

      default:
        return false
    }
  }

  function handleKeyDown(e) {
    // Ignore if in input field (except for some specific shortcuts)
    if (isInputElement(e.target)) {
      // Allow Ctrl+K for search even in input fields
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault()
        document.dispatchEvent(new CustomEvent('toggle-global-search'))
        return
      }
      // Allow Escape
      if (e.key === 'Escape') {
        document.dispatchEvent(new CustomEvent('escape-pressed'))
        return
      }
      return
    }

    const normalizedKey = normalizeKey(e)

    // Clear pending timeout
    if (pendingTimeout.value) {
      clearTimeout(pendingTimeout.value)
      pendingTimeout.value = null
    }

    // Handle multi-key sequences (like 'g h')
    if (pendingKeys.value) {
      const fullKey = `${pendingKeys.value} ${normalizedKey}`
      const shortcut = globalShortcuts.find(s => s.key === fullKey)

      if (shortcut) {
        e.preventDefault()
        pendingKeys.value = ''
        executeAction(shortcut)
        return
      }

      // No match found, reset
      pendingKeys.value = ''
    }

    // Check for single key shortcuts
    const singleKeyShortcut = globalShortcuts.find(s => s.key === normalizedKey && !s.key.includes(' '))
    if (singleKeyShortcut) {
      e.preventDefault()
      executeAction(singleKeyShortcut)
      return
    }

    // Check if this could be the start of a multi-key sequence
    const couldBeSequenceStart = globalShortcuts.some(s => s.key.startsWith(normalizedKey + ' '))
    if (couldBeSequenceStart) {
      pendingKeys.value = normalizedKey

      // Clear pending keys after a timeout
      pendingTimeout.value = setTimeout(() => {
        pendingKeys.value = ''
      }, 1000)
    }
  }

  function openShortcutsModal() {
    isShortcutsModalOpen.value = true
  }

  function closeShortcutsModal() {
    isShortcutsModalOpen.value = false
  }

  onMounted(() => {
    window.addEventListener('keydown', handleKeyDown)
  })

  onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyDown)
    if (pendingTimeout.value) {
      clearTimeout(pendingTimeout.value)
    }
  })

  return {
    shortcuts,
    isShortcutsModalOpen,
    openShortcutsModal,
    closeShortcutsModal,
    pendingKeys,
  }
}

// Export for global access
export { globalShortcuts, isShortcutsModalOpen }
