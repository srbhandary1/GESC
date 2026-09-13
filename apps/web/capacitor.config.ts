import type { CapacitorConfig } from '@capacitor/cli'
import { resolveAndroidRemoteServer } from './src/auth/androidRemoteOrigin'

/**
 * Dual Capacitor configuration.
 *
 * Default (`make demo-android`): presentation demo at `android/`,
 * `com.globalenglish.demo`, packaging `dist-demo` only (D-0078).
 *
 * `make demo-android-remote` sets `GE_ANDROID_REMOTE_ORIGIN` so the demo
 * WebView loads the staff static-IP host (`http://117.254.108.143:8080`).
 *
 * Production closed beta (`CAPACITOR_APP=production` / `make android-closed-beta`):
 * separate tree at `android-app/`, `com.globalenglish.app`, packaging the
 * ordinary product `dist/` build. Never overwrites the demo identity.
 */
const isProductionAndroid = process.env.CAPACITOR_APP === 'production'
const demoRemoteServer = resolveAndroidRemoteServer(
  process.env.GE_ANDROID_REMOTE_ORIGIN,
)

const demoConfig: CapacitorConfig = {
  appId: 'com.globalenglish.demo',
  appName: 'Global English Speaking Course',
  webDir: 'dist-demo',
  android: {
    path: 'android',
  },
  // Bundled APK must stay on https://localhost so getUserMedia is a
  // secure context. HTTP live-load (demo-android-remote) cannot record.
  server:
    demoRemoteServer ??
    ({
      androidScheme: 'https',
      hostname: 'localhost',
    } satisfies NonNullable<CapacitorConfig['server']>),
}

const productionConfig: CapacitorConfig = {
  appId: 'com.globalenglish.app',
  appName: 'Global English',
  webDir: 'dist',
  android: {
    path: 'android-app',
  },
  plugins: {
    // Production package uses project-owned secure-credential and system-browser
    // plugins; ordinary Preferences must never hold auth credentials (ADR-0012).
  },
}

const config: CapacitorConfig = isProductionAndroid
  ? productionConfig
  : demoConfig

export default config
