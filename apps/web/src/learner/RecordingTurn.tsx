import {
  RecordingControls,
  type RecordingCaptureMode,
  type RecordingControlLabels,
  type RecordingControlState,
} from '@global-english/design-system'
import { useCallback, useEffect, useRef, useState } from 'react'
import { HOLD_MAXIMUM_DURATION_MS } from '../conversation/holdToTalkLimit'
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
import { ANDROID_BACK_REQUEST_EVENT } from './LearnerNavigationHistory'

export interface RecordingTurnLabels {
  readonly controls: RecordingControlLabels
  readonly playbackLabel: string
  readonly playbackHelp: string
  readonly retry: string
  readonly permissionExplanation: string
  readonly permissionContinue?: string
  readonly permissionDeniedOnce?: string
  readonly permissionDeniedPermanently?: string
  readonly continueWithout: string
  readonly continueWithoutExplanation: string
  readonly holdRemaining?: string
  readonly tooShort?: string
  readonly backCancelled: string
}

export type HoldPhase = 'idle' | 'listening' | 'processing'

interface RecordingTurnProps {
  readonly captureMode?: RecordingCaptureMode
  readonly compact?: boolean
  readonly hideTypingFallback?: boolean
  readonly showPermissionExplanation?: boolean
  readonly permissionRationaleAccepted?: boolean
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
  readonly recordingDisabled?: boolean
  readonly recordingDisabledStatus?: string
  readonly skipReviewPlayback?: boolean
  readonly minimumDurationMs?: number
}

interface LocalCapture {
  readonly audio: CapturedAudio
  readonly metadata: LocalRecordingMetadata
  readonly objectUrl: string
}

