import {
  cleanup,
  render,
  screen,
  waitFor,
} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { DEFAULT_LOCALE } from '@global-english/localization'
import type { LearnerJourneyApi } from './LearnerJourneyApi'
import { LevelVideosPanel } from './LevelVideosPanel'

describe('LevelVideosPanel', () => {
  afterEach(cleanup)

  it('hides when the enrollment catalog is empty', async () => {
    const api = {
      listLevelVideos: vi.fn(async () => ({
        enrollment_id: '01J30000000000000000000004',
        videos: [],
      })),
    } as unknown as LearnerJourneyApi

    const { container } = render(
      <LevelVideosPanel
        api={api}
        enrollmentId="01J30000000000000000000004"
        locale={DEFAULT_LOCALE}
        localizationPolicy="development-preview"
      />,
    )

    await waitFor(() => expect(container).toBeEmptyDOMElement())
  })

  it('lists available videos and switches the player source', async () => {
    const user = userEvent.setup()
    const api = {
      listLevelVideos: vi.fn(async () => ({
        enrollment_id: '01J30000000000000000000004',
        videos: [
          {
            enrollment_id: '01J30000000000000000000004',
            lesson_id: '01J30000000000000000000021',
            level_label: 'Level A',
            publication_id: '01J30000000000000000000013',
            title: { locale: 'en-IN' as const, text: 'Level A Video' },
            video: {
              asset_id: '01J30000000000000000000031',
              byte_size: 128,
              mime_type: 'video/mp4' as const,
              stream_url: '/api/v1/media-assets/a/lesson-video',
            },
          },
          {
            enrollment_id: '01J30000000000000000000004',
            lesson_id: '01J30000000000000000000022',
            level_label: 'Level B',
            publication_id: '01J30000000000000000000013',
            title: { locale: 'en-IN' as const, text: 'Level B Video' },
            video: {
              asset_id: '01J30000000000000000000032',
              byte_size: 256,
              mime_type: 'video/mp4' as const,
              stream_url: '/api/v1/media-assets/b/lesson-video',
            },
          },
        ],
      })),
    } as unknown as LearnerJourneyApi

    render(
      <LevelVideosPanel
        api={api}
        enrollmentId="01J30000000000000000000004"
        locale={DEFAULT_LOCALE}
        localizationPolicy="development-preview"
      />,
    )

    expect(screen.getByRole('status')).toBeVisible()
    expect(await screen.findByTestId('lesson-video-element')).toBeVisible()
    expect(screen.getByTestId('lesson-video-element').querySelector('source'))
      .toHaveAttribute('src', '/api/v1/media-assets/a/lesson-video')

    await user.click(screen.getByRole('button', { name: 'Level B' }))
    expect(screen.getByTestId('lesson-video-element').querySelector('source'))
      .toHaveAttribute('src', '/api/v1/media-assets/b/lesson-video')
  })

  it('announces catalog failures and retries the same enrollment', async () => {
    const user = userEvent.setup()
    const api = {
      listLevelVideos: vi
        .fn()
        .mockRejectedValueOnce(new Error('catalog unavailable'))
        .mockResolvedValueOnce({
          enrollment_id: '01J30000000000000000000004',
          videos: [
            {
              enrollment_id: '01J30000000000000000000004',
              lesson_id: '01J30000000000000000000021',
              level_label: 'Level A',
              publication_id: '01J30000000000000000000013',
              title: {
                locale: 'en-IN' as const,
                text: 'Level A Video',
              },
              video: {
                asset_id: '01J30000000000000000000031',
                byte_size: 128,
                mime_type: 'video/mp4' as const,
                stream_url: '/api/v1/media-assets/a/lesson-video',
              },
            },
          ],
        }),
    } as unknown as LearnerJourneyApi

    render(
      <LevelVideosPanel
        api={api}
        enrollmentId="01J30000000000000000000004"
        locale={DEFAULT_LOCALE}
        localizationPolicy="development-preview"
      />,
    )

    expect(await screen.findByRole('alert')).not.toBeEmptyDOMElement()
    const retry = screen.getByRole('button')
    expect(retry).toHaveAccessibleName()
    await user.click(retry)

    expect(await screen.findByTestId('lesson-video-element')).toBeVisible()
    expect(api.listLevelVideos).toHaveBeenCalledTimes(2)
    expect(api.listLevelVideos).toHaveBeenNthCalledWith(
      2,
      '01J30000000000000000000004',
      expect.any(AbortSignal),
    )
  })
})
