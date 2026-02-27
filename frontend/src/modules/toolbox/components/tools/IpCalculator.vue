<script setup>
import { ref, computed } from 'vue'

const ipInput = ref('192.168.1.1')
const cidrInput = ref(24)

// Validate IP address
function isValidIp(ip) {
  const parts = ip.split('.')
  if (parts.length !== 4) return false
  return parts.every(part => {
    const num = parseInt(part)
    return !isNaN(num) && num >= 0 && num <= 255 && String(num) === part.trim()
  })
}

// Convert IP to 32-bit integer
function ipToInt(ip) {
  const parts = ip.split('.').map(Number)
  return (parts[0] << 24) + (parts[1] << 16) + (parts[2] << 8) + parts[3]
}

// Convert 32-bit integer to IP
function intToIp(int) {
  return [
    (int >>> 24) & 255,
    (int >>> 16) & 255,
    (int >>> 8) & 255,
    int & 255,
  ].join('.')
}

// Convert IP to binary string
function ipToBinary(ip) {
  return ip.split('.').map(octet =>
    parseInt(octet).toString(2).padStart(8, '0')
  ).join('.')
}

// Calculate subnet mask from CIDR
function cidrToMask(cidr) {
  const mask = cidr === 0 ? 0 : (~0 << (32 - cidr)) >>> 0
  return intToIp(mask)
}

// Calculate wildcard mask
function cidrToWildcard(cidr) {
  const mask = cidr === 32 ? 0 : ~((~0 << (32 - cidr)) >>> 0) >>> 0
  return intToIp(mask)
}

const calculations = computed(() => {
  if (!isValidIp(ipInput.value) || cidrInput.value < 0 || cidrInput.value > 32) {
    return null
  }

  const ip = ipInput.value.trim()
  const cidr = parseInt(cidrInput.value)

  const ipInt = ipToInt(ip)
  const maskInt = cidr === 0 ? 0 : (~0 << (32 - cidr)) >>> 0
  const wildcardInt = ~maskInt >>> 0

  const networkInt = (ipInt & maskInt) >>> 0
  const broadcastInt = (networkInt | wildcardInt) >>> 0
  const firstHostInt = networkInt + 1
  const lastHostInt = broadcastInt - 1

  const totalHosts = Math.pow(2, 32 - cidr)
  const usableHosts = cidr >= 31 ? (cidr === 32 ? 1 : 2) : totalHosts - 2

  return {
    ip: ip,
    cidr: cidr,
    subnetMask: cidrToMask(cidr),
    wildcardMask: cidrToWildcard(cidr),
    networkAddress: intToIp(networkInt),
    broadcastAddress: intToIp(broadcastInt),
    firstHost: cidr >= 31 ? intToIp(networkInt) : intToIp(firstHostInt),
    lastHost: cidr >= 31 ? intToIp(broadcastInt) : intToIp(lastHostInt),
    totalHosts: totalHosts.toLocaleString(),
    usableHosts: usableHosts.toLocaleString(),
    ipClass: getIpClass(ip),
    ipType: getIpType(ip),
    binaryIp: ipToBinary(ip),
    binaryMask: ipToBinary(cidrToMask(cidr)),
  }
})

function getIpClass(ip) {
  const firstOctet = parseInt(ip.split('.')[0])
  if (firstOctet < 128) return 'A'
  if (firstOctet < 192) return 'B'
  if (firstOctet < 224) return 'C'
  if (firstOctet < 240) return 'D (Multicast)'
  return 'E (Reserviert)'
}

function getIpType(ip) {
  const parts = ip.split('.').map(Number)

  // Loopback
  if (parts[0] === 127) return 'Loopback'

  // Private ranges
  if (parts[0] === 10) return 'Privat (Klasse A)'
  if (parts[0] === 172 && parts[1] >= 16 && parts[1] <= 31) return 'Privat (Klasse B)'
  if (parts[0] === 192 && parts[1] === 168) return 'Privat (Klasse C)'

  // Link-local
  if (parts[0] === 169 && parts[1] === 254) return 'Link-Local'

  return 'Öffentlich'
}

// Common subnets
const commonSubnets = [
  { cidr: 8, name: '/8 (Klasse A)', hosts: '16.777.214' },
  { cidr: 16, name: '/16 (Klasse B)', hosts: '65.534' },
  { cidr: 24, name: '/24 (Klasse C)', hosts: '254' },
  { cidr: 25, name: '/25', hosts: '126' },
  { cidr: 26, name: '/26', hosts: '62' },
  { cidr: 27, name: '/27', hosts: '30' },
  { cidr: 28, name: '/28', hosts: '14' },
  { cidr: 29, name: '/29', hosts: '6' },
  { cidr: 30, name: '/30', hosts: '2' },
  { cidr: 32, name: '/32 (Host)', hosts: '1' },
]

