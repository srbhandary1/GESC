import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { ConversationJourneyApi } from './ConversationJourneyApi'
import { playBase64Audio } from './playBase64Audio'
import { speakSessionSpeech } from './speakSessionSpeech'

vi.mock('./playBase64Audio', () => ({
  playBase64Audio: vi.fn(async () => undefined),
}))

describe('speakSessionSpeech', () => {
  beforeEach(() => {
    vi.mocked(playBase64Audio).mockClear()
  })

  it('plays neural WAV from the session speech port', async () => {
    const synthesizeSpeech = vi.fn(async () => ({
      model_audio_base64: 'QQ==',
      model_audio_mime_type: 'audio/wav' as const,
      playback_available: true,
      rendering_mode: 'azure-soft-launch-guidance-wav' as const,
    }))
    const api = {
      startSession: vi.fn(),
      createTurn: vi.fn(),
      events: vi.fn(),
      synthesizeSpeech,
    } as unknown as ConversationJourneyApi

    await speakSessionSpeech({
      api,
      locale: 'en-IN',
      purpose: 'model_en',
      sessionId: '01J00000000000000000000001',
      text: 'I went to the market this morning.',
    })

    expect(synthesizeSpeech).toHaveBeenCalledWith(
      '01J00000000000000000000001',
      {
        purpose: 'model_en',
        text: 'I went to the market this morning.',
      },
      expect.any(String),
    )
    expect(playBase64Audio).toHaveBeenCalledWith('QQ==', 'audio/wav')
  })

  it('stays silent when the speech port has no audio', async () => {
    const api = {
      startSession: vi.fn(),
      createTurn: vi.fn(),
      events: vi.fn(),
      synthesizeSpeech: vi.fn(async () => ({
        playback_available: false,
        rendering_mode: 'deterministic-fake-text-only' as const,
      })),
    } as unknown as ConversationJourneyApi

    await speakSessionSpeech({
      api,
      locale: 'en-IN',
      purpose: 'model_en',
      sessionId: '01J00000000000000000000001',
      text: 'I went to the market this morning.',
    })

    expect(playBase64Audio).not.toHaveBeenCalled()
  })
})
