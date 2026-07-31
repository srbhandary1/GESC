import type {
  ConversationSession,
  ConversationTurn,
  ConversationTurnEvent,
  CreateConversationTurnRequest,
  GrantVoiceConsentRequest,
  ReviewVoiceTranscriptRequest,
  StartConversationSessionRequest,
  SupportedLocale as ApiSupportedLocale,
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
export type VoiceConsents = VoiceConsentListing
export type VoiceConsent = VoiceConsentEvent
export type SecureVoiceRecording = VoiceRecording
export type SecureVoiceDeletion = VoiceDeletion

export interface ConversationJourneyApi {
  startSession(
    input: StartFriendSessionInput,
    commandId: string,
    signal?: AbortSignal,
  ): Promise<FriendSession>

  createTurn(
    sessionId: string,
    input: CreateFriendTurnInput,
    commandId: string,
    signal?: AbortSignal,
  ): Promise<FriendTurn>

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
}

export class ConversationJourneyUnavailableError extends Error {
  readonly status?: number

  constructor(status?: number) {
    super('The deterministic Friend prototype is unavailable.')
    this.name = 'ConversationJourneyUnavailableError'
    this.status = status
  }
}
