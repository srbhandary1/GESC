import react from '@vitejs/plugin-react'
import { configDefaults, defineConfig } from 'vitest/config'
import { demoAzureTtsPlugin } from './vite-plugins/demoAzureTts'

// https://vite.dev/config/
// The explicit `demo` mode produces the disclosed synthetic demo
// distribution in `dist-demo` with relative asset paths so the same
// bundle works from static hosting and the Capacitor Android wrapper.
// The ordinary production build (`dist`) is unchanged and can never
// activate the demo runtime.
// Demo-only Azure TTS (`/demo/tts`) is a local presentation middleware;
// credentials stay in process env and are never shipped in the bundle.
export default defineConfig(({ mode }) => ({
  base: mode === 'demo' ? './' : '/',
  build: {
    outDir: mode === 'demo' ? 'dist-demo' : 'dist',
  },
  plugins: [react(), demoAzureTtsPlugin()],
  server: {
    host: '127.0.0.1',
    port: 5173,
    strictPort: true,
    proxy: {
      '/api': {
        target: 'http://127.0.0.1:8000',
        changeOrigin: false,
      },
      '/sanctum': {
        target: 'http://127.0.0.1:8000',
        changeOrigin: false,
      },
    },
  },
  test: {
    environment: 'jsdom',
    exclude: [...configDefaults.exclude, 'e2e/**', 'e2e-demo/**'],
    setupFiles: './src/test/setup.ts',
  },
}))
