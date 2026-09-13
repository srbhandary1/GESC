import { describe, expect, it, vi } from 'vitest'
import {
  allowlistedPhase1AudioStreamPath,
  asPhase1AuthoredAudioLookup,
  createPhase1AuthorizedFetch,
  defaultPhase1AudioManifest,
  isPhase1PrivateLessonAudioPath,
  mimeTypeForAudioFile,
  parsePhase1SessionsIndex,
  phase1AudioCoverageTable,
  phase1LevelAudioIds,
  phase1LevelAudioPublicUrl,
  phase1LevelAudioRepoPath,
  phase1LevelPrivateSessionNumber,
  phase1PrivateIntroductionAudioPath,
  resolvePhase1AuthoredAudio,
  resolvePhase1AuthoredAudioLookup,
} from './phase1LevelAudioAssets'

function asFetch(
  impl: (input: RequestInfo | URL, init?: RequestInit) => Promise<Response>,
): typeof fetch {
  return impl as unknown as typeof fetch
}

describe('phase1 level audio contract', () => {
  it('lists Pre-1, Pre-2 and Levels 1–17', () => {
    expect(phase1LevelAudioIds()).toEqual([
      'pre-1',
      'pre-2',
      'level-1',
      'level-2',
      'level-3',
      'level-4',
      'level-5',
      'level-6',
      'level-7',
      'level-8',
      'level-9',
      'level-10',
      'level-11',
      'level-12',
      'level-13',
      'level-14',
      'level-15',
      'level-16',
      'level-17',
    ])
    expect(defaultPhase1AudioManifest().levels.every((level) => level.file === null)).toBe(
      true,
    )
  })

  it('uses the public drop-in path and conventional filename', () => {
    expect(phase1LevelAudioRepoPath('pre-1')).toBe(
      'apps/web/public/demo/phase1/audio/pre-1.mp3',
    )
    expect(phase1LevelAudioRepoPath('level-17', 'wav')).toBe(
      'apps/web/public/demo/phase1/audio/level-17.wav',
    )
    expect(phase1LevelAudioPublicUrl('level-1.mp3')).toMatch(
      /demo\/phase1\/audio\/level-1\.mp3$/,
    )
    expect(mimeTypeForAudioFile('pre-2.m4a')).toBe('audio/mp4')
    expect(phase1LevelPrivateSessionNumber('pre-1')).toBeNull()
    expect(phase1LevelPrivateSessionNumber('level-1')).toBe(1)
    expect(phase1LevelPrivateSessionNumber('level-17')).toBe(17)
    expect(phase1PrivateIntroductionAudioPath(3)).toBe(
      '/api/v1/me/levels/3/sessions/introduction/audio',
    )
    expect(
      isPhase1PrivateLessonAudioPath(
        '/api/v1/me/levels/1/sessions/introduction/audio',
      ),
    ).toBe(true)
    expect(
      isPhase1PrivateLessonAudioPath('/api/v1/media-assets/01/content'),
    ).toBe(false)
    expect(
      isPhase1PrivateLessonAudioPath(
        '/api/v1/me/levels/1/sessions/practice/audio',
      ),
    ).toBe(true)
    expect(
      allowlistedPhase1AudioStreamPath(
        '/api/v1/me/levels/4/sessions/introduction/audio',
        4,
      ),
    ).toBe('/api/v1/me/levels/4/sessions/introduction/audio')
    expect(
      allowlistedPhase1AudioStreamPath(
        '/api/v1/me/levels/4/sessions/introduction/audio',
        5,
      ),
    ).toBeNull()
    expect(
      allowlistedPhase1AudioStreamPath(
        'https://evil.example/api/v1/me/levels/1/sessions/introduction/audio',
      ),
    ).toBeNull()
    expect(
      parsePhase1SessionsIndex({
        data: [
          {
            part: 'practice',
            audio_stream_path: '/api/v1/me/levels/1/sessions/practice/audio',
          },
          {
            part: 'introduction',
            media_asset_id: '01JTESTASSET00000000000001',
            audio_stream_path:
              '/api/v1/me/levels/1/sessions/introduction/audio',
          },
        ],
      }),
    ).toEqual([
      {
        part: 'practice',
        audio_stream_path: '/api/v1/me/levels/1/sessions/practice/audio',
      },
      {
        part: 'introduction',
        media_asset_id: '01JTESTASSET00000000000001',
        audio_stream_path: '/api/v1/me/levels/1/sessions/introduction/audio',
      },
    ])
  })

  it('falls back to a probed drop-in when the private stream is absent', async () => {
    const fetchImpl = asFetch(async (input, init) => {
      const href = String(input)
      if (href.endsWith('level-1.mp3') && init?.method === 'HEAD') {
        return new Response(null, {
          status: 200,
          headers: { 'Content-Type': 'audio/mpeg' },
        })
      }

      return new Response(null, { status: 404 })
    })

    await expect(
      resolvePhase1AuthoredAudio('level-1', fetchImpl),
    ).resolves.toMatchObject({
      file: 'level-1.mp3',
      mimeType: 'audio/mpeg',
      source: 'probe',
    })
    await expect(
      resolvePhase1AuthoredAudio('pre-1', fetchImpl),
    ).resolves.toBeNull()
    await expect(
      resolvePhase1AuthoredAudioLookup('pre-1', fetchImpl),
    ).resolves.toEqual({ status: 'missing' })

    expect(
      phase1AudioCoverageTable({
        'level-1': 'level-1.mp3',
      }),
    ).toEqual(
      phase1LevelAudioIds().map((levelId) => ({
        levelId,
        file: levelId === 'level-1' ? 'level-1.mp3' : 'MISSING',
      })),
    )
  })

  it('uses a manifest file entry before probing', async () => {
    const fetchImpl = asFetch(async (input) => {
      const href = String(input)
      if (href.endsWith('manifest.json')) {
        return new Response(
          JSON.stringify({
            schemaVersion: 1,
            kind: 'phase1-level-audio-lessons',
            teacher: 'Swathi',
            basePath: '/demo/phase1/audio/',
            acceptedExtensions: ['mp3'],
            levels: [{ id: 'pre-2', file: 'owned-pre-2.wav', mimeType: 'audio/wav' }],
          }),
          { status: 200, headers: { 'Content-Type': 'application/json' } },
        )
      }

      return new Response(null, { status: 404 })
    })

    await expect(
      resolvePhase1AuthoredAudio('pre-2', fetchImpl),
    ).resolves.toMatchObject({
      file: 'owned-pre-2.wav',
      mimeType: 'audio/wav',
      source: 'manifest',
    })
  })

  it('reports unauthenticated when the private sessions index is 401', async () => {
    const fetchImpl = asFetch(async (input) => {
      const href = String(input)
      if (href.includes('/me/levels/1/sessions') && !href.includes('/audio')) {
        return new Response(JSON.stringify({ title: 'Unauthenticated' }), {
          status: 401,
          headers: { 'Content-Type': 'application/problem+json' },
        })
      }

      return new Response(null, { status: 404 })
    })

    await expect(
      resolvePhase1AuthoredAudioLookup('level-1', fetchImpl),
    ).resolves.toEqual({ status: 'unauthenticated' })
    await expect(
      resolvePhase1AuthoredAudio('level-1', fetchImpl),
    ).resolves.toBeNull()
  })

  it('reports unauthenticated when the private introduction stream is 401', async () => {
    const fetchImpl = asFetch(async (input) => {
      const href = String(input)
      if (href.includes('/me/levels/1/sessions/introduction/audio')) {
        return new Response(JSON.stringify({ title: 'Unauthenticated' }), {
          status: 401,
          headers: { 'Content-Type': 'application/problem+json' },
        })
      }

      return new Response(null, { status: 404 })
    })

    await expect(
      resolvePhase1AuthoredAudioLookup('level-1', fetchImpl),
    ).resolves.toEqual({ status: 'unauthenticated' })
    await expect(
      resolvePhase1AuthoredAudio('level-1', fetchImpl),
    ).resolves.toBeNull()
  })

  it('never calls /me/levels for Pre-1 or Pre-2', async () => {
    const fetchImpl = asFetch(async (input) => {
      const href = String(input)
      if (href.includes('/me/levels')) {
        throw new Error(`Pre-level must not call ${href}`)
      }

      return new Response(null, { status: 404 })
    })

    await expect(
      resolvePhase1AuthoredAudioLookup('pre-1', fetchImpl),
    ).resolves.toEqual({ status: 'missing' })
    await expect(
      resolvePhase1AuthoredAudioLookup('pre-2', fetchImpl),
    ).resolves.toEqual({ status: 'missing' })
  })

  it('prefers an allowlisted audio_stream_path from the sessions index', async () => {
    const createObjectURL = vi
      .spyOn(URL, 'createObjectURL')
      .mockReturnValue('blob:level-3-introduction')
    const requests: { href: string; credentials?: RequestCredentials }[] = []

    const fetchImpl = asFetch(async (input, init) => {
      const href = String(input)
      requests.push({ href, credentials: init?.credentials })

      if (href.endsWith('/me/levels/3/sessions')) {
        return Response.json({
          data: [
            {
              part: 'practice',
              audio_stream_path: '/api/v1/me/levels/3/sessions/practice/audio',
            },
            {
              part: 'introduction',
              media_asset_id: '01JTESTASSET00000000000003',
              audio_stream_path:
                '/api/v1/me/levels/3/sessions/introduction/audio',
            },
          ],
        })
      }

      if (href.includes('/me/levels/3/sessions/introduction/audio')) {
        return new Response(new Uint8Array([9, 8, 7, 6]), {
          status: 200,
          headers: { 'Content-Type': 'audio/mpeg' },
        })
      }

      if (href.includes('/me/levels/3/sessions/practice/audio')) {
        throw new Error('Audio Lessons must use introduction, not practice')
      }

      return new Response(null, { status: 404 })
    })

    await expect(
      resolvePhase1AuthoredAudioLookup('level-3', fetchImpl),
    ).resolves.toEqual({
      status: 'ready',
      audio: {
        levelId: 'level-3',
        url: 'blob:level-3-introduction',
        mimeType: 'audio/mpeg',
        file: '/api/v1/me/levels/3/sessions/introduction/audio',
        source: 'private-api',
      },
    })
    expect(requests.every((request) => request.credentials === 'include')).toBe(
      true,
    )

    createObjectURL.mockRestore()
  })

  it('rejects a non-allowlisted stream path and uses the introduction fallback', async () => {
    const createObjectURL = vi
      .spyOn(URL, 'createObjectURL')
      .mockReturnValue('blob:level-5-introduction')

    const fetchImpl = asFetch(async (input) => {
      const href = String(input)
      if (href.endsWith('/me/levels/5/sessions')) {
        return Response.json({
          data: [
            {
              part: 'introduction',
              audio_stream_path: 'https://evil.example/stolen.mp3',
            },
          ],
        })
      }

      if (href.includes('/me/levels/5/sessions/introduction/audio')) {
        return new Response(new Uint8Array([4, 5, 6]), {
          status: 200,
          headers: { 'Content-Type': 'audio/mpeg' },
        })
      }

      return new Response(null, { status: 404 })
    })

    await expect(
      resolvePhase1AuthoredAudioLookup('level-5', fetchImpl),
    ).resolves.toMatchObject({
      status: 'ready',
      audio: {
        source: 'private-api',
        file: '/api/v1/me/levels/5/sessions/introduction/audio',
        url: 'blob:level-5-introduction',
      },
    })

    createObjectURL.mockRestore()
  })

  it('prefers the signed-in stream over a local drop-in for Levels 1–17', async () => {
    const createObjectURL = vi
      .spyOn(URL, 'createObjectURL')
      .mockReturnValue('blob:level-1-private')

    const fetchImpl = asFetch(async (input) => {
      const href = String(input)
      if (href.endsWith('/me/levels/1/sessions')) {
        return Response.json({
          data: [
            {
              part: 'introduction',
              audio_stream_path:
                '/api/v1/me/levels/1/sessions/introduction/audio',
            },
          ],
        })
      }

      if (href.includes('/me/levels/1/sessions/introduction/audio')) {
        return new Response(new Uint8Array([1, 2, 3]), {
          status: 200,
          headers: { 'Content-Type': 'audio/mpeg' },
        })
      }

      if (href.endsWith('level-1.mp3')) {
        return new Response(null, {
          status: 200,
          headers: { 'Content-Type': 'audio/mpeg' },
        })
      }

      return new Response(null, { status: 404 })
    })

    await expect(
      resolvePhase1AuthoredAudioLookup('level-1', fetchImpl),
    ).resolves.toMatchObject({
      status: 'ready',
      audio: {
        source: 'private-api',
        url: 'blob:level-1-private',
      },
    })

    createObjectURL.mockRestore()
  })

  it('attaches a Bearer token when the authorized fetch wrapper is used', async () => {
    const createObjectURL = vi
      .spyOn(URL, 'createObjectURL')
      .mockReturnValue('blob:level-7-bearer')
    const headers: string[] = []

    const fetchImpl = asFetch(async (input, init) => {
      const href = String(input)
      headers.push(new Headers(init?.headers).get('Authorization') ?? '')

      if (href.endsWith('/me/levels/7/sessions')) {
        return Response.json({
          data: [
            {
              part: 'introduction',
              audio_stream_path:
                '/api/v1/me/levels/7/sessions/introduction/audio',
            },
          ],
        })
      }

      if (href.includes('/me/levels/7/sessions/introduction/audio')) {
        return new Response(new Uint8Array([7, 7, 7]), {
          status: 200,
          headers: { 'Content-Type': 'audio/mpeg' },
        })
      }

      return new Response(null, { status: 404 })
    })

    const authorized = createPhase1AuthorizedFetch(fetchImpl, (next) => {
      next.set('Authorization', 'Bearer memory-only-access-token')
    })

    await expect(
      resolvePhase1AuthoredAudioLookup('level-7', authorized),
    ).resolves.toMatchObject({
      status: 'ready',
      audio: { source: 'private-api', url: 'blob:level-7-bearer' },
    })
    expect(headers.every((value) => value === 'Bearer memory-only-access-token')).toBe(
      true,
    )

    createObjectURL.mockRestore()
  })

  it('does not treat an HTML 200 for a drop-in filename as a master', async () => {
    const fetchImpl = asFetch(async () => {
      return new Response('<!doctype html><title>demo</title>', {
        status: 200,
        headers: { 'Content-Type': 'text/html' },
      })
    })

    await expect(
      resolvePhase1AuthoredAudioLookup('level-1', fetchImpl),
    ).resolves.toEqual({ status: 'missing' })
  })

  it('treats an HTML SPA fallback as missing, not authored audio', async () => {
    const fetchImpl = asFetch(async (input) => {
      const href = String(input)
      if (href.includes('/me/levels/17/sessions/introduction/audio')) {
        return new Response('<!doctype html><title>demo</title>', {
          status: 200,
          headers: { 'Content-Type': 'text/html; charset=utf-8' },
        })
      }

      return new Response(null, { status: 404 })
    })

    await expect(
      resolvePhase1AuthoredAudioLookup('level-17', fetchImpl),
    ).resolves.toEqual({ status: 'missing' })
  })

  it('plays verified private bytes when the introduction stream is 200', async () => {
    const createObjectURL = vi
      .spyOn(URL, 'createObjectURL')
      .mockReturnValue('blob:level-1-introduction')

    const fetchImpl = asFetch(async (input) => {
      const href = String(input)
      if (href.includes('/me/levels/2/sessions/introduction/audio')) {
        return new Response(new Uint8Array([1, 2, 3, 4]), {
          status: 200,
          headers: { 'Content-Type': 'audio/mpeg' },
        })
      }

      return new Response(null, { status: 404 })
    })

    await expect(
      resolvePhase1AuthoredAudioLookup('level-2', fetchImpl),
    ).resolves.toEqual({
      status: 'ready',
      audio: {
        levelId: 'level-2',
        url: 'blob:level-1-introduction',
        mimeType: 'audio/mpeg',
        file: '/api/v1/me/levels/2/sessions/introduction/audio',
        source: 'private-api',
      },
    })

    createObjectURL.mockRestore()
  })

  it('normalizes legacy resolver results', () => {
    expect(asPhase1AuthoredAudioLookup(null)).toEqual({ status: 'missing' })
    expect(asPhase1AuthoredAudioLookup({ status: 'unauthenticated' })).toEqual({
      status: 'unauthenticated',
    })
    expect(
      asPhase1AuthoredAudioLookup({
        levelId: 'level-1',
        url: '/demo/phase1/audio/level-1.mp3',
        mimeType: 'audio/mpeg',
        file: 'level-1.mp3',
        source: 'probe',
      }),
    ).toMatchObject({ status: 'ready', audio: { file: 'level-1.mp3' } })
  })
})
