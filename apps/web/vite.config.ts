import react from '@vitejs/plugin-react'
import { configDefaults, defineConfig } from 'vitest/config'
import { calculateWebBuildFingerprint } from './buildFingerprint.ts'
import { demoAzureTtsPlugin } from './vite-plugins/demoAzureTts.ts'
import { demoOpenRouterChatPlugin } from './vite-plugins/demoOpenRouterChat.ts'

// https://vite.dev/config/
// The explicit `demo` mode produces the disclosed synthetic demo
// distribution in `dist-demo` with relative asset paths so the same
// bundle works from static hosting and the Capacitor Android wrapper.
// The ordinary production build (`dist`) is unchanged and can never
// activate the demo runtime.
// Demo-only Azure TTS (`/demo/tts`) and OpenRouter chat (`/demo/chat`) are
// local presentation middleware; credentials stay in process env and are
// never shipped in the bundle.
export default defineConfig(({ mode }) => {
  const publicShellRelease = calculateWebBuildFingerprint()

  return {
    base: mode === 'demo' ? './' : '/',
    build: {
      outDir: mode === 'demo' ? 'dist-demo' : 'dist',
      rollupOptions: {
        output: {
          manualChunks(id) {
            if (id.includes('/src/operator/')) {
              return 'operator-console'
            }
            if (id.includes('/src/marketing/')) {
              return 'marketing-landing'
            }
            return undefined
          },
        },
      },
    },
    define: {
      'import.meta.env.VITE_PUBLIC_SHELL_RELEASE': JSON.stringify(
        publicShellRelease,
      ),
    },
    plugins: [react(), demoAzureTtsPlugin(), demoOpenRouterChatPlugin()],
    server: {
      host: '127.0.0.1',
      port: 5174,
      strictPort: true,
      allowedHosts: [
        'localhost',
        '127.0.0.1',
        '192.168.1.3',
        '100.89.51.18',
        '117.254.108.143',
        'real-studioz',
        '.ts.net',
        '.trycloudflare.com',
      ],
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
  }
})
