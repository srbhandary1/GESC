import type {
  MessageKey,
  SupportedLocale,
} from '@global-english/localization'
import { useRef, useState } from 'react'
import {
  defaultAudioRecorder,
  type CapturedAudio,
} from '../learner/BrowserAudioRecorder'
import { RecordingTurn } from '../learner/RecordingTurn'
import type {
  ConversationJourneyApi,
  SecureVoiceRecording,
  VoiceConsents,
} from './ConversationJourneyApi'
import { audioToPcmWav } from './audioToPcmWav'

interface VoicePracticePanelProps {
  readonly api: ConversationJourneyApi
  readonly createClientId: () => string
  readonly locale: SupportedLocale
  readonly onTranscriptSelected: (transcript: string) => void
  readonly sessionId: string
  readonly text: (key: MessageKey) => string
}

type VoicePanelState =
  | { readonly kind: 'idle' }
  | { readonly kind: 'loading' }
  | { readonly kind: 'consent'; readonly listing: VoiceConsents }
  | {
      readonly kind: 'recording'
      readonly listing: VoiceConsents
      readonly capture: CapturedAudio | null
    }
  | {
      readonly kind: 'candidate'
      readonly listing: VoiceConsents
      readonly recording: SecureVoiceRecording
    }
  | { readonly kind: 'error'; readonly listing?: VoiceConsents }

const REQUIRED_PURPOSES = [
  'voice_upload_and_transcription',
  'conversation_provider_processing',
] as const

