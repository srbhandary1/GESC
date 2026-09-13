import type {
  CurrentUserResponse,
  OtpChallenge,
  WebSessionSummary,
} from '@global-english/contracts'

export type AuthenticationSnapshot =
  | {
      status: 'anonymous'
    }
  | {
      status: 'authenticated'
      identity: CurrentUserResponse
    }

export interface AuthenticationAdapter {
  readonly transport: 'sanctum-cookie' | 'passport-pkce'

  current(): Promise<AuthenticationSnapshot>

  logout(): Promise<void>

  /**
   * Optional same-origin media/API fetch. Web sessions send cookies.
   * Android PKCE attaches the in-memory Bearer token.
   */
  authorizedFetch?(input: RequestInfo | URL, init?: RequestInit): Promise<Response>
}

export interface WebSessionAuthentication extends AuthenticationAdapter {
  readonly transport: 'sanctum-cookie'

  requestOtp(mobileNumber: string): Promise<OtpChallenge>

  verifyOtp(
    challengeId: string,
    mobileNumber: string,
    code: string,
    institutionInviteCode?: string,
  ): Promise<void>

  sessions(): Promise<ReadonlyArray<WebSessionSummary>>

  revokeSession(sessionId: string): Promise<void>

  revokeAllSessions(): Promise<void>
}

export interface AndroidPkceAuthentication extends AuthenticationAdapter {
  readonly transport: 'passport-pkce'

  beginAuthorization(): Promise<void>

  completeAuthorization(callbackUrl: string): Promise<AuthenticationSnapshot>

  refresh(): Promise<void>
}

export class AuthenticationUnavailableError extends Error {
  constructor() {
    super('Authentication could not be completed.')
    this.name = 'AuthenticationUnavailableError'
  }
}
