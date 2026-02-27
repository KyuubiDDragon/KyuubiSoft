import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/core/api/axios'

interface Tag {
  id: number
  name: string
  slug: string
  color?: string
  type?: string
  usage_count?: number
  [key: string]: unknown
}

interface TagInput {
  name: string
  color?: string
  type?: string
  [key: string]: unknown
}

interface TaggableItem {
  id: number
  type: string
  [key: string]: unknown
}

interface TagByIdMap {
  [id: number]: Tag
}

export const useTagsStore = defineStore('tags', () => {
  const tags = ref<Tag[]>([])
  const isLoading = ref<boolean>(false)
  const error = ref<string | null>(null)
  const validTypes = ref<string[]>([])

  const tagById = computed<TagByIdMap>(() => {
    const map: TagByIdMap = {}
    tags.value.forEach((tag: Tag) => {
      map[tag.id] = tag
    })
    return map
  })

  async function loadTags(search: string = ''): Promise<void> {
    isLoading.value = true
    error.value = null
    try {
      const params: { search?: string } = search ? { search } : {}
      const response = await api.get('/api/v1/tags', { params })
      tags.value = response.data.data
    } catch (err: unknown) {
      error.value = (err as { response?: { data?: { error?: string } } }).response?.data?.error || 'Failed to load tags'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  async function loadValidTypes(): Promise<void> {
    try {
      const response = await api.get('/api/v1/tags/types')
      validTypes.value = response.data.data
    } catch (err) {
      console.error('Failed to load tag types', err)
    }
  }

  async function getTag(id: number): Promise<Tag> {
    try {
      const response = await api.get(`/api/v1/tags/${id}`)
      return response.data.data
    } catch (err) {
      throw err
    }
  }

  async function createTag(data: TagInput): Promise<Tag> {
    try {
      const response = await api.post('/api/v1/tags', data)
      const newTag: Tag = response.data.data
      tags.value.push(newTag)
      return newTag
    } catch (err) {
      throw err
    }
  }

  async function updateTag(id: number, data: TagInput): Promise<Tag> {
    try {
      const response = await api.put(`/api/v1/tags/${id}`, data)
      const updatedTag: Tag = response.data.data
      const index = tags.value.findIndex((t: Tag) => t.id === id)
      if (index !== -1) {
        tags.value[index] = updatedTag
      }
      return updatedTag
    } catch (err) {
      throw err
    }
  }

  async function deleteTag(id: number): Promise<void> {
    try {
      await api.delete(`/api/v1/tags/${id}`)
      tags.value = tags.value.filter((t: Tag) => t.id !== id)
    } catch (err) {
      throw err
    }
  }

  async function tagItem(tagId: number, taggableType: string, taggableId: number): Promise<void> {
    try {
      await api.post(`/api/v1/tags/${tagId}/tag`, {
        taggable_type: taggableType,
        taggable_id: taggableId
      })
      // Update local tag usage count
      const tag = tags.value.find((t: Tag) => t.id === tagId)
      if (tag) {
        tag.usage_count = (tag.usage_count || 0) + 1
      }
    } catch (err) {
      throw err
    }
  }

  async function untagItem(tagId: number, taggableType: string, taggableId: number): Promise<void> {
    try {
      await api.delete(`/api/v1/tags/${tagId}/${taggableType}/${taggableId}`)
      // Update local tag usage count
      const tag = tags.value.find((t: Tag) => t.id === tagId)
      if (tag && tag.usage_count && tag.usage_count > 0) {
        tag.usage_count--
      }
    } catch (err) {
      throw err
    }
  }

  async function getItemTags(taggableType: string, taggableId: number): Promise<Tag[]> {
    try {
      const response = await api.get(`/api/v1/taggable/${taggableType}/${taggableId}`)
      return response.data.data
    } catch (err) {
      throw err
    }
  }

  async function setItemTags(taggableType: string, taggableId: number, tagIds: number[]): Promise<Tag[]> {
    try {
      const response = await api.put(`/api/v1/taggable/${taggableType}/${taggableId}`, {
        tag_ids: tagIds
      })
      return response.data.data
    } catch (err) {
      throw err
    }
  }

  async function searchByTags(tagIds: number[], type: string | null = null): Promise<TaggableItem[]> {
    try {
      const params: { tags: string; type?: string } = { tags: tagIds.join(',') }
      if (type) params.type = type
      const response = await api.get('/api/v1/tags/search', { params })
      return response.data.data
    } catch (err) {
      throw err
    }
  }

  async function mergeTags(sourceIds: number[], targetId: number): Promise<Tag> {
    try {
      const response = await api.post('/api/v1/tags/merge', {
        source_ids: sourceIds,
        target_id: targetId
      })
      // Remove merged source tags from local state
      tags.value = tags.value.filter((t: Tag) => !sourceIds.includes(t.id))
      // Update target tag
      const index = tags.value.findIndex((t: Tag) => t.id === targetId)
      if (index !== -1) {
        tags.value[index] = response.data.data
      }
      return response.data.data
    } catch (err) {
      throw err
    }
  }

  return {
    tags,
    isLoading,
    error,
    validTypes,
    tagById,
    loadTags,
    loadValidTypes,
    getTag,
    createTag,
    updateTag,
    deleteTag,
    tagItem,
    untagItem,
    getItemTags,
    setItemTags,
    searchByTags,
    mergeTags
  }
})
