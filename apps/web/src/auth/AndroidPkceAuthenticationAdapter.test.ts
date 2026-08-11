import { describe, expect, it, vi } from 'vitest'
import {
  AndroidPkceAuthenticationAdapter,
  type NativeSecureCredentialStore,
  type SystemAuthorizationBrowser,
} from './AndroidPkceAuthenticationAdapter'
import { AuthenticationUnavailableError } from './AuthenticationAdapter'

class InMemoryReviewedSecureStore implements NativeSecureCredentialStore {
  readonly installationId = '01J00000000000000000000010'
  pending: { state: string; verifier: string } | null = null
  refreshToken: string | null = null
  clearCount = 0

  async getOrCreateInstallationId(): Promise<string> {
    return this.installationId
  }

  async readRefreshToken(): Promise<string | null> {
    return this.refreshToken
  }

  async writeRefreshToken(refreshToken: string): Promise<void> {
    this.refreshToken = refreshToken
  }

  async clearAuthentication(): Promise<void> {
    this.clearCount += 1
    this.pending = null
    this.refreshToken = null
  }

  async writePendingAuthorization(pending: {
    state: string
    verifier: string
  }): Promise<void> {
    this.pending = pending
  }

  async takePendingAuthorization(): Promise<{
    state: string
    verifier: string
  } | null> {
    const pending = this.pending
    this.pending = null

    return pending
  }
}

