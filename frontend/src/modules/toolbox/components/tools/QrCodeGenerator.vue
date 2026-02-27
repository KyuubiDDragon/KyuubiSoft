<script setup>
import { ref, watch, onMounted } from 'vue'
import { ArrowDownTrayIcon } from '@heroicons/vue/24/outline'

const activeTab = ref('text')
const qrCanvas = ref(null)
const qrDataUrl = ref('')

// Form data
const textInput = ref('https://example.com')
const wifiSsid = ref('')
const wifiPassword = ref('')
const wifiEncryption = ref('WPA')
const vcardName = ref('')
const vcardPhone = ref('')
const vcardEmail = ref('')

const tabs = [
  { id: 'text', name: 'Text/URL' },
  { id: 'wifi', name: 'WiFi' },
  { id: 'vcard', name: 'Kontakt' },
]

// Simple QR Code generation using canvas
// Using a basic QR code algorithm implementation
function generateQR() {
  let data = ''

  switch (activeTab.value) {
    case 'text':
      data = textInput.value
      break
    case 'wifi':
      data = `WIFI:T:${wifiEncryption.value};S:${wifiSsid.value};P:${wifiPassword.value};;`
      break
    case 'vcard':
      data = `BEGIN:VCARD\nVERSION:3.0\nFN:${vcardName.value}\nTEL:${vcardPhone.value}\nEMAIL:${vcardEmail.value}\nEND:VCARD`
      break
  }

  if (!data) {
    qrDataUrl.value = ''
    return
  }

  // Use a simple API for QR code generation (fallback)
  // In production, you'd use a library like qrcode.js
  const size = 200
  qrDataUrl.value = `https://api.qrserver.com/v1/create-qr-code/?size=${size}x${size}&data=${encodeURIComponent(data)}`
}

function downloadQR() {
  if (!qrDataUrl.value) return

  const link = document.createElement('a')
  link.href = qrDataUrl.value
  link.download = 'qrcode.png'
  link.click()
}

watch([activeTab, textInput, wifiSsid, wifiPassword, wifiEncryption, vcardName, vcardPhone, vcardEmail], () => {
  generateQR()
})

onMounted(() => {
  generateQR()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Tabs -->
    <div class="flex gap-2 border-b border-white/[0.06] pb-2">
      <button
        v-for="tab in tabs"
        :key="tab.id"
        @click="activeTab = tab.id"
        class="px-4 py-2 rounded-lg text-sm font-medium transition-colors"
        :class="activeTab === tab.id ? 'bg-primary-600 text-white' : 'text-gray-400 hover:text-white hover:bg-white/[0.04]'"
      >
        {{ tab.name }}
      </button>
    </div>

    <div class="grid md:grid-cols-2 gap-6">
      <!-- Input Forms -->
      <div class="space-y-4">
        <!-- Text/URL -->
        <div v-if="activeTab === 'text'">
          <label class="text-sm text-gray-400 mb-1 block">Text oder URL</label>
          <textarea
            v-model="textInput"
            class="input w-full h-32"
            placeholder="https://example.com oder beliebiger Text"
          ></textarea>
        </div>

        <!-- WiFi -->
        <div v-if="activeTab === 'wifi'" class="space-y-3">
          <div>
            <label class="text-sm text-gray-400 mb-1 block">Netzwerkname (SSID)</label>
            <input v-model="wifiSsid" type="text" class="input w-full" placeholder="MeinWLAN" />
          </div>
          <div>
            <label class="text-sm text-gray-400 mb-1 block">Passwort</label>
            <input v-model="wifiPassword" type="text" class="input w-full" placeholder="Passwort" />
          </div>
          <div>
            <label class="text-sm text-gray-400 mb-1 block">Verschlüsselung</label>
            <select v-model="wifiEncryption" class="input w-full">
              <option value="WPA">WPA/WPA2</option>
              <option value="WEP">WEP</option>
              <option value="nopass">Keine</option>
            </select>
          </div>
        </div>

        <!-- vCard -->
        <div v-if="activeTab === 'vcard'" class="space-y-3">
          <div>
            <label class="text-sm text-gray-400 mb-1 block">Name</label>
            <input v-model="vcardName" type="text" class="input w-full" placeholder="Max Mustermann" />
          </div>
          <div>
            <label class="text-sm text-gray-400 mb-1 block">Telefon</label>
            <input v-model="vcardPhone" type="tel" class="input w-full" placeholder="+49 123 456789" />
          </div>
          <div>
            <label class="text-sm text-gray-400 mb-1 block">E-Mail</label>
            <input v-model="vcardEmail" type="email" class="input w-full" placeholder="max@example.com" />
          </div>
        </div>
      </div>

      <!-- QR Code Preview -->
      <div class="flex flex-col items-center justify-center">
        <div class="bg-white p-4 rounded-lg">
          <img
            v-if="qrDataUrl"
            :src="qrDataUrl"
            alt="QR Code"
            class="w-48 h-48"
          />
          <div v-else class="w-48 h-48 flex items-center justify-center text-gray-400">
            Daten eingeben...
          </div>
        </div>

        <button
          v-if="qrDataUrl"
          @click="downloadQR"
          class="btn-primary mt-4"
        >
          <ArrowDownTrayIcon class="w-5 h-5 mr-2" />
          Download PNG
        </button>
      </div>
    </div>
  </div>
</template>
