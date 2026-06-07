import { ref } from 'vue'

export type ToastType = 'success' | 'error' | 'info' | 'warning'

export interface ToastItem {
  id: number
  message: string
  type: ToastType
}

const toasts = ref<ToastItem[]>([])
let nextId = 1

function dismiss(id: number): void {
  toasts.value = toasts.value.filter((t) => t.id !== id)
}

function show(message: string, type: ToastType = 'info', durationMs = 5000): void {
  const id = nextId++
  toasts.value.push({ id, message, type })
  window.setTimeout(() => dismiss(id), durationMs)
}

export function useToast() {
  return {
    toasts,
    show,
    success: (message: string, durationMs?: number) => show(message, 'success', durationMs),
    error: (message: string, durationMs?: number) => show(message, 'error', durationMs ?? 7000),
    warning: (message: string, durationMs?: number) => show(message, 'warning', durationMs),
    info: (message: string, durationMs?: number) => show(message, 'info', durationMs),
    dismiss,
  }
}
