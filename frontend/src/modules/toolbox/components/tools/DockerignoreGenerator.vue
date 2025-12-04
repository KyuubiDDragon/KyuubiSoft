<script setup>
import { ref, computed } from 'vue'
import { ClipboardIcon, ArrowDownTrayIcon, CheckIcon } from '@heroicons/vue/24/outline'

// Templates
const templates = {
  // Version Control
  git: {
    name: 'Git',
    category: 'vcs',
    patterns: ['.git', '.gitignore', '.gitattributes', '.github'],
  },
  svn: {
    name: 'SVN',
    category: 'vcs',
    patterns: ['.svn'],
  },

  // Node.js
  node: {
    name: 'Node.js',
    category: 'lang',
    patterns: ['node_modules', 'npm-debug.log*', 'yarn-debug.log*', 'yarn-error.log*', '.npm', '.yarn'],
  },

  // Python
  python: {
    name: 'Python',
    category: 'lang',
    patterns: ['__pycache__', '*.py[cod]', '*$py.class', '.Python', 'venv', '.venv', 'env', '.env', '*.egg-info', 'dist', 'build', '.pytest_cache', '.mypy_cache'],
  },

  // PHP
  php: {
    name: 'PHP',
    category: 'lang',
    patterns: ['vendor', 'composer.phar', '.phpunit.result.cache'],
  },

  // Go
  go: {
    name: 'Go',
    category: 'lang',
    patterns: ['*.exe', '*.exe~', '*.dll', '*.so', '*.dylib', '*.test', '*.out', 'vendor/'],
  },

  // Rust
  rust: {
    name: 'Rust',
    category: 'lang',
    patterns: ['target', 'Cargo.lock', '*.rs.bk'],
  },

  // Java
  java: {
    name: 'Java',
    category: 'lang',
    patterns: ['*.class', '*.jar', '*.war', 'target/', '.gradle', 'build/', '*.log'],
  },

  // IDEs
  vscode: {
    name: 'VS Code',
    category: 'ide',
    patterns: ['.vscode', '*.code-workspace'],
  },
  intellij: {
    name: 'IntelliJ/JetBrains',
    category: 'ide',
    patterns: ['.idea', '*.iml', '*.ipr', '*.iws'],
  },
  vim: {
    name: 'Vim',
    category: 'ide',
    patterns: ['*.swp', '*.swo', '*~', '.netrwhist'],
  },

  // OS
  macos: {
    name: 'macOS',
    category: 'os',
    patterns: ['.DS_Store', '.AppleDouble', '.LSOverride', '._*', '.Spotlight-V100', '.Trashes'],
  },
  windows: {
    name: 'Windows',
    category: 'os',
    patterns: ['Thumbs.db', 'ehthumbs.db', 'Desktop.ini', '$RECYCLE.BIN/'],
  },
  linux: {
    name: 'Linux',
    category: 'os',
    patterns: ['*~', '.fuse_hidden*', '.directory', '.Trash-*'],
  },

  // Docker
  docker: {
    name: 'Docker',
    category: 'docker',
    patterns: ['Dockerfile*', 'docker-compose*.yml', 'docker-compose*.yaml', '.docker'],
  },

  // CI/CD
  cicd: {
    name: 'CI/CD',
    category: 'cicd',
    patterns: ['.travis.yml', '.gitlab-ci.yml', 'Jenkinsfile', '.circleci', '.github/workflows'],
  },

  // Secrets & Configs
  secrets: {
    name: 'Secrets & Configs',
    category: 'security',
    patterns: ['.env', '.env.*', '*.pem', '*.key', '*.crt', 'credentials.json', 'secrets.yml', '*.secret'],
  },

  // Documentation
  docs: {
    name: 'Documentation',
    category: 'docs',
    patterns: ['README.md', 'CHANGELOG.md', 'LICENSE', 'docs/', '*.md'],
  },

  // Tests
  tests: {
    name: 'Tests',
    category: 'tests',
    patterns: ['test/', 'tests/', '__tests__/', 'spec/', '*.test.*', '*.spec.*', 'coverage/', '.nyc_output'],
  },

  // Logs & Temp
  logs: {
    name: 'Logs & Temp',
    category: 'temp',
    patterns: ['*.log', 'logs/', 'tmp/', 'temp/', '.cache', '*.tmp'],
  },
}

// Categories
const categories = [
  { id: 'vcs', name: 'Version Control' },
  { id: 'lang', name: 'Sprachen' },
  { id: 'ide', name: 'IDEs & Editoren' },
  { id: 'os', name: 'Betriebssysteme' },
  { id: 'docker', name: 'Docker' },
  { id: 'cicd', name: 'CI/CD' },
  { id: 'security', name: 'Sicherheit' },
  { id: 'docs', name: 'Dokumentation' },
  { id: 'tests', name: 'Tests' },
  { id: 'temp', name: 'Temp & Logs' },
]

