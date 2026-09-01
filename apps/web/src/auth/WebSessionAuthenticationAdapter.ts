import {
  beginPrivilegedMfaEnrollment,
  confirmPrivilegedMfaEnrollment,
  createPrivilegedMfaChallenge,
  getCurrentUser,
  getPrivilegedMfaStatus,
  listWebSessions,
  logoutWebSession,
  regeneratePrivilegedMfaRecoveryCodes,
  requestOtp as requestOtpOperation,
  revokeAllWebSessions,
  revokePrivilegedMfaFactor,
  revokeWebSession,
  verifyPrivilegedMfaChallenge,
  verifyOtp as verifyOtpOperation,
  type OtpChallenge,
  type PrivilegedMfaChallenge,
  type PrivilegedMfaEnrollment,
  type PrivilegedMfaRecoveryCodeIssuance,
  type PrivilegedMfaStatus,
  type PrivilegedMfaVerificationResult,
  type WebSessionSummary,
} from '@global-english/contracts'
import {
  AuthenticationUnavailableError,
  parseFederatedProviderAvailability,
  type AuthenticationSnapshot,
  type FederatedProvider,
  type FederatedProviderAvailability,
  type WebSessionAuthentication,
} from './AuthenticationAdapter'

export interface WebSessionAuthenticationOptions {
  baseUrl?: string
  beforeLogout?: () => Promise<void>
  fetch?: typeof fetch
  readCookie?: () => string
}

