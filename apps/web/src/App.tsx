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
  lazy,
  Suspense,
  useEffect,
  useMemo,
  useRef,
  useState,
  type ChangeEvent,
} from 'react'
import './App.css'
import './demo/demoExperience.css'
import './tutor/tutorExperience.css'
import './theme/themeTokens.css'
import './theme/futuraExperience.css'
import './theme/themeSurfaces.css'
import type {
  AndroidPkceAuthentication,
  AuthenticationAdapter,
  AuthenticationSnapshot,
  WebSessionAuthentication,
} from './auth/AuthenticationAdapter'
import {
  isFederatedAccountAuthentication,
  isPasswordFormAuthentication,
} from './auth/AuthenticationAdapter'
import { bindAndroidPkceDeepLink } from './auth/bindAndroidPkceDeepLink'
import { defaultAuthentication } from './auth/defaultAuthentication'
import { FederatedAccountsPanel } from './auth/FederatedAccountsPanel'
import { WebPasswordSignInPanel } from './auth/WebPasswordSignInPanel'
import type { ConversationJourneyApi, FriendSession } from './conversation/ConversationJourneyApi'
import { defaultConversationJourneyApi } from './conversation/WebConversationJourneyApi'
import {
  INTERFACE_RESOLUTION_POLICY,
  resolveInterfaceMessage,
  useInterfaceFallbackReporting,
  type InterfaceMessageResolution,
} from './localization/interfaceLocalization'
import { LocalizedMessage } from './localization/LocalizedMessage'
import './theme/studioExperience.css'
import './theme/miraGescGreenPin.css'
import './theme/miraLearnerChrome.css'
import type { AudioRecorder } from './learner/BrowserAudioRecorder'
import type { LearnerJourneyApi } from './learner/LearnerJourneyApi'
import { LegalLinks } from './legal/LegalLinks'
import type { TuitionRequestsApi } from './learner/TuitionRequestsApi'
import { LiveAiMinutesChip } from './subscriptions/LiveAiMinutesChip'
import { InterfaceThemeControl } from './theme/InterfaceThemeControl'
import { useInterfaceTheme } from './theme/useInterfaceTheme'
import type { LearnerEntitlement } from './subscriptions/LearnerSubscriptionApi'
import { useLearnerEntitlement } from './subscriptions/useLearnerEntitlement'
import { TutorHomeHero, TutorPresence } from './tutor/TutorPresence'
import type {
  LevelSessionsApi,
  SessionPartId,
} from './learner/LevelSessionsApi'
import { DEMO_SESSION_PARTS } from './demo/demoLevelFixtures'
import { defaultLearnerJourneyApi } from './learner/WebLearnerJourneyApi'
import { defaultLevelSessionsApi } from './learner/WebLevelSessionsApi'
import {
  ANDROID_APK_FILENAME,
  ANDROID_APK_HREF,
} from './marketing/androidDownload'
import { browserOfflineLearningStore } from './offline/BrowserOfflineLearningStore'
import type {
  OfflineLearningStore,
  OfflinePersonalDeviceResume,
  OfflineProfileBinding,
} from './offline/OfflineLearningStore'
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

interface OperatorUsernameResource {
  readonly authentication: WebSessionAuthentication
  readonly membershipId: string
  readonly username: string | null
}

