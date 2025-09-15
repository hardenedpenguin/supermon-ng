<template>
  <div class="login-page">
    <div class="login-background">
      <div class="login-overlay"></div>
    </div>
    
    <div class="login-container">
      <div class="login-header">
        <div class="logo">
          <img src="/allstarlink.jpg" alt="Supermon-ng" class="logo-image" />
        </div>
        <h1 class="login-title">Supermon-ng</h1>
        <p class="login-subtitle">AllStar Link Node Monitoring System</p>
      </div>
      
      <form @submit.prevent="handleLogin" class="login-form">
        <div class="form-group">
          <label for="username" class="form-label">Username</label>
          <div class="input-wrapper">
            <input
              id="username"
              v-model="username"
              type="text"
              required
              class="form-input"
              :class="{ 'error': usernameError }"
              placeholder="Enter your username"
              @input="clearUsernameError"
              @blur="validateUsername"
            />
            <div class="input-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                <circle cx="12" cy="7" r="4"></circle>
              </svg>
            </div>
          </div>
          <span v-if="usernameError" class="error-text">{{ usernameError }}</span>
        </div>
        
        <div class="form-group">
          <label for="password" class="form-label">Password</label>
          <div class="input-wrapper">
            <input
              id="password"
              v-model="password"
              :type="showPassword ? 'text' : 'password'"
              required
              class="form-input"
              :class="{ 'error': passwordError }"
              placeholder="Enter your password"
              @input="clearPasswordError"
              @blur="validatePassword"
            />
            <div class="input-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <circle cx="12" cy="16" r="1"></circle>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
              </svg>
            </div>
            <button 
              type="button" 
              class="password-toggle"
              @click="togglePassword"
              :aria-label="showPassword ? 'Hide password' : 'Show password'"
            >
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path v-if="showPassword" d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                <path v-else d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                <circle v-if="!showPassword" cx="12" cy="12" r="3"></circle>
                <line v-if="showPassword" x1="1" y1="1" x2="23" y2="23"></line>
              </svg>
            </button>
          </div>
          <span v-if="passwordError" class="error-text">{{ passwordError }}</span>
        </div>
        
        <div class="form-actions">
          <button 
            type="submit" 
            class="login-button"
            :disabled="loading || !isFormValid"
            :class="{ 'loading': loading }"
          >
            <span v-if="loading" class="loading-spinner"></span>
            <span v-else>Sign In</span>
          </button>
        </div>
        
        <div v-if="error" class="error-message">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="15" y1="9" x2="9" y2="15"></line>
            <line x1="9" y1="9" x2="15" y2="15"></line>
          </svg>
          {{ error }}
        </div>
      </form>
      
      <div class="login-footer">
        <p class="footer-text">
          Need help? Contact your system administrator
        </p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAppStore } from '@/stores/app'

const router = useRouter()
const appStore = useAppStore()

const username = ref('')
const password = ref('')
const loading = ref(false)
const error = ref('')
const showPassword = ref(false)
const usernameError = ref('')
const passwordError = ref('')

const isFormValid = computed(() => {
  return username.value.trim() && password.value.trim() && !usernameError.value && !passwordError.value
})

const validateUsername = () => {
  if (!username.value.trim()) {
    usernameError.value = 'Username is required'
  } else if (username.value.length < 2) {
    usernameError.value = 'Username must be at least 2 characters'
  } else {
    usernameError.value = ''
  }
}

const validatePassword = () => {
  if (!password.value.trim()) {
    passwordError.value = 'Password is required'
  } else if (password.value.length < 1) {
    passwordError.value = 'Password is required'
  } else {
    passwordError.value = ''
  }
}

const clearUsernameError = () => {
  if (usernameError.value) {
    usernameError.value = ''
  }
}

const clearPasswordError = () => {
  if (passwordError.value) {
    passwordError.value = ''
  }
}

const togglePassword = () => {
  showPassword.value = !showPassword.value
}

