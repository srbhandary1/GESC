import { describe, expect, it, vi } from 'vitest'
import { WebLearnerJourneyApi } from './WebLearnerJourneyApi'

describe('WebLearnerJourneyApi', () => {
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
