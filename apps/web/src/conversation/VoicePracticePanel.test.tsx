import { act, cleanup, render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { resolveInterfaceMessage } from '../localization/interfaceLocalization'
import type {
  ConversationJourneyApi,
  SecureVoiceRecording,
  VoiceConsents,
} from './ConversationJourneyApi'
import { VoicePracticePanel } from './VoicePracticePanel'
import * as audioToPcmWav from './audioToPcmWav'
import { encodeMonoPcmWav } from '../shared/pcmWav'
import { ANDROID_BACK_REQUEST_EVENT } from '../learner/LearnerNavigationHistory'
import * as nativeMicrophonePermission from '../auth/nativeMicrophonePermissionPlugin'

afterEach(() => {
  cleanup()
  localStorage.clear()
  vi.restoreAllMocks()
})

describe('secure voice practice consent boundary', () => {
  it('requires both distinct purposes before exposing recording controls', async () => {
    const initial = listing('not_granted', 'not_granted')
    const uploadGranted = listing('granted', 'not_granted')
    const bothGranted = listing('granted', 'granted')
    let currentListing = initial
    const api: ConversationJourneyApi = {
      createTurn: vi.fn(),
      deleteVoiceRecording: vi.fn(),
      events: vi.fn(),
      grantVoiceConsent: vi.fn(async (input) => {
        currentListing =
          input.purpose === 'voice_upload_and_transcription'
            ? uploadGranted
            : bothGranted

        return {
          effective_at: '2026-07-31T12:00:00Z',
          id: '01J00000000000000000000010',
          notice_revision: 'voice-processing-notice-2026-09-01',
          notice_sha256: 'a'.repeat(64),
          policy_version: 'recording-policy-v1',
          purpose: input.purpose,
          replayed: false,
          requested_locale: input.requested_locale,
          resolved_locale: 'en-IN' as const,
          state: 'granted' as const,
        }
      }),
      requestRealtimeSpeechTicket: vi.fn(async () => ({
        always_on: false as const,
        capture_mode: 'push_to_talk' as const,
        conversation_session_id: '01J00000000000000000000001',
        denial_reasons: ['http_enablement_not_authorized'],
        expires_at: '2026-09-09T16:30:00Z',
        full_duplex: false as const,
        preconnect_allowed: false,
        provider_dispatch: 'denied' as const,
        recovery_route: 'recorded_upload' as const,
        ticket_id: '01J00000000000000000000099',
        transport: 'recorded_recovery' as const,
        websocket_path:
          '/api/v1/conversation-sessions/01J00000000000000000000001/realtime-stt',
      })),
      reviewVoiceTranscript: vi.fn(),
      startSession: vi.fn(),
      uploadVoiceRecording: vi.fn(),
      voiceConsents: vi.fn(async () => currentListing),
      withdrawVoiceConsent: vi.fn(async (purpose, input) => {
        currentListing = uploadGranted

        return {
          effective_at: '2026-07-31T12:00:00Z',
          id: '01J00000000000000000000011',
          notice_revision: 'voice-processing-notice-2026-09-01',
          notice_sha256: 'a'.repeat(64),
          policy_version: 'recording-policy-v1',
          purpose,
          replayed: false,
          requested_locale: input.requested_locale,
          resolved_locale: 'en-IN' as const,
          state: 'withdrawn' as const,
        }
      }),
    }
    const user = userEvent.setup()
    const onProcessingConsentWithdrawn = vi.fn()

    render(
      <VoicePracticePanel
        api={api}
        createClientId={() => crypto.randomUUID()}
        locale="en-IN"
        onProcessingConsentWithdrawn={onProcessingConsentWithdrawn}
        onTranscriptSelected={vi.fn()}
        sessionId="01J00000000000000000000001"
        text={(key) => key}
      />,
    )

    expect(
      (await screen.findByText(initial.notice.notice)).closest('aside'),
    ).toHaveAttribute('lang', 'en-IN')

    await user.click(
      screen.getByRole('button', {
        name: 'conversation.voice.consent_both',
      }),
    )
    expect(
      await screen.findByRole('heading', {
        name: 'learner.recording.region',
      }),
    ).toBeInTheDocument()
    expect(
      screen.queryByRole('button', {
        name: 'conversation.voice.upload',
      }),
    ).not.toBeInTheDocument()
    expect(api.grantVoiceConsent).toHaveBeenNthCalledWith(
      1,
      {
        purpose: 'voice_upload_and_transcription',
        requested_locale: 'en-IN',
      },
      expect.any(String),
    )
    expect(api.grantVoiceConsent).toHaveBeenNthCalledWith(
      2,
      {
        purpose: 'conversation_provider_processing',
        requested_locale: 'en-IN',
      },
      expect.any(String),
    )
    await waitFor(() => {
      expect(api.requestRealtimeSpeechTicket).toHaveBeenCalledWith(
        '01J00000000000000000000001',
        expect.any(String),
        undefined,
      )
    })

    expect(screen.queryByText('conversation.voice.manage_consent')).not.toBeInTheDocument()
    expect(api.withdrawVoiceConsent).not.toHaveBeenCalled()
  })

  it('opens Type instead when the learner continues without recording', async () => {
    const bothGranted = listing('granted', 'granted')
    const onPreferTyping = vi.fn()
    const api: ConversationJourneyApi = {
      createTurn: vi.fn(),
      deleteVoiceRecording: vi.fn(),
      events: vi.fn(),
      grantVoiceConsent: vi.fn(),
      reviewVoiceTranscript: vi.fn(),
      startSession: vi.fn(),
      uploadVoiceRecording: vi.fn(),
      voiceConsents: vi.fn(async () => bothGranted),
      withdrawVoiceConsent: vi.fn(),
    }
    const user = userEvent.setup()

    render(
      <VoicePracticePanel
        api={api}
        createClientId={() => crypto.randomUUID()}
        locale="en-IN"
        onPreferTyping={onPreferTyping}
        onTranscriptSelected={vi.fn()}
        sessionId="01J00000000000000000000001"
        text={(key) => key}
      />,
    )

    await user.click(
      await screen.findByRole('button', {
        name: 'learner.recording.continue_without',
      }),
    )
    expect(onPreferTyping).toHaveBeenCalledOnce()
  })

  it('renders hold-remaining copy without crashing once recording is available', async () => {
    const bothGranted = listing('granted', 'granted')
    const api: ConversationJourneyApi = {
      createTurn: vi.fn(),
      deleteVoiceRecording: vi.fn(),
      events: vi.fn(),
      grantVoiceConsent: vi.fn(),
      reviewVoiceTranscript: vi.fn(),
      startSession: vi.fn(),
      uploadVoiceRecording: vi.fn(),
      voiceConsents: vi.fn(async () => bothGranted),
      withdrawVoiceConsent: vi.fn(),
    }
    const text = (key: Parameters<typeof resolveInterfaceMessage>[0], parameters = {}) =>
      resolveInterfaceMessage(
        key,
        'kn-IN',
        'development-preview',
        parameters,
      ).text

    render(
      <VoicePracticePanel
        api={api}
        createClientId={() => crypto.randomUUID()}
        locale="kn-IN"
        onTranscriptSelected={vi.fn()}
        sessionId="01J00000000000000000000001"
        text={text}
      />,
    )

    await waitFor(() =>
      expect(
        screen.getByRole('heading', { name: text('learner.recording.region') }),
      ).toBeInTheDocument(),
    )
  })

  it('treats a closed voice gate as an honest status, not a failure alert', async () => {
    const api: ConversationJourneyApi = {
      createTurn: vi.fn(),
      deleteVoiceRecording: vi.fn(),
      events: vi.fn(),
      grantVoiceConsent: vi.fn(),
      reviewVoiceTranscript: vi.fn(),
      startSession: vi.fn(),
      uploadVoiceRecording: vi.fn(),
      voiceConsents: vi.fn(async () => {
        throw new Error('voice_gate_closed')
      }),
      withdrawVoiceConsent: vi.fn(),
    }
    render(
      <VoicePracticePanel
        api={api}
        createClientId={() => crypto.randomUUID()}
        locale="en-IN"
        onTranscriptSelected={vi.fn()}
        sessionId="01J00000000000000000000001"
        text={(key) => key}
      />,
    )

    expect(
      await screen.findByRole('status'),
    ).toHaveTextContent('conversation.voice.error')
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
    expect(
      screen.queryByRole('button', { name: 'conversation.voice.setup' }),
    ).not.toBeInTheDocument()
  })

  it('aborts an in-flight upload when hold cancel is pressed', async () => {
    const bothGranted = listing('granted', 'granted')
    let uploadSignal: AbortSignal | undefined
    const api: ConversationJourneyApi = {
      createTurn: vi.fn(),
      deleteVoiceRecording: vi.fn(),
      events: vi.fn(),
      grantVoiceConsent: vi.fn(),
      reviewVoiceTranscript: vi.fn(),
      startSession: vi.fn(),
      uploadVoiceRecording: vi.fn((_sessionId, _audio, _command, signal) => {
        uploadSignal = signal
        return new Promise<SecureVoiceRecording>(() => undefined)
      }),
      voiceConsents: vi.fn(async () => bothGranted),
      withdrawVoiceConsent: vi.fn(),
    }
    const session = {
      cancel: vi.fn(),
      pause: vi.fn(),
      resume: vi.fn(),
      stop: vi.fn(async () => ({
        blob: new Blob([encodeMonoPcmWav(new Float32Array(4_000))], {
          type: 'audio/wav',
        }),
        durationMs: 1_250,
        mimeType: 'audio/webm',
      })),
    }
    const recorder = {
      isSupported: () => true,
      start: vi.fn(async () => session),
    }
    const user = userEvent.setup()

    render(
      <VoicePracticePanel
        api={api}
        createClientId={() => 'voice-command'}
        locale="en-IN"
        onTranscriptSelected={vi.fn()}
        recorder={recorder}
        sessionId="01J00000000000000000000001"
        text={(key) => key}
      />,
    )

    const hold = await screen.findByRole('button', {
      name: 'learner.recording.hold',
    })
    await user.pointer({ keys: '[MouseLeft>]', target: hold })
    await screen.findByRole('button', {
      name: 'learner.recording.release',
    })
    await user.pointer({ keys: '[/MouseLeft]', target: hold })
    const send = screen.getByRole('button', {
      name: 'learner.recording.release',
    })
    await user.pointer({ keys: '[MouseLeft>]', target: send })
    await user.pointer({ keys: '[/MouseLeft]', target: send })
    await vi.waitFor(() => {
      expect(api.uploadVoiceRecording).toHaveBeenCalled()
    })
    await user.click(
      screen.getByRole('button', { name: 'learner.recording.cancel' }),
    )
    expect(uploadSignal?.aborted).toBe(true)
  })

  it('keeps the Android Back cancellation explanation after resetting the parent loop', async () => {
    const api = {
      createTurn: vi.fn(),
      deleteVoiceRecording: vi.fn(),
      events: vi.fn(),
      grantVoiceConsent: vi.fn(),
      reviewVoiceTranscript: vi.fn(),
      startSession: vi.fn(),
      uploadVoiceRecording: vi.fn(),
      voiceConsents: vi.fn(async () => listing('granted', 'granted')),
      withdrawVoiceConsent: vi.fn(),
    } satisfies ConversationJourneyApi
    const session = {
      cancel: vi.fn(),
      pause: vi.fn(),
      resume: vi.fn(),
      stop: vi.fn(),
    }
    const recorder = {
      isSupported: () => true,
      start: vi.fn(async () => session),
    }
    const user = userEvent.setup()

    render(
      <VoicePracticePanel
        api={api}
        createClientId={() => 'voice-command'}
        locale="en-IN"
        onTranscriptSelected={vi.fn()}
        recorder={recorder}
        sessionId="01J00000000000000000000001"
        text={(key) => key}
      />,
    )

    const hold = await screen.findByRole('button', {
      name: 'learner.recording.hold',
    })
    await user.pointer({ keys: '[MouseLeft>]', target: hold })
    await screen.findByRole('button', {
      name: 'learner.recording.release',
    })

    act(() => {
      globalThis.dispatchEvent(
        new CustomEvent(ANDROID_BACK_REQUEST_EVENT, { cancelable: true }),
      )
    })

    expect(session.cancel).toHaveBeenCalledOnce()
    expect(
      screen.getByText('learner.recording.back_cancelled'),
    ).toBeVisible()
    expect(
      screen.getByRole('button', { name: 'learner.recording.hold' }),
    ).toBeEnabled()
    expect(api.uploadVoiceRecording).not.toHaveBeenCalled()
  })

  it.each([[false, 0.92], [true, 0.92], [false, null], [true, null]] as const)('records with matching instructions and reviews unknown confidence (Android: %s, confidence: %s)', async (nativeAndroid, confidence) => {
    vi.spyOn(nativeMicrophonePermission, 'isNativeAndroid').mockReturnValue(nativeAndroid)
    vi.spyOn(audioToPcmWav, 'audioToPcmWav').mockResolvedValue(
      new Blob(['RIFF'.padEnd(128, 'x')], { type: 'audio/wav' }),
    )
    const bothGranted = listing('granted', 'granted')
    const onTranscriptSelected = vi.fn()
    const recording = {
      byte_size: 128,
      codec: 'pcm_s16le',
      created_at: '2026-07-31T12:00:00Z',
      deletion_url:
        '/api/v1/voice-recordings/01JRECORDING000000000000001/deletion',
      duration_ms: 250,
      id: '01JRECORDING000000000000001',
      mime_type: 'audio/wav',
      replayed: false,
      source_deleted: true,
      status: 'awaiting_learner_confirmation',
      transcript: 'I practise English after work.',
      transcript_original: 'I practise English after work.',
      transcript_locale: 'en-IN',
      transcript_source: 'original_speech',
      transcript_confidence: confidence,
      transcript_review_url:
        '/api/v1/voice-recordings/01JRECORDING000000000000001/transcript-review',
    } as const satisfies SecureVoiceRecording
    const api: ConversationJourneyApi = {
      createTurn: vi.fn(),
      deleteVoiceRecording: vi.fn(),
      events: vi.fn(),
      grantVoiceConsent: vi.fn(),
      reviewVoiceTranscript: vi.fn(async () => ({
        ...recording,
        status: 'confirmed' as const,
      })),
      startSession: vi.fn(),
      uploadVoiceRecording: vi.fn(async () => recording),
      voiceConsents: vi.fn(async () => bothGranted),
      withdrawVoiceConsent: vi.fn(),
    }
    const session = {
      cancel: vi.fn(),
      pause: vi.fn(),
      resume: vi.fn(),
      stop: vi.fn(async () => ({
        blob: new Blob(['RIFF'.padEnd(128, 'x')], { type: 'audio/webm' }),
        durationMs: 1_250,
        mimeType: 'audio/webm',
      })),
    }
    const recorder = {
      isSupported: () => true,
      start: vi.fn(async () => session),
    }
    const user = userEvent.setup()

    render(
      <VoicePracticePanel
        api={api}
        createClientId={() => 'voice-command'}
        locale="en-IN"
        onTranscriptSelected={onTranscriptSelected}
        includeTranscriptProvenance={nativeAndroid || confidence === null}
        requireTranscriptConfirmation
        recorder={recorder}
        sessionId="01J00000000000000000000001"
        text={(key) => key}
      />,
    )

    const record = await screen.findByRole('button', {
      name: nativeAndroid ? 'learner.recording.tap_speak' : 'learner.recording.hold',
    })
    expect(screen.getByText(
      nativeAndroid ? 'conversation.voice.hold_help' : 'conversation.voice.permission',
    )).toBeVisible()
    expect(screen.queryByText(
      nativeAndroid ? 'conversation.voice.permission' : 'conversation.voice.hold_help',
    )).not.toBeInTheDocument()
    if (nativeAndroid) {
      expect(screen.queryByText('conversation.voice.title')).not.toBeInTheDocument()
      await user.click(record)
      const send = await screen.findByRole('button', { name: 'learner.recording.tap_send' })
      expect(session.stop).not.toHaveBeenCalled()
      expect(api.uploadVoiceRecording).not.toHaveBeenCalled()
      await user.click(send)
    } else {
      record.focus()
      await user.keyboard('[Space>]')
      await screen.findByRole('button', { name: 'learner.recording.release' })
      expect(session.stop).not.toHaveBeenCalled()
      await user.keyboard('[/Space]')
    }

    if (confidence === null) {
      const confirm = await screen.findByRole('button', { name: 'conversation.voice.send' })
      expect(onTranscriptSelected).not.toHaveBeenCalled()
      expect(api.reviewVoiceTranscript).not.toHaveBeenCalled()
      expect(screen.queryByText('conversation.voice.confidence')).not.toBeInTheDocument()
      expect(screen.queryByText('0%')).not.toBeInTheDocument()
      expect(screen.getByText(recording.transcript)).toHaveAttribute('lang', 'en-IN')
      await user.click(confirm)
    }
    await waitFor(() => {
      if (nativeAndroid || confidence === null) {
        expect(onTranscriptSelected).toHaveBeenCalledWith('I practise English after work.', {
          recordingId: recording.id, locale: 'en-IN', source: 'original_speech',
        })
      } else {
        expect(onTranscriptSelected).toHaveBeenCalledWith('I practise English after work.')
      }
    })
    expect(session.stop).toHaveBeenCalledOnce()
    expect(api.uploadVoiceRecording).toHaveBeenCalledOnce()
    expect(api.reviewVoiceTranscript).toHaveBeenCalledWith(
      recording.id,
      { action: 'accept', corrected_transcript: null },
      'voice-command',
      confidence === null ? undefined : expect.any(AbortSignal),
    )
    if (confidence !== null) {
      expect(screen.getByRole('button', { name: 'learner.recording.processing' })).toBeDisabled()
    } else {
      expect(screen.getByRole('status')).toHaveTextContent('conversation.loop.preparing')
    }
    expect(
      screen.queryByRole('button', { name: 'conversation.voice.use_transcript' }),
    ).not.toBeInTheDocument()
  })

  it('auto-accepts grammar-drill clips for learners without a confirmation step', async () => {
    vi.spyOn(audioToPcmWav, 'audioToPcmWav').mockResolvedValue(
      new Blob(['RIFF'.padEnd(128, 'x')], { type: 'audio/wav' }),
    )
    const bothGranted = listing('granted', 'granted')
    const onTranscriptSelected = vi.fn()
    const recording = {
      byte_size: 128,
      codec: 'pcm_s16le',
      created_at: '2026-07-31T12:00:00Z',
      deletion_url:
        '/api/v1/voice-recordings/01JRECORDING000000000000001/deletion',
      duration_ms: 250,
      id: '01JRECORDING000000000000001',
      mime_type: 'audio/wav',
      replayed: false,
      source_deleted: true,
      status: 'awaiting_learner_confirmation',
      transcript: 'I can drink water.',
      transcript_confidence: 0.92,
      transcript_review_url:
        '/api/v1/voice-recordings/01JRECORDING000000000000001/transcript-review',
    } as const satisfies SecureVoiceRecording
    const api: ConversationJourneyApi = {
      createTurn: vi.fn(),
      deleteVoiceRecording: vi.fn(),
      events: vi.fn(),
      grantVoiceConsent: vi.fn(),
      reviewVoiceTranscript: vi.fn(async () => ({
        ...recording,
        status: 'confirmed' as const,
      })),
      startSession: vi.fn(),
      uploadVoiceRecording: vi.fn(async () => recording),
      voiceConsents: vi.fn(async () => bothGranted),
      withdrawVoiceConsent: vi.fn(),
    }
    const session = {
      cancel: vi.fn(),
      pause: vi.fn(),
      resume: vi.fn(),
      stop: vi.fn(async () => ({
        blob: new Blob(['RIFF'.padEnd(128, 'x')], { type: 'audio/webm' }),
        durationMs: 1_250,
        mimeType: 'audio/webm',
      })),
    }
    const recorder = {
      isSupported: () => true,
      start: vi.fn(async () => session),
    }
    const user = userEvent.setup()

    render(
      <VoicePracticePanel
        api={api}
        createClientId={() => 'voice-command'}
        locale="en-IN"
        onTranscriptSelected={onTranscriptSelected}
        recorder={recorder}
        sessionId="01J00000000000000000000001"
        text={(key) => key}
      />,
    )

    const hold = await screen.findByRole('button', {
      name: 'learner.recording.hold',
    })
    await user.pointer({ keys: '[MouseLeft>]', target: hold })
    await user.pointer({ keys: '[/MouseLeft]', target: hold })
    const send = screen.getByRole('button', {
      name: 'learner.recording.release',
    })
    await user.pointer({ keys: '[MouseLeft>]', target: send })
    await user.pointer({ keys: '[/MouseLeft]', target: send })

    await waitFor(() => {
      expect(onTranscriptSelected).toHaveBeenCalledWith('I can drink water.')
    })
    expect(api.reviewVoiceTranscript).toHaveBeenCalled()
    expect(
      screen.queryByRole('button', {
        name: 'conversation.voice.use_transcript',
      }),
    ).not.toBeInTheDocument()
  })

  it('disables hold-to-talk while Swathi is speaking', async () => {
    const bothGranted = listing('granted', 'granted')
    const api: ConversationJourneyApi = {
      createTurn: vi.fn(),
      deleteVoiceRecording: vi.fn(),
      events: vi.fn(),
      grantVoiceConsent: vi.fn(),
      reviewVoiceTranscript: vi.fn(),
      startSession: vi.fn(),
      uploadVoiceRecording: vi.fn(),
      voiceConsents: vi.fn(async () => bothGranted),
      withdrawVoiceConsent: vi.fn(),
    }

    render(
      <VoicePracticePanel
        api={api}
        createClientId={() => 'voice-command'}
        locale="en-IN"
        onTranscriptSelected={vi.fn()}
        sessionId="01J00000000000000000000001"
        speakLoopPhase="speaking"
        text={(key) => key}
      />,
    )

    const hold = await screen.findByRole('button', {
      name: 'learner.recording.hold',
    })
    expect(hold).toBeDisabled()
    expect(screen.getByText('conversation.loop.speaking')).toBeInTheDocument()
  })

  it('restores the record control with a fresh command after an upload rejection', async () => {
    vi.spyOn(audioToPcmWav, 'audioToPcmWav').mockResolvedValue(
      new Blob([encodeMonoPcmWav(new Float32Array(4_000))], {
        type: 'audio/wav',
      }),
    )
    const bothGranted = listing('granted', 'granted')
    const uploadCommands: string[] = []
    const uploadVoiceRecording: NonNullable<
      ConversationJourneyApi['uploadVoiceRecording']
    > = async (sessionId, audio, commandId) => {
      void sessionId
      void audio
      uploadCommands.push(commandId)
      throw new Error('rejected')
    }
    const api: ConversationJourneyApi = {
      createTurn: vi.fn(),
      deleteVoiceRecording: vi.fn(),
      events: vi.fn(),
      grantVoiceConsent: vi.fn(),
      reviewVoiceTranscript: vi.fn(),
      startSession: vi.fn(),
      uploadVoiceRecording,
      voiceConsents: vi.fn(async () => bothGranted),
      withdrawVoiceConsent: vi.fn(),
    }
    const recorder = {
      isSupported: () => true,
      start: vi.fn(async () => ({
        cancel: vi.fn(),
        pause: vi.fn(),
        resume: vi.fn(),
        stop: vi.fn(async () => ({
          blob: new Blob([encodeMonoPcmWav(new Float32Array(4_000))], {
            type: 'audio/wav',
          }),
          durationMs: 1_250,
          mimeType: 'audio/webm',
        })),
      })),
    }
    const user = userEvent.setup()
    let commandNumber = 0

    render(
      <VoicePracticePanel
        api={api}
        createClientId={() => `voice-command-${++commandNumber}`}
        locale="en-IN"
        onTranscriptSelected={vi.fn()}
        recorder={recorder}
        sessionId="01J00000000000000000000001"
        text={(key) => key}
      />,
    )

    const hold = await screen.findByRole('button', {
      name: 'learner.recording.hold',
    })
    await user.pointer({ keys: '[MouseLeft>]', target: hold })
    await user.pointer({ keys: '[/MouseLeft]', target: hold })
    const send = await screen.findByRole('button', {
      name: 'learner.recording.release',
    })
    await user.pointer({ keys: '[MouseLeft>]', target: send })
    await user.pointer({ keys: '[/MouseLeft]', target: send })

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'conversation.voice.error',
    )
    expect(
      screen.getByRole('button', { name: 'learner.recording.hold' }),
    ).toBeEnabled()

    const retryHold = screen.getByRole('button', {
      name: 'learner.recording.hold',
    })
    await user.pointer({ keys: '[MouseLeft>]', target: retryHold })
    await user.pointer({ keys: '[/MouseLeft]', target: retryHold })
    const retrySend = await screen.findByRole('button', {
      name: 'learner.recording.release',
    })
    await user.pointer({ keys: '[MouseLeft>]', target: retrySend })
    await user.pointer({ keys: '[/MouseLeft]', target: retrySend })

    await waitFor(() => {
      expect(uploadCommands).toHaveLength(2)
    })
    expect(new Set(uploadCommands).size).toBe(2)
  })

  it('keeps retry available when STT returns a recoverable failed recording', async () => {
    vi.spyOn(audioToPcmWav, 'audioToPcmWav').mockResolvedValue(
      new Blob([encodeMonoPcmWav(new Float32Array(4_000))], {
        type: 'audio/wav',
      }),
    )
    const bothGranted = listing('granted', 'granted')
    const failedRecording = {
      byte_size: 8_044,
      codec: 'pcm_s16le',
      created_at: '2026-09-05T16:42:39Z',
      deletion_url:
        '/api/v1/voice-recordings/01M1S76R68W7N8X7W0XSN8AR0Y/deletion',
      duration_ms: 512,
      id: '01M1S76R68W7N8X7W0XSN8AR0Y',
      mime_type: 'audio/wav',
      replayed: false,
      source_deleted: false,
      status: 'provider_failed_recoverable',
      transcript: null,
      transcript_confidence: null,
      transcript_review_url:
        '/api/v1/voice-recordings/01M1S76R68W7N8X7W0XSN8AR0Y/transcript-review',
    } as const satisfies SecureVoiceRecording
    const uploadCommands: string[] = []
    const uploadVoiceRecording: NonNullable<
      ConversationJourneyApi['uploadVoiceRecording']
    > = async (sessionId, audio, commandId) => {
      void sessionId
      void audio
      uploadCommands.push(commandId)
      return failedRecording
    }
    const api: ConversationJourneyApi = {
      createTurn: vi.fn(),
      deleteVoiceRecording: vi.fn(),
      events: vi.fn(),
      grantVoiceConsent: vi.fn(),
      reviewVoiceTranscript: vi.fn(),
      startSession: vi.fn(),
      uploadVoiceRecording,
      voiceConsents: vi.fn(async () => bothGranted),
      withdrawVoiceConsent: vi.fn(),
    }
    const recorder = {
      isSupported: () => true,
      start: vi.fn(async () => ({
        cancel: vi.fn(),
        pause: vi.fn(),
        resume: vi.fn(),
        stop: vi.fn(async () => ({
          blob: new Blob([encodeMonoPcmWav(new Float32Array(4_000))], {
            type: 'audio/wav',
          }),
          durationMs: 1_250,
          mimeType: 'audio/webm',
        })),
      })),
    }
    const user = userEvent.setup()
    let commandNumber = 0

    render(
      <VoicePracticePanel
        api={api}
        createClientId={() => `voice-command-${++commandNumber}`}
        locale="en-IN"
        onTranscriptSelected={vi.fn()}
        recorder={recorder}
        sessionId="01J00000000000000000000001"
        text={(key) => key}
      />,
    )

    const hold = await screen.findByRole('button', {
      name: 'learner.recording.hold',
    })
    await user.pointer({ keys: '[MouseLeft>]', target: hold })
    await user.pointer({ keys: '[/MouseLeft]', target: hold })
    const send = await screen.findByRole('button', {
      name: 'learner.recording.release',
    })
    await user.pointer({ keys: '[MouseLeft>]', target: send })
    await user.pointer({ keys: '[/MouseLeft]', target: send })

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'conversation.voice.error',
    )
    expect(
      screen.getByRole('button', { name: 'learner.recording.hold' }),
    ).toBeEnabled()

    const retryHold = screen.getByRole('button', {
      name: 'learner.recording.hold',
    })
    await user.pointer({ keys: '[MouseLeft>]', target: retryHold })
    await user.pointer({ keys: '[/MouseLeft]', target: retryHold })
    const retrySend = await screen.findByRole('button', {
      name: 'learner.recording.release',
    })
    await user.pointer({ keys: '[MouseLeft>]', target: retrySend })
    await user.pointer({ keys: '[/MouseLeft]', target: retrySend })

    await waitFor(() => {
      expect(uploadCommands).toHaveLength(2)
    })
    expect(new Set(uploadCommands).size).toBe(2)
  })

  it('shows demo exhaustion and does not expose recording controls', async () => {
    const api = {
      createTurn: vi.fn(),
      deleteVoiceRecording: vi.fn(),
      events: vi.fn(),
      grantVoiceConsent: vi.fn(),
      reviewVoiceTranscript: vi.fn(),
      startSession: vi.fn(),
      uploadVoiceRecording: vi.fn(),
      voiceConsents: vi.fn(async () => listing('granted', 'granted')),
      withdrawVoiceConsent: vi.fn(),
    } satisfies ConversationJourneyApi

    render(
      <VoicePracticePanel
        api={api}
        createClientId={() => 'voice-command'}
        locale="en-IN"
        onTranscriptSelected={vi.fn()}
        sessionId="01J00000000000000000000001"
        text={(key) => key}
        unavailableMessageKey="entitlement.demo_ai_exhausted"
      />,
    )

    expect(screen.getByRole('alert')).toHaveTextContent(
      'entitlement.demo_ai_exhausted',
    )
    expect(
      screen.queryByRole('button', { name: 'learner.recording.hold' }),
    ).not.toBeInTheDocument()
  })
})

function listing(
  upload: 'granted' | 'not_granted',
  provider: 'granted' | 'not_granted',
): VoiceConsents {
  return {
    consents: [
      {
        effective_at: upload === 'granted'
          ? '2026-07-31T12:00:00Z'
          : null,
        purpose: 'voice_upload_and_transcription',
        state: upload,
      },
      {
        effective_at: provider === 'granted'
          ? '2026-07-31T12:00:00Z'
          : null,
        purpose: 'conversation_provider_processing',
        state: provider,
      },
      {
        effective_at: null,
        purpose: 'transcript_history',
        state: 'not_granted',
      },
    ],
    notice: {
      history_optional: true,
      notice:
        'Implementation draft: audio is uploaded privately and deleted after transcription.',
      notice_revision: 'voice-processing-notice-2026-09-01',
      notice_sha256: 'a'.repeat(64),
      policy_version: 'recording-policy-v1',
      purposes: [
        'voice_upload_and_transcription',
        'conversation_provider_processing',
        'transcript_history',
      ],
      requested_locale: 'en-IN',
      resolved_locale: 'en-IN',
      review_status: 'implementation_draft',
    },
  }
}
