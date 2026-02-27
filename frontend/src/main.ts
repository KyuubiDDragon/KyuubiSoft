import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import i18n from './locales'
import './assets/styles/main.css'

const app = createApp(App)

app.use(createPinia())
app.use(router)
app.use(i18n)

// Global error handler
app.config.errorHandler = (err: unknown, _instance: unknown, info: string): void => {
  const message = err instanceof Error ? err.message : String(err)

  // Filter known non-critical errors
  const ignoredPatterns = [
    'ResizeObserver loop',
    'Navigation cancelled',
    'Avoided redundant navigation',
    'NavigationDuplicated',
  ]
  if (ignoredPatterns.some((p) => message.includes(p))) return

  console.error(`[${info}]`, err)

  // Show user-facing toast (lazy import to avoid circular deps during init)
  import('./stores/ui').then(({ useUiStore }) => {
    try {
      useUiStore().showError('Ein unerwarteter Fehler ist aufgetreten.')
    } catch {
      // Store not yet available
    }
  }).catch(() => {})
}

app.mount('#app')
