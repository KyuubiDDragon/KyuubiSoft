<script setup>
import { ref, onMounted, nextTick, computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'
import {
  LockClosedIcon,
  DocumentTextIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
  TrashIcon,
} from '@heroicons/vue/24/outline'

const route = useRoute()
const token = computed(() => route.params.token)

// State
const state = ref('loading') // loading | password | view | signed | error
const contract = ref(null)
const password = ref('')
const passwordError = ref('')
const errorMessage = ref('')
const isSubmitting = ref(false)
const isSigning = ref(false)
const signerName = ref('')

// Canvas refs
const canvasRef = ref(null)
const isDrawing = ref(false)
const hasDrawn = ref(false)
let ctx = null

// API base
const apiBase = window.location.origin + '/api/v1'

onMounted(async () => {
  // Detect system dark mode for public page (no auth store available)
  if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
    document.documentElement.classList.add('dark')
  }
  await loadContract()
})

async function loadContract(pw) {
  state.value = 'loading'
  passwordError.value = ''
  try {
    const config = {}
    if (pw) {
      config.method = 'post'
      config.url = `${apiBase}/contracts/public/${token.value}`
      config.data = { password: pw }
    } else {
      config.method = 'get'
      config.url = `${apiBase}/contracts/public/${token.value}`
    }
    const res = await axios(config)
    const resData = res.data.data
    // Backend returns requires_password when password needed but not provided
    if (resData.requires_password) {
      state.value = 'password'
      return
    }
    contract.value = resData
    state.value = 'view'
    if (contract.value.party_b_signed_at) {
      state.value = 'signed'
    }
  } catch (e) {
    const status = e.response?.status
    const msg = e.response?.data?.message || ''
    if (status === 401 && msg.includes('Passwort')) {
      if (pw) {
        passwordError.value = 'Falsches Passwort'
        state.value = 'password'
      } else {
        state.value = 'password'
      }
    } else if (status === 403) {
      state.value = 'error'
      errorMessage.value = msg || 'Dieser Link ist abgelaufen.'
    } else if (status === 404) {
      state.value = 'error'
      errorMessage.value = 'Vertrag nicht gefunden.'
    } else {
      state.value = 'error'
      errorMessage.value = msg || 'Ein Fehler ist aufgetreten.'
    }
  }
}

async function submitPassword() {
  if (!password.value) return
  isSubmitting.value = true
  await loadContract(password.value)
  isSubmitting.value = false
}

// Canvas signature functions
async function initCanvas() {
  await nextTick()
  const canvas = canvasRef.value
  if (!canvas) return
  ctx = canvas.getContext('2d')
  const rect = canvas.parentElement.getBoundingClientRect()
  canvas.width = rect.width
  canvas.height = 180
  const isDark = document.documentElement.classList.contains('dark')
  ctx.strokeStyle = isDark ? '#e5e7eb' : '#111827'
  ctx.lineWidth = 2
  ctx.lineCap = 'round'
  ctx.lineJoin = 'round'
  clearCanvas()
}

function clearCanvas() {
  if (!ctx || !canvasRef.value) return
  const isDark = document.documentElement.classList.contains('dark')
  ctx.fillStyle = isDark ? '#374151' : '#ffffff'
  ctx.fillRect(0, 0, canvasRef.value.width, canvasRef.value.height)
  ctx.strokeStyle = isDark ? '#4b5563' : '#e5e7eb'
  ctx.lineWidth = 1
  ctx.beginPath()
  ctx.moveTo(20, canvasRef.value.height - 40)
  ctx.lineTo(canvasRef.value.width - 20, canvasRef.value.height - 40)
  ctx.stroke()
  ctx.strokeStyle = isDark ? '#e5e7eb' : '#111827'
  ctx.lineWidth = 2
  hasDrawn.value = false
}

function getPos(e) {
  const rect = canvasRef.value.getBoundingClientRect()
  const clientX = e.touches ? e.touches[0].clientX : e.clientX
  const clientY = e.touches ? e.touches[0].clientY : e.clientY
  return { x: clientX - rect.left, y: clientY - rect.top }
}

function startDraw(e) {
  e.preventDefault()
  isDrawing.value = true
  hasDrawn.value = true
  const pos = getPos(e)
  ctx.beginPath()
  ctx.moveTo(pos.x, pos.y)
}

