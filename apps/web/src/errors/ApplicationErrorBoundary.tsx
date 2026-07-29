import { MixedLanguageText } from '@global-english/design-system'
import {
  DEFAULT_LOCALE,
  SUPPORTED_LOCALES,
  type MessageKey,
  type ResolutionPolicy,
  type SupportedLocale,
} from '@global-english/localization'
import {
  Component,
  useEffect,
  useRef,
  type ReactNode,
} from 'react'
import './ApplicationErrorBoundary.css'
import {
  INTERFACE_RESOLUTION_POLICY,
  resolveInterfaceMessage,
  useInterfaceFallbackReporting,
} from '../localization/interfaceLocalization'

export type ApplicationErrorEvent = Readonly<{
  category: 'react_render_failure'
}>

type ApplicationErrorBoundaryProps = {
  children: ReactNode
  localizationPolicy?: ResolutionPolicy
  onCaught?: (event: ApplicationErrorEvent) => void
  onReload?: () => void
}

type ApplicationErrorBoundaryState = {
  hasError: boolean
}

type ApplicationErrorFallbackProps = {
  localizationPolicy: ResolutionPolicy
  onReload: () => void
}

const defaultReload = () => {
  window.location.reload()
}

function ApplicationErrorFallback({
  localizationPolicy,
  onReload,
}: ApplicationErrorFallbackProps) {
  const titleRef = useRef<HTMLHeadingElement>(null)
  const locale = activeRequestedLocale()
  const title = localizedErrorMessage(
    'error.title',
    locale,
    localizationPolicy,
  )
  const message = localizedErrorMessage(
    'error.message',
    locale,
    localizationPolicy,
  )
  const reload = localizedErrorMessage(
    'error.reload',
    locale,
    localizationPolicy,
  )
  const fallbackLabel = localizedErrorMessage(
    'localization.fallback_label',
    locale,
    localizationPolicy,
  )
  const fallbackNotice = localizedErrorMessage(
    'localization.fallback_notice',
    locale,
    localizationPolicy,
  )

  useInterfaceFallbackReporting(
    title.usedFallback
      ? [title, message, reload, fallbackLabel, fallbackNotice]
      : [title, message, reload],
  )

  useEffect(() => {
    titleRef.current?.focus()
  }, [])

  useEffect(() => {
    const previousLocale = document.documentElement.lang
    const previousRequestedLocale =
      document.documentElement.dataset.requestedLocale
    document.documentElement.lang = title.resolvedLocale
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
  }, [locale, title.resolvedLocale])

  return (
    <main
      className="application-error"
      aria-labelledby="application-error-title"
    >
      <h1
        className="application-error__title"
        id="application-error-title"
        ref={titleRef}
        tabIndex={-1}
      >
        <MixedLanguageText
          segments={[
            {
              locale: title.resolvedLocale,
              text: title.text,
            },
          ]}
        />
      </h1>
      {title.usedFallback ? (
        <p
          className="application-error__fallback"
          role="status"
          lang={fallbackNotice.resolvedLocale}
        >
          <strong>
            <MixedLanguageText
              segments={[
                {
                  locale: fallbackLabel.resolvedLocale,
                  text: fallbackLabel.text,
                },
              ]}
            />
          </strong>{' '}
          <MixedLanguageText
            segments={[
              {
                locale: fallbackNotice.resolvedLocale,
                text: fallbackNotice.text,
              },
            ]}
          />
        </p>
      ) : null}
      <p className="application-error__message" role="alert">
        <MixedLanguageText
          segments={[
            {
              locale: message.resolvedLocale,
              text: message.text,
            },
          ]}
        />
      </p>
      <button
        className="application-error__reload"
        type="button"
        onClick={onReload}
      >
        <MixedLanguageText
          segments={[
            {
              locale: reload.resolvedLocale,
              text: reload.text,
            },
          ]}
        />
      </button>
    </main>
  )
}

function activeRequestedLocale(): SupportedLocale {
  const documentLocale =
    typeof document === 'undefined'
      ? undefined
      : (document.documentElement.dataset.requestedLocale ??
        document.documentElement.lang)

  return (
    SUPPORTED_LOCALES.find((locale) => locale === documentLocale) ??
    DEFAULT_LOCALE
  )
}

function localizedErrorMessage(
  key: MessageKey,
  locale: SupportedLocale,
  policy: ResolutionPolicy,
) {
  return resolveInterfaceMessage(key, locale, policy)
}

export class ApplicationErrorBoundary extends Component<
  ApplicationErrorBoundaryProps,
  ApplicationErrorBoundaryState
> {
  state: ApplicationErrorBoundaryState = {
    hasError: false,
  }

  static getDerivedStateFromError(): ApplicationErrorBoundaryState {
    return { hasError: true }
  }

  componentDidCatch(): void {
    this.props.onCaught?.({ category: 'react_render_failure' })
  }

  render(): ReactNode {
    if (this.state.hasError) {
      return (
        <ApplicationErrorFallback
          localizationPolicy={
            this.props.localizationPolicy ?? INTERFACE_RESOLUTION_POLICY
          }
          onReload={this.props.onReload ?? defaultReload}
        />
      )
    }

    return this.props.children
  }
}