export function RecordingTurn({
  captureMode = 'tap',
  compact = false,
  hideTypingFallback = false,
  showPermissionExplanation = true,
  permissionRationaleAccepted = false,
  createClientId,
  labels,
  onAudioCaptureChange,
  onCaptureChange,
  onContinueWithoutRecording,
  onCancelInFlight,
  onHoldPhaseChange,
  recorder,
  recordingDisabled = false,
  recordingDisabledStatus,
  skipReviewPlayback = false,
  minimumDurationMs = 250,
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
  const holdIntent = useRef(false)
  const stopRef = useRef<() => Promise<void>>(async () => undefined)
  const [holdElapsedMs, setHoldElapsedMs] = useState(0)
  const [validationError, setValidationError] = useState<string | null>(null)
  const [permissionRationale, setPermissionRationale] = useState(false)
  const [backCancellationStatus, setBackCancellationStatus] = useState<
    string | null
  >(null)
  const [permissionDenial, setPermissionDenial] = useState<
    'denied_once' | 'denied_permanently' | null
  >(null)
  const contextualRationaleAccepted = useRef(permissionRationaleAccepted)

  useEffect(() => {
    active.current = true

    return () => {
      active.current = false
      requestGeneration.current += 1
      holdIntent.current = false
      sessionRef.current?.cancel()
      sessionRef.current = null
      const currentCapture = captureRef.current
      if (currentCapture !== null) {
        URL.revokeObjectURL(currentCapture.objectUrl)
      }
    }
  }, [])

  useEffect(() => {
    if (
      (captureMode !== 'hold' && captureMode !== 'toggle') ||
      state !== 'recording'
    ) {
      return
    }

    const startedAt = Date.now()
    const tick = window.setInterval(() => {
      setHoldElapsedMs(Date.now() - startedAt)
    }, 250)
    const limit = window.setTimeout(() => {
      void stopRef.current()
    }, HOLD_MAXIMUM_DURATION_MS)

    return () => {
      window.clearInterval(tick)
      window.clearTimeout(limit)
    }
  }, [captureMode, state])

  const start = async (rationaleAccepted = false) => {
    if (sessionRef.current !== null) {
      return
    }

    if (
      recorder.requiresContextualPermissionRationale?.() === true &&
      !contextualRationaleAccepted.current &&
      !rationaleAccepted
    ) {
      setPermissionRationale(true)
      setPermissionDenial(null)
      return
    }

    if (rationaleAccepted) {
      contextualRationaleAccepted.current = true
      setPermissionRationale(false)
    }

    holdIntent.current = true
    clearCapture()
    setBackCancellationStatus(null)
    setValidationError(null)
    setState('requesting-permission')
    const generation = requestGeneration.current + 1
    requestGeneration.current = generation

    try {
      const session = await recorder.start()

      if (
        !active.current ||
        requestGeneration.current !== generation ||
        !holdIntent.current
      ) {
        session.cancel()
        setLiveSession(null)
        if (active.current && requestGeneration.current === generation) {
          setState(recorder.isSupported() ? 'ready' : 'blocked')
          onHoldPhaseChange?.('idle')
        }
        return
      }

      sessionRef.current = session
      setLiveSession(session)
      setHoldElapsedMs(0)
      setState('recording')
      onHoldPhaseChange?.('listening')
    } catch (error) {
      if (!active.current || requestGeneration.current !== generation) {
        return
      }

      holdIntent.current = false
      setLiveSession(null)
      if (error instanceof RecordingPermissionDeniedError) {
        setPermissionDenial(error.status)
        if (error.status === 'denied_once') {
          contextualRationaleAccepted.current = false
        }
        setState('blocked')
      } else {
        setState('error')
      }
      onHoldPhaseChange?.('idle')
    }
  }

  const stop = async () => {
    holdIntent.current = false
    const session = sessionRef.current
    if (session === null) {
      requestGeneration.current += 1
      setState(recorder.isSupported() ? 'ready' : 'blocked')
      onHoldPhaseChange?.('idle')
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

      if (audio.durationMs < minimumDurationMs) {
        setValidationError(labels.tooShort ?? null)
        setState('error')
        onHoldPhaseChange?.('idle')
        return
      }

      if (!isValidLocalCapture(audio)) {
        setState('error')
        onHoldPhaseChange?.('idle')
        return
      }

      const mimeType = canonicalCaptureMimeType(audio.mimeType)
      if (mimeType === null) {
        setState('error')
        onHoldPhaseChange?.('idle')
        return
      }

      const metadata: LocalRecordingMetadata = {
        captured: true,
        byte_size: audio.blob.size,
        client_capture_id: createClientId(),
        duration_ms: audio.durationMs,
        mime_type: mimeType,
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
        objectUrl: URL.createObjectURL(reviewBlob),
      }
      captureRef.current = nextCapture
      setCapture(nextCapture)
      onAudioCaptureChange?.(audio)
      onCaptureChange(metadata)
      setState('ready')
    } catch {
      if (!active.current || requestGeneration.current !== generation) {
        return
      }

      setState('error')
      onHoldPhaseChange?.('idle')
    }
  }

  useEffect(() => {
    stopRef.current = stop
  })

  const cancel = useCallback(() => {
    holdIntent.current = false
    requestGeneration.current += 1
    sessionRef.current?.cancel()
    sessionRef.current = null
    setLiveSession(null)
    setState('ready')
    onHoldPhaseChange?.('idle')
    onCancelInFlight?.()
  }, [onCancelInFlight, onHoldPhaseChange])

  useEffect(() => {
    const handleBack = (event: Event) => {
      if (
        state !== 'requesting-permission' &&
        state !== 'recording' &&
        state !== 'paused' &&
        state !== 'processing'
      ) {
        return
      }

      event.preventDefault()
      cancel()
      setBackCancellationStatus(labels.backCancelled)
    }

    globalThis.addEventListener(ANDROID_BACK_REQUEST_EVENT, handleBack)
    return () => {
      globalThis.removeEventListener(ANDROID_BACK_REQUEST_EVENT, handleBack)
    }
  }, [cancel, labels.backCancelled, state])

  const retry = () => {
    clearCapture()
    setBackCancellationStatus(null)
    setValidationError(null)
    setPermissionDenial(null)
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

  const remainingHoldSeconds = Math.max(
    0,
    Math.ceil((HOLD_MAXIMUM_DURATION_MS - holdElapsedMs) / 1000),
  )
  const holdRemainingText =
    labels.holdRemaining === undefined
      ? null
      : labels.holdRemaining.replaceAll(
          '{seconds}',
          String(remainingHoldSeconds),
        )

  return (
    <section
      aria-labelledby="recording-turn-title"
      className={`learner-card learner-recording${captureMode === 'hold' ? ' learner-recording--hold' : ''}${compact ? ' learner-recording--compact' : ''}`}
    >
      <h3
        className={
          compact ? 'learner-card__title ge-visually-hidden' : 'learner-card__title'
        }
        id="recording-turn-title"
      >
        {labels.controls.region}
      </h3>
      {compact || !showPermissionExplanation ? null : <p>{labels.permissionExplanation}</p>}
      {permissionRationale ? (
        <div className="learner-recording__permission" role="group">
          {compact ? <p>{labels.permissionExplanation}</p> : null}
          <button
            className="ge-button ge-button--primary"
            onClick={() => void start(true)}
            type="button"
          >
            {labels.permissionContinue ?? labels.controls.start}
          </button>
          {hideTypingFallback ? null : (
          <button
            className="ge-button"
            onClick={onContinueWithoutRecording}
            type="button"
          >
            {labels.continueWithout}
          </button>
          )}
        </div>
      ) : (
        <RecordingControls
          captureMode={captureMode}
          hideStatus={false}
          interactionDisabled={recordingDisabled}
          labels={labels.controls}
          statusOverride={recordingDisabled ? recordingDisabledStatus : undefined}
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
      )}
      {validationError === null ? null : (
        <p className="learner-recording__validation" role="alert">
          {validationError}
        </p>
      )}
      {backCancellationStatus === null ? null : (
        <p className="learner-recording__back-status" role="status">
          {backCancellationStatus}
        </p>
      )}
      {state === 'recording' ? (
        <LiveLevelMeter session={liveSession} />
      ) : null}
      {state === 'recording' &&
      (captureMode === 'hold' || captureMode === 'toggle') &&
      holdRemainingText !== null &&
      !compact ? (
        <p role="status">{holdRemainingText}</p>
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
          {permissionDenial === null &&
          ((state === 'blocked' && captureMode === 'hold') ||
          (state === 'error' && captureMode !== 'hold') ? (
            <button
              className="ge-button"
              onClick={retry}
              type="button"
            >
              {labels.retry}
            </button>
          ) : null)}
          {permissionDenial === null ? null : (
            <p role="alert">
              {permissionDenial === 'denied_permanently'
                ? labels.permissionDeniedPermanently ?? labels.controls.blocked
                : labels.permissionDeniedOnce ?? labels.controls.blocked}
            </p>
          )}
          {permissionDenial === 'denied_once' ? (
            <button
              className="ge-button"
              onClick={retry}
              type="button"
            >
              {labels.retry}
            </button>
          ) : null}
          <p>{labels.continueWithoutExplanation}</p>
          {hideTypingFallback ? null : (
          <button
            className="ge-button"
            onClick={onContinueWithoutRecording}
            type="button"
          >
            {labels.continueWithout}
          </button>
          )}
        </div>
      ) : null}
    </section>
  )
}
