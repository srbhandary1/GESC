import { createContext, useContext } from 'react'
import {
  DEFAULT_COACH,
  type CoachGender,
  type CoachPreference,
} from './coachPreferences'

export interface CoachPreferenceContextValue {
  readonly preference: CoachPreference
  readonly selectCoach: (gender: CoachGender) => void
  readonly renameCoach: (displayName: string) => void
  readonly resetCoachName: () => void
}

export const CoachPreferenceContext =
  createContext<CoachPreferenceContextValue>({
    preference: DEFAULT_COACH,
    selectCoach: () => undefined,
    renameCoach: () => undefined,
    resetCoachName: () => undefined,
  })

export function useCoachPreference(): CoachPreferenceContextValue {
  return useContext(CoachPreferenceContext)
}
