<template>
  <div class="toast-container" aria-live="polite" aria-relevant="additions">
    <TransitionGroup name="toast">
      <div
        v-for="toast in toastApi.toasts.value"
        :key="toast.id"
        class="toast"
        :class="`toast--${toast.type}`"
        role="status"
      >
        <span class="toast-message">{{ toast.message }}</span>
        <button
          type="button"
          class="toast-dismiss"
          aria-label="Dismiss"
          @click="toastApi.dismiss(toast.id)"
        >
          ×
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>

<script setup lang="ts">
import { useToast } from '@/composables/useToast'

const toastApi = useToast()
</script>

<style scoped>
.toast-container {
  position: fixed;
  top: 0.75rem;
  right: 0.75rem;
  z-index: 10000;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  max-width: min(24rem, calc(100vw - 1.5rem));
  pointer-events: none;
}

.toast {
  display: flex;
  align-items: flex-start;
  gap: 0.5rem;
  padding: 0.6rem 0.75rem;
  border-radius: 6px;
  border: 1px solid transparent;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  font-size: 0.9rem;
  pointer-events: auto;
}

.toast-message {
  flex: 1;
  line-height: 1.35;
}

.toast-dismiss {
  border: none;
  background: transparent;
  color: inherit;
  font-size: 1.15rem;
  line-height: 1;
  cursor: pointer;
  padding: 0;
  opacity: 0.75;
}

.toast-dismiss:hover {
  opacity: 1;
}

.toast--success {
  background: #d4edda;
  border-color: #b7dfc3;
  color: #155724;
}

.toast--error {
  background: #f8d7da;
  border-color: #f1b0b7;
  color: #721c24;
}

.toast--warning {
  background: #fff3cd;
  border-color: #ffe69c;
  color: #856404;
}

.toast--info {
  background: #d1ecf1;
  border-color: #abdde5;
  color: #0c5460;
}

.toast-enter-active,
.toast-leave-active {
  transition: all 0.2s ease;
}

.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translateX(1rem);
}
</style>
