import {
  INTERFACE_LOCALES,
  resolveMessage,
  type MessageKey,
  type SupportedLocale,
} from '@global-english/localization'

function escapeRegex(value: string): string {
  return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

function resolveLocalizedText(
  key: MessageKey,
  locale: SupportedLocale,
): string {
  return resolveMessage(key, {
    policy: 'development-preview',
    requestedLocale: locale,
  }).text
}

export function message(
  key: MessageKey,
  locale: SupportedLocale = 'kn-IN',
): string {
  return resolveLocalizedText(key, locale)
}

export function localizedPattern(key: MessageKey): RegExp {
  const labels = INTERFACE_LOCALES.map((locale) =>
    resolveLocalizedText(key, locale),
  )
  return new RegExp(labels.map(escapeRegex).join('|'), 'i')
}

export function localizedPatterns(keys: readonly MessageKey[]): RegExp {
  const labels = keys.flatMap((key) =>
    INTERFACE_LOCALES.map((locale) => resolveLocalizedText(key, locale)),
  )
  return new RegExp(labels.map(escapeRegex).join('|'), 'i')
}
