<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ChevronRightIcon } from '@heroicons/vue/24/outline'
import { getAllNavItems, findGroupByHref } from '@/core/config/navigation'

const route = useRoute()
const router = useRouter()

const breadcrumbs = computed(() => {
  const currentPath = route.path

  if (currentPath === '/') return []

  // Find the best matching nav item (longest prefix match)
  let best = null
  for (const item of getAllNavItems()) {
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

  // Add group name if item belongs to a group
  const group = findGroupByHref(best.href)
  if (group && group.children) {
    crumbs.push({ name: group.name, href: null, isGroup: true })
  }

  // Add the page
  crumbs.push({ name: best.name, href: best.href, isGroup: false })

  // Add sub-detail if on a deeper route (e.g. /tickets/123)
  if (currentPath !== best.href) {
    const extra = currentPath.slice(best.href.length).replace(/^\//, '')
    if (extra && !extra.includes('/')) {
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
