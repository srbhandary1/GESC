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

  it('encodes Speech Economics filters and hides commercial 404s', async () => {
    const requests: Request[] = []
    const fetcher = vi.fn(
      async (input: RequestInfo | URL, init?: RequestInit) => {
        const request = new Request(input, init)
        requests.push(request)

        if (request.url.includes('/commercial')) {
          return Response.json({ detail: 'hidden' }, { status: 404 })
        }

        return Response.json({
          empty: true,
          generated_at: '2026-09-09T10:00:00+00:00',
          organization_id: '01J00000000000000000000003',
          partial: false,
          presentation: 'allocated_usage',
          rows: [],
          stale_rate: false,
          suppressed_row_count: 0,
          totals: {
            allocated_inr_microunits: 0,
            attempt_count: 0,
            billable_unit_quantity: 0,
            cancelled_count: 0,
            dispatch_count: 0,
            failure_count: 0,
            fallback_count: 0,
            partial_count: 0,
            success_count: 0,
          },
          unreconciled: false,
          visible_row_count: 0,
        })
      },
    )
    const api = new WebOperatorConsoleApi({
      baseUrl: 'https://app.global-english.test/api/v1',
      fetch: fetcher as typeof fetch,
    })

    await api.getSpeechEconomics({
      from: '2026-09-01',
      provider_stage: 'tts',
    })
    await expect(api.getSpeechEconomicsCommercial()).resolves.toBeNull()

    expect(requests[0]?.url).toBe(
      'https://app.global-english.test/api/v1/operator/speech-economics?from=2026-09-01&provider_stage=tts',
    )
    expect(requests[1]?.url).toContain('/operator/speech-economics/commercial')
  })
})
