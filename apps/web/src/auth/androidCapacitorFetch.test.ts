import { describe, expect, it, vi } from 'vitest'
import { createAndroidCapacitorFetch } from './androidCapacitorFetch'

describe('createAndroidCapacitorFetch', () => {
  it('rewrites Capacitor interceptor responses that claim Content-Length 0', async () => {
    const body = JSON.stringify({
      user: { id: '01TESTUSER0000000000000000' },
      current_membership: null,
      memberships: [],
    })
    const baseFetch = vi.fn(async () => {
      return new Response(body, {
        headers: {
          'Content-Length': '0',
          'Content-Type': 'application/json',
        },
        status: 200,
      })
    })

    const fetch = createAndroidCapacitorFetch(baseFetch as typeof globalThis.fetch)
    const response = await fetch('http://10.0.2.2:8000/api/v1/me')

    expect(response.headers.get('Content-Length')).toBeNull()
    await expect(response.json()).resolves.toEqual({
      user: { id: '01TESTUSER0000000000000000' },
      current_membership: null,
      memberships: [],
    })
  })

  it('leaves ordinary responses unchanged', async () => {
    const baseFetch = vi.fn(async () => {
      return new Response('{"ok":true}', {
        headers: {
          'Content-Length': '11',
          'Content-Type': 'application/json',
        },
        status: 200,
      })
    })

    const fetch = createAndroidCapacitorFetch(baseFetch as typeof globalThis.fetch)
    const response = await fetch('https://api.example.test/api/v1/health/ready')

    expect(response.headers.get('Content-Length')).toBe('11')
    await expect(response.text()).resolves.toBe('{"ok":true}')
  })

  it('does not text-decode binary private media with a zero length header', async () => {
    const bytes = new Uint8Array([0, 255, 1, 254, 2])
    const baseFetch = vi.fn(async () =>
      new Response(bytes, {
        headers: {
          'Content-Length': '0',
          'Content-Type': 'video/mp4',
        },
        status: 200,
      }),
    )
    const fetch = createAndroidCapacitorFetch(
      baseFetch as typeof globalThis.fetch,
    )

    const response = await fetch(
      'https://api.example.test/api/v1/media-assets/video/lesson-video',
    )

    expect(response.headers.get('Content-Length')).toBe('0')
    await expect(response.arrayBuffer()).resolves.toEqual(bytes.buffer)
  })
})