export class WebSessionAuthenticationAdapter
  implements WebSessionAuthentication
{
  readonly transport = 'sanctum-cookie' as const

  readonly #baseUrl?: string
  readonly #beforeLogout: () => Promise<void>
  readonly #fetch: typeof fetch
  readonly #readCookie: () => string

  constructor(options: WebSessionAuthenticationOptions = {}) {
    this.#baseUrl = options.baseUrl
    this.#beforeLogout = options.beforeLogout ?? (() => Promise.resolve())
    this.#fetch = options.fetch ?? globalThis.fetch
    this.#readCookie =
      options.readCookie ??
      (() => (typeof document === 'undefined' ? '' : document.cookie))
  }

  async current(): Promise<AuthenticationSnapshot> {
    const result = await getCurrentUser({
      ...this.#commonOptions(),
      throwOnError: false,
    })

    if (result.data !== undefined) {
      return {
        status: 'authenticated',
        identity: result.data,
      }
    }

    if (result.response?.status === 401) {
      return { status: 'anonymous' }
    }

    throw new AuthenticationUnavailableError()
  }

  async signIn(username: string, password: string): Promise<void> {
    await this.#credentialRequest('/auth/password/sign-ins', 'POST', {
      username,
      password,
    })
  }

  async authenticationMode(): Promise<
    import('./AuthenticationAdapter').PasswordAuthenticationStatus['mode']
  > {
    const response = await this.#fetch(
      `${this.#baseUrl ?? ''}/auth/password/mode`,
      {
        credentials: 'include',
        headers: { Accept: 'application/json' },
      },
    )

    if (!response.ok) {
      throw new AuthenticationUnavailableError()
    }

    const body = (await response.json()) as { mode?: unknown }

    if (
      body.mode !== 'legacy_otp' &&
      body.mode !== 'credential_migration' &&
      body.mode !== 'password_only'
    ) {
      throw new AuthenticationUnavailableError()
    }

    return body.mode
  }

  async requestPasswordRecovery(username: string): Promise<void> {
    await this.#credentialRequest('/auth/password/recovery-requests', 'POST', {
      username,
    })
  }

  async enrollWithInvitation(
    invitationCode: string,
    username: string,
    password: string,
  ): Promise<void> {
    await this.#credentialRequest(
      '/auth/password/invitation-enrollments',
      'POST',
      {
        invitation_code: invitationCode,
        username,
        password,
      },
    )
  }

  async federatedProviders(): Promise<FederatedProviderAvailability> {
    return this.#federatedAvailability('/auth/social/providers')
  }

  async beginFederatedSignIn(provider: FederatedProvider): Promise<void> {
    assignFederatedLocation(`/oauth/social/${provider}/authorize`)
  }

  async federatedLinks(): Promise<FederatedProviderAvailability> {
    return this.#federatedAvailability('/auth/social/links')
  }

  async beginFederatedLink(provider: FederatedProvider): Promise<void> {
    assignFederatedLocation(
      `/oauth/social/${provider}/authorize?intent=link`,
    )
  }

  async unlinkFederated(provider: FederatedProvider): Promise<void> {
    const response = await this.#fetch(
      `${this.#baseUrl ?? ''}/auth/social/${provider}/links`,
      {
        method: 'DELETE',
        credentials: 'include',
        headers: {
          Accept: 'application/json',
          ...(await this.#csrfHeaders()),
        },
      },
    )

    if (!response.ok) {
      throw new AuthenticationUnavailableError()
    }
  }

  async #federatedAvailability(
    path: string,
  ): Promise<FederatedProviderAvailability> {
    const response = await this.#fetch(`${this.#baseUrl ?? ''}${path}`, {
      credentials: 'include',
      headers: { Accept: 'application/json' },
    })

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

  async passwordStatus(): Promise<import('./AuthenticationAdapter').PasswordAuthenticationStatus> {
    const response = await this.#fetch(`${this.#baseUrl ?? ''}/auth/password/status`, {
      credentials: 'include',
      headers: { Accept: 'application/json' },
    })

    if (!response.ok) {
      throw new AuthenticationUnavailableError()
    }

    return (await response.json()) as import('./AuthenticationAdapter').PasswordAuthenticationStatus
  }

  async establishPassword(username: string, password: string): Promise<void> {
    await this.#credentialRequest('/auth/password/establishments', 'POST', {
      username,
      password,
    })
  }

  async confirmPassword(password: string): Promise<void> {
    await this.#credentialRequest('/auth/password/confirmations', 'POST', {
      password,
    })
  }

  async changePassword(
    currentPassword: string,
    newPassword: string,
  ): Promise<void> {
    await this.#credentialRequest('/auth/password', 'PUT', {
      current_password: currentPassword,
      new_password: newPassword,
    })
  }

  async changeUsername(username: string): Promise<void> {
    await this.#credentialRequest('/auth/username', 'PUT', { username })
  }

  async requestOtp(mobileNumber: string): Promise<OtpChallenge> {
    const result = await requestOtpOperation({
      ...this.#commonOptions(),
      body: {
        mobile_number: mobileNumber,
      },
      headers: await this.#csrfHeaders(),
      throwOnError: false,
    })

    if (result.data === undefined) {
      throw new AuthenticationUnavailableError()
    }

    return result.data
  }

  async verifyOtp(
    challengeId: string,
    mobileNumber: string,
    code: string,
    institutionInviteCode?: string,
  ): Promise<void> {
    const body = {
      challenge_id: challengeId,
      mobile_number: mobileNumber,
      code,
      ...(institutionInviteCode === undefined
        ? {}
        : { institution_invite_code: institutionInviteCode }),
    }
    const result = await verifyOtpOperation({
      ...this.#commonOptions(),
      body,
      headers: await this.#csrfHeaders(),
      throwOnError: false,
    })

    if (result.data === undefined) {
      throw new AuthenticationUnavailableError()
    }
  }

  async sessions(): Promise<ReadonlyArray<WebSessionSummary>> {
    const result = await listWebSessions({
      ...this.#commonOptions(),
      throwOnError: false,
    })

    if (result.data === undefined) {
      throw new AuthenticationUnavailableError()
    }

    return result.data.data
  }

  async revokeSession(sessionId: string): Promise<void> {
    const result = await revokeWebSession({
      ...this.#commonOptions(),
      path: {
        session_id: sessionId,
      },
      headers: await this.#csrfHeaders(),
      throwOnError: false,
    })

    if (result.response?.ok !== true) {
      throw new AuthenticationUnavailableError()
    }
  }

  async revokeAllSessions(): Promise<void> {
    const result = await revokeAllWebSessions({
      ...this.#commonOptions(),
      headers: await this.#csrfHeaders(),
      throwOnError: false,
    })

    if (result.response?.ok !== true) {
      throw new AuthenticationUnavailableError()
    }
  }

  async privilegedMfaStatus(): Promise<PrivilegedMfaStatus> {
    const result = await getPrivilegedMfaStatus({
      ...this.#commonOptions(),
      throwOnError: false,
    })

    if (result.data === undefined) {
      throw new AuthenticationUnavailableError()
    }

    return result.data
  }

  async beginPrivilegedMfaEnrollment(): Promise<PrivilegedMfaEnrollment> {
    const result = await beginPrivilegedMfaEnrollment({
      ...this.#commonOptions(),
      headers: await this.#csrfHeaders(),
      throwOnError: false,
    })

    if (result.data === undefined) {
      throw new AuthenticationUnavailableError()
    }

    return result.data
  }

  async confirmPrivilegedMfaEnrollment(
    factorId: string,
    challengeId: string,
    code: string,
  ): Promise<PrivilegedMfaRecoveryCodeIssuance> {
    const result = await confirmPrivilegedMfaEnrollment({
      ...this.#commonOptions(),
      body: {
        challenge_id: challengeId,
        code,
      },
      headers: await this.#csrfHeaders(),
      path: {
        factor_id: factorId,
      },
      throwOnError: false,
    })

    if (result.data === undefined) {
      throw new AuthenticationUnavailableError()
    }

    return result.data
  }

  async createPrivilegedMfaChallenge(): Promise<PrivilegedMfaChallenge> {
    const result = await createPrivilegedMfaChallenge({
      ...this.#commonOptions(),
      headers: await this.#csrfHeaders(),
      throwOnError: false,
    })

    if (result.data === undefined) {
      throw new AuthenticationUnavailableError()
    }

    return result.data
  }

  async verifyPrivilegedMfaChallenge(
    challengeId: string,
    method: 'totp' | 'recovery_code',
    code: string,
  ): Promise<PrivilegedMfaVerificationResult> {
    const result = await verifyPrivilegedMfaChallenge({
      ...this.#commonOptions(),
      body: {
        method,
        code,
      },
      headers: await this.#csrfHeaders(),
      path: {
        challenge_id: challengeId,
      },
      throwOnError: false,
    })

    if (result.data === undefined) {
      throw new AuthenticationUnavailableError()
    }

    return result.data
  }

  async regeneratePrivilegedMfaRecoveryCodes(): Promise<PrivilegedMfaRecoveryCodeIssuance> {
    const result = await regeneratePrivilegedMfaRecoveryCodes({
      ...this.#commonOptions(),
      headers: await this.#csrfHeaders(),
      throwOnError: false,
    })

    if (result.data === undefined) {
      throw new AuthenticationUnavailableError()
    }

    return result.data
  }

  async revokePrivilegedMfaFactor(): Promise<void> {
    const result = await revokePrivilegedMfaFactor({
      ...this.#commonOptions(),
      headers: await this.#csrfHeaders(),
      throwOnError: false,
    })

    if (result.response?.ok !== true) {
      throw new AuthenticationUnavailableError()
    }
  }

  async logout(): Promise<void> {
    await this.#beforeLogout()

    const result = await logoutWebSession({
      ...this.#commonOptions(),
      headers: await this.#csrfHeaders(),
      throwOnError: false,
    })

    if (result.response?.ok !== true && result.response?.status !== 401) {
      throw new AuthenticationUnavailableError()
    }
  }

  #commonOptions() {
    return {
      baseUrl: this.#baseUrl,
      credentials: 'include' as const,
      fetch: this.#fetch,
    }
  }

  async #csrfHeaders(): Promise<Record<string, string>> {
    const response = await this.#fetch(this.#csrfCookieUrl(), {
      credentials: 'include',
      headers: {
        Accept: 'application/json',
      },
    })

    if (!response.ok) {
      throw new AuthenticationUnavailableError()
    }

    const token = readCookieValue(this.#readCookie(), 'XSRF-TOKEN')

    if (token === undefined) {
      throw new AuthenticationUnavailableError()
    }

    return {
      'X-XSRF-TOKEN': token,
    }
  }

  async #credentialRequest(
    path: string,
    method: 'POST' | 'PUT',
    body: Readonly<Record<string, string>>,
  ): Promise<void> {
    const response = await this.#fetch(`${this.#baseUrl ?? ''}${path}`, {
      method,
      credentials: 'include',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        ...(await this.#csrfHeaders()),
      },
      body: JSON.stringify(body),
    })

    if (!response.ok) {
      throw new AuthenticationUnavailableError()
    }
  }

  #csrfCookieUrl(): string {
    if (
      this.#baseUrl !== undefined &&
      /^https?:\/\//u.test(this.#baseUrl)
    ) {
      return new URL('/sanctum/csrf-cookie', this.#baseUrl).toString()
    }

    return '/sanctum/csrf-cookie'
  }
}

function readCookieValue(
  cookieHeader: string,
  expectedName: string,
): string | undefined {
  for (const part of cookieHeader.split(';')) {
    const [name, ...valueParts] = part.trim().split('=')

    if (name !== expectedName || valueParts.length === 0) {
      continue
    }

    try {
      return decodeURIComponent(valueParts.join('='))
    } catch {
      return undefined
    }
  }

  return undefined
}

function assignFederatedLocation(path: string): void {
  if (typeof window === 'undefined') {
    throw new AuthenticationUnavailableError()
  }

  window.location.assign(path)
}
