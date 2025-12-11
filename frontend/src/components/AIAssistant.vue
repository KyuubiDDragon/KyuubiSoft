<script setup>
import { ref, computed, onMounted, nextTick, watch } from 'vue'
import { useAIStore } from '@/stores/ai'
import { useProjectStore } from '@/stores/project'
import { useRouter } from 'vue-router'
import {
  SparklesIcon,
  XMarkIcon,
  PaperAirplaneIcon,
  ChevronDownIcon,
  ChevronUpIcon,
  PlusIcon,
  TrashIcon,
  Cog6ToothIcon,
  ChatBubbleLeftRightIcon,
  ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline'

const aiStore = useAIStore()
const projectStore = useProjectStore()
const router = useRouter()

const isOpen = ref(false)
const isMinimized = ref(false)
const message = ref('')
const messagesContainer = ref(null)
const showHistory = ref(false)

// Local messages for current conversation
const messages = ref([])
const conversationId = ref(null)

const isConfigured = computed(() => aiStore.isConfigured)

onMounted(async () => {
  await aiStore.fetchSettings()
  await aiStore.fetchConversations()
})

// Watch for conversation changes
watch(() => aiStore.currentConversation, (conv) => {
  if (conv) {
    messages.value = [...(conv.messages || [])]
    conversationId.value = conv.id
  }
})

async function sendMessage() {
  if (!message.value.trim() || aiStore.chatLoading) return

  const userMessage = message.value
  message.value = ''

  // Add user message immediately
  messages.value.push({ role: 'user', content: userMessage })
  scrollToBottom()

  try {
    // Get project context if a project is selected
    const context = projectStore.getProjectFilter()
    const response = await aiStore.chat(userMessage, conversationId.value, context)
    conversationId.value = response.conversation_id

    // Add assistant response
    messages.value.push({ role: 'assistant', content: response.message })
    scrollToBottom()

    // Refresh conversations list
    await aiStore.fetchConversations()
  } catch (error) {
    messages.value.push({
      role: 'system',
      content: `Fehler: ${error.message || 'Nachricht konnte nicht gesendet werden'}`
    })
    scrollToBottom()
  }
}

function scrollToBottom() {
  nextTick(() => {
    if (messagesContainer.value) {
      messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
    }
  })
}

function startNewConversation() {
  messages.value = []
  conversationId.value = null
  aiStore.newConversation()
  showHistory.value = false
}

async function loadConversation(conv) {
  await aiStore.fetchConversation(conv.id)
  messages.value = [...(aiStore.currentConversation?.messages || [])]
  conversationId.value = conv.id
  showHistory.value = false
  scrollToBottom()
}

async function deleteConversation(conv) {
  if (confirm('Mochtest du diese Unterhaltung wirklich loschen?')) {
    await aiStore.deleteConversation(conv.id)
    if (conversationId.value === conv.id) {
      startNewConversation()
    }
  }
}

function goToSettings() {
  router.push('/settings?tab=ai')
  isOpen.value = false
}

function toggleOpen() {
  isOpen.value = !isOpen.value
  if (isOpen.value) {
    isMinimized.value = false
  }
}

function formatTime(dateStr) {
  return new Date(dateStr).toLocaleTimeString('de-DE', {
    hour: '2-digit',
    minute: '2-digit'
  })
}
</script>

<template>
  <!-- Floating Button -->
  <button
    v-if="!isOpen"
    @click="toggleOpen"
    class="fixed bottom-6 right-44 w-14 h-14 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 rounded-full shadow-lg flex items-center justify-center text-white transition-all z-50 group"
    title="AI Assistent"
  >
    <SparklesIcon class="w-6 h-6" />
  </button>

  <!-- Chat Panel -->
  <Transition
    enter-active-class="transition ease-out duration-200"
    enter-from-class="transform opacity-0 translate-y-4"
    enter-to-class="transform opacity-100 translate-y-0"
    leave-active-class="transition ease-in duration-150"
    leave-from-class="transform opacity-100 translate-y-0"
    leave-to-class="transform opacity-0 translate-y-4"
  >
    <div
      v-if="isOpen"
      class="fixed bottom-6 right-44 w-[420px] bg-dark-800 border border-dark-600 rounded-xl shadow-2xl z-50 overflow-hidden flex flex-col"
      :class="isMinimized ? 'h-auto' : 'h-[600px]'"
    >
      <!-- Header -->
      <div class="flex items-center justify-between px-4 py-3 bg-gradient-to-r from-purple-900/50 to-indigo-900/50 border-b border-dark-600">
        <div class="flex items-center gap-2">
          <SparklesIcon class="w-5 h-5 text-purple-400" />
          <div>
            <h3 class="font-semibold text-white text-sm">AI Assistent</h3>
            <p v-if="projectStore.selectedProject" class="text-xs text-purple-300">
              {{ projectStore.selectedProject.name }}
            </p>
          </div>
          <span v-if="isConfigured" class="w-2 h-2 bg-green-500 rounded-full"></span>
          <span v-else class="w-2 h-2 bg-red-500 rounded-full"></span>
        </div>
        <div class="flex items-center gap-1">
          <button
            @click="startNewConversation"
            class="p-1.5 text-gray-400 hover:text-white rounded transition-colors"
            title="Neue Unterhaltung"
          >
            <PlusIcon class="w-4 h-4" />
          </button>
          <button
            @click="showHistory = !showHistory"
            class="p-1.5 text-gray-400 hover:text-white rounded transition-colors"
            title="Verlauf"
          >
            <ChatBubbleLeftRightIcon class="w-4 h-4" />
          </button>
          <button
            @click="goToSettings"
            class="p-1.5 text-gray-400 hover:text-white rounded transition-colors"
            title="Einstellungen"
          >
            <Cog6ToothIcon class="w-4 h-4" />
          </button>
          <button
            @click="isMinimized = !isMinimized"
            class="p-1.5 text-gray-400 hover:text-white rounded transition-colors"
          >
            <ChevronDownIcon v-if="!isMinimized" class="w-4 h-4" />
            <ChevronUpIcon v-else class="w-4 h-4" />
          </button>
          <button
            @click="toggleOpen"
            class="p-1.5 text-gray-400 hover:text-white rounded transition-colors"
          >
            <XMarkIcon class="w-4 h-4" />
          </button>
        </div>
      </div>

      <!-- Content -->
      <div v-if="!isMinimized" class="flex-1 flex flex-col overflow-hidden">
        <!-- Not Configured Warning -->
        <div v-if="!isConfigured" class="p-4 bg-yellow-900/20 border-b border-yellow-900/30">
          <div class="flex items-start gap-3">
            <ExclamationTriangleIcon class="w-5 h-5 text-yellow-500 flex-shrink-0 mt-0.5" />
            <div>
              <p class="text-sm text-yellow-200">AI Assistent nicht konfiguriert</p>
              <p class="text-xs text-yellow-300/70 mt-1">
                Bitte hinterlege deinen API-Key in den Einstellungen um den AI-Assistenten zu nutzen.
              </p>
              <button
                @click="goToSettings"
                class="mt-2 text-xs text-yellow-400 hover:text-yellow-300 underline"
              >
                Zu den Einstellungen
              </button>
            </div>
          </div>
        </div>

        <!-- History Panel -->
        <div
          v-if="showHistory"
          class="border-b border-dark-600 max-h-48 overflow-y-auto"
        >
          <div class="p-2 bg-dark-700/50">
            <p class="text-xs text-gray-500 px-2 py-1">Letzte Unterhaltungen</p>
            <div
              v-for="conv in aiStore.conversations"
              :key="conv.id"
              class="flex items-center justify-between px-2 py-1.5 hover:bg-dark-600 rounded cursor-pointer group"
              @click="loadConversation(conv)"
            >
              <div class="flex-1 min-w-0">
                <p class="text-sm text-gray-300 truncate">{{ conv.title || 'Unterhaltung' }}</p>
                <p class="text-xs text-gray-500">{{ formatTime(conv.updated_at) }}</p>
              </div>
              <button
                @click.stop="deleteConversation(conv)"
                class="p-1 text-gray-500 hover:text-red-400 opacity-0 group-hover:opacity-100 transition-opacity"
              >
                <TrashIcon class="w-3.5 h-3.5" />
              </button>
            </div>
            <p v-if="aiStore.conversations.length === 0" class="text-xs text-gray-500 text-center py-4">
              Keine Unterhaltungen
            </p>
          </div>
        </div>

        <!-- Messages -->
        <div
          ref="messagesContainer"
          class="flex-1 overflow-y-auto p-4 space-y-4"
        >
          <!-- Empty State -->
          <div v-if="messages.length === 0" class="text-center py-8">
            <SparklesIcon class="w-12 h-12 text-purple-500/30 mx-auto mb-3" />
            <p class="text-gray-400 text-sm">Wie kann ich dir helfen?</p>
            <p class="text-gray-500 text-xs mt-1">Stelle mir eine Frage...</p>
          </div>

          <!-- Messages -->
          <div
            v-for="(msg, index) in messages"
            :key="index"
            class="flex"
            :class="msg.role === 'user' ? 'justify-end' : 'justify-start'"
          >
            <div
              class="max-w-[85%] rounded-lg px-3 py-2 text-sm"
              :class="{
                'bg-purple-600 text-white': msg.role === 'user',
                'bg-dark-700 text-gray-200': msg.role === 'assistant',
                'bg-red-900/30 text-red-300 border border-red-800': msg.role === 'system'
              }"
            >
              <p class="whitespace-pre-wrap">{{ msg.content }}</p>
            </div>
          </div>

          <!-- Loading -->
          <div v-if="aiStore.chatLoading" class="flex justify-start">
            <div class="bg-dark-700 rounded-lg px-4 py-3">
              <div class="flex items-center gap-2">
                <div class="w-2 h-2 bg-purple-500 rounded-full animate-bounce"></div>
                <div class="w-2 h-2 bg-purple-500 rounded-full animate-bounce" style="animation-delay: 0.1s"></div>
                <div class="w-2 h-2 bg-purple-500 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Input -->
        <div class="p-3 border-t border-dark-600 bg-dark-800">
          <div class="flex gap-2">
            <input
              v-model="message"
              @keyup.enter="sendMessage"
              type="text"
              placeholder="Schreibe eine Nachricht..."
              :disabled="!isConfigured || aiStore.chatLoading"
              class="flex-1 px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-sm text-white placeholder-gray-500 focus:outline-none focus:border-purple-500 disabled:opacity-50"
            />
            <button
              @click="sendMessage"
              :disabled="!message.trim() || !isConfigured || aiStore.chatLoading"
              class="px-3 py-2 bg-purple-600 hover:bg-purple-500 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg transition-colors"
            >
              <PaperAirplaneIcon class="w-4 h-4 text-white" />
            </button>
          </div>
          <p class="text-xs text-gray-500 mt-2 text-center">
            {{ aiStore.currentProvider }} / {{ aiStore.currentModel }}
          </p>
        </div>
      </div>
    </div>
  </Transition>
</template>
