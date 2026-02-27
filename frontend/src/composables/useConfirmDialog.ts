import { ref, readonly } from 'vue'
import type { Ref, DeepReadonly } from 'vue'

// Interfaces
type ConfirmDialogType = 'warning' | 'danger' | 'info'

interface ConfirmDialogOptions {
  title?: string
  message?: string
  confirmText?: string
  cancelText?: string
  type?: ConfirmDialogType
}

interface ConfirmDialogConfig {
  title: string
  message: string
  confirmText: string
  cancelText: string
  type: ConfirmDialogType
  resolve: ((value: boolean) => void) | null
}

interface UseConfirmDialogReturn {
  isOpen: DeepReadonly<Ref<boolean>>
  dialogConfig: DeepReadonly<Ref<ConfirmDialogConfig>>
  confirm: (options: ConfirmDialogOptions | string) => Promise<boolean>
  handleConfirm: () => void
  handleCancel: () => void
}

const isOpen: Ref<boolean> = ref<boolean>(false)
const dialogConfig: Ref<ConfirmDialogConfig> = ref<ConfirmDialogConfig>({
  title: 'Bestätigung',
  message: '',
  confirmText: 'Bestätigen',
  cancelText: 'Abbrechen',
  type: 'warning', // 'warning', 'danger', 'info'
  resolve: null
})

export function useConfirmDialog(): UseConfirmDialogReturn {
  const confirm = (options: ConfirmDialogOptions | string): Promise<boolean> => {
    return new Promise<boolean>((resolve) => {
      let opts: ConfirmDialogOptions
      if (typeof options === 'string') {
        opts = { message: options }
      } else {
        opts = options
      }

      dialogConfig.value = {
        title: opts.title || 'Bestätigung',
        message: opts.message || '',
        confirmText: opts.confirmText || 'Bestätigen',
        cancelText: opts.cancelText || 'Abbrechen',
        type: opts.type || 'warning',
        resolve
      }

      isOpen.value = true
    })
  }

  const handleConfirm = (): void => {
    isOpen.value = false
    if (dialogConfig.value.resolve) {
      dialogConfig.value.resolve(true)
    }
  }

  const handleCancel = (): void => {
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
