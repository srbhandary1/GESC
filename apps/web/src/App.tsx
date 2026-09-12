import {
  ApplicationFrame,
  ThemeToggle,
  useColorScheme,
  type PrimaryDestination,
} from '@global-english/design-system'
import {
  DEFAULT_LOCALE,
  SUPPORTED_LOCALES,
  type MessageKey,
  type ResolutionPolicy,
  type SupportedLocale,
} from '@global-english/localization'
import {
  useEffect,
  useMemo,
  useRef,
  useState,
  type ChangeEvent,
} from 'react'
import './App.css'
import './demo/demoExperience.css'
import './theme/themeTokens.css'
import type {
  AndroidPkceAuthentication,
  AuthenticationAdapter,
  AuthenticationSnapshot,
} from './auth/AuthenticationAdapter'
import { bindAndroidPkceDeepLink } from './auth/bindAndroidPkceDeepLink'
import { defaultAuthentication } from './auth/defaultAuthentication'
import { PreSignInSplash } from './auth/PreSignInSplash'
import { ConversationJourney } from './conversation/ConversationJourney'
import { DemoLearnerProgress } from './demo/DemoLearnerProgress'
import { curriculumByLevelId } from './demo/curriculum/levelCurriculum'
import {
  foundationLessonById,
  lessonIdFromHash,
} from './demo/foundationLessons'
import { DemoPhase1Shell } from './demo/phase1/DemoPhase1Shell'
import { PHASE1_SECONDARY_HASHES } from './demo/phase1/phase1Fixtures'
import type { ConversationJourneyApi } from './conversation/ConversationJourneyApi'
import { RoleplayJourney } from './conversation/RoleplayJourney'
import { defaultConversationJourneyApi } from './conversation/WebConversationJourneyApi'
import {
  INTERFACE_RESOLUTION_POLICY,
  resolveInterfaceMessage,
  useInterfaceFallbackReporting,
  type InterfaceMessageResolution,
} from './localization/interfaceLocalization'
import { LocalizedMessage } from './localization/LocalizedMessage'
import type { AudioRecorder } from './learner/BrowserAudioRecorder'
import type { LearnerJourneyApi } from './learner/LearnerJourneyApi'
import { defaultLearnerJourneyApi } from './learner/WebLearnerJourneyApi'
import { LearnerLevelsSurface } from './learner/LearnerLevelsSurface'
import type { LevelSessionsApi } from './learner/LevelSessionsApi'
import { defaultLevelSessionsApi } from './learner/WebLevelSessionsApi'
import { browserOfflineLearningStore } from './offline/BrowserOfflineLearningStore'
import type {
  OfflineLearningStore,
  OfflinePersonalDeviceResume,
  OfflineProfileBinding,
} from './offline/OfflineLearningStore'
import { ProgressDashboard } from './learner/ProgressDashboard'
import { OperatorConsole } from './operator/OperatorConsole'
import type { OperatorConsoleApi } from './operator/OperatorConsoleApi'
import { defaultOperatorConsoleApi } from './operator/OperatorConsoleApi'

type SessionState =
  | AuthenticationSnapshot
  | { status: 'checking' }
  | { status: 'unavailable' }

interface AppProps {
  authentication?: AuthenticationAdapter
  audioRecorder?: AudioRecorder
  conversationApi?: ConversationJourneyApi
  createClientId?: () => string
  demoMode?: boolean
  learnerJourneyApi?: LearnerJourneyApi
  levelSessionsApi?: LevelSessionsApi
  localizationPolicy?: ResolutionPolicy
  offlineStore?: OfflineLearningStore
  operatorConsoleApi?: OperatorConsoleApi
}

const OPERATOR_ROLE_KEYS = new Set([
  'trainer',
  'organization_admin',
  'content_reviewer',
])

const destinationMessages: Readonly<
  Record<
    PrimaryDestination,
    Readonly<{ description: MessageKey; title: MessageKey }>
  >
> = {
  home: {
    description: 'page.home.description',
    title: 'page.home.title',
  },
  learn: {
    description: 'page.learn.description',
    title: 'page.learn.title',
  },
  practice: {
    description: 'page.practice.description',
    title: 'page.practice.title',
  },
  progress: {
    description: 'page.progress.description',
    title: 'page.progress.title',
  },
}

