import { cleanup, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { ConversationJourney } from './ConversationJourney'
import * as playBase64AudioModule from './playBase64Audio'
import {
  ConversationJourneyUnavailableError,
  type ConversationJourneyApi,
  type FriendSession,
  type FriendTurn,
  type FriendTurnEvent,
} from './ConversationJourneyApi'

import { resetSpeakLoopHistory } from './speakLoopTimings'

afterEach(() => {
  cleanup()
  vi.restoreAllMocks()
  resetSpeakLoopHistory()
})

describe('Friend practice journey', () => {
  it('discloses the AI boundary and completes one typed turn', async () => {
    const session = fakeSession()
    const turn = fakeTurn(session.id)
    const events = fakeEvents()
    const api: ConversationJourneyApi = {
      startSession: vi.fn(async () => session),
      createTurn: vi.fn(async () => turn),
      events: vi.fn(async () => events),
    }
    const createClientId = vi
      .fn()
      .mockReturnValueOnce('session-command')
      .mockReturnValueOnce('turn-command')
      .mockReturnValueOnce('next-turn-command')
    const user = userEvent.setup()
    const onLiveAiUsageChanged = vi.fn()

    render(
      <ConversationJourney
        api={api}
        createClientId={createClientId}
        locale="en-IN"
        localizationPolicy="development-preview"
        onLiveAiUsageChanged={onLiveAiUsageChanged}
      />,
    )

    expect(
      screen.getByText(
        'Swathi is an AI English teacher, not a human. Her advice never changes marks, mastery, or progress.',
      ),
    ).toBeInTheDocument()
    expect(
      screen.getByText(
        'Speak English after you consent. Type instead is optional. Only content-free digests are kept.',
      ),
    ).toBeInTheDocument()

    await user.click(
      screen.getByRole('button', {
        name: 'Talk with Swathi',
      }),
    )
    expect(
      await screen.findByText(
        'I am Swathi, your AI English teacher. Feedback is advisory.',
      ),
    ).toHaveAttribute('lang', 'en-IN')
    expect(screen.getByText('Hear an example')).toBeInTheDocument()
    await user.click(
      screen.getByRole('button', { name: 'Type instead' }),
    )

    await user.type(
      screen.getByRole('textbox', {
        name: 'Type one English practice turn',
      }),
      'I practise English after work.',
    )
    await user.click(
      screen.getByRole('button', { name: 'Send turn' }),
    )

    expect(
      await screen.findByText(
        'Thank you for sharing that. What would you like to talk about next?',
      ),
    ).toHaveAttribute('lang', 'en-IN')
    expect(
      screen.getByText('I practise English after work.'),
    ).toHaveAttribute('lang', 'en-IN')
    expect(
      screen.getByLabelText('Explanation in your language'),
    ).toBeInTheDocument()
    expect(
      screen.getByText(
        'A clearer sentence is: "I enjoy learning English after work."',
      ),
    ).toHaveAttribute('lang', 'en-IN')
    expect(
      screen.getByText(
        'ಸ್ಪಷ್ಟವಾಗಿ ಹೇಳುವುದಾದರೆ: "I enjoy learning English after work."',
      ),
    ).toHaveAttribute('lang', 'kn-IN')
    expect(
      screen.getAllByText(
        'Listen first. You can also read the English.',
      ).length,
    ).toBeGreaterThan(0)
    expect(screen.getByText('Turn completed')).toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: 'Say that again' }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: 'Hint in my language' }),
    ).toBeInTheDocument()
    expect(api.startSession).toHaveBeenCalledWith(
      {
        coaching_mode: 'gentle',
        persona_key: 'patient-friend-prototype',
        scenario_key: 'everyday-friend-prototype',
        ui_locale: 'en-IN',
      },
      'session-command',
    )
    expect(api.createTurn).toHaveBeenCalledWith(
      session.id,
      { prototype_utterance: 'I practise English after work.' },
      'turn-command',
      expect.any(AbortSignal),
    )
    expect(api.events).toHaveBeenCalledWith(
      turn.events_url,
      expect.any(AbortSignal),
    )
    expect(onLiveAiUsageChanged).toHaveBeenCalled()
  })

  it.each([
    [
      'Skill focus',
      'Start Skill Focus',
      'tutor',
      'past-tense-skill-focus-prototype',
      'Yesterday, I went to the market.',
    ],
    [
      'Review',
      'Start Review',
      'gentle',
      'carry-sentence-review-prototype',
      'Please say the sentence once more in your own way.',
    ],
  ] as const)(
    'starts the %s journey with its bounded scenario and coaching default',
    async (
      sessionLabel,
      startLabel,
      expectedCoachingMode,
      expectedScenario,
      expectedModelLine,
    ) => {
      const session = fakeSession()
      const api: ConversationJourneyApi = {
        startSession: vi.fn(async () => session),
        createTurn: vi.fn(),
        events: vi.fn(),
      }
      const user = userEvent.setup()

      render(
        <ConversationJourney
          api={api}
          createClientId={() => 'session-command'}
          locale="en-IN"
          localizationPolicy="development-preview"
        />,
      )

      await user.selectOptions(
        screen.getByRole('combobox', { name: 'Practice type' }),
        sessionLabel,
      )
      expect(
        screen.getByRole('combobox', { name: 'Coaching mode' }),
      ).toHaveValue(expectedCoachingMode)
      await user.click(screen.getByRole('button', { name: startLabel }))
      expect(screen.getByText(expectedModelLine)).toBeInTheDocument()
      expect(api.startSession).toHaveBeenCalledWith(
        {
          coaching_mode: expectedCoachingMode,
          persona_key: 'patient-friend-prototype',
          scenario_key: expectedScenario,
          ui_locale: 'en-IN',
        },
        'session-command',
      )
    },
  )

  it('exposes browser speech actions in the disclosed demo mode', async () => {
    class FakeUtterance {
      lang = ''
      text = ''
      rate = 1
      pitch = 1
      voice: SpeechSynthesisVoice | null = null
      onend: ((event: Event) => void) | null = null
      onerror: ((event: Event) => void) | null = null

      constructor(text: string) {
        this.text = text
      }
    }

    const speak = vi.fn((utterance: FakeUtterance) => {
      utterance.onend?.(new Event('end'))
    })
    vi.stubGlobal('SpeechSynthesisUtterance', FakeUtterance)
    vi.stubGlobal('speechSynthesis', {
      addEventListener: vi.fn(),
      cancel: vi.fn(),
      getVoices: () => [],
      paused: false,
      removeEventListener: vi.fn(),
      resume: vi.fn(),
      speak,
    })
    vi.stubGlobal(
      'fetch',
      vi.fn(async () => ({
        ok: true,
        json: async () => ({ configured: false }),
      })),
    )
    const { resetDemoAzureTtsStatusCache } = await import('../demo/demoTts')
    resetDemoAzureTtsStatusCache()

    const session = fakeSession()
    const turn = fakeTurn(session.id)
    const events = fakeEvents()
    const api: ConversationJourneyApi = {
      startSession: vi.fn(async () => session),
      createTurn: vi.fn(async () => turn),
      events: vi.fn(async () => events),
    }
    const user = userEvent.setup()

    render(
      <ConversationJourney
        api={api}
        createClientId={() => 'demo-command'}
        demoMode
        locale="en-IN"
        localizationPolicy="development-preview"
      />,
    )

    expect(
      screen.getByRole('region', { name: 'Practice with your Friend' }),
    ).toHaveAttribute('data-demo-speech', 'browser')
    expect(
      screen.queryByRole('button', { name: 'Speak after you consent' }),
    ).not.toBeInTheDocument()

    await user.click(
      screen.getByRole('button', { name: 'Start speaking practice' }),
    )
    await user.click(
      screen.getByRole('button', { name: 'Type instead' }),
    )
    await user.type(
      await screen.findByRole('textbox', {
        name: 'Type one English practice turn',
      }),
      'I practise English after work.',
    )
    await user.click(screen.getByRole('button', { name: 'Send turn' }))

    expect(
      await screen.findByRole('button', { name: 'Play Friend reply' }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: 'Speak your turn' }),
    ).toBeInTheDocument()
    await vi.waitFor(() => {
      expect(speak).toHaveBeenCalled()
    })
  })

  it('shows a visible error when the provider is disabled', async () => {
    const api: ConversationJourneyApi = {
      startSession: vi.fn(async () => {
        throw new Error('disabled')
      }),
      createTurn: vi.fn(),
      events: vi.fn(),
    }
    const user = userEvent.setup()

    render(
      <ConversationJourney
        api={api}
        locale="en-IN"
        localizationPolicy="development-preview"
      />,
    )
    await user.click(
      screen.getByRole('button', {
        name: 'Talk with Swathi',
      }),
    )

    expect(
      await screen.findByRole('alert'),
    ).toHaveTextContent(
      'Swathi is unavailable. Try again.',
    )
  })

  it('shows paid live-AI exhaustion from the API problem key', async () => {
    const api: ConversationJourneyApi = {
      startSession: vi.fn(async () => {
        throw new ConversationJourneyUnavailableError(
          409,
          'entitlement.ai_exhausted',
        )
      }),
      createTurn: vi.fn(),
      events: vi.fn(),
    }
    const user = userEvent.setup()

    render(
      <ConversationJourney
        api={api}
        locale="en-IN"
        localizationPolicy="development-preview"
      />,
    )
    await user.click(
      screen.getByRole('button', {
        name: 'Talk with Swathi',
      }),
    )

    expect(await screen.findByRole('alert')).toHaveTextContent(
      "This month's live speaking time is used up. Buy talk time or wait for the next month.",
    )
  })

  it('uses a bound level-session without starting a second generic Friend', async () => {
    const session = fakeSession()
    const startSession = vi.fn()
    const api: ConversationJourneyApi = {
      startSession,
      createTurn: vi.fn(),
      events: vi.fn(),
    }

    render(
      <ConversationJourney
        api={api}
        initialSession={session}
        locale="en-IN"
        localizationPolicy="development-preview"
      />,
    )

    expect(
      screen.queryByRole('button', {
        name: 'Talk with Swathi',
      }),
    ).not.toBeInTheDocument()
    expect(
      screen.queryByRole('textbox', {
        name: 'Type one English practice turn',
      }),
    ).not.toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: 'Type instead' }),
    ).toBeInTheDocument()
    expect(startSession).not.toHaveBeenCalled()
  })

  it('asks the lesson question when a bound session opens', () => {
    const session = fakeSession()
    const api: ConversationJourneyApi = {
      startSession: vi.fn(),
      createTurn: vi.fn(),
      events: vi.fn(),
    }

    render(
      <ConversationJourney
        api={api}
        embedded
        initialSession={session}
        locale="en-IN"
        localizationPolicy="development-preview"
        openingLine="Can I use your pen?"
      />,
    )

    expect(screen.getByText('Can I use your pen?')).toBeInTheDocument()
    expect(
      screen.queryByText('What did you do this morning?'),
    ).not.toBeInTheDocument()
  })

  it('speaks warmup through session guidance TTS, not the phone voice', async () => {
    const session = fakeSession()
    const synthesizeSpeech = vi.fn(async () => ({
      playback_available: false,
      rendering_mode: 'deterministic-fake-text-only' as const,
      model_audio_base64: null,
    }))
    const api: ConversationJourneyApi = {
      startSession: vi.fn(async () => session),
      createTurn: vi.fn(),
      events: vi.fn(),
      synthesizeSpeech,
    }
    const user = userEvent.setup()

    render(
      <ConversationJourney
        api={api}
        createClientId={() => 'session-command'}
        locale="en-IN"
        localizationPolicy="development-preview"
      />,
    )

    await user.click(
      screen.getByRole('button', { name: 'Talk with Swathi' }),
    )

    await vi.waitFor(() => {
      expect(synthesizeSpeech).toHaveBeenCalledWith(
        session.id,
        {
          purpose: 'model_en',
          text: 'What did you do this morning?',
        },
        expect.any(String),
      )
    })
  })

  it('starts reply audio before the events stream resolves', async () => {
    const play = vi
      .spyOn(playBase64AudioModule, 'playBase64Audio')
      .mockImplementation(async (_base64, _mime, options) => {
        options?.onStarted?.()
      })
    const session = fakeSession()
    const events = fakeEvents()
    const turn: FriendTurn = {
      ...fakeTurn(session.id),
      speech: {
        playback_available: true,
        rendering_mode: 'azure-soft-launch-guidance-wav',
        model_audio_base64: 'QQ==',
        model_audio_mime_type: 'audio/wav',
      },
    }
    let releaseEvents: (value: readonly FriendTurnEvent[]) => void =
      () => undefined
    const api: ConversationJourneyApi = {
      startSession: vi.fn(async () => session),
      createTurn: vi.fn(async () => turn),
      events: vi.fn(
        () =>
          new Promise<readonly FriendTurnEvent[]>((resolve) => {
            releaseEvents = resolve
          }),
      ),
    }
    const user = userEvent.setup()

    render(
      <ConversationJourney
        api={api}
        createClientId={vi
          .fn()
          .mockReturnValueOnce('session-command')
          .mockReturnValueOnce('turn-command')
          .mockReturnValueOnce('next-turn-command')}
        locale="en-IN"
        localizationPolicy="development-preview"
      />,
    )

    await user.click(
      screen.getByRole('button', { name: 'Talk with Swathi' }),
    )
    await user.click(
      screen.getByRole('button', { name: 'Type instead' }),
    )
    await user.type(
      screen.getByRole('textbox', {
        name: 'Type one English practice turn',
      }),
      'I practise English after work.',
    )
    await user.click(screen.getByRole('button', { name: 'Send turn' }))

    await vi.waitFor(() => {
      expect(play).toHaveBeenCalled()
    })
    expect(screen.queryByText('Turn completed')).not.toBeInTheDocument()
    releaseEvents(events)
    expect(await screen.findByText('Turn completed')).toBeInTheDocument()
    expect(play).toHaveBeenCalledWith(
      'QQ==',
      'audio/wav',
      expect.objectContaining({
        onStarted: expect.any(Function),
      }),
    )
  })
})

