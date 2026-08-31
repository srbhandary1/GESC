import type {
  MessageKey,
  ResolutionPolicy,
  SupportedLocale,
} from '@global-english/localization'
import { useEffect, useRef, useState, type ReactNode } from 'react'
import { resolveInterfaceMessage } from '../localization/interfaceLocalization'
import { LocalizedMessage } from '../localization/LocalizedMessage'
import './MeHub.css'

export interface MeHubProps {
  /** Role badge and organization line rendered at the top of the hub. */
  readonly accountStatus?: ReactNode
  /** Credential/security management panel (web sessions only). */
  readonly accountSecuritySection?: ReactNode
  /** Interface-language selector moved out of the header (ADR-0032). */
  readonly localeControl?: ReactNode
  readonly locale: SupportedLocale
  readonly localizationPolicy: ResolutionPolicy
  readonly onSignOut?: () => void
  readonly progressSection: ReactNode
  readonly tuitionSection: ReactNode
}

/**
 * The "Me" destination (ADR-0032). Absorbs the former Progress and 1:1
 * primary destinations plus account security, interface locale and sign
 * out. `#progress`, `#tuition`, `#one-to-one` and the account-security
 * deep link keep working by scrolling to the matching section.
 */
export function MeHub({
  accountStatus,
  accountSecuritySection,
  localeControl,
  locale,
  localizationPolicy,
  onSignOut,
  progressSection,
  tuitionSection,
}: MeHubProps) {
  const message = (key: MessageKey) =>
    resolveInterfaceMessage(key, locale, localizationPolicy)
  const tuitionRef = useRef<HTMLElement>(null)
  const securityRef = useRef<HTMLDetailsElement>(null)
  const [securityOpen, setSecurityOpen] = useState(
    () => globalThis.location?.hash === '#operator-account-settings-title',
  )

  useEffect(() => {
    const hash = globalThis.location?.hash
    if (hash === '#tuition' || hash === '#one-to-one') {
      tuitionRef.current?.scrollIntoView({ block: 'start' })
    } else if (hash === '#operator-account-settings-title') {
      securityRef.current?.scrollIntoView({ block: 'start' })
    }

    const onHashChange = () => {
      const nextHash = globalThis.location?.hash
      if (nextHash === '#tuition' || nextHash === '#one-to-one') {
        tuitionRef.current?.scrollIntoView({ block: 'start' })
        return
      }
      if (nextHash === '#operator-account-settings-title') {
        setSecurityOpen(true)
        securityRef.current?.scrollIntoView({ block: 'start' })
      }
    }

    globalThis.addEventListener('hashchange', onHashChange)

    return () => {
      globalThis.removeEventListener('hashchange', onHashChange)
    }
  }, [])

  return (
    <div className="me-hub">
      {accountStatus == null ? null : (
        <section className="me-hub__section me-hub__section--account">
          {accountStatus}
        </section>
      )}
      <section
        aria-labelledby="me-progress-title"
        className="me-hub__section"
        id="me-progress"
      >
        <h2 className="me-hub__section-title" id="me-progress-title">
          <LocalizedMessage resolution={message('page.progress.title')} />
        </h2>
        {progressSection}
      </section>
      <section
        aria-labelledby="me-tuition-title"
        className="me-hub__section"
        id="me-tuition"
        ref={tuitionRef}
      >
        <h2 className="me-hub__section-title" id="me-tuition-title">
          <LocalizedMessage resolution={message('page.tuition.title')} />
        </h2>
        {tuitionSection}
      </section>
      {accountSecuritySection == null ? null : (
        <details
          className="me-hub__section me-hub__section--security"
          id="me-account-security"
          open={securityOpen}
          onToggle={(event) => {
            setSecurityOpen(event.currentTarget.open)
          }}
          ref={securityRef}
        >
          <summary className="me-hub__section-title">
            <LocalizedMessage resolution={message('session.account_menu')} />
          </summary>
          {accountSecuritySection}
        </details>
      )}
      {localeControl == null ? null : (
        <section className="me-hub__section" id="me-locale">
          {localeControl}
        </section>
      )}
      {onSignOut === undefined ? null : (
        <section className="me-hub__section me-hub__section--sign-out">
          <button
            className="ge-button ge-button--quiet me-hub__sign-out"
            onClick={onSignOut}
            type="button"
          >
            <LocalizedMessage
              resolution={message('session.android_sign_out')}
            />
          </button>
        </section>
      )}
    </div>
  )
}
