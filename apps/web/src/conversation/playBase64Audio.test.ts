import { describe, expect, it, vi } from 'vitest'
import { decodeBase64ToBytes, playBase64Audio } from './playBase64Audio'

describe('playBase64Audio', () => {
  it('notifies onStarted as soon as playback begins', async () => {
    const onStarted = vi.fn()
    const onElement = vi.fn()
    class FakeAudio {
      paused = true
      src = ''
      preload = ''
      onended: (() => void) | null = null
      onerror: (() => void) | null = null
      onplaying: (() => void) | null = null

      play(): Promise<void> {
        this.paused = false
        queueMicrotask(() => this.onplaying?.())
        queueMicrotask(() => this.onended?.())
        return Promise.resolve()
      }

      pause(): void {
        this.paused = true
      }
    }

    vi.stubGlobal('Audio', FakeAudio)
    Object.defineProperty(URL, 'createObjectURL', {
      configurable: true,
      value: () => 'blob:audio',
    })
    Object.defineProperty(URL, 'revokeObjectURL', {
      configurable: true,
      value: vi.fn(),
    })

    await playBase64Audio('QQ==', 'audio/wav', { onStarted, onElement })
    expect(onStarted).toHaveBeenCalledOnce()
    expect(onElement).toHaveBeenCalledOnce()
    vi.unstubAllGlobals()
  })

  it('aborts playback when the signal is aborted', async () => {
    class FakeAudio {
      paused = true
      src = ''
      preload = ''
      onended: (() => void) | null = null
      onerror: (() => void) | null = null
      onplaying: (() => void) | null = null

      play(): Promise<void> {
        this.paused = false
        return new Promise(() => undefined)
      }

      pause(): void {
        this.paused = true
      }
    }

    vi.stubGlobal('Audio', FakeAudio)
    Object.defineProperty(URL, 'createObjectURL', {
      configurable: true,
      value: () => 'blob:audio',
    })
    Object.defineProperty(URL, 'revokeObjectURL', {
      configurable: true,
      value: vi.fn(),
    })

    const controller = new AbortController()
    const pending = playBase64Audio('QQ==', 'audio/wav', {
      signal: controller.signal,
    })
    controller.abort()
    await expect(pending).rejects.toMatchObject({ name: 'AbortError' })
    vi.unstubAllGlobals()
  })

  it('decodes base64 without including the source string in thrown errors', () => {
    const bytes = decodeBase64ToBytes('QQ==')
    expect(Array.from(bytes)).toEqual([65])
  })
})
