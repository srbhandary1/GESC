export interface AndroidPkceEnvironment {
  apiBaseUrl: string
  authorizationEndpoint: string
  clientId: string
  redirectUri: string
  tokenEndpoint: string
}

const DEFAULT_REDIRECT_URI = 'com.globalenglish.app:/oauth/callback'
const DEFAULT_CLIENT_ID = 'public-android-client'

/**
 * Closed-beta Android OAuth endpoints are build-time configuration.
 * Relative `/api` paths are invalid inside the Capacitor WebView origin.
 */
export function resolveAndroidPkceEnvironment(
  env: ImportMetaEnv | Record<string, string | undefined> = import.meta.env,
): AndroidPkceEnvironment | null {
  const origin = trimTrailingSlash(
    read(env, 'VITE_MOBILE_API_ORIGIN') ?? '',
  )

  if (origin === '') {
    return null
  }

  return {
    apiBaseUrl: `${origin}/api/v1`,
    authorizationEndpoint:
      read(env, 'VITE_MOBILE_OAUTH_AUTHORIZE_URL') ??
      `${origin}/oauth/authorize`,
    clientId: read(env, 'VITE_MOBILE_OAUTH_CLIENT_ID') ?? DEFAULT_CLIENT_ID,
    redirectUri:
      read(env, 'VITE_MOBILE_OAUTH_REDIRECT_URI') ?? DEFAULT_REDIRECT_URI,
    tokenEndpoint:
      read(env, 'VITE_MOBILE_OAUTH_TOKEN_URL') ?? `${origin}/oauth/token`,
  }
}

function read(
  env: ImportMetaEnv | Record<string, string | undefined>,
  key: string,
): string | undefined {
  const value = env[key]

  return typeof value === 'string' && value.trim() !== ''
    ? value.trim()
    : undefined
}

function trimTrailingSlash(value: string): string {
  return value.replace(/\/+$/u, '')
}
