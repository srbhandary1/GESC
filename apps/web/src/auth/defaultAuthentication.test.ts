import { describe, expect, it } from 'vitest'
import { AndroidPkceAuthenticationAdapter } from './AndroidPkceAuthenticationAdapter'
import { createDefaultAuthentication } from './defaultAuthentication'
import { WebSessionAuthenticationAdapter } from './WebSessionAuthenticationAdapter'

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
})
