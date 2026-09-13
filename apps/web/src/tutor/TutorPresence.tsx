import type {
  MessageKey,
  ResolutionPolicy,
  SupportedLocale,
} from '@global-english/localization'
import { LocalizedMessage } from '../localization/LocalizedMessage'
import { resolveInterfaceMessage } from '../localization/interfaceLocalization'
import './TutorPresence.css'

export type TutorMood =
  | 'idle'
  | 'listening'
  | 'processing'
  | 'preparing'
  | 'speaking'

export interface TutorPresenceProps {
  readonly locale?: SupportedLocale
  readonly localizationPolicy?: ResolutionPolicy
  readonly mood?: TutorMood
  readonly size?: 'sm' | 'md' | 'lg' | 'hero'
  readonly showName?: boolean
  /** Accepted for local soft-launch callers; ignored by this chrome build. */
  readonly enableAvatar3d?: boolean
  readonly avatarPlayback?: unknown
}

export function TutorPresence({
  locale = 'kn-IN',
  localizationPolicy = 'approved-only',
  mood = 'idle',
  size = 'md',
  showName = true,
}: TutorPresenceProps) {
  const message = (key: MessageKey) =>
    resolveInterfaceMessage(key, locale, localizationPolicy)
  const portrait =
    mood === 'speaking' ? '/tutor/tutor-maya-speak.png' : '/tutor/tutor-maya.png'

  return (
    <figure
      className={`tutor-presence tutor-presence--${size} tutor-presence--${mood}`}
      data-mood={mood}
    >
      <span className="tutor-presence__orbit" aria-hidden="true" />
      <span className="tutor-presence__orbit tutor-presence__orbit--delay" aria-hidden="true" />
      <img
        alt=""
        className="tutor-presence__face"
        height={size === 'hero' ? 320 : 160}
        src={portrait}
        width={size === 'hero' ? 320 : 160}
      />
      {showName ? (
        <figcaption className="tutor-presence__caption">
          <strong>
            <LocalizedMessage resolution={message('tutor.name')} />
          </strong>
          <span>
            <LocalizedMessage
              resolution={message(
                mood === 'listening'
                  ? 'tutor.listening'
                  : mood === 'speaking'
                    ? 'tutor.speaking'
                    : mood === 'processing'
                      ? 'tutor.processing'
                      : mood === 'preparing'
                        ? 'tutor.preparing'
                        : 'tutor.ready',
              )}
            />
          </span>
        </figcaption>
      ) : null}
    </figure>
  )
}

export function TutorHomeHero({
  locale,
  localizationPolicy,
}: {
  readonly locale: SupportedLocale
  readonly localizationPolicy: ResolutionPolicy
}) {
  const message = (key: MessageKey) =>
    resolveInterfaceMessage(key, locale, localizationPolicy)

  return (
    <section
      className="tutor-home-hero"
      aria-labelledby="tutor-home-hero-title"
      lang={locale}
    >
      <div className="tutor-home-hero__copy">
        <p className="tutor-home-hero__eyebrow">
          <LocalizedMessage resolution={message('tutor.name')} />
        </p>
        <h2 id="tutor-home-hero-title">
          <LocalizedMessage resolution={message('tutor.greeting')} />
        </h2>
        <p>
          <LocalizedMessage
            resolution={message('level.path.talk_after_listen')}
          />
        </p>
        <a className="tutor-home-hero__cta" href="#learn">
          <LocalizedMessage resolution={message('page.home.continue_practice')} />
        </a>
      </div>
    </section>
  )
}
