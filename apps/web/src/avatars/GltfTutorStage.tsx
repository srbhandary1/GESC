import { useEffect, useRef } from 'react'
import type { AvatarGesture } from '@global-english/contracts'
import type { CoachGender } from '../coach/coachPreferences'

export function GltfTutorStage({
  gender,
  gesture,
  mouthShape,
  onFailure,
  speaking,
}: {
  readonly gender: CoachGender
  readonly gesture: AvatarGesture | null
  readonly mouthShape: string
  readonly onFailure: () => void
  readonly speaking: boolean
}) {
  const canvasRef = useRef<HTMLCanvasElement>(null)
  const onFailureRef = useRef(onFailure)
  const stateRef = useRef({ gesture, mouthShape, speaking })

  useEffect(() => {
    onFailureRef.current = onFailure
    stateRef.current = { gesture, mouthShape, speaking }
  }, [gesture, mouthShape, onFailure, speaking])

  useEffect(() => {
    const canvas = canvasRef.current
    if (canvas === null) {
      return
    }

    const reducedMotion =
      typeof window.matchMedia === 'function' &&
      window.matchMedia('(prefers-reduced-motion: reduce)').matches
    const abort = new AbortController()

    void import('./mountGltfTutor')
      .then(({ mountGltfTutor }) =>
        mountGltfTutor({
          canvas,
          gender,
          reducedMotion,
          signal: abort.signal,
          getGesture: () => stateRef.current.gesture,
          getMouthShape: () => stateRef.current.mouthShape,
          getSpeaking: () => stateRef.current.speaking,
        }),
      )
      .catch(() => {
        if (!abort.signal.aborted) {
          onFailureRef.current()
        }
      })

    return () => abort.abort()
  }, [gender])

  return (
    <canvas
      aria-hidden="true"
      className="block h-full w-full"
      data-testid="interactive-avatar-mouth"
      ref={canvasRef}
    />
  )
}
