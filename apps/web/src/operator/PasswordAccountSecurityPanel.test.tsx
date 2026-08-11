import { cleanup, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import type { WebSessionAuthentication } from '../auth/AuthenticationAdapter'
import { PasswordAccountSecurityPanel } from './PasswordAccountSecurityPanel'

afterEach(() => {
  cleanup()
  vi.restoreAllMocks()
})

describe('password account security panel', () => {
  it('explains one-time credential setup and exposes the save action', async () => {
    const authentication = webAuthentication()

    render(
      <PasswordAccountSecurityPanel
        authentication={authentication}
        locale="en-IN"
        localizationPolicy="development-preview"
        onCredentialsRevoked={vi.fn()}
      />,
    )

    expect(
      await screen.findByText(/This is one-time setup, not sign-in/u),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: 'Set username and password' }),
    ).toBeVisible()

    const username = screen.getByLabelText('Username')
    const password = screen.getByLabelText(
      'New password (15 characters or more)',
    )
    expect(username).toHaveAttribute(
      'pattern',
      '[a-zA-Z][a-zA-Z0-9._-]{3,63}',
    )
    expect(username).toHaveAttribute('maxlength', '64')
    expect(password).toHaveAttribute('minlength', '15')
    expect(password).toHaveAttribute('maxlength', '128')
  })

  it('saves valid credentials, supports password visibility, and signs out', async () => {
    const user = userEvent.setup()
    const authentication = webAuthentication()
    const onCredentialsRevoked = vi.fn()

    render(
      <PasswordAccountSecurityPanel
        authentication={authentication}
        locale="en-IN"
        localizationPolicy="development-preview"
        onCredentialsRevoked={onCredentialsRevoked}
      />,
    )

    const username = await screen.findByLabelText('Username')
    const password = screen.getByLabelText(
      'New password (15 characters or more)',
    )
    await user.type(username, 'learner.one')
    await user.type(password, 'Correct horse battery 2026!')
    await user.click(screen.getByLabelText('Show password'))
    expect(password).toHaveAttribute('type', 'text')
    await user.click(
      screen.getByRole('button', { name: 'Set username and password' }),
    )

    expect(authentication.establishPassword).toHaveBeenCalledWith(
      'learner.one',
      'Correct horse battery 2026!',
    )
    expect(onCredentialsRevoked).toHaveBeenCalledOnce()
    expect(password).toHaveValue('')
  })

  it('shows actionable setup guidance when the server rejects credentials', async () => {
    const user = userEvent.setup()
    const authentication = webAuthentication()
    vi.mocked(authentication.establishPassword).mockRejectedValue(
      new Error('rejected'),
    )

    render(
      <PasswordAccountSecurityPanel
        authentication={authentication}
        locale="en-IN"
        localizationPolicy="development-preview"
        onCredentialsRevoked={vi.fn()}
      />,
    )

    await user.type(await screen.findByLabelText('Username'), 'learner.one')
    await user.type(
      screen.getByLabelText('New password (15 characters or more)'),
      'Correct horse battery 2026!',
    )
    await user.click(
      screen.getByRole('button', { name: 'Set username and password' }),
    )

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Check the username rules, use a different non-common password, and try again.',
    )
  })

  it('does not offer privileged confirmation or username change to an ordinary learner session', async () => {
    const authentication = webAuthentication({
      credentialReady: true,
      privileged: false,
    })

    render(
      <PasswordAccountSecurityPanel
        authentication={authentication}
        locale="en-IN"
        localizationPolicy="development-preview"
        onCredentialsRevoked={vi.fn()}
      />,
    )

    expect(
      await screen.findByText(
        'Review your username, change your password, manage signed-in sessions, and check recovery readiness.',
      ),
    ).toBeInTheDocument()
    expect(
      screen.queryByRole('button', { name: 'Confirm password' }),
    ).not.toBeInTheDocument()
    expect(
      screen.queryByRole('button', { name: 'Change username' }),
    ).not.toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: 'Change password' }),
    ).toBeVisible()
  })

  it('shows confirmation success for a privileged session', async () => {
    const user = userEvent.setup()
    const authentication = webAuthentication({
      credentialReady: true,
      privileged: true,
    })

    render(
      <PasswordAccountSecurityPanel
        authentication={authentication}
        locale="en-IN"
        localizationPolicy="development-preview"
        onCredentialsRevoked={vi.fn()}
      />,
    )

    await user.type(
      (await screen.findAllByLabelText('Current password'))[0]!,
      'Correct horse battery 2026!',
    )
    await user.click(screen.getByRole('button', { name: 'Confirm password' }))

    expect(authentication.confirmPassword).toHaveBeenCalledWith(
      'Correct horse battery 2026!',
    )
    expect(
      await screen.findByText('Password confirmed for five minutes.'),
    ).toBeInTheDocument()
  })

  it('shows actionable guidance when privileged confirmation fails', async () => {
    const user = userEvent.setup()
    const authentication = webAuthentication({
      credentialReady: true,
      privileged: true,
    })
    vi.mocked(authentication.confirmPassword).mockRejectedValue(
      new Error('rejected'),
    )

    render(
      <PasswordAccountSecurityPanel
        authentication={authentication}
        locale="en-IN"
        localizationPolicy="development-preview"
        onCredentialsRevoked={vi.fn()}
      />,
    )

    await user.type(
      (await screen.findAllByLabelText('Current password'))[0]!,
      'Incorrect password',
    )
    await user.click(screen.getByRole('button', { name: 'Confirm password' }))

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Password confirmation failed. Re-enter the current password for this username and try again.',
    )
  })
})

