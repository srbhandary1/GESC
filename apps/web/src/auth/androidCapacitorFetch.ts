/**
 * Capacitor's Android HTTP interceptor (GET via
 * `/_capacitor_http_interceptor_`) often reports `Content-Length: 0` while
 * still returning a non-empty body. The generated hey-api client treats that
 * header as an empty JSON object, so `/api/v1/me` becomes `{}` and the
 * authenticated shell crashes on `identity.user`.
 *
 * Strip the bogus length so callers read the real body.
 */
export function createAndroidCapacitorFetch(
  baseFetch: typeof fetch = globalThis.fetch.bind(globalThis),
): typeof fetch {
  return async (input, init) => {
    const response = await baseFetch(input, init)
    const contentType =
      response.headers.get('Content-Type')?.toLowerCase() ?? ''

    if (
      response.status === 204 ||
      response.headers.get('Content-Length') !== '0' ||
      (!contentType.includes('application/json') &&
        !contentType.includes('+json'))
    ) {
      return response
    }

    const text = await response.text()
    const headers = new Headers(response.headers)
    headers.delete('Content-Length')

    return new Response(text, {
      headers,
      status: response.status,
      statusText: response.statusText,
    })
  }
}
