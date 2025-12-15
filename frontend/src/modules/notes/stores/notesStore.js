import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/core/api/axios'

export const useNotesStore = defineStore('notes', () => {
  // State
  const notes = ref([])
  const noteTree = ref([])
  const currentNote = ref(null)
  const recentNotes = ref([])
  const favoriteNotes = ref([])
  const trashedNotes = ref([])
  const templates = ref({ system_templates: [], custom_templates: [], note_templates: [] })
  const searchResults = ref([])
  const stats = ref(null)

  const isLoading = ref(false)
  const isLoadingTree = ref(false)
  const isLoadingNote = ref(false)
  const isSaving = ref(false)

  // Computed
  const pinnedNotes = computed(() =>
    notes.value.filter(n => n.is_pinned && !n.is_archived)
  )

  const archivedNotes = computed(() =>
    notes.value.filter(n => n.is_archived)
  )

  const rootNotes = computed(() =>
    notes.value.filter(n => !n.parent_id && !n.is_archived && !n.is_template)
  )

  // Actions

  /**
   * Load all notes (flat list)
   */
  async function fetchNotes(params = {}) {
    isLoading.value = true
    try {
      const response = await api.get('/api/v1/notes', { params })
      notes.value = response.data.data?.items || response.data.data || []
      return notes.value
    } catch (error) {
      console.error('Error fetching notes:', error)
      throw error
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Load note tree for sidebar
   */
  async function fetchTree() {
    isLoadingTree.value = true
    try {
      const response = await api.get('/api/v1/notes/tree')
      noteTree.value = response.data.data || []
      return noteTree.value
    } catch (error) {
      console.error('Error fetching note tree:', error)
      throw error
    } finally {
      isLoadingTree.value = false
    }
  }

  /**
   * Load single note with full details
   */
  async function fetchNote(noteId) {
    isLoadingNote.value = true
    try {
      const response = await api.get(`/api/v1/notes/${noteId}`)
      currentNote.value = response.data.data
      return currentNote.value
    } catch (error) {
      console.error('Error fetching note:', error)
      throw error
    } finally {
      isLoadingNote.value = false
    }
  }

  /**
   * Create a new note
   */
  async function createNote(data) {
    try {
      const response = await api.post('/api/v1/notes', data)
      const newNote = response.data.data

      // Update local state
      notes.value.unshift(newNote)
      await fetchTree()

      return newNote
    } catch (error) {
      console.error('Error creating note:', error)
      throw error
    }
  }

  /**
   * Update a note
   */
  async function updateNote(noteId, data) {
    isSaving.value = true
    try {
      const response = await api.put(`/api/v1/notes/${noteId}`, data)
      const updatedNote = response.data.data

      // Update in notes array
      const index = notes.value.findIndex(n => n.id === noteId)
      if (index !== -1) {
        notes.value[index] = { ...notes.value[index], ...updatedNote }
      }

      // Update current note if it's the one being edited
      if (currentNote.value?.id === noteId) {
        currentNote.value = { ...currentNote.value, ...updatedNote }
      }

      return updatedNote
    } catch (error) {
      console.error('Error updating note:', error)
      throw error
    } finally {
      isSaving.value = false
    }
  }

  /**
   * Delete a note (soft delete)
   */
  async function deleteNote(noteId) {
    try {
      await api.delete(`/api/v1/notes/${noteId}`)

      // Remove from local state
      notes.value = notes.value.filter(n => n.id !== noteId)

      // Clear current note if it was deleted
      if (currentNote.value?.id === noteId) {
        currentNote.value = null
      }

      await fetchTree()
    } catch (error) {
      console.error('Error deleting note:', error)
      throw error
    }
  }

  /**
   * Move note to new parent
   */
  async function moveNote(noteId, parentId) {
    try {
      await api.put(`/api/v1/notes/${noteId}/move`, { parent_id: parentId })
      await fetchTree()
    } catch (error) {
      console.error('Error moving note:', error)
      throw error
    }
  }

  /**
   * Reorder notes
   */
  async function reorderNotes(items) {
    try {
      await api.put('/api/v1/notes/reorder', { items })
    } catch (error) {
      console.error('Error reordering notes:', error)
      throw error
    }
  }

  /**
   * Toggle favorite
   */
  async function toggleFavorite(noteId) {
    const note = notes.value.find(n => n.id === noteId) || currentNote.value
    if (!note) return

    try {
      if (note.is_favorite) {
        await api.delete(`/api/v1/notes/${noteId}/favorite`)
      } else {
        await api.post(`/api/v1/notes/${noteId}/favorite`)
      }

      // Update local state
      if (note) {
        note.is_favorite = !note.is_favorite
      }
      if (currentNote.value?.id === noteId) {
        currentNote.value.is_favorite = !currentNote.value.is_favorite
      }

      await fetchFavorites()
    } catch (error) {
      console.error('Error toggling favorite:', error)
      throw error
    }
  }

  /**
   * Toggle pin
   */
  async function togglePin(noteId) {
    const note = notes.value.find(n => n.id === noteId) || currentNote.value
    if (!note) return

    try {
      if (note.is_pinned) {
        await api.delete(`/api/v1/notes/${noteId}/pin`)
      } else {
        await api.post(`/api/v1/notes/${noteId}/pin`)
      }

      // Update local state
      if (note) {
        note.is_pinned = !note.is_pinned
      }
      if (currentNote.value?.id === noteId) {
        currentNote.value.is_pinned = !currentNote.value.is_pinned
      }

      await fetchTree()
    } catch (error) {
      console.error('Error toggling pin:', error)
      throw error
    }
  }

  /**
   * Load recent notes
   */
  async function fetchRecent(limit = 10) {
    try {
      const response = await api.get('/api/v1/notes/recent', { params: { limit } })
      recentNotes.value = response.data.data || []
      return recentNotes.value
    } catch (error) {
      console.error('Error fetching recent notes:', error)
      throw error
    }
  }

  /**
   * Load favorite notes
   */
  async function fetchFavorites() {
    try {
      const response = await api.get('/api/v1/notes/favorites')
      favoriteNotes.value = response.data.data || []
      return favoriteNotes.value
    } catch (error) {
      console.error('Error fetching favorites:', error)
      throw error
    }
  }

  /**
   * Load trashed notes
   */
  async function fetchTrash() {
    try {
      const response = await api.get('/api/v1/notes/trash')
      trashedNotes.value = response.data.data || []
      return trashedNotes.value
    } catch (error) {
      console.error('Error fetching trash:', error)
      throw error
    }
  }

  /**
   * Restore note from trash
   */
  async function restoreNote(noteId) {
    try {
      await api.post(`/api/v1/notes/${noteId}/restore`)
      trashedNotes.value = trashedNotes.value.filter(n => n.id !== noteId)
      await fetchTree()
    } catch (error) {
      console.error('Error restoring note:', error)
      throw error
    }
  }

  /**
   * Permanently delete note
   */
  async function permanentDeleteNote(noteId) {
    try {
      await api.delete(`/api/v1/notes/${noteId}/permanent`)
      trashedNotes.value = trashedNotes.value.filter(n => n.id !== noteId)
    } catch (error) {
      console.error('Error permanently deleting note:', error)
      throw error
    }
  }

  /**
   * Empty trash
   */
  async function emptyTrash() {
    try {
      await api.delete('/api/v1/notes/trash')
      trashedNotes.value = []
    } catch (error) {
      console.error('Error emptying trash:', error)
      throw error
    }
  }

  /**
   * Search notes
   */
  async function search(query, limit = 20) {
    try {
      const response = await api.get('/api/v1/notes/search', {
        params: { q: query, limit }
      })
      searchResults.value = response.data.data || []
      return searchResults.value
    } catch (error) {
      console.error('Error searching notes:', error)
      throw error
    }
  }

  /**
   * Get search suggestions (for wiki links)
   */
  async function getSuggestions(query) {
    try {
      const response = await api.get('/api/v1/notes/search/suggestions', {
        params: { q: query }
      })
      return response.data.data || []
    } catch (error) {
      console.error('Error getting suggestions:', error)
      return []
    }
  }

  /**
   * Load templates
   */
  async function fetchTemplates() {
    try {
      const response = await api.get('/api/v1/notes/templates')
      templates.value = response.data.data || { system_templates: [], custom_templates: [], note_templates: [] }
      return templates.value
    } catch (error) {
      console.error('Error fetching templates:', error)
      throw error
    }
  }

  /**
   * Get templates (alias)
   */
  async function getTemplates() {
    return fetchTemplates()
  }

  /**
   * Create note from template
   */
  async function createFromTemplate(templateId, data = {}) {
    try {
      const response = await api.post(`/api/v1/notes/from-template/${templateId}`, data)
      const newNote = response.data.data
      notes.value.unshift(newNote)
      await fetchTree()
      return newNote
    } catch (error) {
      console.error('Error creating from template:', error)
      throw error
    }
  }

  /**
   * Duplicate note
   */
  async function duplicateNote(noteId) {
    try {
      const response = await api.post(`/api/v1/notes/${noteId}/duplicate`)
      const newNote = response.data.data
      notes.value.unshift(newNote)
      await fetchTree()
      return newNote
    } catch (error) {
      console.error('Error duplicating note:', error)
      throw error
    }
  }

  /**
   * Get note versions
   */
  async function fetchVersions(noteId) {
    try {
      const response = await api.get(`/api/v1/notes/${noteId}/versions`)
      return response.data.data || []
    } catch (error) {
      console.error('Error fetching versions:', error)
      throw error
    }
  }

  /**
   * Get note versions (alias)
   */
  async function getVersions(noteId) {
    return fetchVersions(noteId)
  }

  /**
   * Get single version with content
   */
  async function getVersion(noteId, versionId) {
    try {
      const response = await api.get(`/api/v1/notes/${noteId}/versions/${versionId}`)
      return response.data.data
    } catch (error) {
      console.error('Error fetching version:', error)
      throw error
    }
  }

  /**
   * Restore version
   */
  async function restoreVersion(noteId, versionId) {
    try {
      const response = await api.post(`/api/v1/notes/${noteId}/versions/${versionId}/restore`)
      // Reload current note
      if (currentNote.value?.id === noteId) {
        await fetchNote(noteId)
      }
      return response.data.data
    } catch (error) {
      console.error('Error restoring version:', error)
      throw error
    }
  }

  /**
   * Get backlinks
   */
  async function fetchBacklinks(noteId) {
    try {
      const response = await api.get(`/api/v1/notes/${noteId}/backlinks`)
      return response.data.data || []
    } catch (error) {
      console.error('Error fetching backlinks:', error)
      throw error
    }
  }

  /**
   * Load stats
   */
  async function fetchStats() {
    try {
      const response = await api.get('/api/v1/notes/stats')
      stats.value = response.data.data
      return stats.value
    } catch (error) {
      console.error('Error fetching stats:', error)
      throw error
    }
  }

  /**
   * Get note by slug
   */
  async function fetchNoteBySlug(slug) {
    try {
      const response = await api.get(`/api/v1/notes/by-slug/${slug}`)
      return response.data.data
    } catch (error) {
      console.error('Error fetching note by slug:', error)
      throw error
    }
  }

  /**
   * Clear current note
   */
  function clearCurrentNote() {
    currentNote.value = null
  }

  /**
   * Initialize store (load initial data)
   */
  async function initialize() {
    await Promise.all([
      fetchTree(),
      fetchRecent(),
      fetchFavorites()
    ])
  }

  return {
    // State
    notes,
    noteTree,
    currentNote,
    recentNotes,
    favoriteNotes,
    trashedNotes,
    templates,
    searchResults,
    stats,
    isLoading,
    isLoadingTree,
    isLoadingNote,
    isSaving,

    // Computed
    pinnedNotes,
    archivedNotes,
    rootNotes,

    // Actions
    fetchNotes,
    fetchTree,
    fetchNote,
    createNote,
    updateNote,
    deleteNote,
    moveNote,
    reorderNotes,
    toggleFavorite,
    togglePin,
    fetchRecent,
    fetchFavorites,
    fetchTrash,
    restoreNote,
    permanentDeleteNote,
    emptyTrash,
    search,
    getSuggestions,
    fetchTemplates,
    getTemplates,
    createFromTemplate,
    duplicateNote,
    fetchVersions,
    getVersions,
    getVersion,
    restoreVersion,
    fetchBacklinks,
    fetchStats,
    fetchNoteBySlug,
    clearCurrentNote,
    initialize
  }
})
