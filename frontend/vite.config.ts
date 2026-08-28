import path from 'node:path'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'
import { defineConfig, loadEnv } from 'vite'

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  const allowedHosts = (env.VITE_ALLOWED_HOSTS || '')
    .split(',')
    .map((host) => host.trim())
    .filter(Boolean)
  const backendProxyTarget = env.VITE_BACKEND_PROXY_TARGET || 'http://127.0.0.1:8000'

  // Strip backend CORS headers — requests arrive same-origin through the
  // proxy, so forwarding `access-control-*` headers can confuse Chrome's
  // cookie / credentialed-request logic.
  const stripCorsHeaders = (proxyRes: { headers: Record<string, unknown> }) => {
    delete proxyRes.headers['access-control-allow-origin']
    delete proxyRes.headers['access-control-allow-credentials']
    delete proxyRes.headers['access-control-expose-headers']
  }

  const proxyOpts = {
    target: backendProxyTarget,
    changeOrigin: true,
    cookieDomainRewrite: { '*': '' },
    onProxyRes: stripCorsHeaders,
  }

  return {
    plugins: [react(), tailwindcss()],
    server: {
      port: 5173,
      allowedHosts,
      proxy: {
        '/api': proxyOpts,
        '/sanctum': proxyOpts,
      },
    },
    resolve: {
      alias: {
        '@': path.resolve(import.meta.dirname, './src'),
      },
    },
  }
})
