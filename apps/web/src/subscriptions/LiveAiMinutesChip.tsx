import type {
  MessageKey,
  ResolutionPolicy,
  SupportedLocale,
} from '@global-english/localization'
import { LocalizedMessage } from '../localization/LocalizedMessage'
import { resolveInterfaceMessage } from '../localization/interfaceLocalization'
import type { LearnerEntitlement } from './LearnerSubscriptionApi'

export interface LiveAiMinutesChipProps {
  readonly context?: 'global' | 'speak'
  readonly entitlement: LearnerEntitlement | null
  readonly locale: SupportedLocale
  readonly localizationPolicy: ResolutionPolicy
  readonly onOpenAccount: () => void
}

export function LiveAiMinutesChip({
  context = 'global',
  entitlement,
  locale,
  localizationPolicy,
  onOpenAccount,
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
        onClick={onOpenAccount}
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
      onClick={onOpenAccount}
      title={openAccountLabel}
      type="button"
    >
      <LocalizedMessage
        resolution={message('subscription.chip_minutes', {
          minutes: String(Math.floor(seconds / 60)),
        })}
      />
    </button>
  )
}