function webAuthentication(
  options: { credentialReady?: boolean; privileged?: boolean } = {},
): WebSessionAuthentication {
  const credentialReady = options.credentialReady ?? false

  return {
    transport: 'sanctum-cookie',
    authenticationMode: vi.fn(async () => 'credential_migration' as const),
    beginPrivilegedMfaEnrollment: vi.fn(),
    changePassword: vi.fn(async () => undefined),
    changeUsername: vi.fn(async () => undefined),
    confirmPassword: vi.fn(async () => undefined),
    confirmPrivilegedMfaEnrollment: vi.fn(),
    createPrivilegedMfaChallenge: vi.fn(),
    current: vi.fn(async () => ({ status: 'anonymous' as const })),
    establishPassword: vi.fn(async () => undefined),
    logout: vi.fn(async () => undefined),
    passwordStatus: vi.fn(async () => ({
      credential_ready: credentialReady,
      mode: credentialReady
        ? ('password_only' as const)
        : ('credential_migration' as const),
      password_confirmed_at: null,
      username: credentialReady ? 'learner.one' : null,
    })),
    privilegedMfaStatus: vi.fn(),
    regeneratePrivilegedMfaRecoveryCodes: vi.fn(),
    requestOtp: vi.fn(),
    requestPasswordRecovery: vi.fn(async () => undefined),
    revokeAllSessions: vi.fn(async () => undefined),
    revokePrivilegedMfaFactor: vi.fn(),
    revokeSession: vi.fn(async () => undefined),
    sessions: vi.fn(async () => [
      {
        absolute_expires_at: '2026-08-12T10:00:00Z',
        created_at: '2026-08-11T10:00:00Z',
        current: true,
        expires_at: '2026-08-11T11:00:00Z',
        id: '01J00000000000000000000001',
        last_activity_at: '2026-08-11T10:00:00Z',
        mfa_verified_at: null,
        mfa_verified_method: null,
        mfa_verified_scope: null,
        password_confirmed_at: null,
        privileged: options.privileged ?? false,
      },
    ]),
    signIn: vi.fn(async () => undefined),
    verifyOtp: vi.fn(async () => undefined),
    verifyPrivilegedMfaChallenge: vi.fn(),
  }
}
