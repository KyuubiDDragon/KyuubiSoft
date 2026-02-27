<script setup>
import { ref, computed } from 'vue'

const count = ref(5)
const format = ref('json')
const selectedFields = ref(['id', 'name', 'email'])
const generatedData = ref([])

// German names and data
const firstNames = ['Max', 'Anna', 'Paul', 'Sophie', 'Felix', 'Emma', 'Leon', 'Mia', 'Noah', 'Hannah', 'Lukas', 'Lena', 'Jonas', 'Marie', 'Tim', 'Laura', 'Jan', 'Sarah', 'Tom', 'Julia']
const lastNames = ['Müller', 'Schmidt', 'Schneider', 'Fischer', 'Weber', 'Meyer', 'Wagner', 'Becker', 'Schulz', 'Hoffmann', 'Koch', 'Richter', 'Klein', 'Wolf', 'Neumann', 'Schwarz', 'Braun', 'Zimmermann', 'Krüger', 'Hartmann']
const domains = ['gmail.com', 'outlook.com', 'web.de', 'gmx.de', 'yahoo.de', 'mail.de', 'icloud.com', 'posteo.de']
const streets = ['Hauptstraße', 'Bahnhofstraße', 'Schulstraße', 'Gartenstraße', 'Dorfstraße', 'Bergstraße', 'Waldstraße', 'Ringstraße', 'Kirchstraße', 'Lindenstraße']
const cities = ['Berlin', 'Hamburg', 'München', 'Köln', 'Frankfurt', 'Stuttgart', 'Düsseldorf', 'Leipzig', 'Dortmund', 'Essen', 'Bremen', 'Dresden', 'Hannover', 'Nürnberg', 'Duisburg']
const countries = ['Deutschland', 'Österreich', 'Schweiz']
const companies = ['TechCorp GmbH', 'Digital Solutions AG', 'WebDev Pro', 'CloudSoft GmbH', 'DataTech AG', 'NetWorks GmbH', 'CyberSystems', 'SmartIT GmbH', 'FutureTech AG', 'InnoSoft GmbH']
const jobTitles = ['Software Entwickler', 'Projektmanager', 'Designer', 'Marketing Manager', 'Vertriebsleiter', 'Produktmanager', 'DevOps Engineer', 'Data Scientist', 'UX Designer', 'Teamleiter']
const products = ['Laptop', 'Smartphone', 'Tablet', 'Monitor', 'Tastatur', 'Maus', 'Kopfhörer', 'Webcam', 'USB-Hub', 'SSD']
const colors = ['Rot', 'Blau', 'Grün', 'Gelb', 'Orange', 'Lila', 'Pink', 'Schwarz', 'Weiß', 'Grau']
const lorem = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris.'

const fieldTypes = [
  { id: 'id', name: 'ID', category: 'Basis' },
  { id: 'uuid', name: 'UUID', category: 'Basis' },
  { id: 'name', name: 'Voller Name', category: 'Person' },
  { id: 'firstName', name: 'Vorname', category: 'Person' },
  { id: 'lastName', name: 'Nachname', category: 'Person' },
  { id: 'email', name: 'E-Mail', category: 'Person' },
  { id: 'phone', name: 'Telefon', category: 'Person' },
  { id: 'age', name: 'Alter', category: 'Person' },
  { id: 'birthDate', name: 'Geburtsdatum', category: 'Person' },
  { id: 'gender', name: 'Geschlecht', category: 'Person' },
  { id: 'street', name: 'Straße', category: 'Adresse' },
  { id: 'city', name: 'Stadt', category: 'Adresse' },
  { id: 'zip', name: 'PLZ', category: 'Adresse' },
  { id: 'country', name: 'Land', category: 'Adresse' },
  { id: 'company', name: 'Firma', category: 'Arbeit' },
  { id: 'jobTitle', name: 'Jobtitel', category: 'Arbeit' },
  { id: 'salary', name: 'Gehalt', category: 'Arbeit' },
  { id: 'product', name: 'Produkt', category: 'Sonstiges' },
  { id: 'price', name: 'Preis', category: 'Sonstiges' },
  { id: 'quantity', name: 'Menge', category: 'Sonstiges' },
  { id: 'color', name: 'Farbe', category: 'Sonstiges' },
  { id: 'boolean', name: 'Boolean', category: 'Sonstiges' },
  { id: 'date', name: 'Datum', category: 'Zeit' },
  { id: 'datetime', name: 'Datum+Zeit', category: 'Zeit' },
  { id: 'timestamp', name: 'Timestamp', category: 'Zeit' },
  { id: 'text', name: 'Lorem Text', category: 'Text' },
  { id: 'sentence', name: 'Satz', category: 'Text' },
  { id: 'url', name: 'URL', category: 'Web' },
  { id: 'ip', name: 'IP-Adresse', category: 'Web' },
  { id: 'username', name: 'Username', category: 'Web' },
]

