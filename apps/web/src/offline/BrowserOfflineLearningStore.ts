import type {
  LearnerMissionResult,
  OfflineMissionBundle,
} from '@global-english/contracts'
import { createClientId } from '../shared/createClientId'
import {
  offlineBundleRecordId,
  offlineCheckpointDetailRecordId,
  offlineLevelBundleRecordId,
  offlineLevelQuizDraftRecordId,
  LEVEL_QUIZ_DRAFT_TTL_MILLISECONDS,
  type CachedLevelCheckpointDetail,
  type OfflineLevelBundle,
  type StoredOfflineLevelBundle,
  type OfflineLearningStore,
  type OfflinePersonalDeviceResume,
  type OfflineProfileBinding,
  type PendingLevelCheckpointCommand,
  type PendingOfflineCommand,
  type LevelQuizDraft,
  type LevelQuizDraftPart,
  type StoredOfflineBundle,
} from './OfflineLearningStore'

const DATABASE_NAME = 'global-english-offline-v1'
const DATABASE_VERSION = 1
const PROFILE_STORE = 'profiles'
const RECORD_STORE = 'records'
const RECORD_SCHEMA = 'global-english.encrypted-offline-record.v1'
const PENDING_ACTIVE_MILLISECONDS = 30 * 24 * 60 * 60 * 1000
const encoder = new TextEncoder()
const decoder = new TextDecoder('utf-8', { fatal: true })

interface StoredProfile {
  readonly bindingHash: string
  readonly partitionId: string
  readonly key: CryptoKey
  readonly createdAt: string
}

interface StoredRecord {
  readonly storageKey: string
  readonly partitionId: string
  readonly id: string
  readonly type:
    | 'binding'
    | 'bundle'
    | 'audio'
    | 'command'
    | 'receipt'
    | 'checkpoint-detail'
    | 'level-bundle'
    | 'checkpoint-command'
    | 'level-quiz-draft'
  readonly state: 'stored' | 'pending' | 'needs-attention' | 'accepted'
  readonly iv: ArrayBuffer
  readonly ciphertext: ArrayBuffer
  readonly createdAt: string
}

interface StoredBundlePayload {
  readonly bundle: OfflineMissionBundle
}

interface StoredAudioPayload {
  readonly bytes: Array<number>
}

interface StoredBindingPayload {
  readonly binding: OfflineProfileBinding
}

interface StoredCommandPayload {
  readonly command: PendingOfflineCommand
}

interface StoredReceiptPayload {
  readonly result: LearnerMissionResult
}

interface StoredCheckpointDetailPayload {
  readonly detail: CachedLevelCheckpointDetail
}

interface StoredCheckpointCommandPayload {
  readonly command: PendingLevelCheckpointCommand
}

interface StoredLevelBundlePayload {
  readonly bundle: OfflineLevelBundle
}

interface StoredLevelQuizDraftPayload {
  readonly draft: LevelQuizDraft
}

