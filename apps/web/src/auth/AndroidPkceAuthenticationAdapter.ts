import {
  getCurrentUser,
  revokeMobileTokenFamily,
  type MobileAuthorizationCodeExchange,
  type MobileRefreshTokenExchange,
  type MobileTokenResponse,
} from '@global-english/contracts'
import {
  AuthenticationUnavailableError,
  parseFederatedProviderAvailability,
  type AndroidPkceAuthentication,
  type AuthenticationSnapshot,
  type FederatedProvider,
  type FederatedProviderAvailability,
  type PasswordAuthenticationStatus,
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
  beforeLogout?: () => Promise<void>
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

  async signIn(username: string, password: string): Promise<void> {
    const credentialGeneration = this.#credentialGeneration
    const installationId =
      await this.#options.secureStore.getOrCreateInstallationId()

    try {
      const response = await this.#fetch(
        `${this.#options.apiBaseUrl}/auth/mobile/password/sign-ins`,
        {
          body: JSON.stringify({
            client_id: this.#options.clientId,
            installation_id: installationId,
            password,
            username,
          }),
          credentials: 'omit',
          headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
          },
          method: 'POST',
        },
      )

      if (!response.ok) {
        throw new AuthenticationUnavailableError()
      }

      const tokens = parseTokenResponse(await response.json())

      if (tokens === null) {
        throw new AuthenticationUnavailableError()
      }

      await this.#acceptTokens(tokens, credentialGeneration)
    } catch (error) {
      if (error instanceof AuthenticationUnavailableError) {
        throw error
      }

      throw new AuthenticationUnavailableError()
    }
  }

  async authenticationMode(): Promise<PasswordAuthenticationStatus['mode']> {
    let response: Response

    try {
      response = await this.#fetch(
        `${this.#options.apiBaseUrl}/auth/mobile/password/mode`,
        {
          credentials: 'omit',
          headers: { Accept: 'application/json' },
          method: 'GET',
        },
      )
    } catch {
      throw new AuthenticationUnavailableError()
    }

    if (!response.ok) {
      throw new AuthenticationUnavailableError()
    }

    let body: unknown

    try {
      body = await response.json()
    } catch {
      throw new AuthenticationUnavailableError()
    }

    const mode =
      body !== null &&
      typeof body === 'object' &&
      'mode' in body &&
      typeof body.mode === 'string'
        ? body.mode
        : null

    if (
      mode !== 'legacy_otp' &&
      mode !== 'credential_migration' &&
      mode !== 'password_only'
    ) {
      throw new AuthenticationUnavailableError()
    }

    return mode
  }

  async requestPasswordRecovery(username: string): Promise<void> {
    await this.#passwordJson(
      '/auth/mobile/password/recovery-requests',
      { username },
      202,
    )
  }

  async enrollWithInvitation(
    invitationCode: string,
    username: string,
    password: string,
  ): Promise<void> {
    await this.#passwordJson(
      '/auth/mobile/password/invitation-enrollments',
      {
        invitation_code: invitationCode,
        password,
        username,
      },
      204,
    )
  }

  async federatedProviders(): Promise<FederatedProviderAvailability> {
    return this.#federatedAvailability('/auth/mobile/social/providers')
  }

  async beginFederatedSignIn(provider: FederatedProvider): Promise<void> {
    const installationId =
      await this.#options.secureStore.getOrCreateInstallationId()
    const authorizationUrl = await this.#federatedAuthorizationUrl(
      provider,
      installationId,
    )
    await this.#options.systemBrowser.open(authorizationUrl)
  }

  async federatedLinks(): Promise<FederatedProviderAvailability> {
    let response: Response

    try {
      response = await this.authenticatedFetch(
        `${this.#options.apiBaseUrl}/auth/mobile/social/links`,
        {
          credentials: 'omit',
          headers: { Accept: 'application/json' },
          method: 'GET',
        },
      )
    } catch {
      throw new AuthenticationUnavailableError()
    }

    return this.#readFederatedAvailability(response)
  }

  async beginFederatedLink(provider: FederatedProvider): Promise<void> {
    const installationId =
      await this.#options.secureStore.getOrCreateInstallationId()
    let response: Response

    try {
      response = await this.authenticatedFetch(
        `${this.#options.apiBaseUrl}/auth/mobile/social/${provider}/link-starts`,
        {
          body: JSON.stringify({
            client_id: this.#options.clientId,
            installation_id: installationId,
          }),
          credentials: 'omit',
          headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
          },
          method: 'POST',
        },
      )
    } catch {
      throw new AuthenticationUnavailableError()
    }

    if (!response.ok) {
      throw new AuthenticationUnavailableError()
    }

    let body: unknown

    try {
      body = await response.json()
    } catch {
      throw new AuthenticationUnavailableError()
    }

    const authorizationUrl =
      body !== null &&
      typeof body === 'object' &&
      'authorization_url' in body &&
      typeof body.authorization_url === 'string'
        ? body.authorization_url
        : null

    if (authorizationUrl === null || authorizationUrl === '') {
      throw new AuthenticationUnavailableError()
    }

    await this.#options.systemBrowser.open(authorizationUrl)
  }

  async unlinkFederated(provider: FederatedProvider): Promise<void> {
    let response: Response

    try {
      response = await this.authenticatedFetch(
        `${this.#options.apiBaseUrl}/auth/mobile/social/${provider}/links`,
        {
          credentials: 'omit',
          headers: { Accept: 'application/json' },
          method: 'DELETE',
        },
      )
    } catch {
      throw new AuthenticationUnavailableError()
    }

    if (!response.ok) {
      throw new AuthenticationUnavailableError()
    }
  }

  async #federatedAuthorizationUrl(
    provider: FederatedProvider,
    installationId: string,
  ): Promise<string> {
    let response: Response

    try {
      const url = new URL(
        `${this.#options.apiBaseUrl}/auth/mobile/social/${provider}/authorization`,
      )
      url.searchParams.set('client_id', this.#options.clientId)
      url.searchParams.set('installation_id', installationId)
      response = await this.#fetch(url, {
        credentials: 'omit',
        headers: { Accept: 'application/json' },
        method: 'GET',
      })
    } catch {
      throw new AuthenticationUnavailableError()
    }

    if (!response.ok) {
      throw new AuthenticationUnavailableError()
    }

    let body: unknown

    try {
      body = await response.json()
    } catch {
      throw new AuthenticationUnavailableError()
    }

    const authorizationUrl =
      body !== null &&
      typeof body === 'object' &&
      'authorization_url' in body &&
      typeof body.authorization_url === 'string'
        ? body.authorization_url
        : null

    if (authorizationUrl === null || authorizationUrl === '') {
      throw new AuthenticationUnavailableError()
    }

    return authorizationUrl
  }

  async #federatedAvailability(
    path: string,
  ): Promise<FederatedProviderAvailability> {
    let response: Response

    try {
      response = await this.#fetch(`${this.#options.apiBaseUrl}${path}`, {
        credentials: 'omit',
        headers: { Accept: 'application/json' },
        method: 'GET',
      })
    } catch {
      throw new AuthenticationUnavailableError()
    }

    return this.#readFederatedAvailability(response)
  }

  async #readFederatedAvailability(
    response: Response,
  ): Promise<FederatedProviderAvailability> {
    if (!response.ok) {
      throw new AuthenticationUnavailableError()
    }

    let body: unknown

    try {
      body = await response.json()
    } catch {
      throw new AuthenticationUnavailableError()
    }

    const availability = parseFederatedProviderAvailability(body)

    if (availability === null) {
      throw new AuthenticationUnavailableError()
    }

    return availability
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
    const ticket = callback.searchParams.get('social_ticket')
    const oauthError = callback.searchParams.get('error')

    if (
      !matchesRedirectTarget(callback, expectedRedirect) ||
      callback.searchParams.getAll('state').length !== 1 ||
      returnedState === null ||
      oauthError !== null ||
      callback.searchParams.getAll('error').length > 0 ||
      (ticket !== null && code !== null)
    ) {
      throw new AuthenticationUnavailableError()
    }

    if (ticket !== null) {
      if (
        callback.searchParams.getAll('social_ticket').length !== 1 ||
        ticket === ''
      ) {
        throw new AuthenticationUnavailableError()
      }

      return this.#completeFederatedTicket(ticket, credentialGeneration)
    }

    if (
      callback.searchParams.getAll('code').length !== 1 ||
      code === null
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

  async #completeFederatedTicket(
    ticket: string,
    credentialGeneration: number,
  ): Promise<AuthenticationSnapshot> {
    const installationId =
      await this.#options.secureStore.getOrCreateInstallationId()

    try {
      const response = await this.#fetch(
        `${this.#options.apiBaseUrl}/auth/mobile/social/sign-ins`,
        {
          body: JSON.stringify({
            client_id: this.#options.clientId,
            installation_id: installationId,
            ticket,
          }),
          credentials: 'omit',
          headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
          },
          method: 'POST',
        },
      )

      if (!response.ok) {
        throw new AuthenticationUnavailableError()
      }

      const tokens = parseTokenResponse(await response.json())

      if (tokens === null) {
        throw new AuthenticationUnavailableError()
      }

      await this.#acceptTokens(tokens, credentialGeneration)

      return await this.current()
    } catch (error) {
      if (error instanceof AuthenticationUnavailableError) {
        throw error
      }

      throw new AuthenticationUnavailableError()
    }
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
    await this.#options.beforeLogout?.()

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

  #notifyAuthenticationInvalidated(): void {
    if (this.#invalidationNotified) {
      return
    }

    this.#invalidationNotified = true

    for (const listener of this.#invalidationListeners) {
      try {
        listener()
      } catch {
        // Session invalidation must not be reversed by a UI-listener failure.
      }
    }
  }

  async #passwordJson(
    path: string,
    body: Readonly<Record<string, string>>,
    expectedStatus: 202 | 204,
  ): Promise<void> {
    let response: Response

    try {
      response = await this.#fetch(`${this.#options.apiBaseUrl}${path}`, {
        body: JSON.stringify(body),
        credentials: 'omit',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
        },
        method: 'POST',
      })
    } catch {
      throw new AuthenticationUnavailableError()
    }

    if (response.status !== expectedStatus) {
      throw new AuthenticationUnavailableError()
    }
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
