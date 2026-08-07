const CROCKFORD = '0123456789ABCDEFGHJKMNPQRSTVWXYZ'

/**
 * Creates a random ULID for Android installation binding (ADR-0002).
 * Not a hardware or advertising identifier.
 */
export function createInstallationUlid(
  randomBytes: (size: number) => Uint8Array = defaultRandomBytes,
  nowMs: () => number = () => Date.now(),
): string {
  const time = Math.max(0, Math.floor(nowMs()))
  const timeChars: string[] = Array.from({ length: 10 })

  let remaining = time

  for (let index = 9; index >= 0; index -= 1) {
    timeChars[index] = CROCKFORD[remaining % 32]!
    remaining = Math.floor(remaining / 32)
  }

  const entropy = randomBytes(10)
  const randomChars: string[] = []
  let buffer = 0
  let bits = 0

  for (const byte of entropy) {
    buffer = (buffer << 8) | byte
    bits += 8

    while (bits >= 5) {
      bits -= 5
      randomChars.push(CROCKFORD[(buffer >> bits) & 31]!)
    }
  }

  if (bits > 0) {
    randomChars.push(CROCKFORD[(buffer << (5 - bits)) & 31]!)
  }

  return `${timeChars.join('')}${randomChars.slice(0, 16).join('')}`
}

function defaultRandomBytes(size: number): Uint8Array {
  const bytes = new Uint8Array(size)
  globalThis.crypto.getRandomValues(bytes)

  return bytes
}
