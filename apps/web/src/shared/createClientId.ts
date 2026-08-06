/**
 * Idempotency / capture IDs must work outside secure contexts too.
 * `crypto.randomUUID()` is missing on plain HTTP hosts such as a public
 * static IP (only localhost/HTTPS count as secure contexts in Chromium).
 */
export function createClientId(): string {
  const webCrypto = globalThis.crypto

  if (typeof webCrypto?.randomUUID === 'function') {
    return webCrypto.randomUUID()
  }

  if (typeof webCrypto?.getRandomValues === 'function') {
    const bytes = new Uint8Array(16)
    webCrypto.getRandomValues(bytes)
    bytes[6] = (bytes[6]! & 0x0f) | 0x40
    bytes[8] = (bytes[8]! & 0x3f) | 0x80

    const hex = Array.from(bytes, (byte) =>
      byte.toString(16).padStart(2, '0'),
    ).join('')

    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`
  }

  return `fallback-${Date.now().toString(16)}-${Math.random().toString(16).slice(2, 10)}`
}
