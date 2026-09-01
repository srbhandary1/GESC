import { describe, expect, it, vi } from 'vitest'
import { WebSessionAuthenticationAdapter } from './WebSessionAuthenticationAdapter'

const identityResponse = {
  user: {
    id: '01J00000000000000000000001',
  },
  current_membership: {
    id: '01J00000000000000000000002',
    organization: {
      id: '01J00000000000000000000003',
      name: 'Global English Direct',
      kind: 'platform_direct',
    },
    membership_type: 'learner',
    status: 'active',
    role_keys: ['learner'],
  },
  memberships: [],
} as const

describe('WebSessionAuthenticationAdapter', () => {
  it('submits username/password only over CSRF-protected cookie transport', async () => {
    const requests: Request[] = []
    const fetcher = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
      const request = new Request(input, init)
      requests.push(request)

      return request.url.endsWith('/sanctum/csrf-cookie')
        ? new Response(null, { status: 204 })
        : Response.json({ session: { id: '01J00000000000000000000001' } })
    })
    const adapter = new WebSessionAuthenticationAdapter({
      baseUrl: 'https://app.global-english.test/api/v1',
      fetch: fetcher as typeof fetch,
      readCookie: () => 'XSRF-TOKEN=csrf-token',
    })

    await adapter.signIn('learner.one', 'Correct horse battery 2026!')

    const request = requests.at(-1)
    expect(request?.url).toBe(
      'https://app.global-english.test/api/v1/auth/password/sign-ins',
    )
    expect(request?.credentials).toBe('include')
    expect(request?.headers.get('X-XSRF-TOKEN')).toBe('csrf-token')
    expect(request?.headers.get('Authorization')).toBeNull()
    await expect(request?.json()).resolves.toEqual({
      username: 'learner.one',
      password: 'Correct horse battery 2026!',
    })
  })

  it('starts Google sign-in on the first-party authorize path', async () => {
    const assign = vi.fn()
    vi.stubGlobal('location', { assign })
    const adapter = new WebSessionAuthenticationAdapter({
      baseUrl: 'https://app.global-english.test/api/v1',
      fetch: vi.fn() as typeof fetch,
      readCookie: () => '',
    })

    await adapter.beginFederatedSignIn('google')

    expect(assign).toHaveBeenCalledWith('/oauth/social/google/authorize')
    vi.unstubAllGlobals()
  })

  it('uses CSRF-protected cookie requests without browser credential storage', async () => {
    const requests: Request[] = []
    const fetcher = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
      const request = new Request(input, init)
      requests.push(request)

      if (request.url.endsWith('/sanctum/csrf-cookie')) {
        return new Response(null, { status: 204 })
      }

      if (request.url.endsWith('/auth/otp/requests')) {
        return Response.json(
          {
            challenge_id: '01J00000000000000000000004',
            expires_at: '2026-07-30T10:05:00Z',
          },
          { status: 202 },
        )
      }

      if (request.url.endsWith('/auth/otp/verifications')) {
        return Response.json({
          user: identityResponse.user,
          session: {
            id: '01J00000000000000000000005',
            current: true,
            created_at: '2026-07-30T10:00:00Z',
            last_activity_at: '2026-07-30T10:00:00Z',
            expires_at: '2026-07-30T12:00:00Z',
            privileged: false,
          },
        })
      }

      throw new Error(`Unexpected request: ${request.url}`)
    })
    const localStorageWrite = vi.spyOn(Storage.prototype, 'setItem')
    const indexedDbWrite =
      globalThis.indexedDB === undefined
        ? null
        : vi.spyOn(globalThis.indexedDB, 'open')
    const adapter = new WebSessionAuthenticationAdapter({
      baseUrl: 'https://app.global-english.test/api/v1',
      fetch: fetcher as typeof fetch,
      readCookie: () => 'XSRF-TOKEN=csrf%20token',
    })

    const challenge = await adapter.requestOtp('+919876543210')
    await adapter.verifyOtp(
      challenge.challenge_id,
      '+919876543210',
      '123456',
      'GEI-WEB-INVITE-1234',
    )

    expect(challenge.challenge_id).toBe('01J00000000000000000000004')
    expect(
      requests.filter((request) =>
        request.url.endsWith('/sanctum/csrf-cookie'),
      ),
    ).toHaveLength(2)
    expect(
      requests
        .filter((request) => request.method === 'POST')
        .every(
          (request) =>
            request.credentials === 'include' &&
            request.headers.get('X-XSRF-TOKEN') === 'csrf token' &&
            request.headers.get('Authorization') === null,
        ),
    ).toBe(true)
    expect(localStorageWrite).not.toHaveBeenCalled()
    expect(indexedDbWrite?.mock.calls ?? []).toHaveLength(0)
    const verificationRequest = requests.find((request) =>
      request.url.endsWith('/auth/otp/verifications'),
    )
    await expect(verificationRequest?.json()).resolves.toEqual({
      challenge_id: '01J00000000000000000000004',
      mobile_number: '+919876543210',
      code: '123456',
      institution_invite_code: 'GEI-WEB-INVITE-1234',
    })
  })

  it('maps only an explicit unauthenticated response to anonymous', async () => {
    const anonymousAdapter = new WebSessionAuthenticationAdapter({
      baseUrl: 'https://app.global-english.test/api/v1',
      fetch: vi.fn(async () =>
        Response.json(
          {
            type: 'about:blank',
            title: 'Unauthenticated',
            status: 401,
            detail: 'Authentication is required.',
            instance: '/api/v1/me',
            message_key: 'auth.required',
            correlation_id: '3a8f71f5-aade-4a6e-8b40-48f8912fe63f',
          },
          { status: 401 },
        ),
      ) as typeof fetch,
    })

    await expect(anonymousAdapter.current()).resolves.toEqual({
      status: 'anonymous',
    })

    const authenticatedAdapter = new WebSessionAuthenticationAdapter({
      baseUrl: 'https://app.global-english.test/api/v1',
      fetch: vi.fn(async () => Response.json(identityResponse)) as typeof fetch,
    })

    await expect(authenticatedAdapter.current()).resolves.toEqual({
      status: 'authenticated',
      identity: identityResponse,
    })
  })

  it('purges protected offline keys before requesting server logout', async () => {
    const events: string[] = []
    const adapter = new WebSessionAuthenticationAdapter({
      baseUrl: 'https://app.global-english.test/api/v1',
      beforeLogout: vi.fn(async () => {
        events.push('purge')
      }),
      fetch: vi.fn(async (input: RequestInfo | URL) => {
        const url = new URL(new Request(input).url)
        events.push(url.pathname)

        return url.pathname === '/sanctum/csrf-cookie'
          ? new Response(null, { status: 204 })
          : Response.json({ logged_out: true })
      }) as typeof fetch,
      readCookie: () => 'XSRF-TOKEN=csrf-token',
    })

    await adapter.logout()

    expect(events).toEqual([
      'purge',
      '/sanctum/csrf-cookie',
      '/api/v1/auth/logout',
    ])
  })

  it('does not revoke the session when protected pending work blocks logout', async () => {
    const fetcher = vi.fn()
    const adapter = new WebSessionAuthenticationAdapter({
      beforeLogout: async () => {
        throw new Error('Pending offline work.')
      },
      fetch: fetcher as typeof fetch,
    })

    await expect(adapter.logout()).rejects.toThrow(
      'Pending offline work.',
    )
    expect(fetcher).not.toHaveBeenCalled()
  })
})
