import { afterEach, describe, expect, it, vi } from 'vitest'
import {
  decodeDemoSpeechUrl,
  encodeDemoSpeechUrl,
  isDemoSpeechUrl,
  speakWithBrowserSpeech,
  stopBrowserSpeech,
} from './browserSpeech'

afterEach(() => {
  vi.restoreAllMocks()
  vi.unstubAllGlobals()
})

describe('demo browser speech helpers', () => {
  it('encodes and decodes demo-speech URLs', () => {
    const encoded = encodeDemoSpeechUrl('en-IN', 'Hello, my name is Ravi.')

    expect(isDemoSpeechUrl(encoded)).toBe(true)
    expect(decodeDemoSpeechUrl(encoded)).toEqual({
      locale: 'en-IN',
      text: 'Hello, my name is Ravi.',
    })
    expect(decodeDemoSpeechUrl('https://example.test/audio.wav')).toBeNull()
  })

  it('speaks through the browser speechSynthesis API', async () => {
    class FakeUtterance {
      lang = ''
      pitch = 1
      rate = 1
      text = ''
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
    const cancel = vi.fn()

    vi.stubGlobal('SpeechSynthesisUtterance', FakeUtterance)
    vi.stubGlobal('speechSynthesis', {
      cancel,
      getVoices: () => [],
      speak,
    })

    await speakWithBrowserSpeech('Hello, my name is Ravi.', 'en-IN')

    expect(cancel).toHaveBeenCalled()
    expect(speak).toHaveBeenCalledOnce()
    const utterance = speak.mock.calls[0]?.[0] as FakeUtterance
    expect(utterance.text).toBe('Hello, my name is Ravi.')
    expect(utterance.lang).toBe('en-IN')
  })

  it('cancels browser speech when stopped', () => {
    const cancel = vi.fn()
    vi.stubGlobal('SpeechSynthesisUtterance', class {})
    vi.stubGlobal('speechSynthesis', {
      cancel,
      getVoices: () => [],
      speak: vi.fn(),
    })

    stopBrowserSpeech()

    expect(cancel).toHaveBeenCalledOnce()
  })
})
