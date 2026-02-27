<script setup>
import { ref, computed } from 'vue'
import { ClipboardIcon, CheckIcon, PlayIcon } from '@heroicons/vue/24/outline'

// State
const command = ref('run')
const config = ref({
  // Basic
  image: 'nginx:alpine',
  name: '',
  // Runtime
  detached: true,
  interactive: false,
  tty: false,
  rm: false,
  // Ports
  ports: [],
  // Volumes
  volumes: [],
  // Environment
  envVars: [],
  envFile: '',
  // Network
  network: '',
  hostname: '',
  // Resources
  memory: '',
  cpus: '',
  // User
  user: '',
  workdir: '',
  // Restart
  restart: '',
  // Labels
  labels: [],
  // Entrypoint/Command
  entrypoint: '',
  cmd: '',
  // Other
  privileged: false,
  readonly: false,
  init: false,
})

const copied = ref(false)

// Common images
const commonImages = [
  'nginx:alpine',
  'node:20-alpine',
  'python:3.12-slim',
  'php:8.3-fpm-alpine',
  'mysql:8',
  'postgres:16-alpine',
  'redis:alpine',
  'mongo:7',
  'ubuntu:22.04',
  'alpine:3.19',
]

// Generate command
const generatedCommand = computed(() => {
  if (command.value === 'run') {
    return generateRunCommand()
  } else if (command.value === 'exec') {
    return generateExecCommand()
  } else if (command.value === 'build') {
    return generateBuildCommand()
  }
  return ''
})

function generateRunCommand() {
  const parts = ['docker run']

  // Detached
  if (config.value.detached) {
    parts.push('-d')
  }

  // Interactive & TTY
  if (config.value.interactive) {
    parts.push('-i')
  }
  if (config.value.tty) {
    parts.push('-t')
  }

  // Remove after exit
  if (config.value.rm) {
    parts.push('--rm')
  }

  // Name
  if (config.value.name) {
    parts.push(`--name ${config.value.name}`)
  }

  // Ports
  config.value.ports.forEach(port => {
    if (port.host && port.container) {
      parts.push(`-p ${port.host}:${port.container}`)
    }
  })

  // Volumes
  config.value.volumes.forEach(vol => {
    if (vol.host && vol.container) {
      let volStr = `${vol.host}:${vol.container}`
      if (vol.mode) {
        volStr += `:${vol.mode}`
      }
      parts.push(`-v ${volStr}`)
    }
  })

  // Environment variables
  config.value.envVars.forEach(env => {
    if (env.key) {
      if (env.value) {
        parts.push(`-e ${env.key}="${env.value}"`)
      } else {
        parts.push(`-e ${env.key}`)
      }
    }
  })

  // Environment file
  if (config.value.envFile) {
    parts.push(`--env-file ${config.value.envFile}`)
  }

  // Network
  if (config.value.network) {
    parts.push(`--network ${config.value.network}`)
  }

  // Hostname
  if (config.value.hostname) {
    parts.push(`--hostname ${config.value.hostname}`)
  }

  // Resources
  if (config.value.memory) {
    parts.push(`--memory ${config.value.memory}`)
  }
  if (config.value.cpus) {
    parts.push(`--cpus ${config.value.cpus}`)
  }

  // User
  if (config.value.user) {
    parts.push(`--user ${config.value.user}`)
  }

  // Working directory
  if (config.value.workdir) {
    parts.push(`--workdir ${config.value.workdir}`)
  }

  // Restart policy
  if (config.value.restart) {
    parts.push(`--restart ${config.value.restart}`)
  }

  // Labels
  config.value.labels.forEach(label => {
    if (label.key) {
      parts.push(`--label ${label.key}="${label.value || ''}"`)
    }
  })

  // Privileged
  if (config.value.privileged) {
    parts.push('--privileged')
  }

  // Read-only filesystem
  if (config.value.readonly) {
    parts.push('--read-only')
  }

  // Init
  if (config.value.init) {
    parts.push('--init')
  }

  // Entrypoint
  if (config.value.entrypoint) {
    parts.push(`--entrypoint ${config.value.entrypoint}`)
  }

  // Image
  parts.push(config.value.image || 'IMAGE')

  // Command
  if (config.value.cmd) {
    parts.push(config.value.cmd)
  }

  return parts.join(' \\\n  ')
}

