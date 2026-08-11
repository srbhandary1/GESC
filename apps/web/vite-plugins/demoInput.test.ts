import { describe, expect, it } from 'vitest'
import { containsDisallowedControlCharacter } from './demoInput.ts'

describe('demo input validation', () => {
  it('rejects non-text control characters', () => {
    expect(containsDisallowedControlCharacter('hello\u0000world')).toBe(true)
    expect(containsDisallowedControlCharacter('hello\u001fworld')).toBe(true)
    expect(containsDisallowedControlCharacter('hello\u007fworld')).toBe(true)
  })

  it('allows ordinary multilingual text and text whitespace', () => {
    expect(
      containsDisallowedControlCharacter('Hello\tನಮಸ್ಕಾರ\nவணக்கம்\rनमस्ते'),
    ).toBe(false)
  })
})
