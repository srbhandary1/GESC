export const AvatarGesture = {
  ENCOURAGING_NOD: 'ENCOURAGING_NOD',
  WARM_SMILE: 'WARM_SMILE',
  ATTENTIVE_LEAN: 'ATTENTIVE_LEAN',
} as const

export type AvatarGesture = (typeof AvatarGesture)[keyof typeof AvatarGesture]

export const AVATAR_GESTURES: readonly AvatarGesture[] = [
  AvatarGesture.ENCOURAGING_NOD,
  AvatarGesture.WARM_SMILE,
  AvatarGesture.ATTENTIVE_LEAN,
]

export type VisemeTimestamp = {
  readonly mouth_shape: string
  readonly timestamp_ms: number
}

export type AnimationPayload = {
  readonly audio_url?: string
  readonly viseme_timestamps: readonly VisemeTimestamp[]
  readonly avatar_gesture_trigger: AvatarGesture | null
}

export function isAvatarGesture(value: unknown): value is AvatarGesture {
  return (
    value === AvatarGesture.ENCOURAGING_NOD ||
    value === AvatarGesture.WARM_SMILE ||
    value === AvatarGesture.ATTENTIVE_LEAN
  )
}

export function normalizeVisemeTimestamps(
  value: unknown,
): readonly VisemeTimestamp[] {
  if (!Array.isArray(value)) {
    return []
  }

  const frames: VisemeTimestamp[] = []

  for (const item of value) {
    if (item === null || typeof item !== 'object') {
      continue
    }

    const record = item as Record<string, unknown>
    const mouthShape = record.mouth_shape
    const timestamp = record.timestamp_ms

    if (typeof mouthShape !== 'string') {
      continue
    }

    const shape = mouthShape.trim()
    if (shape === '') {
      continue
    }

    if (typeof timestamp !== 'number' || !Number.isFinite(timestamp) || timestamp < 0) {
      continue
    }

    frames.push({
      mouth_shape: shape,
      timestamp_ms: Math.round(timestamp),
    })
  }

  frames.sort((left, right) => left.timestamp_ms - right.timestamp_ms)

  return frames
}

export function parseAnimationPayload(value: unknown): AnimationPayload | null {
  if (value === null || typeof value !== 'object') {
    return null
  }

  const record = value as Record<string, unknown>
  if (
    record.audio_url !== undefined &&
    typeof record.audio_url !== 'string'
  ) {
    return null
  }

  const audioUrl =
    typeof record.audio_url === 'string' ? record.audio_url.trim() : undefined
  if (audioUrl === '') {
    return null
  }

  const visemeTimestamps = normalizeVisemeTimestamps(record.viseme_timestamps)
  if (visemeTimestamps.length === 0) {
    return null
  }

  const gesture = record.avatar_gesture_trigger
  if (gesture !== null && gesture !== undefined && !isAvatarGesture(gesture)) {
    return null
  }

  return {
    ...(audioUrl === undefined ? {} : { audio_url: audioUrl }),
    viseme_timestamps: visemeTimestamps,
    avatar_gesture_trigger: gesture === undefined ? null : gesture,
  }
}

export function visemeMouthShapeAt(
  frames: readonly VisemeTimestamp[],
  timestampMs: number,
): string {
  if (frames.length === 0 || !Number.isFinite(timestampMs) || timestampMs < 0) {
    return 'rest'
  }

  let low = 0
  let high = frames.length - 1
  let index = -1

  while (low <= high) {
    const mid = (low + high) >> 1
    const frame = frames[mid]
    if (frame === undefined) {
      break
    }

    if (frame.timestamp_ms <= timestampMs) {
      index = mid
      low = mid + 1
    } else {
      high = mid - 1
    }
  }

  return frames[index]?.mouth_shape ?? 'rest'
}
