import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

function vendorChunk(id) {
  if (!id.includes('node_modules')) {
    return undefined
  }

  if (id.includes('lucide-react') || id.includes('@heroicons')) return 'vendor-icons'
  if (id.includes('recharts') || id.includes('d3-')) return 'vendor-charts'
  if (id.includes('socket.io-client') || id.includes('engine.io-client')) return 'vendor-realtime'

  return 'vendor-core'
}

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    host: '127.0.0.1',
    proxy: {
      '/api': 'http://127.0.0.1:8000',
    },
  },
  build: {
    rollupOptions: {
      output: {
        manualChunks(id) {
          return vendorChunk(id)
        },
      },
    },
  },
})