export function VoicePracticePanel({
  api,
  createClientId,
  locale,
  onTranscriptSelected,
  sessionId,
  text,
}: VoicePracticePanelProps) {
  const [state, setState] = useState<VoicePanelState>({ kind: 'idle' })
  const [correctedTranscript, setCorrectedTranscript] = useState('')
  const uploadCommand = useRef(createClientId())
  const reviewCommand = useRef(createClientId())

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

  const loadConsent = async () => {
    setState({ kind: 'loading' })

    try {
      const listing = await api.voiceConsents!(locale)
      setState(
        requiredPurposesGranted(listing)
          ? { capture: null, kind: 'recording', listing }
          : { kind: 'consent', listing },
      )
    } catch {
      setState({ kind: 'error' })
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
      setState(
        requiredPurposesGranted(refreshed)
          ? { capture: null, kind: 'recording', listing: refreshed }
          : { kind: 'consent', listing: refreshed },
      )
    } catch {
      setState({ kind: 'error', listing })
    }
  }

  const upload = async (
    listing: VoiceConsents,
    capture: CapturedAudio,
  ) => {
    setState({ kind: 'loading' })

    try {
      const wav = await audioToPcmWav(capture)
      const recording = await api.uploadVoiceRecording!(
        sessionId,
        wav,
        uploadCommand.current,
      )

      if (
        recording.status !== 'awaiting_learner_confirmation' ||
        recording.transcript === null
      ) {
        setState({ kind: 'error', listing })
        return
      }

      setCorrectedTranscript(recording.transcript)
      setState({ kind: 'candidate', listing, recording })
    } catch {
      setState({ kind: 'error', listing })
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
      const refreshed = await api.voiceConsents!(locale)
      setState({ kind: 'consent', listing: refreshed })
    } catch {
      setState({ kind: 'error', listing })
    }
  }

  const review = async (
    action: 'accept' | 'correct' | 'discard',
    listing: VoiceConsents,
    recording: SecureVoiceRecording,
  ) => {
    setState({ kind: 'loading' })

    try {
      const result = await api.reviewVoiceTranscript!(
        recording.id,
        {
          action,
          corrected_transcript:
            action === 'correct' ? correctedTranscript.trim() : null,
        },
        reviewCommand.current,
      )

      if (action !== 'discard') {
        const selected =
          action === 'correct'
            ? correctedTranscript.trim()
            : recording.transcript

        if (selected !== null && selected !== '') {
          onTranscriptSelected(selected)
        }
      }

      reviewCommand.current = createClientId()
      uploadCommand.current = createClientId()
      setState({ capture: null, kind: 'recording', listing })

      if (result.status === 'discarded') {
        setCorrectedTranscript('')
      }
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
      setCorrectedTranscript('')
      uploadCommand.current = createClientId()
      reviewCommand.current = createClientId()
      setState({ capture: null, kind: 'recording', listing })
    } catch {
      setState({ kind: 'error', listing })
    }
  }

  return (
    <section
      aria-labelledby="secure-voice-title"
      className="conversation-journey__voice"
    >
      <h3 id="secure-voice-title">
        {text('conversation.voice.title')}
      </h3>
      <p>{text('conversation.voice.optional')}</p>

      {state.kind === 'idle' ? (
        <button onClick={() => void loadConsent()} type="button">
          {text('conversation.voice.setup')}
        </button>
      ) : null}

      {state.kind === 'loading' ? (
        <div className="ge-skeleton-status" role="status">
          <span className="ge-visually-hidden">
            {text('conversation.voice.processing')}
          </span>
          <div className="ge-skeleton ge-skeleton--line" />
          <div className="ge-skeleton ge-skeleton--line ge-skeleton--short" />
        </div>
      ) : null}

      {state.kind === 'consent' ? (
        <>
          <aside
            className="conversation-journey__voice-notice"
            lang={state.listing.notice.resolved_locale}
          >
            <strong>{text('conversation.voice.notice_draft')}</strong>
            <p>{state.listing.notice.notice}</p>
          </aside>
          {REQUIRED_PURPOSES.map((purpose) => {
            const granted = state.listing.consents.some(
              (consent) =>
                consent.purpose === purpose &&
                consent.state === 'granted',
            )

            return (
              <button
                key={purpose}
                onClick={() =>
                  void (
                    granted
                      ? withdraw(purpose, state.listing)
                      : grant(purpose, state.listing)
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
          })}
        </>
      ) : null}

      {state.kind === 'recording' ? (
        <>
          <RecordingTurn
            createClientId={createClientId}
            labels={{
              controls: {
                blocked: text('learner.recording.blocked'),
                cancel: text('learner.recording.cancel'),
                error: text('learner.recording.error'),
                idle: text('learner.recording.idle'),
                pause: text('learner.recording.pause'),
                paused: text('learner.recording.paused'),
                processing: text('learner.recording.processing'),
                ready: text('learner.recording.ready'),
                recording: text('learner.recording.recording'),
                region: text('learner.recording.region'),
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
                'conversation.voice.permission',
              ),
              playbackHelp: text('conversation.voice.playback_help'),
              playbackLabel: text('learner.recording.playback_label'),
              retry: text('learner.recording.retry'),
            }}
            onAudioCaptureChange={(capture) =>
              setState({
                capture,
                kind: 'recording',
                listing: state.listing,
              })
            }
            onCaptureChange={() => undefined}
            onContinueWithoutRecording={() =>
              setState({ kind: 'idle' })
            }
            recorder={defaultAudioRecorder}
          />
          <button
            disabled={state.capture === null}
            onClick={() => {
              if (state.capture !== null) {
                void upload(state.listing, state.capture)
              }
            }}
            type="button"
          >
            {text('conversation.voice.upload')}
          </button>
          <button
            onClick={() =>
              setState({
                kind: 'consent',
                listing: state.listing,
              })
            }
            type="button"
          >
            {text('conversation.voice.manage_consent')}
          </button>
        </>
      ) : null}

      {state.kind === 'candidate' ? (
        <>
          <label htmlFor="voice-transcript-candidate">
            {text('conversation.voice.transcript_label')}
          </label>
          <textarea
            id="voice-transcript-candidate"
            maxLength={500}
            onChange={(event) =>
              setCorrectedTranscript(event.currentTarget.value)
            }
            rows={4}
            value={correctedTranscript}
          />
          <p>
            {text('conversation.voice.confidence')}{' '}
            {Math.round(
              (state.recording.transcript_confidence ?? 0) * 100,
            )}
            %
          </p>
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
              {text('conversation.voice.use_transcript')}
            </button>
            <button
              onClick={() =>
                void review(
                  'discard',
                  state.listing,
                  state.recording,
                )
              }
              type="button"
            >
              {text('conversation.voice.discard')}
            </button>
            <button
              onClick={() =>
                void deleteCandidate(state.listing, state.recording)
              }
              type="button"
            >
              {text('conversation.voice.delete')}
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
                  capture: null,
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
