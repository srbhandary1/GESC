import type { HTMLAttributes } from 'react'

export type SupportedLanguage =
  | 'en-IN'
  | 'hi-IN'
  | 'kn-IN'
  | 'ml-IN'
  | 'ta-IN'
  | 'te-IN'

export interface MixedLanguageSegment {
  locale: SupportedLanguage
  text: string
}

export type TextNormalizationMode = 'nfc' | 'caller'

export type SegmentTextNormalizer = (
  text: string,
  locale: SupportedLanguage,
) => string

export interface MixedLanguageTextProps
  extends Omit<HTMLAttributes<HTMLSpanElement>, 'children'> {
  segments: readonly MixedLanguageSegment[]
  normalization?: TextNormalizationMode
  normalizeText?: SegmentTextNormalizer
}

export function normalizeUnicodeNfc(text: string): string {
  return text.normalize('NFC')
}

/**
 * Renders real, ordered bilingual text. Whitespace and punctuation remain the
 * caller's responsibility so message-catalogue output is never rewritten.
 */
export function MixedLanguageText({
  segments,
  normalization = 'nfc',
  normalizeText,
  ...spanProps
}: MixedLanguageTextProps) {
  const normalizer: SegmentTextNormalizer =
    normalizeText ??
    (normalization === 'nfc' ? normalizeUnicodeNfc : (text) => text)

  return (
    <span {...spanProps}>
      {segments.map((segment, index) => (
        <span
          key={`${index}:${segment.locale}`}
          lang={segment.locale}
        >
          {normalizer(segment.text, segment.locale)}
        </span>
      ))}
    </span>
  )
}
