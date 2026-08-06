import { afterEach, describe, expect, it, vi } from 'vitest'
import {
  resetDemoAzureTtsStatusCache,
  speakDemoText,
  stopDemoSpeech,
} from './demoTts'

describe('demo Azure-first speech helper', () => {
  afterEach(() => {
    stopDemoSpeech()
    resetDemoAzureTtsStatusCache()
    vi.unstubAllGlobals()
    vi.restoreAllMocks()
  })

  it('plays Azure WAV audio when /demo/tts succeeds', async () => {
    const wav = new Blob([new Uint8Array([1, 2, 3])], { type: 'audio/wav' })
    const fetchMock = vi.fn(async (input: RequestInfo | URL) => {
      const url = String(input)
      if (url.includes('/demo/tts/status')) {
        return {
          ok: true,
          json: async () => ({ configured: true }),
        }
      }
      return {
        ok: true,
        blob: async () => wav,
      }
    })
    vi.stubGlobal('fetch', fetchMock)
    vi.stubGlobal('URL', {
      createObjectURL: vi.fn(() => 'blob:demo-tts'),
      revokeObjectURL: vi.fn(),
    })

    const play = vi.fn().mockResolvedValue(undefined)

    class FakeAudio {
      src = ''
      onended: ((event: Event) => void) | null = null
      onerror: ((event: Event) => void) | null = null
      play = play
      pause = vi.fn()

      constructor() {
        queueMicrotask(() => {
          this.onended?.(new Event('ended'))
        })
      }
    }

    vi.stubGlobal('Audio', FakeAudio)

    const source = await speakDemoText('ಸ್ವಾಗತ', 'kn-IN')

    expect(source).toBe('azure')
    expect(fetchMock).toHaveBeenCalledWith(
      '/demo/tts',
      expect.objectContaining({
        method: 'POST',
        body: JSON.stringify({ locale: 'kn-IN', text: 'ಸ್ವಾಗತ' }),
      }),
    )
    expect(play).toHaveBeenCalledOnce()
  })

  it('falls back to browser speech when Azure is unavailable', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: true,
        json: async () => ({ configured: false }),
      }),
    )

    const speak = vi.fn((utterance: { onend?: () => void }) => {
      utterance.onend?.()
    })
    vi.stubGlobal('speechSynthesis', {
      cancel: vi.fn(),
      paused: false,
      resume: vi.fn(),
      getVoices: () => [],
      speak,
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
    })
    vi.stubGlobal(
      'SpeechSynthesisUtterance',
      vi.fn().mockImplementation(function FakeUtterance(this: {
        text: string
        lang: string
        onend: (() => void) | null
      }, text: string) {
        this.text = text
        this.lang = ''
        this.onend = null
      }),
    )

    const source = await speakDemoText('Welcome', 'en-IN')

    expect(source).toBe('browser')
    expect(speak).toHaveBeenCalledOnce()
  })
})
