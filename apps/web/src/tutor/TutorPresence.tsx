import type {
  MessageKey,
  ResolutionPolicy,
  SupportedLocale,
} from '@global-english/localization'
import { InteractiveAvatar } from '../components/InteractiveAvatar'
import { coachDisplayName } from '../coach/coachPreferences'
import { useCoachPreference } from '../coach/coachPreferenceContextValue'
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
}

function statusMessageKey(mood: TutorMood): MessageKey {
  switch (mood) {
    case 'listening':
      return 'tutor.listening'
    case 'speaking':
      return 'tutor.speaking'
    case 'processing':
      return 'tutor.processing'
    case 'preparing':
      return 'tutor.preparing'
    default:
      return 'tutor.ready'
  }
}

export function TutorPresence({
  locale = 'kn-IN',
  localizationPolicy = 'approved-only',
  mood = 'idle',
  size = 'md',
  showName = true,
}: TutorPresenceProps) {
  const { preference } = useCoachPreference()
  const message = (key: MessageKey) =>
    resolveInterfaceMessage(key, locale, localizationPolicy)
  const displayName = coachDisplayName(
    preference,
    message('coach.female.name').text,
    message('coach.male.name').text,
  )

  return (
    <InteractiveAvatar
      caption={showName ? <LocalizedMessage resolution={message(statusMessageKey(mood))} /> : null}
      coachGender={preference.gender}
      locale={locale}
      name={displayName}
      presence={mood}
      showName={showName}
      size={size}
    />
  )
}

export function TutorHomeHero({
  locale,
  localizationPolicy,
}: {
  readonly locale: SupportedLocale
  readonly localizationPolicy: ResolutionPolicy
}) {
  const { preference } = useCoachPreference()
  const message = (key: MessageKey) =>
    resolveInterfaceMessage(key, locale, localizationPolicy)
  const displayName = coachDisplayName(
    preference,
    message('coach.female.name').text,
    message('coach.male.name').text,
  )

  return (
    <section
      className="tutor-home-hero tutor-home-hero--compact"
      aria-labelledby="tutor-home-hero-title"
      lang={locale}
    >
      <div className="tutor-home-hero__art" aria-hidden="true">
        <InteractiveAvatar
          coachGender={preference.gender}
          showName={false}
          size="sm"
        />
      </div>
      <div className="tutor-home-hero__copy">
        <p className="tutor-home-hero__eyebrow">{displayName}</p>
        <h2 id="tutor-home-hero-title">
          <LocalizedMessage resolution={message('tutor.greeting')} />
        </h2>
      </div>
    </section>
  )
}
