import { Capacitor } from '@capacitor/core'
import { AndroidPkceAuthenticationAdapter } from './AndroidPkceAuthenticationAdapter'
import type { AuthenticationAdapter } from './AuthenticationAdapter'
import { resolveAndroidPkceEnvironment } from './androidPkceEnvironment'
import { CapacitorSystemAuthorizationBrowser } from './CapacitorSystemAuthorizationBrowser'
import { KeystoreSecureCredentialStore } from './KeystoreSecureCredentialStore'
import { WebSessionAuthenticationAdapter } from './WebSessionAuthenticationAdapter'
import { browserOfflineLearningStore } from '../offline/BrowserOfflineLearningStore'

export const defaultAuthentication: AuthenticationAdapter =
  createDefaultAuthentication()

export function createDefaultAuthentication(
  options: {
    isNativeAndroid?: boolean
    env?: ImportMetaEnv | Record<string, string | undefined>
  } = {},
): AuthenticationAdapter {
  const isNativeAndroid =
    options.isNativeAndroid ??
    (Capacitor.isNativePlatform() && Capacitor.getPlatform() === 'android')

  if (isNativeAndroid) {
    const environment = resolveAndroidPkceEnvironment(options.env)

    if (environment === null) {
      // Misconfigured closed-beta builds must fail closed to anonymous web
      // session semantics rather than silently using cookie auth in WebView.
      return new WebSessionAuthenticationAdapter({
        baseUrl: '/api/v1',
        beforeLogout: () => browserOfflineLearningStore.prepareLogout(),
      })
    }

    return new AndroidPkceAuthenticationAdapter({
      apiBaseUrl: environment.apiBaseUrl,
      authorizationEndpoint: environment.authorizationEndpoint,
      beforeLogout: () => browserOfflineLearningStore.prepareLogout(),
      clientId: environment.clientId,
      redirectUri: environment.redirectUri,
      secureStore: new KeystoreSecureCredentialStore(),
      systemBrowser: new CapacitorSystemAuthorizationBrowser(),
      tokenEndpoint: environment.tokenEndpoint,
    })
  }

  return new WebSessionAuthenticationAdapter({
    baseUrl: '/api/v1',
    beforeLogout: () => browserOfflineLearningStore.prepareLogout(),
  })
}
