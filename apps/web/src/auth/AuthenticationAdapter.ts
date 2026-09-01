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
}

export interface PasswordAuthenticationStatus {
  readonly username: string | null
  readonly credential_ready: boolean
  readonly mode: 'legacy_otp' | 'credential_migration' | 'password_only'
  readonly password_confirmed_at: string | null
}

export interface PasswordFormAuthentication {
  signIn(username: string, password: string): Promise<void>

  authenticationMode(): Promise<PasswordAuthenticationStatus['mode']>

  requestPasswordRecovery(username: string): Promise<void>

  enrollWithInvitation(
    invitationCode: string,
    username: string,
    password: string,
  ): Promise<void>

  federatedProviders(): Promise<FederatedProviderAvailability>

  beginFederatedSignIn(provider: FederatedProvider): Promise<void>
}

export type FederatedProvider = 'google' | 'facebook'

export interface FederatedProviderAvailability {
  readonly google: boolean
  readonly facebook: boolean
}

export interface FederatedAccountAuthentication {
  federatedLinks(): Promise<FederatedProviderAvailability>

  beginFederatedLink(provider: FederatedProvider): Promise<void>

  unlinkFederated(provider: FederatedProvider): Promise<void>
}

export interface WebSessionAuthentication
  extends AuthenticationAdapter,
    PasswordFormAuthentication,
    FederatedAccountAuthentication {
  readonly transport: 'sanctum-cookie'

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

export interface AndroidPkceAuthentication
  extends AuthenticationAdapter,
    PasswordFormAuthentication,
    FederatedAccountAuthentication {
  readonly transport: 'passport-pkce'

  beginAuthorization(): Promise<void>

  completeAuthorization(callbackUrl: string): Promise<AuthenticationSnapshot>

  refresh(): Promise<void>
}

export function isPasswordFormAuthentication(
  authentication: AuthenticationAdapter,
): authentication is AuthenticationAdapter & PasswordFormAuthentication {
  const candidate = authentication as Partial<PasswordFormAuthentication>

  return (
    typeof candidate.signIn === 'function' &&
    typeof candidate.authenticationMode === 'function' &&
    typeof candidate.requestPasswordRecovery === 'function' &&
    typeof candidate.enrollWithInvitation === 'function' &&
    typeof candidate.federatedProviders === 'function' &&
    typeof candidate.beginFederatedSignIn === 'function'
  )
}

export function isFederatedAccountAuthentication(
  authentication: AuthenticationAdapter,
): authentication is AuthenticationAdapter & FederatedAccountAuthentication {
  const candidate = authentication as Partial<FederatedAccountAuthentication>

  return (
    typeof candidate.federatedLinks === 'function' &&
    typeof candidate.beginFederatedLink === 'function' &&
    typeof candidate.unlinkFederated === 'function'
  )
}

export function parseFederatedProviderAvailability(
  value: unknown,
): FederatedProviderAvailability | null {
  if (value === null || typeof value !== 'object') {
    return null
  }

  const candidate = value as Record<string, unknown>

  if (
    Object.keys(candidate).length !== 2 ||
    typeof candidate.google !== 'boolean' ||
    typeof candidate.facebook !== 'boolean'
  ) {
    return null
  }

  return { google: candidate.google, facebook: candidate.facebook }
}

export class AuthenticationUnavailableError extends Error {
  constructor() {
    super('Authentication could not be completed.')
    this.name = 'AuthenticationUnavailableError'
  }
}
