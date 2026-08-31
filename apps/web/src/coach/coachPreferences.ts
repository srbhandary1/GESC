export type CoachGender = 'female' | 'male'

export interface CoachPreference {
  readonly gender: CoachGender
  readonly displayName: string | null
}

export const DEFAULT_COACH: CoachPreference = {
  gender: 'female',
  displayName: null,
}

const MAXIMUM_COACH_NAME_CHARACTERS = 32

export function normalizeCoachDisplayName(value: unknown): string | null {
  if (typeof value !== 'string') {
    return null
  }

  const normalized = value.replace(/\s+/gu, ' ').trim()
  if (normalized === '') {
    return null
  }

  return Array.from(normalized)
    .slice(0, MAXIMUM_COACH_NAME_CHARACTERS)
    .join('')
}

export function coachDisplayName(
  preference: CoachPreference,
  femaleDefault: string,
  maleDefault: string,
): string {
  return (
    preference.displayName ??
    (preference.gender === 'female' ? femaleDefault : maleDefault)
  )
}
