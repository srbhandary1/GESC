import {
  RecordingControls,
  type RecordingControlLabels,
  type RecordingControlState,
} from '@global-english/design-system'
import { useEffect, useRef, useState } from 'react'
import {
  RecordingPermissionDeniedError,
  type AudioRecorder,
  type AudioRecordingSession,
  type CapturedAudio,
} from './BrowserAudioRecorder'
import type { LocalRecordingMetadata } from './LearnerJourneyApi'
import { isValidLocalCapture } from './LocalCaptureReceipt'

export interface RecordingTurnLabels {
  readonly controls: RecordingControlLabels
  readonly playbackLabel: string
  readonly playbackHelp: string
  readonly retry: string
  readonly permissionExplanation: string
  readonly continueWithout: string
  readonly continueWithoutExplanation: string
}

interface RecordingTurnProps {
  readonly createClientId: () => string
  readonly labels: RecordingTurnLabels
  readonly onAudioCaptureChange?: (audio: CapturedAudio | null) => void
  readonly onCaptureChange: (
    metadata: LocalRecordingMetadata | null,
  ) => void
  readonly onContinueWithoutRecording: () => void
  readonly recorder: AudioRecorder
}

interface LocalCapture {
  readonly audio: CapturedAudio
  readonly metadata: LocalRecordingMetadata
  readonly objectUrl: string
}

export function RecordingTurn({
  createClientId,
  labels,
  onAudioCaptureChange,
  onCaptureChange,
  onContinueWithoutRecording,
  recorder,
}: RecordingTurnProps) {
  const [state, setState] = useState<RecordingControlState>(
    recorder.isSupported() ? 'ready' : 'blocked',
  )
  const [capture, setCapture] = useState<LocalCapture | null>(null)
  const sessionRef = useRef<AudioRecordingSession | null>(null)
  const captureRef = useRef<LocalCapture | null>(null)
  const active = useRef(true)
  const requestGeneration = useRef(0)

  useEffect(() => {
    active.current = true

    return () => {
      active.current = false
      requestGeneration.current += 1
      sessionRef.current?.cancel()
      sessionRef.current = null
      const currentCapture = captureRef.current
      if (currentCapture !== null) {
        URL.revokeObjectURL(currentCapture.objectUrl)
      }
    }
  }, [])

  const start = async () => {
    clearCapture()
    setState('requesting-permission')
    const generation = requestGeneration.current + 1
    requestGeneration.current = generation

    try {
      const session = await recorder.start()

      if (!active.current || requestGeneration.current !== generation) {
        session.cancel()
        return
      }

      sessionRef.current = session
      setState('recording')
    } catch (error) {
      if (!active.current || requestGeneration.current !== generation) {
        return
      }

      setState(
        error instanceof RecordingPermissionDeniedError
          ? 'blocked'
          : 'error',
      )
    }
  }

  const stop = async () => {
    const session = sessionRef.current
    if (session === null) {
      setState('error')
      return
    }

    setState('processing')
    const generation = requestGeneration.current

    try {
      const audio = await session.stop()

      if (!active.current || requestGeneration.current !== generation) {
        return
      }

      if (!isValidLocalCapture(audio)) {
        sessionRef.current = null
        setState('error')
        return
      }

      const metadata: LocalRecordingMetadata = {
        captured: true,
        byte_size: audio.blob.size,
        client_capture_id: createClientId(),
        duration_ms: audio.durationMs,
        mime_type: audio.mimeType,
      }
      const nextCapture = {
        audio,
        metadata,
        objectUrl: URL.createObjectURL(audio.blob),
      }
      sessionRef.current = null
      captureRef.current = nextCapture
      setCapture(nextCapture)
      onAudioCaptureChange?.(audio)
      onCaptureChange(metadata)
      setState('ready')
    } catch {
      if (!active.current || requestGeneration.current !== generation) {
        return
      }

      sessionRef.current = null
      setState('error')
    }
  }

  const cancel = () => {
    requestGeneration.current += 1
    sessionRef.current?.cancel()
    sessionRef.current = null
    setState('ready')
  }

  const retry = () => {
    clearCapture()
    setState('ready')
  }

  const clearCapture = () => {
    if (captureRef.current !== null) {
      URL.revokeObjectURL(captureRef.current.objectUrl)
    }
    captureRef.current = null
    setCapture(null)
    onAudioCaptureChange?.(null)
    onCaptureChange(null)
  }

  return (
    <section
      aria-labelledby="recording-turn-title"
      className="learner-card learner-recording"
    >
      <h3
        className="learner-card__title"
        id="recording-turn-title"
      >
        {labels.controls.region}
      </h3>
      <p>{labels.permissionExplanation}</p>
      <RecordingControls
        labels={labels.controls}
        onRequestCancel={cancel}
        onRequestPause={() => {
          sessionRef.current?.pause()
          setState('paused')
        }}
        onRequestResume={() => {
          sessionRef.current?.resume()
          setState('recording')
        }}
        onRequestStart={() => void start()}
        onRequestStop={() => void stop()}
        state={state}
      />

      {capture === null ? null : (
        <section
          aria-label={labels.playbackLabel}
          className="learner-recording__review"
        >
          <p>{labels.playbackHelp}</p>
          <audio
            controls
            preload="metadata"
            src={capture.objectUrl}
          />
          <button
            className="ge-button"
            onClick={retry}
            type="button"
          >
            {labels.retry}
          </button>
        </section>
      )}

      {state === 'blocked' || state === 'error' ? (
        <div className="learner-recording__alternative">
          {state === 'error' ? (
            <button
              className="ge-button"
              onClick={retry}
              type="button"
            >
              {labels.retry}
            </button>
          ) : null}
          <p>{labels.continueWithoutExplanation}</p>
          <button
            className="ge-button"
            onClick={onContinueWithoutRecording}
            type="button"
          >
            {labels.continueWithout}
          </button>
        </div>
      ) : null}
    </section>
  )
}
