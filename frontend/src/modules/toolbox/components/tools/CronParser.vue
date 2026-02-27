<script setup>
import { ref, computed } from 'vue'

const cronExpression = ref('0 9 * * 1-5')
const error = ref('')

const fields = [
  { name: 'Minute', range: '0-59', position: 0 },
  { name: 'Stunde', range: '0-23', position: 1 },
  { name: 'Tag (Monat)', range: '1-31', position: 2 },
  { name: 'Monat', range: '1-12', position: 3 },
  { name: 'Wochentag', range: '0-6 (So-Sa)', position: 4 },
]

const presets = [
  { name: 'Jede Minute', cron: '* * * * *' },
  { name: 'Jede Stunde', cron: '0 * * * *' },
  { name: 'Täglich um Mitternacht', cron: '0 0 * * *' },
  { name: 'Täglich um 9:00', cron: '0 9 * * *' },
  { name: 'Wochentags um 9:00', cron: '0 9 * * 1-5' },
  { name: 'Montags um 9:00', cron: '0 9 * * 1' },
  { name: 'Monatlich am 1.', cron: '0 0 1 * *' },
  { name: 'Jährlich am 1.1.', cron: '0 0 1 1 *' },
  { name: 'Alle 5 Minuten', cron: '*/5 * * * *' },
  { name: 'Alle 15 Minuten', cron: '*/15 * * * *' },
  { name: 'Alle 30 Minuten', cron: '*/30 * * * *' },
  { name: 'Alle 6 Stunden', cron: '0 */6 * * *' },
]