describe('AndroidPkceAuthenticationAdapter', () => {
  it('uses a system-browser S256 flow and persists only the refresh token', async () => {
    const secureStore = new InMemoryReviewedSecureStore()
    const openedUrls: string[] = []
    const systemBrowser: SystemAuthorizationBrowser = {
      open: vi.fn(async (url: string) => {
        openedUrls.push(url)
      }),
    }
    const tokenBodies: URLSearchParams[] = []
    const fetcher = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
      const request = new Request(input, init)

      if (request.url.endsWith('/oauth/token')) {
        tokenBodies.push(new URLSearchParams(await request.text()))

        return Response.json({
          access_token: 'memory-only-access-token',
          expires_in: 900,
          refresh_token: 'secure-refresh-token',
          token_type: 'Bearer',
        })
      }

      if (request.url.endsWith('/api/v1/me')) {
        expect(request.headers.get('Authorization')).toBe(
          'Bearer memory-only-access-token',
        )

        return Response.json({
          user: { id: '01J00000000000000000000011' },
          current_membership: null,
          memberships: [],
        })
      }

      throw new Error(`Unexpected request: ${request.url}`)
    })
    const adapter = createAdapter(secureStore, systemBrowser, fetcher)

    await adapter.beginAuthorization()

    const authorizationUrl = new URL(openedUrls[0]!)
    const pending = secureStore.pending

    expect(authorizationUrl.searchParams.get('code_challenge_method')).toBe(
      'S256',
    )
    expect(authorizationUrl.searchParams.get('code_challenge')).not.toBe(
      pending?.verifier,
    )
    expect(authorizationUrl.searchParams.get('client_secret')).toBeNull()
    expect(authorizationUrl.searchParams.get('installation_id')).toBeNull()
    expect(authorizationUrl.searchParams.get('scope')).toBe('mobile:api')

    const snapshot = await adapter.completeAuthorization(
      `com.globalenglish.app:/oauth/callback?code=authorization-code&state=${pending?.state}`,
    )

    expect(snapshot.status).toBe('authenticated')
    expect(tokenBodies).toHaveLength(1)
    expect(tokenBodies[0]!.get('grant_type')).toBe('authorization_code')
    expect(tokenBodies[0]!.get('code_verifier')).toBe(pending?.verifier)
    expect(tokenBodies[0]!.get('client_secret')).toBeNull()
    expect(secureStore.refreshToken).toBe('secure-refresh-token')
    expect(JSON.stringify(secureStore)).not.toContain(
      'memory-only-access-token',
    )
  })

  it('rejects Capacitor-style empty identity bodies instead of authenticating', async () => {
    const secureStore = new InMemoryReviewedSecureStore()
    const systemBrowser: SystemAuthorizationBrowser = {
      open: vi.fn(async () => undefined),
    }
    const fetcher = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
      const request = new Request(input, init)

      if (request.url.endsWith('/oauth/token')) {
        return Response.json({
          access_token: 'memory-only-access-token',
          expires_in: 900,
          refresh_token: 'secure-refresh-token',
          token_type: 'Bearer',
        })
      }

      if (request.url.endsWith('/api/v1/me')) {
        // hey-api + Capacitor Content-Length: 0 can surface as {}.
        return Response.json({})
      }

      throw new Error(`Unexpected request: ${request.url}`)
    })
    const adapter = createAdapter(secureStore, systemBrowser, fetcher)

    await adapter.beginAuthorization()
    const pending = secureStore.pending

    await expect(
      adapter.completeAuthorization(
        `com.globalenglish.app:/oauth/callback?code=authorization-code&state=${pending?.state}`,
      ),
    ).rejects.toBeInstanceOf(AuthenticationUnavailableError)
    expect(secureStore.refreshToken).toBeNull()
  })

  it('serializes refresh and clears credentials after a failed exchange', async () => {
    const secureStore = new InMemoryReviewedSecureStore()
    secureStore.refreshToken = 'one-time-refresh-token'
    let refreshCalls = 0
    const fetcher = vi.fn(async (_input: RequestInfo | URL, init?: RequestInit) => {
      const body = new URLSearchParams(String(init?.body))

      if (body.get('grant_type') === 'refresh_token') {
        refreshCalls += 1
        await Promise.resolve()

        return new Response(null, { status: 400 })
      }

      throw new Error('Unexpected request')
    })
    const adapter = createAdapter(
      secureStore,
      { open: vi.fn(async () => undefined) },
      fetcher,
    )

    const first = adapter.refresh()
    const second = adapter.refresh()

    await expect(first).rejects.toThrow('Authentication could not be completed.')
    await expect(second).rejects.toThrow(
      'Authentication could not be completed.',
    )
    expect(refreshCalls).toBe(1)
    expect(secureStore.refreshToken).toBeNull()
    expect(secureStore.clearCount).toBe(1)
  })

  it('restores a cold-start session from the native refresh credential', async () => {
    const secureStore = new InMemoryReviewedSecureStore()
    secureStore.refreshToken = 'persisted-refresh-token'
    const requests: Request[] = []
    const adapter = createAdapter(
      secureStore,
      { open: vi.fn(async () => undefined) },
      vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
        const request = new Request(input, init)
        requests.push(request)

        if (request.url.endsWith('/oauth/token')) {
          expect(new URLSearchParams(await request.text()).get('refresh_token'))
            .toBe('persisted-refresh-token')

          return Response.json({
            access_token: 'restored-memory-access-token',
            expires_in: 900,
            refresh_token: 'rotated-refresh-token',
            token_type: 'Bearer',
          })
        }

        if (request.url.endsWith('/api/v1/me')) {
          expect(request.headers.get('Authorization')).toBe(
            'Bearer restored-memory-access-token',
          )
          expect(request.credentials).toBe('omit')

          return Response.json({
            user: { id: '01J00000000000000000000011' },
            current_membership: null,
            memberships: [],
          })
        }

        throw new Error(`Unexpected request: ${request.url}`)
      }) as typeof fetch,
    )

    await expect(adapter.current()).resolves.toMatchObject({
      status: 'authenticated',
    })
    expect(
      requests.map((request) => new URL(request.url).pathname),
    ).toEqual(['/oauth/token', '/api/v1/me'])
    expect(secureStore.refreshToken).toBe('rotated-refresh-token')
  })

  it('preserves the rotating refresh credential after a transient token outage', async () => {
    const secureStore = new InMemoryReviewedSecureStore()
    secureStore.refreshToken = 'retryable-refresh-token'
    const adapter = createAdapter(
      secureStore,
      { open: vi.fn(async () => undefined) },
      vi.fn(async () => new Response(null, { status: 503 })) as typeof fetch,
    )

    await expect(adapter.current()).rejects.toBeInstanceOf(
      AuthenticationUnavailableError,
    )
    expect(secureStore.refreshToken).toBe('retryable-refresh-token')
    expect(secureStore.clearCount).toBe(0)
  })

  it('refreshes once and replays the same API request after a 401', async () => {
    const secureStore = new InMemoryReviewedSecureStore()
    secureStore.refreshToken = 'initial-refresh-token'
    const apiRequests: Request[] = []
    let refreshCount = 0
    const adapter = createAdapter(
      secureStore,
      { open: vi.fn(async () => undefined) },
      vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
        const request = new Request(input, init)

        if (request.url.endsWith('/oauth/token')) {
          refreshCount += 1

          return Response.json({
            access_token:
              refreshCount === 1 ? 'expired-access-token' : 'replacement-access-token',
            expires_in: 900,
            refresh_token:
              refreshCount === 1 ? 'first-rotated-refresh' : 'second-rotated-refresh',
            token_type: 'Bearer',
          })
        }

        if (request.url.endsWith('/api/v1/home')) {
          apiRequests.push(request)

          return request.headers.get('Authorization') ===
            'Bearer expired-access-token'
            ? new Response(null, { status: 401 })
            : Response.json({ ok: true })
        }

        throw new Error(`Unexpected request: ${request.url}`)
      }) as typeof fetch,
    )
    await adapter.refresh()
    const response = await adapter.authenticatedFetch('/api/v1/home', {
      body: JSON.stringify({ command: 'same-on-replay' }),
      credentials: 'include',
      headers: {
        Authorization: 'Bearer caller-supplied-token',
        'Content-Type': 'application/json',
      },
      method: 'POST',
    })

    expect(response.status).toBe(200)
    expect(refreshCount).toBe(2)
    expect(apiRequests).toHaveLength(2)
    expect(apiRequests.map((request) => request.credentials)).toEqual([
      'omit',
      'omit',
    ])
    expect(
      apiRequests.map((request) => request.headers.get('Authorization')),
    ).toEqual([
      'Bearer expired-access-token',
      'Bearer replacement-access-token',
    ])
    await expect(apiRequests[0]!.text()).resolves.toBe(
      '{"command":"same-on-replay"}',
    )
    await expect(apiRequests[1]!.text()).resolves.toBe(
      '{"command":"same-on-replay"}',
    )
    expect(secureStore.refreshToken).toBe('second-rotated-refresh')
  })

  it('coalesces concurrent 401 refreshes and clears credentials after a rejected replay', async () => {
    const secureStore = new InMemoryReviewedSecureStore()
    secureStore.refreshToken = 'initial-refresh-token'
    let refreshCount = 0
    let expiredRequestCount = 0
    let releaseExpiredRequests: () => void = () => undefined
    const bothExpiredRequests = new Promise<void>((resolve) => {
      releaseExpiredRequests = resolve
    })
    const adapter = createAdapter(
      secureStore,
      { open: vi.fn(async () => undefined) },
      vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
        const request = new Request(input, init)

        if (request.url.endsWith('/oauth/token')) {
          refreshCount += 1

          return Response.json({
            access_token:
              refreshCount === 1 ? 'expired-access-token' : 'replacement-access-token',
            expires_in: 900,
            refresh_token: `rotated-refresh-${refreshCount}`,
            token_type: 'Bearer',
          })
        }

        if (request.headers.get('Authorization') === 'Bearer expired-access-token') {
          expiredRequestCount += 1
          if (expiredRequestCount === 2) {
            releaseExpiredRequests()
          }
          await bothExpiredRequests

          return new Response(null, { status: 401 })
        }

        return new Response(null, { status: 401 })
      }) as typeof fetch,
    )
    const onInvalidated = vi.fn()
    adapter.onAuthenticationInvalidated(onInvalidated)

    await adapter.refresh()
    const responses = await Promise.all([
      adapter.authenticatedFetch('/api/v1/home'),
      adapter.authenticatedFetch('/api/v1/progress'),
    ])

    expect(responses.map((response) => response.status)).toEqual([401, 401])
    expect(refreshCount).toBe(2)
    expect(secureStore.refreshToken).toBeNull()
    expect(secureStore.clearCount).toBeGreaterThanOrEqual(1)
    expect(onInvalidated).toHaveBeenCalledTimes(1)
  })

  it('does not repopulate secure credentials when refresh finishes after logout', async () => {
    const secureStore = new InMemoryReviewedSecureStore()
    secureStore.refreshToken = 'initial-refresh-token'
    let refreshCount = 0
    let releaseLateRefresh: () => void = () => undefined
    let markLateRefreshStarted: () => void = () => undefined
    const lateRefreshRelease = new Promise<void>((resolve) => {
      releaseLateRefresh = resolve
    })
    const lateRefreshStarted = new Promise<void>((resolve) => {
      markLateRefreshStarted = resolve
    })
    const adapter = createAdapter(
      secureStore,
      { open: vi.fn(async () => undefined) },
      vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
        const request = new Request(input, init)

        if (request.url.endsWith('/oauth/token')) {
          refreshCount += 1

          if (refreshCount === 2) {
            markLateRefreshStarted()
            await lateRefreshRelease
          }

          return Response.json({
            access_token:
              refreshCount === 1 ? 'expired-access-token' : 'late-access-token',
            expires_in: 900,
            refresh_token: `rotated-refresh-${refreshCount}`,
            token_type: 'Bearer',
          })
        }

        if (request.url.endsWith('/api/v1/auth/mobile/revoke')) {
          expect(request.credentials).toBe('omit')
          expect(request.headers.get('Authorization')).toBe(
            'Bearer expired-access-token',
          )

          return new Response(null, { status: 204 })
        }

        return new Response(null, { status: 401 })
      }) as typeof fetch,
    )

    await adapter.refresh()
    const request = adapter.authenticatedFetch('/api/v1/home')
    await lateRefreshStarted
    await adapter.logout()
    releaseLateRefresh()

    await expect(request).resolves.toMatchObject({ status: 401 })
    expect(secureStore.refreshToken).toBeNull()
  })

  it('refuses to send the bearer outside the configured API boundary', async () => {
    const secureStore = new InMemoryReviewedSecureStore()
    const fetcher = vi.fn(async () => Response.json({ ok: true }))
    const adapter = createAdapter(
      secureStore,
      { open: vi.fn(async () => undefined) },
      fetcher as typeof fetch,
    )

    await expect(
      adapter.authenticatedFetch('https://attacker.example/api/v1/home'),
    ).rejects.toBeInstanceOf(AuthenticationUnavailableError)
    expect(fetcher).not.toHaveBeenCalled()
  })

  it('rejects token responses outside the generated strict contract', async () => {
    const secureStore = new InMemoryReviewedSecureStore()
    const adapter = createAdapter(
      secureStore,
      { open: vi.fn(async () => undefined) },
      vi.fn(async () =>
        Response.json({
          access_token: 'memory-only-access-token',
          expires_in: 900,
          refresh_token: 'secure-refresh-token',
          token_type: 'Bearer',
          unexpected_provider_field: 'must-not-be-accepted',
        }),
      ) as typeof fetch,
    )

    await adapter.beginAuthorization()
    const state = secureStore.pending?.state

    await expect(
      adapter.completeAuthorization(
        `com.globalenglish.app:/oauth/callback?code=authorization-code&state=${state}`,
      ),
    ).rejects.toThrow('Authentication could not be completed.')
    expect(secureStore.refreshToken).toBeNull()
    expect(secureStore.clearCount).toBe(1)
  })

  it('rejects callbacks outside the configured redirect target before exchange', async () => {
    const secureStore = new InMemoryReviewedSecureStore()
    const fetcher = vi.fn(async () => {
      throw new Error('A rejected callback must not reach the token endpoint')
    })
    const adapter = createAdapter(
      secureStore,
      { open: vi.fn(async () => undefined) },
      fetcher,
    )
    const rejectedTargets = [
      'com.globalenglish.attacker:/oauth/callback',
      'com.globalenglish.app:/oauth/other',
      'com.globalenglish.app://attacker/oauth/callback',
      'https://globalenglish.app/oauth/callback',
    ]

    for (const target of rejectedTargets) {
      await adapter.beginAuthorization()
      const state = secureStore.pending?.state

      await expect(
        adapter.completeAuthorization(
          `${target}?code=authorization-code&state=${state}`,
        ),
      ).rejects.toThrow('Authentication could not be completed.')
      // Rejected targets must leave the in-flight PKCE verifier intact.
      expect(secureStore.pending?.state).toBe(state)
    }

    expect(fetcher).not.toHaveBeenCalled()
    expect(secureStore.refreshToken).toBeNull()
    expect(secureStore.clearCount).toBe(0)
  })

  it('keeps pending PKCE after a raced wrong-state callback', async () => {
    const secureStore = new InMemoryReviewedSecureStore()
    const tokenBodies: URLSearchParams[] = []
    const adapter = createAdapter(
      secureStore,
      { open: vi.fn(async () => undefined) },
      vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
        const request = new Request(input, init)
        if (request.url.endsWith('/oauth/token')) {
          tokenBodies.push(new URLSearchParams(await request.text()))
          return Response.json({
            access_token: 'memory-only-access-token',
            expires_in: 900,
            refresh_token: 'secure-refresh-token',
            token_type: 'Bearer',
          })
        }
        if (request.url.endsWith('/api/v1/me')) {
          return Response.json({
            user: { id: '01J00000000000000000000011' },
            current_membership: null,
            memberships: [],
          })
        }
        throw new Error(`Unexpected request: ${request.url}`)
      }) as typeof fetch,
    )

    await adapter.beginAuthorization()
    const pending = secureStore.pending
    expect(pending).not.toBeNull()

    await expect(
      adapter.completeAuthorization(
        'com.globalenglish.app:/oauth/callback?code=stale-code&state=wrong-state',
      ),
    ).rejects.toThrow('Authentication could not be completed.')
    expect(secureStore.pending).toEqual(pending)
    expect(secureStore.clearCount).toBe(0)

    const snapshot = await adapter.completeAuthorization(
      `com.globalenglish.app:/oauth/callback?code=authorization-code&state=${pending?.state}`,
    )
    expect(snapshot.status).toBe('authenticated')
    expect(tokenBodies).toHaveLength(1)
  })

  it('serializes matching callback races and exchanges an authorization code once', async () => {
    const secureStore = new InMemoryReviewedSecureStore()
    let exchangeCount = 0
    const adapter = createAdapter(
      secureStore,
      { open: vi.fn(async () => undefined) },
      vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
        const request = new Request(input, init)

        if (request.url.endsWith('/oauth/token')) {
          exchangeCount += 1
          await Promise.resolve()

          return Response.json({
            access_token: 'memory-only-access-token',
            expires_in: 900,
            refresh_token: 'secure-refresh-token',
            token_type: 'Bearer',
          })
        }

        if (request.url.endsWith('/api/v1/me')) {
          return Response.json({
            user: { id: '01J00000000000000000000011' },
            current_membership: null,
            memberships: [],
          })
        }

        throw new Error(`Unexpected request: ${request.url}`)
      }) as typeof fetch,
    )

    await adapter.beginAuthorization()
    const callback =
      `com.globalenglish.app:/oauth/callback?code=authorization-code&state=${secureStore.pending?.state}`
    const outcomes = await Promise.allSettled([
      adapter.completeAuthorization(callback),
      adapter.completeAuthorization(callback),
    ])

    expect(outcomes.filter((outcome) => outcome.status === 'fulfilled'))
      .toHaveLength(1)
    expect(outcomes.filter((outcome) => outcome.status === 'rejected'))
      .toHaveLength(1)
    expect(exchangeCount).toBe(1)
  })

  it('refreshes after an app restart so logout can revoke the server family', async () => {
    const secureStore = new InMemoryReviewedSecureStore()
    secureStore.refreshToken = 'persisted-refresh-token'
    const requests: Request[] = []
    const events: string[] = []
    const fetcher = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
      const request = new Request(input, init)
      requests.push(request)
      events.push(new URL(request.url).pathname)

      if (request.url.endsWith('/oauth/token')) {
        return Response.json({
          access_token: 'replacement-access-token',
          expires_in: 900,
          refresh_token: 'rotated-refresh-token',
          token_type: 'Bearer',
        })
      }

      if (request.url.endsWith('/api/v1/auth/mobile/revoke')) {
        expect(request.headers.get('Authorization')).toBe(
          'Bearer replacement-access-token',
        )
        await expect(request.json()).resolves.toEqual({
          installation_id: secureStore.installationId,
        })

        return new Response(null, { status: 204 })
      }

      throw new Error(`Unexpected request: ${request.url}`)
    })
    const adapter = createAdapter(
      secureStore,
      { open: vi.fn(async () => undefined) },
      fetcher,
      async () => {
        events.push('purge')
      },
    )

    await adapter.logout()

    expect(requests.map((request) => new URL(request.url).pathname)).toEqual([
      '/oauth/token',
      '/api/v1/auth/mobile/revoke',
    ])
    expect(events).toEqual([
      'purge',
      '/oauth/token',
      '/api/v1/auth/mobile/revoke',
    ])
    expect(secureStore.refreshToken).toBeNull()
  })
})

function createAdapter(
  secureStore: NativeSecureCredentialStore,
  systemBrowser: SystemAuthorizationBrowser,
  fetcher: typeof fetch,
  beforeLogout?: () => Promise<void>,
): AndroidPkceAuthenticationAdapter {
  return new AndroidPkceAuthenticationAdapter({
    apiBaseUrl: 'https://api.global-english.test/api/v1',
    authorizationEndpoint:
      'https://api.global-english.test/oauth/authorize',
    clientId: 'public-android-client',
    beforeLogout,
    fetch: fetcher,
    redirectUri: 'com.globalenglish.app:/oauth/callback',
    secureStore,
    systemBrowser,
    tokenEndpoint: 'https://api.global-english.test/oauth/token',
  })
}
