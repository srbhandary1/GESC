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

    const username = screen.getByLabelText('Phone or username')
    const password = screen.getByLabelText('Password')
    expect(username).toHaveAttribute('autocomplete', 'username')
    expect(username).toHaveAttribute(
      'pattern',
      '([A-Za-z][A-Za-z0-9._\\-]{3,63}|\\+?[0-9]{7,20})',
    )
    expect(password).toHaveAttribute('autocomplete', 'current-password')
    expect(password).toHaveAttribute('type', 'password')
    await user.type(username, 'learner.one')
    await user.type(password, 'Correct horse battery 2026!')
    await user.click(screen.getByLabelText('Show password'))
    expect(password).toHaveAttribute('type', 'text')
    expect(screen.getByLabelText('Show password')).toHaveAttribute(
      'id',
      'web-password-show',
    )
    expect(screen.getByLabelText('Show password')).not.toHaveAttribute(
      'readonly',
    )
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

  it('accepts a previous-website phone number as the sign-in identifier', async () => {
    const user = userEvent.setup()
    const authentication = webAuthentication()
    vi.mocked(authentication.current).mockResolvedValue(authenticatedSnapshot())

    render(
      <WebPasswordSignInPanel
        authentication={authentication}
        message={message}
        onAuthenticated={vi.fn()}
      />,
    )

    await user.type(screen.getByLabelText('Phone or username'), '7439091943')
    await user.type(screen.getByLabelText('Password'), '123')
    await user.click(screen.getByRole('button', { name: 'Sign in' }))

    expect(authentication.signIn).toHaveBeenCalledWith('7439091943', '123')
  })

  it('keeps Sign in as the only primary action', () => {
    render(
      <WebPasswordSignInPanel
        authentication={webAuthentication()}
        message={message}
        onAuthenticated={vi.fn()}
      />,
    )

    expect(screen.getByRole('button', { name: 'Sign in' })).toHaveClass(
      'ge-button--primary',
    )
    expect(
      screen.getByRole('button', { name: 'Pay and create an account' }),
    ).toHaveClass('ge-button')
    expect(
      screen.getByRole('button', { name: 'Pay and create an account' }),
    ).not.toHaveClass('ge-button--primary')
    expect(screen.getByRole('button', { name: 'Forgot password?' })).toHaveClass(
      'web-password-sign-in__text-action',
    )
    expect(
      screen.getByRole('button', { name: 'I have an invitation code' }),
    ).toHaveClass('web-password-sign-in__text-action')
  })

  it('keeps the password and shows the error above Sign in when sign-in fails', async () => {
    const user = userEvent.setup()
    const authentication = webAuthentication()
    vi.mocked(authentication.signIn).mockRejectedValue(new Error('unavailable'))

    render(
      <WebPasswordSignInPanel
        authentication={authentication}
        message={message}
        onAuthenticated={vi.fn()}
      />,
    )

    await user.type(screen.getByLabelText('Phone or username'), 'learner.one')
    await user.type(
      screen.getByLabelText('Password'),
      'Correct horse battery 2026!',
    )
    await user.click(screen.getByRole('button', { name: 'Sign in' }))

    const alert = await screen.findByRole('alert')
    expect(alert).toHaveTextContent(
      'Sign-in could not be completed. Check the details and try again.',
    )
    expect(screen.getByLabelText('Password')).toHaveValue(
      'Correct horse battery 2026!',
    )
    expect(screen.getByLabelText('Phone or username')).toHaveAttribute(
      'aria-invalid',
      'true',
    )
    const submit = screen.getByRole('button', { name: 'Sign in' })
    expect(
      alert.compareDocumentPosition(submit) & Node.DOCUMENT_POSITION_FOLLOWING,
    ).toBeTruthy()
  })

  it('does not treat an anonymous session after a 200 sign-in as success', async () => {
    const user = userEvent.setup()
    const authentication = webAuthentication()
    const onAuthenticated = vi.fn()
    vi.mocked(authentication.current).mockResolvedValue({
      status: 'anonymous',
    })

    render(
      <WebPasswordSignInPanel
        authentication={authentication}
        message={message}
        onAuthenticated={onAuthenticated}
      />,
    )

    await user.type(screen.getByLabelText('Phone or username'), 'learner.one')
    await user.type(
      screen.getByLabelText('Password'),
      'Correct horse battery 2026!',
    )
    await user.click(screen.getByRole('button', { name: 'Sign in' }))

    expect(onAuthenticated).not.toHaveBeenCalled()
    expect(await screen.findByRole('alert')).toBeInTheDocument()
    expect(screen.getByLabelText('Password')).toHaveValue(
      'Correct horse battery 2026!',
    )
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
    await user.type(screen.getByLabelText('Phone or username'), 'unknown.user')
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
    expect(screen.getByLabelText('Username')).toHaveAttribute(
      'pattern',
      '[A-Za-z][A-Za-z0-9._\\-]{3,63}',
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

  it('offers Google, Facebook, and Instagram beside the password form and for paid registration', async () => {
    const user = userEvent.setup()
    const authentication = webAuthentication()
    vi.mocked(authentication.federatedProviders).mockResolvedValue({
      google: true,
      facebook: true,
      instagram: true,
    })

    const view = render(
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
    expect(
      screen.getByRole('button', { name: 'Continue with Instagram' }),
    ).toBeInTheDocument()
    expect(screen.getByLabelText('Phone or username')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Continue with Google' }))
    expect(authentication.beginFederatedSignIn).toHaveBeenCalledWith('google')
    expect(authentication.signIn).not.toHaveBeenCalled()

    const paidAuth = {
      ...authentication,
      listDirectPlans: vi.fn(async () => paidPlans()),
      registerWithPayment: vi.fn(async () => undefined),
    }
    view.rerender(
      <WebPasswordSignInPanel
        authentication={paidAuth}
        message={message}
        onAuthenticated={vi.fn()}
        paidPlanCode="monthly_modules_roleplay"
      />,
    )

    expect(
      await screen.findByRole('radio', { name: /Premium with Role-play/ }),
    ).toBeChecked()
    await user.click(
      screen.getByRole('button', { name: 'Continue with Instagram' }),
    )
    expect(paidAuth.beginFederatedSignIn).toHaveBeenCalledWith('instagram', {
      intent: 'register',
      planCode: 'monthly_modules_roleplay',
      planId: '01JPLAN00000000000000000003',
    })
  })

  it('explains that plain HTTP cannot complete sign-in', () => {
    vi.stubGlobal('location', {
      hostname: 'example.com',
      port: '',
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

  it('allows password sign-in on trusted private HTTP hosts', () => {
    vi.stubGlobal('location', {
      hostname: '100.89.51.18',
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
      screen.queryByText(
        'Use the HTTPS link to sign in. This page is plain HTTP, so the browser cannot keep a sign-in cookie.',
      ),
    ).toBeNull()
  })

  it('shows the selected plan total and refuses a mismatched password before payment', async () => {
    const user = userEvent.setup()
    const registerWithPayment = vi.fn(async () => undefined)
    const authentication = {
      ...webAuthentication(),
      listDirectPlans: vi.fn(async () => paidPlans()),
      registerWithPayment,
    }

    render(
      <WebPasswordSignInPanel
        authentication={authentication}
        message={message}
        onAuthenticated={vi.fn()}
        paidPlanCode="monthly_modules"
      />,
    )

    expect(
      await screen.findByRole('radio', { name: /Premium features/ }),
    ).toBeChecked()
    expect(screen.getByText(/Role-play is not included/)).toBeInTheDocument()
    expect(screen.getAllByText(/₹354\.00/).length).toBeGreaterThan(0)
    expect(screen.getByText(/₹300\.00/)).toBeInTheDocument()
    expect(screen.getByText(/₹54\.00/)).toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: 'Pay ₹354.00 for Premium features' }),
    ).toBeInTheDocument()

    await user.type(screen.getByLabelText('Phone or username'), '7439091943')
    await user.type(screen.getByLabelText('Password'), 'Correct horse battery 2026!')
    await user.type(screen.getByLabelText('Confirm password'), 'Different horse battery 2026!')
    await user.click(
      screen.getByRole('button', { name: 'Pay ₹354.00 for Premium features' }),
    )

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Enter the same password twice.',
    )
    expect(registerWithPayment).not.toHaveBeenCalled()
  })

  it('registers with the plan the student chooses', async () => {
    const user = userEvent.setup()
    const registerWithPayment = vi.fn(async () => undefined)
    const authentication = {
      ...webAuthentication(),
      current: vi.fn(async () => authenticatedSnapshot()),
      listDirectPlans: vi.fn(async () => paidPlans()),
      registerWithPayment,
    }

    render(
      <WebPasswordSignInPanel
        authentication={authentication}
        message={message}
        onAuthenticated={vi.fn()}
        paidPlanCode="demo"
      />,
    )

    expect(await screen.findByRole('radio', { name: /5-day start/ })).toBeChecked()
    await user.click(screen.getByRole('radio', { name: /Premium with Role-play/ }))
    await user.type(screen.getByLabelText('Phone or username'), '7439091943')
    await user.type(screen.getByLabelText('Password'), 'Correct horse battery 2026!')
    await user.type(screen.getByLabelText('Confirm password'), 'Correct horse battery 2026!')
    await user.click(
      screen.getByRole('button', { name: 'Pay ₹590.00 for Premium with Role-play' }),
    )

    expect(registerWithPayment).toHaveBeenCalledWith(
      '7439091943',
      'Correct horse battery 2026!',
      '01JPLAN00000000000000000003',
    )
  })

  it('clears a payment message when the student returns to sign-in', async () => {
    const user = userEvent.setup()
    const authentication = {
      ...webAuthentication(),
      listDirectPlans: vi.fn(async () => paidPlans()),
      registerWithPayment: vi.fn(async () => undefined),
    }
    const view = render(
      <WebPasswordSignInPanel
        authentication={authentication}
        message={message}
        onAuthenticated={vi.fn()}
        paidPlanCode="monthly_modules"
      />,
    )

    await user.type(await screen.findByLabelText('Phone or username'), '7439091943')
    await user.type(screen.getByLabelText('Password'), 'Correct horse battery 2026!')
    await user.type(screen.getByLabelText('Confirm password'), 'Different horse battery 2026!')
    await user.click(
      screen.getByRole('button', { name: 'Pay ₹354.00 for Premium features' }),
    )
    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Enter the same password twice.',
    )

    view.rerender(
      <WebPasswordSignInPanel
        authentication={authentication}
        message={message}
        onAuthenticated={vi.fn()}
        paidPlanCode={null}
      />,
    )

    expect(screen.queryByRole('alert')).toBeNull()
    expect(screen.getByRole('button', { name: 'Sign in' })).toBeInTheDocument()
  })
})

const message = (
  key: Parameters<typeof resolveInterfaceMessage>[0],
  parameters: Parameters<typeof resolveInterfaceMessage>[3] = {},
) => resolveInterfaceMessage(key, 'en-IN', 'development-preview', parameters)

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

function paidPlans() {
  return [
    {
      allowsRoleplay: false,
      amountExGstPaise: 300,
      billingPeriod: 'hours',
      code: 'demo',
      gstPaise: 54,
      id: '01JPLAN00000000000000000002',
      isDemo: true,
      isTalkPack: false,
      liveAiCapSeconds: null,
      maxLevelNumber: 2,
      name: '5-day start',
      recommended: false,
      totalPaise: 354,
    },
    {
      allowsRoleplay: false,
      amountExGstPaise: 30000,
      billingPeriod: 'calendar_month',
      code: 'monthly_modules',
      gstPaise: 5400,
      id: '01JPLAN00000000000000000001',
      isDemo: false,
      isTalkPack: false,
      liveAiCapSeconds: 7200,
      maxLevelNumber: 17,
      name: 'Premium features',
      recommended: false,
      totalPaise: 35400,
    },
    {
      allowsRoleplay: true,
      amountExGstPaise: 50000,
      billingPeriod: 'calendar_month',
      code: 'monthly_modules_roleplay',
      gstPaise: 9000,
      id: '01JPLAN00000000000000000003',
      isDemo: false,
      isTalkPack: false,
      liveAiCapSeconds: 7200,
      maxLevelNumber: 17,
      name: 'Premium with Role-play',
      recommended: true,
      totalPaise: 59000,
    },
  ]
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
