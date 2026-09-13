import { cleanup, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import type {
  AuthenticationSnapshot,
  PasswordAuthenticationStatus,
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

    expect(
      await screen.findByRole('heading', {
        name: 'Sign in with username and password',
      }),
    ).toBeInTheDocument()
    expect(
      screen.queryByLabelText('Mobile number'),
    ).not.toBeInTheDocument()
    expect(
      screen.queryByLabelText('One-time code'),
    ).not.toBeInTheDocument()

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

  it('hides the password form when the host is legacy OTP-only', async () => {
    const authentication = webAuthentication('legacy_otp')

    render(
      <WebPasswordSignInPanel
        authentication={authentication}
        message={message}
        onAuthenticated={vi.fn()}
      />,
    )

    expect(
      await screen.findByText(
        'One-time-code sign-in is not available in this shell. Use username and password when the host is password-only.',
      ),
    ).toBeInTheDocument()
    expect(screen.queryByLabelText('Username')).not.toBeInTheDocument()
    expect(screen.queryByLabelText('Password')).not.toBeInTheDocument()
    expect(screen.queryByLabelText('Mobile number')).not.toBeInTheDocument()
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
})

const message = (key: Parameters<typeof resolveInterfaceMessage>[0]) =>
  resolveInterfaceMessage(key, 'en-IN', 'development-preview')

function webAuthentication(
  mode: PasswordAuthenticationStatus['mode'] = 'password_only',
): WebSessionAuthentication {
  return {
    transport: 'sanctum-cookie',
    authenticationMode: vi.fn(async () => mode),
    current: vi.fn(async () => ({ status: 'anonymous' as const })),
    enrollWithInvitation: vi.fn(async () => undefined),
    logout: vi.fn(async () => undefined),
    requestOtp: vi.fn(),
    requestPasswordRecovery: vi.fn(async () => undefined),
    revokeAllSessions: vi.fn(async () => undefined),
    revokeSession: vi.fn(async () => undefined),
    sessions: vi.fn(async () => []),
    signIn: vi.fn(async () => undefined),
    verifyOtp: vi.fn(async () => undefined),
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
