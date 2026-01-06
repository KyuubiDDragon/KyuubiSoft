<script setup>
import { ref, computed, onMounted, nextTick } from 'vue'
import { useMockupStore } from '../stores/mockupStore'
import { useToast } from '@/composables/useToast'
import MockupCanvas from '../components/MockupCanvas.vue'
import TemplateSelector from '../components/TemplateSelector.vue'
import ElementProperties from '../components/ElementProperties.vue'
import ExportModal from '../components/ExportModal.vue'
import {
  ArrowDownTrayIcon,
  ArrowPathIcon,
  MagnifyingGlassPlusIcon,
  MagnifyingGlassMinusIcon,
  Squares2X2Icon,
  XMarkIcon,
  AdjustmentsHorizontalIcon,
  PhotoIcon,
} from '@heroicons/vue/24/outline'

const toast = useToast()
const mockupStore = useMockupStore()

// State
const showTemplateSelector = ref(true)
const showProperties = ref(true)
const showExportModal = ref(false)
const canvasRef = ref(null)

// Computed
const hasTemplate = computed(() => !!mockupStore.currentTemplate)
const zoomPercent = computed(() => Math.round(mockupStore.zoom * 100))

// Methods
const selectTemplate = (templateId) => {
  mockupStore.selectTemplate(templateId)
  showTemplateSelector.value = false
}

const handleZoomIn = () => {
  mockupStore.setZoom(mockupStore.zoom + 0.1)
}

const handleZoomOut = () => {
  mockupStore.setZoom(mockupStore.zoom - 0.1)
}

const handleZoomReset = () => {
  mockupStore.setZoom(1)
}

const handleReset = () => {
  mockupStore.resetMockup()
  toast.success('Mockup zurückgesetzt')
}

const handleNewMockup = () => {
  mockupStore.clearMockup()
  showTemplateSelector.value = true
}

const handleExport = () => {
  showExportModal.value = true
}

const performExport = async (options) => {
  if (!canvasRef.value) return

  try {
    await canvasRef.value.exportImage(options)
    toast.success('Export erfolgreich!')
    showExportModal.value = false
  } catch (error) {
    console.error('Export failed:', error)
    toast.error('Export fehlgeschlagen')
  }
}
</script>

