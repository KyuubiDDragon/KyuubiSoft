<script setup>
import { ref, computed, onMounted } from 'vue'
import {
  PlusIcon,
  XMarkIcon,
  CheckIcon,
  FireIcon,
  PencilIcon,
  TrashIcon,
  SparklesIcon,
} from '@heroicons/vue/24/outline'
import { useHabitStore } from '../stores/habitStore.js'

const habitStore = useHabitStore()

const showForm = ref(false)
const editingHabit = ref(null)
const form = ref({
  name: '',
  description: '',
  frequency: 'daily',
  color: '#3B82F6',
  icon: 'sparkles',
})

const colorOptions = [
  '#3B82F6', '#10B981', '#F59E0B', '#EF4444',
  '#8B5CF6', '#EC4899', '#06B6D4', '#84CC16',
]

const today = new Date().toISOString().split('T')[0]

const completedCount = computed(() =>
  habitStore.habits.filter(h => h.completed_today && h.is_active).length
)

const totalActive = computed(() =>
  habitStore.habits.filter(h => h.is_active).length
)

function openCreate() {
  editingHabit.value = null
  form.value = { name: '', description: '', frequency: 'daily', color: '#3B82F6', icon: 'sparkles' }
  showForm.value = true
}

function openEdit(habit) {
  editingHabit.value = habit
  form.value = {
    name: habit.name,
    description: habit.description || '',
    frequency: habit.frequency,
    color: habit.color,
    icon: habit.icon,
  }
  showForm.value = true
}

async function saveHabit() {
  if (!form.value.name.trim()) return
  if (editingHabit.value) {
    await habitStore.updateHabit(editingHabit.value.id, form.value)
  } else {
    await habitStore.createHabit(form.value)
  }
  showForm.value = false
}

async function deleteHabit(habit) {
  if (!confirm(`"${habit.name}" wirklich löschen?`)) return
  await habitStore.deleteHabit(habit.id)
}

async function toggleHabit(habit) {
  await habitStore.toggleComplete(habit.id)
}

// Generate last 7 days for heatmap preview
function getLast7Days() {
  const days = []
  for (let i = 6; i >= 0; i--) {
    const d = new Date()
    d.setDate(d.getDate() - i)
    days.push(d.toISOString().split('T')[0])
  }
  return days
}

const last7Days = getLast7Days()