function copyToClipboard(text) {
  navigator.clipboard.writeText(text)
}
</script>

<template>
  <div class="space-y-4">
    <!-- Input -->
    <div class="grid grid-cols-3 gap-4">
      <div class="col-span-2">
        <label class="text-sm text-gray-400 mb-1 block">IP-Adresse</label>
        <input
          v-model="ipInput"
          type="text"
          class="input w-full font-mono"
          placeholder="192.168.1.1"
          :class="{ 'border-red-500': ipInput && !isValidIp(ipInput) }"
        />
      </div>
      <div>
        <label class="text-sm text-gray-400 mb-1 block">CIDR / Prefix</label>
        <input
          v-model.number="cidrInput"
          type="number"
          min="0"
          max="32"
          class="input w-full font-mono"
        />
      </div>
    </div>

    <!-- Validation Error -->
    <div v-if="ipInput && !isValidIp(ipInput)" class="text-red-400 text-sm">
      Ungültige IP-Adresse
    </div>

    <!-- Results -->
    <div v-if="calculations" class="space-y-3">
      <!-- Main Info -->
      <div class="grid grid-cols-2 gap-3">
        <div class="p-3 bg-white/[0.04] rounded-lg">
          <span class="text-xs text-gray-500">Netzwerk-Adresse</span>
          <div class="font-mono text-white">{{ calculations.networkAddress }}/{{ calculations.cidr }}</div>
        </div>
        <div class="p-3 bg-white/[0.04] rounded-lg">
          <span class="text-xs text-gray-500">Broadcast-Adresse</span>
          <div class="font-mono text-white">{{ calculations.broadcastAddress }}</div>
        </div>
        <div class="p-3 bg-white/[0.04] rounded-lg">
          <span class="text-xs text-gray-500">Erster Host</span>
          <div class="font-mono text-white">{{ calculations.firstHost }}</div>
        </div>
        <div class="p-3 bg-white/[0.04] rounded-lg">
          <span class="text-xs text-gray-500">Letzter Host</span>
          <div class="font-mono text-white">{{ calculations.lastHost }}</div>
        </div>
        <div class="p-3 bg-white/[0.04] rounded-lg">
          <span class="text-xs text-gray-500">Subnetz-Maske</span>
          <div class="font-mono text-white">{{ calculations.subnetMask }}</div>
        </div>
        <div class="p-3 bg-white/[0.04] rounded-lg">
          <span class="text-xs text-gray-500">Wildcard-Maske</span>
          <div class="font-mono text-white">{{ calculations.wildcardMask }}</div>
        </div>
        <div class="p-3 bg-white/[0.04] rounded-lg">
          <span class="text-xs text-gray-500">Nutzbare Hosts</span>
          <div class="font-mono text-white">{{ calculations.usableHosts }}</div>
        </div>
        <div class="p-3 bg-white/[0.04] rounded-lg">
          <span class="text-xs text-gray-500">Gesamt Adressen</span>
          <div class="font-mono text-white">{{ calculations.totalHosts }}</div>
        </div>
      </div>

      <!-- Additional Info -->
      <div class="p-3 bg-white/[0.04] rounded-lg space-y-2">
        <div class="flex justify-between">
          <span class="text-sm text-gray-400">IP-Klasse:</span>
          <span class="text-white">{{ calculations.ipClass }}</span>
        </div>
        <div class="flex justify-between">
          <span class="text-sm text-gray-400">IP-Typ:</span>
          <span class="text-white">{{ calculations.ipType }}</span>
        </div>
      </div>

      <!-- Binary Representation -->
      <div class="p-3 bg-white/[0.04] rounded-lg space-y-2">
        <div>
          <span class="text-xs text-gray-500">IP (Binär)</span>
          <div class="font-mono text-xs text-white break-all">{{ calculations.binaryIp }}</div>
        </div>
        <div>
          <span class="text-xs text-gray-500">Maske (Binär)</span>
          <div class="font-mono text-xs text-white break-all">{{ calculations.binaryMask }}</div>
        </div>
      </div>
    </div>

    <!-- Common Subnets Reference -->
    <div>
      <h4 class="text-sm text-gray-400 mb-2">Häufige Subnetze</h4>
      <div class="grid grid-cols-2 gap-2">
        <button
          v-for="subnet in commonSubnets"
          :key="subnet.cidr"
          @click="cidrInput = subnet.cidr"
          class="p-2 text-left bg-white/[0.04] hover:bg-white/[0.04] rounded text-sm transition-colors"
          :class="cidrInput === subnet.cidr ? 'ring-1 ring-primary-500' : ''"
        >
          <div class="text-white">{{ subnet.name }}</div>
          <div class="text-xs text-gray-500">{{ subnet.hosts }} Hosts</div>
        </button>
      </div>
    </div>
  </div>
</template>