export class BrowserOfflineLearningStore
  implements OfflineLearningStore
{
  available(): boolean {
    return (
      typeof indexedDB !== 'undefined' &&
      typeof crypto?.subtle !== 'undefined'
    )
  }

  async hasProfile(binding: OfflineProfileBinding): Promise<boolean> {
    if (!this.available() || !(await databaseExists())) {
      return false
    }

    const database = await openDatabase()

    try {
      return (await this.profile(database, binding)) !== null
    } finally {
      database.close()
    }
  }

  async resumePersonalDevice(): Promise<OfflinePersonalDeviceResume | null> {
    if (!this.available() || !(await databaseExists())) {
      return null
    }

    const database = await openDatabase()

    try {
      const profiles = await allValues<StoredProfile>(
        database,
        PROFILE_STORE,
      )
      const candidates: OfflinePersonalDeviceResume[] = []

      for (const profile of profiles) {
        const bindingPayload =
          await this.readEncrypted<StoredBindingPayload>(
            database,
            profile,
            'binding',
            'binding',
          )

        if (bindingPayload === null) {
          continue
        }

        const storedBundle = await this.latestBundle(
          database,
          profile,
          bindingPayload.binding,
        )

        if (storedBundle !== null) {
          candidates.push({
            binding: bindingPayload.binding,
            storedBundle,
          })
        }
      }

      return candidates.length === 1 ? (candidates[0] ?? null) : null
    } catch {
      return null
    } finally {
      database.close()
    }
  }

  async enablePersonalDevice(
    binding: OfflineProfileBinding,
  ): Promise<void> {
    this.assertAvailable()
    const database = await openDatabase()

    try {
      const existing = await this.profile(database, binding)

      if (existing !== null) {
        await this.assertBinding(database, existing, binding)
        return
      }

      const bindingHash = await hashBinding(binding)
      const profile: StoredProfile = {
        bindingHash,
        partitionId: createClientId(),
        key: await crypto.subtle.generateKey(
          { name: 'AES-GCM', length: 256 },
          false,
          ['encrypt', 'decrypt'],
        ),
        createdAt: new Date().toISOString(),
      }
      await putValue(database, PROFILE_STORE, profile)
      await this.putEncrypted(
        database,
        profile,
        'binding',
        'binding',
        { binding } satisfies StoredBindingPayload,
        'stored',
      )
    } finally {
      database.close()
    }
  }

  async saveBundle(
    binding: OfflineProfileBinding,
    bundle: OfflineMissionBundle,
    audio: Uint8Array,
  ): Promise<void> {
    const database = await openDatabase()

    try {
      const profile = await this.requireProfile(database, binding)
      assertBundleBinding(bundle, binding)
      const verifiedAudio = new Uint8Array(audio)
      await verifyAudio(bundle, verifiedAudio)
      const id = offlineBundleRecordId(
        bundle.manifest.binding.enrollment_id,
        bundle.manifest.mission.id,
      )
      await this.putEncrypted(
        database,
        profile,
        'bundle',
        id,
        { bundle } satisfies StoredBundlePayload,
        'stored',
      )
      await this.putEncrypted(
        database,
        profile,
        'audio',
        id,
        {
          bytes: [...verifiedAudio],
        } satisfies StoredAudioPayload,
        'stored',
      )
    } finally {
      database.close()
    }
  }

  async loadBundle(
    binding: OfflineProfileBinding,
    enrollmentId: string,
    missionId: string,
  ): Promise<StoredOfflineBundle | null> {
    if (!this.available() || !(await databaseExists())) {
      return null
    }

    const database = await openDatabase()

    try {
      const profile = await this.profile(database, binding)

      if (profile === null) {
        return null
      }

      await this.assertBinding(database, profile, binding)
      const id = offlineBundleRecordId(enrollmentId, missionId)
      const bundle = await this.readEncrypted<StoredBundlePayload>(
        database,
        profile,
        'bundle',
        id,
      )
      const audio = await this.readEncrypted<StoredAudioPayload>(
        database,
        profile,
        'audio',
        id,
      )

      if (bundle === null || audio === null) {
        return null
      }

      if (
        new Date(bundle.bundle.manifest.expires_at).getTime() <=
        Date.now()
      ) {
        await deleteValue(
          database,
          RECORD_STORE,
          storageKey(profile.partitionId, 'bundle', id),
        )
        await deleteValue(
          database,
          RECORD_STORE,
          storageKey(profile.partitionId, 'audio', id),
        )

        return null
      }

      assertBundleBinding(bundle.bundle, binding)
      const bytes = Uint8Array.from(audio.bytes)
      await verifyAudio(bundle.bundle, bytes)

      return { audio: bytes, bundle: bundle.bundle }
    } finally {
      database.close()
    }
  }

  async queueCompletion(
    binding: OfflineProfileBinding,
    command: PendingOfflineCommand,
  ): Promise<void> {
    rejectForbiddenOfflineFields(command)
    const database = await openDatabase()

    try {
      const profile = await this.requireProfile(database, binding)
      const existing = await recordsForPartition(
        database,
        profile.partitionId,
        'command',
      )
      const stale = existing.filter(
        ({ createdAt }) =>
          Date.now() - new Date(createdAt).getTime() >=
          PENDING_ACTIVE_MILLISECONDS,
      )

      for (const record of stale) {
        const payload = await this.decrypt<StoredCommandPayload>(
          profile,
          record,
        )
        await this.putEncrypted(
          database,
          profile,
          'command',
          record.id,
          {
            command: {
              ...payload.command,
              state: 'needs-attention',
            },
          } satisfies StoredCommandPayload,
          'needs-attention',
        )
      }

      if (stale.length > 0) {
        throw new OfflinePendingWorkError()
      }

      await this.putEncrypted(
        database,
        profile,
        'command',
        command.commandId,
        { command } satisfies StoredCommandPayload,
        command.state,
      )
    } finally {
      database.close()
    }
  }

  async pendingCommands(
    binding: OfflineProfileBinding,
  ): Promise<ReadonlyArray<PendingOfflineCommand>> {
    if (!this.available() || !(await databaseExists())) {
      return []
    }

    const database = await openDatabase()

    try {
      const profile = await this.profile(database, binding)

      if (profile === null) {
        return []
      }

      await this.assertBinding(database, profile, binding)
      const records = await recordsForPartition(
        database,
        profile.partitionId,
        'command',
      )
      const commands = await Promise.all(
        records.map(async (record) => {
          const payload =
            await this.decrypt<StoredCommandPayload>(
              profile,
              record,
            )

          if (
            payload.command.state === 'pending' &&
            Date.now() -
              new Date(payload.command.createdAt).getTime() >=
              PENDING_ACTIVE_MILLISECONDS
          ) {
            const command = {
              ...payload.command,
              state: 'needs-attention' as const,
            }
            await this.putEncrypted(
              database,
              profile,
              'command',
              record.id,
              { command } satisfies StoredCommandPayload,
              'needs-attention',
            )

            return command
          }

          return payload.command
        }),
      )

      return commands.sort((left, right) =>
        left.createdAt.localeCompare(right.createdAt),
      )
    } finally {
      database.close()
    }
  }

  async prepareLogout(): Promise<void> {
    if (!this.available() || !(await databaseExists())) {
      return
    }

    const database = await openDatabase()

    try {
      const transaction = database.transaction(
        RECORD_STORE,
        'readonly',
      )
      const records = (await requestResult(
        transaction.objectStore(RECORD_STORE).getAll(),
      )) as StoredRecord[]

      if (
        records.some(
          ({ type, state }) =>
            (type === 'command' || type === 'checkpoint-command') &&
            state !== 'accepted',
        )
      ) {
        throw new OfflinePendingWorkError()
      }

      await clearStore(database, PROFILE_STORE)
      await clearStore(database, RECORD_STORE)
    } finally {
      database.close()
    }
  }

  async markNeedsAttention(
    binding: OfflineProfileBinding,
    commandId: string,
  ): Promise<void> {
    const database = await openDatabase()

    try {
      const profile = await this.requireProfile(database, binding)
      const payload = await this.readEncrypted<StoredCommandPayload>(
        database,
        profile,
        'command',
        commandId,
      )

      if (payload === null) {
        return
      }

      await this.putEncrypted(
        database,
        profile,
        'command',
        commandId,
        {
          command: {
            ...payload.command,
            state: 'needs-attention',
          },
        } satisfies StoredCommandPayload,
        'needs-attention',
      )
    } finally {
      database.close()
    }
  }

  async saveCheckpointDetail(
    binding: OfflineProfileBinding,
    detail: CachedLevelCheckpointDetail,
  ): Promise<void> {
    rejectForbiddenOfflineFields(detail)
    const database = await openDatabase()

    try {
      const profile = await this.requireProfile(database, binding)
      await this.putEncrypted(
        database,
        profile,
        'checkpoint-detail',
        offlineCheckpointDetailRecordId(detail.level_number),
        { detail } satisfies StoredCheckpointDetailPayload,
        'stored',
      )
    } finally {
      database.close()
    }
  }

  async loadCheckpointDetail(
    binding: OfflineProfileBinding,
    levelNumber: number,
    blueprintContentHash: string,
  ): Promise<CachedLevelCheckpointDetail | null> {
    if (!this.available() || !(await databaseExists())) {
      return null
    }

    const database = await openDatabase()

    try {
      const profile = await this.profile(database, binding)

      if (profile === null) {
        return null
      }

      await this.assertBinding(database, profile, binding)
      const payload =
        await this.readEncrypted<StoredCheckpointDetailPayload>(
          database,
          profile,
          'checkpoint-detail',
          offlineCheckpointDetailRecordId(levelNumber),
        )

      if (
        payload === null ||
        payload.detail.blueprint_content_hash !== blueprintContentHash
      ) {
        return null
      }

      return payload.detail
    } finally {
      database.close()
    }
  }

  async saveLevelBundle(
    binding: OfflineProfileBinding,
    bundle: OfflineLevelBundle,
  ): Promise<void> {
    rejectForbiddenOfflineFields(bundle)
    const database = await openDatabase()

    try {
      const profile = await this.requireProfile(database, binding)
      await this.putEncrypted(
        database,
        profile,
        'level-bundle',
        offlineLevelBundleRecordId(bundle.manifest.level.level_number),
        { bundle } satisfies StoredLevelBundlePayload,
        'stored',
      )
    } finally {
      database.close()
    }
  }

  async loadLevelBundle(
    binding: OfflineProfileBinding,
    levelNumber: number,
    blueprintContentHash: string,
  ): Promise<StoredOfflineLevelBundle | null> {
    if (!this.available() || !(await databaseExists())) {
      return null
    }

    const database = await openDatabase()

    try {
      const profile = await this.profile(database, binding)

      if (profile === null) {
        return null
      }

      await this.assertBinding(database, profile, binding)
      const payload = await this.readEncrypted<StoredLevelBundlePayload>(
        database,
        profile,
        'level-bundle',
        offlineLevelBundleRecordId(levelNumber),
      )

      if (
        payload === null ||
        payload.bundle.manifest.level.blueprint_content_hash !==
          blueprintContentHash
      ) {
        return null
      }

      return payload
    } finally {
      database.close()
    }
  }

  async queueCheckpointSubmission(
    binding: OfflineProfileBinding,
    command: PendingLevelCheckpointCommand,
  ): Promise<void> {
    rejectForbiddenOfflineFields(command)
    const database = await openDatabase()

    try {
      const profile = await this.requireProfile(database, binding)
      await this.putEncrypted(
        database,
        profile,
        'checkpoint-command',
        command.commandId,
        { command } satisfies StoredCheckpointCommandPayload,
        command.state,
      )
    } finally {
      database.close()
    }
  }

  async pendingCheckpointCommands(
    binding: OfflineProfileBinding,
  ): Promise<ReadonlyArray<PendingLevelCheckpointCommand>> {
    if (!this.available() || !(await databaseExists())) {
      return []
    }

    const database = await openDatabase()

    try {
      const profile = await this.profile(database, binding)

      if (profile === null) {
        return []
      }

      await this.assertBinding(database, profile, binding)
      const records = await recordsForPartition(
        database,
        profile.partitionId,
        'checkpoint-command',
      )
      const commands = await Promise.all(
        records.map(async (record) => {
          const payload =
            await this.decrypt<StoredCheckpointCommandPayload>(
              profile,
              record,
            )

          if (
            payload.command.state === 'pending' &&
            Date.now() -
              new Date(payload.command.createdAt).getTime() >=
              PENDING_ACTIVE_MILLISECONDS
          ) {
            const command = {
              ...payload.command,
              state: 'needs-attention' as const,
            }
            await this.putEncrypted(
              database,
              profile,
              'checkpoint-command',
              record.id,
              { command } satisfies StoredCheckpointCommandPayload,
              'needs-attention',
            )

            return command
          }

          return payload.command
        }),
      )

      return commands.sort((left, right) =>
        left.createdAt.localeCompare(right.createdAt),
      )
    } finally {
      database.close()
    }
  }

  async markCheckpointNeedsAttention(
    binding: OfflineProfileBinding,
    commandId: string,
  ): Promise<void> {
    const database = await openDatabase()

    try {
      const profile = await this.requireProfile(database, binding)
      const payload =
        await this.readEncrypted<StoredCheckpointCommandPayload>(
          database,
          profile,
          'checkpoint-command',
          commandId,
        )

      if (payload === null) {
        return
      }

      await this.putEncrypted(
        database,
        profile,
        'checkpoint-command',
        commandId,
        {
          command: {
            ...payload.command,
            state: 'needs-attention',
          },
        } satisfies StoredCheckpointCommandPayload,
        'needs-attention',
      )
    } finally {
      database.close()
    }
  }

  async acceptCheckpointReceipt(
    binding: OfflineProfileBinding,
    commandId: string,
  ): Promise<void> {
    const database = await openDatabase()

    try {
      const profile = await this.requireProfile(database, binding)
      await deleteValue(
        database,
        RECORD_STORE,
        storageKey(
          profile.partitionId,
          'checkpoint-command',
          commandId,
        ),
      )
    } finally {
      database.close()
    }
  }

  async saveLevelQuizDraft(
    binding: OfflineProfileBinding,
    draft: LevelQuizDraft,
  ): Promise<void> {
    assertLevelQuizDraft(draft)
    const database = await openDatabase()

    try {
      const profile = await this.requireProfile(database, binding)
      await this.putEncrypted(
        database,
        profile,
        'level-quiz-draft',
        offlineLevelQuizDraftRecordId(draft.levelNumber, draft.partId),
        { draft } satisfies StoredLevelQuizDraftPayload,
        'stored',
      )
    } finally {
      database.close()
    }
  }

  async loadLevelQuizDraft(
    binding: OfflineProfileBinding,
    levelNumber: number,
    partId: LevelQuizDraftPart,
  ): Promise<LevelQuizDraft | null> {
    if (!this.available() || !(await databaseExists())) {
      return null
    }

    const database = await openDatabase()

    try {
      const profile = await this.profile(database, binding)

      if (profile === null) {
        return null
      }

      await this.assertBinding(database, profile, binding)
      const id = offlineLevelQuizDraftRecordId(levelNumber, partId)
      const payload = await this.readEncrypted<StoredLevelQuizDraftPayload>(
        database,
        profile,
        'level-quiz-draft',
        id,
      )

      if (payload === null) {
        return null
      }

      try {
        assertLevelQuizDraft(payload.draft)
      } catch {
        await deleteValue(
          database,
          RECORD_STORE,
          storageKey(profile.partitionId, 'level-quiz-draft', id),
        )
        return null
      }

      if (
        payload.draft.levelNumber !== levelNumber ||
        payload.draft.partId !== partId ||
        new Date(payload.draft.expiresAt).getTime() <= Date.now()
      ) {
        await deleteValue(
          database,
          RECORD_STORE,
          storageKey(profile.partitionId, 'level-quiz-draft', id),
        )
        return null
      }

      return payload.draft
    } finally {
      database.close()
    }
  }

  async deleteLevelQuizDraft(
    binding: OfflineProfileBinding,
    levelNumber: number,
    partId: LevelQuizDraftPart,
    attemptId: string,
  ): Promise<void> {
    const database = await openDatabase()

    try {
      const profile = await this.requireProfile(database, binding)
      const id = offlineLevelQuizDraftRecordId(levelNumber, partId)
      const payload = await this.readEncrypted<StoredLevelQuizDraftPayload>(
        database,
        profile,
        'level-quiz-draft',
        id,
      )

      if (payload?.draft.attemptId !== attemptId) {
        return
      }

      await deleteValue(
        database,
        RECORD_STORE,
        storageKey(profile.partitionId, 'level-quiz-draft', id),
      )
    } finally {
      database.close()
    }
  }

  async acceptReceipt(
    binding: OfflineProfileBinding,
    commandId: string,
    result: LearnerMissionResult,
  ): Promise<void> {
    const database = await openDatabase()

    try {
      const profile = await this.requireProfile(database, binding)
      await this.putEncrypted(
        database,
        profile,
        'receipt',
        commandId,
        { result } satisfies StoredReceiptPayload,
        'accepted',
      )
      await deleteValue(
        database,
        RECORD_STORE,
        storageKey(profile.partitionId, 'command', commandId),
      )
      await deleteValue(
        database,
        RECORD_STORE,
        storageKey(profile.partitionId, 'receipt', commandId),
      )
    } finally {
      database.close()
    }
  }

  async purge(binding: OfflineProfileBinding): Promise<void> {
    if (!this.available() || !(await databaseExists())) {
      return
    }

    const database = await openDatabase()

    try {
      const profile = await this.profile(database, binding)

      if (profile === null) {
        return
      }

      await deleteValue(
        database,
        PROFILE_STORE,
        profile.bindingHash,
      )
      await deletePartitionRecords(database, profile.partitionId)
    } finally {
      database.close()
    }
  }

  async purgeAll(): Promise<void> {
    if (!this.available() || !(await databaseExists())) {
      return
    }

    const database = await openDatabase()

    try {
      await clearStore(database, PROFILE_STORE)
      await clearStore(database, RECORD_STORE)
    } finally {
      database.close()
    }
  }

  private async profile(
    database: IDBDatabase,
    binding: OfflineProfileBinding,
  ): Promise<StoredProfile | null> {
    const value = await getValue<StoredProfile>(
      database,
      PROFILE_STORE,
      await hashBinding(binding),
    )

    return value ?? null
  }

  private async requireProfile(
    database: IDBDatabase,
    binding: OfflineProfileBinding,
  ): Promise<StoredProfile> {
    this.assertAvailable()
    const profile = await this.profile(database, binding)

    if (profile === null) {
      throw new OfflineStorageError()
    }

    await this.assertBinding(database, profile, binding)

    return profile
  }

  private async assertBinding(
    database: IDBDatabase,
    profile: StoredProfile,
    binding: OfflineProfileBinding,
  ): Promise<void> {
    const stored = await this.readEncrypted<StoredBindingPayload>(
      database,
      profile,
      'binding',
      'binding',
    )

    if (
      stored === null ||
      stored.binding.userId !== binding.userId ||
      stored.binding.membershipId !== binding.membershipId ||
      stored.binding.organizationId !== binding.organizationId
    ) {
      throw new OfflineStorageError()
    }
  }

  private async putEncrypted(
    database: IDBDatabase,
    profile: StoredProfile,
    type: StoredRecord['type'],
    id: string,
    value: unknown,
    state: StoredRecord['state'],
  ): Promise<void> {
    const iv = crypto.getRandomValues(new Uint8Array(12))
    const ciphertext = await crypto.subtle.encrypt(
      {
        name: 'AES-GCM',
        iv,
        additionalData: additionalData(profile.partitionId, type, id),
      },
      profile.key,
      encoder.encode(JSON.stringify(value)),
    )
    await putValue(database, RECORD_STORE, {
      storageKey: storageKey(profile.partitionId, type, id),
      partitionId: profile.partitionId,
      id,
      type,
      state,
      iv: iv.buffer,
      ciphertext,
      createdAt: new Date().toISOString(),
    } satisfies StoredRecord)
  }

  private async readEncrypted<T>(
    database: IDBDatabase,
    profile: StoredProfile,
    type: StoredRecord['type'],
    id: string,
  ): Promise<T | null> {
    const record = await getValue<StoredRecord>(
      database,
      RECORD_STORE,
      storageKey(profile.partitionId, type, id),
    )

    if (record === undefined) {
      return null
    }

    return this.decrypt<T>(profile, record)
  }

  private async decrypt<T>(
    profile: StoredProfile,
    record: StoredRecord,
  ): Promise<T> {
    try {
      const plaintext = await crypto.subtle.decrypt(
        {
          name: 'AES-GCM',
          iv: record.iv,
          additionalData: additionalData(
            profile.partitionId,
            record.type,
            record.id,
          ),
        },
        profile.key,
        record.ciphertext,
      )

      return JSON.parse(decoder.decode(plaintext)) as T
    } catch {
      throw new OfflineStorageError()
    }
  }

  private async latestBundle(
    database: IDBDatabase,
    profile: StoredProfile,
    binding: OfflineProfileBinding,
  ): Promise<StoredOfflineBundle | null> {
    const bundles = (
      await recordsForPartition(database, profile.partitionId, 'bundle')
    ).sort((left, right) =>
      right.createdAt.localeCompare(left.createdAt),
    )

    for (const record of bundles) {
      const payload = await this.decrypt<StoredBundlePayload>(
        profile,
        record,
      )

      if (
        new Date(payload.bundle.manifest.expires_at).getTime() <=
        Date.now()
      ) {
        await deleteValue(
          database,
          RECORD_STORE,
          storageKey(profile.partitionId, 'bundle', record.id),
        )
        await deleteValue(
          database,
          RECORD_STORE,
          storageKey(profile.partitionId, 'audio', record.id),
        )
        continue
      }

      assertBundleBinding(payload.bundle, binding)
      const audio = await this.readEncrypted<StoredAudioPayload>(
        database,
        profile,
        'audio',
        record.id,
      )

      if (audio === null) {
        continue
      }

      const bytes = Uint8Array.from(audio.bytes)
      await verifyAudio(payload.bundle, bytes)

      return { audio: bytes, bundle: payload.bundle }
    }

    return null
  }

  private assertAvailable(): void {
    if (!this.available()) {
      throw new OfflineStorageError()
    }
  }
}

