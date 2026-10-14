import type { LearnerProviderUsage } from '@global-english/contracts'
import type { ResolutionPolicy, SupportedLocale } from '@global-english/localization'
import { LocalizedMessage } from '../localization/LocalizedMessage'
import { resolveInterfaceMessage } from '../localization/interfaceLocalization'

export function ProviderUsageSummary({ usage, locale, localizationPolicy }: {
  readonly usage?: LearnerProviderUsage
  readonly locale: SupportedLocale
  readonly localizationPolicy: ResolutionPolicy
}) {
  if (!usage) return null
  return <div className="provider-usage-summary">
    <p><LocalizedMessage resolution={resolveInterfaceMessage('subscription.audio_meter', locale, localizationPolicy)} /></p>
    <p><LocalizedMessage resolution={resolveInterfaceMessage('subscription.provider_tokens', locale, localizationPolicy,
      { input: usage.input_tokens, output: usage.output_tokens })} /></p>
    {usage.unknown_usage_attempts > 0 ? <p><LocalizedMessage resolution={resolveInterfaceMessage('subscription.provider_usage_partial', locale, localizationPolicy)} /></p> : null}
  </div>
}
