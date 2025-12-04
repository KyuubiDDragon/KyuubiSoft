<script setup>
import { ref, computed } from 'vue'

const selectedTemplates = ref([])
const customRules = ref('')
const searchQuery = ref('')

const templates = {
  // Languages & Frameworks
  node: {
    name: 'Node.js',
    category: 'Languages',
    rules: `# Dependencies
node_modules/
package-lock.json
yarn.lock
pnpm-lock.yaml

# Build output
dist/
build/
.next/
.nuxt/
.output/

# Environment
.env
.env.local
.env.*.local

# Logs
logs/
*.log
npm-debug.log*
yarn-debug.log*
yarn-error.log*

# Cache
.npm
.eslintcache
.cache/`
  },
  php: {
    name: 'PHP / Composer',
    category: 'Languages',
    rules: `# Dependencies
/vendor/
composer.lock

# Environment
.env
.env.local
.env.*.local

# Cache
/storage/framework/cache/*
/bootstrap/cache/*

# IDE
.idea/
.vscode/
*.sublime-*

# Logs
*.log
storage/logs/*`
  },
  python: {
    name: 'Python',
    category: 'Languages',
    rules: `# Byte-compiled
__pycache__/
*.py[cod]
*$py.class
*.so

# Virtual environments
venv/
.venv/
ENV/
env/

# Distribution
dist/
build/
*.egg-info/
*.egg

# Pip
pip-log.txt

# Testing
.pytest_cache/
.coverage
htmlcov/

# Jupyter
.ipynb_checkpoints/`
  },
  java: {
    name: 'Java / Maven / Gradle',
    category: 'Languages',
    rules: `# Compiled class files
*.class

# Build output
target/
build/
out/

# Package files
*.jar
*.war
*.ear

# Maven
pom.xml.tag
pom.xml.releaseBackup

# Gradle
.gradle/
gradle-app.setting
!gradle-wrapper.jar

# IDE
.idea/
*.iml
.classpath
.project
.settings/`
  },
  go: {
    name: 'Go',
    category: 'Languages',
    rules: `# Binaries
*.exe
*.exe~
*.dll
*.so
*.dylib

# Test binary
*.test

# Output
/bin/
/pkg/

# Go workspace
go.work

# Vendor (optional)
# vendor/

# IDE
.idea/
*.iml`
  },
  rust: {
    name: 'Rust',
    category: 'Languages',
    rules: `# Build output
/target/
debug/
release/

# Cargo lock (for libraries)
# Cargo.lock

# IDE
.idea/
*.iml

# Backup files
**/*.rs.bk`
  },
  // IDEs
  vscode: {
    name: 'VS Code',
    category: 'IDEs',
    rules: `.vscode/*
!.vscode/settings.json
!.vscode/tasks.json
!.vscode/launch.json
!.vscode/extensions.json
*.code-workspace
.history/`
  },
  intellij: {
    name: 'IntelliJ / JetBrains',
    category: 'IDEs',
    rules: `.idea/
*.iml
*.iws
*.ipr
out/
.idea_modules/
atlassian-ide-plugin.xml`
  },
  vim: {
    name: 'Vim',
    category: 'IDEs',
    rules: `# Swap files
[._]*.s[a-v][a-z]
[._]*.sw[a-p]
[._]s[a-rt-v][a-z]
[._]ss[a-gi-z]
[._]sw[a-p]

# Session
Session.vim
Sessionx.vim

# Temporary
.netrwhist
*~

# Tags
tags`
  },
  // Operating Systems
  macos: {
    name: 'macOS',
    category: 'OS',
    rules: `.DS_Store
.AppleDouble
.LSOverride
._*

# Thumbnails
Icon

# Spotlight
.Spotlight-V100
.Trashes

# Volumes
.VolumeIcon.icns
.com.apple.timemachine.donotpresent
.fseventsd`
  },
  windows: {
    name: 'Windows',
    category: 'OS',
    rules: `# Windows
Thumbs.db
Thumbs.db:encryptable
ehthumbs.db
ehthumbs_vista.db

# Desktop.ini
Desktop.ini

# Recycle Bin
$RECYCLE.BIN/

# Shortcuts
*.lnk`
  },
  linux: {
    name: 'Linux',
    category: 'OS',
    rules: `*~

# Temporary files
.fuse_hidden*
.directory
.Trash-*

# KDE
.nfs*`
  },
  // Tools
  docker: {
    name: 'Docker',
    category: 'Tools',
    rules: `# Docker
.docker/
docker-compose.override.yml
.dockerignore

# Volumes data
data/
volumes/`
  },
  terraform: {
    name: 'Terraform',
    category: 'Tools',
    rules: `# Terraform
.terraform/
*.tfstate
*.tfstate.*
crash.log
crash.*.log
*.tfvars
*.tfvars.json
override.tf
override.tf.json
*_override.tf
*_override.tf.json
.terraformrc
terraform.rc`
  },
  laravel: {
    name: 'Laravel',
    category: 'Frameworks',
    rules: `/vendor/
/node_modules/
/public/hot
/public/storage
/storage/*.key
.env
.env.backup
.env.production
.phpunit.result.cache
Homestead.json
Homestead.yaml
npm-debug.log
yarn-error.log`
  },
  vue: {
    name: 'Vue.js',
    category: 'Frameworks',
    rules: `node_modules/
dist/
.nuxt/
.output/
.cache/
*.local
.env.local
.env.*.local`
  },
  react: {
    name: 'React',
    category: 'Frameworks',
    rules: `node_modules/
build/
.next/
out/
.cache/
*.local
.env.local
.env.*.local`
  },
}

