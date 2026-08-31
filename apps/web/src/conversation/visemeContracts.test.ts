import {
  AvatarGesture,
  normalizeVisemeTimestamps,
  parseAnimationPayload,
  visemeMouthShapeAt,
} from '@global-english/contracts'
import { describe, expect, it } from 'vitest'

describe('viseme contracts', () => {
  it('parses a complete animation payload', () => {
    const payload = parseAnimationPayload({
      audio_url: 'blob:reply',
      viseme_timestamps: [
        { mouth_shape: 'open', timestamp_ms: 40 },
        { mouth_shape: 'rest', timestamp_ms: 0 },
      ],
      avatar_gesture_trigger: AvatarGesture.ENCOURAGING_NOD,
    })

    expect(payload).toEqual({
      audio_url: 'blob:reply',
      viseme_timestamps: [
        { mouth_shape: 'rest', timestamp_ms: 0 },
        { mouth_shape: 'open', timestamp_ms: 40 },
      ],
      avatar_gesture_trigger: AvatarGesture.ENCOURAGING_NOD,
    })
  })

  it('rejects empty, corrupt, or incomplete payloads', () => {
    expect(parseAnimationPayload(null)).toBeNull()
    expect(parseAnimationPayload({})).toBeNull()
    expect(
      parseAnimationPayload({
        audio_url: 'blob:reply',
        viseme_timestamps: [],
      }),
    ).toBeNull()
    expect(
      parseAnimationPayload({
        audio_url: 'blob:reply',
        viseme_timestamps: [{ mouth_shape: 'open', timestamp_ms: 10 }],
        avatar_gesture_trigger: 'WAVE',
      }),
    ).toBeNull()
    expect(normalizeVisemeTimestamps('not-an-array')).toEqual([])
    expect(
      normalizeVisemeTimestamps([
        { mouth_shape: '', timestamp_ms: 1 },
        { mouth_shape: 'open', timestamp_ms: -4 },
        { mouth_shape: 'smile', timestamp_ms: 12 },
      ]),
    ).toEqual([{ mouth_shape: 'smile', timestamp_ms: 12 }])
  })

  it('selects the latest viseme at or before the playback clock', () => {
    const frames = [
      { mouth_shape: 'rest', timestamp_ms: 0 },
      { mouth_shape: 'open', timestamp_ms: 80 },
      { mouth_shape: 'smile', timestamp_ms: 160 },
    ]

    expect(visemeMouthShapeAt(frames, -1)).toBe('rest')
    expect(visemeMouthShapeAt(frames, 0)).toBe('rest')
    expect(visemeMouthShapeAt(frames, 79)).toBe('rest')
    expect(visemeMouthShapeAt(frames, 80)).toBe('open')
    expect(visemeMouthShapeAt(frames, 159)).toBe('open')
    expect(visemeMouthShapeAt(frames, 160)).toBe('smile')
    expect(visemeMouthShapeAt([], 40)).toBe('rest')
  })
})
