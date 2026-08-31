import { describe, expect, it } from 'vitest'
import { resolveAndroidPkceEnvironment } from './androidPkceEnvironment'

const TEST_CLIENT_ID = '019fdd3f-4bba-720b-9e4e-a771ecccdd7f'

describe('resolveAndroidPkceEnvironment', () => {
  it('requires an absolute mobile API origin for Capacitor packaging', () => {
    expect(resolveAndroidPkceEnvironment({})).toBeNull()
  })

  it('derives OAuth endpoints and the production redirect URI', () => {
    expect(
      resolveAndroidPkceEnvironment({
        VITE_MOBILE_API_ORIGIN: 'https://api.example.test/',
        VITE_MOBILE_OAUTH_CLIENT_ID: TEST_CLIENT_ID,
      }),
    ).toEqual({
      apiBaseUrl: 'https://api.example.test/api/v1',
      authorizationEndpoint: 'https://api.example.test/oauth/authorize',
      clientId: TEST_CLIENT_ID,
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
        VITE_MOBILE_OAUTH_CLIENT_ID: TEST_CLIENT_ID,
        VITE_MOBILE_OAUTH_TOKEN_URL: 'https://attacker.example/oauth/token',
      },
      {
        VITE_MOBILE_API_ORIGIN: 'https://api.example.test',
        VITE_MOBILE_OAUTH_CLIENT_ID: TEST_CLIENT_ID,
        VITE_MOBILE_OAUTH_REDIRECT_URI: 'com.globalenglish.demo:/oauth/callback',
      },
      {
        VITE_MOBILE_API_ORIGIN: 'https://api.example.test',
        VITE_MOBILE_OAUTH_CLIENT_ID: 'public-android-client',
      },
    ]) {
      expect(resolveAndroidPkceEnvironment(env)).toBeNull()
    }
  })

  it('allows cleartext only for the documented local Android hosts', () => {
    expect(
      resolveAndroidPkceEnvironment({
        VITE_MOBILE_API_ORIGIN: 'http://10.0.2.2:8000',
        VITE_MOBILE_OAUTH_CLIENT_ID: TEST_CLIENT_ID,
      })?.apiBaseUrl,
    ).toBe('http://10.0.2.2:8000/api/v1')
  })

  it('allows invite-only static-IP HTTP on port 8080', () => {
    expect(
      resolveAndroidPkceEnvironment({
        VITE_MOBILE_API_ORIGIN: 'http://117.254.108.143:8080',
        VITE_MOBILE_OAUTH_CLIENT_ID: TEST_CLIENT_ID,
      })?.apiBaseUrl,
    ).toBe('http://117.254.108.143:8080/api/v1')
    expect(
      resolveAndroidPkceEnvironment({
        VITE_MOBILE_API_ORIGIN: 'http://192.168.1.4:8080',
        VITE_MOBILE_OAUTH_CLIENT_ID: TEST_CLIENT_ID,
      })?.apiBaseUrl,
    ).toBe('http://192.168.1.4:8080/api/v1')
  })

  it('rejects cleartext hostnames and IPv4 origins that are not :8080', () => {
    expect(
      resolveAndroidPkceEnvironment({
        VITE_MOBILE_API_ORIGIN: 'http://soft-launch.example:8080',
      }),
    ).toBeNull()
    expect(
      resolveAndroidPkceEnvironment({
        VITE_MOBILE_API_ORIGIN: 'http://117.254.108.143',
      }),
    ).toBeNull()
  })
})
