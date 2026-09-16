import type {
  CompleteOfflineMissionRequest,
  LearnerMissionResult,
  OfflineMissionBundle,
} from '@global-english/contracts'

export interface OfflineProfileBinding {
  readonly userId: string
  readonly organizationId: string
  readonly membershipId: string
}

export interface StoredOfflineBundle {
  readonly bundle: OfflineMissionBundle
  readonly audio: Uint8Array
}

export interface OfflinePersonalDeviceResume {
  readonly binding: OfflineProfileBinding
  readonly storedBundle: StoredOfflineBundle
}

export interface PendingOfflineCommand {
  readonly commandId: string
  readonly body: CompleteOfflineMissionRequest
  readonly createdAt: string
  readonly state: 'pending' | 'needs-attention'
}

export interface CachedLevelCheckpointDetail {
  readonly level_number: number
  readonly concept_key: string
  readonly blueprint_id: string
  readonly blueprint_content_hash: string
  readonly rule_key: string
  readonly rule_hash: string
  readonly pass_threshold: number
  readonly max_attempts: number
  readonly attempts_remaining: number | null
  readonly items: Array<{
    readonly id: string
    readonly weight: number
    readonly prompt: string
    readonly options: readonly string[]
  }>
}

export interface OfflineLevelBundle {
  readonly manifest: {
    readonly schema: 'global-english.offline-level-bundle.v1'
    readonly bundle_id: string
    readonly bundle_hash: string
    readonly issued_at: string
    readonly expires_at: string
    readonly binding: OfflineProfileBinding
    readonly level: {
      readonly level_number: number
      readonly concept_key: string
      readonly blueprint_id: string
      readonly blueprint_content_hash: string
      readonly checkpoint_rule_key: string
      readonly checkpoint_rule_hash: string
    }
    readonly checkpoint: CachedLevelCheckpointDetail
  }
  readonly lease: string
}

export interface StoredOfflineLevelBundle {
  readonly bundle: OfflineLevelBundle
}

export interface PendingLevelCheckpointCommand {
  readonly commandId: string
  readonly levelNumber: number
  readonly blueprintContentHash: string
  readonly bundleId: string
  readonly lease: string
  readonly answers: Record<string, number>
  readonly idempotencyKey: string
  readonly createdAt: string
  readonly state: 'pending' | 'needs-attention'
}

export type LevelQuizDraftPart =
  | 'introduction'
  | 'practice'
  | 'conversation'
  | 'interaction'
  | 'checkpoint'

/**
 * A privacy-minimized, server-identifier-only draft for a level check.
 * Prompts, option copy and learner-authored content must never be added here.
 */
export interface LevelQuizDraft {
  readonly levelNumber: number
  readonly partId: LevelQuizDraftPart
  readonly blueprintContentHash: string
  readonly attemptId: string
  readonly itemIds: readonly string[]
  readonly activeItemIds: readonly string[]
  readonly currentItemIndex: number
  readonly selectedOptionIds: Readonly<Record<string, number>>
  readonly createdAt: string
  readonly updatedAt: string
  readonly expiresAt: string
}

export const LEVEL_QUIZ_DRAFT_TTL_MILLISECONDS = 24 * 60 * 60 * 1000

export interface OfflineLearningStore {
  available(): boolean

  hasProfile(binding: OfflineProfileBinding): Promise<boolean>

  resumePersonalDevice(): Promise<OfflinePersonalDeviceResume | null>

  enablePersonalDevice(binding: OfflineProfileBinding): Promise<void>

  saveBundle(
    binding: OfflineProfileBinding,
    bundle: OfflineMissionBundle,
    audio: Uint8Array,
  ): Promise<void>

  loadBundle(
    binding: OfflineProfileBinding,
    enrollmentId: string,
    missionId: string,
  ): Promise<StoredOfflineBundle | null>

  queueCompletion(
    binding: OfflineProfileBinding,
    command: PendingOfflineCommand,
  ): Promise<void>

  pendingCommands(
    binding: OfflineProfileBinding,
  ): Promise<ReadonlyArray<PendingOfflineCommand>>

  prepareLogout(): Promise<void>

  markNeedsAttention(
    binding: OfflineProfileBinding,
    commandId: string,
  ): Promise<void>

  acceptReceipt(
    binding: OfflineProfileBinding,
    commandId: string,
    result: LearnerMissionResult,
  ): Promise<void>

  purge(binding: OfflineProfileBinding): Promise<void>

  purgeAll(): Promise<void>

  saveCheckpointDetail?(
    binding: OfflineProfileBinding,
    detail: CachedLevelCheckpointDetail,
  ): Promise<void>

  loadCheckpointDetail?(
    binding: OfflineProfileBinding,
    levelNumber: number,
    blueprintContentHash: string,
  ): Promise<CachedLevelCheckpointDetail | null>

  queueCheckpointSubmission?(
    binding: OfflineProfileBinding,
    command: PendingLevelCheckpointCommand,
  ): Promise<void>

  pendingCheckpointCommands?(
    binding: OfflineProfileBinding,
  ): Promise<ReadonlyArray<PendingLevelCheckpointCommand>>

  markCheckpointNeedsAttention?(
    binding: OfflineProfileBinding,
    commandId: string,
  ): Promise<void>

  acceptCheckpointReceipt?(
    binding: OfflineProfileBinding,
    commandId: string,
  ): Promise<void>

  saveLevelBundle?(
    binding: OfflineProfileBinding,
    bundle: OfflineLevelBundle,
  ): Promise<void>

  loadLevelBundle?(
    binding: OfflineProfileBinding,
    levelNumber: number,
    blueprintContentHash: string,
  ): Promise<StoredOfflineLevelBundle | null>

  saveLevelQuizDraft?(
    binding: OfflineProfileBinding,
    draft: LevelQuizDraft,
  ): Promise<void>

  loadLevelQuizDraft?(
    binding: OfflineProfileBinding,
    levelNumber: number,
    partId: LevelQuizDraftPart,
  ): Promise<LevelQuizDraft | null>

  deleteLevelQuizDraft?(
    binding: OfflineProfileBinding,
    levelNumber: number,
    partId: LevelQuizDraftPart,
    attemptId: string,
  ): Promise<void>
}

export function offlineLevelBundleRecordId(levelNumber: number): string {
  return `level-bundle:${levelNumber}`
}

export function offlineBundleRecordId(
  enrollmentId: string,
  missionId: string,
): string {
  return `bundle:${enrollmentId}:${missionId}`
}

export function offlineCheckpointDetailRecordId(levelNumber: number): string {
  return `checkpoint-detail:${levelNumber}`
}

export function offlineLevelQuizDraftRecordId(
  levelNumber: number,
  partId: LevelQuizDraftPart,
): string {
  return `level-quiz-draft:${levelNumber}:${partId}`
}
