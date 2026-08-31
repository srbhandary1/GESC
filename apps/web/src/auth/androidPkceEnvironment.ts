export interface AndroidPkceEnvironment {
  apiBaseUrl: string
  authorizationEndpoint: string
  clientId: string
  redirectUri: string
  tokenEndpoint: string
}

const DEFAULT_REDIRECT_URI = 'com.globalenglish.app:/oauth/callback'
const OAUTH_CLIENT_ID =
  /^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iu

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

  const clientId = normalizeOAuthClientId(
    read(env, 'VITE_MOBILE_OAUTH_CLIENT_ID'),
  )

  if (
    authorizationEndpoint === null ||
    tokenEndpoint === null ||
    redirectUri !== DEFAULT_REDIRECT_URI ||
    clientId === null
  ) {
    return null
  }

  return {
    apiBaseUrl: `${origin}/api/v1`,
    authorizationEndpoint,
    clientId,
    redirectUri,
    tokenEndpoint,
  }
}

function normalizeOAuthClientId(value: string | undefined): string | null {
  if (value === undefined) {
    return null
  }

  return OAUTH_CLIENT_ID.test(value) ? value : null
}

const LOCAL_HTTP_HOSTS = new Set(['10.0.2.2', '127.0.0.1', 'localhost'])
const IPV4_HOST =
  /^(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(?:\.(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}$/

function isAllowedMobileApiOrigin(url: URL): boolean {
  if (url.protocol === 'https:') {
    return true
  }

  if (url.protocol !== 'http:') {
    return false
  }

  if (LOCAL_HTTP_HOSTS.has(url.hostname)) {
    return true
  }

  // D-0248 / D-0257: invite-only static-IP HTTP on :8080. Hostnames stay
  // HTTPS-only so a mis-set http://api.example cannot ship in an APK.
  const port = url.port === '' ? '80' : url.port
  return port === '8080' && IPV4_HOST.test(url.hostname)
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

    if (
      !isAllowedMobileApiOrigin(url) ||
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
