import {
  useMemo,
  useState,
  type ReactNode,
} from 'react'
import {
  DEFAULT_COACH,
  normalizeCoachDisplayName,
  type CoachGender,
  type CoachPreference,
} from './coachPreferences'
import {
  CoachPreferenceContext,
  type CoachPreferenceContextValue,
} from './coachPreferenceContextValue'

export function CoachPreferenceProvider({
  children,
}: {
  readonly children: ReactNode
}) {
  const [preference, setPreference] =
    useState<CoachPreference>(DEFAULT_COACH)

  const value = useMemo<CoachPreferenceContextValue>(
    () => ({
      preference,
      selectCoach: (gender: CoachGender) => {
        setPreference((current) => ({ ...current, gender }))
      },
      renameCoach: (displayName: string) => {
        setPreference((current) => ({
          ...current,
          displayName: normalizeCoachDisplayName(displayName),
        }))
      },
      resetCoachName: () => {
        setPreference((current) => ({ ...current, displayName: null }))
      },
    }),
    [preference],
  )

  return (
    <CoachPreferenceContext.Provider value={value}>
      {children}
    </CoachPreferenceContext.Provider>
  )
}
