<script setup>
import { ref, computed } from 'vue'
import { useDiscordStore } from '../stores/discordStore'
import { useUiStore } from '@/stores/ui'
import {
  XMarkIcon,
  ArrowPathIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  show: Boolean
})

const emit = defineEmits(['close', 'added'])

const discordStore = useDiscordStore()
const uiStore = useUiStore()

// Form state
const step = ref(1)
const clientId = ref('')
const clientSecret = ref('')
const botToken = ref('')
const isValidating = ref(false)
const isAdding = ref(false)
const validatedBot = ref(null)
const validationError = ref('')

// Computed
const canValidate = computed(() => botToken.value.trim().length > 50)
const canSave = computed(() => clientId.value.trim() && botToken.value.trim() && validatedBot.value)

// Methods
function resetForm() {
  step.value = 1
  clientId.value = ''
  clientSecret.value = ''
  botToken.value = ''
  validatedBot.value = null
  validationError.value = ''
}

function close() {
  resetForm()
  emit('close')
}

async function validateToken() {
  if (!canValidate.value) return

  isValidating.value = true
  validationError.value = ''
  validatedBot.value = null

  try {
    const result = await discordStore.validateBot(botToken.value.trim())
    validatedBot.value = result
    step.value = 3
  } catch (error) {
    validationError.value = error.response?.data?.message || 'Ungültiger Bot Token'
  } finally {
    isValidating.value = false
  }
}

async function saveBot() {
  if (!canSave.value) return

  isAdding.value = true
  try {
    const bot = await discordStore.addBot({
      client_id: clientId.value.trim(),
      client_secret: clientSecret.value.trim() || null,
      bot_token: botToken.value.trim(),
    })

    uiStore.showSuccess('Discord Bot erfolgreich hinzugefügt!')
    emit('added', bot)
    close()
  } catch (error) {
    uiStore.showError(error.response?.data?.message || 'Fehler beim Hinzufügen des Bots')
  } finally {
    isAdding.value = false
  }
}
</script>

