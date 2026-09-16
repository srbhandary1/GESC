import type { LearnerGoal } from './LearnerJourneyApi'

export const FIRST_USE_STEPS = [
  'welcome',
  'adult',
  'preferences',
  'goal',
  'placement',
  'audio-output',
  'consent',
  'microphone',
  'practice',
  'acknowledgement',
] as const

export type FirstUseStep = (typeof FIRST_USE_STEPS)[number]
export type PlacementChoice = 'beginning' | 'deferred'

export interface FirstUseResumeRecord {
  readonly version: 1
  readonly step: FirstUseStep
  readonly adultConfirmed: boolean
  readonly goal: LearnerGoal | null
  readonly placement: PlacementChoice | null
  readonly onboardingSaved: boolean
}

export const FIRST_USE_RESUME_STORAGE_KEY =
  'global-english:first-use-onboarding:v1'

const goals: readonly LearnerGoal[] = [
  'everyday-conversation',
  'workplace-confidence',
  'travel-confidence',
]

export function hasFirstUseResume(): boolean {
  return readFirstUseResume() !== null
}

export function readFirstUseResume(): FirstUseResumeRecord | null {
  try {
    const raw = globalThis.localStorage?.getItem(FIRST_USE_RESUME_STORAGE_KEY)
    if (raw === null || raw === undefined) return null
    const value = JSON.parse(raw) as Partial<FirstUseResumeRecord>
    if (
      value.version !== 1 ||
      !FIRST_USE_STEPS.includes(value.step as FirstUseStep)
    ) return null
    if (
      typeof value.adultConfirmed !== 'boolean' ||
      typeof value.onboardingSaved !== 'boolean'
    ) return null
    if (value.goal !== null && !goals.includes(value.goal as LearnerGoal)) {
      return null
    }
    if (
      value.placement !== null &&
      value.placement !== 'beginning' &&
      value.placement !== 'deferred'
    ) return null
    return value as FirstUseResumeRecord
  } catch {
    return null
  }
}

export function writeFirstUseResume(record: FirstUseResumeRecord): void {
  try {
    globalThis.localStorage?.setItem(
      FIRST_USE_RESUME_STORAGE_KEY,
      JSON.stringify(record),
    )
  } catch {
    // Resume is best effort; the flow remains usable without browser storage.
  }
}

export function clearFirstUseResume(): void {
  try {
    globalThis.localStorage?.removeItem(FIRST_USE_RESUME_STORAGE_KEY)
  } catch {
    // Completion remains authoritative even when best-effort cleanup is blocked.
  }
}
