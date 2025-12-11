import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/core/api/axios'

export const useAIStore = defineStore('ai', () => {
  // State
  const settings = ref(null)
  const conversations = ref([])
  const currentConversation = ref(null)
  const prompts = ref([])
  const loading = ref(false)
  const chatLoading = ref(false)
  const error = ref(null)

  // Computed
  const isConfigured = computed(() => settings.value?.has_api_key || false)
  const currentModel = computed(() => settings.value?.model || 'gpt-4o-mini')
  const currentProvider = computed(() => settings.value?.provider || 'openai')

  // Available providers and models
  const providers = [
    { value: 'openai', label: 'OpenAI', models: ['gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'gpt-3.5-turbo'] },
    { value: 'anthropic', label: 'Anthropic', models: ['claude-3-5-sonnet-latest', 'claude-3-5-haiku-latest', 'claude-3-opus-latest'] },
    { value: 'openrouter', label: 'OpenRouter', models: ['openai/gpt-4o', 'anthropic/claude-3.5-sonnet', 'google/gemini-pro', 'meta-llama/llama-3.1-70b-instruct'] },
    { value: 'ollama', label: 'Ollama (Lokal)', models: ['llama3.2', 'llama3.1', 'mistral', 'codellama', 'phi3'] },
    { value: 'custom', label: 'Benutzerdefiniert', models: [] },
  ]

  // Actions
  async function fetchSettings() {
    loading.value = true
    error.value = null
    try {
      const response = await api.get('/api/v1/ai/settings')
      settings.value = response.data
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch AI settings'
    } finally {
      loading.value = false
    }
  }

  async function saveSettings(data) {
    loading.value = true
    error.value = null
    try {
      const response = await api.post('/api/v1/ai/settings', data)
      settings.value = response.data
      return response.data
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to save AI settings'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function removeApiKey() {
    try {
      await api.delete('/api/v1/ai/settings/api-key')
      if (settings.value) {
        settings.value.has_api_key = false
        settings.value.is_enabled = false
      }
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to remove API key'
      throw err
    }
  }

  async function checkStatus() {
    try {
      const response = await api.get('/api/v1/ai/status')
      return response.data
    } catch (err) {
      return { is_configured: false }
    }
  }

  async function chat(message, conversationId = null, context = {}) {
    chatLoading.value = true
    error.value = null
    try {
      const response = await api.post('/api/v1/ai/chat', {
        message,
        conversation_id: conversationId,
        context
      })

      // Update current conversation if we have one
      if (currentConversation.value && currentConversation.value.id === response.data.conversation_id) {
        currentConversation.value.messages.push(
          { role: 'user', content: message },
          { role: 'assistant', content: response.data.message }
        )
      }

      return response.data
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to send message'
      throw err
    } finally {
      chatLoading.value = false
    }
  }

  async function fetchConversations() {
    try {
      const response = await api.get('/api/v1/ai/conversations')
      conversations.value = response.data
    } catch (err) {
      console.error('Failed to fetch conversations:', err)
    }
  }

  async function fetchConversation(id) {
    try {
      const response = await api.get(`/api/v1/ai/conversations/${id}`)
      currentConversation.value = response.data
      return response.data
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch conversation'
      throw err
    }
  }

  async function deleteConversation(id) {
    try {
      await api.delete(`/api/v1/ai/conversations/${id}`)
      conversations.value = conversations.value.filter(c => c.id !== id)
      if (currentConversation.value?.id === id) {
        currentConversation.value = null
      }
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to delete conversation'
      throw err
    }
  }

  function newConversation() {
    currentConversation.value = null
  }

  async function fetchPrompts() {
    try {
      const response = await api.get('/api/v1/ai/prompts')
      prompts.value = response.data
    } catch (err) {
      console.error('Failed to fetch prompts:', err)
    }
  }

  async function savePrompt(data) {
    try {
      const response = await api.post('/api/v1/ai/prompts', data)
      const index = prompts.value.findIndex(p => p.id === response.data.id)
      if (index !== -1) {
        prompts.value[index] = response.data
      } else {
        prompts.value.push(response.data)
      }
      return response.data
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to save prompt'
      throw err
    }
  }

  async function deletePrompt(id) {
    try {
      await api.delete(`/api/v1/ai/prompts/${id}`)
      prompts.value = prompts.value.filter(p => p.id !== id)
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to delete prompt'
      throw err
    }
  }

  return {
    // State
    settings,
    conversations,
    currentConversation,
    prompts,
    loading,
    chatLoading,
    error,
    // Computed
    isConfigured,
    currentModel,
    currentProvider,
    providers,
    // Actions
    fetchSettings,
    saveSettings,
    removeApiKey,
    checkStatus,
    chat,
    fetchConversations,
    fetchConversation,
    deleteConversation,
    newConversation,
    fetchPrompts,
    savePrompt,
    deletePrompt
  }
})
