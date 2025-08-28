<template>
  <button
    :type="type"
    :disabled="disabled"
    :class="buttonClasses"
    @click="handleClick"
  >
    <slot />
  </button>
</template>

<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  type?: 'button' | 'submit' | 'reset'
  variant?: 'primary' | 'secondary' | 'success' | 'warning' | 'error'
  size?: 'sm' | 'md' | 'lg'
  disabled?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  type: 'button',
  variant: 'primary',
  size: 'md',
  disabled: false
})

const emit = defineEmits<{
  click: [event: MouseEvent]
}>()

const buttonClasses = computed(() => {
  return [
    'btn',
    `btn-${props.variant}`,
    `btn-${props.size}`,
    {
      'btn-disabled': props.disabled
    }
  ]
})

const handleClick = (event: MouseEvent) => {
  if (!props.disabled) {
    emit('click', event)
  }
}
</script>

<style scoped>
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1px solid transparent;
  border-radius: var(--border-radius-md);
  font-weight: 500;
  text-align: center;
  text-decoration: none;
  cursor: pointer;
  transition: all var(--transition-normal);
  user-select: none;
  white-space: nowrap;
}

.btn:focus {
  outline: none;
  box-shadow: 0 0 0 2px var(--primary-color);
}

.btn:disabled,
.btn-disabled {
  opacity: 0.6;
  cursor: not-allowed;
  pointer-events: none;
}

/* Variants */
.btn-primary {
  background-color: var(--table-header-bg);
  color: var(--primary-color);
  border-color: var(--border-color);
}

.btn-primary:hover:not(:disabled) {
  background-color: var(--primary-color);
  color: var(--background-color);
  border-color: var(--primary-color);
  transform: translateY(-1px);
}

.btn-secondary {
  background-color: var(--container-bg);
  color: var(--text-color);
  border-color: var(--border-color);
}

.btn-secondary:hover:not(:disabled) {
  background-color: var(--border-color);
  color: var(--text-color);
}

.btn-success {
  background-color: var(--success-color);
  color: white;
  border-color: var(--success-color);
}

.btn-success:hover:not(:disabled) {
  background-color: #45a049;
  border-color: #45a049;
}

.btn-warning {
  background-color: var(--warning-color);
  color: white;
  border-color: var(--warning-color);
}

.btn-warning:hover:not(:disabled) {
  background-color: #e68900;
  border-color: #e68900;
}

.btn-error {
  background-color: var(--error-color);
  color: white;
  border-color: var(--error-color);
}

.btn-error:hover:not(:disabled) {
  background-color: #d32f2f;
  border-color: #d32f2f;
}

/* Sizes */
.btn-sm {
  padding: 0.375rem 0.75rem;
  font-size: 0.875rem;
  line-height: 1.5;
}

.btn-md {
  padding: 0.5rem 1rem;
  font-size: 1rem;
  line-height: 1.5;
}

.btn-lg {
  padding: 0.75rem 1.5rem;
  font-size: 1.125rem;
  line-height: 1.5;
}
</style>
