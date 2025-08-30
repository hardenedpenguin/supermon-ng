import { ref, watch, onMounted } from 'vue'

export interface Theme {
  name: string
  label: string
  description: string
  preview: string
}

export const availableThemes: Theme[] = [
  {
    name: 'dark',
    label: 'Dark',
    description: 'Default dark theme with high contrast',
    preview: '#000000'
  },
  {
    name: 'light',
    label: 'Light',
    description: 'Clean light theme for daytime use',
    preview: '#f5f5f5'
  },
  {
    name: 'blue',
    label: 'Blue',
    description: 'Professional blue theme',
    preview: '#1e3a8a'
  },
  {
    name: 'green',
    label: 'Green',
    description: 'Nature-inspired green theme',
    preview: '#065f46'
  },
  {
    name: 'purple',
    label: 'Purple',
    description: 'Elegant purple theme',
    preview: '#581c87'
  },
  {
    name: 'red',
    label: 'Red',
    description: 'Bold red theme',
    preview: '#7f1d1d'
  },
  {
    name: 'orange',
    label: 'Orange',
    description: 'Warm orange theme',
    preview: '#9a3412'
  },
  {
    name: 'custom',
    label: 'Custom',
    description: 'User-defined custom theme',
    preview: '#2a2a2a'
  }
]

export function useTheme() {
  const currentTheme = ref<string>('dark')
  const isCustomTheme = ref<boolean>(false)

  // Load theme from localStorage
  const loadTheme = () => {
    const savedTheme = localStorage.getItem('supermon-theme')
    if (savedTheme) {
      currentTheme.value = savedTheme
    }
    
    // Apply theme to document
    applyTheme(currentTheme.value)
  }

  // Save theme to localStorage
  const saveTheme = (theme: string) => {
    localStorage.setItem('supermon-theme', theme)
  }

  // Apply theme to document
  const applyTheme = (theme: string) => {
    console.log('Applying theme:', theme)
    const html = document.documentElement
    html.setAttribute('data-theme', theme)
    console.log('Document data-theme attribute set to:', html.getAttribute('data-theme'))
    
    // Debug: Check if CSS variables are being applied
    setTimeout(() => {
      const computedStyle = getComputedStyle(document.documentElement)
      console.log('CSS Variables after theme change:')
      console.log('--background-color:', computedStyle.getPropertyValue('--background-color'))
      console.log('--text-color:', computedStyle.getPropertyValue('--text-color'))
      console.log('--primary-color:', computedStyle.getPropertyValue('--primary-color'))
    }, 100)
    
    // If custom theme, load custom CSS
    if (theme === 'custom') {
      loadCustomTheme()
    }
  }

  // Load custom theme CSS
  const loadCustomTheme = () => {
    // Check if custom CSS file exists
    const customCSS = localStorage.getItem('supermon-custom-css')
    if (customCSS) {
      // Remove existing custom style tag
      const existingStyle = document.getElementById('custom-theme-style')
      if (existingStyle) {
        existingStyle.remove()
      }
      
      // Create new style tag with custom CSS
      const style = document.createElement('style')
      style.id = 'custom-theme-style'
      style.textContent = customCSS
      document.head.appendChild(style)
    }
  }

  // Set custom theme variables
  const setCustomThemeVariables = (variables: Record<string, string>) => {
    const css = Object.entries(variables)
      .map(([key, value]) => `--${key}: ${value};`)
      .join('\n')
    
    const customCSS = `
      [data-theme="custom"] {
        ${css}
      }
    `
    
    localStorage.setItem('supermon-custom-css', customCSS)
    
    if (currentTheme.value === 'custom') {
      loadCustomTheme()
    }
  }

  // Change theme
  const changeTheme = (theme: string) => {
    console.log('Changing theme to:', theme)
    currentTheme.value = theme
    applyTheme(theme)
    saveTheme(theme)
    isCustomTheme.value = theme === 'custom'
    console.log('Theme changed, current theme:', currentTheme.value)
  }

  // Get current theme info
  const getCurrentTheme = () => {
    return availableThemes.find(theme => theme.name === currentTheme.value)
  }

  // Watch for theme changes
  watch(currentTheme, (newTheme) => {
    applyTheme(newTheme)
    saveTheme(newTheme)
  })

  // Initialize theme on mount
  onMounted(() => {
    loadTheme()
  })

  return {
    currentTheme,
    availableThemes,
    isCustomTheme,
    changeTheme,
    getCurrentTheme,
    setCustomThemeVariables,
    loadCustomTheme,
    loadTheme
  }
}
