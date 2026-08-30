import {
  DEFAULT_LOCALE,
  INTERFACE_LOCALES,
  resolveMessage,
  type MessageKey,
  type ResolutionPolicy,
  type SupportedLocale,
} from '@global-english/localization'
import { act, cleanup, render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import App from './App'
import type {
  AndroidPkceAuthentication,
  AuthenticationAdapter,
  AuthenticationSnapshot,
  WebSessionAuthentication,
} from './auth/AuthenticationAdapter'
import type { LearnerJourneyApi } from './learner/LearnerJourneyApi'
import { createLocalDemoRuntime } from './demo/localDemo'
import type { OperatorConsoleApi } from './operator/OperatorConsoleApi'

afterEach(() => {
  cleanup()
  window.history.replaceState(null, '', '/')
  document.documentElement.lang = DEFAULT_LOCALE
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
      screen.getByRole('complementary', {
        name: text('demo.label'),
      }),
    ).toHaveAttribute('data-demo-mode', 'synthetic-local')
    expect(
      await screen.findByRole('heading', {
        name: text('demo.phase1.nav.dashboard'),
      }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('navigation', {
        name: text('demo.phase1.nav_label'),
      }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('navigation', {
        name: text('shell.navigation_label'),
      }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('link', { name: text('nav.speak') }),
    ).toHaveAttribute('href', '#speak')
    expect(
      screen.getByRole('link', { name: text('nav.learn') }),
    ).toHaveAttribute('href', '#learn')
    expect(
      screen.getByRole('link', { name: text('nav.tuition') }),
    ).toHaveAttribute('href', '#tuition')
    expect(
      screen.getByRole('link', { name: text('nav.progress') }),
    ).toHaveAttribute('href', '#progress')
    expect(
      screen.getByText('Global English Local Demo'),
    ).toBeInTheDocument()
  })

  it('opens the four-session level path from the demo Learn destination', async () => {
    const user = userEvent.setup()
    const demo = createLocalDemoRuntime()

    render(
      <App
        authentication={demo.authentication}
        conversationApi={demo.conversationApi}
        demoMode
        learnerJourneyApi={demo.learnerJourneyApi}
        levelSessionsApi={demo.levelSessionsApi}
      />,
    )

    await user.click(
      screen.getByRole('link', { name: text('nav.learn') }),
    )

    expect(
      await screen.findByRole('heading', {
        level: 1,
        name: text('demo.page.learn.title'),
      }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('heading', {
        level: 2,
        name: text('level.path.title'),
      }),
    ).toBeInTheDocument()
    expect(
      await screen.findByRole('button', {
        name: /ಮಟ್ಟ 1|Level 1/i,
      }),
    ).toBeInTheDocument()
  })

  it('opens the Speak room from the demo primary navigation', async () => {
    const user = userEvent.setup()
    const demo = createLocalDemoRuntime()

    render(
      <App
        authentication={demo.authentication}
        conversationApi={demo.conversationApi}
        demoMode
        learnerJourneyApi={demo.learnerJourneyApi}
        levelSessionsApi={demo.levelSessionsApi}
      />,
    )

    await user.click(
      screen.getByRole('link', { name: text('nav.speak') }),
    )

    expect(
      await screen.findByRole('heading', {
        level: 1,
        name: text('demo.page.speak.title'),
      }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('tab', { name: text('speak.mode.everyday') }),
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

  it('renders Kannada first with semantic landmarks and inert destinations', async () => {
    const authentication = anonymousAuthentication()

    render(<App authentication={authentication} />)

    expect(
      await screen.findByRole('heading', {
        level: 1,
        name: text('marketing.hero.title'),
      }),
    ).toBeInTheDocument()

    expect(screen.getByText(text('app.name'))).toBeInTheDocument()
    expect(
      screen.getByRole('link', { name: text('session.label') }),
    ).toHaveAttribute('href', '#sign-in')
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
    expect(screen.queryByLabelText(text('auth.password.username'))).toBeNull()
    expect(
      screen.getByRole('heading', {
        level: 1,
        name: text('marketing.hero.title'),
      }),
    ).toHaveTextContent(text('marketing.hero.title'))
    expect(
      screen
        .getByRole('heading', {
          level: 1,
          name: text('marketing.hero.title'),
        })
        .closest(`[lang="${DEFAULT_LOCALE}"]`),
    ).toBeInTheDocument()
    expect(document.documentElement).toHaveAttribute(
      'lang',
      DEFAULT_LOCALE,
    )
    expect(authentication.current).toHaveBeenCalledOnce()
  })

  it('lets a guest close the dedicated sign-in page back to the marketing home', async () => {
    window.history.replaceState(null, '', '/#sign-in')
    const user = userEvent.setup()
    const authentication = guestWebAuthentication()

    render(<App authentication={authentication} />)

    expect(
      await screen.findByLabelText(text('auth.password.username')),
    ).toBeInTheDocument()
    const close = screen
      .getAllByRole('link', { name: text('nav.home') })
      .find((link) => link.classList.contains('shell-sign-in-page__close'))
    expect(close).toBeDefined()
    expect(close).toHaveAttribute('href', '#home')

    await user.click(close!)

    expect(
      await screen.findByRole('heading', {
        level: 1,
        name: text('marketing.hero.title'),
      }),
    ).toBeInTheDocument()
    expect(screen.queryByLabelText(text('auth.password.username'))).toBeNull()
    expect(
      screen.getByRole('link', { name: text('session.label') }),
    ).toHaveAttribute('href', '#sign-in')
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
        name: text('marketing.hero.title', 'en-IN'),
      }),
    ).toBeInTheDocument()

    await user.click(
      screen.getByRole('link', {
        name: text('session.label', 'en-IN'),
      }),
    )

    expect(
      await screen.findByRole('heading', {
        level: 1,
        name: text('session.label', 'en-IN'),
      }),
    ).toBeInTheDocument()
    expect(localStorageWrite).not.toHaveBeenCalled()
    expect(indexedDbOpen?.mock.calls ?? []).toHaveLength(0)
  })

  it('offers Kannada and English interface locales with correct language metadata', async () => {
    const user = userEvent.setup()

    render(<App authentication={anonymousAuthentication()} />)

    const localeSelect = screen.getByRole('combobox')

    expect(
      [...localeSelect.querySelectorAll('option')].map(
        ({ value }) => value,
      ),
    ).toEqual([...INTERFACE_LOCALES])

    await user.selectOptions(localeSelect, 'en-IN')
    expect(document.documentElement).toHaveAttribute('lang', 'en-IN')
    expect(
      screen.getByRole('heading', {
        level: 1,
        name: text('marketing.hero.title', 'en-IN'),
      }),
    ).toBeInTheDocument()

    await user.selectOptions(localeSelect, DEFAULT_LOCALE)
    expect(document.documentElement).toHaveAttribute('lang', DEFAULT_LOCALE)
    expect(
      screen.getByRole('heading', {
        level: 1,
        name: text('marketing.hero.title', DEFAULT_LOCALE),
      }),
    ).toBeInTheDocument()
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

    const heroHeading = await screen.findByRole('heading', {
      level: 1,
      name: text('marketing.hero.title', 'en-IN', 'approved-only'),
    })

    expect(heroHeading.closest('[lang="en-IN"]')).not.toBeNull()

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
      screen.queryByRole('option', {
        name: text('locale.hi_IN', DEFAULT_LOCALE, 'approved-only'),
      }),
    ).not.toBeInTheDocument()
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

  it('opens the learner journey on Home without inferring role keys', async () => {
    window.history.replaceState(null, '', '/#home')
    const home = vi.fn(async () => ({
      due_review: null,
      enrollment: null,
      next_mission: null,
      onboarding: {
        adult_confirmed: false,
        completed: false,
        goal_key: null,
      },
      streak: {
        as_of_date: '2026-08-30',
        count: 0,
        timezone: 'Asia/Kolkata',
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
        name: text('learner.onboarding.title'),
      }),
    ).toBeInTheDocument()
    expect(home).toHaveBeenCalledOnce()
  })

  it('does not open Learn for a non-learner membership with a learner role key', async () => {
    window.history.replaceState(null, '', '/#learn')
    const home = vi.fn(async () => {
      throw new Error('Learner API must not be called.')
    })
    const getOverview = vi.fn(async () => ({
      generated_at: '2026-08-09T10:00:00+00:00',
      navigation: ['overview'] as const,
      organization: {
        id: '01J00000000000000000000003',
        name: 'Hithaishi Learning Hub',
        timezone: 'Asia/Kolkata',
      },
      panels: [] as const,
      search_available: false,
    }))

    render(
      <App
        authentication={membershipAuthentication(
          'trainer',
          ['learner'],
        )}
        learnerJourneyApi={learnerApi(home)}
        operatorConsoleApi={operatorApi(getOverview)}
      />,
    )

    expect(
      await screen.findByRole('heading', {
        name: text('operator.shell.title'),
      }),
    ).toBeInTheDocument()
    await waitFor(() => expect(getOverview).toHaveBeenCalledOnce())
    expect(home).not.toHaveBeenCalled()
  })

  it('keeps authenticated account security collapsed and offers sign out', async () => {
    const user = userEvent.setup()
    const authentication = webMembershipAuthentication()
    const getOverview = vi.fn(async () => ({
      generated_at: '2026-08-11T10:00:00+00:00',
      navigation: ['overview'] as const,
      organization: {
        id: '01J00000000000000000000003',
        name: 'Hithaishi Learning Hub',
        timezone: 'Asia/Kolkata',
      },
      panels: [] as const,
      search_available: false,
    }))

    render(
      <App
        authentication={authentication}
        operatorConsoleApi={operatorApi(getOverview)}
      />,
    )

    expect(
      await screen.findByRole('heading', {
        name: text('operator.shell.title'),
      }),
    ).toBeInTheDocument()
    const account = screen.getByRole('button', {
      name: text('session.account_menu'),
    })
    expect(account).toHaveAttribute('aria-expanded', 'false')
    expect(authentication.passwordStatus).not.toHaveBeenCalled()
    expect(screen.queryByRole('region', { name: text('session.label') })).not.toBeInTheDocument()
    expect(
      screen.queryByRole('heading', {
        name: text('operator.password.title'),
      }),
    ).not.toBeInTheDocument()

    await user.click(
      screen.getByRole('button', { name: text('session.android_sign_out') }),
    )
    expect(authentication.logout).toHaveBeenCalledOnce()
    expect(
      await screen.findByRole('link', { name: text('session.label') }),
    ).toHaveAttribute('href', '#sign-in')
    expect(screen.queryByLabelText(text('auth.password.username'))).toBeNull()
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
      await screen.findByText(text('session.unavailable')),
    ).toBeInTheDocument()
    expect(screen.queryByText('sensitive provider detail')).not.toBeInTheDocument()
  })

  it('returns the Android shell to anonymous after terminal bearer revocation', async () => {
    let invalidate = () => undefined
    const authentication: AndroidPkceAuthentication = {
      transport: 'passport-pkce',
      authenticatedFetch: vi.fn(),
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
      logout: vi.fn(async () => undefined),
      onAuthenticationInvalidated: vi.fn((listener) => {
        invalidate = listener

        return () => undefined
      }),
      refresh: vi.fn(async () => undefined),
    }

    render(<App authentication={authentication} />)
    expect(
      await screen.findByText(text('session.select_membership')),
    ).toBeInTheDocument()

    act(() => invalidate())

    expect(
      await screen.findByRole('button', {
        name: text('session.android_sign_in'),
      }),
    ).toBeInTheDocument()
  })

  it('offers one plain Android sign-in action in the application header', async () => {
    const user = userEvent.setup()
    const beginAuthorization = vi.fn(async () => undefined)
    const authentication: AndroidPkceAuthentication = {
      transport: 'passport-pkce',
      authenticatedFetch: vi.fn(),
      beginAuthorization,
      completeAuthorization: vi.fn(),
      current: vi.fn(
        async (): Promise<AuthenticationSnapshot> => ({
          status: 'anonymous',
        }),
      ),
      logout: vi.fn(async () => undefined),
      onAuthenticationInvalidated: vi.fn(() => () => undefined),
      refresh: vi.fn(async () => undefined),
    }

    render(<App authentication={authentication} />)

    const signIn = await screen.findByRole('button', {
      name: text('session.android_sign_in'),
    })
    expect(signIn.closest('header')).toBeInTheDocument()
    expect(
      screen.getAllByRole('button', {
        name: text('session.android_sign_in'),
      }),
    ).toHaveLength(1)

    await user.click(signIn)

    expect(beginAuthorization).toHaveBeenCalledOnce()
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

function guestWebAuthentication(): WebSessionAuthentication {
  return {
    ...webMembershipAuthentication(),
    current: vi.fn(
      async (): Promise<AuthenticationSnapshot> => ({
        status: 'anonymous',
      }),
    ),
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
    listLevelVideos: vi.fn(async () => ({
      enrollment_id: '01J30000000000000000000004',
      videos: [],
    })),
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
