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
