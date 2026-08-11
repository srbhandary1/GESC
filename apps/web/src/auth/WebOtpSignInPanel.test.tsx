import { cleanup, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import type {
  AuthenticationSnapshot,
  WebSessionAuthentication,
} from './AuthenticationAdapter'
import { WebOtpSignInPanel } from './WebOtpSignInPanel'
import { resolveInterfaceMessage } from '../localization/interfaceLocalization'

afterEach(() => {
  cleanup()
  vi.restoreAllMocks()
})

describe('web OTP sign-in panel', () => {
  it('keeps an optional invitation code in component memory and submits it only with verification', async () => {
    const user = userEvent.setup()
    const authentication = webAuthentication()
    const onAuthenticated = vi.fn()
    const localStorageWrite = vi.spyOn(Storage.prototype, 'setItem')
    const indexedDbOpen =
      globalThis.indexedDB === undefined
        ? null
        : vi.spyOn(globalThis.indexedDB, 'open')
    vi.mocked(authentication.requestOtp).mockResolvedValue({
      challenge_id: '01J00000000000000000000001',
      expires_at: '2026-08-09T10:05:00Z',
    })
    vi.mocked(authentication.current).mockResolvedValue(
      authenticatedSnapshot(),
    )

    render(
      <WebOtpSignInPanel
        authentication={authentication}
        message={message}
        onAuthenticated={onAuthenticated}
      />,
    )

    await user.type(screen.getByLabelText('Mobile number'), '+919876543210')
    const invitation = screen.getByLabelText(
      'Organization invitation code (optional)',
    )
    expect(invitation).toHaveAttribute('autocomplete', 'off')
    expect(invitation).toHaveAttribute('dir', 'ltr')
    await user.type(invitation, 'GE-INVITE-ONLY-ONCE')
    await user.click(
      screen.getByRole('button', { name: 'Send one-time code' }),
    )

    expect(authentication.requestOtp).toHaveBeenCalledWith('+919876543210')
    expect(screen.queryByDisplayValue('GE-INVITE-ONLY-ONCE')).not.toBeInTheDocument()
    const otp = await screen.findByLabelText('One-time code')
    expect(otp).toHaveFocus()
    await user.type(otp, '123456')
    await user.click(screen.getByRole('button', { name: 'Verify and sign in' }))

    expect(authentication.verifyOtp).toHaveBeenCalledWith(
      '01J00000000000000000000001',
      '+919876543210',
      '123456',
      'GE-INVITE-ONLY-ONCE',
    )
    expect(onAuthenticated).toHaveBeenCalledWith(authenticatedSnapshot())
    expect(localStorageWrite).not.toHaveBeenCalled()
    expect(indexedDbOpen?.mock.calls ?? []).toHaveLength(0)
  })

  it('uses an enumeration-safe error and retains no invitation code in the message', async () => {
    const user = userEvent.setup()
    const authentication = webAuthentication()
    vi.mocked(authentication.requestOtp).mockRejectedValue(
      new Error('invite GE-SECRET was revoked for identity +919999999999'),
    )

    render(
      <WebOtpSignInPanel
        authentication={authentication}
        message={message}
        onAuthenticated={vi.fn()}
      />,
    )

    await user.type(screen.getByLabelText('Mobile number'), '+919999999999')
    await user.type(
      screen.getByLabelText('Organization invitation code (optional)'),
      'GE-SECRET',
    )
    await user.click(
      screen.getByRole('button', { name: 'Send one-time code' }),
    )

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Sign-in could not be completed. Check the details and try again.',
    )
    expect(screen.getByRole('alert')).not.toHaveTextContent(/GE-SECRET|999999/u)
  })
})

const message = (key: Parameters<typeof resolveInterfaceMessage>[0]) =>
  resolveInterfaceMessage(key, 'en-IN', 'development-preview')

function webAuthentication(): WebSessionAuthentication {
  return {
    transport: 'sanctum-cookie',
    authenticationMode: vi.fn(async () => 'credential_migration' as const),
    changePassword: vi.fn(),
    changeUsername: vi.fn(),
    confirmPassword: vi.fn(),
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

function authenticatedSnapshot(): AuthenticationSnapshot {
  return {
    identity: {
      current_membership: {
        id: '01J00000000000000000000002',
        membership_type: 'trainer',
        organization: {
          id: '01J00000000000000000000003',
          kind: 'institution',
          name: 'Hithaishi Learning Hub',
        },
        role_keys: [],
        status: 'active',
      },
      memberships: [],
      user: {
        id: '01J00000000000000000000004',
      },
    },
    status: 'authenticated',
  }
}
