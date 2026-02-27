<script setup>
import { ref, computed } from 'vue'

const amount = ref(3)
const unit = ref('paragraphs') // 'paragraphs', 'sentences', 'words'
const startWithLorem = ref(true)

const loremWords = [
  'lorem', 'ipsum', 'dolor', 'sit', 'amet', 'consectetur', 'adipiscing', 'elit',
  'sed', 'do', 'eiusmod', 'tempor', 'incididunt', 'ut', 'labore', 'et', 'dolore',
  'magna', 'aliqua', 'enim', 'ad', 'minim', 'veniam', 'quis', 'nostrud',
  'exercitation', 'ullamco', 'laboris', 'nisi', 'aliquip', 'ex', 'ea', 'commodo',
  'consequat', 'duis', 'aute', 'irure', 'in', 'reprehenderit', 'voluptate',
  'velit', 'esse', 'cillum', 'fugiat', 'nulla', 'pariatur', 'excepteur', 'sint',
  'occaecat', 'cupidatat', 'non', 'proident', 'sunt', 'culpa', 'qui', 'officia',
  'deserunt', 'mollit', 'anim', 'id', 'est', 'laborum', 'perspiciatis', 'unde',
  'omnis', 'iste', 'natus', 'error', 'voluptatem', 'accusantium', 'doloremque',
  'laudantium', 'totam', 'rem', 'aperiam', 'eaque', 'ipsa', 'quae', 'ab', 'illo',
  'inventore', 'veritatis', 'quasi', 'architecto', 'beatae', 'vitae', 'dicta',
  'explicabo', 'nemo', 'ipsam', 'quia', 'voluptas', 'aspernatur', 'aut', 'odit',
  'fugit', 'consequuntur', 'magni', 'dolores', 'eos', 'ratione', 'sequi',
  'nesciunt', 'neque', 'porro', 'quisquam', 'nihil', 'impedit', 'quo', 'minus',
  'quod', 'maxime', 'placeat', 'facere', 'possimus', 'assumenda', 'repellendus',
  'temporibus', 'quibusdam', 'officiis', 'debitis', 'necessitatibus', 'saepe',
  'eveniet', 'voluptates', 'repudiandae', 'recusandae', 'itaque', 'earum',
  'rerum', 'hic', 'tenetur', 'sapiente', 'delectus', 'reiciendis', 'voluptatibus',
  'maiores', 'alias', 'perferendis', 'doloribus', 'asperiores', 'repellat',
]

function getRandomWord() {
  return loremWords[Math.floor(Math.random() * loremWords.length)]
}

function capitalize(str) {
  return str.charAt(0).toUpperCase() + str.slice(1)
}

function generateSentence(wordCount = null) {
  const count = wordCount || Math.floor(Math.random() * 10) + 5 // 5-14 words
  const words = []

  for (let i = 0; i < count; i++) {
    words.push(getRandomWord())
  }

  // Add commas randomly
  if (count > 6) {
    const commaPos = Math.floor(count / 2) + Math.floor(Math.random() * 3) - 1
    if (commaPos > 0 && commaPos < count - 1) {
      words[commaPos] = words[commaPos] + ','
    }
  }

  return capitalize(words.join(' ')) + '.'
}

function generateParagraph() {
  const sentenceCount = Math.floor(Math.random() * 4) + 4 // 4-7 sentences
  const sentences = []

  for (let i = 0; i < sentenceCount; i++) {
    sentences.push(generateSentence())
  }

  return sentences.join(' ')
}

const generatedText = computed(() => {
  let result = ''

  switch (unit.value) {
    case 'paragraphs':
      const paragraphs = []
      for (let i = 0; i < amount.value; i++) {
        paragraphs.push(generateParagraph())
      }
      result = paragraphs.join('\n\n')
      break

    case 'sentences':
      const sentences = []
      for (let i = 0; i < amount.value; i++) {
        sentences.push(generateSentence())
      }
      result = sentences.join(' ')
      break

    case 'words':
      const words = []
      for (let i = 0; i < amount.value; i++) {
        words.push(getRandomWord())
      }
      result = capitalize(words.join(' ')) + '.'
      break
  }

  // Replace start with "Lorem ipsum dolor sit amet" if enabled
  if (startWithLorem.value && result.length > 0) {
    const loremStart = 'Lorem ipsum dolor sit amet'
    const firstDot = result.indexOf('.')
    if (firstDot > loremStart.length) {
      result = loremStart + result.slice(loremStart.length)
    } else if (unit.value === 'words' && amount.value >= 5) {
      const wordsArray = result.slice(0, -1).split(' ')
      const loremWords = ['Lorem', 'ipsum', 'dolor', 'sit', 'amet']
      for (let i = 0; i < Math.min(5, wordsArray.length); i++) {
        wordsArray[i] = loremWords[i]
      }
      result = wordsArray.join(' ') + '.'
    }
  }

  return result
})

