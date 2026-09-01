import { Capacitor } from '@capacitor/core'
import { AndroidPkceAuthenticationAdapter } from './AndroidPkceAuthenticationAdapter'
import type { AuthenticationAdapter } from './AuthenticationAdapter'
import { resolveAndroidPkceEnvironment } from './androidPkceEnvironment'
import { CapacitorSystemAuthorizationBrowser } from './CapacitorSystemAuthorizationBrowser'
import { KeystoreSecureCredentialStore } from './KeystoreSecureCredentialStore'
import { WebSessionAuthenticationAdapter } from './WebSessionAuthenticationAdapter'
import { browserOfflineLearningStore } from '../offline/BrowserOfflineLearningStore'

export type ApiCsrfProtection = 'none' | 'sanctum-cookie'

export interface DefaultAuthenticationRuntime {
  readonly apiBaseUrl: string
  readonly apiFetch: typeof fetch
  readonly authentication: AuthenticationAdapter
  readonly csrfProtection: ApiCsrfProtection
  readonly privateMediaRequiresFetch: boolean
}

export interface DefaultAuthenticationOptions {
  readonly env?: ImportMetaEnv | Record<string, string | undefined>
  readonly fetch?: typeof fetch
  readonly isNativeAndroid?: boolean
  readonly secureStore?: NativeSecureCredentialStore
  readonly systemBrowser?: SystemAuthorizationBrowser
}

class UnavailableAndroidAuthenticationAdapter
  implements AndroidPkceAuthentication
{
  readonly transport = 'passport-pkce' as const

  readonly authenticatedFetch: typeof fetch = async () => {
    throw new AuthenticationUnavailableError()
  }

  async beginAuthorization(): Promise<void> {
    throw new AuthenticationUnavailableError()
  }

  async signIn(): Promise<void> {
    throw new AuthenticationUnavailableError()
  }

  async authenticationMode(): Promise<never> {
    throw new AuthenticationUnavailableError()
  }

  async requestPasswordRecovery(): Promise<void> {
    throw new AuthenticationUnavailableError()
  }

  async enrollWithInvitation(): Promise<void> {
    throw new AuthenticationUnavailableError()
  }

  async federatedProviders(): Promise<never> {
    throw new AuthenticationUnavailableError()
  }

  async beginFederatedSignIn(): Promise<void> {
    throw new AuthenticationUnavailableError()
  }

  async federatedLinks(): Promise<never> {
    throw new AuthenticationUnavailableError()
  }

  async beginFederatedLink(): Promise<void> {
    throw new AuthenticationUnavailableError()
  }

  async unlinkFederated(): Promise<void> {
    throw new AuthenticationUnavailableError()
  }

  async completeAuthorization(): Promise<never> {
    throw new AuthenticationUnavailableError()
  }

  async current(): Promise<never> {
    throw new AuthenticationUnavailableError()
  }

  async refresh(): Promise<void> {
    throw new AuthenticationUnavailableError()
  }

  onAuthenticationInvalidated(): () => void {
    return () => undefined
  }

  async logout(): Promise<void> {
    await browserOfflineLearningStore.prepareLogout()
  }
}

export const defaultAuthenticationRuntime =
  createDefaultAuthenticationRuntime()

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
