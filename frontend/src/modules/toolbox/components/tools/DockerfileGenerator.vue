<script setup>
import { ref, computed, watch } from 'vue'
import { ClipboardIcon, ArrowDownTrayIcon, CheckIcon } from '@heroicons/vue/24/outline'

// Templates for different languages/frameworks
const templates = {
  node: {
    name: 'Node.js',
    icon: '🟢',
    baseImage: 'node:20-alpine',
    workdir: '/app',
    packageManager: 'npm',
    buildCmd: 'npm run build',
    startCmd: 'npm start',
    port: 3000,
  },
  php: {
    name: 'PHP',
    icon: '🐘',
    baseImage: 'php:8.3-fpm-alpine',
    workdir: '/var/www/html',
    packageManager: 'composer',
    buildCmd: '',
    startCmd: 'php-fpm',
    port: 9000,
  },
  laravel: {
    name: 'Laravel',
    icon: '🔴',
    baseImage: 'php:8.3-fpm-alpine',
    workdir: '/var/www/html',
    packageManager: 'composer',
    buildCmd: 'php artisan config:cache && php artisan route:cache',
    startCmd: 'php-fpm',
    port: 9000,
  },
  python: {
    name: 'Python',
    icon: '🐍',
    baseImage: 'python:3.12-slim',
    workdir: '/app',
    packageManager: 'pip',
    buildCmd: '',
    startCmd: 'python main.py',
    port: 8000,
  },
  go: {
    name: 'Go',
    icon: '🔵',
    baseImage: 'golang:1.22-alpine',
    workdir: '/app',
    packageManager: 'go mod',
    buildCmd: 'go build -o main .',
    startCmd: './main',
    port: 8080,
  },
  rust: {
    name: 'Rust',
    icon: '🦀',
    baseImage: 'rust:1.75-alpine',
    workdir: '/app',
    packageManager: 'cargo',
    buildCmd: 'cargo build --release',
    startCmd: './target/release/app',
    port: 8080,
  },
  java: {
    name: 'Java (Maven)',
    icon: '☕',
    baseImage: 'eclipse-temurin:21-jdk-alpine',
    workdir: '/app',
    packageManager: 'maven',
    buildCmd: 'mvn clean package -DskipTests',
    startCmd: 'java -jar target/*.jar',
    port: 8080,
  },
  static: {
    name: 'Static Site (Nginx)',
    icon: '📄',
    baseImage: 'nginx:alpine',
    workdir: '/usr/share/nginx/html',
    packageManager: '',
    buildCmd: '',
    startCmd: 'nginx -g "daemon off;"',
    port: 80,
  },
}

// State
const selectedTemplate = ref('node')
const config = ref({
  baseImage: 'node:20-alpine',
  workdir: '/app',
  port: 3000,
  useMultistage: true,
  addHealthcheck: true,
  runAsNonRoot: true,
  copyFiles: '.',
  envVars: [],
  extraPackages: '',
  customInstructions: '',
})

const copied = ref(false)

// Watch template changes
watch(selectedTemplate, (newTemplate) => {
  const tpl = templates[newTemplate]
  if (tpl) {
    config.value.baseImage = tpl.baseImage
    config.value.workdir = tpl.workdir
    config.value.port = tpl.port
  }
})

