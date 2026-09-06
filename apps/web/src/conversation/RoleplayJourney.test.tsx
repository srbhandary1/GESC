import { cleanup, render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { defaultAudioRecorder } from '../learner/BrowserAudioRecorder'
import { encodeMonoPcmWav } from '../shared/pcmWav'
import type {
  ConversationJourneyApi,
  FriendSession,
  FriendTurn,
  SecureVoiceRecording,
  VoiceConsents,
} from './ConversationJourneyApi'
import { RoleplayJourney } from './RoleplayJourney'
import * as audioToPcmWav from './audioToPcmWav'

afterEach(() => {
  cleanup()
  vi.restoreAllMocks()
})

describe('synthetic Role-play journey', () => {
  it('discloses synthetic_unreviewed and advances Observe→Reflect', async () => {
    const observe = fakeSession('observe')
    const rehearse = fakeSession('rehearse')
    const perform = fakeSession('perform')
    const reflect = fakeSession('reflect', true)
    const advanceStage = vi
      .fn()
      .mockResolvedValueOnce(rehearse)
      .mockResolvedValueOnce(perform)
      .mockResolvedValueOnce(reflect)
    const createTurn = vi.fn(async () => fakeTurn(perform.id))
    const api: ConversationJourneyApi = {
      startSession: vi.fn(async () => observe),
      advanceStage,
      createTurn,
      events: vi.fn(),
    }
    const createClientId = vi
      .fn()
      .mockReturnValueOnce('start-command')
      .mockReturnValueOnce('advance-1')
      .mockReturnValueOnce('advance-2')
      .mockReturnValueOnce('perform-turn')
      .mockReturnValueOnce('advance-3')
    const user = userEvent.setup()
    const onLiveAiUsageChanged = vi.fn()

    render(
      <RoleplayJourney
        api={api}
        createClientId={createClientId}
        locale="en-IN"
        localizationPolicy="development-preview"
        onLiveAiUsageChanged={onLiveAiUsageChanged}
      />,
    )

    expect(
      screen.getByText(
        'This is a deterministic synthetic Role-play for local testing. It is not approved curriculum or a human.',
      ),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('region', { name: 'Role-play practice' }),
    ).toHaveAttribute('data-review-status', 'synthetic_unreviewed')

    expect(
      screen.getByLabelText('Choose a Role-play scenario'),
    ).toBeInTheDocument()

    await user.click(
      screen.getByRole('button', { name: 'Start this situation' }),
    )
    expect(
      await screen.findByRole('heading', { name: /^Observe$/ }),
    ).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Continue' }))
    expect(
      await screen.findByRole('heading', { name: /^Rehearse$/ }),
    ).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Continue' }))
    expect(
      await screen.findByRole('heading', { name: /^Perform$/ }),
    ).toBeInTheDocument()
    expect(screen.getByText('Greet the receptionist')).toBeInTheDocument()
    expect(screen.getByText('Hear an example')).toBeInTheDocument()
    expect(api.startSession).toHaveBeenCalledWith(
      {
        coaching_mode: 'gentle',
        persona_key: 'clinic-reception-roleplay-prototype',
        scenario_key: 'clinic-reception-roleplay-scenario',
        ui_locale: 'en-IN',
      },
      'start-command',
    )

    await user.click(screen.getByRole('button', { name: 'Type instead' }))
    await user.type(
      screen.getByLabelText('Type one English practice turn'),
      'Hello, I have an appointment.',
    )
    await user.click(screen.getByRole('button', { name: 'Send turn' }))
    expect(createTurn).toHaveBeenCalledWith(
      perform.id,
      { prototype_utterance: 'Hello, I have an appointment.' },
      'perform-turn',
    )
    expect(
      await screen.findByText('Hello, I have an appointment.'),
    ).toBeInTheDocument()
    expect(
      screen.getByText('Welcome. May I have your name and appointment time?'),
    ).toBeInTheDocument()
    expect(onLiveAiUsageChanged).toHaveBeenCalledOnce()
    await user.click(await screen.findByRole('button', { name: 'Continue' }))
    expect(
      await screen.findByRole('heading', { name: 'Reflect' }),
    ).toBeInTheDocument()
    expect(screen.getByText('Advisory feedback')).toBeInTheDocument()
    expect(screen.getByText('Greet the receptionist')).toBeInTheDocument()
    expect(screen.getByText('Met')).toBeInTheDocument()
    expect(screen.getByText('Not yet met')).toBeInTheDocument()
    expect(screen.queryByText('cccccccccccc')).not.toBeInTheDocument()
    expect(advanceStage).toHaveBeenNthCalledWith(
      1,
      observe.id,
      { to_stage: 'rehearse' },
      'advance-1',
    )
    expect(advanceStage).toHaveBeenNthCalledWith(
      3,
      perform.id,
      { to_stage: 'reflect' },
      'advance-3',
    )
  })

  it('restores the record control after every completed voice turn', async () => {
    vi.spyOn(audioToPcmWav, 'audioToPcmWav').mockResolvedValue(
      new Blob([encodeMonoPcmWav(new Float32Array(4_000))], {
        type: 'audio/wav',
      }),
    )
    vi.spyOn(defaultAudioRecorder, 'isSupported').mockReturnValue(true)
    vi.spyOn(defaultAudioRecorder, 'start').mockImplementation(async () => ({
      cancel: vi.fn(),
      pause: vi.fn(),
      resume: vi.fn(),
      stop: vi.fn(async () => ({
        blob: new Blob([encodeMonoPcmWav(new Float32Array(4_000))], {
          type: 'audio/wav',
        }),
        durationMs: 250,
        mimeType: 'audio/webm',
      })),
    }))

    const observe = fakeSession('observe')
    const rehearse = fakeSession('rehearse')
    const perform = fakeSession('perform')
    let recordingNumber = 0
    let turnNumber = 0
    const api: ConversationJourneyApi = {
      createTurn: vi.fn(async () => ({
        ...fakeTurn(perform.id),
        id: `01J0000000000000000000002${turnNumber}`,
        turn_number: ++turnNumber,
      })),
      deleteVoiceRecording: vi.fn(),
      events: vi.fn(),
      grantVoiceConsent: vi.fn(),
      reviewVoiceTranscript: vi.fn(async () => ({
        ...voiceRecording(recordingNumber),
        status: 'confirmed' as const,
      })),
      startSession: vi.fn(async () => observe),
      advanceStage: vi
        .fn()
        .mockResolvedValueOnce(rehearse)
        .mockResolvedValueOnce(perform),
      uploadVoiceRecording: vi.fn(async () => {
        recordingNumber += 1
        return voiceRecording(recordingNumber)
      }),
      voiceConsents: vi.fn(async () => grantedVoiceConsents()),
      withdrawVoiceConsent: vi.fn(),
    }
    const user = userEvent.setup()

    render(
      <RoleplayJourney
        api={api}
        createClientId={() => 'voice-command'}
        locale="kn-IN"
        localizationPolicy="development-preview"
      />,
    )

    await user.click(
      screen.getByRole('button', { name: 'ಈ ಪರಿಸ್ಥಿತಿ ಪ್ರಾರಂಭಿಸಿ' }),
    )

    await user.click(await screen.findByRole('button', { name: 'ಮುಂದುವರಿಸಿ' }))
    await user.click(await screen.findByRole('button', { name: 'ಮುಂದುವರಿಸಿ' }))

    for (let expectedTurn = 1; expectedTurn <= 2; expectedTurn += 1) {
      const hold = await screen.findByRole('button', {
        name: 'ಒತ್ತಿ ಮಾತನಾಡಿ',
      })
      await user.pointer({ keys: '[MouseLeft>]', target: hold })
      await screen.findByRole('button', {
        name: 'ಬಿಟ್ಟರೆ ಕಳುಹಿಸಿ',
      })
      await user.pointer({ keys: '[/MouseLeft]', target: hold })

      await user.click(
        await screen.findByRole('button', { name: 'ಈ ಇಂಗ್ಲಿಷ್ ಬಳಸಿ' }),
      )

      await waitFor(() => {
        expect(api.createTurn).toHaveBeenCalledTimes(expectedTurn)
      })
      expect(
        await screen.findByRole('button', {
          name: 'ಒತ್ತಿ ಮಾತನಾಡಿ',
        }),
      ).toBeEnabled()
    }
  })
})

function fakeSession(
  stage: 'observe' | 'rehearse' | 'perform' | 'reflect',
  withFeedback = false,
): FriendSession {
  return {
    coaching_mode: 'gentle',
    id: '01J00000000000000000000011',
    mode: 'roleplay',
    stage,
    persona: {
      definition_hash: 'a'.repeat(64),
      disclosure:
        'I am a deterministic AI role-play prototype, not a human.',
      display_name: 'Clinic Reception Role-play Prototype',
      id: '01J00000000000000000000012',
      key: 'clinic-reception-roleplay-prototype',
      review_status: 'synthetic_unreviewed',
      version: 1,
      version_id: '01J00000000000000000000013',
    },
    replayed: false,
    scenario: {
      definition_hash: 'b'.repeat(64),
      id: '01J00000000000000000000014',
      key: 'clinic-reception-roleplay-scenario',
      review_status: 'synthetic_unreviewed',
      topic_code: 'clinic-reception',
      version: 1,
      version_id: '01J00000000000000000000015',
    },
    objectives: [
      {
        completion_criteria_code: 'synthetic.greet-receptionist.v1',
        id: '01J00000000000000000000016',
        key: 'greet-receptionist',
        title: 'Greet the receptionist',
      },
      {
        completion_criteria_code: 'synthetic.state-appointment-reason.v1',
        id: '01J00000000000000000000017',
        key: 'state-appointment-reason',
        title: 'State the appointment reason',
      },
    ],
    feedback: withFeedback
      ? [
          {
            completion_state: 'met',
            evidence_digest: 'c'.repeat(64),
            id: '01J00000000000000000000018',
            scenario_objective_id: '01J00000000000000000000016',
          },
          {
            completion_state: 'unmet',
            evidence_digest: 'd'.repeat(64),
            id: '01J00000000000000000000019',
            scenario_objective_id: '01J00000000000000000000017',
          },
        ]
      : [],
    status: 'active',
    ui_locale: 'en-IN',
  }
}

function fakeTurn(sessionId: string): FriendTurn {
  return {
    assistant_reply: 'Welcome. May I have your name and appointment time?',
    conversation_session_id: sessionId,
    events_url:
      '/api/v1/conversation-turns/01J00000000000000000000020/events',
    id: '01J00000000000000000000020',
    priority_correction: null,
    replayed: false,
    safety_state: 'ok',
    speech: {
      playback_available: false,
      rendering_mode: 'deterministic-fake-text-only',
    },
    status: 'completed',
    transcript: 'Hello, I have an appointment.',
    turn_number: 1,
    understood_intent: 'prototype_utterance_received',
    usage: {
      estimated_cost_microunits: 0,
      provider_attempts: 3,
      total_latency_ms: 14,
    },
  }
}

function grantedVoiceConsents(): VoiceConsents {
  return {
    consents: [
      {
        effective_at: '2026-07-31T12:00:00Z',
        purpose: 'voice_upload_and_transcription',
        state: 'granted',
      },
      {
        effective_at: '2026-07-31T12:00:00Z',
        purpose: 'conversation_provider_processing',
        state: 'granted',
      },
      {
        effective_at: null,
        purpose: 'transcript_history',
        state: 'not_granted',
      },
    ],
    notice: {
      history_optional: true,
      notice: 'Synthetic voice-processing notice.',
      notice_revision: 'voice-processing-notice-2026-09-01',
      notice_sha256: 'a'.repeat(64),
      policy_version: 'recording-policy-v1',
      purposes: [
        'voice_upload_and_transcription',
        'conversation_provider_processing',
        'transcript_history',
      ],
      requested_locale: 'kn-IN',
      resolved_locale: 'kn-IN',
      review_status: 'implementation_draft',
    },
  }
}

function voiceRecording(sequence: number): SecureVoiceRecording {
  return {
    byte_size: 8_044,
    codec: 'pcm_s16le',
    created_at: '2026-07-31T12:00:00Z',
    deletion_url: `/api/v1/voice-recordings/01JRECORDING0000000000000${sequence}/deletion`,
    duration_ms: 250,
    id: `01JRECORDING0000000000000${sequence}`,
    mime_type: 'audio/wav',
    replayed: false,
    source_deleted: true,
    status: 'awaiting_learner_confirmation',
    transcript: `Synthetic practice turn ${sequence}.`,
    transcript_confidence: 0.92,
    transcript_review_url: `/api/v1/voice-recordings/01JRECORDING0000000000000${sequence}/transcript-review`,
  }
}
