import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'

// Import global styles
import './style.css'

const app = createApp(App)

app.use(createPinia())
app.use(router)

console.log('🚀 Supermon-ng Vue 3 App Started')
console.log('Environment:', import.meta.env.MODE)
console.log('API Base: /api')

app.mount('#app')


