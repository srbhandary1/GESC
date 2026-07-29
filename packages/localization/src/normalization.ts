export const ZERO_WIDTH_NON_JOINER = '\u200C'
export const ZERO_WIDTH_JOINER = '\u200D'

/**
 * Canonicalize localized text without removing language-significant format
 * characters. String#normalize composes Unicode sequences but preserves both
 * the zero-width joiner and zero-width non-joiner.
 */
export function normalizeLocalizedText(value: string): string {
  return value.normalize('NFC')
}

export function isNfcNormalized(value: string): boolean {
  return value === normalizeLocalizedText(value)
}
