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
    vi.stubGlobal('MediaRecorder', class {})
    vi.stubGlobal('AudioContext', class {})

    const starting = new BrowserAudioRecorder().start()

    expect(getUserMedia).toHaveBeenCalledOnce()
    await expect(starting).rejects.toBeInstanceOf(
      RecordingPermissionDeniedError,
    )
  })
})
