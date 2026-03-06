import { ref, readonly } from 'vue'
import type { Ref, DeepReadonly } from 'vue'
import i18n from '@/locales'

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
const t = () => (i18n.global as any).t
const dialogConfig: Ref<ConfirmDialogConfig> = ref<ConfirmDialogConfig>({
  title: '',
  message: '',
  confirmText: '',
  cancelText: '',
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
        title: opts.title || t()('common.confirmation'),
        message: opts.message || '',
        confirmText: opts.confirmText || t()('common.confirm'),
        cancelText: opts.cancelText || t()('common.cancel'),
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