// Generate Dockerfile
const dockerfile = computed(() => {
  const tpl = templates[selectedTemplate.value]
  const lines = []

  // Multi-stage build for compiled languages
  if (config.value.useMultistage && ['go', 'rust', 'java', 'node'].includes(selectedTemplate.value)) {
    lines.push(`# Build stage`)
    lines.push(`FROM ${config.value.baseImage} AS builder`)
    lines.push('')
    lines.push(`WORKDIR ${config.value.workdir}`)
    lines.push('')

    // Copy dependency files first (better caching)
    if (selectedTemplate.value === 'node') {
      lines.push('# Copy package files')
      lines.push('COPY package*.json ./')
      lines.push('RUN npm ci --only=production')
      lines.push('')
      lines.push('# Copy source and build')
      lines.push(`COPY ${config.value.copyFiles} .`)
      if (tpl.buildCmd) {
        lines.push(`RUN ${tpl.buildCmd}`)
      }
    } else if (selectedTemplate.value === 'go') {
      lines.push('# Copy go mod files')
      lines.push('COPY go.mod go.sum ./')
      lines.push('RUN go mod download')
      lines.push('')
      lines.push('# Copy source and build')
      lines.push(`COPY ${config.value.copyFiles} .`)
      lines.push(`RUN CGO_ENABLED=0 GOOS=linux ${tpl.buildCmd}`)
    } else if (selectedTemplate.value === 'rust') {
      lines.push('# Copy manifest')
      lines.push('COPY Cargo.toml Cargo.lock ./')
      lines.push('RUN mkdir src && echo "fn main() {}" > src/main.rs')
      lines.push('RUN cargo build --release')
      lines.push('RUN rm -rf src')
      lines.push('')
      lines.push('# Copy source and build')
      lines.push(`COPY ${config.value.copyFiles} .`)
      lines.push(`RUN ${tpl.buildCmd}`)
    } else if (selectedTemplate.value === 'java') {
      lines.push('# Copy pom.xml')
      lines.push('COPY pom.xml ./')
      lines.push('RUN mvn dependency:go-offline')
      lines.push('')
      lines.push('# Copy source and build')
      lines.push(`COPY ${config.value.copyFiles} .`)
      lines.push(`RUN ${tpl.buildCmd}`)
    }

    lines.push('')
    lines.push('# Production stage')

    // Runtime image
    if (selectedTemplate.value === 'go' || selectedTemplate.value === 'rust') {
      lines.push('FROM alpine:3.19 AS runtime')
      lines.push('RUN apk --no-cache add ca-certificates')
    } else if (selectedTemplate.value === 'java') {
      lines.push('FROM eclipse-temurin:21-jre-alpine AS runtime')
    } else {
      lines.push(`FROM ${config.value.baseImage.replace('-alpine', '-slim').replace('slim', 'alpine')} AS runtime`)
    }
  } else {
    lines.push(`FROM ${config.value.baseImage}`)
  }

  lines.push('')
  lines.push(`WORKDIR ${config.value.workdir}`)
  lines.push('')

  // Extra packages
  if (config.value.extraPackages) {
    if (config.value.baseImage.includes('alpine')) {
      lines.push(`RUN apk add --no-cache ${config.value.extraPackages}`)
    } else {
      lines.push(`RUN apt-get update && apt-get install -y ${config.value.extraPackages} && rm -rf /var/lib/apt/lists/*`)
    }
    lines.push('')
  }

  // Non-root user
  if (config.value.runAsNonRoot) {
    lines.push('# Create non-root user')
    if (config.value.baseImage.includes('alpine')) {
      lines.push('RUN addgroup -g 1001 appgroup && adduser -u 1001 -G appgroup -D appuser')
    } else {
      lines.push('RUN groupadd -g 1001 appgroup && useradd -u 1001 -g appgroup -m appuser')
    }
    lines.push('')
  }

  // Copy from builder or copy files
  if (config.value.useMultistage && ['go', 'rust', 'java', 'node'].includes(selectedTemplate.value)) {
    if (selectedTemplate.value === 'go') {
      lines.push('COPY --from=builder /app/main .')
    } else if (selectedTemplate.value === 'rust') {
      lines.push('COPY --from=builder /app/target/release/app .')
    } else if (selectedTemplate.value === 'java') {
      lines.push('COPY --from=builder /app/target/*.jar app.jar')
    } else if (selectedTemplate.value === 'node') {
      lines.push('COPY --from=builder /app/node_modules ./node_modules')
      lines.push('COPY --from=builder /app/dist ./dist')
      lines.push('COPY --from=builder /app/package.json .')
    }
  } else {
    // Non-multistage copy
    if (selectedTemplate.value === 'php' || selectedTemplate.value === 'laravel') {
      lines.push('# Install composer')
      lines.push('COPY --from=composer:latest /usr/bin/composer /usr/bin/composer')
      lines.push('')
      lines.push('# Copy composer files')
      lines.push('COPY composer.json composer.lock ./')
      lines.push('RUN composer install --no-dev --no-scripts --no-autoloader')
      lines.push('')
      lines.push('# Copy application')
      lines.push(`COPY ${config.value.copyFiles} .`)
      lines.push('RUN composer dump-autoload --optimize')
      if (tpl.buildCmd) {
        lines.push(`RUN ${tpl.buildCmd}`)
      }
    } else if (selectedTemplate.value === 'python') {
      lines.push('# Copy requirements')
      lines.push('COPY requirements.txt ./')
      lines.push('RUN pip install --no-cache-dir -r requirements.txt')
      lines.push('')
      lines.push('# Copy application')
      lines.push(`COPY ${config.value.copyFiles} .`)
    } else if (selectedTemplate.value === 'static') {
      lines.push('# Copy static files')
      lines.push(`COPY ${config.value.copyFiles} .`)
    } else {
      lines.push(`COPY ${config.value.copyFiles} .`)
    }
  }

  lines.push('')

  // Environment variables
  if (config.value.envVars.length > 0) {
    lines.push('# Environment variables')
    config.value.envVars.forEach(env => {
      if (env.key && env.value) {
        lines.push(`ENV ${env.key}="${env.value}"`)
      }
    })
    lines.push('')
  }

  // Change ownership if non-root
  if (config.value.runAsNonRoot) {
    lines.push(`RUN chown -R appuser:appgroup ${config.value.workdir}`)
    lines.push('USER appuser')
    lines.push('')
  }

  // Port
  lines.push(`EXPOSE ${config.value.port}`)
  lines.push('')

  // Healthcheck
  if (config.value.addHealthcheck) {
    lines.push('HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \\')
    if (selectedTemplate.value === 'php' || selectedTemplate.value === 'laravel') {
      lines.push(`  CMD php-fpm-healthcheck || exit 1`)
    } else {
      lines.push(`  CMD wget --no-verbose --tries=1 --spider http://localhost:${config.value.port}/health || exit 1`)
    }
    lines.push('')
  }

  // Custom instructions
  if (config.value.customInstructions) {
    lines.push('# Custom instructions')
    lines.push(config.value.customInstructions)
    lines.push('')
  }

  // Start command
  if (selectedTemplate.value === 'java' && config.value.useMultistage) {
    lines.push('CMD ["java", "-jar", "app.jar"]')
  } else if (tpl.startCmd) {
    const cmdParts = tpl.startCmd.split(' ')
    lines.push(`CMD [${cmdParts.map(p => `"${p}"`).join(', ')}]`)
  }

  return lines.join('\n')
})

