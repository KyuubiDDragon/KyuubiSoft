import { ref, readonly } from 'vue'

const isOpen = ref(false)
const inputValue = ref('')
const dialogConfig = ref({
  title: 'Eingabe',
  message: '',
  placeholder: '',
  confirmText: 'OK',
  cancelText: 'Abbrechen',
  inputType: 'text',
  resolve: null
})

export function usePromptDialog() {
  const prompt = (options) => {
    return new Promise((resolve) => {
      if (typeof options === 'string') {
        options = { message: options }
      }

      dialogConfig.value = {
        title: options.title || 'Eingabe',
        message: options.message || '',
        placeholder: options.placeholder || '',
        defaultValue: options.defaultValue || '',
        confirmText: options.confirmText || 'OK',
        cancelText: options.cancelText || 'Abbrechen',
        inputType: options.inputType || 'text',
        resolve
      }

      inputValue.value = options.defaultValue || ''
      isOpen.value = true
    })
  }

  const handleConfirm = () => {
    isOpen.value = false
    if (dialogConfig.value.resolve) {
      dialogConfig.value.resolve(inputValue.value)
    }
  }

  const handleCancel = () => {
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
