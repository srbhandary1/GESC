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

  it('keeps play disabled until authenticated audio is in a blob URL', async () => {
    vi.spyOn(URL, 'createObjectURL').mockReturnValue(
      'blob:authenticated-model-audio',
    )
    vi.spyOn(URL, 'revokeObjectURL').mockImplementation(() => undefined)
    let finishLoad: ((audio: Blob) => void) | undefined
    const loadSource = vi.fn(
      () =>
        new Promise<Blob>((resolve) => {
          finishLoad = resolve
        }),
    )

    render(
      <ModelAudioPlayer
        labels={labels}
        loadSource={loadSource}
        mimeType="audio/mpeg"
        source="/api/v1/me/levels/1/sessions/introduction/audio"
      />,
    )

    expect(
      screen.getByRole('button', { name: 'Loading' }),
    ).toBeDisabled()
    expect(screen.queryByTestId('model-audio-element')).toBeNull()

    finishLoad?.(new Blob(['audio'], { type: 'audio/mpeg' }))

    await screen.findByTestId('model-audio-element')
    expect(screen.getByRole('button', { name: 'Play' })).toBeEnabled()
  })

  it('loads authenticated audio into a revocable object URL', async () => {
    const createObjectUrl = vi
      .spyOn(URL, 'createObjectURL')
      .mockReturnValue('blob:authenticated-model-audio')
    const revokeObjectUrl = vi
      .spyOn(URL, 'revokeObjectURL')
      .mockImplementation(() => undefined)
    const loadSource = vi.fn(async () => new Blob(['audio']))

    const view = render(
      <ModelAudioPlayer
        labels={labels}
        loadSource={loadSource}
        mimeType="audio/wav"
        source="https://api.example.test/api/v1/media/model-audio"
      />,
    )

    expect(screen.queryByTestId('model-audio-element')).toBeNull()

    await screen.findByTestId('model-audio-element')

    expect(loadSource).toHaveBeenCalledWith(
      'https://api.example.test/api/v1/media/model-audio',
      expect.any(AbortSignal),
    )
    expect(createObjectUrl).toHaveBeenCalledTimes(1)
    expect(
      screen.getByTestId('model-audio-element').querySelector('source'),
    ).toHaveAttribute('src', 'blob:authenticated-model-audio')

    view.unmount()
    expect(revokeObjectUrl).toHaveBeenCalledWith(
      'blob:authenticated-model-audio',
    )
  })
})
