import { afterEach, describe, expect, it, vi } from 'vitest'
import {
  BrowserAudioRecorder,
  RecordingPermissionDeniedError,
} from './BrowserAudioRecorder'

describe('BrowserAudioRecorder', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('requests the microphone immediately when recording starts', async () => {
    const permissionDenied = new DOMException(
      'Microphone permission was denied.',
      'NotAllowedError',
    )
    const getUserMedia = vi.fn(() => Promise.reject(permissionDenied))

    vi.stubGlobal('navigator', {
      mediaDevices: { getUserMedia },
    })
    vi.stubGlobal('AudioContext', class {})
    Object.defineProperty(window, 'isSecureContext', {
      configurable: true,
      value: true,
    })

    const starting = new BrowserAudioRecorder().start()

    expect(getUserMedia).toHaveBeenCalledOnce()
    await expect(starting).rejects.toBeInstanceOf(
      RecordingPermissionDeniedError,
    )
  })

  it('is unsupported outside a secure browsing context', () => {
    Object.defineProperty(window, 'isSecureContext', {
      configurable: true,
      value: false,
    })
    vi.stubGlobal('navigator', {
      mediaDevices: { getUserMedia: vi.fn() },
    })
    vi.stubGlobal('AudioContext', class {})

    expect(new BrowserAudioRecorder().isSupported()).toBe(false)
  })
})
