import { afterEach, describe, expect, it, vi } from 'vitest'
import {
  BrowserAudioRecorder,
  RecordingPermissionDeniedError,
  RecordingUnavailableError,
} from './BrowserAudioRecorder'
import { canAttemptMicrophoneCapture } from './canAttemptMicrophoneCapture'

vi.mock('./canAttemptMicrophoneCapture', () => ({
  canAttemptMicrophoneCapture: vi.fn(() => false),
}))

describe('BrowserAudioRecorder', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
    vi.resetAllMocks()
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

  it('treats Capacitor Android as supported before getUserMedia appears', async () => {
    vi.mocked(canAttemptMicrophoneCapture).mockReturnValue(true)
    vi.stubGlobal('navigator', {})
    vi.stubGlobal('MediaRecorder', class {})

    expect(new BrowserAudioRecorder().isSupported()).toBe(true)
    await expect(new BrowserAudioRecorder().start()).rejects.toBeInstanceOf(
      RecordingUnavailableError,
    )
  })
})
