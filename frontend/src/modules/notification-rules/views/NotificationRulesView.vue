<script setup>
import { ref, computed, onMounted } from 'vue'
import {
  PlusIcon,
  PencilSquareIcon,
  TrashIcon,
  PlayIcon,
  ChevronDownIcon,
  ChevronUpIcon,
  BellAlertIcon,
  BoltIcon,
  ClockIcon,
  GlobeAltIcon,
} from '@heroicons/vue/24/outline'
import { useNotificationRulesStore } from '@/modules/notification-rules/stores/notificationRulesStore'
import RuleBuilderModal from '@/modules/notification-rules/components/RuleBuilderModal.vue'

const store = useNotificationRulesStore()

// Local state
const showModal = ref(false)
const editingRule = ref(null)
const expandedRuleId = ref(null)
const loadingHistory = ref(false)
const confirmDeleteId = ref(null)

// Module color mapping for trigger event badges
const moduleColorMap = {
  'Server': 'bg-red-500/15 text-red-400',
  'Docker': 'bg-blue-500/15 text-blue-400',
  'Uptime': 'bg-emerald-500/15 text-emerald-400',
  'Projekte': 'bg-purple-500/15 text-purple-400',
  'Tickets': 'bg-amber-500/15 text-amber-400',
  'Storage': 'bg-cyan-500/15 text-cyan-400',
  'System': 'bg-orange-500/15 text-orange-400',
  'Sonstige': 'bg-gray-500/15 text-gray-400',
}

function getEventBadgeClass(triggerEvent) {
  const event = store.availableEvents.find(e => e.key === triggerEvent)
  const module = event?.module || 'Sonstige'
  return moduleColorMap[module] || moduleColorMap['Sonstige']
}

function getEventLabel(triggerEvent) {
  const event = store.availableEvents.find(e => e.key === triggerEvent)
  return event?.label || triggerEvent
}

function getActionsSummary(actions) {
  if (!actions || actions.length === 0) return '-'
  return actions.map(a => {
    if (a.type === 'push') return 'Push-Benachrichtigung'
    if (a.type === 'webhook') return 'Webhook'
    return a.type
  }).join(', ')
}