const categories = computed(() => {
  const cats = {}
  Object.entries(templates).forEach(([key, tmpl]) => {
    if (!cats[tmpl.category]) cats[tmpl.category] = []
    cats[tmpl.category].push({ key, ...tmpl })
  })
  return cats
})

const filteredCategories = computed(() => {
  if (!searchQuery.value.trim()) return categories.value

  const query = searchQuery.value.toLowerCase()
  const result = {}

  Object.entries(categories.value).forEach(([cat, items]) => {
    const filtered = items.filter(item =>
      item.name.toLowerCase().includes(query) ||
      cat.toLowerCase().includes(query)
    )
    if (filtered.length > 0) result[cat] = filtered
  })

  return result
})

const generatedContent = computed(() => {
  const parts = ['# Generated .gitignore', '# https://kyuubisoft.com/toolbox', '']

  selectedTemplates.value.forEach(key => {
    const tmpl = templates[key]
    if (tmpl) {
      parts.push(`# ==================== ${tmpl.name} ====================`)
      parts.push(tmpl.rules.trim())
      parts.push('')
    }
  })

  if (customRules.value.trim()) {
    parts.push('# ==================== Custom Rules ====================')
    parts.push(customRules.value.trim())
    parts.push('')
  }

  return parts.join('\n')
})

function toggleTemplate(key) {
  const idx = selectedTemplates.value.indexOf(key)
  if (idx === -1) {
    selectedTemplates.value.push(key)
  } else {
    selectedTemplates.value.splice(idx, 1)
  }
}

function selectAll(category) {
  const items = categories.value[category] || []
  items.forEach(item => {
    if (!selectedTemplates.value.includes(item.key)) {
      selectedTemplates.value.push(item.key)
    }
  })
}

function clearAll() {
  selectedTemplates.value = []
  customRules.value = ''
}

function copyToClipboard() {
  navigator.clipboard.writeText(generatedContent.value)
}

