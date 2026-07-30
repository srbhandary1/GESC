import {
  ApplicationFrame,
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
import type {
  AuthenticationAdapter,
  AuthenticationSnapshot,
} from './auth/AuthenticationAdapter'
import { defaultAuthentication } from './auth/defaultAuthentication'
import {
  INTERFACE_RESOLUTION_POLICY,
  resolveInterfaceMessage,
  useInterfaceFallbackReporting,
  type InterfaceMessageResolution,
} from './localization/interfaceLocalization'
import { LocalizedMessage } from './localization/LocalizedMessage'
import type { AudioRecorder } from './learner/BrowserAudioRecorder'
import { LearnerJourney } from './learner/LearnerJourney'
import type { LearnerJourneyApi } from './learner/LearnerJourneyApi'
import { defaultLearnerJourneyApi } from './learner/WebLearnerJourneyApi'
import { browserOfflineLearningStore } from './offline/BrowserOfflineLearningStore'
import type {
  OfflineLearningStore,
  OfflinePersonalDeviceResume,
  OfflineProfileBinding,
} from './offline/OfflineLearningStore'

type SessionState =
  | AuthenticationSnapshot
  | { status: 'checking' }
  | { status: 'unavailable' }

interface AppProps {
  authentication?: AuthenticationAdapter
  audioRecorder?: AudioRecorder
  createClientId?: () => string
  learnerJourneyApi?: LearnerJourneyApi
  localizationPolicy?: ResolutionPolicy
  offlineStore?: OfflineLearningStore
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
  practice: {
    description: 'page.practice.description',
    title: 'page.practice.title',
  },
  progress: {
    description: 'page.progress.description',
    title: 'page.progress.title',
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
  createClientId,
  learnerJourneyApi = defaultLearnerJourneyApi,
  localizationPolicy = INTERFACE_RESOLUTION_POLICY,
  offlineStore = browserOfflineLearningStore,
}: AppProps) {
  const [session, setSession] = useState<SessionState>({
    status: 'checking',
  })
  const [locale, setLocale] = useState<SupportedLocale>(DEFAULT_LOCALE)
  const [offlineResume, setOfflineResume] =
    useState<OfflinePersonalDeviceResume | null>(null)
  const [announcedLocale, setAnnouncedLocale] =
    useState<SupportedLocale | null>(null)
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
      destinationTitle.current?.focus()
      previousDestination.current = destination
    }
  }, [destination])

  const brand = resolveInterfaceMessage(
    'app.name',
    'en-IN',
    localizationPolicy,
  )
  const navigationLabel = message('shell.navigation_label')
  const skipToContent = message('shell.skip_to_content')
  const fallbackLabel = message('localization.fallback_label')
  const fallbackNotice = message('localization.fallback_notice')
  const previewLabel = message('localization.preview_label')
  const previewNotice = message('localization.preview_notice')
  const routeMessages = destinationMessages[destination]
  const title = message(routeMessages.title)
  const description = message(routeMessages.description)
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
      label: <LocalizedMessage resolution={message('nav.practice')} />,
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
  const canLearn =
    destination === 'learn' &&
    ((session.status === 'authenticated' &&
      session.identity.current_membership?.membership_type === 'learner') ||
      offlineResume !== null)

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
    <ApplicationFrame
      brandLabel={brand.text}
      brandLabelLocale={brand.resolvedLocale}
      currentDestination={destination}
      headerActions={
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
              const option = resolveInterfaceMessage(
                localeMessageKeys[supported],
                locale,
                localizationPolicy,
              )

              return (
                <option
                  key={supported}
                  lang={option.resolvedLocale}
                  value={supported}
                >
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
      }
      navigationLabel={navigationLabel.text}
      navigationLabelLocale={navigationLabel.resolvedLocale}
      navigationLinks={navigationLinks}
      skipToContentLabel={
        <LocalizedMessage resolution={skipToContent} />
      }
    >
      <div className="shell-content">
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

        <section
          className="shell-introduction"
          aria-labelledby="destination-title"
        >
          <p className="shell-eyebrow">
            <LocalizedMessage resolution={message('shell.eyebrow')} />
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

        {canLearn ? (
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
        ) : (
          <p className="shell-checkpoint-notice">
            <LocalizedMessage
              resolution={message('shell.checkpoint_notice')}
            />
          </p>
        )}

        <SessionSummary
          message={message}
          session={session}
        />
      </div>
    </ApplicationFrame>
  )
}

interface SessionSummaryProps {
  message: (key: MessageKey) => InterfaceMessageResolution
  session: SessionState
}

function SessionSummary({ message, session }: SessionSummaryProps) {
  const statusMessage = sessionMessageKey(session)
  const organization =
    session.status === 'authenticated'
      ? session.identity.current_membership?.organization.name
      : undefined

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

  if (
    candidate === 'home' ||
    candidate === 'learn' ||
    candidate === 'practice' ||
    candidate === 'progress'
  ) {
    return candidate
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
