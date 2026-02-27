<script setup>
import { ref, onMounted, computed } from 'vue'
import { useInboxStore } from '@/stores/inbox'
import {
  InboxArrowDownIcon,
  XMarkIcon,
  PlusIcon,
  ChevronDownIcon,
  ChevronUpIcon,
  TagIcon,
  BellIcon,
  ExclamationTriangleIcon,
  ArrowRightIcon,
} from '@heroicons/vue/24/outline'

const inboxStore = useInboxStore()

const isOpen = ref(false)
const isMinimized = ref(false)
const content = ref('')
const note = ref('')
const priority = ref('normal')
const showAdvanced = ref(false)
const tags = ref([])
const tagInput = ref('')
const reminderAt = ref(null)
const saving = ref(false)

const priorities = [
  { value: 'low', label: 'Niedrig', color: 'text-gray-400' },
  { value: 'normal', label: 'Normal', color: 'text-blue-400' },
  { value: 'high', label: 'Hoch', color: 'text-orange-400' },
  { value: 'urgent', label: 'Dringend', color: 'text-red-400' },
]

const inboxCount = computed(() => inboxStore.stats.inbox || 0)
const urgentCount = computed(() => inboxStore.stats.urgent || 0)

onMounted(() => {
  inboxStore.fetchStats()
})

async function capture() {
  if (!content.value.trim()) return

  saving.value = true
  try {
    await inboxStore.capture({
      content: content.value,
      note: note.value || null,
      priority: priority.value,
      tags: tags.value.length > 0 ? tags.value : null,
      reminder_at: reminderAt.value || null,
    })

    // Reset form
    content.value = ''
    note.value = ''
    priority.value = 'normal'
    tags.value = []
    reminderAt.value = null
    showAdvanced.value = false
  } catch (error) {
    console.error('Failed to capture:', error)
  } finally {
    saving.value = false
  }
}

function addTag() {
  const tag = tagInput.value.trim()
  if (tag && !tags.value.includes(tag)) {
    tags.value.push(tag)
  }
  tagInput.value = ''
}

function removeTag(tag) {
  tags.value = tags.value.filter(t => t !== tag)
}

function toggleOpen() {
  isOpen.value = !isOpen.value
  if (isOpen.value) {
    isMinimized.value = false
  }
}

function handleKeyDown(event) {
  if (event.key === 'Enter' && (event.metaKey || event.ctrlKey)) {
    capture()
  }
}
</script>

