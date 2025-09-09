// Form validation utilities

import type { ValidationRule, FormErrors } from '@/types'

/**
 * Validation rule builders
 */
export const validators = {
  required: (message: string = 'This field is required'): ValidationRule => ({
    type: 'required',
    message,
    validator: (value: any) => {
      if (typeof value === 'string') return value.trim().length > 0
      if (Array.isArray(value)) return value.length > 0
      return value !== null && value !== undefined
    }
  }),

  email: (message: string = 'Please enter a valid email address'): ValidationRule => ({
    type: 'email',
    message,
    validator: (value: string) => {
      if (!value) return true // Let required handle empty values
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
      return emailRegex.test(value)
    }
  }),

  minLength: (min: number, message?: string): ValidationRule => ({
    type: 'min',
    value: min,
    message: message || `Minimum length is ${min} characters`,
    validator: (value: string) => {
      if (!value) return true
      return value.length >= min
    }
  }),

  maxLength: (max: number, message?: string): ValidationRule => ({
    type: 'max',
    value: max,
    message: message || `Maximum length is ${max} characters`,
    validator: (value: string) => {
      if (!value) return true
      return value.length <= max
    }
  }),

  pattern: (regex: RegExp, message: string): ValidationRule => ({
    type: 'pattern',
    value: regex,
    message,
    validator: (value: string) => {
      if (!value) return true
      return regex.test(value)
    }
  }),

  numeric: (message: string = 'Please enter a valid number'): ValidationRule => ({
    type: 'pattern',
    message,
    validator: (value: string) => {
      if (!value) return true
      return !isNaN(Number(value))
    }
  }),

  nodeNumber: (message: string = 'Please enter a valid node number'): ValidationRule => ({
    type: 'pattern',
    message,
    validator: (value: string) => {
      if (!value) return true
      const num = Number(value)
      return !isNaN(num) && num > 0 && num <= 999999
    }
  }),

  ipAddress: (message: string = 'Please enter a valid IP address'): ValidationRule => ({
    type: 'pattern',
    message,
    validator: (value: string) => {
      if (!value) return true
      const ipRegex = /^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/
      return ipRegex.test(value)
    }
  }),

  port: (message: string = 'Please enter a valid port number (1-65535)'): ValidationRule => ({
    type: 'pattern',
    message,
    validator: (value: string) => {
      if (!value) return true
      const num = Number(value)
      return !isNaN(num) && num >= 1 && num <= 65535
    }
  }),

  url: (message: string = 'Please enter a valid URL'): ValidationRule => ({
    type: 'pattern',
    message,
    validator: (value: string) => {
      if (!value) return true
      try {
        new URL(value)
        return true
      } catch {
        return false
      }
    }
  }),

  custom: (validator: (value: any) => boolean, message: string): ValidationRule => ({
    type: 'custom',
    message,
    validator
  })
}

/**
 * Validate a single field
 */
export function validateField(value: any, rules: ValidationRule[]): string[] {
  const errors: string[] = []

  for (const rule of rules) {
    if (!rule.validator(value)) {
      errors.push(rule.message)
    }
  }

  return errors
}

/**
 * Validate multiple fields
 */
export function validateForm(
  values: Record<string, any>,
  rules: Record<string, ValidationRule[]>
): FormErrors {
  const errors: FormErrors = {}

  for (const [field, fieldRules] of Object.entries(rules)) {
    const fieldErrors = validateField(values[field], fieldRules)
    if (fieldErrors.length > 0) {
      errors[field] = fieldErrors
    }
  }

  return errors
}

/**
 * Check if form has errors
 */
export function hasFormErrors(errors: FormErrors): boolean {
  return Object.keys(errors).length > 0
}

/**
 * Get first error for a field
 */
export function getFirstError(errors: FormErrors, field: string): string | null {
  const fieldErrors = errors[field]
  return fieldErrors && fieldErrors.length > 0 ? fieldErrors[0] : null
}

/**
 * Clear errors for specific fields
 */
export function clearFieldErrors(errors: FormErrors, fields: string[]): FormErrors {
  const newErrors = { ...errors }
  fields.forEach(field => {
    delete newErrors[field]
  })
  return newErrors
}

/**
 * AllStar specific validators
 */
export const allstarValidators = {
  callsign: (message: string = 'Please enter a valid callsign'): ValidationRule => ({
    type: 'pattern',
    message,
    validator: (value: string) => {
      if (!value) return true
      // Basic callsign pattern (letters and numbers, 3-8 characters)
      const callsignRegex = /^[A-Z0-9]{3,8}$/i
      return callsignRegex.test(value)
    }
  }),

  frequency: (message: string = 'Please enter a valid frequency'): ValidationRule => ({
    type: 'pattern',
    message,
    validator: (value: string) => {
      if (!value) return true
      const freq = parseFloat(value)
      // Common amateur radio frequency ranges
      return !isNaN(freq) && freq >= 1.8 && freq <= 10000
    }
  }),

  ctcss: (message: string = 'Please enter a valid CTCSS tone'): ValidationRule => ({
    type: 'pattern',
    message,
    validator: (value: string) => {
      if (!value) return true
      const tone = parseFloat(value)
      // Standard CTCSS tones range
      return !isNaN(tone) && tone >= 67.0 && tone <= 254.1
    }
  })
}

/**
 * Async validation helper
 */
export async function validateAsync<T>(
  value: T,
  validator: (value: T) => Promise<boolean>,
  message: string
): Promise<string | null> {
  try {
    const isValid = await validator(value)
    return isValid ? null : message
  } catch (error) {
    return `Validation error: ${error.message}`
  }
}

/**
 * Form validation composable
 */
export function useFormValidation(
  initialValues: Record<string, any> = {},
  validationRules: Record<string, ValidationRule[]> = {}
) {
  const values = ref({ ...initialValues })
  const errors = ref<FormErrors>({})
  const touched = ref<Record<string, boolean>>({})

  const validate = (field?: string) => {
    if (field) {
      // Validate single field
      const fieldRules = validationRules[field] || []
      const fieldErrors = validateField(values.value[field], fieldRules)
      
      if (fieldErrors.length > 0) {
        errors.value[field] = fieldErrors
      } else {
        delete errors.value[field]
      }
    } else {
      // Validate all fields
      errors.value = validateForm(values.value, validationRules)
    }
  }

  const setFieldValue = (field: string, value: any) => {
    values.value[field] = value
    touched.value[field] = true
    
    // Validate field if it was previously touched or has errors
    if (touched.value[field] || errors.value[field]) {
      validate(field)
    }
  }

  const setFieldTouched = (field: string, isTouched = true) => {
    touched.value[field] = isTouched
    if (isTouched) {
      validate(field)
    }
  }

  const resetForm = () => {
    values.value = { ...initialValues }
    errors.value = {}
    touched.value = {}
  }

  const isValid = computed(() => !hasFormErrors(errors.value))
  const isDirty = computed(() => Object.keys(touched.value).length > 0)

  return {
    values,
    errors,
    touched,
    isValid,
    isDirty,
    validate,
    setFieldValue,
    setFieldTouched,
    resetForm,
    getFirstError: (field: string) => getFirstError(errors.value, field)
  }
}

// Add ref and computed imports
import { ref, computed } from 'vue'