export class OfflineStorageError extends Error {
  constructor() {
    super('Protected offline storage is unavailable.')
    this.name = 'OfflineStorageError'
  }
}

export class OfflinePendingWorkError extends Error {
  constructor() {
    super('Protected offline work must be synchronized or explicitly discarded.')
    this.name = 'OfflinePendingWorkError'
  }
}

async function hashBinding(
  binding: OfflineProfileBinding,
): Promise<string> {
  const digest = await crypto.subtle.digest(
    'SHA-256',
    encoder.encode(
      [
        'global-english.offline-binding.v1',
        binding.userId,
        binding.organizationId,
        binding.membershipId,
      ].join('\u0000'),
    ),
  )

  return hex(new Uint8Array(digest))
}

async function verifyAudio(
  bundle: OfflineMissionBundle,
  bytes: Uint8Array<ArrayBuffer>,
): Promise<void> {
  const expected = bundle.manifest.mission.model_audio

  if (bytes.byteLength !== expected.byte_size) {
    throw new OfflineStorageError()
  }

  const digest = await crypto.subtle.digest('SHA-256', bytes)

  if (hex(new Uint8Array(digest)) !== expected.content_hash) {
    throw new OfflineStorageError()
  }
}

function assertBundleBinding(
  bundle: OfflineMissionBundle,
  binding: OfflineProfileBinding,
): void {
  const actual = bundle.manifest.binding

  if (
    actual.user_id !== binding.userId ||
    actual.organization_id !== binding.organizationId ||
    actual.membership_id !== binding.membershipId ||
    new Date(bundle.manifest.expires_at).getTime() <= Date.now()
  ) {
    throw new OfflineStorageError()
  }
}

