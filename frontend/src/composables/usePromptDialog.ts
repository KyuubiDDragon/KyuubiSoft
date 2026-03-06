import { ref, readonly } from 'vue'
import type { Ref, DeepReadonly } from 'vue'
import i18n from '@/locales'

// Interfaces
interface PromptDialogOptions {
  title?: string
  message?: string
  placeholder?: string
  defaultValue?: string
  confirmText?: string
  cancelText?: string
  inputType?: string
}

interface PromptDialogConfig {
  title: string
  message: string
  placeholder: string
  defaultValue?: string
  confirmText: string
  cancelText: string
  inputType: string
  resolve: ((value: string | null) => void) | null
}

interface UsePromptDialogReturn {
  isOpen: DeepReadonly<Ref<boolean>>
  inputValue: Ref<string>
  dialogConfig: DeepReadonly<Ref<PromptDialogConfig>>
  prompt: (options: PromptDialogOptions | string) => Promise<string | null>
  handleConfirm: () => void
  handleCancel: () => void
}

const isOpen: Ref<boolean> = ref<boolean>(false)
const inputValue: Ref<string> = ref<string>('')
const t = () => (i18n.global as any).t
const dialogConfig: Ref<PromptDialogConfig> = ref<PromptDialogConfig>({
  title: '',
  message: '',
  placeholder: '',
  confirmText: 'OK',
  cancelText: '',
  inputType: 'text',
  resolve: null
})

export function usePromptDialog(): UsePromptDialogReturn {
  const prompt = (options: PromptDialogOptions | string): Promise<string | null> => {
    return new Promise<string | null>((resolve) => {
      let opts: PromptDialogOptions
      if (typeof options === 'string') {
        opts = { message: options }
      } else {
        opts = options
      }

      dialogConfig.value = {
        title: opts.title || t()('common.input'),
        message: opts.message || '',
        placeholder: opts.placeholder || '',
        defaultValue: opts.defaultValue || '',
        confirmText: opts.confirmText || 'OK',
        cancelText: opts.cancelText || t()('common.cancel'),
        inputType: opts.inputType || 'text',
        resolve
      }

      inputValue.value = opts.defaultValue || ''
      isOpen.value = true
    })
  }

  const handleConfirm = (): void => {
    isOpen.value = false
    if (dialogConfig.value.resolve) {
      dialogConfig.value.resolve(inputValue.value)
    }
  }

  const handleCancel = (): void => {
    isOpen.value = false
    if (dialogConfig.value.resolve) {
      dialogConfig.value.resolve(null)
    }
  }

  return {
    isOpen: readonly(isOpen),
    inputValue,
    dialogConfig: readonly(dialogConfig),
    prompt,
    handleConfirm,
    handleCancel
  }
}

// Export refs for PromptDialog component
export { isOpen, inputValue, dialogConfig }
