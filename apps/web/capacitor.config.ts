import type { CapacitorConfig } from '@capacitor/cli'

/**
 * Dual Capacitor configuration.
 *
 * Default (`make demo-android`): presentation demo at `android/`,
 * `com.globalenglish.demo`, packaging `dist-demo` only (D-0078).
 *
 * Production closed beta (`CAPACITOR_APP=production` / `make android-closed-beta`):
 * separate tree at `android-app/`, `com.globalenglish.app`, packaging the
 * ordinary product `dist/` build. Never overwrites the demo identity.
 */
const isProductionAndroid = process.env.CAPACITOR_APP === 'production'

const demoConfig: CapacitorConfig = {
  appId: 'com.globalenglish.demo',
  appName: 'Global Spoken English App',
  webDir: 'dist-demo',
  android: {
    path: 'android',
  },
  // Bundled APK must stay on https://localhost so getUserMedia is a
  // secure context. Do not live-load http://117.254.108.143:8080 in the
  // WebView — that origin cannot record.
  server: {
    androidScheme: 'https',
    hostname: 'localhost',
  },
}

const productionConfig: CapacitorConfig = {
  appId: 'com.globalenglish.app',
  appName: 'Global Spoken English App',
  webDir: 'dist',
  android: {
    path: 'android-app',
  },
  plugins: {
    // Production package uses project-owned secure-credential and system-browser
    // plugins; ordinary Preferences must never hold auth credentials (ADR-0012).
    //
    // Capacitor serves the WebView from https://localhost. Browser fetch to a
    // local HTTP API (emulator 10.0.2.2) is mixed-content blocked even when
    // Android cleartext is permitted. Native CapacitorHttp patches fetch so
    // Passport token exchange and /api/v1 calls can reach that origin.
    CapacitorHttp: {
      enabled: true,
    },
  },
}

const config: CapacitorConfig = isProductionAndroid
  ? productionConfig
  : demoConfig

export default config