function generateExecCommand() {
  const parts = ['docker exec']

  if (config.value.interactive) {
    parts.push('-i')
  }
  if (config.value.tty) {
    parts.push('-t')
  }
  if (config.value.user) {
    parts.push(`--user ${config.value.user}`)
  }
  if (config.value.workdir) {
    parts.push(`--workdir ${config.value.workdir}`)
  }

  config.value.envVars.forEach(env => {
    if (env.key && env.value) {
      parts.push(`-e ${env.key}="${env.value}"`)
    }
  })

  parts.push(config.value.name || 'CONTAINER')
  parts.push(config.value.cmd || '/bin/sh')

  return parts.join(' \\\n  ')
}

function generateBuildCommand() {
  const parts = ['docker build']

  if (config.value.name) {
    parts.push(`-t ${config.value.name}`)
  }

  config.value.labels.forEach(label => {
    if (label.key) {
      parts.push(`--label ${label.key}="${label.value || ''}"`)
    }
  })

  // Build args as env vars
  config.value.envVars.forEach(env => {
    if (env.key && env.value) {
      parts.push(`--build-arg ${env.key}="${env.value}"`)
    }
  })

  if (config.value.readonly) {
    parts.push('--no-cache')
  }

  parts.push('.')

  return parts.join(' \\\n  ')
}

// Helpers
function addPort() {
  config.value.ports.push({ host: '', container: '' })
}

function removePort(index) {
  config.value.ports.splice(index, 1)
}

function addVolume() {
  config.value.volumes.push({ host: '', container: '', mode: '' })
}

function removeVolume(index) {
  config.value.volumes.splice(index, 1)
}

function addEnvVar() {
  config.value.envVars.push({ key: '', value: '' })
}

function removeEnvVar(index) {
  config.value.envVars.splice(index, 1)
}

function addLabel() {
  config.value.labels.push({ key: '', value: '' })
}

function removeLabel(index) {
  config.value.labels.splice(index, 1)
}

function copyToClipboard() {
  navigator.clipboard.writeText(generatedCommand.value.replace(/\\\n\s+/g, ' '))
  copied.value = true
  setTimeout(() => { copied.value = false }, 2000)
}
</script>