const handleLogin = async () => {
  // Validate form
  validateUsername()
  validatePassword()
  
  if (!isFormValid.value) {
    return
  }
  
  loading.value = true
  error.value = ''
  
  try {
    await appStore.login(username.value.trim(), password.value)
    
    // Redirect to the intended page or dashboard
    const redirectPath = router.currentRoute.value.query.redirect as string
    router.push(redirectPath || '/')
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Login failed. Please check your credentials.'
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.login-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  background: linear-gradient(135deg, #1a1a1a 0%, #2a2a2a 100%);
}

.login-background {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-image: url('/supermon-ng/background.jpg');
  background-size: cover;
  background-position: center;
  background-repeat: no-repeat;
  opacity: 0.1;
  z-index: 0;
}

.login-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: linear-gradient(135deg, rgba(26, 26, 26, 0.9) 0%, rgba(42, 42, 42, 0.9) 100%);
  z-index: 1;
}

.login-container {
  position: relative;
  z-index: 2;
  background: rgba(42, 42, 42, 0.95);
  backdrop-filter: blur(10px);
  border: 1px solid rgba(64, 64, 64, 0.3);
  border-radius: 16px;
  padding: 40px;
  width: 100%;
  max-width: 450px;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
}

.login-header {
  text-align: center;
  margin-bottom: 40px;
}

.logo {
  margin-bottom: 20px;
}

.logo-image {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  object-fit: cover;
  border: 3px solid var(--primary-color);
}

.login-title {
  font-size: 2.5rem;
  font-weight: 700;
  color: var(--primary-color);
  margin: 0 0 8px 0;
  text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}

.login-subtitle {
  font-size: 1rem;
  color: #a0a0a0;
  margin: 0;
  font-weight: 400;
}

.login-form {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.form-label {
  font-weight: 600;
  color: var(--text-color);
  font-size: 0.9rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.input-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}

.form-input {
  width: 100%;
  padding: 16px 48px 16px 48px;
  border: 2px solid rgba(64, 64, 64, 0.5);
  border-radius: 12px;
  background: rgba(26, 26, 26, 0.8);
  color: var(--text-color);
  font-size: 16px;
  transition: all 0.3s ease;
  backdrop-filter: blur(5px);
}

.form-input:focus {
  outline: none;
  border-color: var(--primary-color);
  box-shadow: 0 0 0 4px rgba(224, 224, 224, 0.1);
  background: rgba(26, 26, 26, 0.9);
}

.form-input.error {
  border-color: var(--error-color);
  box-shadow: 0 0 0 4px rgba(244, 67, 54, 0.1);
}

.form-input::placeholder {
  color: #666;
}

.input-icon {
  position: absolute;
  left: 16px;
  color: #666;
  pointer-events: none;
}

.password-toggle {
  position: absolute;
  right: 16px;
  background: none;
  border: none;
  color: #666;
  cursor: pointer;
  padding: 4px;
  border-radius: 4px;
  transition: color 0.3s ease;
}

.password-toggle:hover {
  color: var(--primary-color);
}

.error-text {
  color: var(--error-color);
  font-size: 0.85rem;
  margin-top: 4px;
}

.form-actions {
  margin-top: 8px;
}

.login-button {
  width: 100%;
  padding: 16px;
  background: linear-gradient(135deg, var(--primary-color) 0%, #d0d0d0 100%);
  color: #1a1a1a;
  border: none;
  border-radius: 12px;
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.login-button:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(224, 224, 224, 0.3);
}

.login-button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
}

.login-button.loading {
  cursor: wait;
}

.loading-spinner {
  width: 20px;
  height: 20px;
  border: 2px solid transparent;
  border-top: 2px solid #1a1a1a;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.error-message {
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--error-color);
  text-align: center;
  padding: 16px;
  background: rgba(244, 67, 54, 0.1);
  border: 1px solid rgba(244, 67, 54, 0.3);
  border-radius: 12px;
  font-size: 0.9rem;
}

.login-footer {
  margin-top: 32px;
  text-align: center;
}

.footer-text {
  color: #666;
  font-size: 0.85rem;
  margin: 0;
}

/* Responsive design */
@media (max-width: 480px) {
  .login-container {
    margin: 20px;
    padding: 30px 24px;
  }
  
  .login-title {
    font-size: 2rem;
  }
  
  .form-input {
    padding: 14px 44px 14px 44px;
  }
}
</style>
