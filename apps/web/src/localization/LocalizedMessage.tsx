import { MixedLanguageText } from '@global-english/design-system'
import type { ReactNode } from 'react'
import {
  useInterfaceFallbackReporting,
  type InterfaceMessageResolution,
} from './interfaceLocalization'

export interface LocalizedMessageProps {
  readonly resolution: InterfaceMessageResolution
}

export function LocalizedMessage({
  resolution,
}: LocalizedMessageProps): ReactNode {
  useInterfaceFallbackReporting([resolution])

  return (
    <MixedLanguageText
      data-fallback-locale={
        resolution.usedFallback ? resolution.resolvedLocale : undefined
      }
      data-message-review-status={resolution.reviewStatus}
      segments={[
        {
          locale: resolution.resolvedLocale,
          text: resolution.text,
        },
      ]}
    />
  )
}
