<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue'
import { useMockupStore } from '../stores/mockupStore'
import { useToast } from '@/composables/useToast'
import MockupCanvas from '../components/MockupCanvas.vue'
import TemplateSelector from '../components/TemplateSelector.vue'
import ElementProperties from '../components/ElementProperties.vue'
import ExportModal from '../components/ExportModal.vue'
import ImageAnnotationEditor from '../components/ImageAnnotationEditor.vue'
import {
  ArrowDownTrayIcon,
  ArrowPathIcon,
  MagnifyingGlassPlusIcon,
  MagnifyingGlassMinusIcon,
  Squares2X2Icon,
  XMarkIcon,
  AdjustmentsHorizontalIcon,
  PhotoIcon,
  PlusIcon,
  DocumentTextIcon,
  MinusIcon,
  Square2StackIcon,
  CursorArrowRaysIcon,
  TagIcon,
  ComputerDesktopIcon,
  DocumentDuplicateIcon,
  TrashIcon,
  BookmarkIcon,
  CloudArrowUpIcon,
  FolderIcon,
  Bars3Icon,
  Bars2Icon,
  StopIcon,
} from '@heroicons/vue/24/outline'

const toast = useToast()
const mockupStore = useMockupStore()

// State
const showTemplateSelector = ref(true)
const showProperties = ref(true)
const showExportModal = ref(false)
const showAddElementMenu = ref(false)
const showSaveMenu = ref(false)
const showSaveTemplateModal = ref(false)
const saveTemplateName = ref('')
const saveTemplateDescription = ref('')
const canvasRef = ref(null)
const canvasContainerRef = ref(null)
const showAnnotationEditor = ref(false)
const annotationElementId = ref(null)

// Auto-fit zoom based on available space
const fitCanvasToView = () => {
  if (!hasTemplate.value || !canvasContainerRef.value) return

  // Wait for DOM to settle after panel transitions
  nextTick(() => {
    const container = canvasContainerRef.value
    if (!container) return

    // Get available space (subtract padding: p-8 = 32px * 2 = 64px each direction)
    const availableWidth = container.clientWidth - 64
    const availableHeight = container.clientHeight - 64

    // Get canvas dimensions
    const canvasWidth = mockupStore.canvasWidth
    const canvasHeight = mockupStore.canvasHeight

    // Calculate zoom to fit
    const zoomX = availableWidth / canvasWidth
    const zoomY = availableHeight / canvasHeight
    const optimalZoom = Math.min(zoomX, zoomY, 1) // Max zoom of 1 (100%)

    // Apply zoom with small margin (95% of calculated to leave some breathing room)
    mockupStore.setZoom(Math.max(0.1, optimalZoom * 0.95))
  })
}

// Watch for sidebar changes and refit canvas
watch([showTemplateSelector, showProperties], () => {
  // Delay to wait for CSS transition (200ms animation)
  setTimeout(fitCanvasToView, 250)
}, { flush: 'post' })

// Element types available for creation
const elementTypes = [
  { type: 'text', label: 'Text', icon: DocumentTextIcon, description: 'Textfeld hinzufügen' },
  { type: 'image', label: 'Bild', icon: PhotoIcon, description: 'Bildplatzhalter hinzufügen' },
  { type: 'line', label: 'Linie', icon: MinusIcon, description: 'Dekorative Linie' },
  { type: 'button', label: 'Button', icon: CursorArrowRaysIcon, description: 'Call-to-Action Button' },
  { type: 'chip', label: 'Tag/Chip', icon: TagIcon, description: 'Feature-Tag Badge' },
  { type: 'container', label: 'Container', icon: Square2StackIcon, description: 'Box/Container Element' },
  { type: 'screen3d', label: '3D Screen', icon: ComputerDesktopIcon, description: 'Screenshot mit 3D-Effekt' },
]

const handleAddElement = (type) => {
  mockupStore.addElement(type)
  showAddElementMenu.value = false
  showProperties.value = true
  toast.success('Element hinzugefügt')
}

const handleDuplicateElement = () => {
  if (!mockupStore.selectedElementId) return
  mockupStore.duplicateElement(mockupStore.selectedElementId)
  toast.success('Element dupliziert')
}

const handleDeleteElement = () => {
  if (!mockupStore.selectedElementId) return
  mockupStore.deleteElement(mockupStore.selectedElementId)
  toast.success('Element gelöscht')
}

// Alignment
const handleCenterHorizontally = () => {
  if (!mockupStore.selectedElementId) return
  mockupStore.centerElementHorizontally(mockupStore.selectedElementId)
}

const handleCenterVertically = () => {
  if (!mockupStore.selectedElementId) return
  mockupStore.centerElementVertically(mockupStore.selectedElementId)
}

const handleCenterBoth = () => {
  if (!mockupStore.selectedElementId) return
  mockupStore.centerElement(mockupStore.selectedElementId)
}

// Save as template
const handleSaveAsTemplate = () => {
  saveTemplateName.value = mockupStore.currentTemplate?.name || 'Mein Template'
  saveTemplateDescription.value = ''
  showSaveTemplateModal.value = true
  showSaveMenu.value = false
}