const fieldCategories = computed(() => {
  const cats = {}
  fieldTypes.forEach(f => {
    if (!cats[f.category]) cats[f.category] = []
    cats[f.category].push(f)
  })
  return cats
})

// Helper functions
function random(arr) {
  return arr[Math.floor(Math.random() * arr.length)]
}

function randomInt(min, max) {
  return Math.floor(Math.random() * (max - min + 1)) + min
}

function generateUUID() {
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
    const r = Math.random() * 16 | 0
    const v = c === 'x' ? r : (r & 0x3 | 0x8)
    return v.toString(16)
  })
}

function generateDate(start = new Date(1970, 0, 1), end = new Date()) {
  return new Date(start.getTime() + Math.random() * (end.getTime() - start.getTime()))
}

function generateFieldValue(fieldId, index) {
  const firstName = random(firstNames)
  const lastName = random(lastNames)

  switch (fieldId) {
    case 'id':
      return index + 1
    case 'uuid':
      return generateUUID()
    case 'name':
      return `${firstName} ${lastName}`
    case 'firstName':
      return firstName
    case 'lastName':
      return lastName
    case 'email':
      return `${firstName.toLowerCase()}.${lastName.toLowerCase()}@${random(domains)}`
    case 'phone':
      return `+49 ${randomInt(100, 999)} ${randomInt(1000000, 9999999)}`
    case 'age':
      return randomInt(18, 80)
    case 'birthDate':
      return generateDate(new Date(1950, 0, 1), new Date(2005, 11, 31)).toISOString().split('T')[0]
    case 'gender':
      return random(['männlich', 'weiblich', 'divers'])
    case 'street':
      return `${random(streets)} ${randomInt(1, 150)}`
    case 'city':
      return random(cities)
    case 'zip':
      return String(randomInt(10000, 99999))
    case 'country':
      return random(countries)
    case 'company':
      return random(companies)
    case 'jobTitle':
      return random(jobTitles)
    case 'salary':
      return randomInt(30000, 120000)
    case 'product':
      return random(products)
    case 'price':
      return parseFloat((Math.random() * 1000).toFixed(2))
    case 'quantity':
      return randomInt(1, 100)
    case 'color':
      return random(colors)
    case 'boolean':
      return Math.random() > 0.5
    case 'date':
      return generateDate(new Date(2020, 0, 1)).toISOString().split('T')[0]
    case 'datetime':
      return generateDate(new Date(2020, 0, 1)).toISOString()
    case 'timestamp':
      return Math.floor(generateDate(new Date(2020, 0, 1)).getTime() / 1000)
    case 'text':
      return lorem
    case 'sentence':
      return lorem.split('.')[randomInt(0, 2)].trim() + '.'
    case 'url':
      return `https://${random(['example', 'test', 'demo', 'sample'])}.${random(['com', 'de', 'org', 'net'])}/${random(['page', 'article', 'post', 'item'])}/${randomInt(1, 9999)}`
    case 'ip':
      return `${randomInt(1, 255)}.${randomInt(0, 255)}.${randomInt(0, 255)}.${randomInt(1, 255)}`
    case 'username':
      return `${firstName.toLowerCase()}${randomInt(1, 999)}`
    default:
      return null
  }
}

