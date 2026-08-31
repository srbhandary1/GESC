import { describe, expect, it } from 'vitest'
import {
  coachDisplayName,
  normalizeCoachDisplayName,
} from './coachPreferences'

describe('coachPreferences', () => {
  it('uses governed defaults by coach gender', () => {
    expect(
      coachDisplayName(
        { gender: 'female', displayName: null },
        'Swathi',
        'Arjun',
      ),
    ).toBe('Swathi')
    expect(
      coachDisplayName(
        { gender: 'male', displayName: null },
        'Swathi',
        'Arjun',
      ),
    ).toBe('Arjun')
  })

  it('normalizes and bounds a device-local display name', () => {
    expect(normalizeCoachDisplayName('  Meera   Teacher  ')).toBe(
      'Meera Teacher',
    )
    expect(normalizeCoachDisplayName(' '.repeat(4))).toBeNull()
    expect(normalizeCoachDisplayName(null)).toBeNull()
    expect(
      normalizeCoachDisplayName('A very long coach display name 1234567890'),
    ).toBe('A very long coach display name 1')
  })

  it('prefers the device-local display name', () => {
    expect(
      coachDisplayName(
        { gender: 'male', displayName: 'Ravi' },
        'Swathi',
        'Arjun',
      ),
    ).toBe('Ravi')
  })
})
