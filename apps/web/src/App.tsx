import { Capacitor } from '@capacitor/core'
import {
  ApplicationFrame,
  type PrimaryDestination,
} from '@global-english/design-system'
import {
  DEFAULT_LOCALE,
  INTERFACE_LOCALES,
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
import './tutor/tutorExperience.css'
import type {
  AndroidPkceAuthentication,
  AuthenticationAdapter,
  AuthenticationSnapshot,
  WebSessionAuthentication,
} from './auth/AuthenticationAdapter'
import { bindAndroidPkceDeepLink } from './auth/bindAndroidPkceDeepLink'
import { defaultAuthentication } from './auth/defaultAuthentication'
import { WebPasswordSignInPanel } from './auth/WebPasswordSignInPanel'
import { DemoLearnerProgress } from './demo/DemoLearnerProgress'
import { DemoPhase1Shell } from './demo/phase1/DemoPhase1Shell'
import type { ConversationJourneyApi, FriendSession } from './conversation/ConversationJourneyApi'
import { SpeakRoom } from './conversation/SpeakRoom'
import { defaultConversationJourneyApi } from './conversation/WebConversationJourneyApi'
import {
  INTERFACE_RESOLUTION_POLICY,
  resolveInterfaceMessage,
  useInterfaceFallbackReporting,
  type InterfaceMessageResolution,
} from './localization/interfaceLocalization'
import { LocalizedMessage } from './localization/LocalizedMessage'
import { MarketingLanding } from './marketing/MarketingLanding'
import type { AudioRecorder } from './learner/BrowserAudioRecorder'
import { LearnerJourney } from './learner/LearnerJourney'
import type { LearnerJourneyApi } from './learner/LearnerJourneyApi'
import { TuitionRequestPanel } from './learner/TuitionRequestPanel'
import type { TuitionRequestsApi } from './learner/TuitionRequestsApi'
import { LevelPathPanel } from './learner/LevelPathPanel'
import { TutorHomeHero, TutorPresence } from './tutor/TutorPresence'
import type { LevelSessionsApi } from './learner/LevelSessionsApi'
import { defaultLearnerJourneyApi } from './learner/WebLearnerJourneyApi'
import { defaultLevelSessionsApi } from './learner/WebLevelSessionsApi'
import { browserOfflineLearningStore } from './offline/BrowserOfflineLearningStore'
import type {
  OfflineLearningStore,
  OfflinePersonalDeviceResume,
  OfflineProfileBinding,
} from './offline/OfflineLearningStore'
import { OperatorConsole } from './operator/OperatorConsole'
import { PasswordAccountSecurityPanel } from './operator/PasswordAccountSecurityPanel'
import type { OperatorConsoleApi } from './operator/OperatorConsoleApi'
import { defaultOperatorConsoleApi } from './operator/OperatorConsoleApi'
import {
  defaultOperatorOrganizationApi,
  type OperatorOrganizationApi,
} from './operator/OperatorOrganizationApi'

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
  operatorOrganizationApi?: OperatorOrganizationApi
}

const demoTuitionRequestsApi: TuitionRequestsApi = {
  async list() {
    return {
      requests: [],
      institute_contact: {
        email: null,
        phone: null,
        whatsapp: null,
      },
    }
  },
  async create() {
    throw new Error('Demo tuition requests are local-only.')
  },
}

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
  speak: {
    description: 'page.speak.description',
    title: 'page.speak.title',
  },
  progress: {
    description: 'page.progress.description',
    title: 'page.progress.title',
  },
  tuition: {
    description: 'page.tuition.description',
    title: 'page.tuition.title',
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
  speak: {
    description: 'demo.page.speak.description',
    title: 'demo.page.speak.title',
  },
  progress: {
    description: 'demo.page.progress.description',
    title: 'demo.page.progress.title',
  },
  tuition: {
    description: 'demo.page.tuition.description',
    title: 'demo.page.tuition.title',
  },
}

const localeMessageKeys: Readonly<
  Record<(typeof INTERFACE_LOCALES)[number], MessageKey>
