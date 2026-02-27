import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { Ref, ComputedRef } from 'vue'
import api from '@/core/api/axios'

// Interfaces
interface AIModel {
  id: string
  name: string
}

interface AIProvider {
  value: string
  label: string
  models: AIModel[]
}

interface AISettings {
  has_api_key: boolean
  is_enabled: boolean
  model: string
  provider: string
  [key: string]: unknown
}

interface ChatMessage {
  role: 'user' | 'assistant' | 'system'
  content: string
}

interface Conversation {
  id: string
  messages: ChatMessage[]
  [key: string]: unknown
}

interface ChatContext {
  [key: string]: unknown
}

interface ChatResponse {
  message: string
  conversation_id: string
  [key: string]: unknown
}

interface AIStatus {
  is_configured: boolean
  [key: string]: unknown
}

interface AIPrompt {
  id: string
  [key: string]: unknown
}

interface ApiError {
  response?: {
    data?: {
      error?: string
    }
  }
}

export const useAIStore = defineStore('ai', () => {
  // State
  const settings: Ref<AISettings | null> = ref<AISettings | null>(null)
  const conversations: Ref<Conversation[]> = ref<Conversation[]>([])
  const currentConversation: Ref<Conversation | null> = ref<Conversation | null>(null)
  const prompts: Ref<AIPrompt[]> = ref<AIPrompt[]>([])
  const loading: Ref<boolean> = ref<boolean>(false)
  const chatLoading: Ref<boolean> = ref<boolean>(false)
  const error: Ref<string | null> = ref<string | null>(null)

  // Computed
  const isConfigured: ComputedRef<boolean> = computed<boolean>(() => settings.value?.has_api_key || false)
  const currentModel: ComputedRef<string> = computed<string>(() => settings.value?.model || 'gpt-4o-mini')
  const currentProvider: ComputedRef<string> = computed<string>(() => settings.value?.provider || 'openai')

  // Available providers and models - use correct model IDs!
  const providers: AIProvider[] = [
    {
      value: 'openai',
      label: 'OpenAI',
      models: [
        { id: 'gpt-4o', name: 'GPT-4o (Empfohlen)' },
        { id: 'gpt-4o-mini', name: 'GPT-4o Mini (Schnell & Günstig)' },
        { id: 'gpt-4-turbo', name: 'GPT-4 Turbo' },
        { id: 'gpt-3.5-turbo', name: 'GPT-3.5 Turbo' },
      ]
    },
    {
      value: 'anthropic',
      label: 'Anthropic',
      models: [
        { id: 'claude-sonnet-4-5-20250929', name: 'Claude 4.5 Sonnet (Neueste)' },
        { id: 'claude-3-5-sonnet-20241022', name: 'Claude 3.5 Sonnet' },
        { id: 'claude-3-5-haiku-20241022', name: 'Claude 3.5 Haiku (Schnell)' },
        { id: 'claude-3-opus-20240229', name: 'Claude 3 Opus (Stärkstes)' },
      ]
    },
    {
      value: 'openrouter',
      label: 'OpenRouter',
      models: [
        { id: 'openai/gpt-4o', name: 'GPT-4o via OpenRouter' },
        { id: 'anthropic/claude-3.5-sonnet', name: 'Claude 3.5 Sonnet via OpenRouter' },
        { id: 'google/gemini-pro-1.5', name: 'Gemini Pro 1.5' },
        { id: 'meta-llama/llama-3.1-70b-instruct', name: 'Llama 3.1 70B' },
        { id: 'mistralai/mistral-large', name: 'Mistral Large' },
      ]
    },
    {
      value: 'ollama',
      label: 'Ollama (Lokal)',
      models: [
        { id: 'llama3.2', name: 'Llama 3.2' },
        { id: 'llama3.1', name: 'Llama 3.1' },
        { id: 'mistral', name: 'Mistral' },
        { id: 'codellama', name: 'Code Llama' },
        { id: 'phi3', name: 'Phi-3' },
        { id: 'qwen2.5', name: 'Qwen 2.5' },
      ]
    },
    { value: 'custom', label: 'Benutzerdefiniert', models: [] },
  ]

  // Actions
  async function fetchSettings(): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const response = await api.get('/api/v1/ai/settings')
      settings.value = response.data.data
    } catch (err: unknown) {
      error.value = (err as ApiError).response?.data?.error || 'Failed to fetch AI settings'
    } finally {
      loading.value = false
    }
  }

  async function saveSettings(data: Partial<AISettings>): Promise<AISettings> {
    loading.value = true
    error.value = null
    try {
      const response = await api.post('/api/v1/ai/settings', data)
      settings.value = response.data.data
      return response.data.data
    } catch (err: unknown) {
      error.value = (err as ApiError).response?.data?.error || 'Failed to save AI settings'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function removeApiKey(): Promise<void> {
    try {
      await api.delete('/api/v1/ai/settings/api-key')
      if (settings.value) {
        settings.value.has_api_key = false
        settings.value.is_enabled = false
      }
    } catch (err: unknown) {
      error.value = (err as ApiError).response?.data?.error || 'Failed to remove API key'
      throw err
    }
  }

  async function checkStatus(): Promise<AIStatus> {
    try {
      const response = await api.get('/api/v1/ai/status')
      return response.data.data
    } catch (err: unknown) {
      return { is_configured: false }
    }
  }

  async function chat(message: string, conversationId: string | null = null, context: ChatContext = {}): Promise<ChatResponse> {
    chatLoading.value = true
    error.value = null
    try {
      const response = await api.post('/api/v1/ai/chat', {
        message,
        conversation_id: conversationId,
        context
      })

      // Update current conversation if we have one
      const chatData = response.data.data
      if (currentConversation.value && currentConversation.value.id === chatData.conversation_id) {
        currentConversation.value.messages.push(
          { role: 'user', content: message },
          { role: 'assistant', content: chatData.message }
        )
      }

      return chatData
    } catch (err: unknown) {
      error.value = (err as ApiError).response?.data?.error || 'Failed to send message'
      throw err
    } finally {
      chatLoading.value = false
    }
  }

  async function fetchConversations(): Promise<void> {
    try {
      const response = await api.get('/api/v1/ai/conversations')
      conversations.value = response.data.data
    } catch (err: unknown) {
      console.error('Failed to fetch conversations:', err)
    }
  }

  async function fetchConversation(id: string): Promise<Conversation> {
    try {
      const response = await api.get(`/api/v1/ai/conversations/${id}`)
      currentConversation.value = response.data.data
      return response.data.data
    } catch (err: unknown) {
      error.value = (err as ApiError).response?.data?.error || 'Failed to fetch conversation'
      throw err
    }
  }

  async function deleteConversation(id: string): Promise<void> {
    try {
      await api.delete(`/api/v1/ai/conversations/${id}`)
      conversations.value = conversations.value.filter(c => c.id !== id)
      if (currentConversation.value?.id === id) {
        currentConversation.value = null
      }
    } catch (err: unknown) {
      error.value = (err as ApiError).response?.data?.error || 'Failed to delete conversation'
      throw err
    }
  }

  function newConversation(): void {
    currentConversation.value = null
  }

  async function fetchPrompts(): Promise<void> {
    try {
      const response = await api.get('/api/v1/ai/prompts')
      prompts.value = response.data.data
    } catch (err: unknown) {
      console.error('Failed to fetch prompts:', err)
    }
  }

  async function savePrompt(data: Partial<AIPrompt>): Promise<AIPrompt> {
    try {
      const response = await api.post('/api/v1/ai/prompts', data)
      const promptData = response.data.data
      const index = prompts.value.findIndex(p => p.id === promptData.id)
      if (index !== -1) {
        prompts.value[index] = promptData
      } else {
        prompts.value.push(promptData)
      }
      return promptData
    } catch (err: unknown) {
      error.value = (err as ApiError).response?.data?.error || 'Failed to save prompt'
      throw err
    }
  }

  async function deletePrompt(id: string): Promise<void> {
    try {
      await api.delete(`/api/v1/ai/prompts/${id}`)
      prompts.value = prompts.value.filter(p => p.id !== id)
    } catch (err: unknown) {
      error.value = (err as ApiError).response?.data?.error || 'Failed to delete prompt'
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
