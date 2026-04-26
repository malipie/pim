import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// HMR through Caddy reverse proxy: WebSocket upgrades on the same single origin (pim.localhost).
// The Vite dev server listens on 0.0.0.0:5173 inside the container; Caddy forwards / and the
// HMR socket. See Caddyfile in repo root.
export default defineConfig({
  plugins: [react()],
  server: {
    host: '0.0.0.0',
    port: 5173,
    strictPort: true,
    hmr: {
      // Caddy terminates TLS; let the browser connect on the public origin.
      clientPort: 443,
      protocol: 'wss',
      host: 'pim.localhost',
    },
    watch: {
      usePolling: true,
    },
  },
})