const stats = computed(() => {
  const text = generatedText.value
  const words = text.split(/\s+/).filter(w => w.length > 0).length
  const chars = text.length
  const charsNoSpace = text.replace(/\s/g, '').length
  const paragraphs = text.split(/\n\n/).filter(p => p.length > 0).length
  const sentences = (text.match(/[.!?]/g) || []).length

  return { words, chars, charsNoSpace, paragraphs, sentences }
})

function copyToClipboard() {
  navigator.clipboard.writeText(generatedText.value)
}

function regenerate() {
  // Force reactivity by changing amount slightly
  amount.value = amount.value
}
</script>

<template>
  <div class="space-y-4">
    <!-- Controls -->
    <div class="flex flex-wrap gap-4 items-end">
      <div>
        <label class="text-sm text-gray-400 mb-1 block">Anzahl</label>
        <input
          v-model.number="amount"
          type="number"
          min="1"
          max="100"
          class="input w-24"
        />
      </div>

      <div>
        <label class="text-sm text-gray-400 mb-1 block">Einheit</label>
        <select v-model="unit" class="input">
          <option value="paragraphs">Absätze</option>
          <option value="sentences">Sätze</option>
          <option value="words">Wörter</option>
        </select>
      </div>

      <label class="flex items-center gap-2 text-sm text-gray-400 pb-2">
        <input
          type="checkbox"
          v-model="startWithLorem"
          class="rounded bg-white/[0.04] border-white/[0.06]"
        />
        Mit "Lorem ipsum" beginnen
      </label>

      <div class="flex gap-2 ml-auto">
        <button
          @click="regenerate"
          class="btn-secondary px-4 py-2"
        >
          Neu generieren
        </button>
        <button
          @click="copyToClipboard"
          class="btn-primary px-4 py-2"
        >
          Kopieren
        </button>
      </div>
    </div>

    <!-- Stats -->
    <div class="flex gap-4 text-sm text-gray-400">
      <span>{{ stats.paragraphs }} Absätze</span>
      <span>{{ stats.sentences }} Sätze</span>
      <span>{{ stats.words }} Wörter</span>
      <span>{{ stats.chars }} Zeichen</span>
    </div>

    <!-- Output -->
    <div class="p-4 bg-white/[0.04] rounded-lg max-h-96 overflow-auto">
      <p
        v-for="(paragraph, i) in generatedText.split('\n\n')"
        :key="i"
        class="text-gray-300 leading-relaxed"
        :class="i > 0 ? 'mt-4' : ''"
      >
        {{ paragraph }}
      </p>
    </div>

    <!-- Quick Amounts -->
    <div>
      <label class="text-sm text-gray-400 mb-2 block">Schnellauswahl</label>
      <div class="flex flex-wrap gap-2">
        <button
          v-for="preset in [
            { amount: 1, unit: 'paragraphs', label: '1 Absatz' },
            { amount: 3, unit: 'paragraphs', label: '3 Absätze' },
            { amount: 5, unit: 'paragraphs', label: '5 Absätze' },
            { amount: 5, unit: 'sentences', label: '5 Sätze' },
            { amount: 10, unit: 'sentences', label: '10 Sätze' },
            { amount: 50, unit: 'words', label: '50 Wörter' },
            { amount: 100, unit: 'words', label: '100 Wörter' },
          ]"
          :key="preset.label"
          @click="amount = preset.amount; unit = preset.unit"
          class="px-3 py-1 text-xs bg-white/[0.08] text-gray-300 rounded hover:bg-white/[0.08] transition-colors"
        >
          {{ preset.label }}
        </button>
      </div>
    </div>
  </div>
</template>
