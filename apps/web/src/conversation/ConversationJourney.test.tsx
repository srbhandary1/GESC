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
        name: 'Start AI Friend prototype',
      }),
    )
    expect(
      await screen.findByText(
        'I am a deterministic AI prototype, not a human.',
      ),
    ).toHaveAttribute('lang', 'en-IN')

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
      screen.getByText(
        'Fake TTS is text-only. No audio was created or stored.',
      ),
    ).toBeInTheDocument()
    expect(screen.getByText('Turn completed')).toBeInTheDocument()
    expect(api.startSession).toHaveBeenCalledWith(
      {
        coaching_mode: 'gentle',
        persona_key: 'patient-friend-prototype',
        scenario_key: 'everyday-friend-prototype',
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
        name: 'Start AI Friend prototype',
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
    replayed: false,
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