function downloadFile() {
  const blob = new Blob([generatedContent.value], { type: 'text/plain' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = '.gitignore'
  a.click()
  URL.revokeObjectURL(url)
}

// Presets
function applyPreset(preset) {
  clearAll()
  switch (preset) {
    case 'fullstack-js':
      selectedTemplates.value = ['node', 'vue', 'vscode', 'macos', 'windows', 'docker']
      break
    case 'php-laravel':
      selectedTemplates.value = ['php', 'laravel', 'node', 'vscode', 'macos', 'windows']
      break
    case 'python-ml':
      selectedTemplates.value = ['python', 'vscode', 'macos', 'windows', 'linux']
      break
    case 'java-spring':
      selectedTemplates.value = ['java', 'intellij', 'macos', 'windows', 'docker']
      break
  }
}
</script>

<template>
  <div class="space-y-4">
    <!-- Quick Presets -->
    <div>
      <label class="block text-xs text-gray-400 mb-2">Schnellauswahl</label>
      <div class="flex flex-wrap gap-2">
        <button @click="applyPreset('fullstack-js')" class="btn-sm bg-dark-700 hover:bg-dark-600">
          Fullstack JS
        </button>
        <button @click="applyPreset('php-laravel')" class="btn-sm bg-dark-700 hover:bg-dark-600">
          PHP/Laravel
        </button>
        <button @click="applyPreset('python-ml')" class="btn-sm bg-dark-700 hover:bg-dark-600">
          Python
        </button>
        <button @click="applyPreset('java-spring')" class="btn-sm bg-dark-700 hover:bg-dark-600">
          Java/Spring
        </button>
        <button @click="clearAll" class="btn-sm bg-red-900/30 hover:bg-red-900/50 text-red-400">
          Zurücksetzen
        </button>
      </div>
    </div>

    <!-- Search -->
    <div>
      <input
        v-model="searchQuery"
        type="text"
        class="input w-full"
        placeholder="Templates suchen..."
      />
    </div>

    <!-- Template Selection -->
    <div class="grid grid-cols-2 gap-4 max-h-64 overflow-y-auto">
      <div v-for="(items, category) in filteredCategories" :key="category">
        <div class="flex items-center justify-between mb-2">
          <h4 class="text-sm font-medium text-gray-300">{{ category }}</h4>
          <button @click="selectAll(category)" class="text-xs text-primary-400 hover:text-primary-300">
            Alle
          </button>
        </div>
        <div class="space-y-1">
          <label
            v-for="item in items"
            :key="item.key"
            class="flex items-center gap-2 p-2 bg-dark-700 rounded cursor-pointer hover:bg-dark-600"
            :class="{ 'ring-1 ring-primary-500': selectedTemplates.includes(item.key) }"
          >
            <input
              type="checkbox"
              :checked="selectedTemplates.includes(item.key)"
              @change="toggleTemplate(item.key)"
              class="rounded bg-dark-600 border-dark-500"
            />
            <span class="text-sm text-white">{{ item.name }}</span>
          </label>
        </div>
      </div>
    </div>

    <!-- Custom Rules -->
    <div>
      <label class="block text-xs text-gray-400 mb-1">Eigene Regeln</label>
      <textarea
        v-model="customRules"
        class="input w-full h-20 font-mono text-sm resize-none"
        placeholder="# Eigene Einträge hier..."
      ></textarea>
    </div>

    <!-- Preview -->
    <div>
      <div class="flex items-center justify-between mb-1">
        <label class="text-xs text-gray-400">
          Vorschau ({{ selectedTemplates.length }} Templates)
        </label>
        <div class="flex gap-2">
          <button @click="copyToClipboard" class="text-xs text-primary-400 hover:text-primary-300">
            Kopieren
          </button>
          <button @click="downloadFile" class="text-xs text-green-400 hover:text-green-300">
            Download .gitignore
          </button>
        </div>
      </div>
      <pre class="p-3 bg-dark-900 rounded-lg text-xs text-gray-300 font-mono max-h-48 overflow-auto">{{ generatedContent }}</pre>
    </div>
  </div>
</template>
