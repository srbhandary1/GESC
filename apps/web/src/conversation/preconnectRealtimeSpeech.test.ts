import { describe, expect, it, vi } from 'vitest'
import type { ConversationJourneyApi } from './ConversationJourneyApi'
import { preconnectRealtimeSpeechAfterConsent } from './preconnectRealtimeSpeech'

describe('preconnectRealtimeSpeechAfterConsent', () => {
  it('skips when the ticket port is absent', async () => {
    await expect(
      preconnectRealtimeSpeechAfterConsent({
        api: {} as ConversationJourneyApi,
        commandId: '01J00000000000000000000001',
        sessionId: '01J00000000000000000000002',
      }),
    ).resolves.toBe('skipped')
  })

  it('requests a ticket after consent and keeps recorded recovery', async () => {
    const requestRealtimeSpeechTicket = vi.fn(async () => ({
      always_on: false as const,
      capture_mode: 'push_to_talk' as const,
      conversation_session_id: '01J00000000000000000000002',
      denial_reasons: ['http_enablement_not_authorized'],
      expires_at: '2026-09-09T16:30:00Z',
      full_duplex: false as const,
      preconnect_allowed: false,
      provider_dispatch: 'denied' as const,
      recovery_route: 'recorded_upload' as const,
      ticket_id: '01J00000000000000000000003',
      transport: 'recorded_recovery' as const,
      websocket_path:
        '/api/v1/conversation-sessions/01J00000000000000000000002/realtime-stt',
    }))

    await expect(
      preconnectRealtimeSpeechAfterConsent({
        api: { requestRealtimeSpeechTicket } as unknown as ConversationJourneyApi,
        commandId: '01J00000000000000000000001',
        sessionId: '01J00000000000000000000002',
      }),
    ).resolves.toBe('recorded_recovery')
    expect(requestRealtimeSpeechTicket).toHaveBeenCalledWith(
      '01J00000000000000000000002',
      '01J00000000000000000000001',
      undefined,
    )
  })
})