<template>
  <Teleport to="body">
    <div v-if="show" class="fixed inset-0 bg-black/60 backdrop-blur-md flex items-center justify-center z-50 p-4">
      <div class="modal w-full max-w-lg">
        <!-- Header -->
        <div class="p-6 border-b border-white/[0.06]">
          <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-white">Discord Bot hinzufügen</h2>
            <button @click="close" class="text-gray-400 hover:text-white">
              <XMarkIcon class="w-6 h-6" />
            </button>
          </div>

          <!-- Progress Steps -->
          <div class="flex items-center gap-2 mt-4">
            <div :class="['w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium', step >= 1 ? 'bg-primary-500 text-white' : 'bg-white/[0.08] text-gray-400']">1</div>
            <div :class="['flex-1 h-1 rounded', step >= 2 ? 'bg-primary-500' : 'bg-white/[0.08]']"></div>
            <div :class="['w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium', step >= 2 ? 'bg-primary-500 text-white' : 'bg-white/[0.08] text-gray-400']">2</div>
            <div :class="['flex-1 h-1 rounded', step >= 3 ? 'bg-primary-500' : 'bg-white/[0.08]']"></div>
            <div :class="['w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium', step >= 3 ? 'bg-primary-500 text-white' : 'bg-white/[0.08] text-gray-400']">3</div>
          </div>
        </div>

        <div class="p-6 space-y-4">
          <!-- Step 1: Instructions -->
          <div v-if="step === 1">
            <h3 class="text-lg font-medium text-white mb-4">Bot im Discord Developer Portal erstellen</h3>

            <div class="bg-white/[0.04] rounded-xl p-4 space-y-3">
              <ol class="list-decimal list-inside space-y-2 text-gray-300">
                <li>Öffne das <a href="https://discord.com/developers/applications" target="_blank" class="text-primary-400 hover:text-primary-300">Discord Developer Portal</a></li>
                <li>Klicke auf "New Application" und gib einen Namen ein</li>
                <li>Gehe zu "Bot" in der linken Seitenleiste</li>
                <li>Klicke "Add Bot" → "Yes, do it!"</li>
                <li>Aktiviere unter "Privileged Gateway Intents":
                  <ul class="list-disc list-inside ml-4 mt-1 text-gray-400 text-sm">
                    <li>Message Content Intent (für Nachrichteninhalt)</li>
                    <li>Server Members Intent (optional, für Mitgliederliste)</li>
                  </ul>
                </li>
                <li>Klicke "Reset Token" um den Bot Token zu kopieren</li>
              </ol>
            </div>

            <div class="bg-yellow-500/10 border border-yellow-500/20 rounded-lg p-3 mt-4">
              <p class="text-sm text-yellow-400">
                <ExclamationTriangleIcon class="w-4 h-4 inline mr-1" />
                <strong>Wichtig:</strong> Bewahre den Bot Token sicher auf! Er wird nur einmal angezeigt.
              </p>
            </div>
          </div>

          <!-- Step 2: Enter Credentials -->
          <div v-if="step === 2">
            <h3 class="text-lg font-medium text-white mb-4">Bot Credentials eingeben</h3>

            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-400 mb-2">
                  Application ID / Client ID <span class="text-red-400">*</span>
                </label>
                <input
                  v-model="clientId"
                  type="text"
                  class="input w-full"
                  placeholder="z.B. 123456789012345678"
                />
                <p class="text-xs text-gray-500 mt-1">
                  Findest du unter "General Information" → "Application ID"
                </p>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-400 mb-2">
                  Client Secret (optional)
                </label>
                <input
                  v-model="clientSecret"
                  type="password"
                  class="input w-full"
                  placeholder="Für OAuth2 Features"
                />
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-400 mb-2">
                  Bot Token <span class="text-red-400">*</span>
                </label>
                <input
                  v-model="botToken"
                  type="password"
                  class="input w-full"
                  placeholder="Dein Bot Token..."
                />
                <p class="text-xs text-gray-500 mt-1">
                  Findest du unter "Bot" → "Token"
                </p>
              </div>

              <!-- Validation Error -->
              <div v-if="validationError" class="bg-red-500/10 border border-red-500/20 rounded-lg p-3">
                <p class="text-sm text-red-400">{{ validationError }}</p>
              </div>
            </div>
          </div>

          <!-- Step 3: Validation Result -->
          <div v-if="step === 3 && validatedBot">
            <div class="text-center">
              <div class="w-20 h-20 mx-auto rounded-full bg-white/[0.08] overflow-hidden mb-4">
                <img
                  v-if="validatedBot.avatar_url"
                  :src="validatedBot.avatar_url"
                  class="w-full h-full object-cover"
                  alt=""
                />
              </div>

              <div class="flex items-center justify-center gap-2 text-green-400 mb-2">
                <CheckCircleIcon class="w-5 h-5" />
                <span class="font-medium">Bot erfolgreich validiert!</span>
              </div>

              <h3 class="text-xl font-bold text-white">
                {{ validatedBot.username }}
                <span class="text-gray-500 font-normal">#{{ validatedBot.discriminator }}</span>
              </h3>

              <p class="text-gray-400 text-sm mt-2">
                Bot ID: {{ validatedBot.id }}
              </p>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="p-6 border-t border-white/[0.06] flex justify-between">
          <button
            v-if="step > 1"
            @click="step--"
            class="btn-secondary"
          >
            Zurück
          </button>
          <div v-else></div>

          <div class="flex gap-3">
            <button @click="close" class="btn-secondary">Abbrechen</button>

            <!-- Step 1: Continue -->
            <button v-if="step === 1" @click="step = 2" class="btn-primary">
              Weiter
            </button>

            <!-- Step 2: Validate -->
            <button
              v-if="step === 2"
              @click="validateToken"
              :disabled="!canValidate || isValidating"
              class="btn-primary"
            >
              <ArrowPathIcon v-if="isValidating" class="w-5 h-5 mr-2 animate-spin" />
              Validieren
            </button>

            <!-- Step 3: Save -->
            <button
              v-if="step === 3"
              @click="saveBot"
              :disabled="!canSave || isAdding"
              class="btn-primary"
            >
              <ArrowPathIcon v-if="isAdding" class="w-5 h-5 mr-2 animate-spin" />
              Bot hinzufügen
            </button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>
