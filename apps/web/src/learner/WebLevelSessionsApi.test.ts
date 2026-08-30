import { describe, expect, it, vi } from 'vitest'
import { WebLevelSessionsApi } from './WebLevelSessionsApi'

describe('WebLevelSessionsApi', () => {
  it('loads Android private lesson audio through the injected authenticated fetch', async () => {
    const audio = new Blob(['private-lesson-audio'], { type: 'audio/mpeg' })
    const fetcher = vi.fn(
      async (input: RequestInfo | URL, init?: RequestInit) => {
        expect(input).toBe('/api/v1/me/levels/1/sessions/introduction/audio')
        expect(new Headers(init?.headers).get('Accept')).toBe('audio/*')

        return new Response(audio, {
          headers: { 'Content-Type': 'audio/mpeg' },
        })
      },
    )
    const api = new WebLevelSessionsApi({
      authenticatedPrivateMedia: true,
      csrfProtection: 'none',
      fetch: fetcher as typeof fetch,
    })

    await expect(
      api.downloadAudio?.(
        '/api/v1/me/levels/1/sessions/introduction/audio',
      ),
    ).resolves.toEqual(audio)
    expect(fetcher).toHaveBeenCalledTimes(1)
  })

  it('rejects lesson audio paths outside the level-session stream contract', async () => {
    const fetcher = vi.fn(async () => new Response(null, { status: 204 }))
    const api = new WebLevelSessionsApi({
      authenticatedPrivateMedia: true,
      csrfProtection: 'none',
      fetch: fetcher as typeof fetch,
    })

    await expect(
      api.downloadAudio?.('/api/v1/media-assets/audio/model-audio'),
    ).rejects.toThrow('level_sessions_audio_path_rejected')
    await expect(
      api.downloadAudio?.(
        '/api/v1/me/levels/1/sessions/introduction/audio?redirect=https://example.test',
      ),
    ).rejects.toThrow('level_sessions_audio_path_rejected')
    expect(fetcher).not.toHaveBeenCalled()
  })

  it('does not expose a lesson-audio downloader when private media uses cookies', () => {
    const api = new WebLevelSessionsApi({
      csrfProtection: 'none',
      fetch: vi.fn() as unknown as typeof fetch,
    })

    expect(api.downloadAudio).toBeUndefined()
  })
})
