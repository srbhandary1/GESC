import { audioForHistory, historyEnabled, type SavedAudio } from './deviceConversationHistory'
import type {
  MessageKey,
  MessageParameters,
  SupportedLocale,
} from '@global-english/localization'
import { useCallback, useEffect, useRef, useState } from 'react'
import {
  defaultAudioRecorder,
  type AudioRecorder,
  type CapturedAudio,
} from '../learner/BrowserAudioRecorder'
import { RecordingTurn } from '../learner/RecordingTurn'
import type {
  ConversationJourneyApi,
  SecureVoiceRecording,
  VoiceConsents,
} from './ConversationJourneyApi'
import { audioToPcmWav } from './audioToPcmWav'
import {
  abandonSpeakLoop,
  markSpeakLoop,
  type SpeakLoopPhase,
} from './speakLoopTimings'
import { isAbortError } from '../shared/isAbortError'
import { HOLD_MAXIMUM_DURATION_MS } from './holdToTalkLimit'
import { conversationErrorMessageKey } from './conversationErrorMessage'
import { preconnectRealtimeSpeechAfterConsent } from './preconnectRealtimeSpeech'
import { isNativeAndroid } from '../auth/nativeMicrophonePermissionPlugin'

export interface SelectedVoiceTranscript {
  readonly audio?: SavedAudio
  readonly recordingId?: string
  readonly locale: SupportedLocale | 'unknown'
  readonly source: 'typed' | 'original_speech' | 'translated_speech'
}

interface VoicePracticePanelProps {
  readonly api: ConversationJourneyApi
  readonly compact?: boolean
  readonly createClientId: () => string
  readonly locale: SupportedLocale
  readonly onPreferTyping?: () => void
  readonly hideTypingFallback?: boolean
  readonly onTranscriptSelected: (transcript: string, metadata?: SelectedVoiceTranscript) => void
  readonly onReviewStateChange?: (reviewing: boolean) => void
  readonly includeTranscriptProvenance?: boolean
  readonly onProcessingConsentWithdrawn?: () => void
  readonly onLoopPhase?: (phase: SpeakLoopPhase) => void
  readonly onCancelInFlight?: () => void
  readonly recorder?: AudioRecorder
  readonly requireTranscriptConfirmation?: boolean
  readonly resetEpoch?: number
  readonly speakLoopPhase?: SpeakLoopPhase
  readonly sessionId: string
  readonly text: (key: MessageKey, parameters?: MessageParameters) => string
  readonly unavailableMessageKey?: MessageKey
}

type VoicePanelState =
  | { readonly kind: 'checking' }
  | { readonly kind: 'idle' }
  | { readonly kind: 'loading' }
  | { readonly kind: 'consent'; readonly listing: VoiceConsents }
  | {
      readonly kind: 'recording'
      readonly listing: VoiceConsents
    }
  | {
      readonly kind: 'candidate'
      readonly listing: VoiceConsents
      readonly recording: SecureVoiceRecording
    }
  | { readonly kind: 'error'; readonly listing?: VoiceConsents }
  | { readonly kind: 'unavailable' }

const REQUIRED_PURPOSES = [
  'voice_upload_and_transcription',
  'conversation_provider_processing',
] as const

const AUTO_ACCEPT_MIN_CONFIDENCE = 0.6

