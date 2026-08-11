import { describe, expect, it } from 'vitest'
import { resolveAndroidPkceEnvironment } from './androidPkceEnvironment'

describe('resolveAndroidPkceEnvironment', () => {
  it('requires an absolute mobile API origin for Capacitor packaging', () => {
    expect(resolveAndroidPkceEnvironment({})).toBeNull()
  })

  it('derives OAuth endpoints and the production redirect URI', () => {
    expect(
      resolveAndroidPkceEnvironment({
        VITE_MOBILE_API_ORIGIN: 'https://api.example.test/',
      }),
    ).toEqual({
      apiBaseUrl: 'https://api.example.test/api/v1',
      authorizationEndpoint: 'https://api.example.test/oauth/authorize',
      clientId: 'public-android-client',
      redirectUri: 'com.globalenglish.app:/oauth/callback',
      tokenEndpoint: 'https://api.example.test/oauth/token',
    })
  })

  it('fails closed for non-origin, insecure remote, or credential-diverting configuration', () => {
    for (const env of [
      { VITE_MOBILE_API_ORIGIN: '/api' },
      { VITE_MOBILE_API_ORIGIN: 'http://api.example.test' },
      { VITE_MOBILE_API_ORIGIN: 'https://api.example.test/prefix' },
      {
        VITE_MOBILE_API_ORIGIN: 'https://api.example.test',
        VITE_MOBILE_OAUTH_TOKEN_URL: 'https://attacker.example/oauth/token',
      },
      {
        VITE_MOBILE_API_ORIGIN: 'https://api.example.test',
        VITE_MOBILE_OAUTH_REDIRECT_URI: 'com.globalenglish.demo:/oauth/callback',
      },
    ]) {
      expect(resolveAndroidPkceEnvironment(env)).toBeNull()
    }
  })

  it('allows cleartext only for the documented local Android hosts', () => {
    expect(
      resolveAndroidPkceEnvironment({
        VITE_MOBILE_API_ORIGIN: 'http://10.0.2.2:8000',
      })?.apiBaseUrl,
    ).toBe('http://10.0.2.2:8000/api/v1')
  })
})