function fakeSession(): FriendSession {
  return {
    coaching_mode: 'gentle',
    id: '01J00000000000000000000001',
    mode: 'friend',
    stage: null,
    persona: {
      definition_hash: 'a'.repeat(64),
      disclosure: 'I am a deterministic AI prototype, not a human.',
      display_name: 'Patient Friend Prototype',
      id: '01J00000000000000000000002',
      key: 'patient-friend-prototype',
      review_status: 'synthetic_unreviewed',
      version: 1,
      version_id: '01J00000000000000000000003',
    },
    replayed: false,
    scenario: {
      definition_hash: 'b'.repeat(64),
      id: '01J00000000000000000000004',
      key: 'everyday-friend-prototype',
      review_status: 'synthetic_unreviewed',
      topic_code: 'everyday-conversation',
      version: 1,
      version_id: '01J00000000000000000000005',
    },
    status: 'active',
    ui_locale: 'kn-IN',
  }
}

function fakeTurn(sessionId: string): FriendTurn {
  return {
    assistant_reply:
      'Thank you for sharing that. What would you like to talk about next?',
    conversation_session_id: sessionId,
    events_url:
      '/api/v1/conversation-turns/01J00000000000000000000006/events',
    id: '01J00000000000000000000006',
    priority_correction: {
      correction_en:
        'A clearer sentence is: "I enjoy learning English after work."',
      error_category: 'grammar.article',
      explanation_l1:
        'ಸ್ಪಷ್ಟವಾಗಿ ಹೇಳುವುದಾದರೆ: "I enjoy learning English after work."',
      explanation_locale: 'kn-IN',
      explanation_kn:
        'ಸ್ಪಷ್ಟವಾಗಿ ಹೇಳುವುದಾದರೆ: "I enjoy learning English after work."',
      needed: true,
      retry_recommended: false,
    },
    replayed: false,
    safety_state: 'ok',
    speech: {
      playback_available: false,
      rendering_mode: 'deterministic-fake-text-only',
    },
    status: 'completed',
    transcript: 'I practise English after work.',
    turn_number: 1,
    understood_intent: 'prototype_utterance_received',
    usage: {
      estimated_cost_microunits: 0,
      provider_attempts: 3,
      total_latency_ms: 14,
    },
  }
}

function fakeEvents(): readonly FriendTurnEvent[] {
  return [
    'accepted',
    'transcribing',
    'generating_reply',
    'synthesizing',
    'completed',
  ].map((state, index) => ({
    event_code:
      state === 'accepted'
        ? 'turn.accepted'
        : state === 'transcribing'
          ? 'turn.transcribing'
          : state === 'generating_reply'
            ? 'turn.generating_reply'
            : state === 'synthesizing'
              ? 'turn.synthesizing'
              : 'turn.completed',
    provider_attempt:
      state === 'accepted' || state === 'completed' ? null : 1,
    provider_stage:
      state === 'transcribing'
        ? 'stt'
        : state === 'generating_reply'
          ? 'model'
          : state === 'synthesizing'
            ? 'tts'
            : null,
    recorded_at: '2026-07-30T12:00:00Z',
    sequence: index + 1,
    state,
  })) as readonly FriendTurnEvent[]
}
