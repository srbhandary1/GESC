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
})