<template>
  <div class="space-y-4">
    <!-- Command Type -->
    <div>
      <label class="block text-xs text-gray-400 mb-2">Befehl</label>
      <div class="flex gap-2">
        <button
          v-for="cmd in ['run', 'exec', 'build']"
          :key="cmd"
          @click="command = cmd"
          :class="[
            'px-4 py-2 rounded-lg transition-colors',
            command === cmd
              ? 'bg-primary-500 text-white'
              : 'bg-white/[0.04] text-gray-300 hover:bg-white/[0.04]'
          ]"
        >
          docker {{ cmd }}
        </button>
      </div>
    </div>

    <!-- Run Command Options -->
    <template v-if="command === 'run'">
      <div class="grid grid-cols-2 gap-4">
        <!-- Image -->
        <div>
          <label class="block text-xs text-gray-400 mb-1">Image</label>
          <input v-model="config.image" list="common-images" class="input w-full" placeholder="nginx:alpine" />
          <datalist id="common-images">
            <option v-for="img in commonImages" :key="img" :value="img" />
          </datalist>
        </div>

        <!-- Container Name -->
        <div>
          <label class="block text-xs text-gray-400 mb-1">Container Name</label>
          <input v-model="config.name" class="input w-full" placeholder="my-container" />
        </div>
      </div>

      <!-- Runtime Options -->
      <div class="flex flex-wrap gap-4">
        <label class="flex items-center gap-2 text-sm">
          <input v-model="config.detached" type="checkbox" class="rounded border-white/[0.08] bg-white/[0.04]" />
          <span>Detached (-d)</span>
        </label>
        <label class="flex items-center gap-2 text-sm">
          <input v-model="config.interactive" type="checkbox" class="rounded border-white/[0.08] bg-white/[0.04]" />
          <span>Interactive (-i)</span>
        </label>
        <label class="flex items-center gap-2 text-sm">
          <input v-model="config.tty" type="checkbox" class="rounded border-white/[0.08] bg-white/[0.04]" />
          <span>TTY (-t)</span>
        </label>
        <label class="flex items-center gap-2 text-sm">
          <input v-model="config.rm" type="checkbox" class="rounded border-white/[0.08] bg-white/[0.04]" />
          <span>Remove on exit (--rm)</span>
        </label>
        <label class="flex items-center gap-2 text-sm">
          <input v-model="config.privileged" type="checkbox" class="rounded border-white/[0.08] bg-white/[0.04]" />
          <span>Privileged</span>
        </label>
        <label class="flex items-center gap-2 text-sm">
          <input v-model="config.init" type="checkbox" class="rounded border-white/[0.08] bg-white/[0.04]" />
          <span>Init</span>
        </label>
      </div>

      <!-- Ports -->
      <div>
        <div class="flex items-center justify-between mb-2">
          <label class="text-xs text-gray-400">Ports</label>
          <button @click="addPort" class="text-xs text-primary-400">+ Port</button>
        </div>
        <div class="space-y-2">
          <div v-for="(port, i) in config.ports" :key="i" class="flex items-center gap-2">
            <input v-model="port.host" class="input w-24" placeholder="8080" />
            <span class="text-gray-500">:</span>
            <input v-model="port.container" class="input w-24" placeholder="80" />
            <button @click="removePort(i)" class="text-red-400">&times;</button>
          </div>
        </div>
      </div>

      <!-- Volumes -->
      <div>
        <div class="flex items-center justify-between mb-2">
          <label class="text-xs text-gray-400">Volumes</label>
          <button @click="addVolume" class="text-xs text-primary-400">+ Volume</button>
        </div>
        <div class="space-y-2">
          <div v-for="(vol, i) in config.volumes" :key="i" class="flex items-center gap-2">
            <input v-model="vol.host" class="input flex-1" placeholder="./data" />
            <span class="text-gray-500">:</span>
            <input v-model="vol.container" class="input flex-1" placeholder="/app/data" />
            <select v-model="vol.mode" class="input w-16">
              <option value="">rw</option>
              <option value="ro">ro</option>
            </select>
            <button @click="removeVolume(i)" class="text-red-400">&times;</button>
          </div>
        </div>
      </div>

      <!-- Environment -->
      <div>
        <div class="flex items-center justify-between mb-2">
          <label class="text-xs text-gray-400">Environment Variables</label>
          <button @click="addEnvVar" class="text-xs text-primary-400">+ Variable</button>
        </div>
        <div class="space-y-2">
          <div v-for="(env, i) in config.envVars" :key="i" class="flex items-center gap-2">
            <input v-model="env.key" class="input w-32" placeholder="KEY" />
            <span class="text-gray-500">=</span>
            <input v-model="env.value" class="input flex-1" placeholder="value" />
            <button @click="removeEnvVar(i)" class="text-red-400">&times;</button>
          </div>
        </div>
      </div>

      <!-- Network & Resources -->
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-xs text-gray-400 mb-1">Network</label>
          <input v-model="config.network" class="input w-full" placeholder="bridge" />
        </div>
        <div>
          <label class="block text-xs text-gray-400 mb-1">Restart Policy</label>
          <select v-model="config.restart" class="input w-full">
            <option value="">none</option>
            <option value="always">always</option>
            <option value="unless-stopped">unless-stopped</option>
            <option value="on-failure">on-failure</option>
          </select>
        </div>
        <div>
          <label class="block text-xs text-gray-400 mb-1">Memory Limit</label>
          <input v-model="config.memory" class="input w-full" placeholder="512m" />
        </div>
        <div>
          <label class="block text-xs text-gray-400 mb-1">CPU Limit</label>
          <input v-model="config.cpus" class="input w-full" placeholder="0.5" />
        </div>
      </div>

      <!-- Command -->
      <div>
        <label class="block text-xs text-gray-400 mb-1">Command (optional)</label>
        <input v-model="config.cmd" class="input w-full" placeholder="/bin/sh -c 'echo hello'" />
      </div>
    </template>

    <!-- Exec Command Options -->
    <template v-else-if="command === 'exec'">
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-xs text-gray-400 mb-1">Container</label>
          <input v-model="config.name" class="input w-full" placeholder="container_name" />
        </div>
        <div>
          <label class="block text-xs text-gray-400 mb-1">Command</label>
          <input v-model="config.cmd" class="input w-full" placeholder="/bin/sh" />
        </div>
      </div>

      <div class="flex flex-wrap gap-4">
        <label class="flex items-center gap-2 text-sm">
          <input v-model="config.interactive" type="checkbox" class="rounded border-white/[0.08] bg-white/[0.04]" />
          <span>Interactive (-i)</span>
        </label>
        <label class="flex items-center gap-2 text-sm">
          <input v-model="config.tty" type="checkbox" class="rounded border-white/[0.08] bg-white/[0.04]" />
          <span>TTY (-t)</span>
        </label>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-xs text-gray-400 mb-1">User</label>
          <input v-model="config.user" class="input w-full" placeholder="root" />
        </div>
        <div>
          <label class="block text-xs text-gray-400 mb-1">Working Directory</label>
          <input v-model="config.workdir" class="input w-full" placeholder="/app" />
        </div>
      </div>
    </template>

    <!-- Build Command Options -->
    <template v-else-if="command === 'build'">
      <div>
        <label class="block text-xs text-gray-400 mb-1">Tag (-t)</label>
        <input v-model="config.name" class="input w-full" placeholder="myapp:latest" />
      </div>

      <div>
        <div class="flex items-center justify-between mb-2">
          <label class="text-xs text-gray-400">Build Arguments</label>
          <button @click="addEnvVar" class="text-xs text-primary-400">+ Argument</button>
        </div>
        <div class="space-y-2">
          <div v-for="(env, i) in config.envVars" :key="i" class="flex items-center gap-2">
            <input v-model="env.key" class="input w-32" placeholder="ARG_NAME" />
            <span class="text-gray-500">=</span>
            <input v-model="env.value" class="input flex-1" placeholder="value" />
            <button @click="removeEnvVar(i)" class="text-red-400">&times;</button>
          </div>
        </div>
      </div>

      <label class="flex items-center gap-2 text-sm">
        <input v-model="config.readonly" type="checkbox" class="rounded border-white/[0.08] bg-white/[0.04]" />
        <span>No Cache</span>
      </label>
    </template>

    <!-- Generated Command -->
    <div>
      <div class="flex items-center justify-between mb-2">
        <label class="text-xs text-gray-400">Generierter Befehl</label>
        <button @click="copyToClipboard" class="btn-sm btn-secondary">
          <component :is="copied ? CheckIcon : ClipboardIcon" class="w-3 h-3" />
          {{ copied ? 'Kopiert!' : 'Kopieren' }}
        </button>
      </div>
      <pre class="bg-white/[0.02] p-4 rounded-lg text-sm text-green-400 font-mono overflow-auto whitespace-pre-wrap">{{ generatedCommand }}</pre>
    </div>
  </div>
</template>
