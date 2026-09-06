import type {
  AdvanceConversationSessionStageRequest,
  ConversationSession,
  ConversationSpeechResult,
  ConversationTurn,
  ConversationTurnEvent,
  CreateConversationTurnRequest,
  CreateLearnerAiMemoryFactRequest,
  GrantVoiceConsentRequest,
  LearnerAiMemoryFact,
  LearnerAiMemoryFactListingItem,
  LearnerAiMemoryListing,
  ReviewVoiceTranscriptRequest,
  StartConversationSessionRequest,
  SupportedLocale as ApiSupportedLocale,
  SynthesizeConversationSpeechRequest,
  UpdateLearnerAiMemoryFactRequest,
  VoiceConsentEvent,
  VoiceConsentListing,
  VoiceDeletion,
  VoiceConsentPurpose,
  VoiceRecording,
  WithdrawVoiceConsentRequest,
} from '@global-english/contracts'

export type FriendSession = ConversationSession
export type FriendTurn = ConversationTurn
export type FriendTurnEvent = ConversationTurnEvent
export type StartFriendSessionInput = StartConversationSessionRequest
export type CreateFriendTurnInput = CreateConversationTurnRequest
export type AdvanceRoleplayStageInput = AdvanceConversationSessionStageRequest
export type VoiceConsents = VoiceConsentListing
export type VoiceConsent = VoiceConsentEvent
export type SecureVoiceRecording = VoiceRecording
export type SecureVoiceDeletion = VoiceDeletion
export type LearnerMemoryFact = LearnerAiMemoryFactListingItem
export type LearnerMemoryFactResult = LearnerAiMemoryFact
export type LearnerMemoryListing = LearnerAiMemoryListing

export interface ConversationJourneyApi {
  startSession(
    input: StartFriendSessionInput,
    commandId: string,
    signal?: AbortSignal,
  ): Promise<FriendSession>

  advanceStage?(
    sessionId: string,
    input: AdvanceRoleplayStageInput,
    commandId: string,
    signal?: AbortSignal,
  ): Promise<FriendSession>

  createTurn(
    sessionId: string,
    input: CreateFriendTurnInput,
    commandId: string,
    signal?: AbortSignal,
  ): Promise<FriendTurn>

  synthesizeSpeech?(
    sessionId: string,
    input: SynthesizeConversationSpeechRequest,
    commandId: string,
    signal?: AbortSignal,
  ): Promise<ConversationSpeechResult>

  events(
    eventsUrl: string,
    signal?: AbortSignal,
  ): Promise<readonly FriendTurnEvent[]>

  voiceConsents?(
    locale: ApiSupportedLocale,
    signal?: AbortSignal,
  ): Promise<VoiceConsents>

  grantVoiceConsent?(
    input: GrantVoiceConsentRequest,
    commandId: string,
    signal?: AbortSignal,
  ): Promise<VoiceConsent>

  withdrawVoiceConsent?(
    purpose: VoiceConsentPurpose,
    input: WithdrawVoiceConsentRequest,
    commandId: string,
    signal?: AbortSignal,
  ): Promise<VoiceConsent>

  uploadVoiceRecording?(
    sessionId: string,
    audio: Blob,
    commandId: string,
    signal?: AbortSignal,
  ): Promise<SecureVoiceRecording>

  reviewVoiceTranscript?(
    recordingId: string,
    input: ReviewVoiceTranscriptRequest,
    commandId: string,
    signal?: AbortSignal,
  ): Promise<SecureVoiceRecording>

  deleteVoiceRecording?(
    recordingId: string,
    signal?: AbortSignal,
  ): Promise<SecureVoiceDeletion>

  listAiMemory?(signal?: AbortSignal): Promise<LearnerMemoryListing>

  createAiMemoryFact?(
    input: CreateLearnerAiMemoryFactRequest,
    commandId: string,
    signal?: AbortSignal,
  ): Promise<LearnerMemoryFactResult>

  updateAiMemoryFact?(
    factId: string,
    input: UpdateLearnerAiMemoryFactRequest,
    commandId: string,
    signal?: AbortSignal,
  ): Promise<LearnerMemoryFactResult>

  deleteAiMemoryFact?(
    factId: string,
    commandId: string,
    signal?: AbortSignal,
  ): Promise<LearnerMemoryFactResult>
}

export class ConversationJourneyUnavailableError extends Error {
  readonly status?: number
  readonly messageKey?: string

  constructor(status?: number, messageKey?: string) {
    super('The deterministic Friend prototype is unavailable.')
    this.name = 'ConversationJourneyUnavailableError'
    this.status = status
    this.messageKey = messageKey
  }
}
