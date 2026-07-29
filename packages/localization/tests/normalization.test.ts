import { describe, expect, it } from 'vitest'

import {
  isNfcNormalized,
  normalizeLocalizedText,
  ZERO_WIDTH_JOINER,
  ZERO_WIDTH_NON_JOINER,
} from '../src/index.ts'

describe('localized text normalization', () => {
  it('composes decomposed text to NFC', () => {
    const decomposed = 'Cafe\u0301'

    expect(isNfcNormalized(decomposed)).toBe(false)
    expect(normalizeLocalizedText(decomposed)).toBe('Café')
    expect(isNfcNormalized(normalizeLocalizedText(decomposed))).toBe(true)
  })

  it('preserves zero-width joiners and non-joiners', () => {
    const text = `ಕ${ZERO_WIDTH_JOINER}ಷ ಕ${ZERO_WIDTH_NON_JOINER}ಷ`
    const normalized = normalizeLocalizedText(text)

    expect(normalized).toContain(ZERO_WIDTH_JOINER)
    expect(normalized).toContain(ZERO_WIDTH_NON_JOINER)
    expect(Array.from(normalized).filter((character) => {
      return character === ZERO_WIDTH_JOINER
    })).toHaveLength(1)
    expect(Array.from(normalized).filter((character) => {
      return character === ZERO_WIDTH_NON_JOINER
    })).toHaveLength(1)
  })
})
