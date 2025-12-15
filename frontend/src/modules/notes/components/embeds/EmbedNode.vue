<script setup>
import { ref, computed, onMounted } from 'vue'
import { NodeViewWrapper } from '@tiptap/vue-3'
import {
  PlayIcon,
  ArrowTopRightOnSquareIcon,
  TrashIcon,
  ArrowsPointingOutIcon,
  XMarkIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
  node: {
    type: Object,
    required: true
  },
  updateAttributes: {
    type: Function,
    required: true
  },
  deleteNode: {
    type: Function,
    required: true
  },
  selected: {
    type: Boolean,
    default: false
  }
})

const isLoaded = ref(false)
const isExpanded = ref(false)
const showControls = ref(false)
const iframeRef = ref(null)

// Provider icons
const providerIcons = {
  youtube: '/icons/youtube.svg',
  vimeo: '/icons/vimeo.svg',
  twitter: '/icons/twitter.svg',
  spotify: '/icons/spotify.svg',
  figma: '/icons/figma.svg',
  codepen: '/icons/codepen.svg',
  loom: '/icons/loom.svg',
  miro: '/icons/miro.svg',
  googleMaps: '/icons/google-maps.svg',
}

// Provider colors
const providerColors = {
  youtube: '#FF0000',
  vimeo: '#1AB7EA',
  twitter: '#1DA1F2',
  spotify: '#1DB954',
  figma: '#F24E1E',
  codepen: '#000000',
  loom: '#625DF5',
  miro: '#FFD02F',
  googleMaps: '#4285F4',
  generic: '#6366F1',
}

// Computed
const embedUrl = computed(() => props.node.attrs.embedUrl || props.node.attrs.src)
const provider = computed(() => props.node.attrs.provider || 'generic')
const title = computed(() => props.node.attrs.title || 'Embed')
const aspectRatio = computed(() => props.node.attrs.aspectRatio || '16/9')
const fixedHeight = computed(() => props.node.attrs.height)
const providerColor = computed(() => providerColors[provider.value] || providerColors.generic)

// Container style
const containerStyle = computed(() => {
  if (fixedHeight.value) {
    return { height: fixedHeight.value }
  }
  return { aspectRatio: aspectRatio.value }
})

// Load embed
function loadEmbed() {
  isLoaded.value = true
}

// Open in new tab
function openExternal() {
  if (embedUrl.value) {
    window.open(embedUrl.value, '_blank')
  }
}

// Toggle fullscreen
function toggleExpanded() {
  isExpanded.value = !isExpanded.value
}

// Handle iframe load
function onIframeLoad() {
  // Could add additional handling here
}
</script>

<template>
  <NodeViewWrapper
    as="div"
    :class="[
      'embed-node my-4 rounded-lg overflow-hidden border border-dark-600',
      selected ? 'ring-2 ring-primary-500' : '',
      isExpanded ? 'fixed inset-4 z-50' : ''
    ]"
    @mouseenter="showControls = true"
    @mouseleave="showControls = false"
  >
    <!-- Header -->
    <div
      class="embed-header flex items-center justify-between px-3 py-2 bg-dark-700 border-b border-dark-600"
      :style="{ borderLeftColor: providerColor, borderLeftWidth: '3px' }"
    >
      <div class="flex items-center gap-2">
        <span class="text-sm font-medium text-white">{{ title }}</span>
        <span class="text-xs text-gray-500">{{ provider }}</span>
      </div>

      <div class="flex items-center gap-1">
        <button
          @click="openExternal"
          class="p-1.5 text-gray-400 hover:text-white hover:bg-dark-600 rounded"
          title="In neuem Tab öffnen"
        >
          <ArrowTopRightOnSquareIcon class="w-4 h-4" />
        </button>
        <button
          @click="toggleExpanded"
          class="p-1.5 text-gray-400 hover:text-white hover:bg-dark-600 rounded"
          title="Vollbild"
        >
          <ArrowsPointingOutIcon class="w-4 h-4" />
        </button>
        <button
          @click="deleteNode"
          class="p-1.5 text-gray-400 hover:text-red-400 hover:bg-dark-600 rounded"
          title="Entfernen"
        >
          <TrashIcon class="w-4 h-4" />
        </button>
      </div>
    </div>

    <!-- Content -->
    <div
      class="embed-content relative bg-dark-800"
      :style="containerStyle"
    >
      <!-- Placeholder (before load) -->
      <div
        v-if="!isLoaded"
        class="absolute inset-0 flex flex-col items-center justify-center cursor-pointer hover:bg-dark-700/50 transition-colors"
        @click="loadEmbed"
      >
        <div
          class="w-16 h-16 rounded-full flex items-center justify-center mb-3"
          :style="{ backgroundColor: providerColor + '20' }"
        >
          <PlayIcon class="w-8 h-8" :style="{ color: providerColor }" />
        </div>
        <p class="text-sm text-gray-400">Klicken zum Laden</p>
        <p class="text-xs text-gray-500 mt-1 max-w-xs truncate px-4">{{ node.attrs.src }}</p>
      </div>

      <!-- Iframe (after load) -->
      <iframe
        v-else
        ref="iframeRef"
        :src="embedUrl"
        class="absolute inset-0 w-full h-full"
        frameborder="0"
        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
        allowfullscreen
        @load="onIframeLoad"
      />
    </div>

    <!-- Expanded overlay -->
    <Teleport to="body">
      <div
        v-if="isExpanded"
        class="fixed inset-0 bg-black/80 z-40"
        @click="isExpanded = false"
      />
    </Teleport>

    <!-- Close button when expanded -->
    <button
      v-if="isExpanded"
      @click="isExpanded = false"
      class="fixed top-6 right-6 z-50 p-2 bg-dark-700 rounded-full text-white hover:bg-dark-600"
    >
      <XMarkIcon class="w-6 h-6" />
    </button>
  </NodeViewWrapper>
</template>

<style scoped>
.embed-node {
  transition: all 0.2s ease;
}

.embed-node:hover {
  border-color: rgb(75, 85, 99);
}

.embed-content {
  min-height: 200px;
}
</style>
