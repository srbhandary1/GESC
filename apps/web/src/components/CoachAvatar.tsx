import {
  parseAnimationPayload,
  type AnimationPayload,
} from '@global-english/contracts'
import type { MessageKey } from '@global-english/localization'
import { Component, type ErrorInfo, type ReactNode } from 'react'
import {
  coachDisplayName,
  type CoachGender,
} from '../coach/coachPreferences'
import { LocalizedMessage } from '../localization/LocalizedMessage'
import { resolveInterfaceMessage } from '../localization/interfaceLocalization'
import type { TutorPresenceProps } from '../tutor/TutorPresence'
import {
  InteractiveAvatar,
  type InteractiveAvatarPresence,
} from './InteractiveAvatar'

const IDLE_ANIMATION: AnimationPayload = {
  viseme_timestamps: [],
  avatar_gesture_trigger: null,
}

export interface CoachAvatarProps extends TutorPresenceProps {
  readonly audio?: HTMLAudioElement | null
  readonly payload?: AnimationPayload | null | unknown
  readonly coachGender?: CoachGender
  readonly coachName?: string | null
}

type CoachAvatarErrorBoundaryProps = {
  readonly fallback: ReactNode
  readonly children: ReactNode
}

type CoachAvatarErrorBoundaryState = {
  readonly hasError: boolean
}

class CoachAvatarErrorBoundary extends Component<
  CoachAvatarErrorBoundaryProps,
  CoachAvatarErrorBoundaryState
> {
  state: CoachAvatarErrorBoundaryState = {
    hasError: false,
  }

  static getDerivedStateFromError(): CoachAvatarErrorBoundaryState {
    return { hasError: true }
  }

  componentDidCatch(_error: Error, _info: ErrorInfo): void {
    void _error
    void _info
  }

  render(): ReactNode {
    if (this.state.hasError) {
      return this.props.fallback
    }

    return this.props.children
  }
}

/** `CoachAvatar`'s own `mood` reflects UI state (recording, waiting on a
 * reply, ...), independent of the `InteractiveAvatar` viseme rig's
 * `isSpeaking`, which only turns on once real audio + visemes are
 * playing. Both feed the ring's cosmetic "presence" state. */
function moodToPresence(mood: TutorPresenceProps['mood']): InteractiveAvatarPresence {
  switch (mood) {
    case 'listening':
    case 'processing':
    case 'preparing':
    case 'speaking':
      return mood
    default:
      return 'idle'
  }
}

const STATIC_BADGE_SIZE_CLASSES: Readonly<Record<NonNullable<TutorPresenceProps['size']>, string>> = {
  sm: 'w-14 sm:w-16',
  md: 'w-28 sm:w-32',
  lg: 'w-44 sm:w-48',
  hero: 'w-56 sm:w-64 lg:w-72',
}

/**
 * Deliberately independent of `InteractiveAvatar`: a plain initial badge
 * with no SVG rig, no viseme parsing, and no gesture logic. If the rig
 * itself is what failed, re-rendering it inside the error boundary's own
 * fallback would just fail again; this fallback shares no code path with
 * it, so it stays renderable even when the rig's assumptions break.
 */
function StaticCoachBadge({
  displayName,
  showName,
  size,
}: {
  readonly displayName: string
  readonly showName: boolean
  readonly size: NonNullable<TutorPresenceProps['size']>
}) {
  const initial = displayName.trim().charAt(0).toUpperCase() || '?'

  return (
    <figure
      className={`flex flex-col items-center gap-2 ${STATIC_BADGE_SIZE_CLASSES[size]}`}
      data-testid="interactive-avatar-fallback"
    >
      <div
        aria-hidden="true"
        className="flex aspect-square w-full items-center justify-center rounded-full border-[3px] border-white/90 bg-gradient-to-b from-[#cfd8ea] to-[#9fb0d1] text-2xl font-bold text-[#1c2a45] shadow-[0_0_0_6px_rgba(46,230,255,0.22),0_18px_40px_rgba(7,16,24,0.28)]"
      >
        {initial}
      </div>
      {showName ? (
        <figcaption
          className="max-w-full truncate text-sm font-semibold text-[#f2eefe]"
          title={displayName}
        >
          {displayName}
        </figcaption>
      ) : null}
    </figure>
  )
}

export function CoachAvatar({
  audio = null,
  payload = null,
  locale = 'kn-IN',
  localizationPolicy = 'approved-only',
  mood = 'idle',
  size = 'md',
  showName = true,
  coachGender = 'female',
  coachName = null,
}: CoachAvatarProps) {
  const message = (key: MessageKey) =>
    resolveInterfaceMessage(key, locale, localizationPolicy)
  const femaleDefault = message('coach.female.name').text
  const maleDefault = message('coach.male.name').text
  const displayName = coachDisplayName(
    { gender: coachGender, displayName: coachName },
    femaleDefault,
    maleDefault,
  )
  const statusKey =
    mood === 'listening'
      ? 'tutor.listening'
      : mood === 'speaking'
        ? 'tutor.speaking'
        : mood === 'processing'
          ? 'tutor.processing'
          : mood === 'preparing'
            ? 'tutor.preparing'
            : 'tutor.ready'
  const animation = parseAnimationPayload(payload) ?? IDLE_ANIMATION

  const fallback = (
    <StaticCoachBadge displayName={displayName} showName={showName} size={size} />
  )

  return (
    <CoachAvatarErrorBoundary fallback={fallback}>
      <InteractiveAvatar
        audio={audio}
        caption={showName ? <LocalizedMessage resolution={message(statusKey)} /> : null}
        coachGender={coachGender}
        gesture={animation.avatar_gesture_trigger}
        locale={locale}
        name={displayName}
        presence={moodToPresence(mood)}
        showName={showName}
        size={size}
        visemeTimestamps={animation.viseme_timestamps}
      />
    </CoachAvatarErrorBoundary>
  )
}
