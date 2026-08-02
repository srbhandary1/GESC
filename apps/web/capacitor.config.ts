import type { CapacitorConfig } from '@capacitor/cli'

// Demo-only Android wrapper configuration. It packages the disclosed
// synthetic `dist-demo` bundle for presentation installs. The identifier is
// a placeholder demo identity, not the client-owned production package
// identity, and this wrapper carries no credentials, providers or learner
// data. PR-014 (production Android with Passport PKCE and native secure
// storage) remains a separately authorized checkpoint.
const config: CapacitorConfig = {
  appId: 'com.globalenglish.demo',
  appName: 'Global English Demo',
  webDir: 'dist-demo',
}

export default config
