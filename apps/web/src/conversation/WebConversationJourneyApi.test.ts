import { describe, expect, it, vi } from 'vitest'
import { WebConversationJourneyApi } from './WebConversationJourneyApi'

describe('web deterministic conversation API', () => {
  it('uses CSRF mutations and parses safe SSE events', async () => {
    const session = {
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
      ui_locale: 'kn-IN',
    } as const
    const turn = {
      assistant_reply: 'Deterministic reply',
      conversation_session_id: session.id,
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
      transcript: 'Hello',
      turn_number: 1,
      understood_intent: 'prototype_utterance_received',
      usage: {
        estimated_cost_microunits: 0,
        provider_attempts: 3,
        total_latency_ms: 14,
      },
    } as const
    const fetch = vi
      .fn<typeof globalThis.fetch>()
      .mockResolvedValueOnce(new Response(null, { status: 204 }))
      .mockResolvedValueOnce(Response.json(session, { status: 201 }))
      .mockResolvedValueOnce(new Response(null, { status: 204 }))
      .mockResolvedValueOnce(Response.json(turn, { status: 201 }))
      .mockResolvedValueOnce(
        new Response(
          'id: 1\nevent: conversation-status\ndata: {"sequence":1,"state":"accepted","event_code":"turn.accepted","provider_stage":null,"provider_attempt":null,"recorded_at":"2026-07-30T12:00:00Z"}\n\n',
          {
            headers: { 'Content-Type': 'text/event-stream' },
            status: 200,
          },
        ),
      )
    const api = new WebConversationJourneyApi({
      fetch,
      readCookie: () => 'XSRF-TOKEN=csrf-token',
    })

    await expect(
      api.startSession(
        {
          coaching_mode: 'gentle',
          persona_key: 'patient-friend-prototype',
          scenario_key: 'everyday-friend-prototype',
          ui_locale: 'kn-IN',
        },
        'session-command',
      ),
    ).resolves.toEqual(session)
    await expect(
      api.createTurn(
        session.id,
        { prototype_utterance: 'Hello' },
        'turn-command',
      ),
    ).resolves.toEqual(turn)
    await expect(api.events(turn.events_url)).resolves.toEqual([
      {
        event_code: 'turn.accepted',
        provider_attempt: null,
        provider_stage: null,
        recorded_at: '2026-07-30T12:00:00Z',
        sequence: 1,
        state: 'accepted',
      },
    ])

    expect(fetch).toHaveBeenNthCalledWith(
      2,
      '/api/v1/conversation-sessions',
      expect.objectContaining({
        credentials: 'include',
        headers: expect.objectContaining({
          'Idempotency-Key': 'session-command',
          'X-XSRF-TOKEN': 'csrf-token',
        }),
        method: 'POST',
      }),
    )
    expect(fetch).toHaveBeenNthCalledWith(
      5,
      turn.events_url,
      expect.objectContaining({
        credentials: 'include',
        headers: { Accept: 'text/event-stream' },
      }),
    )
  })

  it('uploads WAV with CSRF and lets the browser set the multipart boundary', async () => {
    const recording = {
      byte_size: 48,
      codec: 'pcm_s16le',
      created_at: '2026-07-31T12:00:00Z',
      deletion_url:
        '/api/v1/voice-recordings/01J00000000000000000000007/deletion',
      duration_ms: 1,
      id: '01J00000000000000000000007',
      mime_type: 'audio/wav',
      replayed: false,
      source_deleted: true,
      status: 'awaiting_learner_confirmation',
      transcript: 'I practise English every day.',
      transcript_confidence: 0.87,
      transcript_review_url:
        '/api/v1/voice-recordings/01J00000000000000000000007/transcript-review',
    } as const
    const fetch = vi
      .fn<typeof globalThis.fetch>()
      .mockResolvedValueOnce(new Response(null, { status: 204 }))
      .mockResolvedValueOnce(Response.json(recording, { status: 201 }))
    const api = new WebConversationJourneyApi({
      fetch,
      readCookie: () => 'XSRF-TOKEN=voice-csrf',
    })

    await expect(
      api.uploadVoiceRecording(
        '01J00000000000000000000001',
        new Blob(['RIFF'], { type: 'audio/wav' }),
        'voice-command',
      ),
    ).resolves.toEqual(recording)

    const request = fetch.mock.calls[1]
    const init = request?.[1]
    expect(request?.[0]).toBe(
      '/api/v1/conversation-sessions/01J00000000000000000000001/recordings',
    )
    expect(init?.body).toBeInstanceOf(FormData)
    expect(new Headers(init?.headers).get('Content-Type')).toBeNull()
    expect(new Headers(init?.headers).get('Idempotency-Key')).toBe(
      'voice-command',
    )
    expect(new Headers(init?.headers).get('X-XSRF-TOKEN')).toBe(
      'voice-csrf',
    )
  })
})
