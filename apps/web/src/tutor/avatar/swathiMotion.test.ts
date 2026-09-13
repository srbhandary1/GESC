import { describe, expect, it } from 'vitest'
import { speakingMotion } from './swathiMotion'
import { visemeMouthShape } from './visemeMapping'

describe('Swathi speaking motion', () => {
  const rest = visemeMouthShape(0)
  const open = visemeMouthShape(2)

  it('opens the jaw and swings the teaching hand while speaking', () => {
    const a = speakingMotion('speaking', open, 0.12)
    const b = speakingMotion('speaking', open, 0.28)

    expect(a.jawOpen).toBeGreaterThan(0.35)
    expect(Math.abs(a.jawOpen - b.jawOpen)).toBeGreaterThan(0.08)
    expect(a.rightUpperX).toBeLessThan(-0.6)
    expect(Math.abs(a.rightUpperX - b.rightUpperX)).toBeGreaterThan(0.05)
  })

  it('keeps idle lips nearly closed while still moving the hands', () => {
    const a = speakingMotion('idle', rest, 0.4)
    const b = speakingMotion('idle', rest, 1.3)

    expect(a.jawOpen).toBeLessThan(0.12)
    expect(Math.abs(a.rightUpperX - b.rightUpperX)).toBeGreaterThan(0.02)
  })
})
