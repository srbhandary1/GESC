import { describe, expect, it, vi } from 'vitest'
import { WebLearnerJourneyApi } from './WebLearnerJourneyApi'

describe('WebLearnerJourneyApi', () => {
  it('lists pin-gated level videos for an enrollment', async () => {
    const fetcher = vi.fn(
      async (input: RequestInfo | URL, init?: RequestInit) => {
        const request = new Request(input, init)
        expect(request.url).toBe(
          'https://app.global-english.test/api/v1/me/level-videos?enrollment_id=01J30000000000000000000004',
        )
        expect(request.credentials).toBe('include')

        return Response.json({
          enrollment_id: '01J30000000000000000000004',
          videos: [],
        })
      },
    )
    const api = new WebLearnerJourneyApi({
      baseUrl: 'https://app.global-english.test/api/v1',
      fetch: fetcher as typeof fetch,
    })

    await expect(
      api.listLevelVideos('01J30000000000000000000004'),
    ).resolves.toEqual({
      enrollment_id: '01J30000000000000000000004',
      videos: [],
    })
  })

  it('uses cookie CSRF and idempotency without sending recording bytes or a digest', async () => {
    const requests: Request[] = []
    const fetcher = vi.fn(
      async (input: RequestInfo | URL, init?: RequestInit) => {
        const request = new Request(input, init)
        requests.push(request)

        if (request.url.endsWith('/sanctum/csrf-cookie')) {
          return new Response(null, { status: 204 })
        }

        if (request.url.endsWith('/responses')) {
          return new Response(null, { status: 204 })
        }

        throw new Error(`Unexpected request: ${request.url}`)
      },
    )
    const api = new WebLearnerJourneyApi({
      baseUrl: 'https://app.global-english.test/api/v1',
      fetch: fetcher as typeof fetch,
      readCookie: () => 'XSRF-TOKEN=csrf%20token',
    })

    await api.recordResponse('attempt-id', {
      check_id: 'check-id',
      local_recording: {
        byte_size: 2048,
        captured: true,
        client_capture_id: '018f4f82-6400-7a12-8f4b-20b12bf6b6f3',
        duration_ms: 1250,
        mime_type: 'audio/webm',
      },
      selected_option_id: 'option-id',
    }, 'command-id')

    const mutation = requests.at(-1)!
    const body = await mutation.json()

    expect(mutation.credentials).toBe('include')
    expect(mutation.headers.get('X-XSRF-TOKEN')).toBe('csrf token')
    expect(mutation.headers.get('Idempotency-Key')).toBe('command-id')
    expect(body).toEqual({
      check_id: 'check-id',
      local_recording: {
        byte_size: 2048,
        captured: true,
        client_capture_id: '018f4f82-6400-7a12-8f4b-20b12bf6b6f3',
        duration_ms: 1250,
        mime_type: 'audio/webm',
      },
      selected_option_id: 'option-id',
    })
    expect(JSON.stringify(body)).not.toContain('sha256')
    expect(JSON.stringify(body)).not.toContain('blob:')
  })

  it('uses the Android bearer fetch without a Sanctum CSRF preflight', async () => {
    const requests: Request[] = []
    const fetcher = vi.fn(
      async (input: RequestInfo | URL, init?: RequestInit) => {
        const request = new Request(input, init)
        requests.push(request)

        return Response.json({ onboarding: 'complete' })
      },
    )
    const api = new WebLearnerJourneyApi({
      baseUrl: 'https://api.global-english.test/api/v1',
      csrfProtection: 'none',
      fetch: fetcher as typeof fetch,
    })

    await api.updateOnboarding(
      {
        adult_confirmed: true,
        goal_key: 'everyday-conversation',
      },
      'android-command-id',
    )

    expect(requests).toHaveLength(1)
    expect(requests[0]?.url).toBe(
      'https://api.global-english.test/api/v1/me/onboarding',
    )
    expect(requests[0]?.headers.get('Idempotency-Key')).toBe(
      'android-command-id',
    )
    expect(requests[0]?.headers.get('X-XSRF-TOKEN')).toBeNull()
  })

  it('loads Android private video bytes through the injected authenticated fetch', async () => {
    const video = new Blob(['private-video'], { type: 'video/mp4' })
    const fetcher = vi.fn(
      async (input: RequestInfo | URL, init?: RequestInit) => {
        expect(input).toBe('/api/v1/media-assets/video/lesson-video')
        expect(new Headers(init?.headers).get('Accept')).toBe('video/mp4')

        return new Response(video, {
          headers: {
            'Content-Length': String(video.size),
            'Content-Type': 'video/mp4',
          },
        })
      },
    )
    const api = new WebLearnerJourneyApi({
      authenticatedPrivateMedia: true,
      csrfProtection: 'none',
      fetch: fetcher as typeof fetch,
    })

    await expect(
      api.downloadLessonVideo?.(
        '/api/v1/media-assets/video/lesson-video',
        video.size,
      ),
    ).resolves.toMatchObject({ size: video.size, type: 'video/mp4' })
    expect(fetcher).toHaveBeenCalledTimes(1)
  })

  it('rejects Android video metadata or bodies outside the catalog bound', async () => {
    const fetcher = vi
      .fn()
      .mockResolvedValueOnce(
        new Response('short', {
          headers: {
            'Content-Length': '6',
            'Content-Type': 'video/mp4',
          },
        }),
      )
      .mockResolvedValueOnce(
        new Response('longer!', {
          headers: {
            'Content-Length': '6',
            'Content-Type': 'video/mp4',
          },
        }),
      )
      .mockResolvedValueOnce(
        new Response('video', {
          headers: {
            'Content-Length': '5',
            'Content-Type': 'text/plain',
          },
        }),
      )
    const api = new WebLearnerJourneyApi({
      authenticatedPrivateMedia: true,
      csrfProtection: 'none',
      fetch: fetcher as typeof fetch,
    })
    const url = '/api/v1/media-assets/video/lesson-video'

    await expect(api.downloadLessonVideo?.(url, 5)).rejects.toThrow(
      'The learner journey could not be completed.',
    )
    await expect(api.downloadLessonVideo?.(url, 6)).rejects.toThrow(
      'The learner journey could not be completed.',
    )
    await expect(api.downloadLessonVideo?.(url, 5)).rejects.toThrow(
      'The learner journey could not be completed.',
    )
    await expect(
      api.downloadLessonVideo?.(url, 20_000_001),
    ).rejects.toThrow('The learner journey could not be completed.')
    expect(fetcher).toHaveBeenCalledTimes(3)
  })

  it('loads Android private audio bytes through the injected authenticated fetch', async () => {
    const audio = new Blob(['private-audio'], { type: 'audio/wav' })
    const fetcher = vi.fn(
      async (input: RequestInfo | URL, init?: RequestInit) => {
        expect(input).toBe('/api/v1/media-assets/audio/model-audio')
        expect(new Headers(init?.headers).get('Accept')).toBe('audio/*')

        return new Response(audio, {
          headers: { 'Content-Type': 'audio/wav' },
        })
      },
    )
    const api = new WebLearnerJourneyApi({
      authenticatedPrivateMedia: true,
      csrfProtection: 'none',
      fetch: fetcher as typeof fetch,
    })

    await expect(
      api.downloadModelAudio?.(
        '/api/v1/media-assets/audio/model-audio',
      ),
    ).resolves.toEqual(audio)
    expect(fetcher).toHaveBeenCalledTimes(1)
  })

  it('reuses the caller command id after an ambiguous network failure', async () => {
    const mutationKeys: Array<string | null> = []
    let mutationAttempts = 0
    const fetcher = vi.fn(
      async (input: RequestInfo | URL, init?: RequestInit) => {
        const request = new Request(input, init)

        if (request.url.endsWith('/sanctum/csrf-cookie')) {
          return new Response(null, { status: 204 })
        }

        mutationKeys.push(request.headers.get('Idempotency-Key'))
        mutationAttempts += 1
        if (mutationAttempts === 1) {
          throw new TypeError('connection ended after request write')
        }

        return new Response(null, { status: 204 })
      },
    )
    const api = new WebLearnerJourneyApi({
      baseUrl: 'https://app.global-english.test/api/v1',
      fetch: fetcher as typeof fetch,
      readCookie: () => 'XSRF-TOKEN=csrf-token',
    })
    const input = {
      check_id: 'check-id',
      local_recording: null,
      selected_option_id: 'option-id',
    } as const

    await expect(
      api.recordResponse('attempt-id', input, 'stable-command-id'),
    ).rejects.toThrow('The learner journey could not be completed.')
    await expect(
      api.recordResponse('attempt-id', input, 'stable-command-id'),
    ).resolves.toBeUndefined()

    expect(mutationKeys).toEqual([
      'stable-command-id',
      'stable-command-id',
    ])
  })

  it('submits an attempt with an empty request and no JSON content type', async () => {
    const requests: Request[] = []
    const fetcher = vi.fn(
      async (input: RequestInfo | URL, init?: RequestInit) => {
        const request = new Request(input, init)
        requests.push(request)

        if (request.url.endsWith('/sanctum/csrf-cookie')) {
          return new Response(null, { status: 204 })
        }

        return Response.json({})
      },
    )
    const api = new WebLearnerJourneyApi({
      baseUrl: 'https://app.global-english.test/api/v1',
      fetch: fetcher as typeof fetch,
      readCookie: () => 'XSRF-TOKEN=csrf-token',
    })

    await api.submitAttempt('attempt-id', 'submit-command-id')

    const mutation = requests.at(-1)!
    await expect(mutation.text()).resolves.toBe('')
    expect(mutation.headers.get('Content-Type')).toBeNull()
    expect(mutation.headers.get('Idempotency-Key')).toBe(
      'submit-command-id',
    )
  })
})
