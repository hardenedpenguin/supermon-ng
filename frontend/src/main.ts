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

// Initialize CSRF token
initializeCsrfToken().catch((error) => {
  console.error('❌ Failed to initialize CSRF token:', error)
})

app.mount('#app')


