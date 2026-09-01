import {
  DEFAULT_LOCALE,
  resolveMessage,
  SUPPORTED_LOCALES,
  type MessageKey,
  type ResolutionPolicy,
  type SupportedLocale,
} from '@global-english/localization'
import { cleanup, render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import App from './App'
import type {
  AndroidPkceAuthentication,
  AuthenticationAdapter,
  AuthenticationSnapshot,
} from './auth/AuthenticationAdapter'
import type { LearnerJourneyApi } from './learner/LearnerJourneyApi'
import { createLocalDemoRuntime } from './demo/localDemo'

beforeEach(() => {
  document.documentElement.removeAttribute('data-theme')
  window.localStorage.removeItem('ge-ui-color-scheme')
  window.localStorage.removeItem('ge-ui-practice-about-dismissed')
  window.localStorage.setItem('ge-ui-grove-welcome-dismissed', '1')
})

afterEach(() => {
  cleanup()
  window.history.replaceState(null, '', '/')
  document.documentElement.lang = DEFAULT_LOCALE
  document.documentElement.removeAttribute('data-theme')
  window.localStorage.removeItem('ge-ui-color-scheme')
  window.localStorage.removeItem('ge-ui-practice-about-dismissed')
  vi.restoreAllMocks()
})

describe('localized application shell', () => {
  it('opens the explicit synthetic Phase 1 dashboard shell', async () => {
    const demo = createLocalDemoRuntime()

    render(
      <App
        authentication={demo.authentication}
        conversationApi={demo.conversationApi}
        demoMode
        learnerJourneyApi={demo.learnerJourneyApi}
      />,
    )

    expect(
      screen.queryByRole('complementary', {
        name: text('demo.label'),
      }),
    ).not.toBeInTheDocument()
    expect(
      await screen.findByRole('heading', {
        name: text('demo.phase1.home.title'),
      }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('navigation', {
        name: text('demo.phase1.nav_label'),
      }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('button', {
        name: new RegExp(text('demo.phase1.home.next_action')),
      }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('navigation', {
        name: text('shell.navigation_label'),
      }),
    ).toBeInTheDocument()
    expect(
      screen
        .getByRole('navigation', { name: text('shell.navigation_label') })
        .querySelector('[data-destination="practice"]'),
    ).toHaveAttribute('href', '#practice')
    expect(
      screen.getByRole('link', { name: text('nav.learn') }),
    ).toHaveAttribute('href', '#learn')
    expect(
      screen.getByRole('link', { name: text('nav.progress') }),
    ).toHaveAttribute('href', '#progress')
    expect(
      screen.queryByText('Global English Local Demo'),
    ).not.toBeInTheDocument()
    expect(
      screen.getByRole('button', {
        name: new RegExp(text('shell.theme.label')),
      }),
    ).toBeInTheDocument()

    await userEvent.click(
      screen.getByRole('link', { name: text('shell.profile_label') }),
    )
    expect(
      screen.getByRole('heading', {
        name: text('demo.phase1.profile.demo_status'),
      }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('complementary', {
        name: text('demo.label'),
      }),
    ).toHaveAttribute('data-demo-mode', 'synthetic-local')
    expect(
      screen.getByText('Global English Local Demo'),
    ).toBeInTheDocument()
  })

  it('toggles a persisted light/dark appearance without touching credentials', async () => {
    const user = userEvent.setup()
    const demo = createLocalDemoRuntime()

    render(
      <App
        authentication={demo.authentication}
        conversationApi={demo.conversationApi}
        demoMode
        learnerJourneyApi={demo.learnerJourneyApi}
      />,
    )

    const toggle = screen.getByRole('button', {
      name: new RegExp(text('shell.theme.label')),
    })
    expect(toggle).toHaveAttribute('aria-pressed', 'true')
    await user.click(toggle)
    expect(document.documentElement.dataset.theme).toBe('light')
    expect(window.localStorage.getItem('ge-ui-color-scheme')).toBe('light')
    expect(toggle).toHaveAttribute('aria-pressed', 'false')
  })

  it('opens Friend speaking practice from the demo primary navigation', async () => {
    const user = userEvent.setup()
    const demo = createLocalDemoRuntime()

    render(
      <App
        authentication={demo.authentication}
        conversationApi={demo.conversationApi}
        demoMode
        learnerJourneyApi={demo.learnerJourneyApi}
      />,
    )

    await user.click(
      document.querySelector<HTMLAnchorElement>('[data-destination="practice"]')!,
    )

    expect(
      await screen.findByRole('heading', {
        level: 1,
        name: text('demo.page.practice.title'),
      }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('heading', {
        level: 2,
        name: text('conversation.demo.title'),
      }),
    ).toBeInTheDocument()
  })

  it('opens My Levels from the demo Phase 1 module hash', async () => {
    window.history.replaceState(null, '', '/#levels')
    const demo = createLocalDemoRuntime()

    render(
      <App
        authentication={demo.authentication}
        conversationApi={demo.conversationApi}
        demoMode
        learnerJourneyApi={demo.learnerJourneyApi}
      />,
    )

    expect(
      await screen.findByRole('heading', {
        name: text('demo.phase1.nav.levels'),
      }),
    ).toBeInTheDocument()
    expect(
      screen.getByText(
        resolveMessage('demo.phase1.levels.pre_level_title', {
          policy: 'development-preview',
          requestedLocale: DEFAULT_LOCALE,
          parameters: { number: 1 },
        }).text,
      ),
    ).toBeInTheDocument()
    expect(
      screen.getByText(text('demo.phase1.rooms.content.title')),
    ).toBeInTheDocument()
  })

  it('opens My Levels from Learn and Speak practice from the practice hash', async () => {
    window.history.replaceState(null, '', '/#learn')
    const demo = createLocalDemoRuntime()

    render(
      <App
        authentication={demo.authentication}
        conversationApi={demo.conversationApi}
        demoMode
        learnerJourneyApi={demo.learnerJourneyApi}
      />,
    )

    expect(
      await screen.findByRole('heading', {
        name: text('demo.phase1.nav.levels'),
      }),
    ).toBeVisible()
    expect(
      screen.getByText(text('demo.phase1.rooms.audio.title')),
    ).toBeVisible()
    expect(
      screen.queryByRole('heading', { name: '16 speaking lessons' }),
    ).not.toBeInTheDocument()
    expect(
      screen.queryByRole('button', { name: 'Start mission' }),
    ).not.toBeInTheDocument()
    expect(screen.queryByText('Introduce yourself')).not.toBeInTheDocument()

    window.history.replaceState(null, '', '/#practice')
    globalThis.dispatchEvent(new Event('hashchange'))

    expect(
      await screen.findByRole('heading', {
        level: 1,
        name: text('demo.page.practice.title'),
      }),
    ).toBeVisible()
  })

  it('renders Kannada first with semantic landmarks and inert destinations', async () => {
    const authentication = anonymousAuthentication()

    render(<App authentication={authentication} />)

    expect(
      screen.getByRole('banner').querySelector('[lang="en-IN"]'),
    ).toHaveTextContent(text('app.name', 'en-IN'))
    expect(
      screen.queryByRole('navigation', {
        name: text('shell.navigation_label'),
      }),
    ).not.toBeInTheDocument()
    expect(
      screen.getByRole('heading', {
        level: 2,
        name: text('marketing.section.how'),
      }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('heading', {
        level: 3,
        name: text('marketing.path.1.title'),
      }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('heading', {
        level: 2,
        name: text('marketing.section.pedagogy'),
      }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('heading', {
        level: 3,
        name: text('marketing.benefit.hybrid.title'),
      }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('heading', {
        level: 3,
        name: text('marketing.benefit.tuition.title'),
      }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('link', { name: text('legal.privacy') }),
    ).toHaveAttribute('href', '/privacy.html')
    expect(
      screen.getByRole('link', { name: text('legal.account_deletion') }),
    ).toHaveAttribute('href', '/account-deletion.html')
    expect(screen.queryByLabelText(text('auth.password.username'))).toBeNull()
    expect(
      screen.getByRole('heading', {
        level: 1,
        name: text('shell.splash.tagline'),
      }),
    ).toBeInTheDocument()
    expect(
      await screen.findByRole('button', { name: text('session.sign_in') }),
    ).toBeInTheDocument()
    expect(
      screen.queryByText(text('shell.checkpoint_notice')),
    ).not.toBeInTheDocument()
    expect(
      screen.queryByRole('heading', { name: text('session.label') }),
    ).not.toBeInTheDocument()
    expect(
      screen.queryByText(text('shell.eyebrow')),
    ).not.toBeInTheDocument()
    expect(
      await screen.findByText(text('session.anonymous')),
    ).toHaveAttribute('lang', DEFAULT_LOCALE)
    expect(document.documentElement).toHaveAttribute(
      'lang',
      DEFAULT_LOCALE,
    )
    expect(authentication.current).toHaveBeenCalledOnce()
  })

  it('changes locale only in memory and focuses the selected destination', async () => {
    const authentication = anonymousAuthentication()
    const localStorageWrite = vi.spyOn(Storage.prototype, 'setItem')
    const indexedDbOpen =
      globalThis.indexedDB === undefined
        ? null
        : vi.spyOn(globalThis.indexedDB, 'open')
    const user = userEvent.setup()

    render(<App authentication={authentication} />)

    await user.selectOptions(
      screen.getByRole('combobox', {
        name: text('shell.locale_label'),
      }),
      'en-IN',
    )

    expect(document.documentElement).toHaveAttribute('lang', 'en-IN')
    expect(
      screen.getByRole('heading', {
        level: 1,
        name: text('shell.splash.tagline', 'en-IN'),
      }),
    ).toBeInTheDocument()
    expect(
      screen.queryByRole('navigation', {
        name: text('shell.navigation_label', 'en-IN'),
      }),
    ).not.toBeInTheDocument()
    expect(localStorageWrite).not.toHaveBeenCalled()
    expect(indexedDbOpen?.mock.calls ?? []).toHaveLength(0)
  })

  it('offers every approved Phase 1 preview locale with correct language metadata', async () => {
    const user = userEvent.setup()

    render(<App authentication={anonymousAuthentication()} />)

    const localeSelect = screen.getByRole('combobox')

    expect(
      [...localeSelect.querySelectorAll('option')].map(
        ({ value }) => value,
      ),
    ).toEqual(SUPPORTED_LOCALES)

    for (const locale of ['ml-IN', 'ta-IN', 'te-IN', 'hi-IN'] as const) {
      await user.selectOptions(localeSelect, locale)

      expect(document.documentElement).toHaveAttribute('lang', locale)
      expect(
        screen
          .getByRole('heading', {
            level: 1,
            name: text('shell.splash.tagline', locale),
          })
          .querySelector(`[lang="${locale}"]`),
      ).toBeInTheDocument()
      expect(
        screen.getByText(text('localization.preview_notice', locale)),
      ).toHaveAttribute('lang', locale)
    }
  })

  it('falls back visibly to English under the production policy', async () => {
    const consoleWarning = vi
      .spyOn(console, 'warn')
      .mockImplementation(() => undefined)
    const user = userEvent.setup()

    render(
      <App
        authentication={anonymousAuthentication()}
        localizationPolicy="approved-only"
      />,
    )

    expect(
      screen.getByRole('complementary', {
        name: text(
          'localization.fallback_label',
          DEFAULT_LOCALE,
          'approved-only',
        ),
      }),
    ).toHaveTextContent(
      text(
        'localization.fallback_notice',
        DEFAULT_LOCALE,
        'approved-only',
      ),
    )
    expect(
      screen.getByRole('heading', {
        level: 1,
        name: text(
          'shell.splash.tagline',
          DEFAULT_LOCALE,
          'approved-only',
        ),
      }),
    ).toBeInTheDocument()
    expect(
      screen.queryByText(text('localization.preview_notice')),
    ).not.toBeInTheDocument()
    expect(
      screen.getByRole('option', {
        name: text(
          'locale.kn_IN',
          DEFAULT_LOCALE,
          'approved-only',
        ),
      }),
    ).toHaveAttribute('lang', 'kn-IN')
    expect(
      await screen.findByText(
        text(
          'session.anonymous',
          DEFAULT_LOCALE,
          'approved-only',
        ),
      ),
    ).toHaveAttribute('lang', 'en-IN')
    expect(document.documentElement).toHaveAttribute('lang', 'en-IN')
    expect(document.documentElement).toHaveAttribute(
      'data-requested-locale',
      DEFAULT_LOCALE,
    )

    const localeSelect = screen.getByRole('combobox', {
      name: text(
        'shell.locale_label',
        DEFAULT_LOCALE,
        'approved-only',
      ),
    })
    await user.selectOptions(localeSelect, 'en-IN')
    await user.selectOptions(localeSelect, DEFAULT_LOCALE)

    expect(
      screen.getByText(
        text(
          'shell.locale_changed',
          DEFAULT_LOCALE,
          'approved-only',
        ),
      ),
    ).toHaveAttribute('lang', 'en-IN')
    await waitFor(() => expect(consoleWarning).toHaveBeenCalled())
    expect(JSON.stringify(consoleWarning.mock.calls)).not.toContain(
      'Your learning home is ready.',
    )
  })

  it('keeps a tenant-provided organization name language-neutral', async () => {
    const organizationName = 'ಹಿತೈಷಿ Learning Hub'
    const authentication: AuthenticationAdapter = {
      transport: 'sanctum-cookie',
      current: vi.fn(
        async (): Promise<AuthenticationSnapshot> => ({
          status: 'authenticated',
          identity: {
            current_membership: {
              id: '01J00000000000000000000002',
              organization: {
                id: '01J00000000000000000000003',
                kind: 'institution',
                name: organizationName,
              },
              membership_type: 'learner',
              role_keys: ['learner'],
              status: 'active',
            },
            memberships: [],
            user: {
              id: '01J00000000000000000000001',
            },
          },
        }),
      ),
      logout: vi.fn(async () => undefined),
    }

    render(<App authentication={authentication} />)

    const organization = await screen.findByText(organizationName)

    expect(organization.tagName).toBe('BDI')
    expect(organization).toHaveAttribute('lang', '')
    expect(screen.getByText(text('session.authenticated'))).toHaveAttribute(
      'lang',
      DEFAULT_LOCALE,
    )
  })

  it('opens Learn for a learner membership without inferring role keys', async () => {
    window.history.replaceState(null, '', '/#learn')
    const home = vi.fn(async () => ({
      enrollment: null,
      next_mission: null,
      onboarding: {
        adult_confirmed: false,
        completed: false,
        goal_key: null,
      },
    }) as const)

    render(
      <App
        authentication={membershipAuthentication(
          'learner',
          ['institution-student'],
        )}
        learnerJourneyApi={learnerApi(home)}
      />,
    )

    expect(
      await screen.findByRole('heading', {
        name: text('learner.onboarding.adult_title'),
      }),
    ).toBeInTheDocument()
    expect(home).toHaveBeenCalled()
  })

  it('does not open Learn for a non-learner membership with a learner role key', async () => {
    window.history.replaceState(null, '', '/#learn')
    const home = vi.fn(async () => {
      throw new Error('Learner API must not be called.')
    })

    render(
      <App
        authentication={membershipAuthentication(
          'trainer',
          ['learner'],
        )}
        learnerJourneyApi={learnerApi(home)}
      />,
    )

    expect(
      await screen.findByText(text('session.authenticated')),
    ).toBeInTheDocument()
    expect(
      screen.getByText(text('shell.checkpoint_notice')),
    ).toBeInTheDocument()
    expect(home).not.toHaveBeenCalled()
  })

  it('localizes a privacy-safe authentication failure', async () => {
    const authentication: AuthenticationAdapter = {
      transport: 'sanctum-cookie',
      current: vi.fn(async () => {
        throw new Error('sensitive provider detail')
      }),
      logout: vi.fn(async () => undefined),
    }

    render(<App authentication={authentication} />)

    expect(
      await screen.findByRole('alert'),
    ).toHaveTextContent(text('session.unavailable'))
    expect(
      screen.getByRole('button', { name: text('session.retry') }),
    ).toBeInTheDocument()
    expect(screen.queryByText('sensitive provider detail')).not.toBeInTheDocument()
    expect(
      screen.queryByRole('navigation', {
        name: text('shell.navigation_label'),
      }),
    ).not.toBeInTheDocument()
  })

  it('starts Android PKCE authorization from the splash Sign in action', async () => {
    const beginAuthorization = vi.fn(async () => undefined)
    const authentication: AndroidPkceAuthentication = {
      transport: 'passport-pkce',
      authenticatedFetch: vi.fn(),
      authenticationMode: vi.fn(async () => 'password_only' as const),
      beginAuthorization: vi.fn(async () => undefined),
      completeAuthorization: vi.fn(
        async (): Promise<AuthenticationSnapshot> => ({
          status: 'anonymous',
        }),
      ),
      current: vi.fn(
        async (): Promise<AuthenticationSnapshot> => ({
          status: 'authenticated',
          identity: {
            current_membership: null,
            memberships: [],
            user: { id: '01J00000000000000000000001' },
          },
        }),
      ),
      enrollWithInvitation: vi.fn(async () => undefined),
    federatedProviders: vi.fn(async () => ({
      google: false,
      facebook: false,
    })),
    beginFederatedSignIn: vi.fn(async () => undefined),
    federatedLinks: vi.fn(async () => ({ google: false, facebook: false })),
    beginFederatedLink: vi.fn(async () => undefined),
    unlinkFederated: vi.fn(async () => undefined),
      logout: vi.fn(async () => undefined),
      onAuthenticationInvalidated: vi.fn((listener) => {
        invalidate = listener

        return () => undefined
      }),
      refresh: vi.fn(async () => undefined),
      requestPasswordRecovery: vi.fn(async () => undefined),
      signIn: vi.fn(async () => undefined),
    }
    const user = userEvent.setup()

    render(<App authentication={authentication} />)

    act(() => invalidate())

    expect(
      await screen.findByRole('link', {
        name: text('session.label'),
      }),
    ).toHaveAttribute('href', '#sign-in')
    expect(
      screen.queryByRole('button', {
        name: text('session.android_sign_in'),
      }),
    ).not.toBeInTheDocument()
  })

  it('opens the in-app Android password form from the header Sign in link', async () => {
    const user = userEvent.setup()
    const beginAuthorization = vi.fn(async () => undefined)
    const authentication: AndroidPkceAuthentication = {
      transport: 'passport-pkce',
      authenticatedFetch: vi.fn(),
      authenticationMode: vi.fn(async () => 'password_only' as const),
      beginAuthorization,
      completeAuthorization: vi.fn(),
      current: vi.fn(
        async (): Promise<AuthenticationSnapshot> => ({
          status: 'anonymous',
        }),
      ),
      enrollWithInvitation: vi.fn(async () => undefined),
    federatedProviders: vi.fn(async () => ({
      google: false,
      facebook: false,
    })),
    beginFederatedSignIn: vi.fn(async () => undefined),
    federatedLinks: vi.fn(async () => ({ google: false, facebook: false })),
    beginFederatedLink: vi.fn(async () => undefined),
    unlinkFederated: vi.fn(async () => undefined),
      logout: vi.fn(async () => undefined),
      onAuthenticationInvalidated: vi.fn(() => () => undefined),
      refresh: vi.fn(async () => undefined),
      requestPasswordRecovery: vi.fn(async () => undefined),
      signIn: vi.fn(async () => undefined),
    }

    render(<App authentication={anonymousAuthentication()} />)

    const signIn = await screen.findByRole('link', {
      name: text('session.label'),
    })
    expect(signIn.closest('header')).toBeInTheDocument()
    expect(signIn).toHaveAttribute('href', '#sign-in')
    expect(
      screen.queryByRole('button', {
        name: text('session.android_sign_in'),
      }),
    ).not.toBeInTheDocument()

    expect(details).not.toHaveAttribute('open')
    await user.click(signIn)

    expect(
      await screen.findByLabelText(text('auth.password.username')),
    ).toBeInTheDocument()
    expect(beginAuthorization).not.toHaveBeenCalled()
  })
})

function anonymousAuthentication(): AuthenticationAdapter {
  return {
    transport: 'sanctum-cookie',
    current: vi.fn(
      async (): Promise<AuthenticationSnapshot> => ({
        status: 'anonymous',
      }),
    ),
    logout: vi.fn(async () => undefined),
  }
}

function membershipAuthentication(
  membershipType: 'learner' | 'trainer' | 'organization_admin',
  roleKeys: string[],
): AuthenticationAdapter {
  return {
    transport: 'sanctum-cookie',
    current: vi.fn(
      async (): Promise<AuthenticationSnapshot> => ({
        status: 'authenticated',
        identity: {
          current_membership: {
            id: '01J00000000000000000000002',
            membership_type: membershipType,
            organization: {
              id: '01J00000000000000000000003',
              kind: 'institution',
              name: 'Hithaishi Learning Hub',
            },
            role_keys: roleKeys,
            status: 'active',
          },
          memberships: [],
          user: { id: '01J00000000000000000000001' },
        },
      }),
    ),
    logout: vi.fn(async () => undefined),
  }
}

function webMembershipAuthentication(): WebSessionAuthentication {
  return {
    transport: 'sanctum-cookie',
    authenticationMode: vi.fn(async () => 'password_only' as const),
    beginPrivilegedMfaEnrollment: vi.fn(),
    changePassword: vi.fn(async () => undefined),
    changeUsername: vi.fn(async () => undefined),
    confirmPassword: vi.fn(async () => undefined),
    confirmPrivilegedMfaEnrollment: vi.fn(),
    createPrivilegedMfaChallenge: vi.fn(),
    current: vi.fn(
      async (): Promise<AuthenticationSnapshot> => ({
        status: 'authenticated',
        identity: {
          current_membership: {
            id: '01J00000000000000000000002',
            membership_type: 'organization_admin',
            organization: {
              id: '01J00000000000000000000003',
              kind: 'institution',
              name: 'Hithaishi Learning Hub',
            },
            role_keys: ['organization-admin'],
            status: 'active',
          },
          memberships: [],
          user: { id: '01J00000000000000000000001' },
        },
      }),
    ),
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
    passwordStatus: vi.fn(async () => ({
      credential_ready: true,
      mode: 'password_only' as const,
      password_confirmed_at: null,
      username: 'operator.one',
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
        id: '01J00000000000000000000004',
        last_activity_at: '2026-08-11T10:00:00Z',
        mfa_verified_at: null,
        mfa_verified_method: null,
        mfa_verified_scope: null,
        password_confirmed_at: null,
        privileged: true,
      },
    ]),
    signIn: vi.fn(async () => undefined),
    verifyOtp: vi.fn(async () => undefined),
    verifyPrivilegedMfaChallenge: vi.fn(),
  }
}

function operatorApi(
  getOverview: OperatorConsoleApi['getOverview'],
): OperatorConsoleApi {
  return {
    getOverview,
    getPilotAnalytics: vi.fn(async () => ({
      demonstrated_learning: {
        mastery_by_knowledge_state: {},
        mastery_state_count: 0,
        review_schedule_count: 0,
      },
      distinction_note:
        'usage_counts_are_not_demonstrated_learning' as const,
      generated_at: '2026-08-09T10:00:00+00:00',
      usage: {
        conversation_session_count: 0,
        conversation_sessions_by_status: {},
        formative_attempt_count: 0,
        formative_attempts_by_status: {},
        learner_membership_count: 0,
      },
    })),
    listAuditEvents: vi.fn(async () => ({
      items: [],
      next_cursor: null,
    })),
    listDrafts: vi.fn(async () => []),
    listPublications: vi.fn(async () => []),
    listSupportDiagnostics: vi.fn(async () => ({
      generated_at: '2026-08-09T10:00:00+00:00',
      items: [],
    })),
    search: vi.fn(async () => ({
      generated_at: '2026-08-09T10:00:00+00:00',
      items: [],
      next_cursor: null,
    })),
  }
}

function learnerApi(
  home: LearnerJourneyApi['home'],
): LearnerJourneyApi {
  const unreachable = async (): Promise<never> => {
    throw new Error('Unexpected learner journey call.')
  }

  return {
    home,
    mission: vi.fn(unreachable),
    progress: vi.fn(unreachable),
    progressDashboard: vi.fn(unreachable),
    recordResponse: vi.fn(unreachable),
    startAttempt: vi.fn(unreachable),
    submitAttempt: vi.fn(unreachable),
    updateOnboarding: vi.fn(unreachable),
  }
}

function text(
  key: MessageKey,
  locale: SupportedLocale = DEFAULT_LOCALE,
  policy: ResolutionPolicy = 'development-preview',
): string {
  return resolveMessage(key, {
    policy,
    requestedLocale: locale,
  }).text
}
