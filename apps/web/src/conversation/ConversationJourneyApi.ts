import type {
  ConversationSession,
  ConversationTurn,
  ConversationTurnEvent,
  CreateConversationTurnRequest,
  StartConversationSessionRequest,
} from '@global-english/contracts'

export type FriendSession = ConversationSession
export type FriendTurn = ConversationTurn
export type FriendTurnEvent = ConversationTurnEvent
export type StartFriendSessionInput = StartConversationSessionRequest
export type CreateFriendTurnInput = CreateConversationTurnRequest

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
}

export class ConversationJourneyUnavailableError extends Error {
  readonly status?: number

  constructor(status?: number) {
    super('The deterministic Friend prototype is unavailable.')
    this.name = 'ConversationJourneyUnavailableError'
    this.status = status
  }
}
