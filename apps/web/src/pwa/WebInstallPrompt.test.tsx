import { cleanup, render } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { WebInstallPrompt } from './WebInstallPrompt'
import { announceSpeakingTurnCompleted } from './speakingTurnCompleted'

afterEach(() => {
  cleanup()
  window.localStorage.clear()
  vi.restoreAllMocks()
})

beforeEach(() => {
  window.localStorage.clear()
})

describe('web install prompt', () => {
  it('does not prompt on first paint and asks once after a speaking turn', () => {
    const prompt = vi.fn(async () => undefined)
    render(<WebInstallPrompt profileKey="membership-a" />)

    const installEvent = new Event('beforeinstallprompt') as Event & {
      prompt: () => Promise<void>
    }
    installEvent.prompt = prompt
    window.dispatchEvent(installEvent)
    expect(prompt).not.toHaveBeenCalled()

    announceSpeakingTurnCompleted()
    expect(prompt).toHaveBeenCalledOnce()
    expect(
      window.localStorage.getItem('global-english:pwa-install:v1:membership-a'),
    ).toBe('prompted')

    announceSpeakingTurnCompleted()
    expect(prompt).toHaveBeenCalledOnce()
  })
})
