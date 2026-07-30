import type { AudioControlLabels } from '@global-english/design-system'
import { cleanup, render, screen } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ModelAudioPlayer } from './ModelAudioPlayer'

const labels: AudioControlLabels = {
  endedStatus: 'Ended',
  error: 'Error',
  errorStatus: 'Error',
  loading: 'Loading',
  loadingStatus: 'Loading',
  pause: 'Pause',
  pausedStatus: 'Paused',
  play: 'Play',
  playingStatus: 'Playing',
  readyStatus: 'Ready',
  region: 'Model audio',
  replay: 'Replay',
  resume: 'Resume',
  unavailable: 'Unavailable',
  unavailableStatus: 'Unavailable',
}

beforeEach(() => {
  vi.spyOn(HTMLMediaElement.prototype, 'load').mockImplementation(
    () => undefined,
  )
  vi.spyOn(HTMLMediaElement.prototype, 'pause').mockImplementation(
    () => undefined,
  )
})

afterEach(() => {
  cleanup()
  vi.restoreAllMocks()
})

describe('ModelAudioPlayer', () => {
  it('never autoplays and releases the prior source before loading a new one', () => {
    const view = render(
      <ModelAudioPlayer
        labels={labels}
        mimeType="audio/wav"
        source="/first.wav"
      />,
    )
    const firstAudio = screen.getByTestId('model-audio-element')
    const firstSource = firstAudio.querySelector('source')!

    expect(firstAudio).not.toHaveAttribute('autoplay')
    expect(firstSource).toHaveAttribute('src', '/first.wav')
    expect(HTMLMediaElement.prototype.load).toHaveBeenCalled()

    view.rerender(
      <ModelAudioPlayer
        labels={labels}
        mimeType="audio/wav"
        source="/second.wav"
      />,
    )

    const secondAudio = screen.getByTestId('model-audio-element')
    expect(secondAudio).not.toBe(firstAudio)
    expect(firstSource).not.toHaveAttribute('src')
    expect(secondAudio.querySelector('source')).toHaveAttribute(
      'src',
      '/second.wav',
    )
    expect(HTMLMediaElement.prototype.pause).toHaveBeenCalled()
  })
})
