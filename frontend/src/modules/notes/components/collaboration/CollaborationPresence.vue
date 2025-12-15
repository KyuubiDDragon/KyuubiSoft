<script setup>
import { computed } from 'vue'
import { useCollaborationStore } from '../../stores/collaborationStore'
import { useAuthStore } from '@/stores/auth'

const collaborationStore = useCollaborationStore()
const authStore = useAuthStore()

const props = defineProps({
  maxVisible: {
    type: Number,
    default: 5
  }
})

// Current user ID
const currentUserId = computed(() => authStore.user?.id)

// Other participants (excluding current user)
const otherParticipants = computed(() => {
  return collaborationStore.participants.filter(p => p.id !== currentUserId.value)
})

// Visible participants
const visibleParticipants = computed(() => {
  return otherParticipants.value.slice(0, props.maxVisible)
})

// Hidden count
const hiddenCount = computed(() => {
  return Math.max(0, otherParticipants.value.length - props.maxVisible)
})

// Get initials from name
function getInitials(name) {
  if (!name) return '?'
  return name
    .split(' ')
    .map(part => part[0])
    .join('')
    .toUpperCase()
    .slice(0, 2)
}

// Get awareness status
function getAwareness(userId) {
  return collaborationStore.awareness[userId] || {}
}

// Is user typing?
function isTyping(userId) {
  const awareness = getAwareness(userId)
  return awareness.isTyping || false
}
</script>

<template>
  <div class="collaboration-presence flex items-center">
    <!-- Connection status indicator -->
    <div
      :class="[
        'w-2 h-2 rounded-full mr-2',
        collaborationStore.isConnected ? 'bg-green-500' : 'bg-gray-500'
      ]"
      :title="collaborationStore.isConnected ? 'Verbunden' : 'Nicht verbunden'"
    />

    <!-- Participant avatars -->
    <div class="flex -space-x-2">
      <div
        v-for="participant in visibleParticipants"
        :key="participant.id"
        class="relative group"
      >
        <!-- Avatar -->
        <div
          :style="{ backgroundColor: participant.color || '#6366F1' }"
          class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-medium border-2 border-dark-800 ring-2 ring-transparent hover:ring-white/20 transition-all cursor-pointer"
          :title="participant.name"
        >
          {{ getInitials(participant.name) }}
        </div>

        <!-- Typing indicator -->
        <div
          v-if="isTyping(participant.id)"
          class="absolute -bottom-1 -right-1 w-4 h-4 bg-dark-700 rounded-full flex items-center justify-center"
        >
          <span class="typing-indicator flex gap-0.5">
            <span class="w-1 h-1 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0ms" />
            <span class="w-1 h-1 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 150ms" />
            <span class="w-1 h-1 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 300ms" />
          </span>
        </div>

        <!-- Tooltip -->
        <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 bg-dark-700 rounded text-xs text-white whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10">
          {{ participant.name }}
          <span v-if="isTyping(participant.id)" class="text-gray-400 ml-1">tippt...</span>
        </div>
      </div>

      <!-- Hidden count badge -->
      <div
        v-if="hiddenCount > 0"
        class="w-8 h-8 rounded-full bg-dark-600 flex items-center justify-center text-gray-400 text-xs font-medium border-2 border-dark-800"
        :title="`+${hiddenCount} weitere Bearbeiter`"
      >
        +{{ hiddenCount }}
      </div>
    </div>

    <!-- Participant count label -->
    <span
      v-if="otherParticipants.length > 0"
      class="ml-2 text-xs text-gray-500"
    >
      {{ otherParticipants.length }} {{ otherParticipants.length === 1 ? 'Bearbeiter' : 'Bearbeiter' }}
    </span>
  </div>
</template>

<style scoped>
.typing-indicator span {
  animation-duration: 1s;
  animation-iteration-count: infinite;
}
</style>
