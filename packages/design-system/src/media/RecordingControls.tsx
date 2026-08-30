export type RecordingControlState =
  | 'idle'
  | 'ready'
  | 'requesting-permission'
  | 'recording'
  | 'paused'
  | 'processing'
  | 'blocked'
  | 'error'

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
}

export interface RecordingControlsProps {
  labels: RecordingControlLabels
  state: RecordingControlState
  onRequestCancel: () => void
  onRequestPause: () => void
  onRequestResume: () => void
  onRequestStart: () => void
  onRequestStop: () => void
}

export function RecordingControls({
  labels,
  state,
  onRequestCancel,
  onRequestPause,
  onRequestResume,
  onRequestStart,
  onRequestStop,
}: RecordingControlsProps) {
  const statusLabel = {
    idle: labels.idle,
    ready: labels.ready,
    'requesting-permission': labels.requestingPermission,
    recording: labels.recording,
    paused: labels.paused,
    processing: labels.processing,
    blocked: labels.blocked,
    error: labels.error,
  }[state]

  const canStart = state === 'idle' || state === 'ready'
  const isRecording = state === 'recording'
  const isPaused = state === 'paused'
  const holdActive = useRef(false)

  const endHold = () => {
    if (!holdActive.current) {
      return
    }

    holdActive.current = false
    onRequestStop()
  }

  const beginHold = () => {
    if (state === 'processing' || state === 'blocked' || state === 'error') {
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
        {state === 'blocked' || state === 'error' ? null : (
          <div className="ge-media-controls__actions">
            <button
              aria-pressed={holdPressed}
              className="ge-button ge-button--primary ge-button--speak ge-button--hold-to-talk"
              data-hold-state={state}
              disabled={state === 'processing'}
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
              onPointerCancel={endHold}
              onPointerDown={(event) => {
                if (event.button !== 0) {
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
                beginHold()
              }}
              onPointerUp={endHold}
              type="button"
            >
              {holdLabel}
            </button>
            {isRecording || state === 'processing' ? (
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
            className="ge-button ge-button--primary"
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
