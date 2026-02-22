<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ChevronRightIcon } from '@heroicons/vue/24/outline'

const route = useRoute()
const router = useRouter()

// Flat navigation map: href -> { name, group }
const navMap = [
  { href: '/', name: 'Dashboard', group: null },
  { href: '/news', name: 'News', group: null },
  // Inhalte
  { href: '/lists', name: 'Listen', group: 'Inhalte' },
  { href: '/documents', name: 'Dokumente', group: 'Inhalte' },
  { href: '/notes', name: 'Notes', group: 'Inhalte' },
  { href: '/snippets', name: 'Snippets', group: 'Inhalte' },
  { href: '/bookmarks', name: 'Bookmarks', group: 'Inhalte' },
  // KyuubiCloud
  { href: '/storage', name: 'Cloud Storage', group: 'KyuubiCloud' },
  { href: '/storage/shares', name: 'Freigaben', group: 'KyuubiCloud' },
  { href: '/checklists', name: 'Checklisten', group: 'KyuubiCloud' },
  { href: '/links', name: 'Short Links', group: 'KyuubiCloud' },
  { href: '/galleries', name: 'Galerien', group: 'KyuubiCloud' },
  { href: '/mockup-editor', name: 'Mockup Editor', group: 'KyuubiCloud' },
  // Projektmanagement
  { href: '/kanban', name: 'Kanban', group: 'Projektmanagement' },
  { href: '/projects', name: 'Projekte', group: 'Projektmanagement' },
  { href: '/calendar', name: 'Kalender', group: 'Projektmanagement' },
  { href: '/time', name: 'Zeiterfassung', group: 'Projektmanagement' },
  { href: '/recurring-tasks', name: 'Wiederkehrend', group: 'Projektmanagement' },
  // Entwicklung & Tools
  { href: '/connections', name: 'Verbindungen', group: 'Entwicklung & Tools' },
  { href: '/server', name: 'Server', group: 'Entwicklung & Tools' },
  { href: '/git', name: 'Git Repos', group: 'Entwicklung & Tools' },
  { href: '/webhooks', name: 'Webhooks', group: 'Entwicklung & Tools' },
  { href: '/uptime', name: 'Uptime Monitor', group: 'Entwicklung & Tools' },
  { href: '/ssl', name: 'SSL Zertifikate', group: 'Entwicklung & Tools' },
  { href: '/toolbox', name: 'Toolbox', group: 'Entwicklung & Tools' },
  { href: '/workflows', name: 'Workflows', group: 'Entwicklung & Tools' },
  // Docker
  { href: '/docker', name: 'Container Manager', group: 'Docker' },
  { href: '/docker/hosts', name: 'Docker Hosts', group: 'Docker' },
  { href: '/docker/dockerfile', name: 'Dockerfile Generator', group: 'Docker' },
  { href: '/docker/compose', name: 'Compose Builder', group: 'Docker' },
  { href: '/docker/command', name: 'Command Builder', group: 'Docker' },
  { href: '/docker/ignore', name: '.dockerignore', group: 'Docker' },
  // Support
  { href: '/tickets', name: 'Tickets', group: 'Support' },
  { href: '/tickets/categories', name: 'Kategorien', group: 'Support' },
  // Business
  { href: '/invoices', name: 'Rechnungen', group: 'Business' },
  // Discord
  { href: '/discord', name: 'Discord Manager', group: null },
  // Administration
  { href: '/passwords', name: 'Passwörter', group: 'Administration' },
  { href: '/backups', name: 'Backups', group: 'Administration' },
  { href: '/settings', name: 'Einstellungen', group: 'Administration' },
  { href: '/users', name: 'Benutzer', group: 'Administration' },
  { href: '/roles', name: 'Rollen', group: 'Administration' },
  { href: '/system', name: 'System', group: 'Administration' },
  // Wiki / Inbox / Chat
  { href: '/wiki', name: 'Wiki', group: null },
  { href: '/inbox', name: 'Inbox', group: null },
  { href: '/chat', name: 'Team Chat', group: null },
]

const breadcrumbs = computed(() => {
  const currentPath = route.path

  // Dashboard has no breadcrumbs
  if (currentPath === '/') return []

  // Find the best matching nav item (longest prefix match)
  let best = null
  for (const item of navMap) {
    if (item.href === '/') continue
    if (
      currentPath === item.href ||
      currentPath.startsWith(item.href + '/')
    ) {
      if (!best || item.href.length > best.href.length) {
        best = item
      }
    }
  }

  if (!best) return []

  const crumbs = []

  // Add group if present
  if (best.group) {
    crumbs.push({ name: best.group, href: null, isGroup: true })
  }

  // Add the page
  crumbs.push({ name: best.name, href: best.href, isGroup: false })

  // Add sub-detail if on a deeper route (e.g. /tickets/123)
  if (currentPath !== best.href) {
    // Check if the extra segment looks like an ID (numeric)
    const extra = currentPath.slice(best.href.length).replace(/^\//, '')
    if (extra && !extra.includes('/')) {
      // Only show "Details" for ID-looking segments, skip for named sub-routes
      if (/^\d+$/.test(extra)) {
        crumbs.push({ name: '#' + extra, href: null, isGroup: false })
      }
    }
  }

  return crumbs
})

function navigate(href) {
  if (href) router.push(href)
}
</script>

<template>
  <nav
    v-if="breadcrumbs.length > 0"
    class="flex items-center gap-1 text-sm min-w-0"
    aria-label="Breadcrumb"
  >
    <template v-for="(crumb, index) in breadcrumbs" :key="index">
      <ChevronRightIcon
        v-if="index > 0"
        class="w-3.5 h-3.5 text-dark-500 shrink-0"
      />
      <button
        v-if="crumb.href && index < breadcrumbs.length - 1"
        @click="navigate(crumb.href)"
        class="text-gray-500 hover:text-gray-300 transition-colors duration-150 truncate max-w-[120px]"
        :class="crumb.isGroup ? 'text-gray-600' : ''"
      >
        {{ crumb.name }}
      </button>
      <span
        v-else
        class="truncate max-w-[160px] font-medium"
        :class="index === breadcrumbs.length - 1 ? 'text-gray-300' : crumb.isGroup ? 'text-gray-600' : 'text-gray-500'"
      >
        {{ crumb.name }}
      </span>
    </template>
  </nav>
</template>
