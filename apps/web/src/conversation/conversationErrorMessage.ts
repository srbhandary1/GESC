import type { MessageKey } from '@global-english/localization'
import { ConversationJourneyUnavailableError } from './ConversationJourneyApi'

const ENTITLEMENT_TURN_KEYS = new Set<string>([
  'entitlement.ai_exhausted',
  'entitlement.demo_ai_exhausted',
  'entitlement.expired',
  'entitlement.free_speech_locked',
  'entitlement.session_locked',
])

export function conversationErrorMessageKey(
  error: unknown,
  fallback: MessageKey,
): MessageKey {
  if (
    !(error instanceof ConversationJourneyUnavailableError) ||
    error.messageKey === undefined ||
    !ENTITLEMENT_TURN_KEYS.has(error.messageKey)
  ) {
    return fallback
  }

  return error.messageKey as MessageKey
}
