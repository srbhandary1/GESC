import { registerPlugin } from '@capacitor/core'

/**
 * Project-owned Capacitor bridge to AndroidX EncryptedSharedPreferences
 * (ADR-0012). Implemented natively in `apps/web/android-app`.
 */
export interface GlobalEnglishSecureCredentialsPlugin {
  get(options: { key: string }): Promise<{ value: string | null }>
  set(options: { key: string; value: string }): Promise<void>
  remove(options: { key: string }): Promise<void>
  take(options: { key: string }): Promise<{ value: string | null }>
  clearAuthentication(): Promise<void>
}

export const globalEnglishSecureCredentials =
  registerPlugin<GlobalEnglishSecureCredentialsPlugin>(
    'GlobalEnglishSecureCredentials',
  )
