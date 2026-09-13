import type { ConversationJourneyApi } from './ConversationJourneyApi'

export async function preconnectRealtimeSpeechAfterConsent(options: {
  readonly api: ConversationJourneyApi
  readonly sessionId: string
  readonly commandId: string
  readonly signal?: AbortSignal
}): Promise<'recorded_recovery' | 'skipped'> {
  if (options.api.requestRealtimeSpeechTicket === undefined) {
    return 'skipped'
  }

  try {
    const ticket = await options.api.requestRealtimeSpeechTicket(
      options.sessionId,
      options.commandId,
      options.signal,
    )
    if (ticket.always_on || ticket.full_duplex) {
      return 'skipped'
    }

    return ticket.transport === 'recorded_recovery'
      ? 'recorded_recovery'
      : 'skipped'
  } catch {
    return 'skipped'
  }
}