const DemoLearnerProgress = lazy(async () => {
  const module = await import('./demo/DemoLearnerProgress')
  return { default: module.DemoLearnerProgress }
})
const DemoPhase1Shell = lazy(async () => {
  const module = await import('./demo/phase1/DemoPhase1Shell')
  return { default: module.DemoPhase1Shell }
})
const LearnerJourney = lazy(async () => {
  const module = await import('./learner/LearnerJourney')
  return { default: module.LearnerJourney }
})
const LevelPathPanel = lazy(async () => {
  const module = await import('./learner/LevelPathPanel')
  return { default: module.LevelPathPanel }
})
const MarketingLanding = lazy(async () => {
  const module = await import('./marketing/MarketingLanding')
  return { default: module.MarketingLanding }
})
const GuestMiraHome = lazy(async () => {
  const module = await import('./learner/GuestMiraHome')
  return { default: module.GuestMiraHome }
})
const OperatorConsole = lazy(async () => {
  const module = await import('./operator/OperatorConsole')
  return { default: module.OperatorConsole }
})
const ProgressDashboard = lazy(async () => {
  const module = await import('./learner/ProgressDashboard')
  return { default: module.ProgressDashboard }
})
const SpeakRoom = lazy(async () => {
  const module = await import('./conversation/SpeakRoom')
  return { default: module.SpeakRoom }
})
const SubscriptionPanel = lazy(async () => {
  const module = await import('./subscriptions/SubscriptionPanel')
  return { default: module.SubscriptionPanel }
})
const TuitionRequestPanel = lazy(async () => {
  const module = await import('./learner/TuitionRequestPanel')
  return { default: module.TuitionRequestPanel }
})

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
  const { preference: themePreference, resolvedTheme, setPreference: setThemePreference } =
    useInterfaceTheme()
  const [speakMode, setSpeakMode] = useState<'everyday' | 'situation'>(
    'everyday',
  )
  const directEntitlementScope =
    !demoMode &&
    session.status === 'authenticated' &&
    session.identity.current_membership?.membership_type === 'learner' &&
    session.identity.current_membership.organization.kind === 'platform_direct'
      ? session.identity.current_membership.id
      : null
  const {
    applyEntitlement,
    entitlement,
    refreshEntitlement,
    status: entitlementStatus,
  } = useLearnerEntitlement(directEntitlementScope)
  const [boundConversation, setBoundConversation] =
    useState<FriendSession | null>(null)
  const [accountSecurityOpen, setAccountSecurityOpen] = useState(false)
  const [operatorUsernameResource, setOperatorUsernameResource] =
    useState<OperatorUsernameResource | null>(null)
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
  const learnAllowedSessionParts = useMemo(():
    | readonly SessionPartId[]
    | undefined => {
    if (demoMode) {
      return DEMO_SESSION_PARTS
    }
    if (directEntitlementScope === null) {
      return undefined
    }
    if (entitlementStatus === 'loading' || entitlement === null) {
      return DEMO_SESSION_PARTS
    }
    return entitlement.allowed_session_parts as SessionPartId[]
  }, [
    demoMode,
    directEntitlementScope,
    entitlement,
    entitlementStatus,
  ])

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
        globalThis.location.hash = '#home'
        setDestination('home')
        setLocationHash('#home')
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

    updateDestination()
    globalThis.addEventListener('hashchange', updateDestination)

    return () => {
      globalThis.removeEventListener('hashchange', updateDestination)
    }
  }, [])

  useEffect(() => {
    if (session.status !== 'authenticated' || locationHash !== '#sign-in') {
      return
    }

    globalThis.location.hash = '#home'
  }, [locationHash, session.status])

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
  const operatorAuthentication = isWebSessionAuthentication(authentication)
    ? authentication
    : null
  const operatorMembershipId = isOperatorSession
    ? session.identity.current_membership?.id ?? null
    : null
  const operatorUsername =
    operatorAuthentication !== null &&
    operatorMembershipId !== null &&
    operatorUsernameResource?.authentication === operatorAuthentication &&
    operatorUsernameResource.membershipId === operatorMembershipId
      ? operatorUsernameResource.username
      : null
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
  const isOperatorWorkspaceHash = operatorWorkspaceHashFromLocation(locationHash)
  const showDedicatedSignIn =
    isGuestSession &&
    (locationHash === '#sign-in' || isOperatorWorkspaceHash)
  const showMarketingLanding =
    isGuestSession &&
    isMarketingLandingHash(locationHash) &&
    !showDedicatedSignIn
  const showGuestMiraHome =
    isGuestSession &&
    destination === 'home' &&
    !showDedicatedSignIn &&
    !showMarketingLanding &&
    !isOperatorWorkspaceHash
  useEffect(() => {
    if (
      isGuestSession &&
      showMarketingLanding &&
      themePreference !== 'light'
    ) {
      setThemePreference('light')
    }
  }, [
    isGuestSession,
    setThemePreference,
    showMarketingLanding,
    themePreference,
  ])
  const accountSecurityVisible =
    accountSecurityOpen && session.status === 'authenticated'
  const showAuthenticatedAccountPanel =
    !demoMode &&
    accountSecurityVisible &&
    (authentication.transport === 'sanctum-cookie' ||
      androidAuthentication !== null)
  const openAccountBilling = () => {
    setAccountSecurityOpen(true)
    window.setTimeout(() => {
      document
        .getElementById('subscription-panel')
        ?.scrollIntoView({ block: 'nearest' })
    }, 0)
  }
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
    !showGuestMiraHome &&
    !showDedicatedSignIn &&
    !showDemoPhase1 &&
    (isLearnerSession ||
      session.status === 'anonymous' ||
      session.status === 'unavailable')

  useEffect(() => {
    if (operatorAuthentication === null || operatorMembershipId === null) {
      return undefined
    }

    let active = true

    void operatorAuthentication
      .passwordStatus()
      .then((status) => {
        if (active) {
          setOperatorUsernameResource({
            authentication: operatorAuthentication,
            membershipId: operatorMembershipId,
            username: status.username,
          })
        }
      })
      .catch(() => {
        if (active) {
          setOperatorUsernameResource({
            authentication: operatorAuthentication,
            membershipId: operatorMembershipId,
            username: null,
          })
        }
      })

    return () => {
      active = false
    }
  }, [operatorAuthentication, operatorMembershipId])

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
      } else if (showGuestMiraHome) {
        document.getElementById('guest-mira-home-title')?.focus()
      } else {
        destinationTitle.current?.focus()
      }
      previousDestination.current = destination
    }
  }, [destination, showGuestMiraHome, showMarketingLanding])

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
        isOperatorSession
          ? 'ge-application-frame--operator'
          : `ge-application-frame--tutor${
              destination === 'home' ? ' ge-application-frame--home' : ''
            }${isNativeAndroid ? ' ge-application-frame--native-android' : ''}${
              isGuestSession
                ? showGuestMiraHome
                  ? ' ge-application-frame--studio ge-application-frame--studio-learner ge-application-frame--mira-learner'
                  : ' ge-application-frame--studio'
                : isLearnerSession || demoMode
                  ? ' ge-application-frame--studio ge-application-frame--studio-learner ge-application-frame--mira-learner'
                  : ''
            }`
      }
      brandLabel={
        <>
          {isGuestSession || isLearnerSession || demoMode ? (
            <span aria-hidden="true" className="shell-studio-brand">
              <span className="shell-studio-brand__mark">
                <i />
                <i />
                <i />
              </span>
              <span className="shell-studio-brand__name">
                Global <b>English</b>
              </span>
            </span>
          ) : (
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
                resolvedTheme === 'light'
                  ? '/marketing/brand/logo-academy.png'
                  : isNativeAndroid
                    ? '/marketing/brand/logo-android.png'
                    : '/marketing/brand/logo-web.png'
              }
              width={isNativeAndroid ? 110 : 180}
            />
          )}
          <span className="visually-hidden">{brand.text}</span>
        </>
      }
      brandLabelLocale={brand.resolvedLocale}
      currentDestination={destination}
      hidePrimaryNavigation={isOperatorSession || isGuestSession}
      headerActions={
        <div className="shell-header-actions">
          {session.status === 'authenticated' && isOperatorSession ? (
            <p className="shell-operator-identity" role="status">
              <span className="shell-operator-identity__label">
                <LocalizedMessage
                  resolution={message('operator.header.signed_in_as')}
                />
              </span>
              <bdi className="shell-operator-identity__name">
                {operatorUsername ??
                  message('operator.header.username_unavailable').text}
              </bdi>
              {session.identity.current_membership?.organization.name ? (
                <bdi className="shell-operator-identity__organization" lang="">
                  {session.identity.current_membership.organization.name}
                </bdi>
              ) : null}
            </p>
          ) : session.status === 'authenticated' ? (
            <p className="visually-hidden" role="status">
              <LocalizedMessage
                resolution={message(sessionMessageKey(session))}
              />
              {session.identity.current_membership?.organization.name ? (
                <bdi className="shell-account-menu__organization" lang="">
                  {session.identity.current_membership.organization.name}
                </bdi>
              ) : null}
            </p>
          ) : null}
          {!demoMode &&
          session.status === 'unavailable' &&
          (authentication.transport === 'sanctum-cookie' ||
            androidAuthentication !== null) ? (
            <p className="shell-header-actions__notice" role="status">
              <LocalizedMessage resolution={message('session.unavailable')} />
            </p>
          ) : null}
          <div className="shell-header-toolbar">
            {androidAuthentication !== null &&
            session.status === 'authenticated' ? (
              <>
                {isLearnerSession ? (
                  <LiveAiMinutesChip
                    entitlement={entitlement}
                    locale={locale}
                    localizationPolicy={localizationPolicy}
                    onOpenAccount={openAccountBilling}
                  />
                ) : null}
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
                  type="button"
                  onClick={() => {
                    void androidAuthentication
                      .logout()
                      .then(() => {
                        setAccountSecurityOpen(false)
                        setSession({ status: 'anonymous' })
                        globalThis.location.hash = '#home'
                        setDestination('home')
                        setLocationHash('#home')
                      })
                      .catch(() => setSession({ status: 'unavailable' }))
                  }}
                >
                  <LocalizedMessage
                    resolution={message('session.android_sign_out')}
                  />
                </button>
              </>
            ) : null}
            {!demoMode &&
            (authentication.transport === 'sanctum-cookie' ||
              androidAuthentication !== null) &&
            (session.status === 'checking' ||
              session.status === 'anonymous' ||
              session.status === 'unavailable') ? (
              <>
                <a
                  className={
                    showDedicatedSignIn
                      ? 'shell-header-authentication shell-header-authentication--quiet'
                      : 'shell-header-nav-link'
                  }
                  href={showDedicatedSignIn ? '#home' : '#sign-in'}
                  onClick={(event) => {
                    event.preventDefault()

                    if (showDedicatedSignIn) {
                      globalThis.location.hash = '#home'
                      setDestination('home')
                      setLocationHash('#home')
                      return
                    }

                    globalThis.location.hash = '#sign-in'
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
                {showDedicatedSignIn ? null : (
                  <a
                    className="shell-header-authentication"
                    href="#sign-in"
                    onClick={(event) => {
                      event.preventDefault()
                      globalThis.location.hash = '#sign-in'
                      setDestination('home')
                      setLocationHash('#sign-in')
                    }}
                  >
                    <LocalizedMessage
                      resolution={message('marketing.hero.cta_sign_in')}
                    />
                  </a>
                )}
              </>
            ) : null}
            {!demoMode &&
            authentication.transport === 'sanctum-cookie' &&
            session.status === 'authenticated' ? (
              <>
                {isLearnerSession ? (
                  <LiveAiMinutesChip
                    entitlement={entitlement}
                    locale={locale}
                    localizationPolicy={localizationPolicy}
                    onOpenAccount={openAccountBilling}
                  />
                ) : null}
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
                        globalThis.location.hash = '#home'
                        setDestination('home')
                        setLocationHash('#home')
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
              </>
            ) : null}
            {!demoMode &&
            !isNativeAndroid &&
            !isOperatorSession &&
            !showDedicatedSignIn ? (
              <a
                className="shell-android-download"
                download={ANDROID_APK_FILENAME}
                href={ANDROID_APK_HREF}
              >
                <span
                  aria-hidden="true"
                  className="shell-android-download__icon"
                >
                  📱
                </span>
                <LocalizedMessage
                  resolution={message('shell.android_download')}
                />
              </a>
            ) : null}
            {isGuestSession ? null : (
            <InterfaceThemeControl
              locale={locale}
              localizationPolicy={localizationPolicy}
              onPreferenceChange={setThemePreference}
              preference={themePreference}
            />
            )}
            <div className="shell-locale-control shell-locale-control--compact">
              <label className="visually-hidden" htmlFor="interface-locale">
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
              {previewNotice.isPreview ? (
                <aside
                  className="shell-locale-note"
                  aria-label={previewLabel.text}
                  data-review-status={previewNotice.reviewStatus}
                  lang={previewNotice.resolvedLocale}
                >
                  <LocalizedMessage resolution={previewNotice} />
                </aside>
              ) : isFallbackActive ? (
                <aside
                  className="shell-locale-note"
                  aria-label={fallbackLabel.text}
                  data-fallback-reason={title.reason}
                  lang={fallbackNotice.resolvedLocale}
                >
                  <LocalizedMessage resolution={fallbackNotice} />
                </aside>
              ) : null}
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

        {showDemoPhase1 ||
        showMarketingLanding ||
        showGuestMiraHome ||
        showDedicatedSignIn ? null : suppressVisibleHero ? (
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

        <Suspense
          fallback={
            <p className="shell-route-fallback" role="status">
              <LocalizedMessage resolution={message('learner.loading')} />
            </p>
          }
        >
        {showGuestMiraHome ? (
          <GuestMiraHome
            locale={locale}
            localizationPolicy={localizationPolicy}
          />
        ) : null}

        {showMarketingLanding ? (
          <MarketingLanding
            hideAndroidPromo={isNativeAndroid}
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
                  size="sm"
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
                applyEntitlement={applyEntitlement}
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
              allowedSessionParts={learnAllowedSessionParts}
              api={levelSessionsApi}
              conversationApi={conversationApi}
              createClientId={createClientId}
              demoMode={demoMode}
              locale={locale}
              localizationPolicy={localizationPolicy}
              offlineBinding={offlineBinding}
              offlineStore={offlineStore}
              onOpenRoleplay={() => {
                setBoundConversation(null)
                setSpeakMode('situation')
                globalThis.location.hash = '#speak'
              }}
            />
        ) : canSpeak ? (
          <SpeakRoom
              allowsRoleplay={
                demoMode ||
                directEntitlementScope === null ||
                entitlement?.allows_roleplay === true
              }
              api={conversationApi}
              createClientId={createClientId}
              demoMode={demoMode}
              initialMode={speakMode}
              initialSession={boundConversation ?? undefined}
              key={`speak-${speakMode}-${boundConversation?.id ?? 'unbound'}`}
              locale={locale}
              localizationPolicy={localizationPolicy}
              onLiveAiUsageChanged={() => {
                void refreshEntitlement()
              }}
              roleplayStatusMessageKey={
                demoMode || directEntitlementScope === null
                  ? undefined
                  : entitlementStatus === 'loading'
                    ? 'learner.loading'
                    : entitlementStatus === 'unavailable'
                      ? 'subscription.error'
                      : entitlement?.allows_roleplay === true
                        ? undefined
                        : 'subscription.roleplay_locked'
              }
              voiceUnavailableMessageKey={
                entitlement?.live_ai_seconds_remaining !== null &&
                entitlement?.live_ai_seconds_remaining !== undefined &&
                entitlement.live_ai_seconds_remaining <= 10
                  ? entitlement.is_demo
                    ? 'entitlement.demo_ai_exhausted'
                    : 'entitlement.ai_exhausted'
                  : undefined
              }
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
              globalThis.location.hash = '#home'
              setDestination('home')
              setLocationHash('#home')
            }}
            organizationApi={operatorOrganizationApi}
          />
        ) : showMarketingLanding ||
          showGuestMiraHome ||
          showDedicatedSignIn ? null : session.status === 'anonymous' ||
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
                  applyEntitlement={applyEntitlement}
                  authentication={authentication}
                  locale={locale}
                  localizationPolicy={localizationPolicy}
                  message={message}
                  onAccountSecurityOpenChange={setAccountSecurityOpen}
                  onSessionChange={setSession}
                  session={session}
                  showSubscriptionPanel={isLearnerSession}
                />
              )
            : null}

        </Suspense>

        {demoMode ? null : <LegalLinks message={message} />}
      </div>
    </ApplicationFrame>
    </div>
  )
}