onMounted(async () => {
  await habitStore.fetchHabits()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-white">Habit Tracker</h1>
        <p class="text-gray-400 mt-1">
          Heute: {{ completedCount }}/{{ totalActive }} Habits erledigt
        </p>
      </div>
      <button @click="openCreate" class="btn-primary">
        <PlusIcon class="w-4 h-4 mr-2" />
        Neuer Habit
      </button>
    </div>

    <!-- Progress Bar -->
    <div v-if="totalActive > 0" class="card p-4">
      <div class="flex items-center justify-between mb-2">
        <span class="text-sm text-gray-400">Heutiger Fortschritt</span>
        <span class="text-sm font-medium text-white">
          {{ totalActive > 0 ? Math.round((completedCount / totalActive) * 100) : 0 }}%
        </span>
      </div>
      <div class="h-2 bg-white/[0.08] rounded-full overflow-hidden">
        <div
          class="h-full bg-primary-500 rounded-full transition-all duration-500"
          :style="{ width: `${totalActive > 0 ? (completedCount / totalActive) * 100 : 0}%` }"
        ></div>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="habitStore.isLoading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div v-for="i in 3" :key="i" class="card p-6 animate-pulse">
        <div class="h-4 bg-white/[0.08] rounded w-1/2 mb-3"></div>
        <div class="h-10 bg-white/[0.08] rounded w-full"></div>
      </div>
    </div>

    <!-- Habits Grid -->
    <div v-else-if="habitStore.habits.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div
        v-for="habit in habitStore.habits"
        :key="habit.id"
        class="card p-4 relative"
        :class="{ 'opacity-50': !habit.is_active }"
      >
        <!-- Top row: color dot + name + actions -->
        <div class="flex items-start justify-between mb-3">
          <div class="flex items-center gap-2">
            <span class="w-3 h-3 rounded-full flex-shrink-0" :style="{ backgroundColor: habit.color }"></span>
            <h3 class="font-semibold text-white">{{ habit.name }}</h3>
          </div>
          <div class="flex items-center gap-1">
            <button @click="openEdit(habit)" class="p-1 text-gray-500 hover:text-gray-300">
              <PencilIcon class="w-3.5 h-3.5" />
            </button>
            <button @click="deleteHabit(habit)" class="p-1 text-gray-500 hover:text-red-400">
              <TrashIcon class="w-3.5 h-3.5" />
            </button>
          </div>
        </div>

        <!-- Description -->
        <p v-if="habit.description" class="text-xs text-gray-500 mb-3 line-clamp-2">{{ habit.description }}</p>

        <!-- Stats row -->
        <div class="flex items-center gap-3 mb-4 text-xs text-gray-500">
          <span class="flex items-center gap-1">
            <FireIcon class="w-3 h-3 text-orange-400" />
            {{ habit.streak }} Tag{{ habit.streak !== 1 ? 'e' : '' }}
          </span>
          <span class="capitalize">{{ habit.frequency }}</span>
        </div>

        <!-- 7-day mini heatmap -->
        <div class="flex gap-1 mb-4">
          <div
            v-for="day in last7Days"
            :key="day"
            class="flex-1 h-2 rounded-sm"
            :class="day === today ? 'border border-white/[0.06]' : ''"
            :style="{
              backgroundColor: habit.completed_today && day === today
                ? habit.color
                : 'rgb(31, 41, 55)',
            }"
            :title="day"
          ></div>
        </div>

        <!-- Complete button -->
        <button
          @click="toggleHabit(habit)"
          class="w-full py-2 rounded-lg text-sm font-medium transition-all"
          :style="habit.completed_today
            ? { backgroundColor: habit.color + '33', color: habit.color }
            : { backgroundColor: 'rgb(31, 41, 55)', color: '#9CA3AF' }"
        >
          <span class="flex items-center justify-center gap-2">
            <CheckIcon class="w-4 h-4" />
            {{ habit.completed_today ? 'Erledigt!' : 'Als erledigt markieren' }}
          </span>
        </button>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="!habitStore.isLoading" class="card p-12 text-center">
      <SparklesIcon class="w-16 h-16 text-gray-600 mx-auto mb-4" />
      <h3 class="text-lg font-semibold text-white mb-2">Keine Habits vorhanden</h3>
      <p class="text-gray-500 mb-4">Erstelle deinen ersten Habit und starte eine neue Gewohnheit.</p>
      <button @click="openCreate" class="btn-primary">
        <PlusIcon class="w-4 h-4 mr-2" />
        Ersten Habit erstellen
      </button>
    </div>

    <!-- Form Modal -->
    <Teleport to="body">
      <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100"
                  leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
        <div v-if="showForm" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4" @click.self="showForm = false">
          <div class="bg-white/[0.04] border border-white/[0.06] rounded-xl shadow-float w-full max-w-md">
            <div class="flex items-center justify-between px-6 py-4 border-b border-white/[0.06]">
              <h3 class="text-lg font-semibold text-white">{{ editingHabit ? 'Habit bearbeiten' : 'Neuer Habit' }}</h3>
              <button @click="showForm = false" class="text-gray-400 hover:text-white">
                <XMarkIcon class="w-5 h-5" />
              </button>
            </div>
            <div class="p-6 space-y-4">
              <div>
                <label class="label">Name *</label>
                <input v-model="form.name" type="text" class="input" placeholder="z.B. Sport, Lesen, Meditation..." />
              </div>
              <div>
                <label class="label">Beschreibung</label>
                <textarea v-model="form.description" class="input" rows="2" placeholder="Optional"></textarea>
              </div>
              <div>
                <label class="label">Häufigkeit</label>
                <select v-model="form.frequency" class="input">
                  <option value="daily">Täglich</option>
                  <option value="weekly">Wöchentlich</option>
                  <option value="monthly">Monatlich</option>
                </select>
              </div>
              <div>
                <label class="label">Farbe</label>
                <div class="flex gap-2 flex-wrap">
                  <button
                    v-for="color in colorOptions"
                    :key="color"
                    @click="form.color = color"
                    class="w-8 h-8 rounded-full border-2 transition-all"
                    :style="{ backgroundColor: color }"
                    :class="form.color === color ? 'border-white scale-110' : 'border-transparent'"
                  ></button>
                </div>
              </div>
              <div class="flex gap-3 pt-2">
                <button @click="showForm = false" class="btn-secondary flex-1">Abbrechen</button>
                <button @click="saveHabit" class="btn-primary flex-1" :disabled="!form.name.trim()">
                  {{ editingHabit ? 'Speichern' : 'Erstellen' }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>
