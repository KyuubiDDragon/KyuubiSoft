<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue'
import { useChatStore } from '@/stores/chat'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
import {
  ChatBubbleLeftRightIcon,
  PaperAirplaneIcon,
  PlusIcon,
  MagnifyingGlassIcon,
  UserGroupIcon,
  UserIcon,
  EllipsisVerticalIcon,
  FaceSmileIcon,
  PencilIcon,
  TrashIcon,
  XMarkIcon,
  HashtagIcon,
  ArrowLeftIcon,
} from '@heroicons/vue/24/outline'

const chatStore = useChatStore()
const authStore = useAuthStore()
const toast = useToast()
const { confirm } = useConfirmDialog()

// Refs
const messageInput = ref('')
const messagesContainer = ref(null)
const showNewRoomModal = ref(false)
const showNewDMModal = ref(false)
const showEmojiPicker = ref(null)
const editingMessage = ref(null)
const editContent = ref('')
const searchQuery = ref('')

// New room form
const newRoomForm = ref({
  name: '',
  description: '',
  type: 'group',
  participants: [],
})

// Common emojis
const emojis = ['👍', '❤️', '😂', '😮', '😢', '😡', '🎉', '🔥', '✅', '❌']

// Computed
const filteredRooms = computed(() => {
  if (!searchQuery.value) return chatStore.sortedRooms
  const query = searchQuery.value.toLowerCase()
  return chatStore.sortedRooms.filter(room => {
    const name = room.name || room.other_user?.username || ''
    return name.toLowerCase().includes(query)
  })
})

const currentUserId = computed(() => authStore.user?.id)

// Lifecycle
onMounted(async () => {
  await chatStore.fetchRooms()
  await chatStore.fetchAvailableUsers()
})

onUnmounted(() => {
  chatStore.stopPolling()
})

// Watch room changes
watch(() => chatStore.currentRoom, async (room) => {
  if (room) {
    chatStore.stopPolling()
    await chatStore.fetchMessages(room.id)
    await chatStore.markAsRead(room.id)
    chatStore.startPolling(room.id)
    scrollToBottom()
  }
})

// Methods
async function selectRoom(room) {
  chatStore.selectRoom(room)
}

async function sendMessage() {
  if (!messageInput.value.trim() || !chatStore.currentRoom) return

  const content = messageInput.value
  messageInput.value = ''

  await chatStore.sendMessage(chatStore.currentRoom.id, content)
  scrollToBottom()
}

function scrollToBottom() {
  nextTick(() => {
    if (messagesContainer.value) {
      messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
    }
  })
}

async function loadMoreMessages() {
  if (!chatStore.currentRoom || chatStore.messages.length === 0) return
  const oldest = chatStore.messages[0]
  if (oldest) {
    await chatStore.fetchMessages(chatStore.currentRoom.id, oldest.created_at)
  }
}

async function createRoom() {
  if (!newRoomForm.value.name.trim()) return

  await chatStore.createRoom({
    name: newRoomForm.value.name,
    description: newRoomForm.value.description,
    type: newRoomForm.value.type,
    participants: newRoomForm.value.participants,
  })

  showNewRoomModal.value = false
  newRoomForm.value = { name: '', description: '', type: 'group', participants: [] }
}

async function startDM(userId) {
  const room = await chatStore.startDirectMessage(userId)
  chatStore.selectRoom(room)
  showNewDMModal.value = false
}

function startEditMessage(message) {
  editingMessage.value = message.id
  editContent.value = message.content
}

async function saveEdit() {
  if (!editContent.value.trim()) return
  await chatStore.editMessage(editingMessage.value, editContent.value)
  editingMessage.value = null
  editContent.value = ''
}

function cancelEdit() {
  editingMessage.value = null
  editContent.value = ''
}

async function deleteMessage(messageId) {
  if (!await confirm({ message: 'Nachricht wirklich löschen?', type: 'danger', confirmText: 'Löschen' })) return

  await chatStore.deleteMessage(messageId)
}

function toggleReaction(messageId, emoji) {
  const message = chatStore.messages.find(m => m.id === messageId)
  // Simple toggle - in production you'd track user's own reactions
  chatStore.addReaction(messageId, emoji)
  showEmojiPicker.value = null
}

function handleTyping() {
  if (chatStore.currentRoom) {
    chatStore.setTyping(chatStore.currentRoom.id)
  }
}

