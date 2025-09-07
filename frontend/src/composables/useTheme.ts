import { ref, watch, readonly } from 'vue'

// Theme interface
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
    tableBg: string
    success: string
    warning: string
    error: string
    link: string
    menu: string
    localNodeBg: string
    localNodeText: string
    localNodeBorder: string
    localNodeHeader: string
    localNodeHeaderText: string
    // COS/PTT Status Colors for better visual distinction
    statusIdle: string
    statusPtt: string
    statusCos: string
    statusFullDuplex: string
    statusReceiving: string
  }
  isCustom?: boolean
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
      tableBg: '#1a1a1a',
      success: '#4caf50',
      warning: '#ff9800',
      error: '#f44336',
      link: '#2196f3',
      menu: '#2a2a2a',
      localNodeBg: '#2a2a2a',
      localNodeText: '#e0e0e0',
      localNodeBorder: '#404040',
      localNodeHeader: '#404040',
      localNodeHeaderText: '#e0e0e0',
      // COS/PTT Status Colors - theme-harmonious shades for dark theme
      statusIdle: '#2a2a2a',        // Idle - same as local node bg
      statusPtt: '#4a90e2',         // PTT-Keyed - bright blue
      statusCos: '#17a2b8',         // COS-Detected - teal (harmonious with dark theme)
      statusFullDuplex: '#e74c3c',  // Full-Duplex - red
      statusReceiving: '#9b59b6'    // Receiving - purple
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
      tableBg: '#ffffff',
      success: '#4caf50',
      warning: '#ff9800',
      error: '#f44336',
      link: '#2196f3',
      menu: '#f5f5f5',
      localNodeBg: '#f5f5f5',
      localNodeText: '#333333',
      localNodeBorder: '#dddddd',
      localNodeHeader: '#e0e0e0',
      localNodeHeaderText: '#333333',
      // COS/PTT Status Colors - theme-harmonious shades for light theme
      statusIdle: '#f5f5f5',        // Idle - same as local node bg
      statusPtt: '#3498db',         // PTT-Keyed - blue
      statusCos: '#138496',         // COS-Detected - darker teal (harmonious with light theme)
      statusFullDuplex: '#c0392b',  // Full-Duplex - darker red
      statusReceiving: '#8e44ad'    // Receiving - darker purple
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
      tableBg: '#1e1b4b',
      success: '#10b981',
      warning: '#f59e0b',
      error: '#ef4444',
      link: '#60a5fa',
      menu: '#1e40af',
      localNodeBg: '#1e40af',
      localNodeText: '#ffffff',
      localNodeBorder: '#3b82f6',
      localNodeHeader: '#3b82f6',
      localNodeHeaderText: '#ffffff',
      // COS/PTT Status Colors - theme-harmonious shades for blue theme
      statusIdle: '#1e40af',        // Idle - same as local node bg
      statusPtt: '#60a5fa',         // PTT-Keyed - lighter blue
      statusCos: '#06b6d4',         // COS-Detected - cyan (harmonious with blue theme)
      statusFullDuplex: '#ef4444',  // Full-Duplex - red
      statusReceiving: '#a855f7'    // Receiving - purple
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
      tableBg: '#022c22',
      success: '#10b981',
      warning: '#f59e0b',
      error: '#ef4444',
      link: '#34d399',
      menu: '#065f46',
      localNodeBg: '#065f46',
      localNodeText: '#ffffff',
      localNodeBorder: '#059669',
      localNodeHeader: '#059669',
      localNodeHeaderText: '#ffffff',
      // COS/PTT Status Colors - theme-harmonious shades for green theme
      statusIdle: '#065f46',        // Idle - same as local node bg
      statusPtt: '#34d399',         // PTT-Keyed - lighter green
      statusCos: '#0891b2',         // COS-Detected - teal-cyan (harmonious with green theme)
      statusFullDuplex: '#ef4444',  // Full-Duplex - red
      statusReceiving: '#a855f7'    // Receiving - purple
    }
  },
  {
    name: 'seafoam',
    label: 'Seafoam Green',
    colors: {
      primary: '#2d5a4a',
      text: '#2d5a4a',
      background: '#f5f5dc',
      container: '#e8f4f0',
      border: '#7fb3a3',
      input: '#ffffff',
      inputText: '#2d5a4a',
      tableHeader: '#7fb3a3',
      tableBg: '#f0f8f5',
      success: '#4caf50',
      warning: '#ff9800',
      error: '#f44336',
      link: '#2196f3',
      menu: '#e8f4f0',
      localNodeBg: '#e8f4f0',
      localNodeText: '#2d5a4a',
      localNodeBorder: '#7fb3a3',
      localNodeHeader: '#7fb3a3',
      localNodeHeaderText: '#ffffff',
      // COS/PTT Status Colors - theme-harmonious shades for seafoam theme
      statusIdle: '#e8f4f0',        // Idle - same as local node bg
      statusPtt: '#4a90a4',         // PTT-Keyed - darker teal
      statusCos: '#0d9488',         // COS-Detected - seafoam teal (perfectly harmonious)
      statusFullDuplex: '#a93226',  // Full-Duplex - dark red
      statusReceiving: '#7d3c98'    // Receiving - dark purple
    }
  },
  {
    name: 'neon-purple',
    label: 'Neon Purple',
    colors: {
      primary: '#e0b0ff',
      text: '#e0b0ff',
      background: '#000000',
      container: '#1a0a2e',
      border: '#8a2be2',
      input: '#1a0a2e',
      inputText: '#e0b0ff',
      tableHeader: '#8a2be2',
      tableBg: '#0d0519',
      success: '#00ff88',
      warning: '#ffaa00',
      error: '#ff0066',
      link: '#00ffff',
      menu: '#1a0a2e',
      localNodeBg: '#1a0a2e',
      localNodeText: '#e0b0ff',
      localNodeBorder: '#8a2be2',
      localNodeHeader: '#8a2be2',
      localNodeHeaderText: '#ffffff',
      // COS/PTT Status Colors - theme-harmonious neon shades for purple theme
      statusIdle: '#1a0a2e',        // Idle - same as local node bg
      statusPtt: '#00ffff',         // PTT-Keyed - bright cyan
      statusCos: '#00d4aa',         // COS-Detected - bright teal (harmonious with neon purple)
      statusFullDuplex: '#ff0066',  // Full-Duplex - bright magenta
      statusReceiving: '#00ff88'    // Receiving - bright green
    }
  }
]