const demoDestinationMessages: Readonly<
  Record<
    PrimaryDestination,
    Readonly<{ description: MessageKey; title: MessageKey }>
  >
> = {
  home: {
    description: 'demo.page.home.description',
    title: 'demo.page.home.title',
  },
  learn: {
    description: 'demo.page.learn.description',
    title: 'demo.page.learn.title',
  },
  practice: {
    description: 'demo.page.practice.description',
    title: 'demo.page.practice.title',
  },
  progress: {
    description: 'demo.page.progress.description',
    title: 'demo.page.progress.title',
  },
}

const localeMessageKeys: Readonly<Record<SupportedLocale, MessageKey>> = {
  'en-IN': 'locale.en_IN',
  'hi-IN': 'locale.hi_IN',
  'kn-IN': 'locale.kn_IN',
  'ml-IN': 'locale.ml_IN',
  'ta-IN': 'locale.ta_IN',
  'te-IN': 'locale.te_IN',
}

function App({
  authentication = defaultAuthentication,
  audioRecorder,
  conversationApi = defaultConversationJourneyApi,
  createClientId,
  demoMode = false,
  learnerJourneyApi = defaultLearnerJourneyApi,
  levelSessionsApi = defaultLevelSessionsApi,
  localizationPolicy = INTERFACE_RESOLUTION_POLICY,
  offlineStore = browserOfflineLearningStore,
  operatorConsoleApi = defaultOperatorConsoleApi,
}: AppProps) {
  const [session, setSession] = useState<SessionState>({
    status: 'checking',
  })
  const [locale, setLocale] = useState<SupportedLocale>(DEFAULT_LOCALE)
  const [offlineResume, setOfflineResume] =
    useState<OfflinePersonalDeviceResume | null>(null)
  const [announcedLocale, setAnnouncedLocale] =
    useState<SupportedLocale | null>(null)
  const [practiceSurface, setPracticeSurface] = useState<
    'confidence' | 'roleplay'
  >('confidence')
  const { scheme, toggleScheme } = useColorScheme()
  // In demo mode Home renders the learner journey directly, so every
  // runtime starts on Home unless the URL hash selects another destination.
  const [destination, setDestination] = useState<PrimaryDestination>(
    () => destinationFromHash(globalThis.location?.hash) ?? 'home',
  )
  const destinationTitle = useRef<HTMLHeadingElement>(null)
  const previousDestination = useRef(destination)
  const message = (key: MessageKey) =>
    resolveInterfaceMessage(key, locale, localizationPolicy)
  const documentLanguage = message('app.description').resolvedLocale

  useEffect(() => {
    let active = true

    authentication
      .current()
      .then((snapshot) => {
        if (active) {
          setOfflineResume(null)
          setSession(snapshot)
        }
      })
      .catch(async () => {
        const resume =
          globalThis.navigator?.onLine === false
            ? await offlineStore.resumePersonalDevice()
            : null

        if (active) {
          setOfflineResume(resume)
          setSession({ status: 'unavailable' })
        }
      })

    return () => {
      active = false
    }
  }, [authentication, offlineStore])

  useEffect(() => {
    if (authentication.transport !== 'passport-pkce') {
      return
    }

    const androidAuth = authentication as AndroidPkceAuthentication
    let remove: (() => void) | undefined
    let cancelled = false

    void bindAndroidPkceDeepLink(
      androidAuth,
      (snapshot) => {
        if (!cancelled) {
          setOfflineResume(null)
          setSession(snapshot)
        }
      },
      () => {
        if (!cancelled) {
          setSession({ status: 'unavailable' })
        }
      },
    ).then((dispose) => {
      if (cancelled) {
        dispose()
      } else {
        remove = dispose
      }
    })

    return () => {
      cancelled = true
      remove?.()
    }
  }, [authentication])

  useEffect(() => {
    const previousLocale = document.documentElement.lang
    const previousRequestedLocale =
      document.documentElement.dataset.requestedLocale
    document.documentElement.lang = documentLanguage
    document.documentElement.dataset.requestedLocale = locale

    return () => {
      document.documentElement.lang = previousLocale
      if (previousRequestedLocale === undefined) {
        delete document.documentElement.dataset.requestedLocale
      } else {
        document.documentElement.dataset.requestedLocale =
          previousRequestedLocale
      }
    }
  }, [documentLanguage, locale])

  useEffect(() => {
    const updateDestination = () => {
      const nextDestination = destinationFromHash(globalThis.location?.hash)

      if (nextDestination !== null) {
        setDestination(nextDestination)
      }
    }

    globalThis.addEventListener('hashchange', updateDestination)

    return () => {
      globalThis.removeEventListener('hashchange', updateDestination)
    }
  }, [])

  useEffect(() => {
    if (previousDestination.current !== destination) {
      const titleNode =
        destinationTitle.current ??
        document.getElementById('destination-title')
      titleNode?.focus({ preventScroll: true })
      globalThis.scrollTo(0, 0)
      previousDestination.current = destination
    }
  }, [destination])

  const brand = resolveInterfaceMessage(
    'app.name',
    'en-IN',
    localizationPolicy,
  )
  const compactBrand = resolveInterfaceMessage(
    'app.short_name',
    'en-IN',
    localizationPolicy,
  )
  const navigationLabel = message('shell.navigation_label')
  const skipToContent = message('shell.skip_to_content')
  const fallbackLabel = message('localization.fallback_label')
  const fallbackNotice = message('localization.fallback_notice')
  const previewLabel = message('localization.preview_label')
  const previewNotice = message('localization.preview_notice')
  const routeMessages = (
    demoMode ? demoDestinationMessages : destinationMessages
  )[destination]
  const title = message(routeMessages.title)
  const description = message(routeMessages.description)
  const shellEyebrow = message(
    demoMode ? 'demo.shell.eyebrow' : 'shell.eyebrow',
  )
  const demoOrganization =
    demoMode && session.status === 'authenticated'
      ? session.identity.current_membership?.organization.name
      : undefined
  const navigationLinks = {
    home: {
      href: '#home',
      label: <LocalizedMessage resolution={message('nav.home')} />,
    },
    learn: {
      href: '#learn',
      label: <LocalizedMessage resolution={message('nav.learn')} />,
    },
    practice: {
      href: '#practice',
      label: <LocalizedMessage resolution={message('nav.speak')} />,
    },
    progress: {
      href: '#progress',
      label: <LocalizedMessage resolution={message('nav.progress')} />,
    },
  } as const
  const isFallbackActive =
    navigationLabel.usedFallback ||
    title.usedFallback ||
    description.usedFallback
  const authenticatedOfflineBinding = useMemo<OfflineProfileBinding | null>(
    () => {
      if (session.status !== 'authenticated') {
        return null
      }

      const membership = session.identity.current_membership

      return membership === null
        ? null
        : {
            membershipId: membership.id,
            organizationId: membership.organization.id,
            userId: session.identity.user.id,
          }
    },
    [session],
  )
  const offlineBinding =
    authenticatedOfflineBinding ?? offlineResume?.binding ?? null
  const isLearnerSession =
    session.status === 'authenticated' &&
    session.identity.current_membership?.membership_type === 'learner'
  const isOperatorSession =
    !demoMode &&
    session.status === 'authenticated' &&
    (session.identity.current_membership?.role_keys.some((roleKey) =>
      OPERATOR_ROLE_KEYS.has(roleKey),
    ) ??
      false)
  const canLearn =
    destination === 'learn' &&
    (demoMode || isLearnerSession || offlineResume !== null)
  const canPractice =
    destination === 'practice' && (isLearnerSession || demoMode)
  const canShowDemoProgress =
    demoMode && destination === 'progress' && isLearnerSession
  const canShowLevelProgress =
    !demoMode && destination === 'progress' && isLearnerSession
  const canShowOperatorConsole =
    isOperatorSession &&
    (destination === 'home' || destination === 'learn')
  const showDemoPhase1 = demoMode && destination === 'home'
  const showDemoLearnHub = demoMode && canLearn
  const hideDemoIntroduction = demoMode
  const showPreSignInGate =
    !demoMode &&
    session.status !== 'authenticated' &&
    offlineResume === null
  const androidAuth =
    authentication.transport === 'passport-pkce'
      ? (authentication as AndroidPkceAuthentication)
      : null
  const practiceHashId = (globalThis.location?.hash ?? '')
    .replace(/^#/u, '')
    .replace(/^(?:learn|practice)\//u, '')
  const practiceLesson = foundationLessonById(
    lessonIdFromHash(globalThis.location?.hash),
  )
  const practiceCurriculum = curriculumByLevelId(practiceHashId)
  const practiceListenLines =
    practiceCurriculum?.fallbackTurns.length
      ? practiceCurriculum.fallbackTurns
      : practiceLesson === null
        ? undefined
        : [practiceLesson.modelEn]

  useInterfaceFallbackReporting([navigationLabel])

  const handleLocaleChange = (event: ChangeEvent<HTMLSelectElement>) => {
    const selectedLocale = supportedLocale(event.currentTarget.value)

    if (selectedLocale === null) {
      return
    }

    setLocale(selectedLocale)
    setAnnouncedLocale(selectedLocale)
  }

  return (
    <div
      className={demoMode ? 'demo-experience' : undefined}
      data-demo-mode={demoMode ? 'synthetic-local' : undefined}
    >
    <ApplicationFrame
      brandLabel={brand.text}
      brandLabelLocale={brand.resolvedLocale}
      compactBrandLabel={compactBrand.text}
      currentDestination={destination}
      hidePrimaryNavigation={showPreSignInGate}
      headerActions={
        <div className="shell-header-tools">
          {demoMode ? (
            <a className="shell-profile-link" href="#profile">
              <LocalizedMessage resolution={message('shell.profile_label')} />
            </a>
          ) : null}
          <ThemeToggle
            darkLabel={message('shell.theme.dark').text}
            label={message('shell.theme.label').text}
            lightLabel={message('shell.theme.light').text}
            onToggle={toggleScheme}
            scheme={scheme}
          />
          <div className="shell-locale-control">
          <label
            className="shell-locale-control__label"
            htmlFor="interface-locale"
          >
            <LocalizedMessage resolution={message('shell.locale_label')} />
          </label>
          <select
            className="shell-locale-control__select"
            id="interface-locale"
            value={locale}
            onChange={handleLocaleChange}
          >
            {SUPPORTED_LOCALES.map((supported) => {
              // Language names are endonyms in every catalogue. Tag each
              // option with that language so assistive tech and fonts match.
              const option = resolveInterfaceMessage(
                localeMessageKeys[supported],
                locale,
                localizationPolicy,
              )

              return (
                <option key={supported} lang={supported} value={supported}>
                  {option.text}
                </option>
              )
            })}
          </select>
          <p
            className="visually-hidden"
            aria-atomic="true"
            aria-live="polite"
          >
            {announcedLocale === null ? null : (
              <LocalizedMessage
                resolution={resolveInterfaceMessage(
                  'shell.locale_changed',
                  announcedLocale,
                  localizationPolicy,
                )}
              />
            )}
          </p>
          </div>
        </div>
      }
      navigationLabel={navigationLabel.text}
      navigationLabelLocale={navigationLabel.resolvedLocale}
      navigationLinks={navigationLinks}
      skipToContentLabel={
        <LocalizedMessage resolution={skipToContent} />
      }
    >
      <div
        className="shell-content mx-auto w-full max-w-content"
        data-destination={destination}
      >
        {previewNotice.isPreview ? (
          <aside
            className="shell-preview"
            aria-label={previewLabel.text}
            data-review-status={previewNotice.reviewStatus}
            lang={previewNotice.resolvedLocale}
          >
            <span className="shell-preview__label">
              <LocalizedMessage resolution={previewLabel} />
            </span>
            <span className="shell-preview__message">
              <LocalizedMessage resolution={previewNotice} />
            </span>
          </aside>
        ) : isFallbackActive ? (
          <aside
            className="shell-preview"
            aria-label={fallbackLabel.text}
            data-fallback-reason={title.reason}
            lang={fallbackNotice.resolvedLocale}
          >
            <span className="shell-preview__label">
              <LocalizedMessage resolution={fallbackLabel} />
            </span>
            <span className="shell-preview__message">
              <LocalizedMessage resolution={fallbackNotice} />
            </span>
          </aside>
        ) : null}

        {showPreSignInGate ? (
          <PreSignInSplash
            canStartAuthorization={androidAuth !== null}
            message={message}
            onRetry={() => {
              setSession({ status: 'checking' })
              authentication
                .current()
                .then((snapshot) => {
                  setOfflineResume(null)
                  setSession(snapshot)
                })
                .catch(() => {
                  setSession({ status: 'unavailable' })
                })
            }}
            onSignIn={() => {
              if (androidAuth === null) {
                return
              }

              void androidAuth.beginAuthorization().catch(() => {
                setSession({ status: 'unavailable' })
              })
            }}
            status={
              session.status === 'unavailable'
                ? 'unavailable'
                : session.status === 'checking'
                  ? 'checking'
                  : 'anonymous'
            }
          />
        ) : hideDemoIntroduction && destination === 'learn' ? (
          <h1
            className="visually-hidden"
            id="destination-title"
            ref={destinationTitle}
            tabIndex={-1}
          >
            <LocalizedMessage resolution={title} />
          </h1>
        ) : hideDemoIntroduction &&
          (destination === 'practice' || destination === 'progress') ? (
          <h1
            className="shell-title shell-title--compact"
            id="destination-title"
            ref={destinationTitle}
            tabIndex={-1}
          >
            <LocalizedMessage resolution={title} />
          </h1>
        ) : hideDemoIntroduction ? null : (
          <section
            className="shell-introduction"
            aria-labelledby="destination-title"
          >
            <p className="shell-eyebrow">
              <LocalizedMessage resolution={shellEyebrow} />
            </p>
            <h1
              className="shell-title"
              id="destination-title"
              ref={destinationTitle}
              tabIndex={-1}
            >
              <LocalizedMessage resolution={title} />
            </h1>
            <p className="shell-description">
              <LocalizedMessage resolution={description} />
            </p>
          </section>
        )}

        {showPreSignInGate ? null : showDemoPhase1 ? (
          <DemoPhase1Shell
            conversationApi={conversationApi}
            demoMode={demoMode}
            demoOrganization={demoOrganization}
            levelSessionsApi={levelSessionsApi}
            locale={locale}
            localizationPolicy={localizationPolicy}
            onLocaleChange={setLocale}
          />
        ) : canLearn ? (
          <>
            {showDemoLearnHub ? (
              <DemoPhase1Shell
                conversationApi={conversationApi}
                demoMode={demoMode}
                demoOrganization={demoOrganization}
                forcedModule="levels"
                levelSessionsApi={levelSessionsApi}
                locale={locale}
                localizationPolicy={localizationPolicy}
                onLocaleChange={setLocale}
              />
            ) : (
              <LearnerLevelsSurface
                audioRecorder={audioRecorder}
                conversationApi={conversationApi}
                createClientId={createClientId}
                demoMode={demoMode}
                demoOrganization={demoOrganization}
                learnerJourneyApi={learnerJourneyApi}
                levelSessionsApi={levelSessionsApi}
                locale={locale}
                localizationPolicy={localizationPolicy}
                offlineBinding={offlineBinding}
                offlineStore={offlineStore}
                onLocaleChange={setLocale}
                resumeBundle={offlineResume?.storedBundle}
              />
            )}
          </>
        ) : canPractice ? (
          practiceSurface === 'roleplay' ? (
            <RoleplayJourney
              api={conversationApi}
              createClientId={createClientId}
              locale={locale}
              localizationPolicy={localizationPolicy}
              onBack={() => {
                setPracticeSurface('confidence')
              }}
            />
          ) : (
            <>
              <div className="shell-practice-switch">
                <button
                  onClick={() => {
                    setPracticeSurface('roleplay')
                  }}
                  type="button"
                >
                  <LocalizedMessage
                    resolution={message(
                      'conversation.roleplay.entry_button',
                    )}
                  />
                </button>
              </div>
              <ConversationJourney
                api={conversationApi}
                createClientId={createClientId}
                demoMode={demoMode}
                listenLines={practiceListenLines}
                locale={locale}
                localizationPolicy={localizationPolicy}
              />
            </>
          )
        ) : canShowDemoProgress ? (
          <DemoLearnerProgress
            api={learnerJourneyApi}
            locale={locale}
            localizationPolicy={localizationPolicy}
          />
        ) : canShowLevelProgress ? (
          <ProgressDashboard
            api={learnerJourneyApi}
            locale={locale}
            localizationPolicy={localizationPolicy}
            onMissionRequested={() => {
              window.location.hash = '#home'
            }}
          />
        ) : canShowOperatorConsole ? (
          <OperatorConsole
            api={operatorConsoleApi}
            locale={locale}
            localizationPolicy={localizationPolicy}
          />
        ) : (
          <p className="shell-checkpoint-notice">
            <LocalizedMessage
              resolution={message('shell.checkpoint_notice')}
            />
          </p>
        )}

        {demoMode || session.status !== 'authenticated' ? null : (
          <SessionSummary
            authentication={authentication}
            message={message}
            onSessionChange={setSession}
            session={session}
          />
        )}
      </div>
    </ApplicationFrame>
    </div>
  )
}

interface SessionSummaryProps {
  authentication: AuthenticationAdapter
  message: (key: MessageKey) => InterfaceMessageResolution
  onSessionChange: (session: SessionState) => void
  session: SessionState
}

function SessionSummary({
  authentication,
  message,
  onSessionChange,
  session,
}: SessionSummaryProps) {
  const statusMessage = sessionMessageKey(session)
  const organization =
    session.status === 'authenticated'
      ? session.identity.current_membership?.organization.name
      : undefined
  const isAndroidPkce = authentication.transport === 'passport-pkce'
  const androidAuth = isAndroidPkce
    ? (authentication as AndroidPkceAuthentication)
    : null

  return (
    <section
      className="shell-session"
      aria-labelledby="session-title"
    >
      <h2
        className="shell-session__title"
        id="session-title"
      >
        <LocalizedMessage resolution={message('session.label')} />
      </h2>
      <div
        className="shell-session__status"
        role="status"
      >
        <LocalizedMessage resolution={message(statusMessage)} />
        {organization === undefined ? null : (
          <span className="shell-session__organization">
            <span className="shell-session__organization-label">
              <LocalizedMessage
                resolution={message('session.organization_label')}
              />
            </span>
            <bdi
              className="shell-session__organization-name"
              lang=""
            >
              {organization}
            </bdi>
          </span>
        )}
      </div>
      {androidAuth === null ? null : (
        <div className="shell-session__actions">
          {session.status === 'anonymous' || session.status === 'unavailable' ? (
            <button
              type="button"
              onClick={() => {
                void androidAuth.beginAuthorization().catch(() => {
                  onSessionChange({ status: 'unavailable' })
                })
              }}
            >
              <LocalizedMessage
                resolution={message('session.android_sign_in')}
              />
            </button>
          ) : null}
          {session.status === 'authenticated' ? (
            <button
              type="button"
              onClick={() => {
                void androidAuth.logout().then(() => {
                  onSessionChange({ status: 'anonymous' })
                })
              }}
            >
              <LocalizedMessage
                resolution={message('session.android_sign_out')}
              />
            </button>
          ) : null}
        </div>
      )}
    </section>
  )
}

function supportedLocale(value: string): SupportedLocale | null {
  return (
    SUPPORTED_LOCALES.find((locale) => locale === value) ?? null
  )
}

function destinationFromHash(
  hash: string | undefined,
): PrimaryDestination | null {
  const candidate = hash?.replace(/^#/u, '')
  const destination = candidate?.split('/')[0]

  if (
    destination === 'home' ||
    destination === 'learn' ||
    destination === 'practice' ||
    destination === 'progress'
  ) {
    return destination
  }

  if (
    candidate !== undefined &&
    (PHASE1_SECONDARY_HASHES as readonly string[]).includes(candidate)
  ) {
    return 'home'
  }

  return null
}

function sessionMessageKey(session: SessionState): MessageKey {
  if (session.status === 'checking') {
    return 'session.checking'
  }

  if (session.status === 'anonymous') {
    return 'session.anonymous'
  }

  if (session.status === 'unavailable') {
    return 'session.unavailable'
  }

  return session.identity.current_membership === null
    ? 'session.select_membership'
    : 'session.authenticated'
}

export default App
