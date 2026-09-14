export type AudioControlState =
  | 'ready'
  | 'loading'
  | 'playing'
  | 'paused'
  | 'ended'
  | 'unavailable'
  | 'error'

export interface AudioControlLabels {
  region: string
  play: string
  pause: string
  resume: string
  replay: string
  loading: string
  unavailable: string
  error: string
  readyStatus: string
  loadingStatus: string
  playingStatus: string
  pausedStatus: string
  endedStatus: string
  unavailableStatus: string
  errorStatus: string
}

export interface AudioControlsProps {
  labels: AudioControlLabels
  state: AudioControlState
  hideStatus?: boolean
  onRequestPause: () => void
  onRequestPlay: () => void
  onRequestReplay: () => void
}

interface AudioAction {
  disabled: boolean
  label: string
  request: () => void
}

export function AudioControls({
  labels,
  state,
  hideStatus = false,
  onRequestPause,
  onRequestPlay,
  onRequestReplay,
}: AudioControlsProps) {
  const statusLabel = {
    ready: labels.readyStatus,
    loading: labels.loadingStatus,
    playing: labels.playingStatus,
    paused: labels.pausedStatus,
    ended: labels.endedStatus,
    unavailable: labels.unavailableStatus,
    error: labels.errorStatus,
  }[state]

  const action: AudioAction = (() => {
    switch (state) {
      case 'playing':
        return {
          disabled: false,
          label: labels.pause,
          request: onRequestPause,
        }
      case 'paused':
        return {
          disabled: false,
          label: labels.resume,
          request: onRequestPlay,
        }
      case 'ended':
        return {
          disabled: false,
          label: labels.replay,
          request: onRequestReplay,
        }
      case 'loading':
        return {
          disabled: true,
          label: labels.loading,
          request: onRequestPlay,
        }
      case 'unavailable':
        return {
          disabled: true,
          label: labels.unavailable,
          request: onRequestPlay,
        }
      case 'error':
        return {
          disabled: true,
          label: labels.error,
          request: onRequestPlay,
        }
      case 'ready':
        return {
          disabled: false,
          label: labels.play,
          request: onRequestPlay,
        }
    }
  })()

  return (
    <section
      aria-label={labels.region}
      className="ge-media-controls"
    >
      <button
        className="ge-button ge-button--primary"
        disabled={action.disabled}
        onClick={action.request}
        type="button"
      >
        {action.label}
      </button>
      <p
        aria-live="polite"
        className={
          hideStatus
            ? 'ge-media-controls__status ge-visually-hidden'
            : 'ge-media-controls__status'
        }
      >
        {statusLabel}
      </p>
    </section>
  )
}
