<script setup>
import { ref, onMounted, onUnmounted, watch, nextTick } from 'vue'
import { XMarkIcon, TrashIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  show: Boolean,
})

const emit = defineEmits(['close', 'save'])

const canvasRef = ref(null)
const isDrawing = ref(false)
let ctx = null

watch(() => props.show, async (val) => {
  if (val) {
    await nextTick()
    initCanvas()
  }
})

function initCanvas() {
  const canvas = canvasRef.value
  if (!canvas) return
  ctx = canvas.getContext('2d')
  const rect = canvas.parentElement.getBoundingClientRect()
  canvas.width = rect.width
  canvas.height = 200
  ctx.strokeStyle = '#111827'
  ctx.lineWidth = 2
  ctx.lineCap = 'round'
  ctx.lineJoin = 'round'
  clearCanvas()
}

function clearCanvas() {
  if (!ctx || !canvasRef.value) return
  ctx.fillStyle = '#ffffff'
  ctx.fillRect(0, 0, canvasRef.value.width, canvasRef.value.height)
  // Draw baseline
  ctx.strokeStyle = '#e5e7eb'
  ctx.lineWidth = 1
  ctx.beginPath()
  ctx.moveTo(20, canvasRef.value.height - 40)
  ctx.lineTo(canvasRef.value.width - 20, canvasRef.value.height - 40)
  ctx.stroke()
  // Reset for signature
  ctx.strokeStyle = '#111827'
  ctx.lineWidth = 2
}

function getPos(e) {
  const rect = canvasRef.value.getBoundingClientRect()
  const clientX = e.touches ? e.touches[0].clientX : e.clientX
  const clientY = e.touches ? e.touches[0].clientY : e.clientY
  return {
    x: clientX - rect.left,
    y: clientY - rect.top,
  }
}

function startDraw(e) {
  e.preventDefault()
  isDrawing.value = true
  const pos = getPos(e)
  ctx.beginPath()
  ctx.moveTo(pos.x, pos.y)
}

function draw(e) {
  if (!isDrawing.value) return
  e.preventDefault()
  const pos = getPos(e)
  ctx.lineTo(pos.x, pos.y)
  ctx.stroke()
}

function stopDraw() {
  isDrawing.value = false
}

function saveSignature() {
  if (!canvasRef.value) return
  const dataUrl = canvasRef.value.toDataURL('image/png')
  emit('save', dataUrl)
}
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition ease-out duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition ease-in duration-150"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="show"
        class="fixed inset-0 bg-black/60 backdrop-blur-md flex items-center justify-center z-50 p-4"
        @click.self="$emit('close')"
      >
        <div class="modal w-full max-w-lg">
          <div class="px-6 py-4 border-b border-white/[0.06] flex items-center justify-between">
            <h2 class="text-lg font-bold text-white">Unterschrift</h2>
            <button @click="$emit('close')" class="p-2 rounded-lg text-gray-400 hover:text-white hover:bg-white/[0.04]">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <div class="p-6">
            <p class="text-sm text-gray-400 mb-4">Zeichnen Sie Ihre Unterschrift in das Feld unten. Sie koennen auch den Touchscreen verwenden.</p>

            <div class="bg-white rounded-xl overflow-hidden border border-gray-200">
              <canvas
                ref="canvasRef"
                class="w-full cursor-crosshair touch-none"
                @mousedown="startDraw"
                @mousemove="draw"
                @mouseup="stopDraw"
                @mouseleave="stopDraw"
                @touchstart="startDraw"
                @touchmove="draw"
                @touchend="stopDraw"
              ></canvas>
            </div>

            <div class="flex items-center justify-between mt-4">
              <button @click="clearCanvas" class="flex items-center gap-1 px-3 py-2 rounded-lg text-sm text-gray-400 hover:text-white hover:bg-white/[0.04]">
                <TrashIcon class="w-4 h-4" /> Loeschen
              </button>
              <div class="flex gap-2">
                <button @click="$emit('close')" class="px-4 py-2 rounded-lg text-sm text-gray-400 hover:text-white hover:bg-white/[0.04]">
                  Abbrechen
                </button>
                <button @click="saveSignature" class="px-5 py-2 rounded-lg text-sm font-semibold bg-green-600 text-white hover:bg-green-500">
                  Unterschrift speichern
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
