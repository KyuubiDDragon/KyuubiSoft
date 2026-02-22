import { defineStore } from 'pinia'
import { ref, computed, watch } from 'vue'
import api from '@/core/api/axios'

interface Project {
  id: string
  name: string
  status: 'active' | 'archived' | 'completed'
  color?: string
  [key: string]: unknown
}

export const useProjectStore = defineStore('project', () => {
  // State
  const projects = ref<Project[]>([])
  const selectedProjectId = ref<string | null>(null)
  const isLoading = ref<boolean>(false)

  // Initialize from localStorage
  const storedProjectId = localStorage.getItem('selectedProjectId')
  if (storedProjectId && storedProjectId !== 'null') {
    selectedProjectId.value = storedProjectId
  }

  // Computed
  const selectedProject = computed<Project | null>(() => {
    if (!selectedProjectId.value) return null
    return projects.value.find(p => p.id === selectedProjectId.value) || null
  })

  const activeProjects = computed<Project[]>(() => {
    return projects.value.filter(p => p.status === 'active')
  })

  // Watch for changes and persist to localStorage
  watch(selectedProjectId, (value) => {
    localStorage.setItem('selectedProjectId', value || '')
  })

  // Actions
  async function loadProjects(): Promise<void> {
    isLoading.value = true
    try {
      const response = await api.get('/api/v1/projects')
      projects.value = response.data.data?.items || []

      // Validate that selected project still exists
      if (selectedProjectId.value && !projects.value.find(p => p.id === selectedProjectId.value)) {
        selectedProjectId.value = null
      }
    } catch (error) {
      console.error('Failed to load projects:', error)
    } finally {
      isLoading.value = false
    }
  }

  function selectProject(projectId: string): void {
    selectedProjectId.value = projectId
  }

  function clearSelection(): void {
    selectedProjectId.value = null
  }

  // Helper to get project_id filter param for API calls
  function getProjectFilter(): Record<string, string> {
    return selectedProjectId.value ? { project_id: selectedProjectId.value } : {}
  }

  // Auto-link a newly created item to the selected project
  async function linkToSelectedProject(itemType: string, itemId: string): Promise<void> {
    if (!selectedProjectId.value) return

    try {
      await api.post(`/api/v1/projects/${selectedProjectId.value}/links`, {
        type: itemType,
        item_id: itemId
      })
    } catch (error) {
      console.error('Failed to link item to project:', error)
    }
  }

  return {
    // State
    projects,
    selectedProjectId,
    isLoading,

    // Computed
    selectedProject,
    activeProjects,

    // Actions
    loadProjects,
    selectProject,
    clearSelection,
    getProjectFilter,
    linkToSelectedProject,
  }
})
