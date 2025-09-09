<template>
  <div class="form-field" :class="{ 'has-error': hasError, 'is-required': required }">
    <label v-if="label" :for="fieldId" class="form-label">
      {{ label }}
      <span v-if="required" class="required-indicator">*</span>
    </label>
    
    <!-- Text Input -->
    <input
      v-if="type === 'text' || type === 'email' || type === 'password' || type === 'number'"
      :id="fieldId"
      v-model="internalValue"
      :type="type"
      :placeholder="placeholder"
      :disabled="disabled"
      :readonly="readonly"
      :class="inputClasses"
      @blur="handleBlur"
      @input="handleInput"
    />
    
    <!-- Textarea -->
    <textarea
      v-else-if="type === 'textarea'"
      :id="fieldId"
      v-model="internalValue"
      :placeholder="placeholder"
      :disabled="disabled"
      :readonly="readonly"
      :rows="rows || 3"
      :class="inputClasses"
      @blur="handleBlur"
      @input="handleInput"
    />
    
    <!-- Select -->
    <select
      v-else-if="type === 'select'"
      :id="fieldId"
      v-model="internalValue"
      :disabled="disabled"
      :class="inputClasses"
      @blur="handleBlur"
      @change="handleInput"
    >
      <option v-if="placeholder" value="" disabled>{{ placeholder }}</option>
      <optgroup
        v-for="group in groupedOptions"
        :key="group.label"
        :label="group.label"
      >
        <option
          v-for="option in group.options"
          :key="option.value"
          :value="option.value"
          :disabled="option.disabled"
        >
          {{ option.label }}
        </option>
      </optgroup>
      <option
        v-for="option in ungroupedOptions"
        :key="option.value"
        :value="option.value"
        :disabled="option.disabled"
      >
        {{ option.label }}
      </option>
    </select>
    
    <!-- Checkbox -->
    <label v-else-if="type === 'checkbox'" :for="fieldId" class="checkbox-label">
      <input
        :id="fieldId"
        v-model="internalValue"
        type="checkbox"
        :disabled="disabled"
        class="checkbox-input"
        @blur="handleBlur"
        @change="handleInput"
      />
      <span class="checkbox-text">{{ checkboxLabel || label }}</span>
    </label>
    
    <!-- Radio Group -->
    <div v-else-if="type === 'radio'" class="radio-group">
      <label
        v-for="option in options"
        :key="option.value"
        class="radio-label"
      >
        <input
          :name="fieldId"
          v-model="internalValue"
          type="radio"
          :value="option.value"
          :disabled="disabled || option.disabled"
          class="radio-input"
          @blur="handleBlur"
          @change="handleInput"
        />
        <span class="radio-text">{{ option.label }}</span>
      </label>
    </div>
    
    <!-- Help Text -->
    <div v-if="helpText && !hasError" class="help-text">
      {{ helpText }}
    </div>
    
    <!-- Error Messages -->
    <div v-if="hasError" class="error-messages">
      <div v-for="error in errorMessages" :key="error" class="error-message">
        {{ error }}
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import type { SelectOption, InputType } from '@/types'

interface Props {
  modelValue?: any
  type: InputType
  label?: string
  placeholder?: string
  required?: boolean
  disabled?: boolean
  readonly?: boolean
  options?: SelectOption[]
  rows?: number
  helpText?: string
  errors?: string[]
  checkboxLabel?: string
}

interface Emits {
  (e: 'update:modelValue', value: any): void
  (e: 'blur', event: Event): void
  (e: 'input', event: Event): void
}

const props = withDefaults(defineProps<Props>(), {
  type: 'text',
  required: false,
  disabled: false,
  readonly: false,
  options: () => [],
  errors: () => []
})

const emit = defineEmits<Emits>()

// Generate unique field ID
const fieldId = ref(`field-${Math.random().toString(36).substr(2, 9)}`)

// Internal value management
const internalValue = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

// Error handling
const hasError = computed(() => props.errors && props.errors.length > 0)
const errorMessages = computed(() => props.errors || [])

// Option grouping for select
const groupedOptions = computed(() => {
  if (!props.options) return []
  
  const groups = props.options
    .filter(option => option.group)
    .reduce((acc, option) => {
      const groupName = option.group!
      if (!acc[groupName]) {
        acc[groupName] = { label: groupName, options: [] }
      }
      acc[groupName].options.push(option)
      return acc
    }, {} as Record<string, { label: string; options: SelectOption[] }>)
  
  return Object.values(groups)
})

const ungroupedOptions = computed(() => {
  return props.options?.filter(option => !option.group) || []
})

// CSS classes
const inputClasses = computed(() => [
  'form-input',
  {
    'form-input--error': hasError.value,
    'form-input--disabled': props.disabled,
    'form-input--readonly': props.readonly
  }
])

// Event handlers
const handleBlur = (event: Event) => {
  emit('blur', event)
}

const handleInput = (event: Event) => {
  emit('input', event)
}
</script>

<style scoped>
.form-field {
  margin-bottom: 1rem;
}

.form-label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
  color: var(--text-color);
}

.required-indicator {
  color: var(--error-color, #dc3545);
  margin-left: 0.25rem;
}

.form-input {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid var(--border-color);
  border-radius: 0.375rem;
  background-color: var(--input-bg, var(--container-bg));
  color: var(--text-color);
  font-size: 1rem;
  transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.form-input:focus {
  outline: none;
  border-color: var(--primary-color);
  box-shadow: 0 0 0 0.2rem var(--primary-color-alpha, rgba(0, 123, 255, 0.25));
}

.form-input--error {
  border-color: var(--error-color, #dc3545);
}

.form-input--error:focus {
  border-color: var(--error-color, #dc3545);
  box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
}

.form-input--disabled {
  background-color: var(--disabled-bg, #f8f9fa);
  opacity: 0.6;
  cursor: not-allowed;
}

.form-input--readonly {
  background-color: var(--readonly-bg, #f8f9fa);
}

.checkbox-label,
.radio-label {
  display: flex;
  align-items: center;
  cursor: pointer;
  margin-bottom: 0.5rem;
}

.checkbox-input,
.radio-input {
  margin-right: 0.5rem;
}

.radio-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.help-text {
  margin-top: 0.25rem;
  font-size: 0.875rem;
  color: var(--text-muted, #6c757d);
}

.error-messages {
  margin-top: 0.25rem;
}

.error-message {
  font-size: 0.875rem;
  color: var(--error-color, #dc3545);
  margin-bottom: 0.25rem;
}

.has-error .form-label {
  color: var(--error-color, #dc3545);
}

@media (max-width: 768px) {
  .form-input {
    padding: 0.5rem;
    font-size: 16px; /* Prevent zoom on iOS */
  }
}
</style>
