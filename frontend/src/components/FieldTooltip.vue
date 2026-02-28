<script setup>
import { ref, nextTick } from 'vue'
import { InformationCircleIcon } from '@heroicons/vue/24/outline'

const triggerRef = ref(null)
const tooltipRef = ref(null)
const show = ref(false)
const pos = ref({ top: 0, left: 0 })

async function onEnter() {
  show.value = true
  await nextTick()
  const el = triggerRef.value
  const tip = tooltipRef.value
  if (!el || !tip) return
  const rect = el.getBoundingClientRect()
  const tipRect = tip.getBoundingClientRect()
  // Default: above the icon
  let top = rect.top - tipRect.height - 8
  // If no room above, place below
  if (top < 4) top = rect.bottom + 8
  // Center horizontally on icon
  let left = rect.left + rect.width / 2 - tipRect.width / 2
  // Clamp to viewport edges
  if (left < 4) left = 4
  if (left + tipRect.width > window.innerWidth - 4) left = window.innerWidth - tipRect.width - 4
  pos.value = { top, left }
}

function onLeave() {
  show.value = false
}
</script>

<template>
  <span
    ref="triggerRef"
    class="inline-flex"
    @mouseenter="onEnter"
    @mouseleave="onLeave"
    @click.prevent
  >
    <InformationCircleIcon class="w-3.5 h-3.5 text-gray-500 hover:text-gray-300 cursor-help transition-colors" />
    <Teleport to="body">
      <div
        v-if="show"
        ref="tooltipRef"
        class="fixed z-[9999] px-3 py-2 text-xs font-medium text-gray-200
               bg-dark-900/95 backdrop-blur-lg border border-white/[0.12]
               rounded-lg shadow-lg pointer-events-none
               whitespace-normal w-64 leading-relaxed"
        :style="{ top: pos.top + 'px', left: pos.left + 'px' }"
      >
        <slot />
      </div>
    </Teleport>
  </span>
</template>
