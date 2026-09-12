import { AsyncError, SkeletonStatus } from '@global-english/design-system'
import type { MessageKey } from '@global-english/localization'
import { useRef } from 'react'
import { LocalizedMessage } from '../localization/LocalizedMessage'
import type { InterfaceMessageResolution } from '../localization/interfaceLocalization'
import { AvatarSlot } from '../ui/AvatarSlot'

export interface PreSignInSplashProps {
  readonly canStartAuthorization: boolean
  readonly message: (key: MessageKey) => InterfaceMessageResolution
  readonly onRetry: () => void
  readonly onSignIn: () => void
  readonly status: 'checking' | 'anonymous' | 'unavailable'
}

export function PreSignInSplash({
  canStartAuthorization,
  message,
  onRetry,
  onSignIn,
  status,
}: PreSignInSplashProps) {
  const sessionDetails = useRef<HTMLDetailsElement>(null)
  const teacherLabel = message('shell.avatar.teacher').text

  return (
    <section
      aria-labelledby="splash-title"
      className="mx-auto grid max-w-md justify-items-center gap-5 py-6 text-center"
      data-pre-sign-in={status}
    >
      <AvatarSlot label={teacherLabel} slot="home-teacher" />
      <h1
        className="max-w-sm text-balance text-3xl font-extrabold tracking-tight text-ink"
        id="splash-title"
        tabIndex={-1}
      >
        <LocalizedMessage resolution={message('shell.splash.tagline')} />
      </h1>

      {status === 'checking' ? (
        <SkeletonStatus
          label={<LocalizedMessage resolution={message('session.checking')} />}
        />
      ) : null}

      {status === 'anonymous' ? (
        <button
          className="ge-button ge-button--primary min-h-target min-w-target px-8"
          onClick={() => {
            if (canStartAuthorization) {
              onSignIn()
              return
            }

            if (sessionDetails.current) {
              sessionDetails.current.open = true
            }
          }}
          type="button"
        >
          <LocalizedMessage resolution={message('session.sign_in')} />
        </button>
      ) : null}

      {status === 'unavailable' ? (
        <AsyncError
          actionLabel={
            <LocalizedMessage resolution={message('session.retry')} />
          }
          onRetry={onRetry}
          title={<LocalizedMessage resolution={message('session.unavailable')} />}
        >
          <LocalizedMessage resolution={message('session.unavailable')} />
        </AsyncError>
      ) : null}

      {status === 'anonymous' ? (
        <details
          className="w-full max-w-sm rounded-lg border border-line/70 bg-surface p-3 text-start text-sm text-muted"
          ref={sessionDetails}
        >
          <summary className="min-h-target cursor-pointer font-semibold text-ink">
            <LocalizedMessage resolution={message('session.details')} />
          </summary>
          <p className="mt-2" role="status">
            <LocalizedMessage resolution={message('session.anonymous')} />
          </p>
        </details>
      ) : null}
    </section>
  )
}
