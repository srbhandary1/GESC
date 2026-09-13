import type { AvatarVisemeTimeline } from '@global-english/contracts'

const DIGRAPH_VISEMES: Readonly<Record<string, number>> = {
  th: 17,
  sh: 16,
  ch: 16,
  ow: 8,
  aw: 9,
  oy: 10,
  ay: 11,
  ee: 6,
  oo: 7,
  ar: 2,
  or: 8,
  er: 5,
}

export function estimateVisemeTimeline(
  text: string,
  durationMs: number,
): AvatarVisemeTimeline | null {
  const segments = segmentsForText(text)
  if (segments.length === 0 || durationMs <= 0) {
    return null
  }

  const totalWeight = segments.reduce((sum, segment) => sum + segment.weight, 0)
  if (totalWeight <= 0) {
    return null
  }

  const frames: AvatarVisemeTimeline['frames'] = []
  let cursor = 0

  segments.forEach((segment, index) => {
    if (index === 0) {
      frames.push({ offset_ms: 0, viseme_id: segment.visemeId })
    } else {
      frames.push({
        offset_ms: Math.min(cursor, Math.max(0, durationMs - 1)),
        viseme_id: segment.visemeId,
      })
    }
    cursor += Math.round((segment.weight / totalWeight) * durationMs)
  })

  const lastOffset = frames.at(-1)?.offset_ms ?? 0
  if (lastOffset < Math.max(0, durationMs - 1)) {
    frames.push({ offset_ms: Math.max(0, durationMs - 1), viseme_id: 0 })
  }

  return {
    source: 'text-duration-estimator-v1',
    duration_ms: durationMs,
    frames,
  }
}

export async function base64AudioDurationMs(
  base64: string,
  mimeType = 'audio/wav',
): Promise<number | null> {
  const bytes = decodeBase64ToBytes(base64)
  const fromHeader = wavDurationMsFromBytes(bytes)
  if (fromHeader !== null) {
    return fromHeader
  }

  const copy = new ArrayBuffer(bytes.byteLength)
  new Uint8Array(copy).set(bytes)
  const blob = new Blob([copy], { type: mimeType })
  const url = URL.createObjectURL(blob)
  const audio = new Audio()

  try {
    return await new Promise<number | null>((resolve) => {
      let settled = false
      const finish = (value: number | null) => {
        if (settled) {
          return
        }
        settled = true
        resolve(value)
      }

      audio.preload = 'metadata'
      audio.onloadedmetadata = () => {
        finish(
          Number.isFinite(audio.duration)
            ? Math.round(audio.duration * 1000)
            : null,
        )
      }
      audio.onerror = () => finish(null)
      window.setTimeout(() => finish(null), 80)
      audio.src = url
    })
  } finally {
    URL.revokeObjectURL(url)
  }
}

function segmentsForText(text: string): readonly { visemeId: number; weight: number }[] {
  const letters = text.toLowerCase().replace(/[^a-z]+/g, '')
  if (letters === '') {
    return []
  }

  const segments: { visemeId: number; weight: number }[] = []
  let index = 0

  while (index < letters.length) {
    const pair = letters.slice(index, index + 2)
    const digraph = DIGRAPH_VISEMES[pair]
    if (pair.length === 2 && digraph !== undefined) {
      segments.push({ visemeId: digraph, weight: weightForViseme(digraph) })
      index += 2
      continue
    }

    const visemeId = visemeForChar(letters[index] ?? '')
    segments.push({ visemeId, weight: weightForViseme(visemeId) })
    index += 1
  }

  return segments
}

function visemeForChar(char: string): number {
  switch (char) {
    case 'a':
      return 2
    case 'e':
      return 4
    case 'i':
    case 'y':
      return 6
    case 'o':
      return 8
    case 'u':
      return 7
    case 'b':
    case 'p':
    case 'm':
      return 21
    case 'f':
    case 'v':
      return 18
    case 'w':
      return 7
    case 'h':
      return 12
    case 'r':
      return 13
    case 'l':
      return 14
    case 's':
    case 'z':
      return 15
    case 'c':
      return 20
    case 'j':
      return 16
    case 'd':
    case 't':
    case 'n':
      return 19
    case 'k':
    case 'g':
    case 'q':
    case 'x':
      return 20
    default:
      return 0
  }
}

function weightForViseme(visemeId: number): number {
  if (visemeId === 0) {
    return 0.5
  }
  return [2, 4, 6, 7, 8, 9, 10, 11, 5].includes(visemeId) ? 2 : 1
}

export function wavDurationMsFromBytes(bytes: Uint8Array): number | null {
  if (bytes.length < 44 || readAscii(bytes, 0, 4) !== 'RIFF') {
    return null
  }

  let offset = 12
  let sampleRate = 0
  let bitsPerSample = 16
  let dataBytes = 0
  let channels = 1

  while (offset + 8 <= bytes.length) {
    const chunkId = readAscii(bytes, offset, 4)
    const chunkSize = readUint32(bytes, offset + 4)
    const chunkStart = offset + 8

    if (chunkId === 'fmt ') {
      channels = readUint16(bytes, chunkStart + 2)
      sampleRate = readUint32(bytes, chunkStart + 4)
      bitsPerSample = readUint16(bytes, chunkStart + 14)
    } else if (chunkId === 'data') {
      dataBytes = chunkSize
    }

    offset = chunkStart + chunkSize + (chunkSize % 2)
  }

  if (sampleRate <= 0 || dataBytes <= 0) {
    return null
  }

  const bytesPerSample = Math.max(1, bitsPerSample / 8)
  const samples = dataBytes / (bytesPerSample * Math.max(1, channels))
  return Math.max(1, Math.round((samples / sampleRate) * 1000))
}

function readAscii(bytes: Uint8Array, offset: number, length: number): string {
  return String.fromCharCode(...bytes.slice(offset, offset + length))
}

function readUint16(bytes: Uint8Array, offset: number): number {
  return bytes[offset]! | (bytes[offset + 1]! << 8)
}

function readUint32(bytes: Uint8Array, offset: number): number {
  return (
    bytes[offset]! |
    (bytes[offset + 1]! << 8) |
    (bytes[offset + 2]! << 16) |
    (bytes[offset + 3]! << 24)
  )
}

function decodeBase64ToBytes(base64: string): Uint8Array {
  const binary = atob(base64)
  const bytes = new Uint8Array(binary.length)
  for (let index = 0; index < binary.length; index += 1) {
    bytes[index] = binary.charCodeAt(index)
  }
  return bytes
}
