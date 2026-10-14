import type { FriendSession } from '../conversation/ConversationJourneyApi'

export type LevelAvailability = 'available' | 'commissioning_pending'

export type LevelEntitlementAccess = 'allowed' | 'upgrade_required'

export type LevelProgressAccess = 'allowed' | 'locked' | 'not_applicable'

export type LevelCheckpointStatus =
  | 'not_applicable'
  | 'not_ready'
  | 'ready'
  | 'passed'
  | 'attempts_exhausted'
  | 'review_due_blocked'

export type LevelSpeakingEvidenceState =
  | 'not_recorded'
  | 'recorded_unassessed'
  | 'awaiting_review'
  | 'reviewed'

export interface LevelSpokenEvidence {
  evidence_state: LevelSpeakingEvidenceState
  spoken_state: 'unassessed'
  spoken_progress_claim: false
}

export interface LevelSummary {
  level_number: number
  concept_key: string | null
  availability: LevelAvailability
  entitlement_access?: LevelEntitlementAccess
  progress_access?: LevelProgressAccess
  unlock_after_level_number?: number | null
  checkpoint_status?: LevelCheckpointStatus
  checkpoint_attempts_remaining?: number | null
  review_due_blocking?: boolean
  spoken_evidence?: LevelSpokenEvidence | null
  blueprint_id: string | null
  session_part_ids: string[]
}

export type SessionPartId =
  | 'introduction'
  | 'practice'
  | 'conversation'
  | 'interaction'

export interface LevelSessionSummary {
  part_id: SessionPartId
  part_order: number
  media_asset_id: string
  completed: boolean
}

export interface LevelSessionsList {
  level_number: number
  concept_key: string
  blueprint_id: string
  blueprint_content_hash: string
  sessions: LevelSessionSummary[]
}

export interface LevelSessionDetail {
  level_number: number
  part_id: SessionPartId
  part_order: number
  completed: boolean
  media_asset_id: string
  audio_stream_path: string
  objective: string | null
  target_patterns: string[]
  kannada_guidance: { summary?: string; [key: string]: unknown }
  talk: {
    scenario: string | null
    elicitation_goals: string[]
    fallback_turns: string[]
    free_speech: boolean
  } | null
  apply: {
    task: string | null
    rubric: string[]
    free_speech: boolean
  } | null
  check: {
    pass_threshold: number
    items: Array<{
      id: string
      weight: number
      prompt: string
      options: string[]
    }>
  } | null
  activities: Array<{ id: string; prompt: string }>
  conversation_binding: {
    prototype: string
    coaching_mode: string
  } | null
}

export interface CheckSubmissionResult {
  replayed: boolean
  score: number
  pass_threshold: number
  passed: boolean
  item_results: Array<{
    id: string
    selected_option: number
    correct: boolean
    rationale: string | null
  }>
}

export interface LevelCheckpointDetail {
  level_number: number
  concept_key: string
  blueprint_id: string
  blueprint_content_hash: string
  rule_key: string
  rule_hash: string
  pass_threshold: number
  max_attempts: number
  attempts_remaining: number | null
  items: Array<{
    id: string
    weight: number
    prompt: string
    options: string[]
  }>
}

export interface LevelCheckpointSubmissionResult extends CheckSubmissionResult {
  attempt_id: string
  attempt_number: number
  attempts_remaining: number | null
  spoken_evidence?: LevelSpokenEvidence
  level_unlock?: {
    replayed: boolean
    unlock_decision_id: string
    level_number: number
  } | null
}

export interface LevelSessionsApi {
  listLevels(signal?: AbortSignal): Promise<{ levels: LevelSummary[] }>
  listSessions(
    levelNumber: number,
    signal?: AbortSignal,
  ): Promise<LevelSessionsList>
  sessionDetail(
    levelNumber: number,
    partId: SessionPartId,
    signal?: AbortSignal,
  ): Promise<LevelSessionDetail>
  completeSession(
    levelNumber: number,
    partId: SessionPartId,
    idempotencyKey: string,
    signal?: AbortSignal,
  ): Promise<{ replayed: boolean; completion_id: string; part_id: string }>
  submitCheck(
    levelNumber: number,
    partId: SessionPartId,
    answers: Record<string, number>,
    idempotencyKey: string,
    signal?: AbortSignal,
  ): Promise<CheckSubmissionResult>
  startConversation(
    levelNumber: number,
    partId: SessionPartId,
    uiLocale: string,
    idempotencyKey: string,
    signal?: AbortSignal,
  ): Promise<FriendSession>
  startTutorHelp?(
    levelNumber: number,
    partId: SessionPartId,
    uiLocale: string,
    idempotencyKey: string,
    signal?: AbortSignal,
  ): Promise<FriendSession>
  checkpointDetail?(
    levelNumber: number,
    signal?: AbortSignal,
  ): Promise<LevelCheckpointDetail>
  offlineLevelBundle?(
    levelNumber: number,
    signal?: AbortSignal,
  ): Promise<import('../offline/OfflineLearningStore').OfflineLevelBundle>

  submitOfflineLevelCheckpoint?(
    bundleId: string,
    lease: string,
    levelNumber: number,
    answers: Record<string, number>,
    idempotencyKey: string,
    signal?: AbortSignal,
  ): Promise<LevelCheckpointSubmissionResult>

  submitCheckpoint?(
    levelNumber: number,
    answers: Record<string, number>,
    idempotencyKey: string,
    signal?: AbortSignal,
  ): Promise<LevelCheckpointSubmissionResult>
  /** Android-only private lesson-audio delivery through the bearer fetch adapter. */
  downloadAudio?(
    url: string,
    signal?: AbortSignal,
  ): Promise<Blob>
}
