import {
  resolveMessage,
  type FallbackEvent,
  type MessageKey,
  type MessageParameters,
  type ResolvedMessage,
  type ResolutionPolicy,
  type SupportedLocale,
} from '@global-english/localization'
import { useEffect, useRef } from 'react'

export interface InterfaceLocalizationEnvironment {
  readonly development: boolean
  readonly previewRequested: boolean
  readonly test: boolean
}

export interface InterfaceMessageResolution extends ResolvedMessage {
  readonly fallbackEvent: FallbackEvent | null
}

export type InterfaceFallbackReporter = (event: FallbackEvent) => void

export function selectInterfaceResolutionPolicy(
  environment: InterfaceLocalizationEnvironment,
): ResolutionPolicy {
  return environment.development ||
    environment.test ||
    environment.previewRequested
    ? 'development-preview'
    : 'approved-only'
}

export const INTERFACE_RESOLUTION_POLICY =
  selectInterfaceResolutionPolicy({
    development: import.meta.env.DEV,
    previewRequested:
      import.meta.env.VITE_UI_TRANSLATION_PREVIEW === 'true',
    test: import.meta.env.MODE === 'test',
  })

export function resolveInterfaceMessage(
  key: MessageKey,
  locale: SupportedLocale,
  policy: ResolutionPolicy = INTERFACE_RESOLUTION_POLICY,
  parameters: MessageParameters = {},
): InterfaceMessageResolution {
  let fallbackEvent: FallbackEvent | null = null
  const resolution = resolveMessage(key, {
    onFallback: (event) => {
      fallbackEvent = event
    },
    parameters,
    policy,
    requestedLocale: locale,
  })

  return Object.freeze({
    ...resolution,
    fallbackEvent,
  })
}

export const reportInterfaceFallback: InterfaceFallbackReporter = (
  event,
) => {
  console.warn('localization.fallback', event)
}

export function useInterfaceFallbackReporting(
  resolutions: readonly InterfaceMessageResolution[],
  reporter: InterfaceFallbackReporter = reportInterfaceFallback,
): void {
  const events = new Map<string, FallbackEvent>()

  for (const { fallbackEvent } of resolutions) {
    if (fallbackEvent !== null) {
      events.set(fallbackEventSignature(fallbackEvent), fallbackEvent)
    }
  }

  const serializedEvents = JSON.stringify(
    [...events.entries()]
      .sort(([left], [right]) => left.localeCompare(right))
      .map(([, event]) => event),
  )
  const lastReportedSignature = useRef<string | null>(null)

  useEffect(() => {
    if (serializedEvents === '[]') {
      lastReportedSignature.current = null
      return
    }

    if (lastReportedSignature.current === serializedEvents) {
      return
    }

    const currentEvents = JSON.parse(serializedEvents) as FallbackEvent[]
    for (const event of currentEvents) {
      reporter(event)
    }

    lastReportedSignature.current = serializedEvents
  }, [reporter, serializedEvents])
}

function fallbackEventSignature(event: FallbackEvent): string {
  return [
    event.type,
    event.messageKey,
    event.requestedLocale,
    event.resolvedLocale,
    event.policy,
    event.reason,
    event.requestedRevision ?? '',
  ].join('|')
}
