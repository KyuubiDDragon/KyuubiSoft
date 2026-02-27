<script setup>
import { ref } from 'vue'
import { useRegisterSW } from 'virtual:pwa-register/vue'
import { ArrowPathIcon, XMarkIcon } from '@heroicons/vue/24/outline'

const {
  needRefresh,
  updateServiceWorker,
} = useRegisterSW({
  onRegistered(r) {
    // Check for updates every hour
    r && setInterval(() => {
      r.update()
    }, 60 * 60 * 1000)
  },
  onRegisterError(error) {
    console.error('SW registration error', error)
  },
})

const isUpdating = ref(false)

async function updateApp() {
  isUpdating.value = true
  await updateServiceWorker(true)
}

function close() {
  needRefresh.value = false
}
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-300 ease-out"
      enter-from-class="translate-y-full opacity-0"
      enter-to-class="translate-y-0 opacity-100"
      leave-active-class="transition duration-200 ease-in"
      leave-from-class="translate-y-0 opacity-100"
      leave-to-class="translate-y-full opacity-0"
    >
      <div
        v-if="needRefresh"
        class="fixed bottom-4 left-4 right-4 md:left-auto md:right-4 md:w-96 z-50"
      >
        <div class="bg-dark-900/95 backdrop-blur-2xl border border-white/[0.08] rounded-2xl shadow-float p-4">
          <div class="flex items-start gap-3">
            <div class="flex-shrink-0 w-10 h-10 bg-primary-500/[0.12] rounded-xl flex items-center justify-center">
              <ArrowPathIcon class="w-5 h-5 text-primary-400" />
            </div>
            <div class="flex-1 min-w-0">
              <h3 class="text-white font-medium">Neue Version verfügbar</h3>
              <p class="text-gray-400 text-sm mt-1">
                Eine aktualisierte Version von KyuubiSoft ist bereit.
              </p>
            </div>
            <button
              @click="close"
              class="flex-shrink-0 p-1 text-gray-400 hover:text-white rounded transition-colors"
            >
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>
          <div class="flex gap-2 mt-4">
            <button
              @click="close"
              class="flex-1 px-4 py-2 text-sm text-gray-300 hover:text-white btn-secondary btn-sm transition-colors"
            >
              Später
            </button>
            <button
              @click="updateApp"
              :disabled="isUpdating"
              class="flex-1 px-4 py-2 text-sm text-white btn-primary btn-sm transition-colors disabled:opacity-50 flex items-center justify-center gap-2"
            >
              <ArrowPathIcon v-if="isUpdating" class="w-4 h-4 animate-spin" />
              {{ isUpdating ? 'Aktualisiere...' : 'Jetzt aktualisieren' }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