function formatTimestamp(dateString) {
  if (!dateString) return '-'
  return new Date(dateString).toLocaleString('de-DE', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

// Actions
function openCreateModal() {
  editingRule.value = null
  showModal.value = true
}

function openEditModal(rule) {
  editingRule.value = rule
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  editingRule.value = null
}

async function onSaved() {
  await store.fetchRules()
}

async function handleToggle(rule) {
  await store.toggleRule(rule.id)
}

async function handleTest(rule) {
  await store.testRule(rule.id)
}

function requestDelete(ruleId) {
  confirmDeleteId.value = ruleId
}

function cancelDelete() {
  confirmDeleteId.value = null
}

async function confirmDelete(ruleId) {
  await store.deleteRule(ruleId)
  confirmDeleteId.value = null
  if (expandedRuleId.value === ruleId) {
    expandedRuleId.value = null
  }
}

async function toggleExpand(ruleId) {
  if (expandedRuleId.value === ruleId) {
    expandedRuleId.value = null
    return
  }
  expandedRuleId.value = ruleId
  loadingHistory.value = true
  await store.fetchHistory(ruleId)
  loadingHistory.value = false
}

// Lifecycle
onMounted(async () => {
  await Promise.all([
    store.fetchRules(),
    store.fetchAvailableEvents(),
  ])
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-white">Benachrichtigungsregeln</h1>
        <p class="text-gray-400 mt-1">Automatische Benachrichtigungen bei Systemereignissen</p>
      </div>
      <button @click="openCreateModal" class="btn-primary">
        <PlusIcon class="w-5 h-5 mr-2" />
        Regel erstellen
      </button>
    </div>

    <!-- Loading -->
    <div v-if="store.loading" class="flex justify-center py-12">
      <div class="w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full animate-spin"></div>
    </div>

    <!-- Empty State -->
    <div
      v-else-if="store.rules.length === 0"
      class="card-glass p-12 text-center"
    >
      <div class="w-16 h-16 rounded-2xl bg-primary-500/10 flex items-center justify-center mx-auto mb-4">
        <BellAlertIcon class="w-8 h-8 text-primary-400" />
      </div>
      <h3 class="text-lg font-semibold text-white mb-2">Keine Regeln erstellt</h3>
      <p class="text-gray-400 text-sm mb-6">
        Erstelle deine erste Benachrichtigungsregel.
      </p>
      <button @click="openCreateModal" class="btn-primary">
        <PlusIcon class="w-5 h-5 mr-2" />
        Regel erstellen
      </button>
    </div>

    <!-- Rules List -->
    <div v-else class="space-y-4">
      <div
        v-for="rule in store.rules"
        :key="rule.id"
        class="card-glass overflow-hidden"
      >
        <!-- Rule Card Content -->
        <div class="p-5">
          <div class="flex items-start justify-between gap-4">
            <!-- Left: Info -->
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-3 mb-2">
                <h3 class="text-white font-semibold text-base truncate">{{ rule.name }}</h3>
                <span
                  class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium shrink-0"
                  :class="getEventBadgeClass(rule.trigger_event)"
                >
                  {{ getEventLabel(rule.trigger_event) }}
                </span>
              </div>

              <div class="flex flex-wrap items-center gap-4 text-sm">
                <!-- Actions summary -->
                <div class="flex items-center gap-1.5 text-gray-400">
                  <BoltIcon class="w-4 h-4 shrink-0" />
                  <span>{{ getActionsSummary(rule.actions) }}</span>
                </div>

                <!-- Trigger count -->
                <div class="flex items-center gap-1.5 text-gray-500">
                  <GlobeAltIcon class="w-4 h-4 shrink-0" />
                  <span>{{ rule.trigger_count || 0 }}x ausgelöst</span>
                </div>

                <!-- Last triggered -->
                <div class="flex items-center gap-1.5 text-gray-500">
                  <ClockIcon class="w-4 h-4 shrink-0" />
                  <span>{{ rule.last_triggered_at ? formatTimestamp(rule.last_triggered_at) : 'Noch nicht ausgelöst' }}</span>
                </div>
              </div>
            </div>

            <!-- Right: Controls -->
            <div class="flex items-center gap-2 shrink-0">
              <!-- Toggle Switch -->
              <button
                @click.stop="handleToggle(rule)"
                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none"
                :class="rule.is_active ? 'bg-emerald-500' : 'bg-white/[0.1]'"
                :title="rule.is_active ? 'Deaktivieren' : 'Aktivieren'"
              >
                <span
                  class="inline-block h-4 w-4 rounded-full bg-white transition-transform"
                  :class="rule.is_active ? 'translate-x-6' : 'translate-x-1'"
                ></span>
              </button>

              <!-- Test Button -->
              <button
                @click.stop="handleTest(rule)"
                class="btn-icon-sm text-gray-400 hover:text-amber-400"
                title="Regel testen"
              >
                <PlayIcon class="w-4 h-4" />
              </button>

              <!-- Edit Button -->
              <button
                @click.stop="openEditModal(rule)"
                class="btn-icon-sm text-gray-400 hover:text-primary-400"
                title="Bearbeiten"
              >
                <PencilSquareIcon class="w-4 h-4" />
              </button>

              <!-- Delete Button -->
              <button
                v-if="confirmDeleteId !== rule.id"
                @click.stop="requestDelete(rule.id)"
                class="btn-icon-sm text-gray-400 hover:text-red-400"
                title="Löschen"
              >
                <TrashIcon class="w-4 h-4" />
              </button>
              <div v-else class="flex items-center gap-1">
                <button
                  @click.stop="confirmDelete(rule.id)"
                  class="text-xs text-red-400 hover:text-red-300 font-medium px-2 py-1 rounded bg-red-500/10 hover:bg-red-500/20 transition-colors"
                >
                  Löschen
                </button>
                <button
                  @click.stop="cancelDelete"
                  class="text-xs text-gray-400 hover:text-white font-medium px-2 py-1"
                >
                  Abbrechen
                </button>
              </div>

              <!-- Expand/Collapse -->
              <button
                @click.stop="toggleExpand(rule.id)"
                class="btn-icon-sm text-gray-400 hover:text-white"
                title="Historie anzeigen"
              >
                <ChevronUpIcon v-if="expandedRuleId === rule.id" class="w-4 h-4" />
                <ChevronDownIcon v-else class="w-4 h-4" />
              </button>
            </div>
          </div>
        </div>

        <!-- Expanded: Execution History -->
        <div
          v-if="expandedRuleId === rule.id"
          class="border-t border-white/[0.06] bg-white/[0.02]"
        >
          <div class="p-5">
            <h4 class="text-sm font-medium text-gray-400 mb-3">Ausführungshistorie</h4>

            <!-- Loading History -->
            <div v-if="loadingHistory" class="flex justify-center py-6">
              <div class="w-5 h-5 border-2 border-primary-600 border-t-transparent rounded-full animate-spin"></div>
            </div>

            <!-- Empty History -->
            <div v-else-if="store.ruleHistory.length === 0" class="text-center py-6">
              <p class="text-gray-500 text-sm">Keine Ausführungen vorhanden</p>
            </div>

            <!-- History Entries -->
            <div v-else class="space-y-2">
              <div
                v-for="entry in store.ruleHistory"
                :key="entry.id"
                class="flex items-center justify-between px-4 py-2.5 rounded-lg bg-white/[0.03]"
              >
                <div class="flex items-center gap-3">
                  <span
                    class="w-2 h-2 rounded-full shrink-0"
                    :class="entry.status === 'success' ? 'bg-emerald-400' : 'bg-red-400'"
                  ></span>
                  <span class="text-sm text-gray-300">
                    {{ entry.status === 'success' ? 'Erfolgreich' : 'Fehler' }}
                  </span>
                  <span v-if="entry.message" class="text-sm text-gray-500 truncate max-w-xs">
                    — {{ entry.message }}
                  </span>
                </div>
                <span class="text-xs text-gray-500 font-mono whitespace-nowrap">
                  {{ formatTimestamp(entry.triggered_at) }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Rule Builder Modal -->
    <RuleBuilderModal
      :show="showModal"
      :rule="editingRule"
      @close="closeModal"
      @saved="onSaved"
    />
  </div>
</template>
