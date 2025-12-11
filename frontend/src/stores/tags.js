import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/core/api/axios'

export const useTagsStore = defineStore('tags', () => {
  const tags = ref([])
  const isLoading = ref(false)
  const error = ref(null)
  const validTypes = ref([])

  const tagById = computed(() => {
    const map = {}
    tags.value.forEach(tag => {
      map[tag.id] = tag
    })
    return map
  })

  async function loadTags(search = '') {
    isLoading.value = true
    error.value = null
    try {
      const params = search ? { search } : {}
      const response = await api.get('/api/v1/tags', { params })
      tags.value = response.data.data
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to load tags'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  async function loadValidTypes() {
    try {
      const response = await api.get('/api/v1/tags/types')
      validTypes.value = response.data.data
    } catch (err) {
      console.error('Failed to load tag types', err)
    }
  }

  async function getTag(id) {
    try {
      const response = await api.get(`/api/v1/tags/${id}`)
      return response.data.data
    } catch (err) {
      throw err
    }
  }

  async function createTag(data) {
    try {
      const response = await api.post('/api/v1/tags', data)
      const newTag = response.data.data
      tags.value.push(newTag)
      return newTag
    } catch (err) {
      throw err
    }
  }

  async function updateTag(id, data) {
    try {
      const response = await api.put(`/api/v1/tags/${id}`, data)
      const updatedTag = response.data.data
      const index = tags.value.findIndex(t => t.id === id)
      if (index !== -1) {
        tags.value[index] = updatedTag
      }
      return updatedTag
    } catch (err) {
      throw err
    }
  }

  async function deleteTag(id) {
    try {
      await api.delete(`/api/v1/tags/${id}`)
      tags.value = tags.value.filter(t => t.id !== id)
    } catch (err) {
      throw err
    }
  }

  async function tagItem(tagId, taggableType, taggableId) {
    try {
      await api.post(`/api/v1/tags/${tagId}/tag`, {
        taggable_type: taggableType,
        taggable_id: taggableId
      })
      // Update local tag usage count
      const tag = tags.value.find(t => t.id === tagId)
      if (tag) {
        tag.usage_count = (tag.usage_count || 0) + 1
      }
    } catch (err) {
      throw err
    }
  }

  async function untagItem(tagId, taggableType, taggableId) {
    try {
      await api.delete(`/api/v1/tags/${tagId}/${taggableType}/${taggableId}`)
      // Update local tag usage count
      const tag = tags.value.find(t => t.id === tagId)
      if (tag && tag.usage_count > 0) {
        tag.usage_count--
      }
    } catch (err) {
      throw err
    }
  }

  async function getItemTags(taggableType, taggableId) {
    try {
      const response = await api.get(`/api/v1/taggable/${taggableType}/${taggableId}`)
      return response.data.data
    } catch (err) {
      throw err
    }
  }

  async function setItemTags(taggableType, taggableId, tagIds) {
    try {
      const response = await api.put(`/api/v1/taggable/${taggableType}/${taggableId}`, {
        tag_ids: tagIds
      })
      return response.data.data
    } catch (err) {
      throw err
    }
  }

  async function searchByTags(tagIds, type = null) {
    try {
      const params = { tags: tagIds.join(',') }
      if (type) params.type = type
      const response = await api.get('/api/v1/tags/search', { params })
      return response.data.data
    } catch (err) {
      throw err
    }
  }

  async function mergeTags(sourceIds, targetId) {
    try {
      const response = await api.post('/api/v1/tags/merge', {
        source_ids: sourceIds,
        target_id: targetId
      })
      // Remove merged source tags from local state
      tags.value = tags.value.filter(t => !sourceIds.includes(t.id))
      // Update target tag
      const index = tags.value.findIndex(t => t.id === targetId)
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
