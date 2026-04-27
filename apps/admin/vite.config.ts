import path from 'node:path';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import { defineConfig } from 'vite';

// HMR through Caddy reverse proxy: WebSocket upgrades on the same single origin (pim.localhost).
// The Vite dev server listens on 0.0.0.0:5173 inside the container; Caddy forwards / and the
// HMR socket. See Caddyfile in repo root.
export default defineConfig({
  plugins: [react(), tailwindcss()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
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
});
