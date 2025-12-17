import { ref, readonly } from 'vue'

const isOpen = ref(false)
const dialogConfig = ref({
  title: 'Bestätigung',
  message: '',
  confirmText: 'Bestätigen',
  cancelText: 'Abbrechen',
  type: 'warning', // 'warning', 'danger', 'info'
  resolve: null
})

export function useConfirmDialog() {
  const confirm = (options) => {
    return new Promise((resolve) => {
      if (typeof options === 'string') {
        options = { message: options }
      }

      dialogConfig.value = {
        title: options.title || 'Bestätigung',
        message: options.message || '',
        confirmText: options.confirmText || 'Bestätigen',
        cancelText: options.cancelText || 'Abbrechen',
        type: options.type || 'warning',
        resolve
      }

      isOpen.value = true
    })
  }

  const handleConfirm = () => {
    isOpen.value = false
    if (dialogConfig.value.resolve) {
      dialogConfig.value.resolve(true)
    }
  }

  const handleCancel = () => {
    isOpen.value = false
    if (dialogConfig.value.resolve) {
      dialogConfig.value.resolve(false)
    }
  }

  return {
    isOpen: readonly(isOpen),
    dialogConfig: readonly(dialogConfig),
    confirm,
    handleConfirm,
    handleCancel
  }
}

// Export refs for ConfirmDialog component
export { isOpen, dialogConfig }
