import { cleanup, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { ConversationJourney } from './ConversationJourney'
import type {
  ConversationJourneyApi,
  FriendSession,
  FriendTurn,
  FriendTurnEvent,
} from './ConversationJourneyApi'

afterEach(() => {
  cleanup()
  vi.restoreAllMocks()
})

describe('deterministic Friend prototype', () => {
  it('discloses the fake boundary and completes one typed turn', async () => {
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

    render(
      <ConversationJourney
        api={api}
        createClientId={createClientId}
        locale="en-IN"
        localizationPolicy="development-preview"
      />,
    )

    expect(
      screen.getByText(
        'This is a deterministic fake for local testing. It is not a real AI provider or a human.',
      ),
    ).toBeInTheDocument()
    expect(
      screen.getByText(
        'Your typed text is processed only for this request. Conversation text and audio are not persisted.',
      ),
    ).toBeInTheDocument()

    await user.click(
      screen.getByRole('button', {
        name: 'Start Confidence Conversation',
      }),
    )
    expect(
      await screen.findByText(
        'I am a deterministic AI prototype, not a human.',
      ),
    ).toHaveAttribute('lang', 'en-IN')
    expect(screen.getByRole('heading', { name: 'Warm-up' })).toBeInTheDocument()
    await user.click(
      screen.getByRole('button', { name: 'Continue to listen' }),
    )
    expect(screen.getByRole('heading', { name: 'Listen' })).toBeInTheDocument()
    expect(
      screen.getByText('I went to the market this morning.'),
    ).toBeInTheDocument()
    await user.click(
      screen.getByRole('button', { name: 'Continue to talk' }),
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
    expect(screen.getByText('Priority correction')).toBeInTheDocument()
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
      screen.getByText(
        'The English text above is enough. Playback is optional.',
      ),
    ).toBeInTheDocument()
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
    )
    expect(api.events).toHaveBeenCalledWith(turn.events_url)
  })

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
      screen.getByRole('region', { name: 'Speak with Swathi' }),
    ).toHaveAttribute('data-demo-speech', 'browser')
    expect(
      screen.queryByRole('button', { name: 'Set up optional voice practice' }),
    ).not.toBeInTheDocument()

    await user.click(
      screen.getByRole('button', { name: 'Start speaking with Swathi' }),
    )
    await user.click(
      await screen.findByRole('button', { name: 'Continue to listen' }),
    )
    await user.click(
      screen.getByRole('button', { name: 'Continue to talk' }),
    )
    await user.type(
      await screen.findByRole('textbox', {
        name: 'Type one English practice turn',
      }),
      'I practise English after work.',
    )
    await user.click(screen.getByRole('button', { name: 'Send turn' }))

    expect(
      await screen.findByRole('button', { name: 'Play Swathi reply' }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: 'Speak your turn' }),
    ).toBeInTheDocument()
    await vi.waitFor(() => {
      expect(speak).toHaveBeenCalled()
    })
  })

  it('shows a visible error when the fake provider is disabled', async () => {
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
        name: 'Start Confidence Conversation',
      }),
    )

    expect(
      await screen.findByRole('alert'),
    ).toHaveTextContent(
      'The AI Friend prototype is unavailable. Try again.',
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
