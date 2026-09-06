import { reactive } from 'vue'
import type { ToastTone } from './components/ToastNotification.vue'

interface Toast {
  id: number
  message: string
  tone: ToastTone
}

const state = reactive<{ toasts: Toast[] }>({ toasts: [] })
let nextId = 1

export function useToast() {
  function push(message: string, tone: ToastTone = 'info') {
    const id = nextId++
    state.toasts.push({ id, message, tone })
    setTimeout(() => dismiss(id), 5000)
  }

  function dismiss(id: number) {
    const index = state.toasts.findIndex((t) => t.id === id)
    if (index !== -1) state.toasts.splice(index, 1)
  }

  return { toasts: state.toasts, push, dismiss }
}
