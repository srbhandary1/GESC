import type { FriendSession } from '../conversation/ConversationJourneyApi'

export type LevelAvailability = 'available' | 'commissioning_pending'

export interface LevelSummary {
  level_number: number
  concept_key: string | null
  availability: LevelAvailability
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
  /** Android-only private lesson-audio delivery through the bearer fetch adapter. */
  downloadAudio?(
    url: string,
    signal?: AbortSignal,
  ): Promise<Blob>
}