const currentTheme = ref<string>('dark')
const isLoaded = ref(false)
const customThemes = ref<Theme[]>([])

export function useTheme() {
  // Load theme from localStorage or use default
  const loadTheme = () => {
    const savedTheme = localStorage.getItem('supermon-theme')
    const savedCustomThemes = localStorage.getItem('supermon-custom-themes')
    
    // Load custom themes
    if (savedCustomThemes) {
      try {
        customThemes.value = JSON.parse(savedCustomThemes)
      } catch (e) {
        console.error('Failed to load custom themes:', e)
        customThemes.value = []
      }
    }
    
    if (savedTheme && (themes.find(t => t.name === savedTheme) || customThemes.value.find(t => t.name === savedTheme))) {
      currentTheme.value = savedTheme
    } else {
      currentTheme.value = 'dark' // Default to dark theme
    }
    applyTheme(currentTheme.value)
    isLoaded.value = true
  }

  // Generate shade variations of a color
  const generateShades = (baseColor: string) => {
    // Convert hex to RGB
    const hex = baseColor.replace('#', '')
    const r = parseInt(hex.substr(0, 2), 16)
    const g = parseInt(hex.substr(2, 2), 16)
    const b = parseInt(hex.substr(4, 2), 16)
    
    // Generate lighter and darker shades
    const lighter = `rgb(${Math.min(255, r + 60)}, ${Math.min(255, g + 60)}, ${Math.min(255, b + 60)})`
    const light = `rgb(${Math.min(255, r + 30)}, ${Math.min(255, g + 30)}, ${Math.min(255, b + 30)})`
    const dark = `rgb(${Math.max(0, r - 30)}, ${Math.max(0, g - 30)}, ${Math.max(0, b - 30)})`
    const darker = `rgb(${Math.max(0, r - 60)}, ${Math.max(0, g - 60)}, ${Math.max(0, b - 60)})`
    
    return { lighter, light, dark, darker }
  }

  // Apply theme to CSS variables
  const applyTheme = (themeName: string) => {
    const allThemes = [...themes, ...customThemes.value]
    const theme = allThemes.find(t => t.name === themeName)
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
    root.style.setProperty('--table-bg', theme.colors.tableBg)
    root.style.setProperty('--success-color', theme.colors.success)
    root.style.setProperty('--warning-color', theme.colors.warning)
    root.style.setProperty('--error-color', theme.colors.error)
    root.style.setProperty('--link-color', theme.colors.link)
    root.style.setProperty('--menu-background', theme.colors.menu)
    
    // Apply local node table specific colors
    root.style.setProperty('--local-node-bg', theme.colors.localNodeBg)
    root.style.setProperty('--local-node-text', theme.colors.localNodeText)
    root.style.setProperty('--local-node-border', theme.colors.localNodeBorder)
    root.style.setProperty('--local-node-header', theme.colors.localNodeHeader)
    root.style.setProperty('--local-node-header-text', theme.colors.localNodeHeaderText)
    
    // Apply COS/PTT status colors
    root.style.setProperty('--status-idle', theme.colors.statusIdle)
    root.style.setProperty('--status-ptt', theme.colors.statusPtt)
    root.style.setProperty('--status-cos', theme.colors.statusCos)
    root.style.setProperty('--status-full-duplex', theme.colors.statusFullDuplex)
    root.style.setProperty('--status-receiving', theme.colors.statusReceiving)
    
    // Generate and apply primary color shades for status indicators
    const shades = generateShades(theme.colors.primary)
    root.style.setProperty('--primary-color-lighter', shades.lighter)
    root.style.setProperty('--primary-color-light', shades.light)
    root.style.setProperty('--primary-color-dark', shades.dark)
    root.style.setProperty('--primary-color-darker', shades.darker)
  }

  // Set theme
  const setTheme = (themeName: string) => {
    const allThemes = [...themes, ...customThemes.value]
    if (!allThemes.find(t => t.name === themeName)) return
    
    currentTheme.value = themeName
    localStorage.setItem('supermon-theme', themeName)
    applyTheme(themeName)
  }

  // Create custom theme
  const createCustomTheme = (themeData: Omit<Theme, 'name' | 'isCustom'>) => {
    const customTheme: Theme = {
      ...themeData,
      name: `custom-${Date.now()}`,
      isCustom: true
    }
    
    customThemes.value.push(customTheme)
    localStorage.setItem('supermon-custom-themes', JSON.stringify(customThemes.value))
    
    return customTheme
  }

  // Update custom theme
  const updateCustomTheme = (themeName: string, themeData: Partial<Theme>) => {
    const themeIndex = customThemes.value.findIndex(t => t.name === themeName)
    if (themeIndex === -1) return false
    
    customThemes.value[themeIndex] = { ...customThemes.value[themeIndex], ...themeData }
    localStorage.setItem('supermon-custom-themes', JSON.stringify(customThemes.value))
    
    // If this is the current theme, reapply it
    if (currentTheme.value === themeName) {
      applyTheme(themeName)
    }
    
    return true
  }

  // Delete custom theme
  const deleteCustomTheme = (themeName: string) => {
    const themeIndex = customThemes.value.findIndex(t => t.name === themeName)
    if (themeIndex === -1) return false
    
    customThemes.value.splice(themeIndex, 1)
    localStorage.setItem('supermon-custom-themes', JSON.stringify(customThemes.value))
    
    // If this was the current theme, switch to dark theme
    if (currentTheme.value === themeName) {
      setTheme('dark')
    }
    
    return true
  }

  // Get current theme
  const getCurrentTheme = () => currentTheme.value

  // Get all available themes (built-in + custom)
  const getAllThemes = () => [...themes, ...customThemes.value]

  // Get built-in themes only
  const getBuiltInThemes = () => themes

  // Get custom themes only
  const getCustomThemes = () => customThemes.value

  // Watch for theme changes
  watch(currentTheme, (newTheme) => {
    applyTheme(newTheme)
  })

  return {
    currentTheme: readonly(currentTheme),
    isLoaded: readonly(isLoaded),
    customThemes: readonly(customThemes),
    loadTheme,
    setTheme,
    createCustomTheme,
    updateCustomTheme,
    deleteCustomTheme,
    getCurrentTheme,
    getAllThemes,
    getBuiltInThemes,
    getCustomThemes,
    themes
  }
}
