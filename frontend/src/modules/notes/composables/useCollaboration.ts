import { ref, computed, watch, onUnmounted, type Ref, type ComputedRef } from 'vue'
import { useCollaborationStore } from '../stores/collaborationStore'
import type { Editor } from '@tiptap/core'

/**
 * Cursor decoration data for rendering remote cursors
 */
export interface CursorDecoration {
  userId: string
  position: number
  user: CollaborationUser | undefined
  color: string
}

/**
 * Selection decoration data for rendering remote selections
 */
export interface SelectionDecoration {
  userId: string
  from: number
  to: number
  user: CollaborationUser | undefined
  color: string
}

/**
 * Remote update payload from collaboration server
 */
export interface RemoteUpdateData {
  update: string
  userId?: string
  [key: string]: unknown
}

/**
 * Sync response payload from collaboration server
 */
export interface SyncResponseData {
  document?: unknown
  [key: string]: unknown
}

/**
 * Collaboration user info
 */
export interface CollaborationUser {
  name?: string
  color?: string
  [key: string]: unknown
}

/**
 * Cursor data from the collaboration store
 */
export interface CursorData {
  position?: number
  user?: CollaborationUser
  [key: string]: unknown
}

/**
 * Selection data from the collaboration store
 */
export interface SelectionData {
  from?: number
  to?: number
  user?: CollaborationUser
  [key: string]: unknown
}

/**
 * Options for the useCollaboration composable
 */
export interface UseCollaborationOptions {
  editor: Ref<Editor | null>
  noteId: string
  onRemoteUpdate?: (data: RemoteUpdateData) => void
}

/**
 * Return type of the useCollaboration composable
 */
export interface UseCollaborationReturn {
  isCollaborating: Ref<boolean>
  syncError: Ref<string | null>
  participants: ComputedRef<unknown[]>
  otherParticipants: ComputedRef<unknown[]>
  cursors: ComputedRef<Record<string, CursorData>>
  selections: ComputedRef<Record<string, SelectionData>>
  startCollaboration: () => Promise<void>
  stopCollaboration: () => void
  sendUpdate: (content: string | object) => void
  sendCursorPosition: (position: number) => void
  sendSelection: (from: number, to: number) => void
  sendAwareness: (data: Record<string, unknown>) => void
  getCursorDecorations: () => CursorDecoration[]
  getSelectionDecorations: () => SelectionDecoration[]
}

/**
 * Composable for TipTap collaboration integration
 */
