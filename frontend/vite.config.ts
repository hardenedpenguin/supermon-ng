import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': resolve(__dirname, 'src')
    }
  },
  server: {
    port: 5174,
    host: true,
    proxy: {
      '/api': {
        target: 'http://localhost:8000',
        changeOrigin: true,
        secure: false,
        cookieDomainRewrite: 'localhost',
        configure: (proxy, options) => {
          proxy.on('proxyReq', (proxyReq, req, res) => {
            console.log('🔐 Proxy request:', req.method, req.url)
          })
          proxy.on('proxyRes', (proxyRes, req, res) => {
            console.log('🔐 Proxy response:', proxyRes.statusCode, req.url)
            console.log('🔐 Proxy cookies:', proxyRes.headers['set-cookie'])
          })
        },
        onProxyReq: (proxyReq, req, res) => {
          // Forward cookies from browser to backend
          if (req.headers.cookie) {
            proxyReq.setHeader('Cookie', req.headers.cookie)
          }
        },
        onProxyRes: (proxyRes, req, res) => {
          // Forward cookies from backend to browser
          if (proxyRes.headers['set-cookie']) {
            res.setHeader('Set-Cookie', proxyRes.headers['set-cookie'])
          }
        }
      },
      '/server.php': {
        target: 'http://localhost:8000',
        changeOrigin: true,
        secure: false
      }
    }
  },
  build: {
    outDir: 'dist',
    sourcemap: true
  }
})
