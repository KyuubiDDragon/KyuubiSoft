<script setup>
import { ref, watch, computed } from 'vue'
import api from '@/core/api/axios'
import {
  XMarkIcon,
  LinkIcon,
  ClipboardDocumentIcon,
  CheckIcon,
  TrashIcon,
  EyeIcon,
  LockClosedIcon,
  CalendarIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  show: Boolean,
  contract: { type: Object, default: null },
})

const emit = defineEmits(['close', 'updated'])

const isLoading = ref(false)
const shareActive = ref(false)
const shareToken = ref('')
const sharePassword = ref('')
const shareExpiresAt = ref('')
const shareViewCount = ref(0)
const copied = ref(false)
const error = ref('')
const setPassword = ref(false)

const shareUrl = computed(() => {
  if (!shareToken.value) return ''
  return `${window.location.origin}/contract/${shareToken.value}`
})

watch(() => props.show, async (val) => {
  if (val && props.contract) {
    await loadShareInfo()
  } else {
    resetState()
  }
})

function resetState() {
  shareActive.value = false
  shareToken.value = ''
  sharePassword.value = ''
  shareExpiresAt.value = ''
  shareViewCount.value = 0
  copied.value = false
  error.value = ''
  setPassword.value = false
}

async function loadShareInfo() {
  isLoading.value = true
  error.value = ''
  try {
    const res = await api.get(`/api/v1/contracts/${props.contract.id}/share`)
    const data = res.data.data
    shareActive.value = data.active
    shareToken.value = data.token || ''
    shareExpiresAt.value = data.expires_at ? data.expires_at.substring(0, 16) : ''
    shareViewCount.value = data.view_count || 0
    setPassword.value = data.has_password || false
  } catch {
    shareActive.value = false
  } finally {
    isLoading.value = false
  }
}

async function enableShare() {
  isLoading.value = true
  error.value = ''
  try {
    const payload = {}
    if (setPassword.value && sharePassword.value) {
      payload.password = sharePassword.value
    }
    if (shareExpiresAt.value) {
      payload.expires_at = shareExpiresAt.value
    }
    const res = await api.post(`/api/v1/contracts/${props.contract.id}/share`, payload)
    const data = res.data.data
    shareActive.value = true
    shareToken.value = data.token
    shareViewCount.value = 0
    sharePassword.value = ''
    emit('updated')
  } catch (e) {
    error.value = e.response?.data?.message || 'Fehler beim Aktivieren'
  } finally {
    isLoading.value = false
  }
}

async function disableShare() {
  isLoading.value = true
  error.value = ''
  try {
    await api.delete(`/api/v1/contracts/${props.contract.id}/share`)
    resetState()
    emit('updated')
  } catch (e) {
    error.value = e.response?.data?.message || 'Fehler beim Deaktivieren'
  } finally {
    isLoading.value = false
  }
}

