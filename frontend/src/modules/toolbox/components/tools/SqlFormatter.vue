<script setup>
import { ref, computed, watch } from 'vue'

const input = ref('')
const indentSize = ref(2)
const uppercase = ref(true)
const lineBreakBeforeAnd = ref(true)

// SQL keywords
const keywords = [
  'SELECT', 'FROM', 'WHERE', 'AND', 'OR', 'NOT', 'IN', 'LIKE', 'BETWEEN',
  'JOIN', 'INNER JOIN', 'LEFT JOIN', 'RIGHT JOIN', 'FULL JOIN', 'CROSS JOIN',
  'ON', 'AS', 'ORDER BY', 'GROUP BY', 'HAVING', 'LIMIT', 'OFFSET',
  'INSERT INTO', 'VALUES', 'UPDATE', 'SET', 'DELETE FROM',
  'CREATE TABLE', 'ALTER TABLE', 'DROP TABLE', 'TRUNCATE TABLE',
  'CREATE INDEX', 'DROP INDEX', 'CREATE VIEW', 'DROP VIEW',
  'UNION', 'UNION ALL', 'EXCEPT', 'INTERSECT',
  'CASE', 'WHEN', 'THEN', 'ELSE', 'END',
  'NULL', 'IS NULL', 'IS NOT NULL', 'TRUE', 'FALSE',
  'ASC', 'DESC', 'DISTINCT', 'ALL', 'EXISTS',
  'COUNT', 'SUM', 'AVG', 'MIN', 'MAX',
  'PRIMARY KEY', 'FOREIGN KEY', 'REFERENCES', 'UNIQUE', 'DEFAULT',
  'INT', 'INTEGER', 'VARCHAR', 'TEXT', 'BOOLEAN', 'DATE', 'DATETIME', 'TIMESTAMP',
  'BEGIN', 'COMMIT', 'ROLLBACK', 'TRANSACTION'
]

const mainClauses = ['SELECT', 'FROM', 'WHERE', 'JOIN', 'INNER JOIN', 'LEFT JOIN', 'RIGHT JOIN', 'FULL JOIN', 'ON', 'ORDER BY', 'GROUP BY', 'HAVING', 'LIMIT', 'OFFSET', 'INSERT INTO', 'VALUES', 'UPDATE', 'SET', 'DELETE FROM', 'UNION', 'UNION ALL']

function formatSQL(sql) {
  if (!sql.trim()) return ''

  let formatted = sql.trim()

  // Normalize whitespace
  formatted = formatted.replace(/\s+/g, ' ')

  // Handle keywords case
  if (uppercase.value) {
    keywords.forEach(kw => {
      const regex = new RegExp(`\\b${kw.replace(/\s+/g, '\\s+')}\\b`, 'gi')
      formatted = formatted.replace(regex, kw.toUpperCase())
    })
  } else {
    keywords.forEach(kw => {
      const regex = new RegExp(`\\b${kw.replace(/\s+/g, '\\s+')}\\b`, 'gi')
      formatted = formatted.replace(regex, kw.toLowerCase())
    })
  }

  // Add line breaks before main clauses
  const indent = ' '.repeat(indentSize.value)

  mainClauses.forEach(clause => {
    const upperClause = uppercase.value ? clause : clause.toLowerCase()
    const regex = new RegExp(`\\s+${upperClause}\\b`, 'gi')
    formatted = formatted.replace(regex, `\n${upperClause}`)
  })

  // Handle AND/OR
  if (lineBreakBeforeAnd.value) {
    const andOr = uppercase.value ? ['AND', 'OR'] : ['and', 'or']
    andOr.forEach(op => {
      const regex = new RegExp(`\\s+${op}\\b`, 'gi')
      formatted = formatted.replace(regex, `\n${indent}${op}`)
    })
  }

  // Handle commas in SELECT
  formatted = formatted.replace(/,\s*/g, ',\n' + indent)

  // Clean up multiple newlines
  formatted = formatted.replace(/\n\s*\n/g, '\n')

  // Indent after main clauses
  const lines = formatted.split('\n')
  const result = []
  let currentIndent = 0

  for (let line of lines) {
    line = line.trim()
    if (!line) continue

    // Check if line starts with main clause
    const startsWithClause = mainClauses.some(c => {
      const check = uppercase.value ? c : c.toLowerCase()
      return line.toUpperCase().startsWith(c)
    })

    if (startsWithClause) {
      result.push(line)
    } else {
      result.push(indent + line)
    }
  }

  return result.join('\n')
}

