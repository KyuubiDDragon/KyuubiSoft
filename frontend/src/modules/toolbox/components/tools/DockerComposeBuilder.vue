<script setup>
import { ref, computed } from 'vue'
import { ClipboardIcon, ArrowDownTrayIcon, CheckIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'

// Preset service templates
const servicePresets = {
  nginx: {
    name: 'nginx',
    image: 'nginx:alpine',
    ports: ['80:80'],
    volumes: ['./nginx.conf:/etc/nginx/nginx.conf:ro', './html:/usr/share/nginx/html:ro'],
    restart: 'unless-stopped',
  },
  mysql: {
    name: 'mysql',
    image: 'mysql:8',
    ports: ['3306:3306'],
    environment: { MYSQL_ROOT_PASSWORD: 'rootpassword', MYSQL_DATABASE: 'app', MYSQL_USER: 'user', MYSQL_PASSWORD: 'password' },
    volumes: ['mysql_data:/var/lib/mysql'],
    restart: 'unless-stopped',
  },
  postgres: {
    name: 'postgres',
    image: 'postgres:16-alpine',
    ports: ['5432:5432'],
    environment: { POSTGRES_USER: 'user', POSTGRES_PASSWORD: 'password', POSTGRES_DB: 'app' },
    volumes: ['postgres_data:/var/lib/postgresql/data'],
    restart: 'unless-stopped',
  },
  redis: {
    name: 'redis',
    image: 'redis:alpine',
    ports: ['6379:6379'],
    volumes: ['redis_data:/data'],
    restart: 'unless-stopped',
  },
  mongodb: {
    name: 'mongodb',
    image: 'mongo:7',
    ports: ['27017:27017'],
    environment: { MONGO_INITDB_ROOT_USERNAME: 'root', MONGO_INITDB_ROOT_PASSWORD: 'password' },
    volumes: ['mongo_data:/data/db'],
    restart: 'unless-stopped',
  },
  rabbitmq: {
    name: 'rabbitmq',
    image: 'rabbitmq:3-management-alpine',
    ports: ['5672:5672', '15672:15672'],
    environment: { RABBITMQ_DEFAULT_USER: 'user', RABBITMQ_DEFAULT_PASS: 'password' },
    volumes: ['rabbitmq_data:/var/lib/rabbitmq'],
    restart: 'unless-stopped',
  },
  mailhog: {
    name: 'mailhog',
    image: 'mailhog/mailhog',
    ports: ['1025:1025', '8025:8025'],
    restart: 'unless-stopped',
  },
  adminer: {
    name: 'adminer',
    image: 'adminer',
    ports: ['8080:8080'],
    restart: 'unless-stopped',
  },
  node: {
    name: 'app',
    build: { context: '.', dockerfile: 'Dockerfile' },
    ports: ['3000:3000'],
    volumes: ['.:/app', '/app/node_modules'],
    environment: { NODE_ENV: 'development' },
    restart: 'unless-stopped',
  },
  php: {
    name: 'php',
    build: { context: '.', dockerfile: 'Dockerfile' },
    volumes: ['.:/var/www/html'],
    restart: 'unless-stopped',
  },
}

// State
const composeVersion = ref('3.8')
const services = ref([])
const networks = ref([])
const volumes = ref([])
const copied = ref(false)

// Add service from preset
function addServiceFromPreset(presetKey) {
  const preset = servicePresets[presetKey]
  if (preset) {
    const service = JSON.parse(JSON.stringify(preset))
    // Make name unique if duplicate
    let baseName = service.name
    let counter = 1
    while (services.value.some(s => s.name === service.name)) {
      service.name = `${baseName}_${counter++}`
    }
    services.value.push(service)

    // Auto-add volumes
    if (service.volumes) {
      service.volumes.forEach(v => {
        const parts = v.split(':')
        if (parts[0] && !parts[0].startsWith('.') && !parts[0].startsWith('/')) {
          if (!volumes.value.includes(parts[0])) {
            volumes.value.push(parts[0])
          }
        }
      })
    }
  }
}

// Add empty service
function addEmptyService() {
  services.value.push({
    name: `service_${services.value.length + 1}`,
    image: '',
    build: null,
    ports: [],
    volumes: [],
    environment: {},
    depends_on: [],
    restart: 'unless-stopped',
  })
}

// Remove service
function removeService(index) {
  services.value.splice(index, 1)
}

// Add port to service
function addPort(service) {
  if (!service.ports) service.ports = []
  service.ports.push('')
}

// Remove port from service
function removePort(service, index) {
  service.ports.splice(index, 1)
}

// Add volume to service
function addVolume(service) {
  if (!service.volumes) service.volumes = []
  service.volumes.push('')
}

// Remove volume from service
function removeVolume(service, index) {
  service.volumes.splice(index, 1)
}

// Add environment variable to service
function addEnvVar(service) {
  if (!service.environment) service.environment = {}
  const key = `VAR_${Object.keys(service.environment).length + 1}`
  service.environment[key] = ''
}

// Remove environment variable from service
function removeEnvVar(service, key) {
  delete service.environment[key]
}

// Add network
function addNetwork() {
  networks.value.push(`network_${networks.value.length + 1}`)
}

// Remove network
function removeNetwork(index) {
  networks.value.splice(index, 1)
}

// Add volume definition
function addVolumeDefinition() {
  volumes.value.push(`volume_${volumes.value.length + 1}`)
}

// Remove volume definition
function removeVolumeDefinition(index) {
  volumes.value.splice(index, 1)
}

// Generate docker-compose.yml
const composeYaml = computed(() => {
  const compose = {
    version: composeVersion.value,
  }

  // Services
  if (services.value.length > 0) {
    compose.services = {}
    services.value.forEach(service => {
      const svc = {}

      if (service.build) {
        svc.build = service.build
      } else if (service.image) {
        svc.image = service.image
      }

      if (service.ports && service.ports.length > 0) {
        svc.ports = service.ports.filter(p => p)
      }

      if (service.volumes && service.volumes.length > 0) {
        svc.volumes = service.volumes.filter(v => v)
      }

      if (service.environment && Object.keys(service.environment).length > 0) {
        svc.environment = service.environment
      }

      if (service.depends_on && service.depends_on.length > 0) {
        svc.depends_on = service.depends_on
      }

      if (service.restart) {
        svc.restart = service.restart
      }

      if (networks.value.length > 0) {
        svc.networks = networks.value
      }

      compose.services[service.name] = svc
    })
  }

  // Networks
  if (networks.value.length > 0) {
    compose.networks = {}
    networks.value.forEach(network => {
      compose.networks[network] = { driver: 'bridge' }
    })
  }

  // Volumes
  if (volumes.value.length > 0) {
    compose.volumes = {}
    volumes.value.forEach(volume => {
      compose.volumes[volume] = null
    })
  }

  return generateYaml(compose)
})

// Simple YAML generator
function generateYaml(obj, indent = 0) {
  const spaces = '  '.repeat(indent)
  let yaml = ''

  for (const [key, value] of Object.entries(obj)) {
    if (value === null || value === undefined) {
      yaml += `${spaces}${key}:\n`
    } else if (Array.isArray(value)) {
      yaml += `${spaces}${key}:\n`
      value.forEach(item => {
        if (typeof item === 'object') {
          yaml += `${spaces}  -\n`
          yaml += generateYaml(item, indent + 2)
        } else {
          yaml += `${spaces}  - ${item}\n`
        }
      })
    } else if (typeof value === 'object') {
      yaml += `${spaces}${key}:\n`
      yaml += generateYaml(value, indent + 1)
    } else {
      yaml += `${spaces}${key}: ${value}\n`
    }
  }

  return yaml
}

// Copy to clipboard
function copyToClipboard() {
  navigator.clipboard.writeText(composeYaml.value)
  copied.value = true
  setTimeout(() => { copied.value = false }, 2000)
}

// Download file
function downloadCompose() {
  const blob = new Blob([composeYaml.value], { type: 'text/yaml' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = 'docker-compose.yml'
  a.click()
  URL.revokeObjectURL(url)
}

// Get other service names for depends_on
function getOtherServices(currentService) {
  return services.value
    .filter(s => s.name !== currentService.name)
    .map(s => s.name)
}
</script>

<template>
  <div class="space-y-4">
    <!-- Quick Add Presets -->
    <div>
      <label class="block text-xs text-gray-400 mb-2">Schnell hinzufügen</label>
      <div class="flex flex-wrap gap-2">
        <button
          v-for="(preset, key) in servicePresets"
          :key="key"
          @click="addServiceFromPreset(key)"
          class="px-3 py-1 bg-dark-700 hover:bg-dark-600 rounded text-sm transition-colors"
        >
          {{ preset.name }}
        </button>
      </div>
    </div>

    <!-- Services -->
    <div>
      <div class="flex items-center justify-between mb-2">
        <label class="text-sm font-medium text-gray-300">Services ({{ services.length }})</label>
        <button @click="addEmptyService" class="btn-sm btn-secondary">
          <PlusIcon class="w-3 h-3" />
          Leerer Service
        </button>
      </div>

      <div class="space-y-3">
        <div
          v-for="(service, sIndex) in services"
          :key="sIndex"
          class="card p-3 space-y-3"
        >
          <div class="flex items-center justify-between">
            <input
              v-model="service.name"
              class="input w-40 text-sm font-medium"
              placeholder="service_name"
            />
            <button @click="removeService(sIndex)" class="btn-icon text-red-400">
              <TrashIcon class="w-4 h-4" />
            </button>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <!-- Image -->
            <div>
              <label class="block text-xs text-gray-400 mb-1">Image</label>
              <input v-model="service.image" class="input w-full text-sm" placeholder="nginx:alpine" />
            </div>

            <!-- Restart -->
            <div>
              <label class="block text-xs text-gray-400 mb-1">Restart Policy</label>
              <select v-model="service.restart" class="input w-full text-sm">
                <option value="">none</option>
                <option value="always">always</option>
                <option value="unless-stopped">unless-stopped</option>
                <option value="on-failure">on-failure</option>
              </select>
            </div>
          </div>

          <!-- Ports -->
          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="text-xs text-gray-400">Ports</label>
              <button @click="addPort(service)" class="text-xs text-primary-400">+ Port</button>
            </div>
            <div class="flex flex-wrap gap-2">
              <div v-for="(port, pIndex) in service.ports" :key="pIndex" class="flex items-center gap-1">
                <input
                  v-model="service.ports[pIndex]"
                  class="input w-28 text-sm"
                  placeholder="8080:80"
                />
                <button @click="removePort(service, pIndex)" class="text-red-400 text-xs">&times;</button>
              </div>
            </div>
          </div>

          <!-- Volumes -->
          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="text-xs text-gray-400">Volumes</label>
              <button @click="addVolume(service)" class="text-xs text-primary-400">+ Volume</button>
            </div>
            <div class="space-y-1">
              <div v-for="(vol, vIndex) in service.volumes" :key="vIndex" class="flex items-center gap-1">
                <input
                  v-model="service.volumes[vIndex]"
                  class="input flex-1 text-sm"
                  placeholder="./data:/app/data"
                />
                <button @click="removeVolume(service, vIndex)" class="text-red-400 text-xs">&times;</button>
              </div>
            </div>
          </div>

          <!-- Environment -->
          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="text-xs text-gray-400">Environment</label>
              <button @click="addEnvVar(service)" class="text-xs text-primary-400">+ Variable</button>
            </div>
            <div class="space-y-1">
              <div v-for="(value, key) in service.environment" :key="key" class="flex items-center gap-1">
                <input
                  :value="key"
                  @input="e => { const newKey = e.target.value; const val = service.environment[key]; delete service.environment[key]; service.environment[newKey] = val }"
                  class="input w-32 text-sm"
                  placeholder="KEY"
                />
                <input
                  v-model="service.environment[key]"
                  class="input flex-1 text-sm"
                  placeholder="value"
                />
                <button @click="removeEnvVar(service, key)" class="text-red-400 text-xs">&times;</button>
              </div>
            </div>
          </div>

          <!-- Depends On -->
          <div v-if="getOtherServices(service).length > 0">
            <label class="block text-xs text-gray-400 mb-1">Depends On</label>
            <div class="flex flex-wrap gap-2">
              <label
                v-for="other in getOtherServices(service)"
                :key="other"
                class="flex items-center gap-1 text-sm"
              >
                <input
                  type="checkbox"
                  :checked="service.depends_on?.includes(other)"
                  @change="e => {
                    if (!service.depends_on) service.depends_on = []
                    if (e.target.checked) {
                      service.depends_on.push(other)
                    } else {
                      service.depends_on = service.depends_on.filter(d => d !== other)
                    }
                  }"
                  class="rounded border-dark-500 bg-dark-700"
                />
                {{ other }}
              </label>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Networks & Volumes -->
    <div class="grid grid-cols-2 gap-4">
      <div>
        <div class="flex items-center justify-between mb-2">
          <label class="text-xs text-gray-400">Networks</label>
          <button @click="addNetwork" class="text-xs text-primary-400">+ Network</button>
        </div>
        <div class="space-y-1">
          <div v-for="(network, nIndex) in networks" :key="nIndex" class="flex items-center gap-1">
            <input v-model="networks[nIndex]" class="input flex-1 text-sm" />
            <button @click="removeNetwork(nIndex)" class="text-red-400 text-xs">&times;</button>
          </div>
        </div>
      </div>

      <div>
        <div class="flex items-center justify-between mb-2">
          <label class="text-xs text-gray-400">Volumes</label>
          <button @click="addVolumeDefinition" class="text-xs text-primary-400">+ Volume</button>
        </div>
        <div class="space-y-1">
          <div v-for="(volume, vIndex) in volumes" :key="vIndex" class="flex items-center gap-1">
            <input v-model="volumes[vIndex]" class="input flex-1 text-sm" />
            <button @click="removeVolumeDefinition(vIndex)" class="text-red-400 text-xs">&times;</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Generated Output -->
    <div>
      <div class="flex items-center justify-between mb-2">
        <label class="text-xs text-gray-400">docker-compose.yml</label>
        <div class="flex gap-2">
          <button @click="copyToClipboard" class="btn-sm btn-secondary">
            <component :is="copied ? CheckIcon : ClipboardIcon" class="w-3 h-3" />
            {{ copied ? 'Kopiert!' : 'Kopieren' }}
          </button>
          <button @click="downloadCompose" class="btn-sm btn-primary">
            <ArrowDownTrayIcon class="w-3 h-3" />
            Download
          </button>
        </div>
      </div>
      <pre class="bg-dark-900 p-4 rounded-lg text-sm text-gray-300 font-mono overflow-auto max-h-80">{{ composeYaml }}</pre>
    </div>
  </div>
</template>
