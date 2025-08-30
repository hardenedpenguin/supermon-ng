import { ref, watch, readonly } from 'vue'

export interface Theme {
  name: string
  label: string
  colors: {
    primary: string
    text: string
    background: string
    container: string
    border: string
    input: string
    inputText: string
    tableHeader: string
    success: string
    warning: string
    error: string
    link: string
    menu: string
  }
}

// Define available themes
export const themes: Theme[] = [
  {
    name: 'dark',
    label: 'Dark Theme',
    colors: {
      primary: '#e0e0e0',
      text: '#e0e0e0',
      background: '#000000',
      container: '#2a2a2a',
      border: '#404040',
      input: '#1a1a1a',
      inputText: '#e0e0e0',
      tableHeader: '#404040',
      success: '#4caf50',
      warning: '#ff9800',
      error: '#f44336',
      link: '#2196f3',
      menu: '#2a2a2a'
    }
  },
  {
    name: 'light',
    label: 'Light Theme',
    colors: {
      primary: '#333333',
      text: '#333333',
      background: '#ffffff',
      container: '#f5f5f5',
      border: '#dddddd',
      input: '#ffffff',
      inputText: '#333333',
      tableHeader: '#e0e0e0',
      success: '#4caf50',
      warning: '#ff9800',
      error: '#f44336',
      link: '#2196f3',
      menu: '#f5f5f5'
    }
  },
  {
    name: 'blue',
    label: 'Blue Theme',
    colors: {
      primary: '#ffffff',
      text: '#ffffff',
      background: '#1e3a8a',
      container: '#1e40af',
      border: '#3b82f6',
      input: '#1e40af',
      inputText: '#ffffff',
      tableHeader: '#3b82f6',
      success: '#10b981',
      warning: '#f59e0b',
      error: '#ef4444',
      link: '#60a5fa',
      menu: '#1e40af'
    }
  },
  {
    name: 'green',
    label: 'Green Theme',
    colors: {
      primary: '#ffffff',
      text: '#ffffff',
      background: '#064e3b',
      container: '#065f46',
      border: '#059669',
      input: '#065f46',
      inputText: '#ffffff',
      tableHeader: '#059669',
      success: '#10b981',
      warning: '#f59e0b',
      error: '#ef4444',
      link: '#34d399',
      menu: '#065f46'
    }
  }
]

const currentTheme = ref<string>('dark')
const isLoaded = ref(false)

export function useTheme() {
  // Load theme from localStorage or use default
  const loadTheme = () => {
    const savedTheme = localStorage.getItem('supermon-theme')
    if (savedTheme && themes.find(t => t.name === savedTheme)) {
      currentTheme.value = savedTheme
    } else {
      currentTheme.value = 'dark' // Default to dark theme
    }
    applyTheme(currentTheme.value)
    isLoaded.value = true
  }

  // Apply theme to CSS variables
  const applyTheme = (themeName: string) => {
    const theme = themes.find(t => t.name === themeName)
    if (!theme) return

    const root = document.documentElement
    root.style.setProperty('--primary-color', theme.colors.primary)
    root.style.setProperty('--text-color', theme.colors.text)
    root.style.setProperty('--background-color', theme.colors.background)
    root.style.setProperty('--container-bg', theme.colors.container)
    root.style.setProperty('--border-color', theme.colors.border)
    root.style.setProperty('--input-bg', theme.colors.input)
    root.style.setProperty('--input-text', theme.colors.inputText)
    root.style.setProperty('--table-header-bg', theme.colors.tableHeader)
    root.style.setProperty('--success-color', theme.colors.success)
    root.style.setProperty('--warning-color', theme.colors.warning)
    root.style.setProperty('--error-color', theme.colors.error)
    root.style.setProperty('--link-color', theme.colors.link)
    root.style.setProperty('--menu-background', theme.colors.menu)
  }

  // Set theme
  const setTheme = (themeName: string) => {
    if (!themes.find(t => t.name === themeName)) return
    
    currentTheme.value = themeName
    localStorage.setItem('supermon-theme', themeName)
    applyTheme(themeName)
  }

  // Get current theme
  const getCurrentTheme = () => currentTheme.value

  // Get all available themes
  const getThemes = () => themes

  // Watch for theme changes
  watch(currentTheme, (newTheme) => {
    applyTheme(newTheme)
  })

  return {
    currentTheme: readonly(currentTheme),
    isLoaded: readonly(isLoaded),
    loadTheme,
    setTheme,
    getCurrentTheme,
    getThemes,
    themes
  }
}