export function VoicePracticePanel({
  api,
  compact = false,
  createClientId,
  locale,
  onPreferTyping,
  hideTypingFallback = false,
  onTranscriptSelected,
  onReviewStateChange,
  onLoopPhase,
  onCancelInFlight,
  recorder = defaultAudioRecorder,
  requireTranscriptConfirmation = false,
  includeTranscriptProvenance = false,
  onProcessingConsentWithdrawn,
  resetEpoch = 0,
  speakLoopPhase = 'waiting',
  sessionId,
  text,
  unavailableMessageKey,
}: VoicePracticePanelProps) {
  const [state, setState] = useState<VoicePanelState>({ kind: 'checking' })
  const [correctedTranscript, setCorrectedTranscript] = useState('')
  const [showInstructions, setShowInstructions] = useState(() => {
    try { return localStorage.getItem('ge.voice-instructions.v1') !== 'seen' } catch { return true }
  })
  const [loopPhase, setLoopPhase] = useState<SpeakLoopPhase>('waiting')
  const [localResetEpoch, setLocalResetEpoch] = useState(0)
  const [uploadError, setUploadError] = useState<MessageKey | null>(null)
  const pendingAudio = useRef<SavedAudio | undefined>(undefined)
  const uploadCommand = useRef('')
  const reviewCommand = useRef('')
  const uploadAbort = useRef<AbortController | null>(null)
  const tapToSpeak = compact || isNativeAndroid()
  const voiceTitle = text(
    tapToSpeak ? 'learner.recording.tap_speak' : 'conversation.voice.title',
  )

  const setPhase = useCallback((phase: SpeakLoopPhase) => {
    setLoopPhase(phase)
    onLoopPhase?.(phase)
  }, [onLoopPhase])

  const releaseHold = useCallback(() => {
    setLocalResetEpoch((epoch) => epoch + 1)
    setPhase('waiting')
  }, [setPhase])

  const cancelInFlight = useCallback(() => {
    uploadAbort.current?.abort()
    pendingAudio.current = undefined
    uploadAbort.current = null
    abandonSpeakLoop()
    onCancelInFlight?.()
    setPhase('waiting')
  }, [onCancelInFlight, setPhase])

  const recordingLocked =
    speakLoopPhase === 'speaking' || speakLoopPhase === 'preparing'
  const effectiveLoopPhase =
    onLoopPhase === undefined ? loopPhase : speakLoopPhase

  useEffect(() => {
    if (api.voiceConsents === undefined) {
      return
    }

    let cancelled = false

    const refreshConsent = async () => {
      try {
        const listing = await api.voiceConsents!(locale)
        if (cancelled) {
          return
        }
        setState(
          requiredPurposesGranted(listing)
            ? { kind: 'recording', listing }
            : { kind: 'consent', listing },
        )
      } catch {
        if (!cancelled) {
          setState({ kind: 'unavailable' })
        }
      }
    }
    void refreshConsent()
    const onChanged = () => { uploadAbort.current?.abort(); void refreshConsent() }
    window.addEventListener('global-english:voice-consent-changed', onChanged)
    return () => {
      cancelled = true
      uploadAbort.current?.abort()
      window.removeEventListener('global-english:voice-consent-changed', onChanged)
    }
  }, [api, locale])

  useEffect(() => { onReviewStateChange?.(state.kind === 'candidate' || state.kind === 'loading') }, [onReviewStateChange, state.kind])

  if (
    api.voiceConsents === undefined ||
    api.grantVoiceConsent === undefined ||
    api.uploadVoiceRecording === undefined ||
    api.reviewVoiceTranscript === undefined ||
    api.withdrawVoiceConsent === undefined ||
    api.deleteVoiceRecording === undefined
  ) {
    return null
  }

  if (unavailableMessageKey !== undefined) {
    return (
      <section
        aria-labelledby="secure-voice-title"
        className="conversation-journey__voice"
      >
        <h3 id="secure-voice-title">{voiceTitle}</h3>
        <p role="alert">{text(unavailableMessageKey)}</p>
      </section>
    )
  }

  const loadConsent = async () => {
    setState({ kind: 'loading' })

    try {
      const listing = await api.voiceConsents!(locale)
      setState(
        requiredPurposesGranted(listing)
          ? { kind: 'recording', listing }
          : { kind: 'consent', listing },
      )
    } catch {
      setState({ kind: 'unavailable' })
    }
  }

  const grant = async (
    purpose: (typeof REQUIRED_PURPOSES)[number],
    listing: VoiceConsents,
  ) => {
    setState({ kind: 'loading' })

    try {
      await api.grantVoiceConsent!(
        { purpose, requested_locale: locale },
        createClientId(),
      )
      const refreshed = await api.voiceConsents!(locale)
      if (requiredPurposesGranted(refreshed)) {
        void preconnectRealtimeSpeechAfterConsent({
          api,
          commandId: createClientId(),
          sessionId,
        })
        setState({ kind: 'recording', listing: refreshed })
      } else {
        setState({ kind: 'consent', listing: refreshed })
      }
    } catch {
      setState({ kind: 'error', listing })
    }
  }

  const grantBoth = async (listing: VoiceConsents) => {
    setState({ kind: 'loading' })

    try {
      for (const purpose of REQUIRED_PURPOSES) {
        const alreadyGranted = listing.consents.some(
          (consent) =>
            consent.purpose === purpose && consent.state === 'granted',
        )
        if (!alreadyGranted) {
          await api.grantVoiceConsent!(
            { purpose, requested_locale: locale },
            createClientId(),
          )
        }
      }
      const refreshed = await api.voiceConsents!(locale)
      if (requiredPurposesGranted(refreshed)) {
        void preconnectRealtimeSpeechAfterConsent({
          api,
          commandId: createClientId(),
          sessionId,
        })
        setState({ kind: 'recording', listing: refreshed })
      } else {
        setState({ kind: 'consent', listing: refreshed })
      }
    } catch {
      setState({ kind: 'error', listing })
    }
  }

  const uploadAndAccept = async (
    listing: VoiceConsents,
    capture: CapturedAudio,
  ) => {
    setUploadError(null)
    setPhase('processing')
    uploadAbort.current?.abort()
    const controller = new AbortController()
    uploadAbort.current = controller

    try {
      if (uploadCommand.current === '') {
        uploadCommand.current = createClientId()
      }
      const wav = await audioToPcmWav(capture, HOLD_MAXIMUM_DURATION_MS)
      pendingAudio.current = historyEnabled() ? await audioForHistory(wav) : undefined
      markSpeakLoop('wav_ready')
      const recording = await api.uploadVoiceRecording!(
        sessionId,
        wav,
        uploadCommand.current,
        controller.signal,
      )
      markSpeakLoop('upload_done')

      if (
        recording.status !== 'awaiting_learner_confirmation' ||
        recording.transcript === null ||
        recording.transcript.trim() === ''
      ) {
        uploadCommand.current = createClientId()
        setUploadError('conversation.voice.error')
        setState({ kind: 'recording', listing })
        releaseHold()
        return
      }

      if (!requireTranscriptConfirmation) {
        await acceptTranscript(listing, recording, 'accept', controller.signal)
        return
      }

      const confidence = recording.transcript_confidence
      if (
        confidence !== null &&
        confidence >= AUTO_ACCEPT_MIN_CONFIDENCE
      ) {
        await acceptTranscript(listing, recording, 'accept', controller.signal)
        return
      }

      setCorrectedTranscript(recording.transcript)
      setState({ kind: 'candidate', listing, recording })
      releaseHold()
    } catch (error) {
      if (isAbortError(error) || controller.signal.aborted) {
        releaseHold()
        return
      }

      uploadCommand.current = createClientId()
      setUploadError(
        conversationErrorMessageKey(error, 'conversation.voice.error'),
      )
      setState({ kind: 'recording', listing })
      releaseHold()
    }
  }

  const withdraw = async (
    purpose: (typeof REQUIRED_PURPOSES)[number],
    listing: VoiceConsents,
  ) => {
    setState({ kind: 'loading' })

    try {
      await api.withdrawVoiceConsent!(
        purpose,
        { requested_locale: locale },
        createClientId(),
      )
      if (purpose === 'conversation_provider_processing') onProcessingConsentWithdrawn?.()
      const refreshed = await api.voiceConsents!(locale)
      setState({ kind: 'consent', listing: refreshed })
    } catch {
      setState({ kind: 'error', listing })
    }
  }

  const acceptTranscript = async (
    listing: VoiceConsents,
    recording: SecureVoiceRecording,
    action: 'accept' | 'correct' = 'accept',
    signal?: AbortSignal,
  ) => {
    if (reviewCommand.current === '') {
      reviewCommand.current = createClientId()
    }
    const result = await api.reviewVoiceTranscript!(
      recording.id,
      {
        action,
        corrected_transcript:
          action === 'correct' ? correctedTranscript.trim() : null,
      },
      reviewCommand.current,
      signal,
    )

    const selected =
      action === 'correct'
        ? correctedTranscript.trim()
        : includeTranscriptProvenance
          ? result.transcript_original ?? result.transcript ?? recording.transcript
          : recording.transcript

    if (selected !== null && selected !== '') {
      markSpeakLoop('transcript_ready')
      setPhase('preparing')
      if (includeTranscriptProvenance) {
        onTranscriptSelected(selected, action === 'correct'
          ? { locale: 'unknown', source: 'typed', audio: pendingAudio.current }
          : {
              recordingId: result.id,
              ...(pendingAudio.current ? { audio: pendingAudio.current } : {}),
              locale: result.transcript_locale ?? 'unknown',
              source: result.transcript_source ?? 'translated_speech',
            })
      } else {
        if (pendingAudio.current) onTranscriptSelected(selected, { audio: pendingAudio.current, source: 'original_speech', locale: result.transcript_locale ?? 'unknown' })
        else onTranscriptSelected(selected)
      }
    }

    pendingAudio.current = undefined
    reviewCommand.current = createClientId()
    uploadCommand.current = createClientId()
    setState({ kind: 'recording', listing })

    return result
  }

  const review = async (
    action: 'accept' | 'correct' | 'discard',
    listing: VoiceConsents,
    recording: SecureVoiceRecording,
  ) => {
    setState({ kind: 'loading' })

    try {
      if (action === 'discard') {
        if (reviewCommand.current === '') {
          reviewCommand.current = createClientId()
        }
        await api.reviewVoiceTranscript!(
          recording.id,
          { action, corrected_transcript: null },
          reviewCommand.current,
        )
        reviewCommand.current = createClientId()
        uploadCommand.current = createClientId()
          setCorrectedTranscript('')
        setState({ kind: 'recording', listing })
        return
      }

      await acceptTranscript(listing, recording, action)
    } catch {
      setState({ kind: 'error', listing })
    }
  }

  const deleteCandidate = async (
    listing: VoiceConsents,
    recording: SecureVoiceRecording,
  ) => {
    setState({ kind: 'loading' })

    try {
      await api.deleteVoiceRecording!(recording.id)
      pendingAudio.current = undefined
      setCorrectedTranscript('')
      uploadCommand.current = createClientId()
      reviewCommand.current = createClientId()
      setState({ kind: 'recording', listing })
    } catch {
      setState({ kind: 'error', listing })
    }
  }

  const purposeButtons = (listing: VoiceConsents) =>
    REQUIRED_PURPOSES.map((purpose) => {
      const granted = listing.consents.some(
        (consent) =>
          consent.purpose === purpose && consent.state === 'granted',
      )

      return (
        <button
          key={purpose}
          onClick={() =>
            void (
              granted ? withdraw(purpose, listing) : grant(purpose, listing)
            )
          }
          type="button"
        >
          {granted
            ? text(
                purpose === 'voice_upload_and_transcription'
                  ? 'conversation.voice.withdraw_upload'
                  : 'conversation.voice.withdraw_provider',
              )
            : text(
                purpose === 'voice_upload_and_transcription'
                  ? 'conversation.voice.consent_upload'
                  : 'conversation.voice.consent_provider',
              )}
        </button>
      )
    })

  return (
    <section
      aria-labelledby="secure-voice-title"
      className={
        compact
          ? 'conversation-journey__voice conversation-journey__voice--compact'
          : 'conversation-journey__voice'
      }
      data-voice-panel-state={state.kind}
    >
      <h3
        className={
          compact
            ? 'conversation-journey__voice-title ge-visually-hidden'
            : 'conversation-journey__voice-title'
        }
        id="secure-voice-title"
      >
        {voiceTitle}
      </h3>
      {compact ? null : (
        <p className="conversation-journey__voice-intro">
          {text('conversation.voice.optional')}
        </p>
      )}

      {state.kind === 'idle' ? (
        <button onClick={() => void loadConsent()} type="button">
          {text('conversation.voice.setup')}
        </button>
      ) : null}

      {state.kind === 'loading' ? (
        <p role="status">{text('conversation.voice.processing')}</p>
      ) : null}

      {!compact &&
      state.kind === 'recording' &&
      effectiveLoopPhase !== 'waiting' ? (
        <p role="status" data-speak-loop-phase={effectiveLoopPhase}>
          {text(
            effectiveLoopPhase === 'listening'
              ? 'conversation.loop.listening'
              : effectiveLoopPhase === 'processing'
                ? 'conversation.loop.processing'
                : effectiveLoopPhase === 'preparing'
                  ? 'conversation.loop.preparing'
                  : effectiveLoopPhase === 'speaking'
                    ? 'conversation.loop.speaking'
                    : 'conversation.voice.processing',
          )}
        </p>
      ) : null}

      {state.kind === 'consent' ? (
        <>
          <aside
            className="conversation-journey__voice-notice"
            lang={state.listing.notice.resolved_locale}
          >
            <p>{state.listing.notice.notice}</p>
          </aside>
          <button
            className="ge-button ge-button--primary"
            onClick={() => void grantBoth(state.listing)}
            type="button"
          >
            {text('conversation.voice.consent_both')}
          </button>
          {purposeButtons(state.listing)}
        </>
      ) : null}

      {state.kind === 'recording' ? (
        <>
          {uploadError !== null ? (
            <p role="alert">{text(uploadError)}</p>
          ) : null}
          <RecordingTurn
            key={`${resetEpoch}-${localResetEpoch}-${recordingLocked ? 'locked' : 'ready'}`}
            captureMode={tapToSpeak ? 'toggle' : 'hold'}
            compact={compact}
            hideTypingFallback={hideTypingFallback}
            showPermissionExplanation={showInstructions}
            permissionRationaleAccepted={!showInstructions}
            createClientId={createClientId}
            labels={{
              backCancelled: text('learner.recording.back_cancelled'),
              controls: {
                blocked:
                  typeof window !== 'undefined' && !window.isSecureContext
                    ? text('conversation.voice.error')
                    : text(
                        tapToSpeak
                          ? 'learner.recording.permission_denied_once'
                          : 'learner.recording.blocked',
                      ),
                cancel: text('learner.recording.cancel'),
                error: text('learner.recording.error'),
                hold: tapToSpeak
                  ? text('learner.recording.tap_speak')
                  : text('learner.recording.hold'),
                holding: text('learner.recording.holding'),
                idle: text('learner.recording.idle'),
                pause: text('learner.recording.pause'),
                paused: text('learner.recording.paused'),
                processing: text('learner.recording.processing'),
                ready: text('learner.recording.ready'),
                recording: text('learner.recording.recording'),
                region: text('learner.recording.region'),
                release: tapToSpeak
                  ? text('learner.recording.tap_send')
                  : text('learner.recording.release'),
                requestingPermission: text(
                  'learner.recording.requesting_permission',
                ),
                resume: text('learner.recording.resume'),
                start: text('learner.recording.start'),
                stop: text('learner.recording.stop'),
              },
              continueWithout: text('learner.recording.continue_without'),
              continueWithoutExplanation: text(
                'conversation.voice.text_fallback',
              ),
              permissionExplanation: text(
                tapToSpeak
                  ? 'conversation.voice.hold_help'
                  : 'conversation.voice.permission',
              ),
              permissionContinue: text(
                'learner.recording.permission_continue',
              ),
              permissionDeniedOnce: text(
                'learner.recording.permission_denied_once',
              ),
              permissionDeniedPermanently: text(
                'learner.recording.permission_denied_permanently',
              ),
              playbackHelp: text('conversation.voice.playback_help'),
              playbackLabel: text('learner.recording.playback_label'),
              retry: text('learner.recording.retry'),
              holdRemaining: text('conversation.voice.hold_remaining', {
                seconds: '{seconds}',
              }),
              tooShort: text('conversation.voice.too_short'),
            }}
            minimumDurationMs={compact ? 400 : 1_000}
            recordingDisabled={recordingLocked}
            recordingDisabledStatus={text(
              speakLoopPhase === 'preparing'
                ? 'conversation.loop.preparing'
                : 'conversation.loop.speaking',
            )}
            onAudioCaptureChange={(capture) => {
              if (capture === null) {
                return
              }

              void uploadAndAccept(state.listing, capture)
            }}
            onCaptureChange={() => undefined}
            onCancelInFlight={cancelInFlight}
            onContinueWithoutRecording={() => onPreferTyping?.()}
            onHoldPhaseChange={(phase) => {
              if (phase === 'listening') {
                setShowInstructions(false)
                try { localStorage.setItem('ge.voice-instructions.v1', 'seen') } catch { /* In-memory preference still works. */ }
                setPhase('listening')
              } else if (phase === 'processing') {
                setPhase('processing')
              } else if (
                effectiveLoopPhase !== 'preparing' &&
                effectiveLoopPhase !== 'speaking'
              ) {
                setPhase('waiting')
              }
            }}
            recorder={recorder}
            skipReviewPlayback
          />
        </>
      ) : null}

      {state.kind === 'candidate' ? (
        <>
          <p>
            <strong>{text('conversation.voice.heard')}</strong>
          </p>
          <p lang={includeTranscriptProvenance ? (state.recording.transcript_locale === 'unknown' ? undefined : state.recording.transcript_locale) : 'en-IN'}>{includeTranscriptProvenance ? state.recording.transcript_original ?? state.recording.transcript : state.recording.transcript}</p>
          <div className="conversation-journey__voice-actions">
            <button
              onClick={() =>
                void review(
                  correctedTranscript === state.recording.transcript
                    ? 'accept'
                    : 'correct',
                  state.listing,
                  state.recording,
                )
              }
              type="button"
            >
              {text('conversation.voice.send')}
            </button>
            <button
              onClick={() =>
                void deleteCandidate(state.listing, state.recording)
              }
              type="button"
            >
              {text('learner.retry')}
            </button>
          </div>
        </>
      ) : null}

      {state.kind === 'error' ? (
        <>
          <p role="alert">{text('conversation.voice.error')}</p>
          <button
            onClick={() => {
              if (state.listing === undefined) {
                void loadConsent()
              } else if (requiredPurposesGranted(state.listing)) {
                setState({
                  kind: 'recording',
                  listing: state.listing,
                })
              } else {
                setState({
                  kind: 'consent',
                  listing: state.listing,
                })
              }
            }}
            type="button"
          >
            {text('learner.retry')}
          </button>
        </>
      ) : null}

      {state.kind === 'unavailable' ? (
        <p role="status">{text('conversation.voice.error')}</p>
      ) : null}
    </section>
  )
}

function requiredPurposesGranted(listing: VoiceConsents): boolean {
  return REQUIRED_PURPOSES.every((purpose) =>
    listing.consents.some(
      (consent) =>
        consent.purpose === purpose && consent.state === 'granted',
    ),
  )
}
