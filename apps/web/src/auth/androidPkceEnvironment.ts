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
  const origin = normalizeApiOrigin(
    read(env, 'VITE_MOBILE_API_ORIGIN'),
  )

  if (origin === null) {
    return null
  }

  const authorizationEndpoint = normalizeOAuthEndpoint(
    read(env, 'VITE_MOBILE_OAUTH_AUTHORIZE_URL') ??
      `${origin}/oauth/authorize`,
    origin,
  )
  const tokenEndpoint = normalizeOAuthEndpoint(
    read(env, 'VITE_MOBILE_OAUTH_TOKEN_URL') ?? `${origin}/oauth/token`,
    origin,
  )
  const redirectUri =
    read(env, 'VITE_MOBILE_OAUTH_REDIRECT_URI') ?? DEFAULT_REDIRECT_URI

  if (
    authorizationEndpoint === null ||
    tokenEndpoint === null ||
    redirectUri !== DEFAULT_REDIRECT_URI
  ) {
    return null
  }

  return {
    apiBaseUrl: `${origin}/api/v1`,
    authorizationEndpoint,
    clientId: read(env, 'VITE_MOBILE_OAUTH_CLIENT_ID') ?? DEFAULT_CLIENT_ID,
    redirectUri,
    tokenEndpoint,
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

function normalizeApiOrigin(value: string | undefined): string | null {
  if (value === undefined) {
    return null
  }

  try {
    const url = new URL(value)
    const localHttpHosts = new Set(['10.0.2.2', '127.0.0.1', 'localhost'])
    // Staff LAN closed-beta may publish HTTP on port 8080. Other remote HTTP
    // origins stay fail-closed.
    const ipv4Literal =
      /^(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(?:\.(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}$/u
    const httpPort = url.port === '' ? '80' : url.port
    const allowedHttp =
      localHttpHosts.has(url.hostname) ||
      (httpPort === '8080' && ipv4Literal.test(url.hostname))

    if (
      (url.protocol !== 'https:' &&
        !(url.protocol === 'http:' && allowedHttp)) ||
      url.username !== '' ||
      url.password !== '' ||
      (url.pathname !== '' && url.pathname !== '/') ||
      url.search !== '' ||
      url.hash !== ''
    ) {
      return null
    }

    return url.origin
  } catch {
    return null
  }
}

function normalizeOAuthEndpoint(
  value: string,
  expectedOrigin: string,
): string | null {
  try {
    const url = new URL(value)

    if (
      url.origin !== expectedOrigin ||
      url.username !== '' ||
      url.password !== '' ||
      url.search !== '' ||
      url.hash !== ''
    ) {
      return null
    }

    return url.toString()
  } catch {
    return null
  }
}
