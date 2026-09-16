import {
  hashOfflineProfileBinding,
  type OfflineProfileBinding,
} from '../offline/OfflineLearningStore'
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

export const FIRST_USE_RESUME_TTL_MILLISECONDS = 24 * 60 * 60 * 1000
export const FIRST_USE_RESUME_STORAGE_PREFIX =
  'global-english:first-use-onboarding:v2:'

export interface FirstUseResumeRecord {
  readonly version: 2
  readonly bindingHash: string
  readonly step: FirstUseStep
  readonly goal: LearnerGoal | null
  readonly placement: PlacementChoice | null
  readonly issuedAt: string
  readonly expiresAt: string
}

export interface FirstUseResumeInput {
  readonly step: FirstUseStep
  readonly goal: LearnerGoal | null
  readonly placement: PlacementChoice | null
}

const goals: readonly LearnerGoal[] = [
  'everyday-conversation',
  'workplace-confidence',
  'travel-confidence',
]

const bindingHashPromises = new WeakMap<
  OfflineProfileBinding,
  Promise<string>
>()
const storageMutations = new Map<string, Promise<void>>()

function resumeBindingHash(binding: OfflineProfileBinding): Promise<string> {
  const existing = bindingHashPromises.get(binding)
  if (existing !== undefined) return existing

  const pending = hashOfflineProfileBinding(binding)
  bindingHashPromises.set(binding, pending)
  return pending
}

async function enqueueStorageMutation(
  binding: OfflineProfileBinding,
  mutate: (storageKey: string) => void,
): Promise<void> {
  const storageKey = `${FIRST_USE_RESUME_STORAGE_PREFIX}${await resumeBindingHash(binding)}`
  const previous = storageMutations.get(storageKey) ?? Promise.resolve()
  const current = previous.catch(() => undefined).then(() => mutate(storageKey))
  storageMutations.set(storageKey, current)

  try {
    await current
  } finally {
    if (storageMutations.get(storageKey) === current) {
      storageMutations.delete(storageKey)
    }
  }
}

export async function firstUseResumeStorageKey(
  binding: OfflineProfileBinding,
): Promise<string> {
  return `${FIRST_USE_RESUME_STORAGE_PREFIX}${await resumeBindingHash(binding)}`
}

export async function hasFirstUseResume(
  binding: OfflineProfileBinding | null,
): Promise<boolean> {
  return (await readFirstUseResume(binding)) !== null
}

export async function readFirstUseResume(
  binding: OfflineProfileBinding | null,
  now = Date.now(),
): Promise<FirstUseResumeRecord | null> {
  if (binding === null) return null

  try {
    const bindingHash = await resumeBindingHash(binding)
    const storageKey = `${FIRST_USE_RESUME_STORAGE_PREFIX}${bindingHash}`
    await storageMutations.get(storageKey)?.catch(() => undefined)
    const raw = globalThis.localStorage?.getItem(storageKey)
    if (raw === null || raw === undefined) return null

    const value = JSON.parse(raw) as Partial<FirstUseResumeRecord>
    const issuedAt = Date.parse(value.issuedAt ?? '')
    const expiresAt = Date.parse(value.expiresAt ?? '')
    const valid =
      value.version === 2 &&
      value.bindingHash === bindingHash &&
      FIRST_USE_STEPS.includes(value.step as FirstUseStep) &&
      (value.goal === null || goals.includes(value.goal as LearnerGoal)) &&
      (value.placement === null ||
        value.placement === 'beginning' ||
        value.placement === 'deferred') &&
      Number.isFinite(issuedAt) &&
      Number.isFinite(expiresAt) &&
      issuedAt <= now &&
      expiresAt > now &&
      expiresAt - issuedAt <= FIRST_USE_RESUME_TTL_MILLISECONDS

    if (!valid) {
      globalThis.localStorage?.removeItem(storageKey)
      return null
    }

    return value as FirstUseResumeRecord
  } catch {
    return null
  }
}

export async function writeFirstUseResume(
  binding: OfflineProfileBinding | null,
  input: FirstUseResumeInput,
  now = Date.now(),
): Promise<void> {
  if (binding === null) return

  try {
    const bindingHash = await resumeBindingHash(binding)
    const record: FirstUseResumeRecord = {
      ...input,
      bindingHash,
      expiresAt: new Date(
        now + FIRST_USE_RESUME_TTL_MILLISECONDS,
      ).toISOString(),
      issuedAt: new Date(now).toISOString(),
      version: 2,
    }
    await enqueueStorageMutation(binding, (storageKey) => {
      globalThis.localStorage?.setItem(storageKey, JSON.stringify(record))
    })
  } catch {
    // Resume is best effort; the flow remains usable without browser storage.
  }
}

export async function clearFirstUseResume(
  binding: OfflineProfileBinding | null,
): Promise<void> {
  if (binding === null) return

  try {
    await enqueueStorageMutation(binding, (storageKey) => {
      globalThis.localStorage?.removeItem(storageKey)
    })
  } catch {
    // Completion remains authoritative even when best-effort cleanup is blocked.
  }
}