function rejectForbiddenOfflineFields(value: unknown): void {
  const serialized = JSON.stringify(value).toLowerCase()

  for (const forbidden of [
    'raw_audio',
    'audio_bytes',
    'access_token',
    'refresh_token',
    'xsrf-token',
    'mobile_number',
    'display_name',
  ]) {
    if (serialized.includes(forbidden)) {
      throw new OfflineStorageError()
    }
  }
}

function assertLevelQuizDraft(draft: LevelQuizDraft): void {
  rejectForbiddenOfflineFields(draft)

  const allowedParts = new Set<LevelQuizDraftPart>([
    'introduction',
    'practice',
    'conversation',
    'interaction',
    'checkpoint',
  ])
  const allowedFields = new Set([
    'activeItemIds',
    'attemptId',
    'blueprintContentHash',
    'createdAt',
    'currentItemIndex',
    'expiresAt',
    'itemIds',
    'levelNumber',
    'partId',
    'selectedOptionIds',
    'updatedAt',
  ])

  if (
    Object.keys(draft).some((field) => !allowedFields.has(field)) ||
    !Number.isInteger(draft.levelNumber) ||
    draft.levelNumber < 1 ||
    draft.attemptId.trim() === '' ||
    draft.blueprintContentHash.trim() === '' ||
    !allowedParts.has(draft.partId) ||
    draft.itemIds.length === 0 ||
    draft.activeItemIds.length === 0 ||
    !Number.isInteger(draft.currentItemIndex) ||
    draft.currentItemIndex < 0 ||
    draft.currentItemIndex >= draft.activeItemIds.length ||
    !Number.isFinite(new Date(draft.createdAt).getTime()) ||
    !Number.isFinite(new Date(draft.updatedAt).getTime()) ||
    !Number.isFinite(new Date(draft.expiresAt).getTime()) ||
    new Date(draft.expiresAt).getTime() <=
      new Date(draft.createdAt).getTime() ||
    new Date(draft.expiresAt).getTime() -
      new Date(draft.createdAt).getTime() >
      LEVEL_QUIZ_DRAFT_TTL_MILLISECONDS
  ) {
    throw new OfflineStorageError()
  }

  const itemIds = new Set(draft.itemIds)
  if (
    itemIds.size !== draft.itemIds.length ||
    new Set(draft.activeItemIds).size !== draft.activeItemIds.length ||
    draft.itemIds.some((itemId) => itemId.trim() === '') ||
    draft.activeItemIds.some((itemId) => !itemIds.has(itemId)) ||
    Object.entries(draft.selectedOptionIds).some(
      ([itemId, optionId]) =>
        !itemIds.has(itemId) ||
        !Number.isInteger(optionId) ||
        optionId < 0,
    )
  ) {
    throw new OfflineStorageError()
  }
}

