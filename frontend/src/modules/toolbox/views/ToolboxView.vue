<script setup>
import { ref, computed } from 'vue'
import { MagnifyingGlassIcon, XMarkIcon } from '@heroicons/vue/24/outline'

// Tool Components
import JsonToolkit from '../components/tools/JsonToolkit.vue'
import QrCodeGenerator from '../components/tools/QrCodeGenerator.vue'
import PasswordGenerator from '../components/tools/PasswordGenerator.vue'
import RegexTester from '../components/tools/RegexTester.vue'
import JwtDecoder from '../components/tools/JwtDecoder.vue'
import CronParser from '../components/tools/CronParser.vue'
import ColorPicker from '../components/tools/ColorPicker.vue'
import PomodoroTimer from '../components/tools/PomodoroTimer.vue'
import UnitConverter from '../components/tools/UnitConverter.vue'
import DiffViewer from '../components/tools/DiffViewer.vue'
import HashGenerator from '../components/tools/HashGenerator.vue'
import LoremIpsumGenerator from '../components/tools/LoremIpsumGenerator.vue'

// State
const searchQuery = ref('')
const activeTool = ref(null)

// Tool definitions
const toolCategories = [
  {
    id: 'dev',
    name: 'Entwickler Tools',
    icon: '🛠️',
    tools: [
      {
        id: 'json-toolkit',
        name: 'JSON Toolkit',
        description: 'JSON formatieren, validieren, minifizieren',
        icon: '📋',
        component: JsonToolkit,
      },
      {
        id: 'regex-tester',
        name: 'Regex Tester',
        description: 'Reguläre Ausdrücke testen und debuggen',
        icon: '🔍',
        component: RegexTester,
      },
      {
        id: 'jwt-decoder',
        name: 'JWT Decoder',
        description: 'JWT Tokens dekodieren und analysieren',
        icon: '🔐',
        component: JwtDecoder,
      },
      {
        id: 'cron-parser',
        name: 'Cron Parser',
        description: 'Cron-Ausdrücke erklären und testen',
        icon: '⏰',
        component: CronParser,
      },
      {
        id: 'diff-viewer',
        name: 'Diff Viewer',
        description: 'Texte vergleichen und Unterschiede anzeigen',
        icon: '📊',
        component: DiffViewer,
      },
      {
        id: 'hash-generator',
        name: 'Hash Generator',
        description: 'MD5, SHA1, SHA256 Hashes erstellen',
        icon: '🔒',
        component: HashGenerator,
      },
      {
        id: 'lorem-ipsum',
        name: 'Lorem Ipsum',
        description: 'Platzhaltertext generieren',
        icon: '📝',
        component: LoremIpsumGenerator,
      },
    ],
  },
  {
    id: 'media',
    name: 'Medien Tools',
    icon: '🎬',
    tools: [
      {
        id: 'youtube-downloader',
        name: 'YouTube Downloader',
        description: 'Videos und Audio von YouTube laden',
        icon: '📺',
        route: '/youtube-downloader',
      },
      {
        id: 'qr-code',
        name: 'QR Code Generator',
        description: 'QR Codes für URLs, Text, WiFi erstellen',
        icon: '📱',
        component: QrCodeGenerator,
      },
      {
        id: 'color-picker',
        name: 'Color Picker',
        description: 'Farben konvertieren (HEX, RGB, HSL)',
        icon: '🎨',
        component: ColorPicker,
      },
    ],
  },
  {
    id: 'productivity',
    name: 'Produktivität',
    icon: '⚡',
    tools: [
      {
        id: 'password-generator',
        name: 'Password Generator',
        description: 'Sichere Passwörter generieren',
        icon: '🔑',
        component: PasswordGenerator,
      },
      {
        id: 'pomodoro',
        name: 'Pomodoro Timer',
        description: 'Focus-Timer mit Intervallen',
        icon: '🍅',
        component: PomodoroTimer,
      },
      {
        id: 'unit-converter',
        name: 'Unit Converter',
        description: 'Einheiten umrechnen',
        icon: '📐',
        component: UnitConverter,
      },
    ],
  },
  {
    id: 'api',
    name: 'API & Netzwerk',
    icon: '🌐',
    tools: [
      {
        id: 'api-tester',
        name: 'API Tester',
        description: 'REST APIs testen und debuggen',
        icon: '🧪',
        route: '/api-tester',
      },
    ],
  },
]

// Computed
const filteredCategories = computed(() => {
  if (!searchQuery.value.trim()) {
    return toolCategories
  }

  const query = searchQuery.value.toLowerCase()
  return toolCategories
    .map(category => ({
      ...category,
      tools: category.tools.filter(
        tool =>
          tool.name.toLowerCase().includes(query) ||
          tool.description.toLowerCase().includes(query)
      ),
    }))
    .filter(category => category.tools.length > 0)
})

const totalTools = computed(() => {
  return toolCategories.reduce((sum, cat) => sum + cat.tools.length, 0)
})

// Methods
function openTool(tool) {
  if (tool.route) {
    // Navigate to route for complex tools
    window.location.href = tool.route
  } else {
    // Open in modal for simple tools
    activeTool.value = tool
  }
}

function closeTool() {
  activeTool.value = null
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-white flex items-center gap-2">
          <span>🧰</span> Toolbox
        </h1>
        <p class="text-gray-400 mt-1">{{ totalTools }} praktische Tools für den Alltag</p>
      </div>

      <!-- Search -->
      <div class="relative w-full sm:w-80">
        <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Tool suchen..."
          class="input w-full pl-10"
        />
      </div>
    </div>

    <!-- Categories -->
    <div class="space-y-8">
      <div v-for="category in filteredCategories" :key="category.id">
        <h2 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
          <span>{{ category.icon }}</span>
          {{ category.name }}
        </h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
          <button
            v-for="tool in category.tools"
            :key="tool.id"
            @click="openTool(tool)"
            class="card p-4 text-left hover:bg-dark-700 hover:border-primary-500 transition-all duration-200 group"
          >
            <div class="flex items-start gap-3">
              <span class="text-2xl">{{ tool.icon }}</span>
              <div class="flex-1 min-w-0">
                <h3 class="font-medium text-white group-hover:text-primary-400 transition-colors">
                  {{ tool.name }}
                </h3>
                <p class="text-sm text-gray-400 mt-1">
                  {{ tool.description }}
                </p>
              </div>
            </div>
          </button>
        </div>
      </div>

      <!-- No results -->
      <div v-if="filteredCategories.length === 0" class="text-center py-12">
        <p class="text-gray-400">Kein Tool gefunden für "{{ searchQuery }}"</p>
      </div>
    </div>

    <!-- Tool Modal -->
    <Teleport to="body">
      <div
        v-if="activeTool"
        class="fixed inset-0 bg-black/70 flex items-center justify-center z-50 p-4"
        @click.self="closeTool"
      >
        <div class="bg-dark-800 rounded-xl border border-dark-600 w-full max-w-5xl max-h-[90vh] flex flex-col">
          <!-- Modal Header -->
          <div class="flex items-center justify-between p-4 border-b border-dark-600">
            <h2 class="text-lg font-semibold text-white flex items-center gap-2">
              <span>{{ activeTool.icon }}</span>
              {{ activeTool.name }}
            </h2>
            <button @click="closeTool" class="btn-icon">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <!-- Modal Body -->
          <div class="flex-1 overflow-auto p-4">
            <component :is="activeTool.component" v-if="activeTool.component" />
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
