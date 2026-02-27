import { onMounted, onUnmounted, ref, computed } from 'vue'
import type { Ref, ComputedRef } from 'vue'
import { useRouter } from 'vue-router'
import type { Router } from 'vue-router'
import { useUiStore } from '@/stores/ui'

// Interfaces
interface ShortcutDefinition {
  key: string
  description: string
  route?: string
  action?: string
}

interface UseKeyboardShortcutsReturn {
  shortcuts: ComputedRef<ShortcutDefinition[]>
  isShortcutsModalOpen: Ref<boolean>
  openShortcutsModal: () => void
  closeShortcutsModal: () => void
  pendingKeys: Ref<string>
}

// Global shortcut definitions
const globalShortcuts: ShortcutDefinition[] = [
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
  { key: 'Ctrl+k', description: 'Command Palette öffnen', action: 'commandPalette' },
  { key: '?', description: 'Shortcuts anzeigen', action: 'showShortcuts' },
  { key: 'Escape', description: 'Modal/Dialog schließen', action: 'escape' },
  { key: 'n', description: 'Neu erstellen (kontextabhängig)', action: 'new' },
]

// Store for the keyboard shortcuts state
const isShortcutsModalOpen: Ref<boolean> = ref<boolean>(false)
const pendingKeys: Ref<string> = ref<string>('')
const pendingTimeout: Ref<ReturnType<typeof setTimeout> | null> = ref<ReturnType<typeof setTimeout> | null>(null)

export function useKeyboardShortcuts(): UseKeyboardShortcutsReturn {
  const router: Router = useRouter()
  const uiStore = useUiStore()

  const shortcuts: ComputedRef<ShortcutDefinition[]> = computed<ShortcutDefinition[]>(() => globalShortcuts)

  function isInputElement(element: EventTarget | null): boolean {
    const el = element as HTMLElement | null
    const tagName: string | undefined = el?.tagName?.toLowerCase()
    return tagName === 'input' ||
           tagName === 'textarea' ||
           tagName === 'select' ||
           el?.isContentEditable || false
  }

  function normalizeKey(e: KeyboardEvent): string {
    let key: string = e.key
    const parts: string[] = []

    if (e.ctrlKey || e.metaKey) parts.push('Ctrl')
    if (e.altKey) parts.push('Alt')
    if (e.shiftKey && key.length > 1) parts.push('Shift')

    // Normalize key
    if (key === ' ') key = 'Space'
    if (key.length === 1) key = key.toLowerCase()

    parts.push(key)
    return parts.join('+')
  }

  function executeAction(shortcut: ShortcutDefinition): boolean {
    if (shortcut.route) {
      router.push(shortcut.route)
      return true
    }

    switch (shortcut.action) {
      case 'search':
        document.dispatchEvent(new CustomEvent('toggle-global-search'))
        return true

      case 'commandPalette':
        document.dispatchEvent(new CustomEvent('toggle-command-palette'))
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

  function handleKeyDown(e: KeyboardEvent): void {
    // Ignore if in input field (except for some specific shortcuts)
    if (isInputElement(e.target)) {
      // Allow Ctrl+K for command palette even in input fields
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault()
        document.dispatchEvent(new CustomEvent('toggle-command-palette'))
        return
      }
      // Allow Escape
      if (e.key === 'Escape') {
        document.dispatchEvent(new CustomEvent('escape-pressed'))
        return
      }
      return
    }

    const normalizedKey: string = normalizeKey(e)

    // Clear pending timeout
    if (pendingTimeout.value) {
      clearTimeout(pendingTimeout.value)
      pendingTimeout.value = null
    }

    // Handle multi-key sequences (like 'g h')
    if (pendingKeys.value) {
      const fullKey: string = `${pendingKeys.value} ${normalizedKey}`
      const shortcut: ShortcutDefinition | undefined = globalShortcuts.find(s => s.key === fullKey)

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
    const singleKeyShortcut: ShortcutDefinition | undefined = globalShortcuts.find(s => s.key === normalizedKey && !s.key.includes(' '))
    if (singleKeyShortcut) {
      e.preventDefault()
      executeAction(singleKeyShortcut)
      return
    }

    // Check if this could be the start of a multi-key sequence
    const couldBeSequenceStart: boolean = globalShortcuts.some(s => s.key.startsWith(normalizedKey + ' '))
    if (couldBeSequenceStart) {
      pendingKeys.value = normalizedKey

      // Clear pending keys after a timeout
      pendingTimeout.value = setTimeout(() => {
        pendingKeys.value = ''
      }, 1000)
    }
  }

  function openShortcutsModal(): void {
    isShortcutsModalOpen.value = true
  }

  function closeShortcutsModal(): void {
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
