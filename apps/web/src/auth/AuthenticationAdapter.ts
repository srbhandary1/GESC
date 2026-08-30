import type {
  CurrentUserResponse,
  OtpChallenge,
  PrivilegedMfaChallenge,
  PrivilegedMfaEnrollment,
  PrivilegedMfaRecoveryCodeIssuance,
  PrivilegedMfaStatus,
  PrivilegedMfaVerificationResult,
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
}

export interface WebSessionAuthentication extends AuthenticationAdapter {
  readonly transport: 'sanctum-cookie'

  signIn(username: string, password: string): Promise<void>

  authenticationMode(): Promise<PasswordAuthenticationStatus['mode']>

  requestPasswordRecovery(username: string): Promise<void>

  enrollWithInvitation(
    invitationCode: string,
    username: string,
    password: string,
  ): Promise<void>

  passwordStatus(): Promise<PasswordAuthenticationStatus>

  establishPassword(username: string, password: string): Promise<void>

  confirmPassword(password: string): Promise<void>

  changePassword(
    currentPassword: string,
    newPassword: string,
  ): Promise<void>

  changeUsername(username: string): Promise<void>

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

  privilegedMfaStatus(): Promise<PrivilegedMfaStatus>

  beginPrivilegedMfaEnrollment(): Promise<PrivilegedMfaEnrollment>

  confirmPrivilegedMfaEnrollment(
    factorId: string,
    challengeId: string,
    code: string,
  ): Promise<PrivilegedMfaRecoveryCodeIssuance>

  createPrivilegedMfaChallenge(): Promise<PrivilegedMfaChallenge>

  verifyPrivilegedMfaChallenge(
    challengeId: string,
    method: 'totp' | 'recovery_code',
    code: string,
  ): Promise<PrivilegedMfaVerificationResult>

  regeneratePrivilegedMfaRecoveryCodes(): Promise<PrivilegedMfaRecoveryCodeIssuance>

  revokePrivilegedMfaFactor(): Promise<void>
}

export interface PasswordAuthenticationStatus {
  readonly username: string | null
  readonly credential_ready: boolean
  readonly mode: 'legacy_otp' | 'credential_migration' | 'password_only'
  readonly password_confirmed_at: string | null
}

export interface AndroidPkceAuthentication extends AuthenticationAdapter {
  readonly transport: 'passport-pkce'

  /**
   * The only Android path for authenticated API and private-media requests.
   * Implementations keep the bearer in memory, restore through the reviewed
   * native refresh store, and perform at most one refresh/replay after a 401.
   */
  readonly authenticatedFetch: typeof fetch

  beginAuthorization(): Promise<void>

  completeAuthorization(callbackUrl: string): Promise<AuthenticationSnapshot>

  refresh(): Promise<void>

  onAuthenticationInvalidated(listener: () => void): () => void
}

export class AuthenticationUnavailableError extends Error {
  constructor() {
    super('Authentication could not be completed.')
    this.name = 'AuthenticationUnavailableError'
  }
}