const confirmSaveTemplate = async () => {
  if (!saveTemplateName.value.trim()) {
    toast.error('Bitte gib einen Namen ein')
    return
  }

  try {
    await mockupStore.saveAsTemplate(
      saveTemplateName.value.trim(),
      saveTemplateDescription.value.trim()
    )
    toast.success('Template gespeichert!')
    showSaveTemplateModal.value = false
  } catch (err) {
    toast.error('Speichern fehlgeschlagen')
  }
}

// Save as draft
const handleSaveDraft = async () => {
  showSaveMenu.value = false
  try {
    await mockupStore.saveDraft()
    toast.success('Entwurf gespeichert!')
  } catch (err) {
    toast.error('Speichern fehlgeschlagen')
  }
}

// Computed
const hasTemplate = computed(() => !!mockupStore.currentTemplate)
const zoomPercent = computed(() => Math.round(mockupStore.zoom * 100))

// Methods
const selectTemplate = (templateId) => {
  mockupStore.selectTemplate(templateId)
  showTemplateSelector.value = false
  // Auto-fit canvas to view after template is loaded
  setTimeout(fitCanvasToView, 300)
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

const handleEditImage = (elementId) => {
  annotationElementId.value = elementId
  showAnnotationEditor.value = true
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

// Close dropdown when clicking outside
const handleClickOutside = (e) => {
  if (showAddElementMenu.value && !e.target.closest('.add-element-menu')) {
    showAddElementMenu.value = false
  }
  if (showSaveMenu.value && !e.target.closest('.save-menu')) {
    showSaveMenu.value = false
  }
}

// ResizeObserver for responsive canvas fitting
let resizeObserver = null
let resizeTimeout = null

onMounted(() => {
  document.addEventListener('click', handleClickOutside)

  // Set up ResizeObserver to auto-fit when container size changes (debounced)
  nextTick(() => {
    if (canvasContainerRef.value) {
      resizeObserver = new ResizeObserver(() => {
        // Debounce resize events
        if (resizeTimeout) clearTimeout(resizeTimeout)
        resizeTimeout = setTimeout(() => {
          if (hasTemplate.value) {
            fitCanvasToView()
          }
        }, 100)
      })
      resizeObserver.observe(canvasContainerRef.value)
    }
  })
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)

  // Clean up timeout
  if (resizeTimeout) {
    clearTimeout(resizeTimeout)
    resizeTimeout = null
  }

  // Clean up ResizeObserver
  if (resizeObserver) {
    resizeObserver.disconnect()
    resizeObserver = null
  }
})
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
          <button @click="handleZoomOut" class="p-1 text-gray-400 hover:text-white transition-colors" title="Verkleinern">
            <MagnifyingGlassMinusIcon class="w-4 h-4" />
          </button>
          <span class="text-gray-300 text-sm min-w-[48px] text-center">{{ zoomPercent }}%</span>
          <button @click="handleZoomIn" class="p-1 text-gray-400 hover:text-white transition-colors" title="Vergrössern">
            <MagnifyingGlassPlusIcon class="w-4 h-4" />
          </button>
          <div class="w-px h-4 bg-gray-600 mx-1" />
          <button @click="fitCanvasToView" class="p-1 text-gray-400 hover:text-white transition-colors" title="An Fenster anpassen">
            <span class="text-xs">Fit</span>
          </button>
          <button @click="handleZoomReset" class="p-1 text-gray-400 hover:text-white transition-colors" title="Zoom zurücksetzen">
            <span class="text-xs">100%</span>
          </button>
        </div>

        <!-- Actions -->
        <div v-if="hasTemplate" class="flex items-center gap-2 ml-4">
          <!-- Add Element Dropdown -->
          <div class="relative add-element-menu">
            <button @click="showAddElementMenu = !showAddElementMenu"
                    class="flex items-center gap-1.5 px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors">
              <PlusIcon class="w-4 h-4" />
              <span class="text-sm">Element</span>
            </button>

            <!-- Dropdown Menu -->
            <Transition name="fade">
              <div v-if="showAddElementMenu"
                   class="absolute top-full left-0 mt-2 w-56 bg-gray-800 border border-gray-700 rounded-lg shadow-xl z-50 overflow-hidden">
                <div class="py-1">
                  <button
                    v-for="item in elementTypes"
                    :key="item.type"
                    @click="handleAddElement(item.type)"
                    class="w-full px-4 py-2.5 flex items-center gap-3 text-left hover:bg-gray-700 transition-colors"
                  >
                    <component :is="item.icon" class="w-5 h-5 text-amber-400" />
                    <div>
                      <div class="text-sm text-white font-medium">{{ item.label }}</div>
                      <div class="text-xs text-gray-500">{{ item.description }}</div>
                    </div>
                  </button>
                </div>
              </div>
            </Transition>
          </div>

          <!-- Save Dropdown -->
          <div class="relative save-menu">
            <button @click="showSaveMenu = !showSaveMenu"
                    class="flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
              <CloudArrowUpIcon class="w-4 h-4" />
              <span class="text-sm">Speichern</span>
            </button>

            <!-- Dropdown Menu -->
            <Transition name="fade">
              <div v-if="showSaveMenu"
                   class="absolute top-full left-0 mt-2 w-56 bg-gray-800 border border-gray-700 rounded-lg shadow-xl z-50 overflow-hidden">
                <div class="py-1">
                  <button
                    @click="handleSaveDraft"
                    class="w-full px-4 py-2.5 flex items-center gap-3 text-left hover:bg-gray-700 transition-colors"
                  >
                    <FolderIcon class="w-5 h-5 text-blue-400" />
                    <div>
                      <div class="text-sm text-white font-medium">Als Entwurf speichern</div>
                      <div class="text-xs text-gray-500">Später weiterbearbeiten</div>
                    </div>
                  </button>
                  <button
                    @click="handleSaveAsTemplate"
                    class="w-full px-4 py-2.5 flex items-center gap-3 text-left hover:bg-gray-700 transition-colors"
                  >
                    <BookmarkIcon class="w-5 h-5 text-amber-400" />
                    <div>
                      <div class="text-sm text-white font-medium">Als Template speichern</div>
                      <div class="text-xs text-gray-500">Wiederverwendbare Vorlage</div>
                    </div>
                  </button>
                </div>
              </div>
            </Transition>
          </div>

          <!-- Element Actions (when selected) -->
          <template v-if="mockupStore.selectedElementId">
            <div class="w-px h-6 bg-gray-600 mx-1" />

            <!-- Alignment buttons -->
            <button @click="handleCenterHorizontally"
                    class="flex items-center gap-1 px-2 py-1.5 text-gray-300 hover:text-white hover:bg-gray-700 rounded-lg transition-colors"
                    title="Horizontal zentrieren">
              <Bars3Icon class="w-4 h-4" />
            </button>
            <button @click="handleCenterVertically"
                    class="flex items-center gap-1 px-2 py-1.5 text-gray-300 hover:text-white hover:bg-gray-700 rounded-lg transition-colors"
                    title="Vertikal zentrieren">
              <Bars2Icon class="w-4 h-4 rotate-90" />
            </button>
            <button @click="handleCenterBoth"
                    class="flex items-center gap-1 px-2 py-1.5 text-gray-300 hover:text-white hover:bg-gray-700 rounded-lg transition-colors"
                    title="Mittig zentrieren">
              <StopIcon class="w-4 h-4" />
            </button>

            <div class="w-px h-6 bg-gray-600 mx-1" />

            <button @click="handleDuplicateElement"
                    class="flex items-center gap-1.5 px-2 py-1.5 text-gray-300 hover:text-white hover:bg-gray-700 rounded-lg transition-colors"
                    title="Element duplizieren">
              <DocumentDuplicateIcon class="w-4 h-4" />
            </button>
            <button @click="handleDeleteElement"
                    class="flex items-center gap-1.5 px-2 py-1.5 text-gray-300 hover:text-red-400 hover:bg-gray-700 rounded-lg transition-colors"
                    title="Element löschen">
              <TrashIcon class="w-4 h-4" />
            </button>
          </template>

          <div class="w-px h-6 bg-gray-600 mx-1" />

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
      <div ref="canvasContainerRef" class="flex-1 overflow-auto bg-gray-900 flex items-center justify-center p-8">
        <template v-if="hasTemplate">
          <MockupCanvas ref="canvasRef" @edit-image="handleEditImage" />
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

    <!-- Image Annotation Editor -->
    <ImageAnnotationEditor
      :show="showAnnotationEditor"
      :element-id="annotationElementId"
      @close="showAnnotationEditor = false"
    />

    <!-- Save Template Modal -->
    <Teleport to="body">
      <Transition name="fade">
        <div v-if="showSaveTemplateModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div class="absolute inset-0 bg-black/50" @click="showSaveTemplateModal = false" />
          <div class="relative bg-gray-800 rounded-xl shadow-xl w-full max-w-md p-6">
            <h3 class="text-lg font-semibold text-white mb-4">Als Template speichern</h3>

            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Name</label>
                <input
                  v-model="saveTemplateName"
                  type="text"
                  class="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-amber-500"
                  placeholder="Template Name"
                />
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Beschreibung (optional)</label>
                <textarea
                  v-model="saveTemplateDescription"
                  rows="3"
                  class="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-amber-500 resize-none"
                  placeholder="Kurze Beschreibung..."
                />
              </div>
            </div>

            <div class="flex justify-end gap-3 mt-6">
              <button
                @click="showSaveTemplateModal = false"
                class="px-4 py-2 text-gray-300 hover:text-white transition-colors"
              >
                Abbrechen
              </button>
              <button
                @click="confirmSaveTemplate"
                :disabled="mockupStore.isLoading"
                class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-gray-900 font-medium rounded-lg transition-colors disabled:opacity-50"
              >
                {{ mockupStore.isLoading ? 'Speichert...' : 'Speichern' }}
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
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

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
