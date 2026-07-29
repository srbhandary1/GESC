import {
  getCurrentUser,
  revokeMobileTokenFamily,
  type MobileAuthorizationCodeExchange,
  type MobileRefreshTokenExchange,
  type MobileTokenResponse,
} from '@global-english/contracts'
import {
  AuthenticationUnavailableError,
  type AndroidPkceAuthentication,
  type AuthenticationSnapshot,
} from './AuthenticationAdapter'

interface PendingAuthorization {
  state: string
  verifier: string
}

export interface NativeSecureCredentialStore {
  getOrCreateInstallationId(): Promise<string>
  readRefreshToken(): Promise<string | null>
  writeRefreshToken(refreshToken: string): Promise<void>
  clearAuthentication(): Promise<void>
  writePendingAuthorization(pending: PendingAuthorization): Promise<void>
  takePendingAuthorization(): Promise<PendingAuthorization | null>
}

export interface SystemAuthorizationBrowser {
  open(url: string): Promise<void>
}

export interface AndroidPkceAuthenticationOptions {
  authorizationEndpoint: string
  apiBaseUrl: string
  clientId: string
  fetch?: typeof fetch
  redirectUri: string
  scope?: string
  secureStore: NativeSecureCredentialStore
  systemBrowser: SystemAuthorizationBrowser
  tokenEndpoint: string
}

export class AndroidPkceAuthenticationAdapter
  implements AndroidPkceAuthentication
{
  readonly transport = 'passport-pkce' as const

  readonly #options: AndroidPkceAuthenticationOptions
  readonly #fetch: typeof fetch
  #accessToken: string | null = null
  #refreshInFlight: Promise<void> | null = null

  constructor(options: AndroidPkceAuthenticationOptions) {
    this.#options = options
    this.#fetch = options.fetch ?? globalThis.fetch
  }

  async beginAuthorization(): Promise<void> {
    const verifier = randomBase64Url(64)
    const state = randomBase64Url(32)
    await this.#options.secureStore.getOrCreateInstallationId()

    await this.#options.secureStore.writePendingAuthorization({
      state,
      verifier,
    })

    const url = new URL(this.#options.authorizationEndpoint)
    url.searchParams.set('client_id', this.#options.clientId)
    url.searchParams.set('code_challenge', await sha256Base64Url(verifier))
    url.searchParams.set('code_challenge_method', 'S256')
    url.searchParams.set('redirect_uri', this.#options.redirectUri)
    url.searchParams.set('response_type', 'code')
    url.searchParams.set('scope', this.#options.scope ?? 'mobile:api')
    url.searchParams.set('state', state)

    await this.#options.systemBrowser.open(url.toString())
  }

  async completeAuthorization(
    callbackUrl: string,
  ): Promise<AuthenticationSnapshot> {
    const pending =
      await this.#options.secureStore.takePendingAuthorization()
    let callback: URL
    let expectedRedirect: URL

    try {
      callback = new URL(callbackUrl)
      expectedRedirect = new URL(this.#options.redirectUri)
    } catch {
      await this.#clearAuthentication()
      throw new AuthenticationUnavailableError()
    }

    const returnedState = callback.searchParams.get('state')
    const code = callback.searchParams.get('code')

    if (
      pending === null ||
      !matchesRedirectTarget(callback, expectedRedirect) ||
      callback.searchParams.getAll('state').length !== 1 ||
      callback.searchParams.getAll('code').length !== 1 ||
      returnedState === null ||
      code === null ||
      !constantTimeEqual(returnedState, pending.state)
    ) {
      await this.#clearAuthentication()
      throw new AuthenticationUnavailableError()
    }

    const installationId =
      await this.#options.secureStore.getOrCreateInstallationId()

    try {
      const tokens = await this.#exchangeToken({
        client_id: this.#options.clientId,
        code,
        code_verifier: pending.verifier,
        grant_type: 'authorization_code',
        installation_id: installationId,
        redirect_uri: this.#options.redirectUri,
      })

      await this.#acceptTokens(tokens)
    } catch {
      await this.#clearAuthentication()
      throw new AuthenticationUnavailableError()
    }

    return this.current()
  }

  async current(): Promise<AuthenticationSnapshot> {
    if (this.#accessToken === null) {
      return { status: 'anonymous' }
    }

    const result = await getCurrentUser({
      baseUrl: this.#options.apiBaseUrl,
      fetch: this.#fetch,
      headers: {
        Authorization: `Bearer ${this.#accessToken}`,
      },
      throwOnError: false,
    })

    if (result.data !== undefined) {
      return {
        status: 'authenticated',
        identity: result.data,
      }
    }

    if (result.response?.status === 401) {
      await this.#clearAuthentication()

      return { status: 'anonymous' }
    }

    throw new AuthenticationUnavailableError()
  }

  async refresh(): Promise<void> {
    if (this.#refreshInFlight !== null) {
      return this.#refreshInFlight
    }

    this.#refreshInFlight = this.#performRefresh().finally(() => {
      this.#refreshInFlight = null
    })

    return this.#refreshInFlight
  }

  async logout(): Promise<void> {
    try {
      if (
        this.#accessToken === null &&
        (await this.#options.secureStore.readRefreshToken()) !== null
      ) {
        try {
          await this.refresh()
        } catch {
          return
        }
      }

      if (this.#accessToken !== null) {
        const firstAttempt = await this.#revokeFamily()

        if (
          firstAttempt === 401 &&
          (await this.#options.secureStore.readRefreshToken()) !== null
        ) {
          try {
            await this.refresh()
          } catch {
            return
          }

          await this.#revokeFamily()
        }
      }
    } catch {
      // Local sign-out must still complete when the revocation service is
      // unavailable. The server family remains bounded by its configured TTL.
    } finally {
      await this.#clearAuthentication()
    }
  }

  async #performRefresh(): Promise<void> {
    const refreshToken =
      await this.#options.secureStore.readRefreshToken()

    if (refreshToken === null) {
      throw new AuthenticationUnavailableError()
    }

    try {
      const installationId =
        await this.#options.secureStore.getOrCreateInstallationId()
      const tokens = await this.#exchangeToken({
        client_id: this.#options.clientId,
        grant_type: 'refresh_token',
        installation_id: installationId,
        refresh_token: refreshToken,
      })

      await this.#acceptTokens(tokens)
    } catch {
      await this.#clearAuthentication()
      throw new AuthenticationUnavailableError()
    }
  }

  async #exchangeToken(
    fields: MobileAuthorizationCodeExchange | MobileRefreshTokenExchange,
  ): Promise<MobileTokenResponse> {
    const response = await this.#fetch(this.#options.tokenEndpoint, {
      body: new URLSearchParams(Object.entries(fields)),
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      method: 'POST',
    })

    if (!response.ok) {
      throw new AuthenticationUnavailableError()
    }

    const body: unknown = await response.json()
    const tokens = parseTokenResponse(body)

    if (tokens === null) {
      throw new AuthenticationUnavailableError()
    }

    return tokens
  }

  async #acceptTokens(tokens: MobileTokenResponse): Promise<void> {
    this.#accessToken = tokens.access_token
    await this.#options.secureStore.writeRefreshToken(tokens.refresh_token)
  }

  async #clearAuthentication(): Promise<void> {
    this.#accessToken = null
    await this.#options.secureStore.clearAuthentication()
  }

  async #revokeFamily(): Promise<number | undefined> {
    if (this.#accessToken === null) {
      return undefined
    }

    const installationId =
      await this.#options.secureStore.getOrCreateInstallationId()
    const result = await revokeMobileTokenFamily({
      baseUrl: this.#options.apiBaseUrl,
      body: {
        installation_id: installationId,
      },
      fetch: this.#fetch,
      headers: {
        Authorization: `Bearer ${this.#accessToken}`,
      },
      throwOnError: false,
    })

    return result.response?.status
  }
}

