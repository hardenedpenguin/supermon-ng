import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'

/**
 * Injects modulepreload for the entry JS chunk only.
 * We do not preload CSS: Vite already emits <link rel="stylesheet"> for the main bundle,
 * and an extra rel=preload as=style duplicates fetch and triggers Chrome warnings
 * ("preloaded but not used within a few seconds").
 */
function preloadPlugin() {
  return {
    name: 'preload-critical-assets',
    transformIndexHtml: {
      order: 'post',
      handler(html: string) {
        const preloads: string[] = []
        const jsSrc = html.match(/<script[^>]+src="([^"]+\.js)"[^>]*>/)?.[1]
        if (jsSrc) preloads.push(`<link rel="modulepreload" href="${jsSrc}">`)
        if (preloads.length === 0) return html
        const headClose = html.indexOf('</head>')
        if (headClose === -1) return html
        return html.slice(0, headClose) + '\n    ' + preloads.join('\n    ') + '\n  ' + html.slice(headClose)
      }
    }
  }
}

// Relative base: one production build serves / and /supermon-ng (runtime base in basePath.ts).
// Dev proxy: optional VITE_APP_BASE_PATH or APP_BASE_PATH for local subdirectory testing.
function devProxyBase(): string {
  const raw = process.env.VITE_APP_BASE_PATH ?? process.env.APP_BASE_PATH ?? '/supermon-ng'
  const trimmed = raw.trim()
  if (trimmed === '' || trimmed === '/') {
    return ''
  }
  return `/${trimmed.replace(/^\/+|\/+$/g, '')}`
}

const devPrefix = devProxyBase()
const apiProxyPath = `${devPrefix}/api/v1`.replace(/\/+/g, '/') || '/api/v1'

const devProxy = {
  target: 'http://localhost:8000',
  changeOrigin: true,
  secure: false,
  cookieDomainRewrite: 'localhost',
  configure: (proxy: { on: (ev: string, fn: (...args: unknown[]) => void) => void }) => {
    proxy.on('proxyReq', (proxyReq: { setHeader: (k: string, v: string) => void }, req: { method?: string; url?: string; headers: { cookie?: string } }) => {
      if (req.headers.cookie) {
        proxyReq.setHeader('Cookie', req.headers.cookie)
      }
    })
    proxy.on('proxyRes', (_proxyRes: unknown, _req: unknown, res: { setHeader: (k: string, v: string | string[]) => void }) => {
      return res
    })
  }
}

const serverProxy: Record<string, typeof devProxy> = {
  [apiProxyPath]: devProxy
}
if (devPrefix) {
  serverProxy['/api/v1'] = { target: 'http://localhost:8000', changeOrigin: true, secure: false, cookieDomainRewrite: 'localhost' }
}

export default defineConfig({
  plugins: [vue(), preloadPlugin()],
  base: './',
  resolve: {
    alias: {
      '@': resolve(__dirname, 'src')
    }
  },
  server: {
    port: 5179,
    host: true,
    proxy: {
      ...serverProxy,
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
    rollupOptions: {
      output: {
        manualChunks: {
          vendor: ['vue', 'vue-router', 'pinia', 'axios'],
          dompurify: ['dompurify']
        }
      }
    }
  }
})
