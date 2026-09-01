import {
  getCurrentUser,
  revokeMobileTokenFamily,
  type CurrentUserResponse,
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
import { formDataWithFileParts } from './formDataWithFileParts'

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
  #authorizationCompletionTail: Promise<void> = Promise.resolve()
  #credentialGeneration = 0
  #invalidationNotified = false
  readonly #invalidationListeners = new Set<() => void>()
  #refreshInFlight: Promise<void> | null = null

  readonly authenticatedFetch: typeof fetch = async (input, init) =>
    this.#authenticatedApiFetch(input, await this.#withNativeMultipart(init))

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
    const completion = this.#authorizationCompletionTail.then(
      () => this.#completeAuthorization(callbackUrl),
      () => this.#completeAuthorization(callbackUrl),
    )

    this.#authorizationCompletionTail = completion.then(
      () => undefined,
      () => undefined,
    )

    return completion
  }

  async #completeAuthorization(
    callbackUrl: string,
  ): Promise<AuthenticationSnapshot> {
    const credentialGeneration = this.#credentialGeneration
    let callback: URL
    let expectedRedirect: URL

    try {
      callback = new URL(callbackUrl)
      expectedRedirect = new URL(this.#options.redirectUri)
    } catch {
      // Malformed deliveries must not wipe an in-flight PKCE attempt.
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

    // Validate shape before consuming pending authorization so raced Custom
    // Tab / delayed native redeliveries cannot destroy a still-valid verifier.
    if (
      callback.searchParams.getAll('code').length !== 1 ||
      code === null
    ) {
      throw new AuthenticationUnavailableError()
    }

    const pending =
      await this.#options.secureStore.takePendingAuthorization()

    if (pending === null) {
      throw new AuthenticationUnavailableError()
    }

    if (!constantTimeEqual(returnedState, pending.state)) {
      await this.#options.secureStore.writePendingAuthorization(pending)
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

      await this.#acceptTokens(tokens, credentialGeneration)

      // Identity load stays inside the same failure boundary so a malformed
      // /me body (e.g. Capacitor Content-Length: 0 → {}) cannot leave a
      // half-authenticated secure-store refresh token behind.
      return await this.current()
    } catch {
      await this.#clearAuthentication()
      throw new AuthenticationUnavailableError()
    }
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
      let refreshToken: string | null

      try {
        refreshToken =
          await this.#options.secureStore.readRefreshToken()
      } catch {
        throw new AuthenticationUnavailableError()
      }

      if (refreshToken === null) {
        return { status: 'anonymous' }
      }

      try {
        await this.refresh()
      } catch {
        // A terminal OAuth rejection clears the secure credential and is an
        // anonymous session. Network/server failure preserves it for retry.
        if (
          (await this.#readRefreshTokenOrUnavailable()) === null
        ) {
          return { status: 'anonymous' }
        }

        throw new AuthenticationUnavailableError()
      }
    }

    let result

    try {
      result = await getCurrentUser({
        baseUrl: this.#options.apiBaseUrl,
        fetch: this.authenticatedFetch,
        throwOnError: false,
      })
    } catch {
      throw new AuthenticationUnavailableError()
    }

    if (isCurrentUserIdentity(result.data)) {
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

  onAuthenticationInvalidated(listener: () => void): () => void {
    this.#invalidationListeners.add(listener)

    return () => {
      this.#invalidationListeners.delete(listener)
    }
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

  async #withNativeMultipart(
    init?: RequestInit,
  ): Promise<RequestInit | undefined> {
    if (init === undefined || !(init.body instanceof FormData)) {
      return init
    }

    return {
      ...init,
      body: formDataWithFileParts(init.body),
    }
  }

  async #authenticatedApiFetch(
    input: RequestInfo | URL,
    init?: RequestInit,
  ): Promise<Response> {
    const formBody = init?.body instanceof FormData ? init.body : null
    const request = this.#apiRequest(
      input,
      formBody === null ? init : { ...init, body: undefined },
    )

    if (this.#accessToken === null) {
      const refreshToken = await this.#readRefreshTokenOrUnavailable()

      if (refreshToken !== null) {
        try {
          await this.refresh()
        } catch {
          if ((await this.#readRefreshTokenOrUnavailable()) !== null) {
            throw new AuthenticationUnavailableError()
          }
        }
      }
    }

    const firstAccessToken = this.#accessToken
    const retryRequest = request.clone()
    const response = await this.#sendAuthorized(
      request,
      firstAccessToken,
      formBody,
    )

    if (response.status !== 401 || firstAccessToken === null) {
      return response
    }

    if (this.#accessToken === firstAccessToken) {
      try {
        await this.refresh()
      } catch {
        if ((await this.#readRefreshTokenOrUnavailable()) !== null) {
          throw new AuthenticationUnavailableError()
        }

        await this.#clearAuthentication()
        this.#notifyAuthenticationInvalidated()

        return response
      }
    }

    const replacementAccessToken = this.#accessToken

    if (replacementAccessToken === null) {
      return response
    }

    const retried = await this.#sendAuthorized(
      retryRequest,
      replacementAccessToken,
      formBody,
    )

    if (retried.status === 401) {
      await this.#clearAuthentication()
      this.#notifyAuthenticationInvalidated()
    }

    return retried
  }

  /**
   * Capacitor's fetch interceptor reads `init.body`. A pre-built Request turns
   * FormData into a ReadableStream, which it UTF-8-decodes and corrupts.
   */
  #sendAuthorized(
    request: Request,
    accessToken: string | null,
    formBody: FormData | null,
  ): Promise<Response> {
    if (formBody !== null) {
      return this.#fetch(request.url, {
        body: formBody,
        credentials: 'omit',
        headers: this.#authorizedHeaders(request, accessToken),
        method: request.method,
      })
    }

    return this.#fetch(this.#authorizedRequest(request, accessToken))
  }

  #apiRequest(
    input: RequestInfo | URL,
    init?: RequestInit,
  ): Request {
    const base = new URL(this.#options.apiBaseUrl)
    const candidate = this.#resolveApiUrl(input, base)
    const basePath = base.pathname.replace(/\/+$/u, '')

    if (
      candidate.origin !== base.origin ||
      (candidate.pathname !== basePath &&
        !candidate.pathname.startsWith(`${basePath}/`))
    ) {
      throw new AuthenticationUnavailableError()
    }

    const normalizedInput =
      input instanceof Request
        ? new Request(candidate.toString(), input)
        : candidate.toString()

    return new Request(normalizedInput, init)
  }

  /**
   * Capacitor serves the WebView from https://localhost. Relative `/api/v1`
   * fetches can be resolved against that origin before this adapter runs.
   * Map only that WebView origin back onto the baked API origin; other hosts
   * stay rejected.
   */
  #resolveApiUrl(input: RequestInfo | URL, base: URL): URL {
    const candidate =
      input instanceof Request
        ? new URL(input.url)
        : new URL(input.toString(), `${base.origin}/`)
    const webViewHosts = new Set(['localhost', '127.0.0.1'])

    if (
      !webViewHosts.has(candidate.hostname) ||
      (candidate.protocol !== 'https:' && candidate.protocol !== 'http:')
    ) {
      return candidate
    }

    const rewritten = new URL(candidate.href)
    rewritten.protocol = base.protocol
    rewritten.host = base.host

    return rewritten
  }

  #authorizedHeaders(
    request: Request,
    accessToken: string | null,
  ): Headers {
    const headers = new Headers(request.headers)

    if (accessToken === null) {
      headers.delete('Authorization')
    } else {
      headers.set('Authorization', `Bearer ${accessToken}`)
    }

    return headers
  }

  #authorizedRequest(
    request: Request,
    accessToken: string | null,
  ): Request {
    return new Request(request, {
      credentials: 'omit',
      headers: this.#authorizedHeaders(request, accessToken),
    })
  }

  async #performRefresh(): Promise<void> {
    const credentialGeneration = this.#credentialGeneration
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

      await this.#acceptTokens(tokens, credentialGeneration)
    } catch (error) {
      if (
        !(error instanceof TokenExchangeError) ||
        error.invalidatesCredential
      ) {
        await this.#clearAuthentication()
      }

      throw new AuthenticationUnavailableError()
    }
  }

  async #exchangeToken(
    fields: MobileAuthorizationCodeExchange | MobileRefreshTokenExchange,
  ): Promise<MobileTokenResponse> {
    let response: Response

    try {
      response = await this.#fetch(this.#options.tokenEndpoint, {
        body: new URLSearchParams(Object.entries(fields)),
        credentials: 'omit',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        method: 'POST',
      })
    } catch {
      throw new TokenExchangeError(false)
    }

    if (!response.ok) {
      throw new TokenExchangeError(
        response.status === 400 || response.status === 401,
      )
    }

    let body: unknown

    try {
      body = await response.json()
    } catch {
      // A successful response may already have rotated the refresh token.
      throw new TokenExchangeError(true)
    }

    const tokens = parseTokenResponse(body)

    if (tokens === null) {
      throw new TokenExchangeError(true)
    }

    return tokens
  }

  async #acceptTokens(
    tokens: MobileTokenResponse,
    credentialGeneration: number,
  ): Promise<void> {
    await this.#options.secureStore.writeRefreshToken(tokens.refresh_token)

    if (credentialGeneration !== this.#credentialGeneration) {
      await this.#clearAuthentication()
      throw new TokenExchangeError(true)
    }

    this.#accessToken = tokens.access_token
    this.#invalidationNotified = false
  }

  async #readRefreshTokenOrUnavailable(): Promise<string | null> {
    try {
      return await this.#options.secureStore.readRefreshToken()
    } catch {
      throw new AuthenticationUnavailableError()
    }
  }

  async #clearAuthentication(): Promise<void> {
    this.#credentialGeneration += 1
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
      fetch: this.authenticatedFetch,
      throwOnError: false,
    })

    return result.response?.status
  }
}

class TokenExchangeError extends Error {
  readonly invalidatesCredential: boolean

  constructor(invalidatesCredential: boolean) {
    super('The OAuth token exchange failed.')
    this.name = 'TokenExchangeError'
    this.invalidatesCredential = invalidatesCredential
  }
}

function isCurrentUserIdentity(
  value: unknown,
): value is CurrentUserResponse {
  if (value === null || typeof value !== 'object') {
    return false
  }

  const candidate = value as Record<string, unknown>
  const user = candidate.user

  return (
    user !== null &&
    typeof user === 'object' &&
    typeof (user as { id?: unknown }).id === 'string' &&
    (user as { id: string }).id !== '' &&
    'current_membership' in candidate &&
    Array.isArray(candidate.memberships)
  )
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
