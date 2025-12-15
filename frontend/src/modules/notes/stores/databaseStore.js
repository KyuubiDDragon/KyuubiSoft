import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/core/api/axios'

export const useDatabaseStore = defineStore('noteDatabase', () => {
  // State
  const databases = ref({})  // Cached databases by ID
  const currentDatabase = ref(null)
  const isLoading = ref(false)
  const isSaving = ref(false)

  // Property type configurations
  const propertyTypes = [
    { value: 'text', label: 'Text', icon: 'Aa' },
    { value: 'number', label: 'Zahl', icon: '#' },
    { value: 'select', label: 'Auswahl', icon: '▼' },
    { value: 'multi_select', label: 'Multi-Auswahl', icon: '☰' },
    { value: 'date', label: 'Datum', icon: '📅' },
    { value: 'checkbox', label: 'Checkbox', icon: '☑' },
    { value: 'url', label: 'URL', icon: '🔗' },
    { value: 'email', label: 'E-Mail', icon: '✉' },
    { value: 'phone', label: 'Telefon', icon: '📞' },
    { value: 'person', label: 'Person', icon: '👤' },
    { value: 'relation', label: 'Relation', icon: '↗' },
    { value: 'created_time', label: 'Erstellt am', icon: '⏰' },
    { value: 'updated_time', label: 'Aktualisiert am', icon: '⏰' },
  ]

  // Select colors
  const selectColors = [
    { value: 'gray', label: 'Grau', class: 'bg-gray-500/20 text-gray-400' },
    { value: 'red', label: 'Rot', class: 'bg-red-500/20 text-red-400' },
    { value: 'orange', label: 'Orange', class: 'bg-orange-500/20 text-orange-400' },
    { value: 'yellow', label: 'Gelb', class: 'bg-yellow-500/20 text-yellow-400' },
    { value: 'green', label: 'Grün', class: 'bg-green-500/20 text-green-400' },
    { value: 'blue', label: 'Blau', class: 'bg-blue-500/20 text-blue-400' },
    { value: 'purple', label: 'Lila', class: 'bg-purple-500/20 text-purple-400' },
    { value: 'pink', label: 'Pink', class: 'bg-pink-500/20 text-pink-400' },
  ]

  // Actions

  /**
   * Create a new database
   */
  async function createDatabase(noteId, data = {}) {
    try {
      const response = await api.post('/api/v1/databases', {
        note_id: noteId,
        ...data
      })
      const database = response.data.data
      databases.value[database.id] = database
      return database
    } catch (error) {
      console.error('Error creating database:', error)
      throw error
    }
  }

  /**
   * Fetch a database with all data
   */
  async function fetchDatabase(databaseId) {
    isLoading.value = true
    try {
      const response = await api.get(`/api/v1/databases/${databaseId}`)
      const database = response.data.data
      databases.value[databaseId] = database
      currentDatabase.value = database
      return database
    } catch (error) {
      console.error('Error fetching database:', error)
      throw error
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Get cached database or fetch
   */
  async function getDatabase(databaseId) {
    if (databases.value[databaseId]) {
      currentDatabase.value = databases.value[databaseId]
      return databases.value[databaseId]
    }
    return fetchDatabase(databaseId)
  }

  /**
   * Update database settings
   */
  async function updateDatabase(databaseId, data) {
    isSaving.value = true
    try {
      const response = await api.put(`/api/v1/databases/${databaseId}`, data)
      const database = response.data.data
      if (databases.value[databaseId]) {
        databases.value[databaseId] = { ...databases.value[databaseId], ...database }
      }
      if (currentDatabase.value?.id === databaseId) {
        currentDatabase.value = { ...currentDatabase.value, ...database }
      }
      return database
    } catch (error) {
      console.error('Error updating database:', error)
      throw error
    } finally {
      isSaving.value = false
    }
  }

  /**
   * Delete a database
   */
  async function deleteDatabase(databaseId) {
    try {
      await api.delete(`/api/v1/databases/${databaseId}`)
      delete databases.value[databaseId]
      if (currentDatabase.value?.id === databaseId) {
        currentDatabase.value = null
      }
    } catch (error) {
      console.error('Error deleting database:', error)
      throw error
    }
  }

  /**
   * Duplicate a database
   */
  async function duplicateDatabase(databaseId, noteId = null) {
    try {
      const response = await api.post(`/api/v1/databases/${databaseId}/duplicate`, {
        note_id: noteId
      })
      const database = response.data.data
      databases.value[database.id] = database
      return database
    } catch (error) {
      console.error('Error duplicating database:', error)
      throw error
    }
  }

  // =========================================================================
  // PROPERTIES
  // =========================================================================

  /**
   * Add a property to database
   */
  async function addProperty(databaseId, data) {
    try {
      const response = await api.post(`/api/v1/databases/${databaseId}/properties`, data)
      const property = response.data.data

      // Update local cache
      if (databases.value[databaseId]) {
        databases.value[databaseId].properties.push(property)
      }
      if (currentDatabase.value?.id === databaseId) {
        currentDatabase.value.properties.push(property)
      }

      return property
    } catch (error) {
      console.error('Error adding property:', error)
      throw error
    }
  }

  /**
   * Update a property
   */
  async function updateProperty(databaseId, propertyId, data) {
    try {
      const response = await api.put(`/api/v1/databases/${databaseId}/properties/${propertyId}`, data)
      const property = response.data.data

      // Update local cache
      const updateProps = (props) => {
        const index = props.findIndex(p => p.id === propertyId)
        if (index !== -1) {
          props[index] = { ...props[index], ...property }
        }
      }

      if (databases.value[databaseId]?.properties) {
        updateProps(databases.value[databaseId].properties)
      }
      if (currentDatabase.value?.id === databaseId) {
        updateProps(currentDatabase.value.properties)
      }

      return property
    } catch (error) {
      console.error('Error updating property:', error)
      throw error
    }
  }

  /**
   * Delete a property
   */
  async function deleteProperty(databaseId, propertyId) {
    try {
      await api.delete(`/api/v1/databases/${databaseId}/properties/${propertyId}`)

      // Update local cache
      const removeFromProps = (props) => {
        const index = props.findIndex(p => p.id === propertyId)
        if (index !== -1) {
          props.splice(index, 1)
        }
      }

      if (databases.value[databaseId]?.properties) {
        removeFromProps(databases.value[databaseId].properties)
      }
      if (currentDatabase.value?.id === databaseId) {
        removeFromProps(currentDatabase.value.properties)
      }
    } catch (error) {
      console.error('Error deleting property:', error)
      throw error
    }
  }

  /**
   * Reorder properties
   */
  async function reorderProperties(databaseId, order) {
    try {
      await api.put(`/api/v1/databases/${databaseId}/properties/reorder`, { order })

      // Update local sort order
      const updateOrder = (props) => {
        order.forEach((propId, index) => {
          const prop = props.find(p => p.id === propId)
          if (prop) {
            prop.sort_order = index
          }
        })
        props.sort((a, b) => a.sort_order - b.sort_order)
      }

      if (databases.value[databaseId]?.properties) {
        updateOrder(databases.value[databaseId].properties)
      }
      if (currentDatabase.value?.id === databaseId) {
        updateOrder(currentDatabase.value.properties)
      }
    } catch (error) {
      console.error('Error reordering properties:', error)
      throw error
    }
  }

  // =========================================================================
  // ROWS
  // =========================================================================

  /**
   * Add a row
   */
  async function addRow(databaseId, cells = {}) {
    try {
      const response = await api.post(`/api/v1/databases/${databaseId}/rows`, { cells })
      const row = response.data.data

      // Update local cache
      if (databases.value[databaseId]?.rows) {
        databases.value[databaseId].rows.push(row)
      }
      if (currentDatabase.value?.id === databaseId) {
        currentDatabase.value.rows.push(row)
      }

      return row
    } catch (error) {
      console.error('Error adding row:', error)
      throw error
    }
  }

  /**
   * Update row cells
   */
  async function updateRow(databaseId, rowId, cells) {
    try {
      await api.put(`/api/v1/databases/${databaseId}/rows/${rowId}`, { cells })

      // Update local cache
      const updateRowCells = (rows) => {
        const row = rows.find(r => r.id === rowId)
        if (row) {
          row.cells = { ...row.cells, ...cells }
          row.updated_at = new Date().toISOString()
        }
      }

      if (databases.value[databaseId]?.rows) {
        updateRowCells(databases.value[databaseId].rows)
      }
      if (currentDatabase.value?.id === databaseId) {
        updateRowCells(currentDatabase.value.rows)
      }
    } catch (error) {
      console.error('Error updating row:', error)
      throw error
    }
  }

  /**
   * Delete a row
   */
  async function deleteRow(databaseId, rowId) {
    try {
      await api.delete(`/api/v1/databases/${databaseId}/rows/${rowId}`)

      // Update local cache
      const removeRow = (rows) => {
        const index = rows.findIndex(r => r.id === rowId)
        if (index !== -1) {
          rows.splice(index, 1)
        }
      }

      if (databases.value[databaseId]?.rows) {
        removeRow(databases.value[databaseId].rows)
      }
      if (currentDatabase.value?.id === databaseId) {
        removeRow(currentDatabase.value.rows)
      }
    } catch (error) {
      console.error('Error deleting row:', error)
      throw error
    }
  }

  /**
   * Reorder rows
   */
  async function reorderRows(databaseId, order) {
    try {
      await api.put(`/api/v1/databases/${databaseId}/rows/reorder`, { order })

      // Update local sort order
      const updateOrder = (rows) => {
        order.forEach((rowId, index) => {
          const row = rows.find(r => r.id === rowId)
          if (row) {
            row.sort_order = index
          }
        })
        rows.sort((a, b) => a.sort_order - b.sort_order)
      }

      if (databases.value[databaseId]?.rows) {
        updateOrder(databases.value[databaseId].rows)
      }
      if (currentDatabase.value?.id === databaseId) {
        updateOrder(currentDatabase.value.rows)
      }
    } catch (error) {
      console.error('Error reordering rows:', error)
      throw error
    }
  }

  // =========================================================================
  // VIEWS
  // =========================================================================

  /**
   * Create a view
   */
  async function createView(databaseId, data) {
    try {
      const response = await api.post(`/api/v1/databases/${databaseId}/views`, data)
      const view = response.data.data

      // Update local cache
      if (databases.value[databaseId]?.views) {
        databases.value[databaseId].views.push(view)
      }
      if (currentDatabase.value?.id === databaseId) {
        currentDatabase.value.views.push(view)
      }

      return view
    } catch (error) {
      console.error('Error creating view:', error)
      throw error
    }
  }

  /**
   * Update a view
   */
  async function updateView(databaseId, viewId, data) {
    try {
      await api.put(`/api/v1/databases/${databaseId}/views/${viewId}`, data)

      // Update local cache
      const updateViewData = (views) => {
        const view = views.find(v => v.id === viewId)
        if (view) {
          Object.assign(view, data)
        }
      }

      if (databases.value[databaseId]?.views) {
        updateViewData(databases.value[databaseId].views)
      }
      if (currentDatabase.value?.id === databaseId) {
        updateViewData(currentDatabase.value.views)
      }
    } catch (error) {
      console.error('Error updating view:', error)
      throw error
    }
  }

  /**
   * Delete a view
   */
  async function deleteView(databaseId, viewId) {
    try {
      await api.delete(`/api/v1/databases/${databaseId}/views/${viewId}`)

      // Update local cache
      const removeView = (views) => {
        const index = views.findIndex(v => v.id === viewId)
        if (index !== -1) {
          views.splice(index, 1)
        }
      }

      if (databases.value[databaseId]?.views) {
        removeView(databases.value[databaseId].views)
      }
      if (currentDatabase.value?.id === databaseId) {
        removeView(currentDatabase.value.views)
      }
    } catch (error) {
      console.error('Error deleting view:', error)
      throw error
    }
  }

  // =========================================================================
  // HELPERS
  // =========================================================================

  /**
   * Get color class for select option
   */
  function getColorClass(color) {
    const colorConfig = selectColors.find(c => c.value === color)
    return colorConfig?.class || 'bg-gray-500/20 text-gray-400'
  }

  /**
   * Clear current database
   */
  function clearCurrentDatabase() {
    currentDatabase.value = null
  }

  /**
   * Invalidate cache for a database
   */
  function invalidateCache(databaseId) {
    delete databases.value[databaseId]
  }

  return {
    // State
    databases,
    currentDatabase,
    isLoading,
    isSaving,
    propertyTypes,
    selectColors,

    // Database actions
    createDatabase,
    fetchDatabase,
    getDatabase,
    updateDatabase,
    deleteDatabase,
    duplicateDatabase,

    // Property actions
    addProperty,
    updateProperty,
    deleteProperty,
    reorderProperties,

    // Row actions
    addRow,
    updateRow,
    deleteRow,
    reorderRows,

    // View actions
    createView,
    updateView,
    deleteView,

    // Helpers
    getColorClass,
    clearCurrentDatabase,
    invalidateCache
  }
})