<template>
  <div class="h-[calc(100vh-64px)] flex flex-col bg-gray-900">
    <!-- Top Toolbar -->
    <div class="flex items-center justify-between px-4 py-2 bg-gray-800 border-b border-gray-700">
      <div class="flex items-center gap-4">
        <h1 class="text-lg font-semibold text-white flex items-center gap-2">
          <PhotoIcon class="w-5 h-5 text-amber-400" />
          Tebex Mockup Editor
        </h1>

        <div v-if="hasTemplate" class="flex items-center gap-2 ml-4">
          <span class="text-gray-400 text-sm">Template:</span>
          <span class="text-white text-sm font-medium">{{ mockupStore.currentTemplate?.name }}</span>
        </div>
      </div>

      <div class="flex items-center gap-2">
        <!-- Zoom Controls -->
        <div v-if="hasTemplate" class="flex items-center gap-1 bg-gray-700 rounded-lg px-2 py-1">
          <button @click="handleZoomOut" class="p-1 text-gray-400 hover:text-white transition-colors">
            <MagnifyingGlassMinusIcon class="w-4 h-4" />
          </button>
          <span class="text-gray-300 text-sm min-w-[48px] text-center">{{ zoomPercent }}%</span>
          <button @click="handleZoomIn" class="p-1 text-gray-400 hover:text-white transition-colors">
            <MagnifyingGlassPlusIcon class="w-4 h-4" />
          </button>
          <button @click="handleZoomReset" class="p-1 text-gray-400 hover:text-white transition-colors ml-1" title="Zoom zurücksetzen">
            <span class="text-xs">100%</span>
          </button>
        </div>

        <!-- Actions -->
        <div v-if="hasTemplate" class="flex items-center gap-2 ml-4">
          <button @click="handleReset"
                  class="flex items-center gap-1.5 px-3 py-1.5 text-gray-300 hover:text-white hover:bg-gray-700 rounded-lg transition-colors">
            <ArrowPathIcon class="w-4 h-4" />
            <span class="text-sm">Zurücksetzen</span>
          </button>

          <button @click="showTemplateSelector = true"
                  class="flex items-center gap-1.5 px-3 py-1.5 text-gray-300 hover:text-white hover:bg-gray-700 rounded-lg transition-colors">
            <Squares2X2Icon class="w-4 h-4" />
            <span class="text-sm">Templates</span>
          </button>

          <button @click="showProperties = !showProperties"
                  class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg transition-colors"
                  :class="showProperties ? 'bg-amber-500/20 text-amber-400' : 'text-gray-300 hover:text-white hover:bg-gray-700'">
            <AdjustmentsHorizontalIcon class="w-4 h-4" />
            <span class="text-sm">Eigenschaften</span>
          </button>

          <button @click="handleExport"
                  class="flex items-center gap-1.5 px-4 py-1.5 bg-amber-500 hover:bg-amber-600 text-gray-900 font-medium rounded-lg transition-colors">
            <ArrowDownTrayIcon class="w-4 h-4" />
            <span class="text-sm">Exportieren</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Main Content -->
    <div class="flex-1 flex overflow-hidden">
      <!-- Template Selector Sidebar -->
      <Transition name="slide-left">
        <div v-if="showTemplateSelector" class="w-80 bg-gray-800 border-r border-gray-700 flex flex-col overflow-hidden">
          <div class="flex items-center justify-between px-4 py-3 border-b border-gray-700">
            <h2 class="font-semibold text-white">Templates</h2>
            <button v-if="hasTemplate" @click="showTemplateSelector = false" class="text-gray-400 hover:text-white">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>
          <div class="flex-1 overflow-y-auto">
            <TemplateSelector @select="selectTemplate" />
          </div>
        </div>
      </Transition>

      <!-- Canvas Area -->
      <div class="flex-1 overflow-auto bg-gray-900 flex items-center justify-center p-8">
        <template v-if="hasTemplate">
          <MockupCanvas ref="canvasRef" />
        </template>
        <template v-else>
          <div class="text-center">
            <PhotoIcon class="w-16 h-16 mx-auto text-gray-600" />
            <h2 class="mt-4 text-xl font-semibold text-gray-400">Wähle ein Template</h2>
            <p class="mt-2 text-gray-500">Wähle ein Template aus der Liste, um zu beginnen.</p>
          </div>
        </template>
      </div>

      <!-- Properties Panel -->
      <Transition name="slide-right">
        <div v-if="showProperties && hasTemplate" class="w-80 bg-gray-800 border-l border-gray-700 flex flex-col overflow-hidden">
          <div class="flex items-center justify-between px-4 py-3 border-b border-gray-700">
            <h2 class="font-semibold text-white">Eigenschaften</h2>
            <button @click="showProperties = false" class="text-gray-400 hover:text-white">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>
          <div class="flex-1 overflow-y-auto">
            <ElementProperties />
          </div>
        </div>
      </Transition>
    </div>

    <!-- Export Modal -->
    <ExportModal
      :show="showExportModal"
      @close="showExportModal = false"
      @export="performExport"
    />
  </div>
</template>

<style scoped>
.slide-left-enter-active,
.slide-left-leave-active,
.slide-right-enter-active,
.slide-right-leave-active {
  transition: all 0.2s ease;
}

.slide-left-enter-from,
.slide-left-leave-to {
  transform: translateX(-100%);
  opacity: 0;
}

.slide-right-enter-from,
.slide-right-leave-to {
  transform: translateX(100%);
  opacity: 0;
}
</style>
