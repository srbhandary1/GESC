import { cleanup, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import type {
  AuthenticationSnapshot,
  WebSessionAuthentication,
} from './AuthenticationAdapter'
import { WebPasswordSignInPanel } from './WebPasswordSignInPanel'
import { resolveInterfaceMessage } from '../localization/interfaceLocalization'

afterEach(() => {
  cleanup()
  vi.restoreAllMocks()
})

describe('web password sign-in panel', () => {
  it('supports password managers, show/hide, and keeps credentials out of browser storage', async () => {
    const user = userEvent.setup()
    const authentication = webAuthentication()
    const onAuthenticated = vi.fn()
    const localStorageWrite = vi.spyOn(Storage.prototype, 'setItem')
    const indexedDbOpen =
      globalThis.indexedDB === undefined
        ? null
        : vi.spyOn(globalThis.indexedDB, 'open')
    vi.mocked(authentication.current).mockResolvedValue(authenticatedSnapshot())

    render(
      <WebPasswordSignInPanel
        authentication={authentication}
        message={message}
        onAuthenticated={onAuthenticated}
      />,
    )

    const username = screen.getByLabelText('Username')
    const password = screen.getByLabelText('Password')
    expect(username).toHaveAttribute('autocomplete', 'username')
    expect(password).toHaveAttribute('autocomplete', 'current-password')
    expect(password).toHaveAttribute('type', 'password')
    await user.type(username, 'learner.one')
    await user.type(password, 'Correct horse battery 2026!')
    await user.click(screen.getByLabelText('Show password'))
    expect(password).toHaveAttribute('type', 'text')
    await user.click(screen.getByRole('button', { name: 'Sign in' }))

    expect(authentication.signIn).toHaveBeenCalledWith(
      'learner.one',
      'Correct horse battery 2026!',
    )
    expect(onAuthenticated).toHaveBeenCalledWith(authenticatedSnapshot())
    expect(password).toHaveValue('')
    expect(localStorageWrite).not.toHaveBeenCalled()
    expect(indexedDbOpen?.mock.calls ?? []).toHaveLength(0)
  })

  it('shows the same generic recovery response without exposing account state', async () => {
    const user = userEvent.setup()
    const authentication = webAuthentication()

    render(
      <WebPasswordSignInPanel
        authentication={authentication}
        message={message}
        onAuthenticated={vi.fn()}
      />,
    )

    await user.click(screen.getByRole('button', { name: 'Forgot password?' }))
    await user.type(screen.getByLabelText('Username'), 'unknown.user')
    await user.click(
      screen.getByRole('button', { name: 'Send recovery instructions' }),
    )

    expect(authentication.requestPasswordRecovery).toHaveBeenCalledWith(
      'unknown.user',
    )
    expect(await screen.findByRole('status')).toHaveTextContent(
      'If the account is eligible, recovery instructions will be sent.',
    )
  })
})

const message = (key: Parameters<typeof resolveInterfaceMessage>[0]) =>
  resolveInterfaceMessage(key, 'en-IN', 'development-preview')

function webAuthentication(): WebSessionAuthentication {
  return {
    transport: 'sanctum-cookie',
    authenticationMode: vi.fn(async () => 'password_only' as const),
    beginPrivilegedMfaEnrollment: vi.fn(),
    changePassword: vi.fn(async () => undefined),
    changeUsername: vi.fn(async () => undefined),
    confirmPassword: vi.fn(async () => undefined),
    confirmPrivilegedMfaEnrollment: vi.fn(),
    createPrivilegedMfaChallenge: vi.fn(),
    current: vi.fn(async () => ({ status: 'anonymous' as const })),
    establishPassword: vi.fn(async () => undefined),
    logout: vi.fn(async () => undefined),
    passwordStatus: vi.fn(),
    privilegedMfaStatus: vi.fn(),
    regeneratePrivilegedMfaRecoveryCodes: vi.fn(),
    requestOtp: vi.fn(),
    requestPasswordRecovery: vi.fn(async () => undefined),
    revokeAllSessions: vi.fn(async () => undefined),
    revokePrivilegedMfaFactor: vi.fn(),
    revokeSession: vi.fn(async () => undefined),
    sessions: vi.fn(async () => []),
    signIn: vi.fn(async () => undefined),
    verifyOtp: vi.fn(async () => undefined),
    verifyPrivilegedMfaChallenge: vi.fn(),
  }
}

function authenticatedSnapshot(): AuthenticationSnapshot {
  return {
    status: 'authenticated',
    identity: {
      user: { id: '01J00000000000000000000001' },
      current_membership: null,
      memberships: [],
    },
  }
}