function getRoomName(room) {
  if (room.type === 'direct') {
    return room.other_user?.username || 'Unknown'
  }
  return room.name || 'Unnamed Room'
}

function getRoomIcon(room) {
  if (room.type === 'direct') return UserIcon
  if (room.type === 'channel') return HashtagIcon
  return UserGroupIcon
}

function formatTime(dateStr) {
  const date = new Date(dateStr)
  const now = new Date()
  const isToday = date.toDateString() === now.toDateString()

  if (isToday) {
    return date.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' })
  }
  return date.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })
}

function toggleParticipant(userId) {
  const idx = newRoomForm.value.participants.indexOf(userId)
  if (idx >= 0) {
    newRoomForm.value.participants.splice(idx, 1)
  } else {
    newRoomForm.value.participants.push(userId)
  }
}
</script>

<template>
  <div class="h-[calc(100vh-64px)] flex bg-white/[0.02] -m-4 lg:-m-6">
    <!-- Sidebar: Room List -->
    <div class="w-80 bg-white/[0.04] border-r border-white/[0.06] flex flex-col">
      <!-- Header -->
      <div class="p-4 border-b border-white/[0.06]">
        <div class="flex items-center justify-between mb-4">
          <div class="flex items-center gap-2">
            <ChatBubbleLeftRightIcon class="w-6 h-6 text-primary-400" />
            <h2 class="text-lg font-semibold text-white">Team Chat</h2>
          </div>
          <div class="flex items-center gap-1">
            <button
              @click="showNewDMModal = true"
              class="p-2 text-gray-400 hover:text-white hover:bg-white/[0.04] rounded-lg transition-colors"
              title="Neue Direktnachricht"
            >
              <UserIcon class="w-5 h-5" />
            </button>
            <button
              @click="showNewRoomModal = true"
              class="p-2 text-gray-400 hover:text-white hover:bg-white/[0.04] rounded-lg transition-colors"
              title="Neuer Raum"
            >
              <PlusIcon class="w-5 h-5" />
            </button>
          </div>
        </div>

        <!-- Search -->
        <div class="relative">
          <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-500" />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Suchen..."
            class="input w-full pl-9 pr-4 py-2 text-sm"
          />
        </div>
      </div>

      <!-- Room List -->
      <div class="flex-1 overflow-y-auto">
        <div v-if="chatStore.loading" class="flex items-center justify-center py-8">
          <div class="w-6 h-6 border-2 border-primary-500 border-t-transparent rounded-full animate-spin"></div>
        </div>

        <div v-else-if="filteredRooms.length === 0" class="text-center py-8 text-gray-500 text-sm">
          Keine Unterhaltungen
        </div>

        <div v-else>
          <div
            v-for="room in filteredRooms"
            :key="room.id"
            @click="selectRoom(room)"
            class="flex items-center gap-3 px-4 py-3 cursor-pointer hover:bg-white/[0.04] transition-colors"
            :class="{ 'bg-white/[0.04]': chatStore.currentRoom?.id === room.id }"
          >
            <div class="w-10 h-10 rounded-full bg-white/[0.08] flex items-center justify-center">
              <component :is="getRoomIcon(room)" class="w-5 h-5 text-gray-400" />
            </div>
            <div class="flex-1 min-w-0">
              <div class="flex items-center justify-between">
                <p class="font-medium text-white truncate">{{ getRoomName(room) }}</p>
                <span v-if="room.unread_count > 0" class="px-2 py-0.5 bg-primary-600 text-white text-xs rounded-full">
                  {{ room.unread_count }}
                </span>
              </div>
              <p class="text-sm text-gray-500 truncate">{{ room.last_message_preview || 'Keine Nachrichten' }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Main: Chat Area -->
    <div class="flex-1 flex flex-col">
      <!-- Empty State -->
      <div v-if="!chatStore.currentRoom" class="flex-1 flex items-center justify-center">
        <div class="text-center">
          <ChatBubbleLeftRightIcon class="w-16 h-16 text-gray-600 mx-auto mb-4" />
          <h3 class="text-lg font-medium text-gray-400">Wahle eine Unterhaltung</h3>
          <p class="text-gray-500 text-sm mt-1">Oder starte eine neue</p>
        </div>
      </div>

      <!-- Chat Content -->
      <template v-else>
        <!-- Chat Header -->
        <div class="px-6 py-4 border-b border-white/[0.06] flex items-center justify-between">
          <div class="flex items-center gap-3">
            <button
              @click="chatStore.selectRoom(null)"
              class="lg:hidden p-2 text-gray-400 hover:text-white rounded-lg"
            >
              <ArrowLeftIcon class="w-5 h-5" />
            </button>
            <div class="w-10 h-10 rounded-full bg-white/[0.08] flex items-center justify-center">
              <component :is="getRoomIcon(chatStore.currentRoom)" class="w-5 h-5 text-gray-400" />
            </div>
            <div>
              <h3 class="font-medium text-white">{{ getRoomName(chatStore.currentRoom) }}</h3>
              <p v-if="chatStore.currentRoom.participants" class="text-sm text-gray-500">
                {{ chatStore.currentRoom.participants.length }} Teilnehmer
              </p>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <button class="p-2 text-gray-400 hover:text-white rounded-lg">
              <EllipsisVerticalIcon class="w-5 h-5" />
            </button>
          </div>
        </div>

        <!-- Messages -->
        <div ref="messagesContainer" class="flex-1 overflow-y-auto p-4 space-y-4">
          <!-- Load More -->
          <button
            v-if="chatStore.messages.length >= 50"
            @click="loadMoreMessages"
            class="w-full text-center text-sm text-primary-400 hover:text-primary-300 py-2"
          >
            Altere Nachrichten laden
          </button>

          <!-- Messages -->
          <div
            v-for="message in chatStore.messages"
            :key="message.id"
            class="flex gap-3"
            :class="{ 'flex-row-reverse': message.user_id === currentUserId }"
          >
            <div class="w-8 h-8 rounded-full bg-white/[0.08] flex items-center justify-center flex-shrink-0">
              <UserIcon class="w-4 h-4 text-gray-400" />
            </div>

            <div
              class="max-w-[70%] group"
              :class="{ 'text-right': message.user_id === currentUserId }"
            >
              <div class="flex items-center gap-2 mb-1" :class="{ 'flex-row-reverse': message.user_id === currentUserId }">
                <span class="text-sm font-medium text-gray-300">{{ message.username }}</span>
                <span class="text-xs text-gray-500">{{ formatTime(message.created_at) }}</span>
                <span v-if="message.is_edited" class="text-xs text-gray-500">(bearbeitet)</span>
              </div>

              <!-- Message Content -->
              <div
                v-if="editingMessage !== message.id"
                class="rounded-lg px-4 py-2"
                :class="message.user_id === currentUserId ? 'bg-primary-600 text-white' : 'bg-white/[0.04] text-gray-200'"
              >
                <p class="whitespace-pre-wrap">{{ message.content }}</p>
              </div>

              <!-- Editing -->
              <div v-else class="flex items-center gap-2">
                <input
                  v-model="editContent"
                  @keyup.enter="saveEdit"
                  @keyup.escape="cancelEdit"
                  class="input flex-1"
                  autofocus
                />
                <button @click="saveEdit" class="text-green-400 hover:text-green-300">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                  </svg>
                </button>
                <button @click="cancelEdit" class="text-red-400 hover:text-red-300">
                  <XMarkIcon class="w-5 h-5" />
                </button>
              </div>

              <!-- Reactions -->
              <div v-if="message.reactions && Object.keys(message.reactions).length > 0" class="flex items-center gap-1 mt-1" :class="{ 'justify-end': message.user_id === currentUserId }">
                <span
                  v-for="(count, emoji) in message.reactions"
                  :key="emoji"
                  class="px-2 py-0.5 bg-white/[0.08] rounded-full text-xs"
                >
                  {{ emoji }} {{ count }}
                </span>
              </div>

              <!-- Actions -->
              <div
                class="flex items-center gap-1 mt-1 opacity-0 group-hover:opacity-100 transition-opacity"
                :class="{ 'justify-end': message.user_id === currentUserId }"
              >
                <div class="relative">
                  <button
                    @click="showEmojiPicker = showEmojiPicker === message.id ? null : message.id"
                    class="p-1 text-gray-500 hover:text-gray-300 rounded"
                  >
                    <FaceSmileIcon class="w-4 h-4" />
                  </button>
                  <!-- Emoji Picker -->
                  <div
                    v-if="showEmojiPicker === message.id"
                    class="absolute bottom-full left-0 mb-1 p-2 bg-white/[0.04] rounded-lg shadow-glass flex gap-1 z-10"
                  >
                    <button
                      v-for="emoji in emojis"
                      :key="emoji"
                      @click="toggleReaction(message.id, emoji)"
                      class="hover:scale-125 transition-transform"
                    >
                      {{ emoji }}
                    </button>
                  </div>
                </div>
                <button
                  v-if="message.user_id === currentUserId"
                  @click="startEditMessage(message)"
                  class="p-1 text-gray-500 hover:text-gray-300 rounded"
                >
                  <PencilIcon class="w-4 h-4" />
                </button>
                <button
                  v-if="message.user_id === currentUserId"
                  @click="deleteMessage(message.id)"
                  class="p-1 text-gray-500 hover:text-red-400 rounded"
                >
                  <TrashIcon class="w-4 h-4" />
                </button>
              </div>
            </div>
          </div>

          <!-- Typing Indicator -->
          <div v-if="chatStore.typingUsers.length > 0" class="flex items-center gap-2 text-sm text-gray-500">
            <span>{{ chatStore.typingUsers.map(u => u.username).join(', ') }} schreibt...</span>
          </div>
        </div>

        <!-- Input -->
        <div class="p-4 border-t border-white/[0.06]">
          <div class="flex items-center gap-3">
            <input
              v-model="messageInput"
              @keyup.enter="sendMessage"
              @input="handleTyping"
              type="text"
              placeholder="Nachricht schreiben..."
              class="input flex-1"
            />
            <button
              @click="sendMessage"
              :disabled="!messageInput.trim()"
              class="btn-primary p-3"
            >
              <PaperAirplaneIcon class="w-5 h-5 text-white" />
            </button>
          </div>
        </div>
      </template>
    </div>

    <!-- New Room Modal -->
    <Teleport to="body">
      <div v-if="showNewRoomModal" class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-md" @click="showNewRoomModal = false"></div>
        <div class="relative modal p-6 w-full max-w-md">
          <h3 class="text-lg font-semibold text-white mb-4">Neuen Raum erstellen</h3>

          <div class="space-y-4">
            <div>
              <label class="block text-sm text-gray-400 mb-1">Name</label>
              <input
                v-model="newRoomForm.name"
                type="text"
                class="input w-full"
                placeholder="Raumname"
              />
            </div>

            <div>
              <label class="block text-sm text-gray-400 mb-1">Beschreibung</label>
              <textarea
                v-model="newRoomForm.description"
                rows="2"
                class="textarea w-full resize-none"
                placeholder="Optional"
              ></textarea>
            </div>

            <div>
              <label class="block text-sm text-gray-400 mb-2">Teilnehmer hinzufugen</label>
              <div class="max-h-40 overflow-y-auto space-y-1">
                <label
                  v-for="user in chatStore.availableUsers"
                  :key="user.id"
                  class="flex items-center gap-2 p-2 hover:bg-white/[0.04] rounded cursor-pointer"
                >
                  <input
                    type="checkbox"
                    :checked="newRoomForm.participants.includes(user.id)"
                    @change="toggleParticipant(user.id)"
                    class="rounded bg-white/[0.08] border-white/[0.08] text-primary-600"
                  />
                  <span class="text-gray-300">{{ user.username }}</span>
                </label>
              </div>
            </div>
          </div>

          <div class="flex justify-end gap-2 mt-6">
            <button
              @click="showNewRoomModal = false"
              class="btn-secondary"
            >
              Abbrechen
            </button>
            <button
              @click="createRoom"
              :disabled="!newRoomForm.name.trim()"
              class="btn-primary"
            >
              Erstellen
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- New DM Modal -->
    <Teleport to="body">
      <div v-if="showNewDMModal" class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-md" @click="showNewDMModal = false"></div>
        <div class="relative modal p-6 w-full max-w-md">
          <h3 class="text-lg font-semibold text-white mb-4">Neue Direktnachricht</h3>

          <div class="space-y-1 max-h-80 overflow-y-auto">
            <div
              v-for="user in chatStore.availableUsers"
              :key="user.id"
              @click="startDM(user.id)"
              class="flex items-center gap-3 p-3 hover:bg-white/[0.04] rounded-lg cursor-pointer"
            >
              <div class="w-10 h-10 rounded-full bg-white/[0.08] flex items-center justify-center">
                <UserIcon class="w-5 h-5 text-gray-400" />
              </div>
              <div>
                <p class="font-medium text-white">{{ user.username }}</p>
                <p class="text-sm text-gray-500">{{ user.email }}</p>
              </div>
            </div>
          </div>

          <div class="flex justify-end mt-4">
            <button
              @click="showNewDMModal = false"
              class="btn-secondary"
            >
              Abbrechen
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
