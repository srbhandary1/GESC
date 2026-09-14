import type { AudioControlLabels } from '@global-english/design-system'
import { cleanup, render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
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
        seekLabel="Seek"
        slowLabel="Slow"
        source="/first.wav"
        stopLabel="Stop"
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
        seekLabel="Seek"
        slowLabel="Slow"
        source="/second.wav"
        stopLabel="Stop"
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
        seekLabel="Seek"
        slowLabel="Slow"
        source="/api/v1/me/levels/1/sessions/introduction/audio"
        stopLabel="Stop"
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
        seekLabel="Seek"
        slowLabel="Slow"
        source="https://api.example.test/api/v1/media/model-audio"
        stopLabel="Stop"
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

  it('exposes stop and seek controls while audio is playing', async () => {
    const user = userEvent.setup()
    const play = vi
      .spyOn(HTMLMediaElement.prototype, 'play')
      .mockResolvedValue(undefined)
    Object.defineProperty(HTMLMediaElement.prototype, 'duration', {
      configurable: true,
      get: () => 12,
    })
    Object.defineProperty(HTMLMediaElement.prototype, 'currentTime', {
      configurable: true,
      get: () => 4,
      set: () => undefined,
    })

    render(
      <ModelAudioPlayer
        labels={labels}
        mimeType="audio/wav"
        seekLabel="Seek"
        slowLabel="Slow"
        source="/first.wav"
        stopLabel="Stop"
      />,
    )

    const audio = screen.getByTestId('model-audio-element')
    audio.dispatchEvent(new Event('loadedmetadata'))
    audio.dispatchEvent(new Event('canplay'))
    await user.click(screen.getByRole('button', { name: 'Play' }))
    audio.dispatchEvent(new Event('playing'))

    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Stop' })).toBeInTheDocument()
    })
    expect(screen.getByRole('slider', { name: 'Seek' })).toBeInTheDocument()
    play.mockRestore()
  })

  it('exposes a slow-speed toggle without resetting resume position', async () => {
    const user = userEvent.setup()
    render(
      <ModelAudioPlayer
        labels={labels}
        mimeType="audio/wav"
        seekLabel="Seek"
        slowLabel="Slow"
        source="/first.wav"
        stopLabel="Stop"
      />,
    )

    const slow = screen.getByRole('button', { name: 'Slow' })
    expect(slow).toHaveAttribute('aria-pressed', 'false')
    await user.click(slow)
    expect(slow).toHaveAttribute('aria-pressed', 'true')
    expect(screen.getByRole('button', { name: 'Play' })).toBeEnabled()
  })
})
