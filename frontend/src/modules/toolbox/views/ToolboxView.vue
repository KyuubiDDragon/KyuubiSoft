<script setup>
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { MagnifyingGlassIcon, XMarkIcon } from '@heroicons/vue/24/outline'

// Tool Components - Developer
import JsonToolkit from '../components/tools/JsonToolkit.vue'
import SwqlToolkit from '../components/tools/SwqlToolkit.vue'
import RegexTester from '../components/tools/RegexTester.vue'
import JwtDecoder from '../components/tools/JwtDecoder.vue'
import CronParser from '../components/tools/CronParser.vue'
import DiffViewer from '../components/tools/DiffViewer.vue'
import HashGenerator from '../components/tools/HashGenerator.vue'
import LoremIpsumGenerator from '../components/tools/LoremIpsumGenerator.vue'
import TimestampConverter from '../components/tools/TimestampConverter.vue'
import BaseConverter from '../components/tools/BaseConverter.vue'
import MarkdownPreview from '../components/tools/MarkdownPreview.vue'
import UuidGenerator from '../components/tools/UuidGenerator.vue'
import TextCaseConverter from '../components/tools/TextCaseConverter.vue'
import EncodingTool from '../components/tools/EncodingTool.vue'
import SqlFormatter from '../components/tools/SqlFormatter.vue'
import CsvJsonConverter from '../components/tools/CsvJsonConverter.vue'
import MockDataGenerator from '../components/tools/MockDataGenerator.vue'
import GitignoreGenerator from '../components/tools/GitignoreGenerator.vue'
import DockerfileGenerator from '../components/tools/DockerfileGenerator.vue'
import DockerComposeBuilder from '../components/tools/DockerComposeBuilder.vue'
import DockerCommandBuilder from '../components/tools/DockerCommandBuilder.vue'
import DockerignoreGenerator from '../components/tools/DockerignoreGenerator.vue'

// Tool Components - Media
import QrCodeGenerator from '../components/tools/QrCodeGenerator.vue'
import ColorPicker from '../components/tools/ColorPicker.vue'
import ImageCompressor from '../components/tools/ImageCompressor.vue'
import FaviconGenerator from '../components/tools/FaviconGenerator.vue'

// Tool Components - Productivity
import PasswordGenerator from '../components/tools/PasswordGenerator.vue'
import PomodoroTimer from '../components/tools/PomodoroTimer.vue'
import UnitConverter from '../components/tools/UnitConverter.vue'
import QuickNotes from '../components/tools/QuickNotes.vue'
import MeetingTimer from '../components/tools/MeetingTimer.vue'

// Tool Components - Network
import IpCalculator from '../components/tools/IpCalculator.vue'
import SslChecker from '../components/tools/SslChecker.vue'
import DnsLookup from '../components/tools/DnsLookup.vue'
import WhoisLookup from '../components/tools/WhoisLookup.vue'
import SecurityHeadersChecker from '../components/tools/SecurityHeadersChecker.vue'
import OpenGraphPreviewer from '../components/tools/OpenGraphPreviewer.vue'

// State
const searchQuery = ref('')
const activeTool = ref(null)