function draw(e) {
  if (!isDrawing.value) return
  e.preventDefault()
  const pos = getPos(e)
  ctx.lineTo(pos.x, pos.y)
  ctx.stroke()
}

function stopDraw() {
  isDrawing.value = false
}

async function submitSignature() {
  if (!canvasRef.value || !hasDrawn.value) return
  isSigning.value = true
  try {
    const signatureData = canvasRef.value.toDataURL('image/png')
    const payload = {
      signature_data: signatureData,
      signer_name: signerName.value || undefined,
    }
    if (password.value) {
      payload.password = password.value
    }
    await axios.post(`${apiBase}/contracts/public/${token.value}/sign`, payload)
    state.value = 'signed'
  } catch (e) {
    errorMessage.value = e.response?.data?.message || 'Fehler beim Unterschreiben.'
  } finally {
    isSigning.value = false
  }
}

// Init canvas when view state is reached
watch(state, (val) => {
  if (val === 'view') {
    initCanvas()
  }
})

// Format helpers
function formatDate(d) {
  if (!d) return '-'
  return new Date(d).toLocaleDateString('de-DE')
}

function formatCurrency(amount, currency = 'EUR') {
  return new Intl.NumberFormat('de-DE', { style: 'currency', currency }).format(amount || 0)
}

