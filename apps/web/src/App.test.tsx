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
import { afterEach, describe, expect, it, vi } from 'vitest'
import App from './App'
import type {
  AuthenticationAdapter,
  AuthenticationSnapshot,
} from './auth/AuthenticationAdapter'
import type { LearnerJourneyApi } from './learner/LearnerJourneyApi'
import { createLocalDemoRuntime } from './demo/localDemo'

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
      screen.getByRole('link', { name: text('demo.nav.speak') }),
    ).toHaveAttribute('href', '#speak')
    expect(
      screen.getByRole('link', { name: text('nav.learn') }),
    ).toHaveAttribute('href', '#learn')
    expect(
      screen.getByRole('link', { name: text('nav.progress') }),
    ).toHaveAttribute('href', '#progress')
    expect(
      screen.getByText('Global English Local Demo'),
    ).toBeInTheDocument()
  })

  it('opens Speak with Swathi from the demo primary navigation', async () => {
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
      screen.getByRole('link', { name: text('demo.nav.speak') }),
    )

    expect(
      await screen.findByRole('heading', {
        level: 1,
        name: text('demo.nav.speak'),
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

  it('opens My Levels rooms from the demo Learn tab', async () => {
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

    await user.click(screen.getByRole('link', { name: text('nav.learn') }))

    expect(
      await screen.findByRole('heading', {
        name: text('demo.phase1.nav.levels'),
      }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('button', {
        name: text('demo.phase1.rooms.audio.title'),
      }),
    ).toBeInTheDocument()
  })

  it('renders Kannada first with semantic landmarks and inert destinations', async () => {
    const authentication = anonymousAuthentication()

    render(<App authentication={authentication} />)

    expect(
      screen.getByRole('banner').querySelector('[lang="en-IN"]'),
    ).toHaveTextContent(text('app.name', 'en-IN'))
    expect(
      screen.getByRole('navigation', {
        name: text('shell.navigation_label'),
      }),
    ).toHaveAttribute('lang', DEFAULT_LOCALE)
    expect(
      screen.getByRole('link', { name: text('nav.home') }),
    ).toHaveAttribute('aria-current', 'page')
    expect(
      screen.getByRole('heading', {
        level: 1,
        name: text('page.home.title'),
      }),
    ).toBeInTheDocument()
    expect(
      screen.getByText(text('shell.checkpoint_notice')),
    ).toBeInTheDocument()
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
      screen.getByRole('navigation', {
        name: text('shell.navigation_label', 'en-IN'),
      }),
    ).toHaveAttribute('lang', 'en-IN')

    await user.click(
      screen.getByRole('link', {
        name: text('nav.practice', 'en-IN'),
      }),
    )

    const practiceTitle = await screen.findByRole('heading', {
      level: 1,
      name: text('page.practice.title', 'en-IN'),
    })

    await waitFor(() => expect(practiceTitle).toHaveFocus())
    expect(
      screen.getByRole('link', {
        name: text('nav.practice', 'en-IN'),
      }),
    ).toHaveAttribute('aria-current', 'page')
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
            name: text('page.home.title', locale),
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
          'page.home.title',
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
    const home = vi.fn(async () => {
      throw new Error('Daily mission API must not replace Audio Lessons.')
    })

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
        name: text('demo.phase1.nav.levels'),
      }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('button', {
        name: text('demo.phase1.rooms.audio.title'),
      }),
    ).toBeInTheDocument()
    expect(home).not.toHaveBeenCalled()
  })

  it('keeps the guided daily lesson on #mission for a signed-in learner', async () => {
    window.history.replaceState(null, '', '/#mission')
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
      await screen.findByText(text('session.unavailable')),
    ).toBeInTheDocument()
    expect(screen.queryByText('sensitive provider detail')).not.toBeInTheDocument()
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
