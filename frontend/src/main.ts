import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import { initializeCsrfToken } from './utils/api'

// Import global styles
import './style.css'

const app = createApp(App)

app.use(createPinia())
app.use(router)

console.log('🚀 Supermon-ng Vue 3 App Started')
console.log('Environment:', import.meta.env.MODE)
console.log('API Base: /api')

// Initialize CSRF token
initializeCsrfToken().then(() => {
  console.log('✅ CSRF token initialized')
}).catch((error) => {
  console.error('❌ Failed to initialize CSRF token:', error)
})

app.mount('#app')