const output = computed(() => formatSQL(input.value))

function copyOutput() {
  navigator.clipboard.writeText(output.value)
}

function minify() {
  input.value = input.value.replace(/\s+/g, ' ').trim()
}

function setExample(type) {
  switch (type) {
    case 'select':
      input.value = `SELECT u.id, u.name, u.email, COUNT(o.id) as order_count FROM users u LEFT JOIN orders o ON u.id = o.user_id WHERE u.status = 'active' AND u.created_at > '2024-01-01' GROUP BY u.id HAVING COUNT(o.id) > 5 ORDER BY order_count DESC LIMIT 10`
      break
    case 'insert':
      input.value = `INSERT INTO users (name, email, status, created_at) VALUES ('Max Mustermann', 'max@example.com', 'active', NOW())`
      break
    case 'update':
      input.value = `UPDATE users SET status = 'inactive', updated_at = NOW() WHERE last_login < DATE_SUB(NOW(), INTERVAL 1 YEAR) AND status = 'active'`
      break
    case 'create':
      input.value = `CREATE TABLE products (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(255) NOT NULL, price DECIMAL(10,2) DEFAULT 0.00, category_id INT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (category_id) REFERENCES categories(id))`
      break
  }
}
</script>

<template>
  <div class="space-y-4">
    <!-- Options -->
    <div class="flex flex-wrap gap-4">
      <label class="flex items-center gap-2 text-sm text-gray-300">
        <input type="checkbox" v-model="uppercase" class="rounded bg-white/[0.08]" />
        Großbuchstaben
      </label>
      <label class="flex items-center gap-2 text-sm text-gray-300">
        <input type="checkbox" v-model="lineBreakBeforeAnd" class="rounded bg-white/[0.08]" />
        AND/OR in neuer Zeile
      </label>
      <div class="flex items-center gap-2">
        <span class="text-sm text-gray-400">Einrückung:</span>
        <select v-model.number="indentSize" class="input py-1 px-2 w-16">
          <option :value="2">2</option>
          <option :value="4">4</option>
        </select>
      </div>
    </div>

    <!-- Examples -->
    <div class="flex flex-wrap gap-2">
      <span class="text-xs text-gray-500">Beispiele:</span>
      <button @click="setExample('select')" class="text-xs text-primary-400 hover:text-primary-300">SELECT</button>
      <button @click="setExample('insert')" class="text-xs text-primary-400 hover:text-primary-300">INSERT</button>
      <button @click="setExample('update')" class="text-xs text-primary-400 hover:text-primary-300">UPDATE</button>
      <button @click="setExample('create')" class="text-xs text-primary-400 hover:text-primary-300">CREATE</button>
    </div>

    <!-- Input -->
    <div>
      <div class="flex items-center justify-between mb-1">
        <label class="text-xs text-gray-400">SQL Eingabe</label>
        <button @click="minify" class="text-xs text-gray-500 hover:text-white">Minifizieren</button>
      </div>
      <textarea
        v-model="input"
        class="input w-full h-32 font-mono text-sm resize-none"
        placeholder="SELECT * FROM users WHERE id = 1"
      ></textarea>
    </div>

    <!-- Output -->
    <div v-if="output">
      <div class="flex items-center justify-between mb-1">
        <label class="text-xs text-gray-400">Formatiert</label>
        <button @click="copyOutput" class="text-xs text-primary-400 hover:text-primary-300">Kopieren</button>
      </div>
      <pre class="p-3 bg-white/[0.02] rounded-lg text-sm font-mono max-h-64 overflow-auto"><code class="text-blue-400">{{ output }}</code></pre>
    </div>

    <!-- Keyword Reference -->
    <details class="text-xs">
      <summary class="text-gray-500 cursor-pointer hover:text-gray-400">SQL Keywords Referenz</summary>
      <div class="mt-2 p-2 bg-white/[0.04] rounded-lg text-gray-400 flex flex-wrap gap-1">
        <span v-for="kw in keywords.slice(0, 30)" :key="kw" class="px-1 bg-white/[0.08] rounded">
          {{ uppercase ? kw : kw.toLowerCase() }}
        </span>
        <span class="text-gray-600">...</span>
      </div>
    </details>
  </div>
</template>