// Methods
function copyToClipboard() {
  navigator.clipboard.writeText(dockerfile.value)
  copied.value = true
  setTimeout(() => { copied.value = false }, 2000)
}

function downloadDockerfile() {
  const blob = new Blob([dockerfile.value], { type: 'text/plain' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = 'Dockerfile'
  a.click()
  URL.revokeObjectURL(url)
}

function addEnvVar() {
  config.value.envVars.push({ key: '', value: '' })
}

function removeEnvVar(index) {
  config.value.envVars.splice(index, 1)
}
</script>

<template>
  <div class="space-y-4">
    <!-- Template Selection -->
    <div>
      <label class="block text-xs text-gray-400 mb-2">Sprache / Framework</label>
      <div class="grid grid-cols-4 gap-2">
        <button
          v-for="(tpl, key) in templates"
          :key="key"
          @click="selectedTemplate = key"
          :class="[
            'p-2 rounded-lg border text-center transition-all',
            selectedTemplate === key
              ? 'border-primary-500 bg-primary-500/10'
              : 'border-white/[0.06] hover:border-white/[0.08]'
          ]"
        >
          <span class="text-xl">{{ tpl.icon }}</span>
          <p class="text-xs mt-1">{{ tpl.name }}</p>
        </button>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <!-- Base Image -->
      <div>
        <label class="block text-xs text-gray-400 mb-1">Base Image</label>
        <input v-model="config.baseImage" class="input w-full" />
      </div>

      <!-- Working Directory -->
      <div>
        <label class="block text-xs text-gray-400 mb-1">Working Directory</label>
        <input v-model="config.workdir" class="input w-full" />
      </div>

      <!-- Port -->
      <div>
        <label class="block text-xs text-gray-400 mb-1">Port</label>
        <input v-model.number="config.port" type="number" class="input w-full" />
      </div>

      <!-- Extra Packages -->
      <div>
        <label class="block text-xs text-gray-400 mb-1">Extra Packages</label>
        <input v-model="config.extraPackages" class="input w-full" placeholder="curl git vim" />
      </div>
    </div>

    <!-- Options -->
    <div class="flex flex-wrap gap-4">
      <label class="flex items-center gap-2 text-sm">
        <input v-model="config.useMultistage" type="checkbox" class="rounded border-white/[0.08] bg-white/[0.04]" />
        <span>Multi-stage Build</span>
      </label>
      <label class="flex items-center gap-2 text-sm">
        <input v-model="config.addHealthcheck" type="checkbox" class="rounded border-white/[0.08] bg-white/[0.04]" />
        <span>Healthcheck</span>
      </label>
      <label class="flex items-center gap-2 text-sm">
        <input v-model="config.runAsNonRoot" type="checkbox" class="rounded border-white/[0.08] bg-white/[0.04]" />
        <span>Non-Root User</span>
      </label>
    </div>

    <!-- Environment Variables -->
    <div>
      <div class="flex items-center justify-between mb-2">
        <label class="text-xs text-gray-400">Umgebungsvariablen</label>
        <button @click="addEnvVar" class="text-xs text-primary-400 hover:text-primary-300">
          + Hinzufügen
        </button>
      </div>
      <div class="space-y-2">
        <div v-for="(env, i) in config.envVars" :key="i" class="flex gap-2">
          <input v-model="env.key" class="input flex-1" placeholder="KEY" />
          <input v-model="env.value" class="input flex-1" placeholder="value" />
          <button @click="removeEnvVar(i)" class="btn-icon text-red-400">
            &times;
          </button>
        </div>
      </div>
    </div>

    <!-- Custom Instructions -->
    <div>
      <label class="block text-xs text-gray-400 mb-1">Zusätzliche Instruktionen</label>
      <textarea
        v-model="config.customInstructions"
        class="input w-full h-16 font-mono text-sm"
        placeholder="RUN npm run test"
      ></textarea>
    </div>

    <!-- Generated Dockerfile -->
    <div>
      <div class="flex items-center justify-between mb-2">
        <label class="text-xs text-gray-400">Generiertes Dockerfile</label>
        <div class="flex gap-2">
          <button @click="copyToClipboard" class="btn-sm btn-secondary">
            <component :is="copied ? CheckIcon : ClipboardIcon" class="w-3 h-3" />
            {{ copied ? 'Kopiert!' : 'Kopieren' }}
          </button>
          <button @click="downloadDockerfile" class="btn-sm btn-primary">
            <ArrowDownTrayIcon class="w-3 h-3" />
            Download
          </button>
        </div>
      </div>
      <pre class="bg-white/[0.02] p-4 rounded-lg text-sm text-gray-300 font-mono overflow-auto max-h-80 whitespace-pre-wrap">{{ dockerfile }}</pre>
    </div>
  </div>
</template>