function storageKey(
  partitionId: string,
  type: StoredRecord['type'],
  id: string,
): string {
  return `${partitionId}:${type}:${id}`
}

function additionalData(
  partitionId: string,
  type: StoredRecord['type'],
  id: string,
): Uint8Array<ArrayBuffer> {
  return encoder.encode(
    [RECORD_SCHEMA, partitionId, type, id].join('\u0000'),
  )
}

function hex(value: Uint8Array): string {
  return [...value]
    .map((byte) => byte.toString(16).padStart(2, '0'))
    .join('')
}

function openDatabase(): Promise<IDBDatabase> {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open(DATABASE_NAME, DATABASE_VERSION)

    request.onupgradeneeded = () => {
      const database = request.result

      if (!database.objectStoreNames.contains(PROFILE_STORE)) {
        database.createObjectStore(PROFILE_STORE, {
          keyPath: 'bindingHash',
        })
      }

      if (!database.objectStoreNames.contains(RECORD_STORE)) {
        const records = database.createObjectStore(RECORD_STORE, {
          keyPath: 'storageKey',
        })
        records.createIndex('partitionId', 'partitionId', {
          unique: false,
        })
      }
    }
    request.onsuccess = () => resolve(request.result)
    request.onerror = () => reject(new OfflineStorageError())
    request.onblocked = () => reject(new OfflineStorageError())
  })
}