function generate() {
  const data = []
  for (let i = 0; i < count.value; i++) {
    const item = {}
    selectedFields.value.forEach(fieldId => {
      item[fieldId] = generateFieldValue(fieldId, i)
    })
    data.push(item)
  }
  generatedData.value = data
}

const output = computed(() => {
  if (generatedData.value.length === 0) return ''

  switch (format.value) {
    case 'json':
      return JSON.stringify(generatedData.value, null, 2)
    case 'json-compact':
      return JSON.stringify(generatedData.value)
    case 'csv':
      const headers = selectedFields.value.join(',')
      const rows = generatedData.value.map(item =>
        selectedFields.value.map(f => {
          const val = item[f]
          if (typeof val === 'string' && (val.includes(',') || val.includes('"'))) {
            return `"${val.replace(/"/g, '""')}"`
          }
          return val
        }).join(',')
      )
      return [headers, ...rows].join('\n')
    case 'sql':
      const tableName = 'users'
      const cols = selectedFields.value.join(', ')
      const inserts = generatedData.value.map(item => {
        const vals = selectedFields.value.map(f => {
          const val = item[f]
          if (val === null) return 'NULL'
          if (typeof val === 'boolean') return val ? '1' : '0'
          if (typeof val === 'number') return val
          return `'${String(val).replace(/'/g, "''")}'`
        }).join(', ')
        return `INSERT INTO ${tableName} (${cols}) VALUES (${vals});`
      })
      return inserts.join('\n')
    default:
      return ''
  }
})

function toggleField(fieldId) {
  const idx = selectedFields.value.indexOf(fieldId)
  if (idx === -1) {
    selectedFields.value.push(fieldId)
  } else {
    selectedFields.value.splice(idx, 1)
  }
}

function copyToClipboard() {
  navigator.clipboard.writeText(output.value)
}

// Generate on load
generate()
</script>

<template>
  <div class="space-y-4">
    <!-- Controls -->
    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-xs text-gray-400 mb-1">Anzahl</label>
        <input
          v-model.number="count"
          type="number"
          min="1"
          max="1000"
          class="input w-full"
        />
      </div>
      <div>
        <label class="block text-xs text-gray-400 mb-1">Format</label>
        <select v-model="format" class="input w-full">
          <option value="json">JSON (formatiert)</option>
          <option value="json-compact">JSON (kompakt)</option>
          <option value="csv">CSV</option>
          <option value="sql">SQL INSERT</option>
        </select>
      </div>
    </div>

    <!-- Field Selection -->
    <div>
      <label class="block text-xs text-gray-400 mb-2">Felder auswählen</label>
      <div class="grid grid-cols-2 md:grid-cols-3 gap-3 max-h-48 overflow-y-auto p-1">
        <div v-for="(fields, category) in fieldCategories" :key="category">
          <div class="text-xs text-gray-500 mb-1">{{ category }}</div>
          <div class="space-y-1">
            <label
              v-for="field in fields"
              :key="field.id"
              class="flex items-center gap-2 text-sm cursor-pointer hover:text-white"
              :class="selectedFields.includes(field.id) ? 'text-primary-400' : 'text-gray-400'"
            >
              <input
                type="checkbox"
                :checked="selectedFields.includes(field.id)"
                @change="toggleField(field.id)"
                class="rounded bg-white/[0.08] border-white/[0.08]"
              />
              {{ field.name }}
            </label>
          </div>
        </div>
      </div>
    </div>

    <!-- Generate Button -->
    <button @click="generate" class="btn-primary w-full">
      Generieren ({{ count }} Einträge)
    </button>

    <!-- Output -->
    <div v-if="output">
      <div class="flex items-center justify-between mb-1">
        <label class="text-xs text-gray-400">Ausgabe</label>
        <button @click="copyToClipboard" class="text-xs text-primary-400 hover:text-primary-300">
          Kopieren
        </button>
      </div>
      <pre class="p-3 bg-white/[0.02] rounded-lg text-xs text-green-400 font-mono max-h-64 overflow-auto">{{ output }}</pre>
    </div>
  </div>
</template>
