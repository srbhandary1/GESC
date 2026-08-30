import { cleanup, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import type { WebSessionAuthentication } from '../auth/AuthenticationAdapter'
import { PrivilegedMfaPanel } from './PrivilegedMfaPanel'

afterEach(() => {
  cleanup()
  vi.restoreAllMocks()
})

describe('privileged MFA panel', () => {
  it('enrolls TOTP and keeps one-time recovery codes in component memory only', async () => {
    const user = userEvent.setup()
    const recoveryCodes = codes('initial')
    const authentication = mfaAuthentication()
    vi.mocked(authentication.privilegedMfaStatus)
      .mockResolvedValueOnce(status('none'))
      .mockResolvedValueOnce(status('active'))
    vi.mocked(authentication.beginPrivilegedMfaEnrollment).mockResolvedValue({
      factor_id: '01J00000000000000000000011',
      challenge_id: '01J00000000000000000000012',
      provisioning_uri:
        'otpauth://totp/Global%20English?secret=ABCDEFGHIJKLMNOPQRSTUV234567ABCD',
      expires_at: '2026-08-08T10:05:00Z',
    })
    vi.mocked(authentication.confirmPrivilegedMfaEnrollment).mockResolvedValue({
      recovery_codes: recoveryCodes,
      sessions_revoked: false,
    })
    const localStorageWrite = vi.spyOn(Storage.prototype, 'setItem')

    render(
      <PrivilegedMfaPanel
        authentication={authentication}
        locale="en-IN"
        localizationPolicy="development-preview"
        onCredentialsRevoked={vi.fn()}
      />,
    )

    await user.click(
      await screen.findByRole('button', { name: 'Set up authenticator' }),
    )
    expect(screen.getByText(/^otpauth:\/\/totp/u)).toBeInTheDocument()
    await user.type(
      screen.getByLabelText('Current six-digit authenticator code'),
      '123456',
    )
    await user.click(
      screen.getByRole('button', { name: 'Confirm authenticator' }),
    )

    expect(
      await screen.findByRole('heading', { name: 'New recovery codes' }),
    ).toBeInTheDocument()
    for (const code of recoveryCodes) {
      expect(screen.getByText(code)).toBeInTheDocument()
    }
    expect(localStorageWrite).not.toHaveBeenCalled()

    await user.click(
      screen.getByRole('button', { name: 'I saved these codes' }),
    )
    expect(screen.queryByText(recoveryCodes[0] ?? '')).not.toBeInTheDocument()
  })

  it('keeps recovery assertions replacement-only and signs out after replacement codes are acknowledged', async () => {
    const user = userEvent.setup()
    const onCredentialsRevoked = vi.fn()
    const authentication = mfaAuthentication()
    vi.mocked(authentication.privilegedMfaStatus)
      .mockResolvedValueOnce(status('active'))
      .mockResolvedValueOnce({
        ...status('active'),
        can_enroll: true,
        fresh_assertion_scope: 'factor_replacement',
        fresh_assertion_expires_at: '2026-08-08T10:15:00Z',
      })
    vi.mocked(authentication.createPrivilegedMfaChallenge).mockResolvedValue({
      challenge_id: '01J00000000000000000000021',
      expires_at: '2026-08-08T10:05:00Z',
    })
    vi.mocked(authentication.verifyPrivilegedMfaChallenge).mockResolvedValue({
      verified_at: '2026-08-08T10:00:00Z',
      scope: 'factor_replacement',
    })
    vi.mocked(authentication.beginPrivilegedMfaEnrollment).mockResolvedValue({
      factor_id: '01J00000000000000000000022',
      challenge_id: '01J00000000000000000000023',
      provisioning_uri:
        'otpauth://totp/Global%20English?secret=234567ABCDEFGHIJKLMNOPQRSTUVABCD',
      expires_at: '2026-08-08T10:05:00Z',
    })
    vi.mocked(authentication.confirmPrivilegedMfaEnrollment).mockResolvedValue({
      recovery_codes: codes('replacement'),
      sessions_revoked: true,
    })

    render(
      <PrivilegedMfaPanel
        authentication={authentication}
        locale="en-IN"
        localizationPolicy="development-preview"
        onCredentialsRevoked={onCredentialsRevoked}
      />,
    )

    await user.click(
      await screen.findByRole('button', {
        name: 'Verify for a privileged action',
      }),
    )
    await user.selectOptions(
      screen.getByLabelText('Verification method'),
      'recovery_code',
    )
    await user.type(
      screen.getByLabelText('Authenticator or recovery code'),
      'aaaaaaaa-bbbbbbbb-cccccccc-dddddddd',
    )
    await user.click(screen.getByRole('button', { name: 'Verify code' }))

    expect(authentication.verifyPrivilegedMfaChallenge).toHaveBeenCalledWith(
      '01J00000000000000000000021',
      'recovery_code',
      'aaaaaaaa-bbbbbbbb-cccccccc-dddddddd',
    )
    await user.click(
      await screen.findByRole('button', { name: 'Replace authenticator' }),
    )
    await user.type(
      screen.getByLabelText('Current six-digit authenticator code'),
      '654321',
    )
    await user.click(
      screen.getByRole('button', { name: 'Confirm authenticator' }),
    )

    expect(
      await screen.findByText(/All web sessions and Android token families/u),
    ).toBeInTheDocument()
    expect(onCredentialsRevoked).not.toHaveBeenCalled()
    await user.click(
      screen.getByRole('button', { name: 'I saved these codes' }),
    )
    expect(onCredentialsRevoked).toHaveBeenCalledOnce()
  })
})

function mfaAuthentication(): WebSessionAuthentication {
  return {
    transport: 'sanctum-cookie',
    authenticationMode: vi.fn(async () => 'password_only' as const),
    changePassword: vi.fn(),
    changeUsername: vi.fn(),
    confirmPassword: vi.fn(),
    enrollWithInvitation: vi.fn(),
    establishPassword: vi.fn(),
    passwordStatus: vi.fn(),
    requestPasswordRecovery: vi.fn(),
    signIn: vi.fn(),
    beginPrivilegedMfaEnrollment: vi.fn(),
    confirmPrivilegedMfaEnrollment: vi.fn(),
    createPrivilegedMfaChallenge: vi.fn(),
    current: vi.fn(async () => ({ status: 'anonymous' as const })),
    logout: vi.fn(async () => undefined),
    privilegedMfaStatus: vi.fn(),
    regeneratePrivilegedMfaRecoveryCodes: vi.fn(),
    requestOtp: vi.fn(),
    revokeAllSessions: vi.fn(async () => undefined),
    revokePrivilegedMfaFactor: vi.fn(async () => undefined),
    revokeSession: vi.fn(async () => undefined),
    sessions: vi.fn(async () => []),
    verifyOtp: vi.fn(async () => undefined),
    verifyPrivilegedMfaChallenge: vi.fn(),
  }
}

function status(factorStatus: 'none' | 'active') {
  return {
    factor_status: factorStatus,
    factor_id:
      factorStatus === 'active' ? '01J00000000000000000000001' : null,
    pending_expires_at: null,
    recovery_codes_remaining: factorStatus === 'active' ? 10 : 0,
    fresh_assertion_scope: null,
    fresh_assertion_expires_at: null,
    can_enroll: factorStatus === 'none',
    requires_support: false,
  } as const
}

function codes(prefix: string): [
  string,
  string,
  string,
  string,
  string,
  string,
  string,
  string,
  string,
  string,
] {
  const code = (index: number) =>
    `${prefix}-${index.toString().padStart(2, '0')}-recovery-code`

  return [
    code(0),
    code(1),
    code(2),
    code(3),
    code(4),
    code(5),
    code(6),
    code(7),
    code(8),
    code(9),
  ]
}
