/**
 * Capacitor's Android fetch interceptor drops FormData file parts, so the
 * Passport adapter must send an explicit multipart body Laravel can parse.
 */
export interface SerializedMultipartFormData {
  readonly body: Uint8Array
  readonly contentType: string
}

const SAFE_NAME = /^[A-Za-z0-9._-]{1,80}$/u

export async function serializeMultipartFormData(
  formData: FormData,
): Promise<SerializedMultipartFormData> {
  const boundary = `----geAndroidForm${randomBoundaryToken()}`
  const parts: Uint8Array[] = []
  const encoder = new TextEncoder()

  for (const [name, value] of formData.entries()) {
    if (!SAFE_NAME.test(name)) {
      throw new Error('The form field name is not allowed.')
    }

    parts.push(encoder.encode(`--${boundary}\r\n`))

    if (typeof value === 'string') {
      parts.push(
        encoder.encode(
          `Content-Disposition: form-data; name="${name}"\r\n\r\n${value}\r\n`,
        ),
      )
      continue
    }

    const filename = safeFilename(value.name)
    const mimeType = safeMimeType(value.type)
    parts.push(
      encoder.encode(
        `Content-Disposition: form-data; name="${name}"; filename="${filename}"\r\nContent-Type: ${mimeType}\r\n\r\n`,
      ),
    )
    parts.push(new Uint8Array(await value.arrayBuffer()))
    parts.push(encoder.encode('\r\n'))
  }

  parts.push(encoder.encode(`--${boundary}--\r\n`))

  return {
    body: concatBytes(parts),
    contentType: `multipart/form-data; boundary=${boundary}`,
  }
}

function concatBytes(parts: readonly Uint8Array[]): Uint8Array {
  const total = parts.reduce((sum, part) => sum + part.byteLength, 0)
  const bytes = new Uint8Array(total)
  let offset = 0

  for (const part of parts) {
    bytes.set(part, offset)
    offset += part.byteLength
  }

  return bytes
}

function safeFilename(name: string): string {
  return SAFE_NAME.test(name) ? name : 'upload.bin'
}

function safeMimeType(type: string): string {
  return type === 'audio/wav' ||
    type === 'audio/webm' ||
    type === 'application/octet-stream'
    ? type
    : 'application/octet-stream'
}

function randomBoundaryToken(): string {
  const webCrypto = globalThis.crypto

  if (typeof webCrypto?.getRandomValues === 'function') {
    const bytes = new Uint8Array(12)
    webCrypto.getRandomValues(bytes)

    return Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join(
      '',
    )
  }

  return Date.now().toString(16)
}
