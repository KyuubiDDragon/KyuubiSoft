import { ref, readonly } from 'vue'
import type { Ref, DeepReadonly } from 'vue'

// Interfaces
type ToastType = 'success' | 'error' | 'warning' | 'info'

interface Toast {
  id: number
  message: string
  type: ToastType
  duration: number
  visible: boolean
}

interface UseToastReturn {
  toasts: DeepReadonly<Ref<Toast[]>>
  addToast: (message: string, type?: ToastType, duration?: number) => number
  removeToast: (id: number) => void
  success: (message: string, duration?: number) => number
  error: (message: string, duration?: number) => number
  warning: (message: string, duration?: number) => number
  info: (message: string, duration?: number) => number
}

const toasts: Ref<Toast[]> = ref<Toast[]>([])
let toastId: number = 0

export function useToast(): UseToastReturn {
  const addToast = (message: string, type: ToastType = 'info', duration: number = 4000): number => {
    const id: number = ++toastId
    const toast: Toast = {
      id,
      message,
      type, // 'success', 'error', 'warning', 'info'
      duration,
      visible: true
    }

    toasts.value.push(toast)

    if (duration > 0) {
      setTimeout(() => {
        removeToast(id)
      }, duration)
    }

    return id
  }

  const removeToast = (id: number): void => {
    const index: number = toasts.value.findIndex(t => t.id === id)
    if (index > -1) {
      toasts.value.splice(index, 1)
    }
  }

  const success = (message: string, duration?: number): number => addToast(message, 'success', duration)
  const error = (message: string, duration?: number): number => addToast(message, 'error', duration ?? 6000)
  const warning = (message: string, duration?: number): number => addToast(message, 'warning', duration)
  const info = (message: string, duration?: number): number => addToast(message, 'info', duration)

  return {
    toasts: readonly(toasts),
    addToast,
    removeToast,
    success,
    error,
    warning,
    info
  }
}

// Singleton instance for global access
export const toast = {
  success: (message: string, duration?: number): number => useToast().success(message, duration),
  error: (message: string, duration?: number): number => useToast().error(message, duration),
  warning: (message: string, duration?: number): number => useToast().warning(message, duration),
  info: (message: string, duration?: number): number => useToast().info(message, duration)
}

// Export toasts ref for ToastContainer
export { toasts }
