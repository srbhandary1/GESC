import { describe, expect, it, vi } from 'vitest'
import {
  AndroidPkceAuthenticationAdapter,
  type NativeSecureCredentialStore,
} from './AndroidPkceAuthenticationAdapter'
import {
  createDefaultAuthentication,
  createDefaultAuthenticationRuntime,
} from './defaultAuthentication'
import { WebSessionAuthenticationAdapter } from './WebSessionAuthenticationAdapter'

class RuntimeSecureStore implements NativeSecureCredentialStore {
  refreshToken: string | null = 'persisted-refresh-token'

  async getOrCreateInstallationId(): Promise<string> {
    return '01J00000000000000000000010'
  }

  async readRefreshToken(): Promise<string | null> {
    return this.refreshToken
  }

  async writeRefreshToken(refreshToken: string): Promise<void> {
    this.refreshToken = refreshToken
  }

  async clearAuthentication(): Promise<void> {
    this.refreshToken = null
  }

  async writePendingAuthorization(): Promise<void> {}

  async takePendingAuthorization(): Promise<null> {
    return null
  }
}

describe('createDefaultAuthentication', () => {
  it('wires Android PKCE when the Capacitor Android runtime is active', () => {
    const authentication = createDefaultAuthentication({
      isNativeAndroid: true,
      env: {
        VITE_MOBILE_API_ORIGIN: 'https://api.global-english.test',
        VITE_MOBILE_OAUTH_CLIENT_ID: 'public-android-client',
      },
    })

    expect(authentication).toBeInstanceOf(AndroidPkceAuthenticationAdapter)
    expect(authentication.transport).toBe('passport-pkce')
  })

  it('keeps Sanctum cookie sessions for ordinary web/PWA', () => {
    const authentication = createDefaultAuthentication({
      isNativeAndroid: false,
    })

    expect(authentication).toBeInstanceOf(WebSessionAuthenticationAdapter)
    expect(authentication.transport).toBe('sanctum-cookie')
  })

  it('fails a misconfigured native build closed without cookie requests', async () => {
    const fetcher = vi.fn(async () => Response.json({}))
    const runtime = createDefaultAuthenticationRuntime({
      env: {},
      fetch: fetcher as typeof fetch,
      isNativeAndroid: true,
    })

    expect(runtime.authentication.transport).toBe('passport-pkce')
    expect(runtime.csrfProtection).toBe('none')
    await expect(runtime.authentication.current()).rejects.toThrow(
      'Authentication could not be completed.',
    )
    await expect(runtime.apiFetch('/api/v1/me')).rejects.toThrow(
      'Authentication could not be completed.',
    )
    expect(fetcher).not.toHaveBeenCalled()
  })

  it('shares one Android bearer fetch and absolute API base with product clients', async () => {
    const secureStore = new RuntimeSecureStore()
    const requests: Request[] = []
    const runtime = createDefaultAuthenticationRuntime({
      env: {
        VITE_MOBILE_API_ORIGIN: 'https://api.global-english.test',
        VITE_MOBILE_OAUTH_CLIENT_ID: 'public-android-client',
      },
      fetch: vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
        const request = new Request(input, init)
        requests.push(request)

        if (request.url.endsWith('/oauth/token')) {
          return Response.json({
            access_token: 'memory-only-access-token',
            expires_in: 900,
            refresh_token: 'rotated-refresh-token',
            token_type: 'Bearer',
          })
        }

        return Response.json({ ok: true })
      }) as typeof fetch,
      isNativeAndroid: true,
      secureStore,
      systemBrowser: { open: async () => undefined },
    })

    expect(runtime.apiBaseUrl).toBe(
      'https://api.global-english.test/api/v1',
    )
    expect(runtime.csrfProtection).toBe('none')
    expect(runtime.privateMediaRequiresFetch).toBe(true)

    await runtime.apiFetch('/api/v1/home')

    expect(requests).toHaveLength(2)
    expect(requests[1]?.headers.get('Authorization')).toBe(
      'Bearer memory-only-access-token',
    )
  })
})
