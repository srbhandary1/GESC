import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { ConversationJourneyApi } from './ConversationJourneyApi'
import { playBase64Audio } from './playBase64Audio'
import { speakSessionSpeech } from './speakSessionSpeech'

vi.mock('./playBase64Audio', () => ({
  playBase64Audio: vi.fn(async () => undefined),
}))

vi.mock('../demo/browserSpeech', () => ({
  browserSpeechSynthesisAvailable: vi.fn(() => false),
  speakWithBrowserSpeech: vi.fn(async () => undefined),
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

    await expect(
      speakSessionSpeech({
        api,
        locale: 'en-IN',
        purpose: 'model_en',
        sessionId: '01J00000000000000000000001',
        text: 'I went to the market this morning.',
      }),
    ).resolves.toBe(true)

    expect(synthesizeSpeech).toHaveBeenCalledWith(
      '01J00000000000000000000001',
      {
        purpose: 'model_en',
        text: 'I went to the market this morning.',
      },
      expect.any(String),
    )
    expect(playBase64Audio).toHaveBeenCalledWith('QQ==', 'audio/wav', expect.objectContaining({ onTimeUpdate: expect.any(Function), signal: expect.any(AbortSignal) }))
  })

  it('asks TTS to say and and Or only for grammar-drill formulas', async () => {
    const synthesizeSpeech = vi.fn(async () => ({
      playback_available: false,
      rendering_mode: 'deterministic-fake-text-only' as const,
    }))
    const api = {
      startSession: vi.fn(),
      createTurn: vi.fn(),
      events: vi.fn(),
      synthesizeSpeech,
    } as unknown as ConversationJourneyApi

    await speakSessionSpeech({
      api,
      grammarDrill: true,
      locale: 'en-IN',
      purpose: 'model_en',
      sessionId: '01J00000000000000000000001',
      text: 'Let us practise: I can + base verb. Yes, you can. / No, you cannot.',
    })

    expect(synthesizeSpeech).toHaveBeenCalledWith(
      '01J00000000000000000000001',
      {
        purpose: 'model_en',
        text: 'Let us practise: I can and base verb. Yes, you can. Or No, you cannot.',
      },
      expect.any(String),
    )
  })

  it('leaves plus unchanged in free speech', async () => {
    const synthesizeSpeech = vi.fn(async () => ({
      playback_available: false,
      rendering_mode: 'deterministic-fake-text-only' as const,
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
      text: 'Two and two is 2 + 2.',
    })

    expect(synthesizeSpeech).toHaveBeenCalledWith(
      '01J00000000000000000000001',
      {
        purpose: 'model_en',
        text: 'Two and two is 2 + 2.',
      },
      expect.any(String),
    )
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

    await expect(
      speakSessionSpeech({
        api,
        locale: 'en-IN',
        purpose: 'model_en',
        sessionId: '01J00000000000000000000001',
        text: 'I went to the market this morning.',
      }),
    ).resolves.toBe(false)

    expect(playBase64Audio).not.toHaveBeenCalled()
  })

  it('drives the avatar from the speech timeline for the whole playback', async () => {
    const timeline = {
      source: 'text-duration-estimator-v1' as const,
      duration_ms: 600,
      frames: [
        { offset_ms: 0, viseme_id: 6 },
        { offset_ms: 300, viseme_id: 2 },
        { offset_ms: 599, viseme_id: 0 },
      ],
    }
    const api = {
      startSession: vi.fn(),
      createTurn: vi.fn(),
      events: vi.fn(),
      synthesizeSpeech: vi.fn(async () => ({
        model_audio_base64: 'QQ==',
        model_audio_mime_type: 'audio/wav' as const,
        model_avatar_viseme_timeline: timeline,
        playback_available: true,
        rendering_mode: 'azure-soft-launch-guidance-wav' as const,
      })),
    } as unknown as ConversationJourneyApi
    const onPlaybackChange = vi.fn()

    vi.mocked(playBase64Audio).mockImplementationOnce(
      async (_audio, _mimeType, options) => {
        options?.onStarted?.()
        options?.onTimeUpdate?.(300)
      },
    )

    await speakSessionSpeech({
      api,
      locale: 'en-IN',
      onPlaybackChange,
      purpose: 'model_en',
      sessionId: '01J00000000000000000000001',
      text: 'Hello there.',
    })

    expect(onPlaybackChange).toHaveBeenNthCalledWith(1, {
      timeline,
      timeMs: 0,
    })
    expect(onPlaybackChange).toHaveBeenNthCalledWith(2, {
      timeline,
      timeMs: 300,
    })
    expect(onPlaybackChange).toHaveBeenLastCalledWith(null)
  })
})