const contractTypeLabels = {
  license: 'Softwarelizenzvertrag',
  development: 'Softwareentwicklungsvertrag',
  saas: 'SaaS-Vertrag',
  maintenance: 'Wartungsvertrag',
  nda: 'Geheimhaltungsvereinbarung',
}
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 dark:from-dark-950 dark:to-dark-900">
    <!-- Header -->
    <div class="bg-white dark:bg-dark-800 border-b border-gray-200 dark:border-dark-700 shadow-sm">
      <div class="max-w-4xl mx-auto px-6 py-4 flex items-center gap-3">
        <DocumentTextIcon class="w-6 h-6 text-blue-600" />
        <h1 class="text-lg font-bold text-gray-900 dark:text-gray-100">Vertragsdokument</h1>
      </div>
    </div>

    <div class="max-w-4xl mx-auto px-6 py-8">
      <!-- Loading -->
      <div v-if="state === 'loading'" class="text-center py-20">
        <div class="w-8 h-8 border-2 border-blue-600 border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
        <p class="text-gray-500 dark:text-gray-400">Vertrag wird geladen...</p>
      </div>

      <!-- Password Required -->
      <div v-else-if="state === 'password'" class="max-w-md mx-auto py-20">
        <div class="bg-white dark:bg-dark-800 rounded-2xl shadow-lg border border-gray-200 dark:border-dark-700 p-8 text-center">
          <div class="w-14 h-14 bg-amber-100 dark:bg-amber-900/30 rounded-xl flex items-center justify-center mx-auto mb-4">
            <LockClosedIcon class="w-7 h-7 text-amber-600" />
          </div>
          <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-2">Passwort erforderlich</h2>
          <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Dieses Dokument ist passwortgeschützt. Bitte geben Sie das Passwort ein.</p>

          <form @submit.prevent="submitPassword" class="space-y-4">
            <div>
              <input
                v-model="password"
                type="password"
                placeholder="Passwort eingeben..."
                class="w-full px-4 py-3 rounded-xl border border-gray-300 dark:border-dark-600 text-gray-900 dark:text-gray-100 dark:bg-dark-700 placeholder-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:focus:ring-blue-800 outline-none transition-colors"
                autofocus
              />
              <p v-if="passwordError" class="mt-2 text-sm text-red-600">{{ passwordError }}</p>
            </div>
            <button
              type="submit"
              :disabled="!password || isSubmitting"
              class="w-full py-3 rounded-xl bg-blue-600 text-white font-semibold hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              {{ isSubmitting ? 'Prüfe...' : 'Zugang erhalten' }}
            </button>
          </form>
        </div>
      </div>

      <!-- Error -->
      <div v-else-if="state === 'error'" class="max-w-md mx-auto py-20">
        <div class="bg-white dark:bg-dark-800 rounded-2xl shadow-lg border border-gray-200 dark:border-dark-700 p-8 text-center">
          <div class="w-14 h-14 bg-red-100 dark:bg-red-900/30 rounded-xl flex items-center justify-center mx-auto mb-4">
            <ExclamationTriangleIcon class="w-7 h-7 text-red-600" />
          </div>
          <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-2">Zugriff nicht möglich</h2>
          <p class="text-sm text-gray-500 dark:text-gray-400">{{ errorMessage }}</p>
        </div>
      </div>

      <!-- Contract View -->
      <template v-else-if="state === 'view' && contract">
        <!-- Contract Card -->
        <div class="bg-white dark:bg-dark-800 rounded-2xl shadow-lg border border-gray-200 dark:border-dark-700 overflow-hidden mb-8">
          <!-- Contract Header -->
          <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-8 py-6 text-white">
            <div class="flex items-start justify-between">
              <div>
                <div class="text-blue-200 text-sm font-medium">{{ contractTypeLabels[contract.contract_type] || contract.contract_type }}</div>
                <h2 class="text-2xl font-bold mt-1">{{ contract.title }}</h2>
                <div class="text-blue-200 text-sm mt-1">{{ contract.contract_number }}</div>
              </div>
              <div class="text-right">
                <div class="text-3xl font-bold">{{ formatCurrency(contract.total_value, contract.currency) }}</div>
              </div>
            </div>
          </div>

          <!-- Parties -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-8 border-b border-gray-100 dark:border-dark-700">
            <div>
              <div class="text-xs text-gray-400 uppercase tracking-wider font-semibold mb-2">Auftragnehmer</div>
              <div class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ contract.party_a_name }}</div>
              <div v-if="contract.party_a_company" class="text-sm text-gray-600 dark:text-gray-400">{{ contract.party_a_company }}</div>
              <div v-if="contract.party_a_address" class="text-sm text-gray-500 dark:text-gray-400 mt-1 whitespace-pre-line">{{ contract.party_a_address }}</div>
              <div v-if="contract.party_a_email" class="text-sm text-gray-500 dark:text-gray-400">{{ contract.party_a_email }}</div>
              <div v-if="contract.party_a_signed_at" class="text-sm text-green-600 font-medium mt-2">
                <CheckCircleIcon class="w-4 h-4 inline-block mr-1" />
                Unterschrieben am {{ formatDate(contract.party_a_signed_at) }}
              </div>
            </div>
            <div>
              <div class="text-xs text-gray-400 uppercase tracking-wider font-semibold mb-2">Auftraggeber</div>
              <div class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ contract.party_b_name }}</div>
              <div v-if="contract.party_b_company" class="text-sm text-gray-600 dark:text-gray-400">{{ contract.party_b_company }}</div>
              <div v-if="contract.party_b_address" class="text-sm text-gray-500 dark:text-gray-400 mt-1 whitespace-pre-line">{{ contract.party_b_address }}</div>
              <div v-if="contract.party_b_email" class="text-sm text-gray-500 dark:text-gray-400">{{ contract.party_b_email }}</div>
            </div>
          </div>

          <!-- Contract Details -->
          <div class="grid grid-cols-2 md:grid-cols-4 gap-6 p-8 border-b border-gray-100 dark:border-dark-700">
            <div>
              <div class="text-xs text-gray-400 uppercase font-semibold">Vertragsbeginn</div>
              <div class="text-sm font-medium text-gray-900 dark:text-gray-100 mt-1">{{ formatDate(contract.start_date) }}</div>
            </div>
            <div>
              <div class="text-xs text-gray-400 uppercase font-semibold">Vertragsende</div>
              <div class="text-sm font-medium text-gray-900 dark:text-gray-100 mt-1">{{ contract.end_date ? formatDate(contract.end_date) : 'Unbefristet' }}</div>
            </div>
            <div>
              <div class="text-xs text-gray-400 uppercase font-semibold">Zahlungsplan</div>
              <div class="text-sm font-medium text-gray-900 dark:text-gray-100 mt-1">{{ contract.payment_schedule || '-' }}</div>
            </div>
            <div>
              <div class="text-xs text-gray-400 uppercase font-semibold">Kündigungsfrist</div>
              <div class="text-sm font-medium text-gray-900 dark:text-gray-100 mt-1">{{ contract.notice_period_days || 30 }} Tage</div>
            </div>
          </div>

          <!-- Contract Content (all §-paragraphs) -->
          <div v-if="contract.content_html" class="p-8 border-b border-gray-100 dark:border-dark-700">
            <div class="text-xs text-gray-400 uppercase font-semibold mb-3">Vertragsklauseln</div>
            <div class="prose prose-sm max-w-none text-gray-700 dark:text-gray-300 dark:prose-invert" v-html="contract.content_html"></div>
          </div>

          <!-- Notes -->
          <div v-if="contract.notes" class="p-8">
            <div class="text-xs text-gray-400 uppercase font-semibold mb-2">Anmerkungen</div>
            <div class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ contract.notes }}</div>
          </div>
        </div>

        <!-- Signature Section -->
        <div class="bg-white dark:bg-dark-800 rounded-2xl shadow-lg border border-gray-200 dark:border-dark-700 overflow-hidden">
          <div class="px-8 py-5 border-b border-gray-100 dark:border-dark-700 bg-gray-50 dark:bg-dark-850">
            <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Vertrag unterschreiben</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Zeichnen Sie Ihre Unterschrift in das Feld unten. Sie können auch den Touchscreen verwenden.</p>
          </div>

          <div class="p-8 space-y-4">
            <!-- Signer Name -->
            <div>
              <label class="text-sm text-gray-600 dark:text-gray-400 font-medium block mb-1.5">Ihr Name</label>
              <input
                v-model="signerName"
                type="text"
                placeholder="Vor- und Nachname..."
                class="w-full px-4 py-2.5 rounded-xl border border-gray-300 dark:border-dark-600 text-gray-900 dark:text-gray-100 dark:bg-dark-700 placeholder-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:focus:ring-blue-800 outline-none transition-colors"
              />
            </div>

            <!-- Signature Canvas -->
            <div>
              <label class="text-sm text-gray-600 dark:text-gray-400 font-medium block mb-1.5">Unterschrift</label>
              <div class="bg-white dark:bg-dark-700 rounded-xl overflow-hidden border-2 border-gray-200 dark:border-dark-600 border-dashed">
                <canvas
                  ref="canvasRef"
                  class="w-full cursor-crosshair touch-none"
                  @mousedown="startDraw"
                  @mousemove="draw"
                  @mouseup="stopDraw"
                  @mouseleave="stopDraw"
                  @touchstart="startDraw"
                  @touchmove="draw"
                  @touchend="stopDraw"
                ></canvas>
              </div>
              <div class="flex items-center justify-between mt-2">
                <button
                  @click="clearCanvas"
                  class="flex items-center gap-1 px-3 py-1.5 rounded-lg text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-dark-700 transition-colors"
                >
                  <TrashIcon class="w-4 h-4" /> Löschen
                </button>
                <span v-if="!hasDrawn" class="text-xs text-gray-400">Bitte unterschreiben Sie oben</span>
              </div>
            </div>

            <!-- Error -->
            <div v-if="errorMessage" class="text-sm text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 rounded-lg p-3 border border-red-200 dark:border-red-800">{{ errorMessage }}</div>

            <!-- Submit -->
            <button
              @click="submitSignature"
              :disabled="!hasDrawn || isSigning"
              class="w-full py-3 rounded-xl bg-green-600 text-white font-semibold hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors text-base"
            >
              {{ isSigning ? 'Wird gespeichert...' : 'Vertrag verbindlich unterschreiben' }}
            </button>

            <p class="text-xs text-gray-400 text-center">
              Mit dem Klick auf "Vertrag verbindlich unterschreiben" bestätigen Sie, dass Sie den Vertrag gelesen haben und ihm zustimmen.
            </p>
          </div>
        </div>
      </template>

      <!-- Signed Confirmation -->
      <div v-else-if="state === 'signed'" class="max-w-lg mx-auto py-20">
        <div class="bg-white dark:bg-dark-800 rounded-2xl shadow-lg border border-gray-200 dark:border-dark-700 p-8 text-center">
          <div class="w-16 h-16 bg-green-100 dark:bg-green-900/30 rounded-full flex items-center justify-center mx-auto mb-4">
            <CheckCircleIcon class="w-9 h-9 text-green-600" />
          </div>
          <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-2">Vertrag unterschrieben</h2>
          <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
            Vielen Dank! Ihre Unterschrift wurde erfolgreich gespeichert.
          </p>
          <div v-if="contract" class="text-sm text-gray-600 dark:text-gray-300 bg-gray-50 dark:bg-dark-700 rounded-xl p-4 text-left space-y-1">
            <div><span class="font-medium">Vertrag:</span> {{ contract.title }}</div>
            <div><span class="font-medium">Nummer:</span> {{ contract.contract_number }}</div>
            <div v-if="contract.party_b_signed_at"><span class="font-medium">Unterschrieben am:</span> {{ formatDate(contract.party_b_signed_at) }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
