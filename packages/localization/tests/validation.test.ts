import { describe, expect, it } from 'vitest'

import {
  cloneCanonicalValidationInput,
  hashCatalogue,
  validateCanonicalCatalogues,
  validateCatalogues,
} from '../src/validation.ts'

function mutableCatalogues(
  input: ReturnType<typeof cloneCanonicalValidationInput>,
): Record<string, Record<string, unknown>> {
  return input.catalogues as Record<string, Record<string, unknown>>
}

function mutableGovernance(
  input: ReturnType<typeof cloneCanonicalValidationInput>,
): Record<string, unknown> {
  return input.governance as Record<string, unknown>
}

function governanceEntries(
  input: ReturnType<typeof cloneCanonicalValidationInput>,
): Record<string, Record<string, unknown>> {
  const governance = mutableGovernance(input)

  return governance.catalogues as Record<string, Record<string, unknown>>
}

function issueCodes(
  result: ReturnType<typeof validateCatalogues>,
): readonly string[] {
  return result.issues.map(({ code }) => code)
}

describe('UI catalogue validation', () => {
  it('accepts the canonical catalogues while reporting pending review honestly', () => {
    const result = validateCanonicalCatalogues()

    expect(result.valid).toBe(true)
    expect(result.errors).toHaveLength(0)
    expect(result.warnings).toEqual([
      expect.objectContaining({
        code: 'pending-independent-review',
        locale: 'kn-IN',
      }),
    ])
  })

  it('rejects unsupported catalogue and governance locales', () => {
    const input = cloneCanonicalValidationInput()
    mutableCatalogues(input)['fr-FR'] = { 'app.name': 'Global English' }
    governanceEntries(input)['fr-FR'] = {
      locale: 'fr-FR',
    }

    const result = validateCatalogues(input)

    expect(issueCodes(result)).toContain('unsupported-locale')
    expect(issueCodes(result)).toContain('unsupported-governance-locale')
  })

  it('rejects missing and unknown keys', () => {
    const input = cloneCanonicalValidationInput()
    delete mutableCatalogues(input)['kn-IN']?.['nav.home']
    mutableCatalogues(input)['kn-IN']!['unknown.future_key'] = 'ಅಪರಿಚಿತ'

    const result = validateCatalogues(input)

    expect(issueCodes(result)).toContain('missing-key')
    expect(issueCodes(result)).toContain('unknown-key')
  })

  it('rejects placeholder drift and malformed placeholders', () => {
    const input = cloneCanonicalValidationInput()
    mutableCatalogues(input)['en-IN']!['app.name'] = 'Hello, {learner}'
    mutableCatalogues(input)['kn-IN']!['app.name'] = 'ನಮಸ್ಕಾರ, {name}'
    mutableCatalogues(input)['kn-IN']!['app.description'] = 'ತಪ್ಪಾದ {placeholder'

    const result = validateCatalogues(input)

    expect(issueCodes(result)).toContain('placeholder-mismatch')
    expect(issueCodes(result)).toContain('malformed-placeholder')
  })

  it('rejects text that is not NFC-normalized', () => {
    const input = cloneCanonicalValidationInput()
    mutableCatalogues(input)['en-IN']!['app.name'] = 'Cafe\u0301'

    const result = validateCatalogues(input)

    expect(issueCodes(result)).toContain('not-nfc')
  })

  it('rejects empty and whitespace-only messages', () => {
    const input = cloneCanonicalValidationInput()
    mutableCatalogues(input)['en-IN']!['app.name'] = ''
    mutableCatalogues(input)['kn-IN']!['nav.home'] = ' \n\t '

    const result = validateCatalogues(input)

    expect(
      result.issues.filter(({ code }) => code === 'blank-message'),
    ).toEqual([
      expect.objectContaining({ key: 'nav.home', locale: 'kn-IN' }),
      expect.objectContaining({ key: 'app.name', locale: 'en-IN' }),
    ])
  })

  it('detects catalogue, source hash, and source revision drift', () => {
    const input = cloneCanonicalValidationInput()
    mutableCatalogues(input)['kn-IN']!['nav.home'] = 'ಬದಲಾದ ಮುಖಪುಟ'
    governanceEntries(input)['kn-IN']!.sourceHash =
      `sha256:${'0'.repeat(64)}`
    governanceEntries(input)['kn-IN']!.sourceRevision =
      'ui-en-IN-2026-07-29.1'

    const result = validateCatalogues(input)

    expect(issueCodes(result)).toContain('catalogue-hash-mismatch')
    expect(issueCodes(result)).toContain('source-hash-mismatch')
    expect(issueCodes(result)).toContain('source-revision-mismatch')
  })

  it('rejects a fabricated independent approval claim', () => {
    const input = cloneCanonicalValidationInput()
    const kannada = governanceEntries(input)['kn-IN']!
    kannada.reviewStatus = 'approved'
    kannada.reviewedBy = null
    kannada.approvedAt = null
    kannada.independentReview = false

    const result = validateCatalogues(input)

    expect(issueCodes(result)).toContain('dishonest-review-status')
  })

  it('rejects a fallback catalogue that does not retain source status', () => {
    const input = cloneCanonicalValidationInput()
    const fallback = governanceEntries(input)['en-IN']!
    fallback.reviewStatus = 'stale'

    const result = validateCatalogues(input)

    expect(issueCodes(result)).toContain(
      'invalid-fallback-review-status',
    )
  })

  it('accepts honest independent approval metadata', () => {
    const input = cloneCanonicalValidationInput()
    const kannada = governanceEntries(input)['kn-IN']!
    kannada.reviewStatus = 'approved'
    kannada.translatedBy = 'translator-reference'
    kannada.reviewedBy = 'independent-reviewer-reference'
    kannada.approvedAt = '2026-07-30T12:00:00+05:30'
    kannada.independentReview = true

    const result = validateCatalogues(input)

    expect(result.valid).toBe(true)
    expect(result.warnings).toHaveLength(0)
  })

  it('uses deterministic canonical JSON hashes', () => {
    expect(hashCatalogue({ second: '2', first: '1' })).toBe(
      hashCatalogue({ first: '1', second: '2' }),
    )
  })
})