function parseTokenResponse(value: unknown): MobileTokenResponse | null {
  if (value === null || typeof value !== 'object') {
    return null
  }

  const candidate = value as Record<string, unknown>
  const expectedFields = [
    'access_token',
    'expires_in',
    'refresh_token',
    'token_type',
  ]

  if (
    Object.keys(candidate).sort().join('\0') !==
      expectedFields.sort().join('\0') ||
    typeof candidate.access_token !== 'string' ||
    candidate.access_token === '' ||
    candidate.expires_in !== 900 ||
    typeof candidate.refresh_token !== 'string' ||
    candidate.refresh_token === '' ||
    candidate.token_type !== 'Bearer'
  ) {
    return null
  }

  return candidate as MobileTokenResponse
}

function matchesRedirectTarget(callback: URL, expected: URL): boolean {
  return (
    callback.protocol === expected.protocol &&
    callback.username === expected.username &&
    callback.password === expected.password &&
    callback.hostname === expected.hostname &&
    callback.port === expected.port &&
    callback.pathname === expected.pathname &&
    callback.hash === expected.hash
  )
}

function randomBase64Url(byteLength: number): string {
  const bytes = new Uint8Array(byteLength)
  globalThis.crypto.getRandomValues(bytes)

  return bytesToBase64Url(bytes)
}

async function sha256Base64Url(value: string): Promise<string> {
  const digest = await globalThis.crypto.subtle.digest(
    'SHA-256',
    new TextEncoder().encode(value),
  )

  return bytesToBase64Url(new Uint8Array(digest))
}

function bytesToBase64Url(bytes: Uint8Array): string {
  let binary = ''

  for (const byte of bytes) {
    binary += String.fromCharCode(byte)
  }

  return btoa(binary)
    .replaceAll('+', '-')
    .replaceAll('/', '_')
    .replace(/=+$/u, '')
}

function constantTimeEqual(left: string, right: string): boolean {
  const length = Math.max(left.length, right.length)
  let difference = left.length ^ right.length

  for (let index = 0; index < length; index += 1) {
    difference |=
      (left.charCodeAt(index) || 0) ^ (right.charCodeAt(index) || 0)
  }

  return difference === 0
}
