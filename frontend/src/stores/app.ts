import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { api } from '@/utils/api'
import type { User, UserPreferences, LoginResponse, AuthState } from '@/types'

export const useAppStore = defineStore('app', () => {
  // State
  const user = ref<User | null>(null)
  const isAuthenticated = ref(false)
  const loading = ref(false)
  const error = ref<string | null>(null)
  const initialized = ref(false)

  // Computed
  const hasPermission = computed(() => {
    return (permission: string): boolean => {
      if (!user.value || !user.value.permissions) return false
      return user.value.permissions[permission] === true
    }
  })

  const userPreferences = computed(() => {
    return user.value?.preferences || {
      showDetail: true,
      displayedNodes: 999,
      showCount: false,
      showAll: true
    }
  })

  // Actions
  const initialize = async () => {
    if (initialized.value) return
    
    loading.value = true
    error.value = null
    
    try {
      // Check authentication status
      await checkAuth()
      initialized.value = true
    } catch (err) {
      error.value = 'Failed to initialize application'
      console.error('App initialization error:', err)
    } finally {
      loading.value = false
    }
  }

  const checkAuth = async () => {
    try {
      const response = await api.get('/auth/me')
      
      if (response.data.success && response.data.data) {
        const data = response.data.data
        user.value = {
          ...data.user,
          permissions: data.permissions
        }
        isAuthenticated.value = data.authenticated
      } else {
        user.value = null
        isAuthenticated.value = false
      }
      return isAuthenticated.value
    } catch (err) {
      user.value = null
      isAuthenticated.value = false
      return false
    }
  }

  const login = async (username: string, password: string) => {
    loading.value = true
    error.value = null
    
    try {
      const response = await api.post('/auth/login', {
        username,
        password
      })
      
      if (response.data.success && response.data.data) {
        const data = response.data.data
        user.value = {
          ...data.user,
          permissions: data.permissions
        }
        isAuthenticated.value = data.authenticated
      }
      
      return response.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Login failed'
      throw err
    } finally {
      loading.value = false
    }
  }

  const logout = async () => {
    loading.value = true
    
    try {
      await api.post('/auth/logout')
    } catch (err) {
      console.error('Logout error:', err)
    } finally {
      user.value = null
      isAuthenticated.value = false
      loading.value = false
    }
  }

  const updatePreferences = async (preferences: Partial<UserPreferences>) => {
    if (!user.value) return
    
    try {
      const response = await api.put('/config/user/preferences', preferences)
      user.value.preferences = { ...user.value.preferences, ...preferences }
      
      // Save to cookies for persistence
      savePreferencesToCookies(user.value.preferences)
      
      return response.data
    } catch (err) {
      error.value = 'Failed to update preferences'
      throw err
    }
  }

  const savePreferencesToCookies = (preferences: UserPreferences) => {
    const cookieData = {
      'show-detailed': preferences.showDetail ? '1' : '0',
      'number-displayed': preferences.displayedNodes.toString(),
      'show-number': preferences.showCount ? '1' : '0',
      'show-all': preferences.showAll ? '1' : '0'
    }
    
    // Set cookies with long expiration
    const expireTime = 2147483645
    for (const [key, value] of Object.entries(cookieData)) {
      document.cookie = `display-data[${key}]=${value}; expires=${new Date(expireTime * 1000).toUTCString()}; path=/`
    }
  }

  const loadPreferencesFromCookies = (): UserPreferences => {
    const cookies = document.cookie.split(';').reduce((acc, cookie) => {
      const [key, value] = cookie.trim().split('=')
      if (key.startsWith('display-data[')) {
        const cleanKey = key.replace('display-data[', '').replace(']', '')
        acc[cleanKey] = value
      }
      return acc
    }, {} as Record<string, string>)
    
    return {
      showDetail: cookies['show-detailed'] !== '0',
      displayedNodes: parseInt(cookies['number-displayed']) || 999,
      showCount: cookies['show-number'] === '1',
      showAll: cookies['show-all'] !== '0'
    }
  }

  const clearError = () => {
    error.value = null
  }

  const reset = () => {
    user.value = null
    isAuthenticated.value = false
    loading.value = false
    error.value = null
    initialized.value = false
  }

  return {
    // State
    user,
    isAuthenticated,
    loading,
    error,
    initialized,
    
    // Computed
    hasPermission,
    userPreferences,
    
    // Actions
    initialize,
    checkAuth,
    login,
    logout,
    updatePreferences,
    savePreferencesToCookies,
    loadPreferencesFromCookies,
    clearError,
    reset
  }
})
