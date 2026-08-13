import { ref } from 'vue'

export type ToastType = 'success' | 'error' | 'info' | 'warning'

export interface ToastItem {
  id: number
  message: string
  type: ToastType
}

const toasts = ref<ToastItem[]>([])
let nextId = 1
const dismissTimers = new Map<number, ReturnType<typeof setTimeout>>()

function dismiss(id: number): void {
  const timer = dismissTimers.get(id)
  if (timer !== undefined) {
    clearTimeout(timer)
    dismissTimers.delete(id)
  }
  toasts.value = toasts.value.filter((t) => t.id !== id)
}

function show(message: string, type: ToastType = 'info', durationMs = 5000): void {
  const id = nextId++
  toasts.value.push({ id, message, type })
  dismissTimers.set(id, setTimeout(() => dismiss(id), durationMs))
}

export function useToast() {
  return {
    toasts,
    show,
    success: (message: string, durationMs?: number) => show(message, 'success', durationMs ?? 5000),
    error: (message: string, durationMs?: number) => show(message, 'error', durationMs ?? 7000),
    warning: (message: string, durationMs?: number) => show(message, 'warning', durationMs ?? 5000),
    info: (message: string, durationMs?: number) => show(message, 'info', durationMs ?? 5000),
    dismiss,
  }
}
