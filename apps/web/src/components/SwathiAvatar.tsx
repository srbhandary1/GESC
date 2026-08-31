import type { AnimationPayload } from '@global-english/contracts'
import { useCoachPreference } from '../coach/coachPreferenceContextValue'
import {
  CoachAvatar,
  type CoachAvatarProps,
} from './CoachAvatar'

export interface SwathiAvatarProps extends CoachAvatarProps {
  readonly payload?: AnimationPayload | null | unknown
}

export function SwathiAvatar(props: SwathiAvatarProps) {
  const { preference } = useCoachPreference()

  return (
    <CoachAvatar
      {...props}
      coachGender={preference.gender}
      coachName={preference.displayName}
    />
  )
}