// Tool definitions
const toolCategories = [
  {
    id: 'solarwinds',
    name: 'SolarWinds Tools',
    icon: '☀️',
    tools: [
      {
        id: 'swql-toolkit',
        name: 'SWQL Toolkit',
        description: 'SWQL Query Builder, Templates, Schema Explorer, PowerShell Generator',
        icon: '🔍',
        component: SwqlToolkit,
        fullWidth: true,
      },
    ],
  },
  {
    id: 'dev',
    name: 'Entwickler Tools',
    icon: '🛠️',
    tools: [
      {
        id: 'json-toolkit',
        name: 'JSON Toolkit',
        description: 'JSON formatieren, validieren, minifizieren, Base64, UUID',
        icon: '📋',
        component: JsonToolkit,
      },
      {
        id: 'regex-tester',
        name: 'Regex Tester',
        description: t('toolbox.regulaereAusdrueckeTestenUndDebuggen'),
        icon: '🔍',
        component: RegexTester,
      },
      {
        id: 'jwt-decoder',
        name: 'JWT Tool',
        description: 'JWT Tokens dekodieren, analysieren und generieren',
        icon: '🔐',
        component: JwtDecoder,
      },
      {
        id: 'cron-parser',
        name: 'Cron Parser',
        description: t('toolbox.cronausdrueckeErklaerenUndTesten'),
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
        description: 'MD5, SHA1, SHA256, SHA512 Hashes erstellen',
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
      {
        id: 'timestamp-converter',
        name: 'Timestamp Converter',
        description: 'Unix/ISO/Datum Zeitstempel umrechnen',
        icon: '🕐',
        component: TimestampConverter,
      },
      {
        id: 'base-converter',
        name: 'Base Converter',
        description: t('toolbox.binaerOktalDezimalHexUmrechnen'),
        icon: '🔢',
        component: BaseConverter,
      },
      {
        id: 'markdown-preview',
        name: 'Markdown Preview',
        description: 'Markdown schreiben und live Vorschau',
        icon: '📄',
        component: MarkdownPreview,
      },
      {
        id: 'uuid-generator',
        name: 'UUID Generator',
        description: 'UUID v1/v4, ULID, NanoID generieren',
        icon: '🆔',
        component: UuidGenerator,
      },
      {
        id: 'text-case-converter',
        name: 'Text Case Converter',
        description: 'camelCase, snake_case, kebab-case umwandeln',
        icon: '🔤',
        component: TextCaseConverter,
      },
      {
        id: 'encoding-tool',
        name: 'Encoding Tool',
        description: 'Base64, URL, HTML, Hex kodieren/dekodieren',
        icon: '🔄',
        component: EncodingTool,
      },
      {
        id: 'sql-formatter',
        name: 'SQL Formatter',
        description: 'SQL-Abfragen formatieren und strukturieren',
        icon: '🗃️',
        component: SqlFormatter,
      },
      {
        id: 'csv-json-converter',
        name: 'CSV/JSON Converter',
        description: 'CSV und JSON bidirektional konvertieren',
        icon: '📊',
        component: CsvJsonConverter,
      },
      {
        id: 'mock-data-generator',
        name: 'Mock Data Generator',
        description: 'Testdaten generieren (Namen, E-Mails, etc.)',
        icon: '🎲',
        component: MockDataGenerator,
      },
      {
        id: 'gitignore-generator',
        name: '.gitignore Generator',
        description: t('toolbox.gitignoreDateienFuerVerschiedeneProjekteErstellen'),
        icon: '📁',
        component: GitignoreGenerator,
      },
    ],
  },
  {
    id: 'docker',
    name: 'Docker Tools',
    icon: '🐳',
    tools: [
      {
        id: 'docker-manager',
        name: 'Docker Manager',
        description: 'Container, Images, Volumes verwalten',
        icon: '📦',
        route: '/docker',
      },
      {
        id: 'dockerfile-generator',
        name: 'Dockerfile Generator',
        description: t('toolbox.dockerfilesFuerVerschiedeneSprachenErstellen'),
        icon: '📄',
        component: DockerfileGenerator,
      },
      {
        id: 'docker-compose-builder',
        name: 'Docker Compose Builder',
        description: 'docker-compose.yml visuell erstellen',
        icon: '🔧',
        component: DockerComposeBuilder,
      },
      {
        id: 'docker-command-builder',
        name: 'Docker Command Builder',
        description: 'docker run Befehle zusammenstellen',
        icon: '⚙️',
        component: DockerCommandBuilder,
      },
      {
        id: 'dockerignore-generator',
        name: '.dockerignore Generator',
        description: t('toolbox.dockerignoreDateienGenerieren'),
        icon: '🚫',
        component: DockerignoreGenerator,
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
        name: t('toolbox.youtubeDownloader'),
        description: t('toolbox.videosUndAudioVonYoutubeLaden'),
        icon: '📺',
        route: '/youtube-downloader',
      },
      {
        id: 'qr-code',
        name: 'QR Code Generator',
        description: t('toolbox.qrCodesFuerUrlsTextWifiErstellen'),
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
      {
        id: 'image-compressor',
        name: 'Image Compressor',
        description: 'Bilder komprimieren und konvertieren',
        icon: '🖼️',
        component: ImageCompressor,
      },
      {
        id: 'favicon-generator',
        name: 'Favicon Generator',
        description: t('toolbox.faviconsInAllenGroessenErstellen'),
        icon: '⭐',
        component: FaviconGenerator,
      },
    ],
  },
  {
    id: 'productivity',
    name: t('dashboard.productivity'),
    icon: '⚡',
    tools: [
      {
        id: 'password-generator',
        name: 'Password Generator',
        description: t('toolbox.sicherePasswoerterGenerieren'),
        icon: '🔑',
        component: PasswordGenerator,
      },
      {
        id: 'pomodoro',
        name: t('toolbox.pomodoroTimer'),
        description: t('toolbox.focustimerMitPausenintervallen'),
        icon: '🍅',
        component: PomodoroTimer,
      },
      {
        id: 'unit-converter',
        name: 'Unit Converter',
        description: t('toolbox.einheitenUmrechnenLaengeGewichtEtc'),
        icon: '📐',
        component: UnitConverter,
      },
      {
        id: 'quick-notes',
        name: 'Quick Notes',
        description: t('toolbox.schnelleNotizenMitAutosave'),
        icon: '📓',
        component: QuickNotes,
        fullWidth: true,
      },
      {
        id: 'meeting-timer',
        name: t('toolbox.meetingTimer'),
        description: t('toolbox.meetingtimerMitRedezeittracking'),
        icon: '👥',
        component: MeetingTimer,
      },
    ],
  },
  {
    id: 'network',
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
      {
        id: 'ip-calculator',
        name: 'IP/Subnet Calculator',
        description: 'IP-Adressen und Subnetze berechnen',
        icon: '🔌',
        component: IpCalculator,
      },
      {
        id: 'ssl-checker',
        name: 'SSL Checker',
        description: t('toolbox.sslzertifikatePruefen'),
        icon: '🛡️',
        component: SslChecker,
      },
      {
        id: 'dns-lookup',
        name: 'DNS Lookup',
        description: 'DNS Records abfragen',
        icon: '🔎',
        component: DnsLookup,
      },
      {
        id: 'whois-lookup',
        name: 'WHOIS Lookup',
        description: 'Domain-Informationen abfragen',
        icon: '📇',
        component: WhoisLookup,
      },
      {
        id: 'security-headers',
        name: 'Security Headers',
        description: t('toolbox.httpSecurityHeadersPruefenUndBewerten'),
        icon: '🔐',
        component: SecurityHeadersChecker,
      },
      {
        id: 'open-graph',
        name: 'Open Graph Previewer',
        description: 'Social Media Vorschau und Meta-Tags analysieren',
        icon: '🔗',
        component: OpenGraphPreviewer,
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
          <span class="text-sm font-normal text-gray-500">({{ category.tools.length }})</span>
        </h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
          <button
            v-for="tool in category.tools"
            :key="tool.id"
            @click="openTool(tool)"
            class="card p-4 text-left hover:bg-white/[0.04] hover:border-primary-500 transition-all duration-200 group"
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
              <span v-if="tool.route" class="text-xs text-gray-600">↗</span>
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
        class="fixed inset-0 bg-black/60 backdrop-blur-md flex items-center justify-center z-50 p-4"
        
      >
        <div
          class="modal max-h-[90vh] flex flex-col"
          :class="activeTool.fullWidth ? 'w-full max-w-6xl' : 'w-full max-w-4xl'"
        >
          <!-- Modal Header -->
          <div class="flex items-center justify-between p-4 border-b border-white/[0.06]">
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
