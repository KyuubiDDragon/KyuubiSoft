import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/core/api/axios'

export interface DatabaseProperty {
  id: string
  name?: string
  type: string
  sort_order: number
  options?: SelectOption[]
  [key: string]: unknown
}

export interface SelectOption {
  id?: string
  label: string
  color?: string
  [key: string]: unknown
}

export interface DatabaseRow {
  id: string
  cells: Record<string, unknown>
  sort_order: number
  created_at?: string
  updated_at?: string
  [key: string]: unknown
}

export interface DatabaseView {
  id: string
  name?: string
  type?: string
  filters?: Record<string, unknown>[]
  sorts?: Record<string, unknown>[]
  [key: string]: unknown
}

export interface Database {
  id: string
  note_id?: string
  name?: string
  properties: DatabaseProperty[]
  rows: DatabaseRow[]
  views: DatabaseView[]
  [key: string]: unknown
}

export interface PropertyTypeConfig {
  value: string
  label: string
  icon: string
}

export interface SelectColorConfig {
  value: string
  label: string
  class: string
}

export const useDatabaseStore = defineStore('noteDatabase', () => {
  // State
  const databases = ref<Record<string, Database>>({})  // Cached databases by ID
  const currentDatabase = ref<Database | null>(null)
  const isLoading = ref<boolean>(false)
  const isSaving = ref<boolean>(false)

  // Property type configurations
  const propertyTypes: PropertyTypeConfig[] = [
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
  const selectColors: SelectColorConfig[] = [
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
  async function createDatabase(noteId: string, data: Partial<Database> = {}): Promise<Database> {
    try {
      const response = await api.post('/api/v1/databases', {
        note_id: noteId,
        ...data
      })
      const database: Database = response.data.data
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
  async function fetchDatabase(databaseId: string): Promise<Database> {
    isLoading.value = true
    try {
      const response = await api.get(`/api/v1/databases/${databaseId}`)
      const database: Database = response.data.data
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
  async function getDatabase(databaseId: string): Promise<Database> {
    if (databases.value[databaseId]) {
      currentDatabase.value = databases.value[databaseId]
      return databases.value[databaseId]
    }
    return fetchDatabase(databaseId)
  }

  /**
   * Update database settings
   */
  async function updateDatabase(databaseId: string, data: Partial<Database>): Promise<Database> {
    isSaving.value = true
    try {
      const response = await api.put(`/api/v1/databases/${databaseId}`, data)
      const database: Database = response.data.data
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
  async function deleteDatabase(databaseId: string): Promise<void> {
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
  async function duplicateDatabase(databaseId: string, noteId: string | null = null): Promise<Database> {
    try {
      const response = await api.post(`/api/v1/databases/${databaseId}/duplicate`, {
        note_id: noteId
      })
      const database: Database = response.data.data
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
  async function addProperty(databaseId: string, data: Partial<DatabaseProperty>): Promise<DatabaseProperty> {
    try {
      const response = await api.post(`/api/v1/databases/${databaseId}/properties`, data)
      const property: DatabaseProperty = response.data.data

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
  async function updateProperty(databaseId: string, propertyId: string, data: Partial<DatabaseProperty>): Promise<DatabaseProperty> {
    try {
      const response = await api.put(`/api/v1/databases/${databaseId}/properties/${propertyId}`, data)
      const property: DatabaseProperty = response.data.data

      // Update local cache
      const updateProps = (props: DatabaseProperty[]): void => {
        const index = props.findIndex((p: DatabaseProperty) => p.id === propertyId)
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
  async function deleteProperty(databaseId: string, propertyId: string): Promise<void> {
    try {
      await api.delete(`/api/v1/databases/${databaseId}/properties/${propertyId}`)

      // Update local cache
      const removeFromProps = (props: DatabaseProperty[]): void => {
        const index = props.findIndex((p: DatabaseProperty) => p.id === propertyId)
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
  async function reorderProperties(databaseId: string, order: string[]): Promise<void> {
    try {
      await api.put(`/api/v1/databases/${databaseId}/properties/reorder`, { order })

      // Update local sort order
      const updateOrder = (props: DatabaseProperty[]): void => {
        order.forEach((propId: string, index: number) => {
          const prop = props.find((p: DatabaseProperty) => p.id === propId)
          if (prop) {
            prop.sort_order = index
          }
        })
        props.sort((a: DatabaseProperty, b: DatabaseProperty) => a.sort_order - b.sort_order)
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
  async function addRow(databaseId: string, cells: Record<string, unknown> = {}): Promise<DatabaseRow> {
    try {
      const response = await api.post(`/api/v1/databases/${databaseId}/rows`, { cells })
      const row: DatabaseRow = response.data.data

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
  async function updateRow(databaseId: string, rowId: string, cells: Record<string, unknown>): Promise<void> {
    try {
      await api.put(`/api/v1/databases/${databaseId}/rows/${rowId}`, { cells })

      // Update local cache
      const updateRowCells = (rows: DatabaseRow[]): void => {
        const row = rows.find((r: DatabaseRow) => r.id === rowId)
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
  async function deleteRow(databaseId: string, rowId: string): Promise<void> {
    try {
      await api.delete(`/api/v1/databases/${databaseId}/rows/${rowId}`)

      // Update local cache
      const removeRow = (rows: DatabaseRow[]): void => {
        const index = rows.findIndex((r: DatabaseRow) => r.id === rowId)
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
  async function reorderRows(databaseId: string, order: string[]): Promise<void> {
    try {
      await api.put(`/api/v1/databases/${databaseId}/rows/reorder`, { order })

      // Update local sort order
      const updateOrder = (rows: DatabaseRow[]): void => {
        order.forEach((rowId: string, index: number) => {
          const row = rows.find((r: DatabaseRow) => r.id === rowId)
          if (row) {
            row.sort_order = index
          }
        })
        rows.sort((a: DatabaseRow, b: DatabaseRow) => a.sort_order - b.sort_order)
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
  async function createView(databaseId: string, data: Partial<DatabaseView>): Promise<DatabaseView> {
    try {
      const response = await api.post(`/api/v1/databases/${databaseId}/views`, data)
      const view: DatabaseView = response.data.data

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
  async function updateView(databaseId: string, viewId: string, data: Partial<DatabaseView>): Promise<void> {
    try {
      await api.put(`/api/v1/databases/${databaseId}/views/${viewId}`, data)

      // Update local cache
      const updateViewData = (views: DatabaseView[]): void => {
        const view = views.find((v: DatabaseView) => v.id === viewId)
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
  async function deleteView(databaseId: string, viewId: string): Promise<void> {
    try {
      await api.delete(`/api/v1/databases/${databaseId}/views/${viewId}`)

      // Update local cache
      const removeView = (views: DatabaseView[]): void => {
        const index = views.findIndex((v: DatabaseView) => v.id === viewId)
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
  function getColorClass(color: string): string {
    const colorConfig = selectColors.find((c: SelectColorConfig) => c.value === color)
    return colorConfig?.class || 'bg-gray-500/20 text-gray-400'
  }

  /**
   * Clear current database
   */
  function clearCurrentDatabase(): void {
    currentDatabase.value = null
  }

  /**
   * Invalidate cache for a database
   */
  function invalidateCache(databaseId: string): void {
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