const weekdays = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag']
const months = ['', 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember']

const parsedCron = computed(() => {
  error.value = ''

  const parts = cronExpression.value.trim().split(/\s+/)

  if (parts.length !== 5) {
    error.value = 'Cron-Ausdruck muss genau 5 Felder haben'
    return null
  }

  return {
    minute: parts[0],
    hour: parts[1],
    dayOfMonth: parts[2],
    month: parts[3],
    dayOfWeek: parts[4],
  }
})

const humanReadable = computed(() => {
  if (!parsedCron.value) return ''

  const { minute, hour, dayOfMonth, month, dayOfWeek } = parsedCron.value
  let parts = []

  // Time
  if (minute === '*' && hour === '*') {
    parts.push('Jede Minute')
  } else if (minute.startsWith('*/')) {
    parts.push(`Alle ${minute.slice(2)} Minuten`)
  } else if (hour === '*') {
    parts.push(`Zur Minute ${minute} jeder Stunde`)
  } else if (hour.startsWith('*/')) {
    parts.push(`Alle ${hour.slice(2)} Stunden${minute !== '0' ? ` zur Minute ${minute}` : ''}`)
  } else {
    const h = hour.padStart(2, '0')
    const m = minute.padStart(2, '0')
    parts.push(`Um ${h}:${m} Uhr`)
  }

  // Day of month
  if (dayOfMonth !== '*') {
    if (dayOfMonth.includes('-')) {
      const [start, end] = dayOfMonth.split('-')
      parts.push(`vom ${start}. bis ${end}. des Monats`)
    } else if (dayOfMonth.includes(',')) {
      parts.push(`am ${dayOfMonth.replace(/,/g, '., ')}. des Monats`)
    } else {
      parts.push(`am ${dayOfMonth}. des Monats`)
    }
  }

  // Month
  if (month !== '*') {
    if (month.includes('-')) {
      const [start, end] = month.split('-')
      parts.push(`von ${months[parseInt(start)]} bis ${months[parseInt(end)]}`)
    } else if (month.includes(',')) {
      const monthNames = month.split(',').map(m => months[parseInt(m)]).join(', ')
      parts.push(`in ${monthNames}`)
    } else {
      parts.push(`im ${months[parseInt(month)]}`)
    }
  }

  // Day of week
  if (dayOfWeek !== '*') {
    if (dayOfWeek.includes('-')) {
      const [start, end] = dayOfWeek.split('-')
      parts.push(`${weekdays[parseInt(start)]} bis ${weekdays[parseInt(end)]}`)
    } else if (dayOfWeek.includes(',')) {
      const dayNames = dayOfWeek.split(',').map(d => weekdays[parseInt(d)]).join(', ')
      parts.push(`an ${dayNames}`)
    } else {
      parts.push(`jeden ${weekdays[parseInt(dayOfWeek)]}`)
    }
  }

  return parts.join(', ')
})

const nextExecutions = computed(() => {
  if (!parsedCron.value) return []

  // Simple calculation of next executions
  const executions = []
  const now = new Date()
  let current = new Date(now)

  for (let i = 0; i < 5 && executions.length < 5; i++) {
    current = getNextExecution(current)
    if (current) {
      executions.push(current.toLocaleString('de-DE'))
      current = new Date(current.getTime() + 60000) // Add 1 minute
    } else {
      break
    }
  }

  return executions
})

function getNextExecution(from) {
  if (!parsedCron.value) return null

  const { minute, hour, dayOfMonth, month, dayOfWeek } = parsedCron.value

  let date = new Date(from)
  date.setSeconds(0)
  date.setMilliseconds(0)

  // Simple implementation - just advance by 1 minute until match or max iterations
  for (let i = 0; i < 525600; i++) { // Max 1 year of minutes
    date = new Date(date.getTime() + 60000)

    if (matchesCron(date, minute, hour, dayOfMonth, month, dayOfWeek)) {
      return date
    }
  }

  return null
}

function matchesCron(date, minute, hour, dayOfMonth, month, dayOfWeek) {
  return matchesField(date.getMinutes(), minute) &&
         matchesField(date.getHours(), hour) &&
         matchesField(date.getDate(), dayOfMonth) &&
         matchesField(date.getMonth() + 1, month) &&
         matchesField(date.getDay(), dayOfWeek)
}

function matchesField(value, field) {
  if (field === '*') return true

  if (field.includes('/')) {
    const [, step] = field.split('/')
    return value % parseInt(step) === 0
  }

  if (field.includes('-')) {
    const [start, end] = field.split('-').map(Number)
    return value >= start && value <= end
  }

  if (field.includes(',')) {
    return field.split(',').map(Number).includes(value)
  }

  return parseInt(field) === value
}

function usePreset(cron) {
  cronExpression.value = cron
}
</script>

<template>
  <div class="space-y-4">
    <!-- Input -->
    <div>
      <label class="text-sm text-gray-400 mb-1 block">Cron Expression</label>
      <input
        v-model="cronExpression"
        type="text"
        class="input w-full font-mono text-lg"
        placeholder="* * * * *"
      />
    </div>

    <!-- Field Reference -->
    <div class="grid grid-cols-5 gap-2 text-center text-xs">
      <div v-for="field in fields" :key="field.position" class="p-2 bg-white/[0.04] rounded">
        <div class="font-medium text-white">{{ field.name }}</div>
        <div class="text-gray-500">{{ field.range }}</div>
      </div>
    </div>

    <!-- Presets -->
    <div>
      <label class="text-sm text-gray-400 mb-2 block">Häufige Ausdrücke</label>
      <div class="flex flex-wrap gap-2">
        <button
          v-for="preset in presets"
          :key="preset.cron"
          @click="usePreset(preset.cron)"
          class="px-2 py-1 text-xs bg-white/[0.04] text-gray-300 rounded hover:bg-white/[0.04]"
          :class="cronExpression === preset.cron ? 'ring-1 ring-primary-500' : ''"
        >
          {{ preset.name }}
        </button>
      </div>
    </div>

    <!-- Error -->
    <div v-if="error" class="p-3 bg-red-900/30 border border-red-700 rounded-lg text-red-400 text-sm">
      {{ error }}
    </div>

    <!-- Human Readable -->
    <div v-if="humanReadable" class="p-4 bg-primary-900/20 border border-primary-700 rounded-lg">
      <div class="text-sm text-gray-400 mb-1">Bedeutung:</div>
      <div class="text-lg text-white">{{ humanReadable }}</div>
    </div>

    <!-- Next Executions -->
    <div v-if="nextExecutions.length > 0">
      <label class="text-sm text-gray-400 mb-2 block">Nächste Ausführungen</label>
      <div class="space-y-1">
        <div
          v-for="(exec, i) in nextExecutions"
          :key="i"
          class="p-2 bg-white/[0.04] rounded text-sm font-mono"
        >
          {{ exec }}
        </div>
      </div>
    </div>

    <!-- Syntax Help -->
    <div class="text-xs text-gray-500 space-y-1">
      <p class="font-medium">Syntax:</p>
      <ul class="list-disc list-inside space-y-0.5">
        <li><code>*</code> - Jeder Wert</li>
        <li><code>*/n</code> - Alle n Einheiten</li>
        <li><code>n-m</code> - Bereich von n bis m</li>
        <li><code>n,m</code> - Spezifische Werte</li>
      </ul>
    </div>
  </div>
</template>
