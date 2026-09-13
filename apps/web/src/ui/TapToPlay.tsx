import type { ReactNode } from 'react'
import { useState } from 'react'

export interface TapToPlayProps {
  readonly idleLabel: ReactNode
  readonly playingLabel: ReactNode
  readonly unavailableLabel: ReactNode
  readonly onPlay?: () => Promise<void> | void
}

/**
 * Compact listen chip for AI text. Wires to existing TTS when `onPlay`
 * is provided; otherwise announces that playback is not available yet.
 */
export function TapToPlay({
  idleLabel,
  playingLabel,
  unavailableLabel,
  onPlay,
}: TapToPlayProps) {
  const [playing, setPlaying] = useState(false)
  const [unavailable, setUnavailable] = useState(false)

  return (
    <div className="ge-tap-to-play">
      <button
        aria-busy={playing}
        className="ge-tap-to-play__button"
        disabled={playing}
        onClick={() => {
          if (onPlay === undefined) {
            setUnavailable(true)
            return
          }

          setUnavailable(false)
          setPlaying(true)
          void Promise.resolve(onPlay())
            .catch(() => {
              setUnavailable(true)
            })
            .finally(() => {
              setPlaying(false)
            })
        }}
        type="button"
      >
        <span aria-hidden="true" className="ge-tap-to-play__glyph">
          {playing ? '❚❚' : '▶'}
        </span>
        {playing ? playingLabel : idleLabel}
      </button>
      {unavailable ? (
        <p className="ge-tap-to-play__status" role="status">
          {unavailableLabel}
        </p>
      ) : null}
    </div>
  )
}
