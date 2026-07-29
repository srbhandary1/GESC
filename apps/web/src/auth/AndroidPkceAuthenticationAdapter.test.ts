import { describe, expect, it, vi } from 'vitest'
import {
  AndroidPkceAuthenticationAdapter,
  type NativeSecureCredentialStore,
  type SystemAuthorizationBrowser,
} from './AndroidPkceAuthenticationAdapter'

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
    }

    expect(fetcher).not.toHaveBeenCalled()
    expect(secureStore.refreshToken).toBeNull()
    expect(secureStore.clearCount).toBe(rejectedTargets.length)
  })

  it('refreshes after an app restart so logout can revoke the server family', async () => {
    const secureStore = new InMemoryReviewedSecureStore()
    secureStore.refreshToken = 'persisted-refresh-token'
    const requests: Request[] = []
    const fetcher = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
      const request = new Request(input, init)
      requests.push(request)

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
    )

    await adapter.logout()

    expect(requests.map((request) => new URL(request.url).pathname)).toEqual([
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
): AndroidPkceAuthenticationAdapter {
  return new AndroidPkceAuthenticationAdapter({
    apiBaseUrl: 'https://api.global-english.test/api/v1',
    authorizationEndpoint:
      'https://api.global-english.test/oauth/authorize',
    clientId: 'public-android-client',
    fetch: fetcher,
    redirectUri: 'com.globalenglish.app:/oauth/callback',
    secureStore,
    systemBrowser,
    tokenEndpoint: 'https://api.global-english.test/oauth/token',
  })
}
