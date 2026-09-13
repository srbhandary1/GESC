import type { NativeSecureCredentialStore } from './AndroidPkceAuthenticationAdapter'
import { createInstallationUlid } from './createInstallationUlid'
import {
  globalEnglishSecureCredentials,
  type GlobalEnglishSecureCredentialsPlugin,
} from './nativeSecureCredentialPlugin'

const INSTALLATION_ID_KEY = 'installation_ulid'
const REFRESH_TOKEN_KEY = 'refresh_token'
const PENDING_AUTHORIZATION_KEY = 'pending_authorization'

export interface KeystoreSecureCredentialStoreOptions {
  createUlid?: () => string
  plugin?: GlobalEnglishSecureCredentialsPlugin
}

/**
 * Reviewed native secure store adapter (ADR-0012 / D-0110).
 * Persists installation ULID, refresh token and transient PKCE pending state
 * only. Never writes to ordinary Capacitor Preferences or WebView storage.
 */
export class KeystoreSecureCredentialStore
  implements NativeSecureCredentialStore
{
  readonly #plugin: GlobalEnglishSecureCredentialsPlugin
  readonly #createUlid: () => string

  constructor(options: KeystoreSecureCredentialStoreOptions = {}) {
    this.#plugin = options.plugin ?? globalEnglishSecureCredentials
    this.#createUlid = options.createUlid ?? createInstallationUlid
  }

  async getOrCreateInstallationId(): Promise<string> {
    const existing = await this.#plugin.get({ key: INSTALLATION_ID_KEY })

    if (existing.value !== null && existing.value !== '') {
      return existing.value
    }

    const installationId = this.#createUlid()
    await this.#plugin.set({
      key: INSTALLATION_ID_KEY,
      value: installationId,
    })

    return installationId
  }

  async readRefreshToken(): Promise<string | null> {
    const result = await this.#plugin.get({ key: REFRESH_TOKEN_KEY })

    return result.value === '' ? null : result.value
  }

  async writeRefreshToken(refreshToken: string): Promise<void> {
    await this.#plugin.set({
      key: REFRESH_TOKEN_KEY,
      value: refreshToken,
    })
  }

  async clearAuthentication(): Promise<void> {
    // Clears refresh + pending PKCE state. Installation ULID is retained so
    // the same installation binding continues after local sign-out.
    await this.#plugin.clearAuthentication()
  }

  async writePendingAuthorization(pending: {
    state: string
    verifier: string
  }): Promise<void> {
    await this.#plugin.set({
      key: PENDING_AUTHORIZATION_KEY,
      value: JSON.stringify(pending),
    })
  }

  async takePendingAuthorization(): Promise<{
    state: string
    verifier: string
  } | null> {
    const result = await this.#plugin.take({
      key: PENDING_AUTHORIZATION_KEY,
    })

    if (result.value === null || result.value === '') {
      return null
    }

    try {
      const parsed: unknown = JSON.parse(result.value)

      if (
        parsed === null ||
        typeof parsed !== 'object' ||
        typeof (parsed as { state?: unknown }).state !== 'string' ||
        typeof (parsed as { verifier?: unknown }).verifier !== 'string'
      ) {
        return null
      }

      return {
        state: (parsed as { state: string }).state,
        verifier: (parsed as { verifier: string }).verifier,
      }
    } catch {
      return null
    }
  }
}

export const SECURE_CREDENTIAL_STORAGE_KEYS = {
  installationId: INSTALLATION_ID_KEY,
  refreshToken: REFRESH_TOKEN_KEY,
  pendingAuthorization: PENDING_AUTHORIZATION_KEY,
} as const
