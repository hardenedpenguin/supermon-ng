import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import './style.css'

// Create the Vue app
const app = createApp(App)

// Install plugins
app.use(createPinia())
app.use(router)

// Mount the app
app.mount('#app')

console.log('🚀 Supermon-ng Vue 3 App Started')
console.log('Environment:', import.meta.env.MODE)
console.log('API Base:', import.meta.env.VITE_API_BASE_URL || '/api')


