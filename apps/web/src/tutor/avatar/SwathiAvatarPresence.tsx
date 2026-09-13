import { Suspense, lazy, useMemo } from 'react'
import type { TutorMood } from '../TutorPresence'
import { Avatar3dBoundary } from './Avatar3dBoundary'
import type { AvatarPlaybackState } from './types'
import { resolveVisemeAtTime, visemeMouthShape } from './visemeMapping'

const SwathiAvatarCanvas = lazy(async () => ({
  default: (await import('./SwathiAvatarCanvas')).SwathiAvatarCanvas,
}))

export interface SwathiAvatarPresenceProps {
  readonly facingY?: number
  readonly glbPath: string
  readonly mood: TutorMood
  readonly rigType?: 'static' | 'skinned'
  readonly size: 'sm' | 'md' | 'lg' | 'hero'
  readonly avatarPlayback?: AvatarPlaybackState | null
}

export function SwathiAvatarPresence({
  facingY,
  glbPath,
  mood,
  rigType,
  size,
  avatarPlayback = null,
}: SwathiAvatarPresenceProps) {
  const visemeId = useMemo(() => {
    if (avatarPlayback === null) {
      return mood === 'speaking' ? 6 : 0
    }

    return resolveVisemeAtTime(
      avatarPlayback.timeline,
      avatarPlayback.timeMs,
    )
  }, [avatarPlayback, mood])
  const mouth = visemeMouthShape(visemeId)
  const placeholder = (
    <div
      aria-hidden="true"
      className={`tutor-presence__avatar tutor-presence__avatar--loading tutor-presence__avatar--${size}`}
    />
  )

  return (
    <Avatar3dBoundary fallback={placeholder}>
      <Suspense fallback={placeholder}>
        <SwathiAvatarCanvas
          className={`tutor-presence__avatar tutor-presence__avatar--${size}`}
          facingY={facingY}
          glbPath={glbPath}
          mood={mood}
          mouth={mouth}
          rigType={rigType}
          visemeId={visemeId}
        />
      </Suspense>
    </Avatar3dBoundary>
  )
}
