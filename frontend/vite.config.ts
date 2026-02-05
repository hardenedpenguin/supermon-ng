import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'

/** Injects preload/modulepreload for critical JS and CSS to speed initial load */
function preloadPlugin() {
  return {
    name: 'preload-critical-assets',
    transformIndexHtml: {
      order: 'post',
      handler(html: string) {
        const preloads: string[] = []
        const cssHref = html.match(/<link[^>]+href="([^"]+\.css)"[^>]*>/)?.[1]
        const jsSrc = html.match(/<script[^>]+src="([^"]+\.js)"[^>]*>/)?.[1]
        if (cssHref) preloads.push(`<link rel="preload" href="${cssHref}" as="style">`)
        if (jsSrc) preloads.push(`<link rel="modulepreload" href="${jsSrc}">`)
        if (preloads.length === 0) return html
        const headClose = html.indexOf('</head>')
        if (headClose === -1) return html
        return html.slice(0, headClose) + '\n    ' + preloads.join('\n    ') + '\n  ' + html.slice(headClose)
      }
    }
  }
}

export default defineConfig({
  plugins: [vue(), preloadPlugin()],
  base: '/supermon-ng/',
  resolve: {
    alias: {
      '@': resolve(__dirname, 'src')
    }
  },
  server: {
    port: 5179,
    host: true,
    proxy: {
      '/supermon-ng/api': {
        target: 'http://localhost:8000',
        changeOrigin: true,
        secure: false,
        cookieDomainRewrite: 'localhost',
        configure: (proxy, options) => {
          proxy.on('proxyReq', (proxyReq, req, res) => {
            console.log('🔐 Proxy request:', req.method, req.url)
            // Forward cookies from browser to backend
            if (req.headers.cookie) {
              proxyReq.setHeader('Cookie', req.headers.cookie)
            }
          })
          proxy.on('proxyRes', (proxyRes, req, res) => {
            console.log('🔐 Proxy response:', proxyRes.statusCode, req.url)
            console.log('🔐 Proxy cookies:', proxyRes.headers['set-cookie'])
            // Forward cookies from backend to browser
            if (proxyRes.headers['set-cookie']) {
              res.setHeader('Set-Cookie', proxyRes.headers['set-cookie'])
            }
          })
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
    sourcemap: true,
    // CSS for lazy-loaded components is emitted in their chunks (deferred until modal opens)
  }
})