export function useCollaboration({ editor, noteId, onRemoteUpdate }: UseCollaborationOptions): UseCollaborationReturn {
  const collaborationStore = useCollaborationStore()

  const isCollaborating = ref<boolean>(false)
  const syncError = ref<string | null>(null)
  const lastLocalUpdate = ref<string | null>(null)

  // Throttle cursor updates
  let cursorThrottle: ReturnType<typeof setTimeout> | null = null
  const CURSOR_THROTTLE_MS = 50

  // Throttle selection updates
  let selectionThrottle: ReturnType<typeof setTimeout> | null = null
  const SELECTION_THROTTLE_MS = 100

  /**
   * Start collaboration for this note
   */
  async function startCollaboration(): Promise<void> {
    if (!noteId) return

    try {
      await collaborationStore.connect()
      await collaborationStore.joinRoom(noteId)
      isCollaborating.value = true

      // Register update handler
      collaborationStore.onMessage('update', handleRemoteUpdate)

      // Register sync handler
      collaborationStore.onMessage('sync_response', handleSyncResponse)
    } catch (error: unknown) {
      console.error('Failed to start collaboration:', error)
      syncError.value = error instanceof Error ? error.message : String(error)
    }
  }

  /**
   * Stop collaboration
   */
  function stopCollaboration(): void {
    collaborationStore.leaveRoom()
    isCollaborating.value = false
  }

  /**
   * Handle remote update from another user
   */
  function handleRemoteUpdate(data: RemoteUpdateData): void {
    // Ignore our own updates
    if (data.update === lastLocalUpdate.value) {
      return
    }

    if (onRemoteUpdate) {
      onRemoteUpdate(data)
    }

    // Apply the update to the editor
    // This is a simplified approach - for production, use Y.js or similar CRDT
    if (editor.value && data.update) {
      try {
        // Parse the update as JSON if it's document content
        if (typeof data.update === 'string') {
          const content = JSON.parse(data.update)

          // Store current selection
          const { from, to } = editor.value.state.selection

          // Update content
          editor.value.commands.setContent(content, false, {
            preserveWhitespace: 'full'
          })

          // Restore selection if possible
          const docSize = editor.value.state.doc.content.size
          const newFrom = Math.min(from, docSize)
          const newTo = Math.min(to, docSize)
          editor.value.commands.setTextSelection({ from: newFrom, to: newTo })
        }
      } catch (error: unknown) {
        console.error('Failed to apply remote update:', error)
      }
    }
  }

  /**
   * Handle sync response
   */
  function handleSyncResponse(_data: SyncResponseData): void {
    // Handle full sync if needed - data contains the current document state
  }

  /**
   * Send local update to server
   */
  function sendUpdate(content: string | object): void {
    if (!isCollaborating.value) return

    const update = typeof content === 'string' ? content : JSON.stringify(content)
    lastLocalUpdate.value = update
    collaborationStore.sendUpdate(update)
  }

  /**
   * Send cursor position
   */
  function sendCursorPosition(position: number): void {
    if (!isCollaborating.value) return

    // Throttle cursor updates
    if (cursorThrottle) return
    cursorThrottle = setTimeout(() => {
      cursorThrottle = null
    }, CURSOR_THROTTLE_MS)

    collaborationStore.sendCursor({
      position: position,
      timestamp: Date.now(),
    })
  }

  /**
   * Send selection range
   */
  function sendSelection(from: number, to: number): void {
    if (!isCollaborating.value) return

    // Throttle selection updates
    if (selectionThrottle) return
    selectionThrottle = setTimeout(() => {
      selectionThrottle = null
    }, SELECTION_THROTTLE_MS)

    collaborationStore.sendSelection({
      from,
      to,
      timestamp: Date.now(),
    })
  }

  /**
   * Send awareness (typing status, etc.)
   */
  function sendAwareness(data: Record<string, unknown>): void {
    if (!isCollaborating.value) return
    collaborationStore.sendAwareness(data)
  }

  /**
   * Get cursor decorations for other users
   */
  function getCursorDecorations(): CursorDecoration[] {
    const cursors = collaborationStore.cursors as Record<string, CursorData>
    const decorations: CursorDecoration[] = []

    for (const [userId, cursorData] of Object.entries(cursors)) {
      if (cursorData && cursorData.position !== undefined) {
        decorations.push({
          userId,
          position: cursorData.position,
          user: cursorData.user,
          color: cursorData.user?.color || '#6366F1',
        })
      }
    }

    return decorations
  }

  /**
   * Get selection decorations for other users
   */
  function getSelectionDecorations(): SelectionDecoration[] {
    const selections = collaborationStore.selections as Record<string, SelectionData>
    const decorations: SelectionDecoration[] = []

    for (const [userId, selectionData] of Object.entries(selections)) {
      if (selectionData && selectionData.from !== undefined) {
        decorations.push({
          userId,
          from: selectionData.from,
          to: selectionData.to!,
          user: selectionData.user,
          color: selectionData.user?.color || '#6366F1',
        })
      }
    }

    return decorations
  }

  // Watch for editor selection changes
  watch(
    () => editor.value?.state?.selection,
    (selection) => {
      if (selection && isCollaborating.value) {
        sendCursorPosition(selection.anchor)
        if (selection.from !== selection.to) {
          sendSelection(selection.from, selection.to)
        }
      }
    },
    { deep: true }
  )

  // Cleanup on unmount
  onUnmounted(() => {
    stopCollaboration()
  })

  return {
    // State
    isCollaborating,
    syncError,
    participants: computed(() => collaborationStore.participants),
    otherParticipants: computed(() => collaborationStore.otherParticipants),
    cursors: computed(() => collaborationStore.cursors),
    selections: computed(() => collaborationStore.selections),

    // Actions
    startCollaboration,
    stopCollaboration,
    sendUpdate,
    sendCursorPosition,
    sendSelection,
    sendAwareness,
    getCursorDecorations,
    getSelectionDecorations,
  }
}
