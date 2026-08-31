import {
  normalizeVisemeTimestamps,
  visemeMouthShapeAt,
  type VisemeTimestamp,
} from '@global-english/contracts'
import { useEffect, useState } from 'react'

const REST_MOUTH_SHAPE = 'rest'

export interface UseVisemePlayerOptions {
  readonly audio: HTMLAudioElement | null
  readonly visemes: readonly VisemeTimestamp[] | null | undefined
}

export interface VisemePlayerState {
  readonly currentMouthShape: string
  readonly isAnimating: boolean
}

function visemeSignature(
  visemes: readonly VisemeTimestamp[] | null | undefined,
): string {
  return (visemes ?? [])
    .map((frame) => `${frame.timestamp_ms}:${frame.mouth_shape}`)
    .join('|')
}

export function useVisemePlayer({
  audio,
  visemes,
}: UseVisemePlayerOptions): VisemePlayerState {
  const [liveMouthShape, setLiveMouthShape] = useState(REST_MOUTH_SHAPE)
  const signature = visemeSignature(visemes)
  const canAnimate = audio !== null && signature !== ''
  const currentMouthShape = canAnimate ? liveMouthShape : REST_MOUTH_SHAPE

  useEffect(() => {
    if (audio === null || signature === '') {
      return
    }

    const frames = normalizeVisemeTimestamps(visemes ?? [])
    if (frames.length === 0) {
      return
    }

    let frameId = 0
    let cancelled = false

    const tick = () => {
      if (cancelled) {
        return
      }

      const timestampMs = Math.round(audio.currentTime * 1000)
      const next = audio.ended
        ? REST_MOUTH_SHAPE
        : visemeMouthShapeAt(frames, timestampMs)
      setLiveMouthShape((previous) => (previous === next ? previous : next))
      frameId = window.requestAnimationFrame(tick)
    }

    frameId = window.requestAnimationFrame(tick)

    return () => {
      cancelled = true
      window.cancelAnimationFrame(frameId)
    }
  }, [audio, signature, visemes])

  return {
    currentMouthShape,
    isAnimating: canAnimate && currentMouthShape !== REST_MOUTH_SHAPE,
  }
}
