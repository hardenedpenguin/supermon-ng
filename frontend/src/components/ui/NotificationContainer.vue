<template>
  <Teleport to="body">
    <div class="notification-container">
      <TransitionGroup name="notification" tag="div">
        <div
          v-for="notification in notifications"
          :key="notification.id"
          :class="notificationClasses(notification)"
          @click="removeNotification(notification.id)"
        >
          <div class="notification-content">
            <span class="notification-message">{{ notification.message }}</span>
            <button class="notification-close" @click.stop="removeNotification(notification.id)">
              ×
            </button>
          </div>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useNotification } from '@/composables/useNotification'

const { notifications, removeNotification } = useNotification()

const notificationClasses = (notification: any) => {
  return [
    'notification',
    `notification-${notification.type}`
  ]
}
</script>

<style scoped>
.notification-container {
  position: fixed;
  top: 1rem;
  right: 1rem;
  z-index: 9999;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  max-width: 400px;
}

.notification {
  background: var(--container-bg);
  border: 1px solid var(--border-color);
  border-radius: var(--border-radius-md);
  box-shadow: var(--shadow-md);
  padding: 1rem;
  cursor: pointer;
  transition: all var(--transition-normal);
  min-width: 300px;
}

.notification:hover {
  transform: translateX(-5px);
  box-shadow: var(--shadow-lg);
}

.notification-content {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
}

.notification-message {
  flex: 1;
  color: var(--text-color);
  font-size: 0.875rem;
  line-height: 1.4;
}

.notification-close {
  background: none;
  border: none;
  color: var(--text-color);
  cursor: pointer;
  font-size: 1.25rem;
  padding: 0;
  width: 1.5rem;
  height: 1.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--border-radius-sm);
  transition: background-color var(--transition-fast);
  flex-shrink: 0;
}

.notification-close:hover {
  background-color: var(--border-color);
}

/* Notification types */
.notification-success {
  border-left: 4px solid var(--success-color);
  background-color: rgba(76, 175, 80, 0.1);
}

.notification-error {
  border-left: 4px solid var(--error-color);
  background-color: rgba(244, 67, 54, 0.1);
}

.notification-warning {
  border-left: 4px solid var(--warning-color);
  background-color: rgba(255, 152, 0, 0.1);
}

.notification-info {
  border-left: 4px solid var(--link-color);
  background-color: rgba(33, 150, 243, 0.1);
}

/* Transitions */
.notification-enter-active,
.notification-leave-active {
  transition: all var(--transition-normal);
}

.notification-enter-from {
  opacity: 0;
  transform: translateX(100%);
}

.notification-leave-to {
  opacity: 0;
  transform: translateX(100%);
}

.notification-move {
  transition: transform var(--transition-normal);
}

/* Responsive */
@media (max-width: 768px) {
  .notification-container {
    top: 0.5rem;
    right: 0.5rem;
    left: 0.5rem;
    max-width: none;
  }
  
  .notification {
    min-width: auto;
  }
}
</style>
