import {
  RecordingControls,
  type RecordingControlLabels,
  type RecordingControlState,
} from '@global-english/design-system'
import { useEffect, useRef, useState } from 'react'
import { beginSpeakLoop } from '../conversation/speakLoopTimings'
import { audioToPcmWav } from '../conversation/audioToPcmWav'
import {
  RecordingPermissionDeniedError,
  type AudioRecorder,
  type AudioRecordingSession,
  type CapturedAudio,
} from './BrowserAudioRecorder'
import type { LocalRecordingMetadata } from './LearnerJourneyApi'
import { isValidLocalCapture, canonicalCaptureMimeType } from './LocalCaptureReceipt'
import { LiveLevelMeter } from './LiveLevelMeter'

export interface RecordingTurnLabels {
  readonly controls: RecordingControlLabels
  readonly playbackLabel: string
  readonly playbackHelp: string
  readonly retry: string
  readonly permissionExplanation: string
  readonly continueWithout: string
  readonly continueWithoutExplanation: string
}

export type HoldPhase = 'idle' | 'listening' | 'processing'

interface RecordingTurnProps {
  readonly createClientId: () => string
  readonly labels: RecordingTurnLabels
  readonly onAudioCaptureChange?: (audio: CapturedAudio | null) => void
  readonly onCaptureChange: (
    metadata: LocalRecordingMetadata | null,
  ) => void
  readonly onContinueWithoutRecording: () => void
  readonly onCancelInFlight?: () => void
  readonly onHoldPhaseChange?: (phase: HoldPhase) => void
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
  onCancelInFlight,
  onHoldPhaseChange,
  recorder,
}: RecordingTurnProps) {
  const [state, setState] = useState<RecordingControlState>(
    recorder.isSupported() ? 'ready' : 'blocked',
  )
  const [capture, setCapture] = useState<LocalCapture | null>(null)
  const [liveSession, setLiveSession] = useState<AudioRecordingSession | null>(
    null,
  )
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
        setLiveSession(null)
        if (active.current && requestGeneration.current === generation) {
          setState(recorder.isSupported() ? 'ready' : 'blocked')
        }
        return
      }

      sessionRef.current = session
      setLiveSession(session)
      setState('recording')
      onHoldPhaseChange?.('listening')
    } catch (error) {
      if (!active.current || requestGeneration.current !== generation) {
        return
      }

      holdIntent.current = false
      setLiveSession(null)
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

    sessionRef.current = null
    setLiveSession(null)
    beginSpeakLoop()
    setState('processing')
    onHoldPhaseChange?.('processing')
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

      if (skipReviewPlayback) {
        onAudioCaptureChange?.(audio)
        onCaptureChange(metadata)
        return
      }

      const reviewBlob =
        audio.blob.type === 'audio/wav'
          ? audio.blob
          : await audioToPcmWav(audio).catch(() => audio.blob)
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
    setLiveSession(null)
    setState('ready')
    onHoldPhaseChange?.('idle')
    onCancelInFlight?.()
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
          onHoldPhaseChange?.('listening')
        }}
        onRequestStart={() => void start()}
        onRequestStop={() => void stop()}
        state={state}
      />
      {state === 'recording' ? (
        <LiveLevelMeter session={liveSession} />
      ) : null}

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
