import type {
  MessageKey,
  ResolutionPolicy,
  SupportedLocale,
} from '@global-english/localization'
import { LocalizedMessage } from '../localization/LocalizedMessage'
import { resolveInterfaceMessage } from '../localization/interfaceLocalization'
import type { LearnerEntitlement } from './LearnerSubscriptionApi'

export const LIVE_AI_MINUTES_CHIP_ID = 'shell-live-ai-minutes'

export interface LiveAiMinutesChipProps {
  readonly context?: 'global' | 'speak'
  readonly entitlement: LearnerEntitlement | null
  readonly locale: SupportedLocale
  readonly localizationPolicy: ResolutionPolicy
  readonly onOpenAccount: () => void
  readonly openerId?: string
}

export function LiveAiMinutesChip({
  context = 'global',
  entitlement,
  locale,
  localizationPolicy,
  onOpenAccount,
  openerId = LIVE_AI_MINUTES_CHIP_ID,
}: LiveAiMinutesChipProps) {
  const message = (key: MessageKey, parameters?: Record<string, string>) =>
    resolveInterfaceMessage(key, locale, localizationPolicy, parameters)

  if (entitlement === null || entitlement.applies === false) {
    return null
  }

  const seconds = entitlement.live_ai_seconds_remaining
  if (seconds === null) {
    return null
  }

  const openAccountLabel = message('subscription.chip_open_account').text

  if (entitlement.is_demo && context === 'global') {
    return null
  }

  if (entitlement.is_demo) {
    const resolution =
      seconds <= 10
        ? message('subscription.chip_exhausted')
        : message('subscription.chip_seconds', {
            seconds: String(seconds),
          })

    return (
      <button
        aria-label={openAccountLabel}
        className="shell-live-ai-chip"
        id={openerId}
        onClick={() => onOpenAccount()}
        title={openAccountLabel}
        type="button"
      >
        <LocalizedMessage resolution={resolution} />
      </button>
    )
  }

  return (
    <button
      aria-label={openAccountLabel}
      className="shell-live-ai-chip"
      id={openerId}
      onClick={() => onOpenAccount()}
      title={openAccountLabel}
      type="button"
    >
      <span className="shell-live-ai-chip__full">
        <LocalizedMessage
          resolution={message('subscription.chip_minutes', {
            minutes: String(Math.floor(seconds / 60)),
          })}
        />
      </span>
      <span aria-hidden="true" className="shell-live-ai-chip__compact">
        AI · {Math.floor(seconds / 60)} min
      </span>
    </button>
  )
}
