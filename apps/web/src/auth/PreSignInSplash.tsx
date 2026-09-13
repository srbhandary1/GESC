import { AsyncError, SkeletonStatus } from '@global-english/design-system'
import type { MessageKey } from '@global-english/localization'
import { useRef } from 'react'
import { LocalizedMessage } from '../localization/LocalizedMessage'
import type { InterfaceMessageResolution } from '../localization/interfaceLocalization'
import { AvatarSlot } from '../ui/AvatarSlot'
import { GroveMark } from '../ui/avatars/GrovePortraits'

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
      className="mx-auto grid w-full min-w-0 max-w-full justify-items-center gap-5 py-6 text-center"
      data-pre-sign-in={status}
    >
      <GroveMark className="h-14 w-14" />
      <AvatarSlot label={teacherLabel} size="lg" slot="home-teacher" />
      <p className="text-sm font-bold tracking-[0.14em] text-action uppercase">
        <LocalizedMessage resolution={message('shell.splash.brand')} />
      </p>
      <h1
        className="w-full min-w-0 text-balance text-2xl font-extrabold tracking-tight text-ink [overflow-wrap:anywhere] sm:text-3xl"
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
          className="ge-button ge-button--primary min-h-target min-w-target px-6"
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
          className="w-full min-w-0 rounded-lg border border-line/70 bg-surface p-3 text-start text-sm text-muted"
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
