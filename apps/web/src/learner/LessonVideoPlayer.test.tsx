import {
  fireEvent,
  cleanup,
  render,
  screen,
  waitFor,
} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { LessonVideoPlayer } from './LessonVideoPlayer'

const commonLabels = {
  errorLabel: 'Video unavailable.',
  loadingLabel: 'Loading video.',
  retryLabel: 'Try again',
}

describe('LessonVideoPlayer', () => {
  afterEach(() => {
    cleanup()
    vi.restoreAllMocks()
  })

  it('renders an accessible native video and exposes a recoverable media error', async () => {
    const user = userEvent.setup()
    vi.spyOn(HTMLMediaElement.prototype, 'pause').mockImplementation(
      () => undefined,
    )
    vi.spyOn(HTMLMediaElement.prototype, 'load').mockImplementation(
      () => undefined,
    )
    render(
      <LessonVideoPlayer
        {...commonLabels}
        byteSize={128}
        label="Level 1 Video"
        mimeType="video/mp4"
        source="/api/v1/media-assets/01J00000000000000000000033/lesson-video?enrollment_id=01J00000000000000000000004&lesson_id=01J00000000000000000000008"
      />,
    )

    expect(screen.getByText('Level 1 Video')).toBeInTheDocument()
    const video = screen.getByTestId('lesson-video-element')
    expect(video).toHaveAttribute('controls')
    expect(video).toHaveAccessibleName('Level 1 Video')
    expect(video.querySelector('source')).toHaveAttribute(
      'type',
      'video/mp4',
    )

    fireEvent.error(video)
    expect(screen.getByRole('alert')).toHaveTextContent(
      'Video unavailable.',
    )
    expect(screen.queryByTestId('lesson-video-element')).not
      .toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Try again' }))
    expect(screen.getByTestId('lesson-video-element')).toBeVisible()
  })

  it('revokes authenticated object URLs and resets media on source changes and unmount', async () => {
    const createObjectURL = vi
      .spyOn(URL, 'createObjectURL')
      .mockReturnValueOnce('blob:android-private-video-a')
      .mockReturnValueOnce('blob:android-private-video-b')
    const revokeObjectURL = vi
      .spyOn(URL, 'revokeObjectURL')
      .mockImplementation(() => undefined)
    const pause = vi
      .spyOn(HTMLMediaElement.prototype, 'pause')
      .mockImplementation(() => undefined)
    const load = vi
      .spyOn(HTMLMediaElement.prototype, 'load')
      .mockImplementation(() => undefined)
    const loadSource = vi.fn(async () =>
      new Blob(['video'], { type: 'video/mp4' }),
    )

    const { container, rerender, unmount } = render(
      <LessonVideoPlayer
        {...commonLabels}
        byteSize={5}
        label="Private Android Video A"
        loadSource={loadSource}
        mimeType="video/mp4"
        source="/api/v1/media-assets/private-a/lesson-video"
      />,
    )

    expect(screen.getByRole('status')).toHaveTextContent('Loading video.')
    await waitFor(() => {
      expect(container.querySelector('video source'))
        .toHaveAttribute('src', 'blob:android-private-video-a')
    })
    expect(loadSource).toHaveBeenNthCalledWith(
      1,
      '/api/v1/media-assets/private-a/lesson-video',
      5,
      expect.any(AbortSignal),
    )

    rerender(
      <LessonVideoPlayer
        {...commonLabels}
        byteSize={6}
        label="Private Android Video B"
        loadSource={loadSource}
        mimeType="video/mp4"
        source="/api/v1/media-assets/private-b/lesson-video"
      />,
    )

    await waitFor(() => {
      expect(container.querySelector('video source'))
        .toHaveAttribute('src', 'blob:android-private-video-b')
    })
    expect(revokeObjectURL).toHaveBeenCalledWith(
      'blob:android-private-video-a',
    )
    expect(loadSource).toHaveBeenNthCalledWith(
      2,
      '/api/v1/media-assets/private-b/lesson-video',
      6,
      expect.any(AbortSignal),
    )

    unmount()
    expect(revokeObjectURL).toHaveBeenCalledWith(
      'blob:android-private-video-b',
    )
    expect(createObjectURL).toHaveBeenCalledTimes(2)
    expect(pause).toHaveBeenCalled()
    expect(load).toHaveBeenCalled()
  })

  it('shows authenticated loading failures and retries the bounded request', async () => {
    const user = userEvent.setup()
    vi.spyOn(URL, 'createObjectURL').mockReturnValue(
      'blob:retried-private-video',
    )
    const revokeObjectURL = vi
      .spyOn(URL, 'revokeObjectURL')
      .mockImplementation(() => undefined)
    const loadSource = vi
      .fn<() => Promise<Blob>>()
      .mockRejectedValueOnce(new Error('network unavailable'))
      .mockResolvedValueOnce(
        new Blob(['video'], { type: 'video/mp4' }),
      )

    render(
      <LessonVideoPlayer
        {...commonLabels}
        byteSize={5}
        label="Private Android Video"
        loadSource={loadSource}
        mimeType="video/mp4"
        source="/api/v1/media-assets/private/lesson-video"
      />,
    )

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Video unavailable.',
    )
    await user.click(screen.getByRole('button', { name: 'Try again' }))

    const video = await screen.findByTestId('lesson-video-element')
    expect(video).toBeVisible()
    expect(loadSource).toHaveBeenCalledTimes(2)

    fireEvent.error(video)
    expect(revokeObjectURL).toHaveBeenCalledWith(
      'blob:retried-private-video',
    )
    expect(screen.getByRole('alert')).toBeVisible()
  })
})