async function copyLink() {
  try {
    await navigator.clipboard.writeText(shareUrl.value)
    copied.value = true
    setTimeout(() => { copied.value = false }, 2000)
  } catch {
    // Fallback
    const ta = document.createElement('textarea')
    ta.value = shareUrl.value
    document.body.appendChild(ta)
    ta.select()
    document.execCommand('copy')
    document.body.removeChild(ta)
    copied.value = true
    setTimeout(() => { copied.value = false }, 2000)
  }
}
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition ease-out duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition ease-in duration-150"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="show && contract"
        class="fixed inset-0 bg-black/60 backdrop-blur-md flex items-center justify-center z-50 p-4"
        @click.self="$emit('close')"
      >
        <div class="modal w-full max-w-lg">
          <!-- Header -->
          <div class="px-6 py-4 border-b border-white/[0.06] flex items-center justify-between">
            <div class="flex items-center gap-2">
              <LinkIcon class="w-5 h-5 text-primary-400" />
              <h2 class="text-lg font-bold text-white">Vertrag teilen</h2>
            </div>
            <button @click="$emit('close')" class="p-2 rounded-lg text-gray-400 hover:text-white hover:bg-white/[0.04]">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <div class="p-6 space-y-5">
            <!-- Loading -->
            <div v-if="isLoading" class="text-center py-8 text-gray-400 text-sm">Lade...</div>

            <!-- Share Active -->
            <template v-else-if="shareActive">
              <!-- Share Link -->
              <div>
                <label class="text-xs text-gray-400 uppercase font-semibold block mb-2">Share-Link</label>
                <div class="flex items-center gap-2">
                  <input
                    :value="shareUrl"
                    readonly
                    class="input flex-1 text-sm font-mono"
                    @focus="$event.target.select()"
                  />
                  <button
                    @click="copyLink"
                    class="flex items-center gap-1 px-3 py-2 rounded-lg text-sm font-medium transition-colors"
                    :class="copied ? 'bg-green-600 text-white' : 'bg-primary-600 text-white hover:bg-primary-500'"
                  >
                    <CheckIcon v-if="copied" class="w-4 h-4" />
                    <ClipboardDocumentIcon v-else class="w-4 h-4" />
                    {{ copied ? 'Kopiert!' : 'Kopieren' }}
                  </button>
                </div>
              </div>

              <!-- Stats -->
              <div class="flex items-center gap-4 text-sm">
                <div class="flex items-center gap-1.5 text-gray-400">
                  <EyeIcon class="w-4 h-4" />
                  <span>{{ shareViewCount }} Aufrufe</span>
                </div>
                <div v-if="setPassword" class="flex items-center gap-1.5 text-amber-400">
                  <LockClosedIcon class="w-4 h-4" />
                  <span>Passwortgeschützt</span>
                </div>
                <div v-if="shareExpiresAt" class="flex items-center gap-1.5 text-gray-400">
                  <CalendarIcon class="w-4 h-4" />
                  <span>Läuft ab: {{ new Date(shareExpiresAt).toLocaleDateString('de-DE') }}</span>
                </div>
              </div>

              <!-- Info -->
              <div class="text-xs text-gray-500 bg-white/[0.03] rounded-lg p-3">
                Der Empfänger kann den Vertrag einsehen und als Kunde (Auftraggeber) direkt online unterschreiben.
                Die Unterschrift wird automatisch im System gespeichert.
              </div>

              <!-- Disable -->
              <button
                @click="disableShare"
                class="flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium text-red-400 hover:text-red-300 hover:bg-red-500/10 transition-colors"
              >
                <TrashIcon class="w-4 h-4" />
                Share-Link deaktivieren
              </button>
            </template>

            <!-- Share Not Active -->
            <template v-else>
              <div class="text-center py-4">
                <LinkIcon class="w-10 h-10 text-gray-500 mx-auto mb-3" />
                <p class="text-sm text-gray-400 mb-1">Dieser Vertrag ist noch nicht geteilt.</p>
                <p class="text-xs text-gray-500">Erstellen Sie einen Share-Link, damit Ihr Kunde den Vertrag einsehen und unterschreiben kann.</p>
              </div>

              <!-- Password Option -->
              <div class="space-y-3">
                <label class="flex items-center gap-2 cursor-pointer">
                  <input type="checkbox" v-model="setPassword" class="rounded border-gray-600 text-primary-500 focus:ring-primary-500 bg-dark-700" />
                  <span class="text-sm text-gray-300">Mit Passwort schützen</span>
                </label>
                <input
                  v-if="setPassword"
                  v-model="sharePassword"
                  type="text"
                  placeholder="Passwort eingeben..."
                  class="input text-sm w-full"
                />
              </div>

              <!-- Expiry Option -->
              <div>
                <label class="text-xs text-gray-400 uppercase font-semibold block mb-1.5">Ablaufdatum (optional)</label>
                <input
                  v-model="shareExpiresAt"
                  type="datetime-local"
                  class="input text-sm w-full"
                />
              </div>

              <!-- Enable Button -->
              <button
                @click="enableShare"
                :disabled="isLoading || (setPassword && !sharePassword)"
                class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold bg-primary-600 text-white hover:bg-primary-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              >
                <LinkIcon class="w-4 h-4" />
                Share-Link erstellen
              </button>
            </template>

            <!-- Error -->
            <div v-if="error" class="text-sm text-red-400 bg-red-500/10 rounded-lg p-3">{{ error }}</div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
