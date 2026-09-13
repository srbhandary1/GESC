import { describe, expect, it } from 'vitest'
import { interpolateMouthShape, resolveVisemeAtTime, visemeMouthShape } from './visemeMapping'
import { SWATHI_AVATAR_MODE } from './SwathiAvatarScene'

describe('visemeMapping', () => {
  it('maps viseme ids into bounded mouth shapes', () => {
    expect(visemeMouthShape(0).jawOpen).toBeLessThan(visemeMouthShape(2).jawOpen)
    expect(visemeMouthShape(99).jawOpen).toBe(visemeMouthShape(21).jawOpen)
  })

  it('resolves the active viseme at playback time', () => {
    const timeline = {
      source: 'text-duration-estimator-v1' as const,
      duration_ms: 500,
      frames: [
        { offset_ms: 0, viseme_id: 6 },
        { offset_ms: 120, viseme_id: 19 },
        { offset_ms: 420, viseme_id: 0 },
      ],
    }

    expect(resolveVisemeAtTime(timeline, 0)).toBe(6)
    expect(resolveVisemeAtTime(timeline, 200)).toBe(19)
    expect(resolveVisemeAtTime(timeline, 480)).toBe(0)
  })

  it('interpolates between mouth shapes', () => {
    const blended = interpolateMouthShape(
      visemeMouthShape(0),
      visemeMouthShape(6),
      0.5,
    )

    expect(blended.jawOpen).toBeGreaterThan(visemeMouthShape(0).jawOpen)
    expect(blended.jawOpen).toBeLessThan(visemeMouthShape(6).jawOpen)
  })

  it('keeps the tutor as an original project-owned sculpt', () => {
    expect(SWATHI_AVATAR_MODE).toBe('original-sculpt-v4')
  })
})
