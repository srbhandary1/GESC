import { describe, expect, it, vi } from 'vitest'

import {
  catalogueGovernance,
  catalogues,
  DEFAULT_LOCALE,
  FALLBACK_LOCALE,
  knINCatalogue,
  resolveMessage,
  SUPPORTED_LOCALES,
} from '../src/index.ts'
import type {
  CatalogueCollection,
  FallbackEvent,
  GovernanceManifest,
  SupportedLocale,
} from '../src/index.ts'

function mutableGovernance(): Record<
  SupportedLocale,
  Record<string, unknown>
> {
  const manifest = structuredClone(catalogueGovernance)

  return manifest.catalogues as unknown as Record<
    SupportedLocale,
    Record<string, unknown>
  >
}

function manifestWith(
  entries: Record<SupportedLocale, Record<string, unknown>>,
): GovernanceManifest {
  return {
    ...structuredClone(catalogueGovernance),
    catalogues: entries,
  } as unknown as GovernanceManifest
}

describe('message resolution', () => {
  it('declares exact locale defaults', () => {
    expect(DEFAULT_LOCALE).toBe('kn-IN')
    expect(FALLBACK_LOCALE).toBe('en-IN')
    expect(SUPPORTED_LOCALES).toEqual([
      'kn-IN',
      'ml-IN',
      'ta-IN',
      'te-IN',
      'hi-IN',
      'en-IN',
    ])
  })

  it('uses approved-only resolution by default', () => {
    const onFallback = vi.fn<(event: FallbackEvent) => void>()

    const result = resolveMessage('nav.home', { onFallback })

    expect(result).toEqual({
      text: 'Home',
      requestedLocale: 'kn-IN',
      resolvedLocale: 'en-IN',
      usedFallback: true,
      reason: 'unapproved-translation',
      reviewStatus: 'source',
      revision: 'ui-en-IN-2026-08-11.7',
      isPreview: false,
    })
    expect(onFallback).toHaveBeenCalledOnce()
  })

  it.each(['ml-IN', 'ta-IN', 'te-IN', 'hi-IN'] as const)(
    'returns the pending %s catalogue only under explicit development preview',
    (locale) => {
      const onFallback = vi.fn<(event: FallbackEvent) => void>()

      const result = resolveMessage('nav.home', {
        policy: 'development-preview',
        requestedLocale: locale,
        onFallback,
      })

      expect(result.text).toBe(catalogues[locale]['nav.home'])
      expect(result.requestedLocale).toBe(locale)
      expect(result.resolvedLocale).toBe(locale)
      expect(result.usedFallback).toBe(false)
      expect(result.reason).toBe('development-preview')
      expect(result.reviewStatus).toBe('pending-independent-review')
      expect(result.isPreview).toBe(true)
      expect(onFallback).not.toHaveBeenCalled()
    },
  )

  it.each(['ml-IN', 'ta-IN', 'te-IN', 'hi-IN'] as const)(
    'falls back from pending %s under the production policy',
    (locale) => {
      const result = resolveMessage('nav.home', {
        policy: 'approved-only',
        requestedLocale: locale,
      })

      expect(result.text).toBe('Home')
      expect(result.requestedLocale).toBe(locale)
      expect(result.resolvedLocale).toBe(FALLBACK_LOCALE)
      expect(result.reason).toBe('unapproved-translation')
      expect(result.usedFallback).toBe(true)
      expect(result.isPreview).toBe(false)
    },
  )

  it('returns pending Kannada only under explicit development preview', () => {
    const onFallback = vi.fn<(event: FallbackEvent) => void>()

    const result = resolveMessage('nav.home', {
      policy: 'development-preview',
      onFallback,
    })

    expect(result.text).toBe(knINCatalogue['nav.home'])
    expect(result.requestedLocale).toBe('kn-IN')
    expect(result.resolvedLocale).toBe('kn-IN')
    expect(result.usedFallback).toBe(false)
    expect(result.reason).toBe('development-preview')
    expect(result.reviewStatus).toBe('pending-independent-review')
    expect(result.isPreview).toBe(true)
    expect(onFallback).not.toHaveBeenCalled()
  })

  it('falls back when a preview catalogue is incomplete', () => {
    const incompleteKannada: Record<string, string> = {
      ...catalogues['kn-IN'],
    }
    delete incompleteKannada['nav.learn']

    const result = resolveMessage('nav.learn', {
      policy: 'development-preview',
      catalogues: {
        ...catalogues,
        'kn-IN': incompleteKannada,
      } as CatalogueCollection,
    })

    expect(result.text).toBe('Learn')
    expect(result.resolvedLocale).toBe('en-IN')
    expect(result.usedFallback).toBe(true)
    expect(result.reason).toBe('missing-message')
    expect(result.isPreview).toBe(false)
  })

  it('falls back from stale Kannada even in development preview', () => {
    const entries = mutableGovernance()
    entries['kn-IN'].reviewStatus = 'stale'
    entries['kn-IN'].sourceRevision = 'ui-en-IN-2026-07-29.1'
    entries['kn-IN'].independentReview = false

    const result = resolveMessage('nav.practice', {
      policy: 'development-preview',
      governance: manifestWith(entries),
    })

    expect(result.text).toBe('Practice')
    expect(result.reason).toBe('stale-translation')
    expect(result.usedFallback).toBe(true)
  })

  it('falls back from rejected Kannada as unapproved', () => {
    const entries = mutableGovernance()
    entries['kn-IN'].reviewStatus = 'rejected'

    const result = resolveMessage('nav.progress', {
      policy: 'development-preview',
      governance: manifestWith(entries),
    })

    expect(result.text).toBe('Progress')
    expect(result.reason).toBe('unapproved-translation')
    expect(result.usedFallback).toBe(true)
  })

  it('does not trust a fabricated approval claim at runtime', () => {
    const entries = mutableGovernance()
    entries['kn-IN'].reviewStatus = 'approved'
    entries['kn-IN'].reviewedBy = null
    entries['kn-IN'].approvedAt = null
    entries['kn-IN'].independentReview = false

    const result = resolveMessage('nav.home', {
      governance: manifestWith(entries),
    })

    expect(result.text).toBe('Home')
    expect(result.reason).toBe('unapproved-translation')
    expect(result.usedFallback).toBe(true)
  })

  it('uses an honestly approved current Kannada revision without preview', () => {
    const entries = mutableGovernance()
    entries['kn-IN'].reviewStatus = 'approved'
    entries['kn-IN'].translatedBy = 'translator-reference'
    entries['kn-IN'].reviewedBy = 'independent-reviewer-reference'
    entries['kn-IN'].approvedAt = '2026-07-30T12:00:00+05:30'
    entries['kn-IN'].independentReview = true

    const result = resolveMessage('nav.home', {
      governance: manifestWith(entries),
    })

    expect(result.text).toBe(knINCatalogue['nav.home'])
    expect(result.reason).toBe('requested-locale')
    expect(result.reviewStatus).toBe('approved')
    expect(result.usedFallback).toBe(false)
    expect(result.isPreview).toBe(false)
  })

  it('falls back deterministically for unsupported locales', () => {
    const result = resolveMessage('app.name', {
      requestedLocale: 'fr-FR',
    })

    expect(result.text).toBe('Global spoken English Course')
    expect(result.requestedLocale).toBe('fr-FR')
    expect(result.resolvedLocale).toBe('en-IN')
    expect(result.reason).toBe('unsupported-locale')
  })

  it('emits fallback events without message content or parameters', () => {
    let emittedEvent: FallbackEvent | undefined
    const secretParameter = 'sensitive-learner-value'
    const syntheticCatalogues = {
      ...catalogues,
      'en-IN': {
        ...catalogues['en-IN'],
        'app.description': 'Welcome, {learner}',
      },
      'kn-IN': {
        ...catalogues['kn-IN'],
        'app.description': 'ಸ್ವಾಗತ, {learner}',
      },
    } as CatalogueCollection

    const result = resolveMessage('app.description', {
      parameters: { learner: secretParameter },
      catalogues: syntheticCatalogues,
      onFallback: (event) => {
        emittedEvent = event
      },
    })

    expect(result.text).toBe(`Welcome, ${secretParameter}`)
    expect(emittedEvent).toBeDefined()
    expect(emittedEvent).not.toHaveProperty('text')
    expect(emittedEvent).not.toHaveProperty('message')
    expect(emittedEvent).not.toHaveProperty('parameters')
    expect(JSON.stringify(emittedEvent)).not.toContain(secretParameter)
    expect(JSON.stringify(emittedEvent)).not.toContain('Welcome')
  })
})
