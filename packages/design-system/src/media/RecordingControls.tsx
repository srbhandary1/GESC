import { useRef } from 'react'

export type RecordingControlState =
  | 'idle'
  | 'ready'
  | 'requesting-permission'
  | 'recording'
  | 'paused'
  | 'processing'
  | 'blocked'
  | 'error'

export type RecordingCaptureMode = 'tap' | 'hold'

export interface RecordingControlLabels {
  region: string
  start: string
  pause: string
  resume: string
  stop: string
  cancel: string
  idle: string
  ready: string
  requestingPermission: string
  recording: string
  paused: string
  processing: string
  blocked: string
  error: string
  hold?: string
  holding?: string
  release?: string
}

export interface RecordingControlsProps {
  labels: RecordingControlLabels
  state: RecordingControlState
  captureMode?: RecordingCaptureMode
  interactionDisabled?: boolean
  statusOverride?: string
  onRequestCancel: () => void
  onRequestPause: () => void
  onRequestResume: () => void
  onRequestStart: () => void
  onRequestStop: () => void
}

export function RecordingControls({
  labels,
  state,
  captureMode = 'tap',
  interactionDisabled = false,
  statusOverride,
  onRequestCancel,
  onRequestPause,
  onRequestResume,
  onRequestStart,
  onRequestStop,
}: RecordingControlsProps) {
  const statusLabel = statusOverride ?? {
    idle: labels.idle,
    ready: labels.ready,
    'requesting-permission': labels.requestingPermission,
    recording: labels.recording,
    paused: labels.paused,
    processing: labels.processing,
    blocked: labels.blocked,
    error: labels.error,
  }[state]

  const canStart =
    !interactionDisabled &&
    (state === 'idle' ||
      state === 'ready' ||
      (captureMode === 'hold' && (state === 'error' || state === 'blocked')))
  const isRecording = state === 'recording'
  const isPaused = state === 'paused'
  const holdActive = useRef(false)
  const tapLatched = useRef(false)
  const pointerStartedAt = useRef<number | null>(null)
  const pointerStartedWhileRecording = useRef(false)

  // A quick tap is a deliberate mobile fallback: it starts recording and a
  // second tap sends. A sustained press keeps the original hold-to-talk
  // behaviour. This avoids Android touch releases creating unusably short
  // clips while preserving the faster press-and-release gesture.
  const TAP_TO_TOGGLE_THRESHOLD_MS = 500

  const endHold = () => {
    if (!holdActive.current) {
      return
    }

    holdActive.current = false
    onRequestStop()
  }

  const beginHold = () => {
    if (interactionDisabled || state === 'processing') {
      return
    }

    if (
      tapLatched.current &&
      (isRecording || state === 'requesting-permission')
    ) {
      tapLatched.current = false
      holdActive.current = false
      onRequestStop()
      return
    }

    if (isRecording || state === 'requesting-permission') {
      holdActive.current = true
      return
    }

    if (!canStart) {
      return
    }

    holdActive.current = true
    onRequestStart()
  }

  const endPointerHold = () => {
    const startedAt = pointerStartedAt.current
    const startedWhileRecording = pointerStartedWhileRecording.current
    pointerStartedAt.current = null
    pointerStartedWhileRecording.current = false

    const elapsed =
      startedAt === null
        ? Number.POSITIVE_INFINITY
        : performance.now() - startedAt
    if (!startedWhileRecording && elapsed < TAP_TO_TOGGLE_THRESHOLD_MS) {
      holdActive.current = false
      tapLatched.current = true
      return
    }

    tapLatched.current = false
    endHold()
  }

  if (captureMode === 'hold') {
    const holdPressed =
      isRecording || state === 'requesting-permission'
    const holdLabel = isRecording
      ? (labels.release ?? labels.stop)
      : state === 'requesting-permission'
        ? (labels.holding ?? labels.requestingPermission)
        : state === 'processing'
          ? labels.processing
          : (labels.hold ?? labels.start)

    return (
      <section
        aria-label={labels.region}
        className="ge-media-controls ge-media-controls--hold"
      >
        <p
          aria-live="polite"
          className="ge-media-controls__status"
        >
          {statusLabel}
        </p>
        {state === 'blocked' && captureMode !== 'hold' ? null : (
          <div className="ge-media-controls__actions">
            <button
              aria-pressed={holdPressed}
              className="ge-button ge-button--primary ge-button--speak ge-button--hold-to-talk"
              data-hold-state={state}
              aria-disabled={interactionDisabled || undefined}
              disabled={interactionDisabled || state === 'processing'}
              onContextMenu={(event) => {
                event.preventDefault()
              }}
              onKeyDown={(event) => {
                if (event.key !== ' ' && event.key !== 'Enter') {
                  return
                }

                if (event.repeat) {
                  return
                }

                event.preventDefault()
                beginHold()
              }}
              onKeyUp={(event) => {
                if (event.key !== ' ' && event.key !== 'Enter') {
                  return
                }

                event.preventDefault()
                endHold()
              }}
              onLostPointerCapture={endHold}
              onPointerCancel={() => {
                pointerStartedAt.current = null
                pointerStartedWhileRecording.current = false
                tapLatched.current = false
                endHold()
              }}
              onPointerDown={(event) => {
                if (event.button !== 0 || interactionDisabled) {
                  return
                }

                event.preventDefault()
                if (typeof event.currentTarget.setPointerCapture === 'function') {
                  try {
                    event.currentTarget.setPointerCapture(event.pointerId)
                  } catch {
                    // jsdom and some WebViews omit pointer capture.
                  }
                }
                pointerStartedAt.current = performance.now()
                pointerStartedWhileRecording.current =
                  isRecording || state === 'requesting-permission'
                beginHold()
              }}
              onPointerUp={endPointerHold}
              type="button"
            >
              <span aria-hidden="true" className="ge-button__record-dot" />
              <span>{holdLabel}</span>
            </button>
            {isRecording ||
            state === 'processing' ||
            state === 'requesting-permission' ? (
              <button
                className="ge-button ge-button--quiet"
                onClick={onRequestCancel}
                type="button"
              >
                {labels.cancel}
              </button>
            ) : null}
          </div>
        )}
      </section>
    )
  }

  return (
    <section
      aria-label={labels.region}
      className="ge-media-controls"
    >
      <p
        aria-live="polite"
        className="ge-media-controls__status"
      >
        {statusLabel}
      </p>
      <div className="ge-media-controls__actions">
        {canStart ? (
          <button
            className="ge-button ge-button--primary ge-button--speak"
            onClick={onRequestStart}
            type="button"
          >
            {labels.start}
          </button>
        ) : null}
        {isRecording ? (
          <button
            className="ge-button"
            onClick={onRequestPause}
            type="button"
          >
            {labels.pause}
          </button>
        ) : null}
        {isPaused ? (
          <button
            className="ge-button"
            onClick={onRequestResume}
            type="button"
          >
            {labels.resume}
          </button>
        ) : null}
        {isRecording || isPaused ? (
          <>
            <button
              className="ge-button ge-button--primary"
              onClick={onRequestStop}
              type="button"
            >
              {labels.stop}
            </button>
            <button
              className="ge-button ge-button--quiet"
              onClick={onRequestCancel}
              type="button"
            >
              {labels.cancel}
            </button>
          </>
        ) : null}
      </div>
    </section>
  )
}
