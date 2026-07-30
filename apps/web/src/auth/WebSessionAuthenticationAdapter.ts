import {
  getCurrentUser,
  listWebSessions,
  logoutWebSession,
  requestOtp as requestOtpOperation,
  revokeAllWebSessions,
  revokeWebSession,
  verifyOtp as verifyOtpOperation,
  type OtpChallenge,
  type WebSessionSummary,
} from '@global-english/contracts'
import {
  AuthenticationUnavailableError,
  type AuthenticationSnapshot,
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