> = {
  'en-IN': 'locale.en_IN',
  'kn-IN': 'locale.kn_IN',
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
  operatorOrganizationApi = defaultOperatorOrganizationApi,
}: AppProps) {
  const [session, setSession] = useState<SessionState>({
    status: 'checking',
  })
  const [locale, setLocale] = useState<SupportedLocale>(DEFAULT_LOCALE)
  const [offlineResume, setOfflineResume] =
    useState<OfflinePersonalDeviceResume | null>(null)
  const [announcedLocale, setAnnouncedLocale] =
    useState<SupportedLocale | null>(null)
  const [speakMode, setSpeakMode] = useState<'everyday' | 'situation'>(
    'everyday',
  )
  const [boundConversation, setBoundConversation] =
    useState<FriendSession | null>(null)
  const [accountSecurityOpen, setAccountSecurityOpen] = useState(false)
  const [locationHash, setLocationHash] = useState(
    () => globalThis.location?.hash ?? '',
  )
  // In demo mode Home renders the learner journey directly, so every
  // runtime starts on Home unless the URL hash selects another destination.
  const [destination, setDestination] = useState<PrimaryDestination>(
    () => destinationFromHash(globalThis.location?.hash) ?? 'home',
  )
  const isNativeAndroid =
    Capacitor.isNativePlatform() && Capacitor.getPlatform() === 'android'
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
    const removeInvalidation = androidAuth.onAuthenticationInvalidated(() => {
      if (!cancelled) {
        setOfflineResume(null)
        setSession({ status: 'anonymous' })
      }
    })

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
      removeInvalidation()
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
      const hash = globalThis.location?.hash ?? ''
      setLocationHash(hash)
      const nextDestination = destinationFromHash(hash)

      if (nextDestination !== null) {
        setDestination(nextDestination)
      }
    }

    globalThis.addEventListener('hashchange', updateDestination)

    return () => {
      globalThis.removeEventListener('hashchange', updateDestination)
    }
  }, [])

  const brand = message('app.name')
  const navigationLabel = message('shell.navigation_label')
  const skipToContent = message('shell.skip_to_content')
  const fallbackLabel = message('localization.fallback_label')
  const fallbackNotice = message('localization.fallback_notice')
  const previewLabel = message('localization.preview_label')
  const previewNotice = message('localization.preview_notice')
  const routeMessages = (
    demoMode ? demoDestinationMessages : destinationMessages
  )[destination]
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
    speak: {
      href: '#speak',
      label: <LocalizedMessage resolution={message('nav.speak')} />,
    },
    progress: {
      href: '#progress',
      label: <LocalizedMessage resolution={message('nav.progress')} />,
    },
    tuition: {
      href: '#tuition',
      label: <LocalizedMessage resolution={message('nav.tuition')} />,
    },
  } as const
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
    authentication.transport === 'sanctum-cookie' &&
    session.identity.current_membership !== null &&
    session.identity.current_membership.membership_type !== 'learner'
  const title = message(
    isOperatorSession ? 'operator.shell.title' : routeMessages.title,
  )
  const description = message(
    isOperatorSession
      ? 'operator.shell.description'
      : routeMessages.description,
  )
  const shellEyebrow = message(
    isOperatorSession
      ? 'operator.shell.eyebrow'
      : demoMode
        ? 'demo.shell.eyebrow'
        : 'shell.eyebrow',
  )
  const isFallbackActive =
    navigationLabel.usedFallback ||
    title.usedFallback ||
    description.usedFallback
  const canHomeLearner =
    destination === 'home' &&
    isLearnerSession &&
    !demoMode
  const canLearnPath =
    destination === 'learn' &&
    (isLearnerSession || offlineResume !== null)
  const canSpeak = destination === 'speak' && isLearnerSession
  const canTuition = destination === 'tuition' && isLearnerSession
  const showDemoMission =
    demoMode && locationHash.replace(/^#/u, '') === 'mission' && isLearnerSession
  const canShowDemoProgress =
    demoMode && destination === 'progress' && isLearnerSession
  const canShowLevelProgress =
    !demoMode && destination === 'progress' && isLearnerSession
  const canShowOperatorConsole = isOperatorSession
  const mfaAuthentication = isWebSessionAuthentication(authentication)
    ? authentication
    : undefined
  const androidAuthentication =
    authentication.transport === 'passport-pkce'
      ? (authentication as AndroidPkceAuthentication)
      : null
  const isGuestSession =
    !demoMode &&
    (session.status === 'checking' ||
      session.status === 'anonymous' ||
      session.status === 'unavailable')
  const showDemoPhase1 = demoMode && destination === 'home'
  const showDedicatedSignIn = isGuestSession && locationHash === '#sign-in'
  const showMarketingLanding =
    isGuestSession && destination === 'home' && !showDedicatedSignIn
  const accountSecurityVisible =
    accountSecurityOpen && session.status === 'authenticated'
  const showAuthenticatedAccountPanel =
    !demoMode && accountSecurityVisible
  const suppressVisibleHero =
    showDemoMission ||
    canHomeLearner ||
    canLearnPath ||
    canSpeak ||
    canTuition ||
    canShowDemoProgress ||
    canShowLevelProgress ||
    canShowOperatorConsole
  const useQuietHero =
    !suppressVisibleHero &&
    !showMarketingLanding &&
    !showDedicatedSignIn &&
    !showDemoPhase1 &&
    (isLearnerSession ||
      session.status === 'anonymous' ||
      session.status === 'unavailable')

  useEffect(() => {
    const openAccountSecurityFromHash = () => {
      if (window.location.hash === '#operator-account-settings-title') {
        setAccountSecurityOpen(true)
      }
    }

    openAccountSecurityFromHash()
    window.addEventListener('hashchange', openAccountSecurityFromHash)

    return () => {
      window.removeEventListener('hashchange', openAccountSecurityFromHash)
    }
  }, [])

  useEffect(() => {
    if (!showDedicatedSignIn) {
      return
    }

    const focusSignIn = () => {
      window.scrollTo(0, 0)
      const username = document.getElementById('web-password-username')
      if (username instanceof HTMLElement) {
        username.focus()
        return
      }
      document.getElementById('sign-in-title')?.focus()
    }

    window.scrollTo(0, 0)
    const frame = window.requestAnimationFrame(focusSignIn)
    return () => {
      window.cancelAnimationFrame(frame)
    }
  }, [showDedicatedSignIn])
  useEffect(() => {
    if (previousDestination.current !== destination) {
      if (showMarketingLanding) {
        document.getElementById('marketing-hero-title')?.focus()
      } else {
        destinationTitle.current?.focus()
      }
      previousDestination.current = destination
    }
  }, [destination, showMarketingLanding])

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
      className={
        isOperatorSession ? undefined : 'ge-application-frame--tutor'
      }
      brandLabel={
        <>
          <img
            alt=""
            aria-hidden="true"
            className={
              isNativeAndroid
                ? 'shell-brand-logo shell-brand-logo--android'
                : 'shell-brand-logo'
            }
            height={isNativeAndroid ? 48 : 72}
            src={
              isNativeAndroid
                ? '/marketing/brand/logo-android.png'
                : '/marketing/brand/logo-web.png'
            }
            width={isNativeAndroid ? 110 : 180}
          />
          <span className="visually-hidden">{brand.text}</span>
        </>
      }
      brandLabelLocale={brand.resolvedLocale}
      currentDestination={destination}
      hidePrimaryNavigation={isOperatorSession || isGuestSession}
      headerActions={
        <div className="shell-header-actions">
          {androidAuthentication === null || session.status === 'checking' ? null : (
            <>
              {session.status === 'authenticated' ? (
                <p className="shell-account-menu__status" role="status">
                  <LocalizedMessage
                    resolution={message(sessionMessageKey(session))}
                  />
                </p>
              ) : null}
              <button
              className="shell-header-authentication"
              type="button"
              onClick={() => {
                if (session.status === 'authenticated') {
                  void androidAuthentication
                    .logout()
                    .then(() => setSession({ status: 'anonymous' }))
                    .catch(() => setSession({ status: 'unavailable' }))

                  return
                }

                void androidAuthentication.beginAuthorization().catch(() => {
                  setSession({ status: 'unavailable' })
                })
              }}
            >
              <LocalizedMessage
                resolution={message(
                  session.status === 'authenticated'
                    ? 'session.android_sign_out'
                    : 'session.android_sign_in',
                )}
              />
            </button>
            </>
          )}
          {!demoMode &&
          authentication.transport === 'sanctum-cookie' &&
          (session.status === 'anonymous' ||
            session.status === 'unavailable') ? (
            <>
              {session.status === 'unavailable' ? (
                <p className="shell-account-menu__status" role="status">
                  <LocalizedMessage
                    resolution={message('session.unavailable')}
                  />
                </p>
              ) : null}
            <a
              className={
                showDedicatedSignIn
                  ? 'shell-header-authentication shell-header-authentication--quiet'
                  : 'shell-header-authentication'
              }
              href={showDedicatedSignIn ? '#home' : '#sign-in'}
              onClick={() => {
                if (showDedicatedSignIn) {
                  setDestination('home')
                  setLocationHash('#home')
                  return
                }

                setDestination('home')
                setLocationHash('#sign-in')
              }}
            >
              <LocalizedMessage
                resolution={message(
                  showDedicatedSignIn ? 'nav.home' : 'session.label',
                )}
              />
            </a>
            </>
          ) : null}
          {!demoMode &&
          authentication.transport === 'sanctum-cookie' &&
          session.status === 'authenticated' ? (
            <div className="shell-account-menu">
              <p className="shell-account-menu__status" role="status">
                <LocalizedMessage
                  resolution={message(sessionMessageKey(session))}
                />
                {session.identity.current_membership?.organization.name ? (
                  <bdi className="shell-account-menu__organization" lang="">
                    {session.identity.current_membership.organization.name}
                  </bdi>
                ) : null}
              </p>
              <div className="shell-account-menu__actions">
                <button
                  aria-controls="session-account-security"
                  aria-expanded={accountSecurityVisible}
                  className="shell-header-authentication"
                  onClick={() =>
                    setAccountSecurityOpen((current) => !current)
                  }
                  type="button"
                >
                  <LocalizedMessage
                    resolution={message('session.account_menu')}
                  />
                </button>
                <button
                  className="shell-header-authentication shell-header-authentication--quiet"
                  onClick={() => {
                    const webAuth = isWebSessionAuthentication(authentication)
                      ? authentication
                      : null
                    if (webAuth === null) {
                      return
                    }
                    void webAuth
                      .logout()
                      .then(() => {
                        setAccountSecurityOpen(false)
                        setSession({ status: 'anonymous' })
                      })
                      .catch(() => {
                        setAccountSecurityOpen(false)
                        setSession({ status: 'unavailable' })
                      })
                  }}
                  type="button"
                >
                  <LocalizedMessage
                    resolution={message('session.android_sign_out')}
                  />
                </button>
              </div>
            </div>
          ) : null}
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
              {INTERFACE_LOCALES.map((supported) => {
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
        className={
          showMarketingLanding
            ? 'shell-content shell-content--gate shell-content--landing'
            : session.status === 'anonymous' || session.status === 'unavailable'
              ? 'shell-content shell-content--gate'
              : 'shell-content'
        }
      >
        {demoMode ? (
          <aside
            className="shell-demo"
            aria-label={message('demo.label').text}
            data-demo-mode="synthetic-local"
          >
            <strong>
              <LocalizedMessage resolution={message('demo.label')} />
            </strong>
            <span>
              <LocalizedMessage resolution={message('demo.notice')} />
            </span>
            {demoOrganization === undefined ? null : (
              <span className="shell-demo__organization">
                {demoOrganization}
              </span>
            )}
          </aside>
        ) : null}

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

        {showDemoPhase1 || showMarketingLanding || showDedicatedSignIn ? null : suppressVisibleHero ? (
          <h1
            className="visually-hidden"
            id="destination-title"
            ref={destinationTitle}
            tabIndex={-1}
          >
            <LocalizedMessage resolution={title} />
          </h1>
        ) : (
          <section
            className={
              useQuietHero
                ? 'shell-introduction shell-introduction--quiet'
                : 'shell-introduction'
            }
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

        {showMarketingLanding ? (
          <MarketingLanding
            locale={locale}
            message={message}
          />
        ) : null}

        {showDedicatedSignIn ? (
          <section
            className="shell-sign-in-page"
            aria-labelledby="sign-in-title"
            id="web-sign-in-page"
          >
            <div className="shell-sign-in-page__card">
              <a
                className="shell-sign-in-page__close"
                href="#home"
                onClick={() => {
                  setDestination('home')
                  setLocationHash('#home')
                }}
              >
                <span aria-hidden="true">×</span>
                <LocalizedMessage resolution={message('nav.home')} />
              </a>
              <header className="shell-sign-in-page__header">
                <TutorPresence
                  locale={locale}
                  localizationPolicy={localizationPolicy}
                  size="md"
                />
                <p className="shell-sign-in-page__eyebrow">
                  <LocalizedMessage
                    resolution={message('marketing.hero.badge')}
                  />
                </p>
                <h1 id="sign-in-title" tabIndex={-1}>
                  <LocalizedMessage resolution={message('session.label')} />
                </h1>
                <p className="shell-sign-in-page__lede">
                  <LocalizedMessage
                    resolution={message('marketing.join.body')}
                  />
                </p>
              </header>
              <SessionSummary
                accountSecurityOpen={accountSecurityVisible}
                authentication={authentication}
                embedded
                locale={locale}
                localizationPolicy={localizationPolicy}
                message={message}
                onAccountSecurityOpenChange={setAccountSecurityOpen}
                onSessionChange={setSession}
                session={session}
              />
            </div>
          </section>
        ) : null}

        {showDemoMission ? (
          <LearnerJourney
            api={learnerJourneyApi}
            audioRecorder={audioRecorder}
            createClientId={createClientId}
            locale={locale}
            localizationPolicy={localizationPolicy}
            offlineBinding={offlineBinding}
            offlineStore={offlineStore}
            resumeBundle={offlineResume?.storedBundle}
          />
        ) : showDemoPhase1 ? (
          <DemoPhase1Shell
            locale={locale}
            localizationPolicy={localizationPolicy}
          />
        ) : canHomeLearner ? (
          <div className="learner-stack">
            <TutorHomeHero
              locale={locale}
              localizationPolicy={localizationPolicy}
            />
            <LearnerJourney
              api={learnerJourneyApi}
              audioRecorder={audioRecorder}
              createClientId={createClientId}
              locale={locale}
              localizationPolicy={localizationPolicy}
              offlineBinding={offlineBinding}
              offlineStore={offlineStore}
              resumeBundle={offlineResume?.storedBundle}
            />
          </div>
        ) : canLearnPath ? (
          <LevelPathPanel
            api={levelSessionsApi}
            conversationApi={conversationApi}
            createClientId={createClientId}
            demoMode={demoMode}
            locale={locale}
            localizationPolicy={localizationPolicy}
            onOpenRoleplay={() => {
              setBoundConversation(null)
              setSpeakMode('situation')
              globalThis.location.hash = '#speak'
            }}
          />
        ) : canSpeak ? (
          <SpeakRoom
            api={conversationApi}
            createClientId={createClientId}
            demoMode={demoMode}
            initialMode={speakMode}
            initialSession={boundConversation ?? undefined}
            key={`speak-${speakMode}-${boundConversation?.id ?? 'unbound'}`}
            locale={locale}
            localizationPolicy={localizationPolicy}
          />
        ) : canTuition ? (
          <TuitionRequestPanel
            api={
              demoMode
                ? demoTuitionRequestsApi
                : undefined
            }
            createClientId={createClientId}
            locale={locale}
            localizationPolicy={localizationPolicy}
          />
        ) : canShowDemoProgress ? (
          <DemoLearnerProgress
            api={learnerJourneyApi}
            locale={locale}
            localizationPolicy={localizationPolicy}
          />
        ) : canShowLevelProgress ? (
          <LevelPathPanel
            api={levelSessionsApi}
            conversationApi={conversationApi}
            createClientId={createClientId}
            demoMode={demoMode}
            locale={locale}
            localizationPolicy={localizationPolicy}
            onOpenRoleplay={() => {
              setBoundConversation(null)
              setSpeakMode('situation')
              globalThis.location.hash = '#speak'
            }}
          />
        ) : canShowOperatorConsole ? (
          <OperatorConsole
            api={operatorConsoleApi}
            createCommandId={createClientId}
            currentMembershipId={
              session.status === 'authenticated'
                ? (session.identity.current_membership?.id ?? '')
                : ''
            }
            locale={locale}
            localizationPolicy={localizationPolicy}
            mfaAuthentication={mfaAuthentication}
            onCredentialsRevoked={() => {
              setOfflineResume(null)
              setSession({ status: 'anonymous' })
            }}
            organizationApi={operatorOrganizationApi}
          />
        ) : showMarketingLanding ? null : session.status === 'anonymous' ||
          session.status === 'unavailable' ? (
          <p className="shell-sign-in-hint">
            <LocalizedMessage resolution={message('session.sign_in_hint')} />{' '}
            <a
              href="#sign-in"
              onClick={() => {
                setDestination('home')
                setLocationHash('#sign-in')
              }}
            >
              <LocalizedMessage
                resolution={message('session.label')}
              />
            </a>
          </p>
        ) : (
          <p className="shell-checkpoint-notice">
            <LocalizedMessage
              resolution={message('shell.checkpoint_notice')}
            />
          </p>
        )}

        {demoMode || showMarketingLanding || showDedicatedSignIn
          ? null
          : showAuthenticatedAccountPanel
            ? (
                <SessionSummary
                  accountSecurityOpen={accountSecurityVisible}
                  authentication={authentication}
                  locale={locale}
                  localizationPolicy={localizationPolicy}
                  message={message}
                  onAccountSecurityOpenChange={setAccountSecurityOpen}
                  onSessionChange={setSession}
                  session={session}
                />
              )
            : null}
      </div>
    </ApplicationFrame>
    </div>
  )
}

interface SessionSummaryProps {
  accountSecurityOpen: boolean
  authentication: AuthenticationAdapter
  embedded?: boolean
  locale: SupportedLocale
  localizationPolicy: ResolutionPolicy
  message: (key: MessageKey) => InterfaceMessageResolution
  onAccountSecurityOpenChange: (open: boolean) => void
  onSessionChange: (session: SessionState) => void
  session: SessionState
}

function SessionSummary({
  accountSecurityOpen,
  authentication,
  embedded = false,
  locale,
  localizationPolicy,
  message,
  onAccountSecurityOpenChange,
  onSessionChange,
  session,
}: SessionSummaryProps) {
  useEffect(() => {
    if (
      accountSecurityOpen &&
      window.location.hash === '#operator-account-settings-title'
    ) {
      document
        .getElementById('operator-account-settings-title')
        ?.scrollIntoView({ block: 'start' })
    }
  }, [accountSecurityOpen])

  const statusMessage = sessionMessageKey(session)
  const organization =
    session.status === 'authenticated'
      ? session.identity.current_membership?.organization.name
      : undefined
  const webAuth = isWebSessionAuthentication(authentication)
    ? authentication
    : null
  const isAuthenticated = session.status === 'authenticated'

  return (
    <section
      className={`shell-session${
        webAuth !== null && !isAuthenticated ? ' shell-session--sign-in' : ''
      }${isAuthenticated ? ' shell-session--account' : ''}${
        embedded ? ' shell-session--embedded' : ''
      }`}
      aria-labelledby={embedded && !isAuthenticated ? undefined : 'session-title'}
      id={
        embedded
          ? undefined
          : isAuthenticated
            ? 'session-account-security'
            : 'sign-in'
      }
    >
      {embedded && !isAuthenticated ? null : (
        <h2 className="shell-session__title" id="session-title">
          <LocalizedMessage
            resolution={message(
              isAuthenticated ? 'session.account_menu' : 'session.label',
            )}
          />
        </h2>
      )}
      {!isAuthenticated && (!embedded || session.status !== 'anonymous') ? (
        <div
          className={
            embedded
              ? 'shell-session__status shell-session__status--embedded'
              : 'shell-session__status'
          }
          role="status"
        >
          <LocalizedMessage resolution={message(statusMessage)} />
        </div>
      ) : null}
      {!embedded && isAuthenticated && organization !== undefined ? (
        <div className="shell-session__status" role="status">
          <span className="shell-session__organization">
            <span className="shell-session__organization-label">
              <LocalizedMessage
                resolution={message('session.organization_label')}
              />
            </span>
            <bdi className="shell-session__organization-name" lang="">
              {organization}
            </bdi>
          </span>
        </div>
      ) : null}
      {webAuth !== null && !isAuthenticated ? (
        <WebPasswordSignInPanel
          authentication={webAuth}
          embedded={embedded}
          message={message}
          onAuthenticated={onSessionChange}
        />
      ) : null}
      {webAuth !== null && isAuthenticated && accountSecurityOpen ? (
        <PasswordAccountSecurityPanel
          authentication={webAuth}
          locale={locale}
          localizationPolicy={localizationPolicy}
          onCredentialsRevoked={() => {
            onAccountSecurityOpenChange(false)
            onSessionChange({ status: 'anonymous' })
          }}
        />
      ) : null}
    </section>
  )
}

function supportedLocale(value: string): SupportedLocale | null {
  return INTERFACE_LOCALES.find((locale) => locale === value) ?? null
}

function isWebSessionAuthentication(
  authentication: AuthenticationAdapter,
): authentication is WebSessionAuthentication {
  const candidate = authentication as Partial<WebSessionAuthentication>

  return (
    authentication.transport === 'sanctum-cookie' &&
    typeof candidate.signIn === 'function' &&
    typeof candidate.authenticationMode === 'function' &&
    typeof candidate.requestPasswordRecovery === 'function' &&
    typeof candidate.passwordStatus === 'function' &&
    typeof candidate.establishPassword === 'function' &&
    typeof candidate.confirmPassword === 'function' &&
    typeof candidate.changePassword === 'function' &&
    typeof candidate.changeUsername === 'function' &&
    typeof candidate.privilegedMfaStatus === 'function' &&
    typeof candidate.beginPrivilegedMfaEnrollment === 'function' &&
    typeof candidate.confirmPrivilegedMfaEnrollment === 'function' &&
    typeof candidate.createPrivilegedMfaChallenge === 'function' &&
    typeof candidate.verifyPrivilegedMfaChallenge === 'function' &&
    typeof candidate.regeneratePrivilegedMfaRecoveryCodes === 'function' &&
    typeof candidate.revokePrivilegedMfaFactor === 'function' &&
    typeof candidate.requestOtp === 'function' &&
    typeof candidate.verifyOtp === 'function' &&
    typeof candidate.sessions === 'function' &&
    typeof candidate.revokeSession === 'function' &&
    typeof candidate.revokeAllSessions === 'function'
  )
}

function destinationFromHash(
  hash: string | undefined,
): PrimaryDestination | null {
  const candidate = hash?.replace(/^#/u, '')

  if (candidate === 'practice' || candidate === 'speak') {
    return 'speak'
  }

  if (candidate === 'home' || candidate === 'learn' || candidate === 'progress') {
    return candidate
  }

  if (candidate === 'tuition' || candidate === 'one-to-one') {
    return 'tuition'
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
