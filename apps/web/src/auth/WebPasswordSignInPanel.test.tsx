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
  Object.defineProperty(window, 'isSecureContext', {
    configurable: true,
    value: true,
  })
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
    expect(username).toHaveAttribute('pattern', '[A-Za-z][A-Za-z0-9._-]{3,63}')
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

  it('creates an invited account without retaining its code or password', async () => {
    const user = userEvent.setup()
    const authentication = webAuthentication()
    const localStorageWrite = vi.spyOn(Storage.prototype, 'setItem')

    render(
      <WebPasswordSignInPanel
        authentication={authentication}
        message={message}
        onAuthenticated={vi.fn()}
      />,
    )

    await user.click(
      screen.getByRole('button', { name: 'I have an invitation code' }),
    )
    await user.type(
      screen.getByLabelText('Invitation code'),
      'GEI-CLOSED-GROUP-2026',
    )
    await user.type(screen.getByLabelText('Username'), 'closed.tester')
    await user.type(
      screen.getByLabelText('Password'),
      'Closed group learner phrase 2026!',
    )
    await user.type(
      screen.getByLabelText('Confirm password'),
      'Closed group learner phrase 2026!',
    )
    await user.click(screen.getByRole('button', { name: 'Create account' }))

    expect(authentication.enrollWithInvitation).toHaveBeenCalledWith(
      'GEI-CLOSED-GROUP-2026',
      'closed.tester',
      'Closed group learner phrase 2026!',
    )
    expect(await screen.findByRole('status')).toHaveTextContent(
      'Your account is ready. You can sign in now.',
    )
    expect(screen.queryByDisplayValue('GEI-CLOSED-GROUP-2026')).toBeNull()
    expect(
      screen.queryByDisplayValue('Closed group learner phrase 2026!'),
    ).toBeNull()
    expect(localStorageWrite).not.toHaveBeenCalled()
  })

  it('offers Google and Facebook beside the password form', async () => {
    const user = userEvent.setup()
    const authentication = webAuthentication()
    vi.mocked(authentication.federatedProviders).mockResolvedValue({
      google: true,
      facebook: true,
    })

    render(
      <WebPasswordSignInPanel
        authentication={authentication}
        message={message}
        onAuthenticated={vi.fn()}
      />,
    )

    expect(
      await screen.findByRole('button', { name: 'Continue with Google' }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: 'Continue with Facebook' }),
    ).toBeInTheDocument()
    expect(screen.getByLabelText('Username')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Continue with Google' }))
    expect(authentication.beginFederatedSignIn).toHaveBeenCalledWith('google')
    expect(authentication.signIn).not.toHaveBeenCalled()
  })

  it('explains that plain HTTP cannot complete sign-in', () => {
    vi.stubGlobal('location', {
      hostname: '117.254.108.143',
      protocol: 'http:',
    })

    render(
      <WebPasswordSignInPanel
        authentication={webAuthentication()}
        message={message}
        onAuthenticated={vi.fn()}
      />,
    )

    expect(
      screen.getByRole('alert'),
    ).toHaveTextContent(
      'Use the HTTPS link to sign in. This page is plain HTTP, so the browser cannot keep a sign-in cookie.',
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
    enrollWithInvitation: vi.fn(async () => undefined),
    federatedProviders: vi.fn(async () => ({
      google: false,
      facebook: false,
    })),
    beginFederatedSignIn: vi.fn(async () => undefined),
    federatedLinks: vi.fn(async () => ({ google: false, facebook: false })),
    beginFederatedLink: vi.fn(async () => undefined),
    unlinkFederated: vi.fn(async () => undefined),
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