// Presets
const presets = {
  nodeApp: {
    name: 'Node.js App',
    templates: ['git', 'node', 'vscode', 'macos', 'windows', 'secrets', 'tests', 'logs', 'docs'],
  },
  pythonApp: {
    name: 'Python App',
    templates: ['git', 'python', 'vscode', 'macos', 'windows', 'secrets', 'tests', 'logs', 'docs'],
  },
  phpApp: {
    name: 'PHP/Laravel App',
    templates: ['git', 'php', 'node', 'vscode', 'intellij', 'macos', 'windows', 'secrets', 'tests', 'logs', 'docs'],
  },
  goApp: {
    name: 'Go App',
    templates: ['git', 'go', 'vscode', 'macos', 'windows', 'secrets', 'tests', 'logs'],
  },
  javaApp: {
    name: 'Java App',
    templates: ['git', 'java', 'intellij', 'macos', 'windows', 'secrets', 'tests', 'logs'],
  },
  minimal: {
    name: 'Minimal',
    templates: ['git', 'macos', 'windows', 'secrets', 'logs'],
  },
}

// State
const selectedTemplates = ref(['git', 'macos', 'windows', 'secrets', 'logs'])
const customPatterns = ref('')
const copied = ref(false)

// Methods
function toggleTemplate(templateKey) {
  const index = selectedTemplates.value.indexOf(templateKey)
  if (index === -1) {
    selectedTemplates.value.push(templateKey)
  } else {
    selectedTemplates.value.splice(index, 1)
  }
}

function applyPreset(presetKey) {
  selectedTemplates.value = [...presets[presetKey].templates]
}

function getTemplatesByCategory(categoryId) {
  return Object.entries(templates)
    .filter(([_, tpl]) => tpl.category === categoryId)
    .map(([key, tpl]) => ({ key, ...tpl }))
}

// Generate .dockerignore content
const dockerignoreContent = computed(() => {
  const sections = []

  // Group selected templates by category
  const groupedTemplates = {}
  selectedTemplates.value.forEach(key => {
    const tpl = templates[key]
    if (tpl) {
      if (!groupedTemplates[tpl.category]) {
        groupedTemplates[tpl.category] = []
      }
      groupedTemplates[tpl.category].push({ key, ...tpl })
    }
  })

  // Generate content by category
  categories.forEach(cat => {
    if (groupedTemplates[cat.id]) {
      sections.push(`# ${cat.name}`)
      groupedTemplates[cat.id].forEach(tpl => {
        tpl.patterns.forEach(pattern => {
          sections.push(pattern)
        })
      })
      sections.push('')
    }
  })

  // Add custom patterns
  if (customPatterns.value.trim()) {
    sections.push('# Custom')
    sections.push(customPatterns.value.trim())
    sections.push('')
  }

  return sections.join('\n').trim()
})

// Copy to clipboard
function copyToClipboard() {
  navigator.clipboard.writeText(dockerignoreContent.value)
  copied.value = true
  setTimeout(() => { copied.value = false }, 2000)
}

// Download file
function downloadFile() {
  const blob = new Blob([dockerignoreContent.value], { type: 'text/plain' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = '.dockerignore'
  a.click()
  URL.revokeObjectURL(url)
}
</script>

<template>
  <div class="space-y-4">
    <!-- Quick Presets -->
    <div>
      <label class="block text-xs text-gray-400 mb-2">Quick Presets</label>
      <div class="flex flex-wrap gap-2">
        <button
          v-for="(preset, key) in presets"
          :key="key"
          @click="applyPreset(key)"
          class="px-3 py-1 bg-dark-700 hover:bg-dark-600 rounded text-sm transition-colors"
        >
          {{ preset.name }}
        </button>
      </div>
    </div>

    <!-- Template Selection by Category -->
    <div class="space-y-3">
      <div v-for="category in categories" :key="category.id">
        <label class="block text-xs text-gray-400 mb-2">{{ category.name }}</label>
        <div class="flex flex-wrap gap-2">
          <button
            v-for="tpl in getTemplatesByCategory(category.id)"
            :key="tpl.key"
            @click="toggleTemplate(tpl.key)"
            :class="[
              'px-3 py-1 rounded text-sm transition-colors border',
              selectedTemplates.includes(tpl.key)
                ? 'bg-primary-500/20 border-primary-500 text-primary-400'
                : 'bg-dark-700 border-dark-600 hover:border-dark-500'
            ]"
          >
            {{ tpl.name }}
          </button>
        </div>
      </div>
    </div>

    <!-- Custom Patterns -->
    <div>
      <label class="block text-xs text-gray-400 mb-1">Eigene Patterns (eine pro Zeile)</label>
      <textarea
        v-model="customPatterns"
        class="input w-full h-20 font-mono text-sm"
        placeholder="my-folder/&#10;*.custom&#10;!important.txt"
      ></textarea>
    </div>

    <!-- Generated Output -->
    <div>
      <div class="flex items-center justify-between mb-2">
        <label class="text-xs text-gray-400">.dockerignore</label>
        <div class="flex gap-2">
          <button @click="copyToClipboard" class="btn-sm btn-secondary">
            <component :is="copied ? CheckIcon : ClipboardIcon" class="w-3 h-3" />
            {{ copied ? 'Kopiert!' : 'Kopieren' }}
          </button>
          <button @click="downloadFile" class="btn-sm btn-primary">
            <ArrowDownTrayIcon class="w-3 h-3" />
            Download
          </button>
        </div>
      </div>
      <pre class="bg-dark-900 p-4 rounded-lg text-sm text-gray-300 font-mono overflow-auto max-h-64">{{ dockerignoreContent || '# Wähle Templates aus...' }}</pre>
    </div>
  </div>
</template>