interface SessionSummaryProps {
  accountSecurityOpen: boolean
  applyEntitlement: (entitlement: LearnerEntitlement) => void
  authentication: AuthenticationAdapter
  embedded?: boolean
  locale: SupportedLocale
  localizationPolicy: ResolutionPolicy
  message: (key: MessageKey) => InterfaceMessageResolution
  onAccountSecurityOpenChange: (open: boolean) => void
  onSessionChange: (session: SessionState) => void
  session: SessionState
  showSubscriptionPanel?: boolean
}

function SessionSummary({
  accountSecurityOpen,
  applyEntitlement,
  authentication,
  embedded = false,
  locale,
  localizationPolicy,
  message,
  onAccountSecurityOpenChange,
  onSessionChange,
  session,
  showSubscriptionPanel = false,
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
  const passwordAuth = isPasswordFormAuthentication(authentication)
    ? authentication
    : null
  const isAuthenticated = session.status === 'authenticated'

  return (
    <section
      className={`shell-session${
        passwordAuth !== null && !isAuthenticated ? ' shell-session--sign-in' : ''
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
      {passwordAuth !== null && !isAuthenticated ? (
        <WebPasswordSignInPanel
          authentication={passwordAuth}
          embedded={embedded}
          message={message}
          onAuthenticated={onSessionChange}
        />
      ) : null}
      {showSubscriptionPanel && isAuthenticated && accountSecurityOpen ? (
        <SubscriptionPanel
          locale={locale}
          localizationPolicy={localizationPolicy}
          onChange={applyEntitlement}
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
      {webAuth === null &&
      isAuthenticated &&
      isFederatedAccountAuthentication(authentication) &&
      isPasswordFormAuthentication(authentication) ? (
        <FederatedAccountsPanel
          authentication={authentication}
          message={message}
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
    typeof candidate.revokeAllSessions === 'function' &&
    typeof candidate.federatedProviders === 'function' &&
    typeof candidate.beginFederatedSignIn === 'function' &&
    typeof candidate.federatedLinks === 'function' &&
    typeof candidate.beginFederatedLink === 'function' &&
    typeof candidate.unlinkFederated === 'function'
  )
}

function operatorWorkspaceHashFromLocation(hash: string): boolean {
  const normalized = hash.replace(/^#/u, '')

  return (
    normalized.startsWith('operator-') ||
    normalized === 'operator-workspace-overview'
  )
}

function isMarketingLandingHash(hash: string): boolean {
  return hash === '#about' || hash === '#marketing'
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
