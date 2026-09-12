import { Capacitor } from '@capacitor/core'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { canAttemptMicrophoneCapture } from './canAttemptMicrophoneCapture'

vi.mock('@capacitor/core', () => ({
  Capacitor: {
    getPlatform: vi.fn(() => 'web'),
    isNativePlatform: vi.fn(() => false),
  },
}))

describe('canAttemptMicrophoneCapture', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
    vi.mocked(Capacitor.getPlatform).mockReturnValue('web')
    vi.mocked(Capacitor.isNativePlatform).mockReturnValue(false)
  })

  it('allows a secure context', () => {
    vi.stubGlobal('window', { isSecureContext: true })

    expect(canAttemptMicrophoneCapture()).toBe(true)
  })

  it('allows Capacitor Android even before mediaDevices appears', () => {
    vi.mocked(Capacitor.isNativePlatform).mockReturnValue(true)
    vi.mocked(Capacitor.getPlatform).mockReturnValue('android')
    vi.stubGlobal('window', { isSecureContext: false })

    expect(canAttemptMicrophoneCapture()).toBe(true)
  })
})
