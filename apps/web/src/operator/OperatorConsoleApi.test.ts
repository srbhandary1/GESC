import { describe, expect, it, vi } from 'vitest'
import {
  OperatorConsoleUnavailableError,
  WebOperatorConsoleApi,
} from './OperatorConsoleApi'

describe('web operator console API', () => {
  it('uses cookie credentials and encodes search and cursor parameters', async () => {
    const requests: Request[] = []
    const fetcher = vi.fn(
      async (input: RequestInfo | URL, init?: RequestInit) => {
        requests.push(new Request(input, init))

        return Response.json({
          generated_at: '2026-08-09T10:00:00+00:00',
          items: [],
          next_cursor: null,
        })
      },
    )
    const api = new WebOperatorConsoleApi({
      baseUrl: 'https://app.global-english.test/api/v1',
      fetch: fetcher as typeof fetch,
      readCookie: () => 'XSRF-TOKEN=csrf%20token',
    })

    await api.search('review / draft', 'encrypted+/=')

    expect(requests[0]?.url).toBe(
      'https://app.global-english.test/api/v1/operator/search?limit=20&q=review+%2F+draft&cursor=encrypted%2B%2F%3D',
    )
    expect(requests[0]?.credentials).toBe('include')
    expect(requests[0]?.method).toBe('GET')
    expect(requests[0]?.headers.get('X-XSRF-TOKEN')).toBe('csrf token')
  })

  it('keeps hidden denials content-free at the client boundary', async () => {
    const fetcher = vi.fn(async () =>
      Response.json(
        { detail: 'sensitive authorization explanation' },
        { status: 404 },
      ),
    )
    const api = new WebOperatorConsoleApi({
      fetch: fetcher as typeof fetch,
    })

    await expect(api.getOverview()).rejects.toEqual(
      new OperatorConsoleUnavailableError(),
    )
    await expect(api.getOverview()).rejects.not.toThrow(
      'sensitive authorization explanation',
    )
  })
})
