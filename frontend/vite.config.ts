import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import { VitePWA } from 'vite-plugin-pwa'

export default defineConfig({
  plugins: [
    react(),
    VitePWA({
      registerType: 'autoUpdate',
      includeAssets: ['favicon.ico', 'favicon.svg', 'apple-touch-icon.png', 'logo.svg'],
      manifest: {
        name: 'RescueLens',
        short_name: 'RescueLens',
        description: 'AI assisted community emergency reporting for Bangladesh',
        lang: 'bn',
        theme_color: '#1877F2',
        background_color: '#F0F2F5',
        display: 'standalone',
        start_url: '/feed',
        icons: [
          { src: '/icons/icon-192.png', sizes: '192x192', type: 'image/png' },
          { src: '/icons/icon-512.png', sizes: '512x512', type: 'image/png' },
          { src: '/icons/icon-maskable-512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
        ],
      },
      workbox: {
        navigateFallback: '/index.html',
        runtimeCaching: [
          { urlPattern: /\/api\/v1\/(incidents|incidents\/map|stats|emergency-resources)(\?.*)?$/, handler: 'NetworkFirst', options: { cacheName: 'rl-api', networkTimeoutSeconds: 4, expiration: { maxEntries: 50, maxAgeSeconds: 3600 } } },
          { urlPattern: /^https:\/\/[abc]?\.?tile\.openstreetmap\.org\//, handler: 'CacheFirst', options: { cacheName: 'osm-tiles', expiration: { maxEntries: 300, maxAgeSeconds: 604800 } } },
        ],
      },
    }),
  ],
  build: {
    rollupOptions: { output: { manualChunks: (id) => (id.includes('leaflet') ? 'map' : id.includes('node_modules') ? 'vendor' : undefined) } },
  },
  server: { port: 5173 },
})