<template>
  <!-- Floating Button -->
  <button
    v-if="!isOpen"
    @click="toggleOpen"
    class="fixed bottom-6 right-24 w-14 h-14 bg-indigo-600 hover:bg-indigo-500 rounded-full shadow-glow flex items-center justify-center text-white transition-all z-50 group"
    title="Quick Capture"
  >
    <InboxArrowDownIcon class="w-6 h-6" />
    <span
      v-if="inboxCount > 0"
      class="absolute -top-1 -right-1 w-5 h-5 rounded-full text-xs flex items-center justify-center"
      :class="urgentCount > 0 ? 'bg-red-500' : 'bg-indigo-400'"
    >
      {{ inboxCount > 9 ? '9+' : inboxCount }}
    </span>
  </button>

  <!-- Capture Panel -->
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
      class="fixed bottom-6 right-24 w-96 bg-dark-900/95 backdrop-blur-2xl border border-white/[0.08] rounded-2xl shadow-float z-50 overflow-hidden"
      :class="{ 'h-auto': isMinimized }"
    >
      <!-- Header -->
      <div class="flex items-center justify-between px-4 py-3 bg-white/[0.03] border-b border-white/[0.06]">
        <div class="flex items-center gap-2">
          <InboxArrowDownIcon class="w-5 h-5 text-indigo-400" />
          <h3 class="font-semibold text-white">Quick Capture</h3>
          <span class="text-xs text-gray-500">({{ inboxCount }} im Inbox)</span>
        </div>
        <div class="flex items-center gap-1">
          <router-link
            to="/inbox"
            class="p-1 text-gray-400 hover:text-white rounded transition-colors"
            title="Inbox anzeigen"
          >
            <ArrowRightIcon class="w-4 h-4" />
          </router-link>
          <button
            @click="isMinimized = !isMinimized"
            class="p-1 text-gray-400 hover:text-white rounded transition-colors"
          >
            <ChevronDownIcon v-if="!isMinimized" class="w-4 h-4" />
            <ChevronUpIcon v-else class="w-4 h-4" />
          </button>
          <button
            @click="toggleOpen"
            class="p-1 text-gray-400 hover:text-white rounded transition-colors"
          >
            <XMarkIcon class="w-4 h-4" />
          </button>
        </div>
      </div>

      <!-- Content -->
      <div v-if="!isMinimized" class="p-4 space-y-4">
        <!-- Main Input -->
        <div>
          <textarea
            v-model="content"
            @keydown="handleKeyDown"
            placeholder="Was hast du im Kopf? Schnell erfassen, spater sortieren..."
            rows="3"
            class="textarea text-sm w-full resize-none"
          ></textarea>
        </div>

        <!-- Priority Selection -->
        <div class="flex items-center gap-2">
          <span class="text-xs text-gray-500">Prioritat:</span>
          <div class="flex gap-1">
            <button
              v-for="p in priorities"
              :key="p.value"
              @click="priority = p.value"
              class="px-2 py-1 rounded text-xs transition-colors"
              :class="priority === p.value
                ? 'bg-white/[0.08] ' + p.color + ' font-medium'
                : 'text-gray-500 hover:text-gray-300'"
            >
              {{ p.label }}
            </button>
          </div>
        </div>

        <!-- Advanced Options Toggle -->
        <button
          @click="showAdvanced = !showAdvanced"
          class="text-xs text-gray-500 hover:text-gray-300 flex items-center gap-1"
        >
          <span>Erweiterte Optionen</span>
          <ChevronDownIcon
            class="w-3 h-3 transition-transform"
            :class="{ 'rotate-180': showAdvanced }"
          />
        </button>

        <!-- Advanced Options -->
        <div v-if="showAdvanced" class="space-y-3 pt-2 border-t border-white/[0.06]">
          <!-- Note -->
          <div>
            <label class="text-xs text-gray-500 mb-1 block">Notiz</label>
            <textarea
              v-model="note"
              placeholder="Zusatzliche Details..."
              rows="2"
              class="textarea text-sm w-full resize-none"
            ></textarea>
          </div>

          <!-- Tags -->
          <div>
            <label class="text-xs text-gray-500 mb-1 flex items-center gap-1">
              <TagIcon class="w-3 h-3" />
              Tags
            </label>
            <div class="flex flex-wrap gap-1 mb-2">
              <span
                v-for="tag in tags"
                :key="tag"
                class="inline-flex items-center gap-1 px-2 py-0.5 bg-indigo-500/[0.12] text-indigo-300 rounded-full text-xs"
              >
                {{ tag }}
                <button @click="removeTag(tag)" class="hover:text-indigo-100">
                  <XMarkIcon class="w-3 h-3" />
                </button>
              </span>
            </div>
            <input
              v-model="tagInput"
              @keyup.enter="addTag"
              type="text"
              placeholder="Tag hinzufugen..."
              class="input text-sm w-full"
            />
          </div>

          <!-- Reminder -->
          <div>
            <label class="text-xs text-gray-500 mb-1 flex items-center gap-1">
              <BellIcon class="w-3 h-3" />
              Erinnerung
            </label>
            <input
              v-model="reminderAt"
              type="datetime-local"
              class="input text-sm w-full"
            />
          </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-between pt-2">
          <span class="text-xs text-gray-500">Cmd/Ctrl + Enter zum Speichern</span>
          <button
            @click="capture"
            :disabled="!content.trim() || saving"
            class="flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed rounded-xl text-sm font-medium text-white transition-colors"
          >
            <span v-if="saving">Speichern...</span>
            <template v-else>
              <PlusIcon class="w-4 h-4" />
              <span>Erfassen</span>
            </template>
          </button>
        </div>
      </div>
    </div>
  </Transition>
</template>
