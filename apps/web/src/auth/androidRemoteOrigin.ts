/**
 * Optional Capacitor live-load origin for the demo APK.
 * Staff static-IP review may use HTTP on port 8080 only.
 */
export function resolveAndroidRemoteServer(
  origin: string | undefined,
): { url: string; cleartext: boolean } | undefined {
  if (origin === undefined) {
    return undefined
  }

  const trimmed = origin.trim()
  if (trimmed === '') {
    return undefined
  }

  try {
    const url = new URL(trimmed)
    const ipv4Literal =
      /^(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(?:\.(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}$/u
    const httpPort = url.port === '' ? '80' : url.port
    const allowedHttp =
      httpPort === '8080' && ipv4Literal.test(url.hostname)

    if (
      url.username !== '' ||
      url.password !== '' ||
      (url.pathname !== '' && url.pathname !== '/') ||
      url.search !== '' ||
      url.hash !== '' ||
      (url.protocol !== 'https:' &&
        !(url.protocol === 'http:' && allowedHttp))
    ) {
      return undefined
    }

    return {
      url: url.origin,
      cleartext: url.protocol === 'http:',
    }
  } catch {
    return undefined
  }
}
