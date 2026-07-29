import type {
  LivenessResponse,
  ProblemDetails,
} from '@global-english/contracts'
import { LivenessResponseSchema } from '@global-english/contracts'
import { describe, expect, it, vi } from 'vitest'
import {
  fetchLiveness,
  LivenessUnavailableError,
} from './health'

const baseUrl = 'https://global-english.test/api/v1'

describe('health API client', () => {
  it('exposes generated runtime schemas through the package boundary', () => {
    expect(LivenessResponseSchema.properties.status.const).toBe('ok')
  })

  it('uses the generated contract to fetch liveness', async () => {
    const payload: LivenessResponse = {
      status: 'ok',
      service: 'global-english-api',
      checked_at: '2026-07-29T12:00:00Z',
      correlation_id: '019fab10-a105-7daa-a9ad-4a09e5b07db7',
    }
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(JSON.stringify(payload), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    )

    await expect(
      fetchLiveness({ baseUrl, fetch: fetchMock }),
    ).resolves.toEqual(payload)

    expect(fetchMock).toHaveBeenCalledOnce()
    const request = fetchMock.mock.calls[0]?.[0]
    expect(request).toBeInstanceOf(Request)
    expect((request as Request).url).toBe(`${baseUrl}/health/live`)
  })

  it('replaces server details with a generic client error', async () => {
    const problem: ProblemDetails = {
      type: 'https://global-english.test/problems/unavailable',
      title: 'Unavailable',
      status: 503,
      detail: 'Sensitive upstream failure detail',
      instance: 'https://global-english.test/api/v1/health/live',
      message_key: 'health.unavailable',
      correlation_id: '019fab10-a105-7daa-a9ad-4a09e5b07db7',
    }
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(JSON.stringify(problem), {
        status: 503,
        headers: { 'Content-Type': 'application/problem+json' },
      }),
    )

    const request = fetchLiveness({ baseUrl, fetch: fetchMock })

    await expect(request).rejects.toBeInstanceOf(LivenessUnavailableError)
    await expect(request).rejects.not.toThrow(problem.detail)
  })
})