async function databaseExists(): Promise<boolean> {
  if (typeof indexedDB.databases !== 'function') {
    return false
  }

  const databases = await indexedDB.databases()

  return databases.some(({ name }) => name === DATABASE_NAME)
}

function requestResult<T>(request: IDBRequest<T>): Promise<T> {
  return new Promise((resolve, reject) => {
    request.onsuccess = () => resolve(request.result)
    request.onerror = () => reject(new OfflineStorageError())
  })
}

async function getValue<T>(
  database: IDBDatabase,
  storeName: string,
  key: IDBValidKey,
): Promise<T | undefined> {
  const transaction = database.transaction(storeName, 'readonly')
  const value = await requestResult(
    transaction.objectStore(storeName).get(key),
  )

  return value as T | undefined
}

async function allValues<T>(
  database: IDBDatabase,
  storeName: string,
): Promise<T[]> {
  const transaction = database.transaction(storeName, 'readonly')

  return (await requestResult(
    transaction.objectStore(storeName).getAll(),
  )) as T[]
}

async function putValue(
  database: IDBDatabase,
  storeName: string,
  value: unknown,
): Promise<void> {
  const transaction = database.transaction(storeName, 'readwrite')
  await requestResult(transaction.objectStore(storeName).put(value))
}

async function deleteValue(
  database: IDBDatabase,
  storeName: string,
  key: IDBValidKey,
): Promise<void> {
  const transaction = database.transaction(storeName, 'readwrite')
  await requestResult(transaction.objectStore(storeName).delete(key))
}

async function clearStore(
  database: IDBDatabase,
  storeName: string,
): Promise<void> {
  const transaction = database.transaction(storeName, 'readwrite')
  await requestResult(transaction.objectStore(storeName).clear())
}

async function recordsForPartition(
  database: IDBDatabase,
  partitionId: string,
  type?: StoredRecord['type'],
): Promise<StoredRecord[]> {
  const transaction = database.transaction(RECORD_STORE, 'readonly')
  const index = transaction.objectStore(RECORD_STORE).index('partitionId')
  const records = (await requestResult(
    index.getAll(partitionId),
  )) as StoredRecord[]

  return type === undefined
    ? records
    : records.filter((record) => record.type === type)
}

async function deletePartitionRecords(
  database: IDBDatabase,
  partitionId: string,
): Promise<void> {
  const records = await recordsForPartition(database, partitionId)
  const transaction = database.transaction(RECORD_STORE, 'readwrite')
  const store = transaction.objectStore(RECORD_STORE)

  await Promise.all(
    records.map((record) =>
      requestResult(store.delete(record.storageKey)),
    ),
  )
}

export const browserOfflineLearningStore =
  new BrowserOfflineLearningStore()
