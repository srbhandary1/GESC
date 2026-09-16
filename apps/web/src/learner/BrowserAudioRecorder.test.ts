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

  it.each([
    ['denied_once', 'denied_once'],
    ['denied_permanently', 'denied_permanently'],
  ] as const)(
    'exposes the Android %s result without calling getUserMedia',
    async (status, expected) => {
      const getUserMedia = vi.fn()
      const nativePermission = {
        requestAfterSpeakingAction: vi.fn(async () => ({ status })),
      }

      vi.stubGlobal('navigator', { mediaDevices: { getUserMedia } })
      vi.stubGlobal('AudioContext', class {})
      Object.defineProperty(window, 'isSecureContext', {
        configurable: true,
        value: true,
      })

      const starting = new BrowserAudioRecorder(
        nativePermission,
        () => true,
      ).start()

      await expect(starting).rejects.toMatchObject({ status: expected })
      expect(nativePermission.requestAfterSpeakingAction).toHaveBeenCalledOnce()
      expect(getUserMedia).not.toHaveBeenCalled()
    },
  )

  it('requests WebView capture only after the contextual Android grant', async () => {
    const permissionDenied = new DOMException('Denied by WebView', 'NotAllowedError')
    const getUserMedia = vi.fn(() => Promise.reject(permissionDenied))
    const nativePermission = {
      requestAfterSpeakingAction: vi.fn(async () => ({
        status: 'granted' as const,
      })),
    }

    vi.stubGlobal('navigator', { mediaDevices: { getUserMedia } })
    vi.stubGlobal('AudioContext', class {})
    Object.defineProperty(window, 'isSecureContext', {
      configurable: true,
      value: true,
    })

    await expect(
      new BrowserAudioRecorder(nativePermission, () => true).start(),
    ).rejects.toBeInstanceOf(RecordingPermissionDeniedError)
    expect(nativePermission.requestAfterSpeakingAction).toHaveBeenCalledOnce()
    expect(getUserMedia).toHaveBeenCalledOnce()
    expect(
      nativePermission.requestAfterSpeakingAction.mock.invocationCallOrder[0],
    ).toBeLessThan(getUserMedia.mock.invocationCallOrder[0]!)
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
