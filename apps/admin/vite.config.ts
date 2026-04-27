import path from 'node:path';
import { fileURLToPath } from 'node:url';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import { defineConfig } from 'vite';

// `__dirname` is not defined in ESM (`"type": "module"` in package.json) — derive it
// from `import.meta.url` so the @/* alias resolves correctly under both Vite dev
// and Vite build.
const here = path.dirname(fileURLToPath(import.meta.url));

// HMR through Caddy reverse proxy: WebSocket upgrades on the same single origin (pim.localhost).
// The Vite dev server listens on 0.0.0.0:5173 inside the container; Caddy forwards / and the
// HMR socket. See Caddyfile in repo root.
export default defineConfig({
  plugins: [react(), tailwindcss()],
  resolve: {
    alias: {
      '@': path.resolve(here, './src'),
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
