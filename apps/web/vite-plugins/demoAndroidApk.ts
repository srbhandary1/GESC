import type { Plugin } from 'vite'
import { attachDemoAndroidApkMiddleware } from './demoAndroidApkHandler.mjs'

/**
 * Serve the demo debug APK at the existing Home download URL
 * `/downloads/global-english-demo.apk` during `make demo` / preview.
 * The file is the Gradle output — never committed.
 */
export function demoAndroidApkPlugin(): Plugin {
  return {
    name: 'demo-android-apk',
    configureServer(server) {
      attachDemoAndroidApkMiddleware(server.middlewares)
    },
    configurePreviewServer(server) {
      attachDemoAndroidApkMiddleware(server.middlewares)
    },
  }
}
