import { Capacitor } from '@capacitor/core'
import {
  AndroidPkceAuthenticationAdapter,
  type NativeSecureCredentialStore,
  type SystemAuthorizationBrowser,
} from './AndroidPkceAuthenticationAdapter'
import {
  AuthenticationUnavailableError,
  type AndroidPkceAuthentication,
  type AuthenticationAdapter,
} from './AuthenticationAdapter'
import { createAndroidCapacitorFetch } from './androidCapacitorFetch'
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
  defaultAuthenticationRuntime.authentication

export function createDefaultAuthentication(
  options: DefaultAuthenticationOptions = {},
): AuthenticationAdapter {
  return createDefaultAuthenticationRuntime(options).authentication
}

export function createDefaultAuthenticationRuntime(
  options: DefaultAuthenticationOptions = {},
): DefaultAuthenticationRuntime {
  const isNativeAndroid =
    options.isNativeAndroid ??
    (Capacitor.isNativePlatform() && Capacitor.getPlatform() === 'android')

  if (isNativeAndroid) {
    const environment = resolveAndroidPkceEnvironment(options.env)

    if (environment === null) {
      // Misconfigured closed-beta builds must not silently switch the WebView
      // to cookie authentication or send requests to its localhost origin.
      const authentication = new UnavailableAndroidAuthenticationAdapter()

      return {
        apiBaseUrl: '/api/v1',
        apiFetch: authentication.authenticatedFetch,
        authentication,
        csrfProtection: 'none',
        privateMediaRequiresFetch: true,
      }
    }

    const nativeFetch = createAndroidCapacitorFetch(options.fetch)
    const authentication = new AndroidPkceAuthenticationAdapter({
      apiBaseUrl: environment.apiBaseUrl,
      authorizationEndpoint: environment.authorizationEndpoint,
      beforeLogout: () => browserOfflineLearningStore.prepareLogout(),
      clientId: environment.clientId,
      // Capacitor Android GET interceptor can advertise Content-Length: 0 with
      // a real JSON body; normalize before hey-api identity parsing.
      fetch: nativeFetch,
      redirectUri: environment.redirectUri,
      secureStore:
        options.secureStore ?? new KeystoreSecureCredentialStore(),
      systemBrowser:
        options.systemBrowser ?? new CapacitorSystemAuthorizationBrowser(),
      tokenEndpoint: environment.tokenEndpoint,
    })

    return {
      apiBaseUrl: environment.apiBaseUrl,
      apiFetch: authentication.authenticatedFetch,
      authentication,
      csrfProtection: 'none',
      privateMediaRequiresFetch: true,
    }
  }

  const fetcher = options.fetch ?? globalThis.fetch.bind(globalThis)
  const authentication = new WebSessionAuthenticationAdapter({
    baseUrl: '/api/v1',
    beforeLogout: () => browserOfflineLearningStore.prepareLogout(),
    fetch: fetcher,
  })

  return {
    apiBaseUrl: '/api/v1',
    apiFetch: fetcher,
    authentication,
    csrfProtection: 'sanctum-cookie',
    privateMediaRequiresFetch: false,
  }
}
