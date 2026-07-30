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
}

export function offlineBundleRecordId(
  enrollmentId: string,
  missionId: string,
): string {
  return `bundle:${enrollmentId}:${missionId}`
}
