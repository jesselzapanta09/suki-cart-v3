import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig(({ mode }) => {
  const isDev = mode === 'development'

  return {
    base: './', // IMPORTANT for Cordova (file:// support)

    plugins: [
      react(),
      tailwindcss(),
    ],

    build: {
      outDir: '../suki-mobile/www',
      emptyOutDir: true,
    },

    server: {
      host: '0.0.0.0',
      port: 3000,

      // ONLY for dev (ignored in production build)
      proxy: isDev
        ? {
            '/api': {
              target: 'http://192.168.1.105:8000', // 👈 your Laravel local IP
              changeOrigin: true,
            },
            '/storage': {
              target: 'http://192.168.1.105:8000',
              changeOrigin: true,
            },
          }
        : undefined,
    },
  }
})