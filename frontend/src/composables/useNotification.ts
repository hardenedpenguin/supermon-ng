import { ref } from 'vue'

export interface Notification {
  id: string
  type: 'success' | 'error' | 'warning' | 'info'
  message: string
  duration?: number
  timestamp: number
}

const notifications = ref<Notification[]>([])

export function useNotification() {
  const showNotification = (
    type: Notification['type'],
    message: string,
    duration: number = 5000
  ) => {
    const id = `notification-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`
    
    const notification: Notification = {
      id,
      type,
      message,
      duration,
      timestamp: Date.now()
    }
    
    notifications.value.push(notification)
    
    // Auto-remove notification after duration
    if (duration > 0) {
      setTimeout(() => {
        removeNotification(id)
      }, duration)
    }
    
    return id
  }
  
  const removeNotification = (id: string) => {
    const index = notifications.value.findIndex(n => n.id === id)
    if (index > -1) {
      notifications.value.splice(index, 1)
    }
  }
  
  const clearAllNotifications = () => {
    notifications.value = []
  }
  
  const success = (message: string, duration?: number) => {
    return showNotification('success', message, duration)
  }
  
  const error = (message: string, duration?: number) => {
    return showNotification('error', message, duration)
  }
  
  const warning = (message: string, duration?: number) => {
    return showNotification('warning', message, duration)
  }
  
  const info = (message: string, duration?: number) => {
    return showNotification('info', message, duration)
  }
  
  return {
    notifications,
    showNotification,
    removeNotification,
    clearAllNotifications,
    success,
    error,
    warning,
    info
  }
}
