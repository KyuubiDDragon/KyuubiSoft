import { ref, computed, watch, onUnmounted } from 'vue'
import { useCollaborationStore } from '../stores/collaborationStore'

/**
 * Composable for TipTap collaboration integration
 *
 * @param {Object} options
 * @param {Ref<Editor>} options.editor - TipTap editor instance
 * @param {string} options.noteId - Current note ID
 * @param {Function} options.onRemoteUpdate - Callback for remote updates
 */
export function useCollaboration({ editor, noteId, onRemoteUpdate }) {
  const collaborationStore = useCollaborationStore()

  const isCollaborating = ref(false)
  const syncError = ref(null)
  const lastLocalUpdate = ref(null)

  // Throttle cursor updates
  let cursorThrottle = null
  const CURSOR_THROTTLE_MS = 50

  // Throttle selection updates
  let selectionThrottle = null
  const SELECTION_THROTTLE_MS = 100

  /**
   * Start collaboration for this note
   */
  async function startCollaboration() {
    if (!noteId) return

    try {
      await collaborationStore.connect()
      await collaborationStore.joinRoom(noteId)
      isCollaborating.value = true

      // Register update handler
      collaborationStore.onMessage('update', handleRemoteUpdate)

      // Register sync handler
      collaborationStore.onMessage('sync_response', handleSyncResponse)

      console.log('Collaboration started for note:', noteId)
    } catch (error) {
      console.error('Failed to start collaboration:', error)
      syncError.value = error.message
    }
  }

  /**
   * Stop collaboration
   */
  function stopCollaboration() {
    collaborationStore.leaveRoom()
    isCollaborating.value = false
  }

  /**
   * Handle remote update from another user
   */
  function handleRemoteUpdate(data) {
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
      } catch (error) {
        console.error('Failed to apply remote update:', error)
      }
    }
  }

  /**
   * Handle sync response
   */
  function handleSyncResponse(data) {
    console.log('Sync response received:', data)
    // Handle full sync if needed
  }

  /**
   * Send local update to server
   */
  function sendUpdate(content) {
    if (!isCollaborating.value) return

    const update = typeof content === 'string' ? content : JSON.stringify(content)
    lastLocalUpdate.value = update
    collaborationStore.sendUpdate(update)
  }

  /**
   * Send cursor position
   */
  function sendCursorPosition(position) {
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
  function sendSelection(from, to) {
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
  function sendAwareness(data) {
    if (!isCollaborating.value) return
    collaborationStore.sendAwareness(data)
  }

  /**
   * Get cursor decorations for other users
   */
  function getCursorDecorations() {
    const cursors = collaborationStore.cursors
    const decorations = []

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
  function getSelectionDecorations() {
    const selections = collaborationStore.selections
    const decorations = []

    for (const [userId, selectionData] of Object.entries(selections)) {
      if (selectionData && selectionData.from !== undefined) {
        decorations.push({
          userId,
          from: selectionData.from,
          to: selectionData.to,
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
