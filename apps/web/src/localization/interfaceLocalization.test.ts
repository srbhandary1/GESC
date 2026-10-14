import {
  catalogueGovernance,
  resolveMessage,
  type GovernanceManifest,
} from '@global-english/localization'
import { describe, expect, it, vi } from 'vitest'
import {
  reportInterfaceFallback,
  resolveInterfaceMessage,
  selectInterfaceResolutionPolicy,
} from './interfaceLocalization'

function pendingHindiGovernance(): GovernanceManifest {
  const governance = structuredClone(
    catalogueGovernance,
  ) as unknown as GovernanceManifest
  const hindi = governance.catalogues['hi-IN']
  if (hindi === undefined) {
    throw new Error('Missing hi-IN governance metadata.')
  }

  return {
    ...governance,
    catalogues: {
      ...governance.catalogues,
      'hi-IN': {
        ...hindi,
        reviewStatus: 'pending-independent-review',
        reviewedBy: null,
        approvedAt: null,
        independentReview: false,
      },
    },
  }
}

describe('interface localization policy', () => {
  it('uses approved translations only for an ordinary production build', () => {
    expect(
      selectInterfaceResolutionPolicy({
        development: false,
        previewRequested: false,
        test: false,
      }),
    ).toBe('approved-only')
  })

  it('requires an explicit development, test or preview context for drafts', () => {
    expect(
      selectInterfaceResolutionPolicy({
        development: true,
        previewRequested: false,
        test: false,
      }),
    ).toBe('development-preview')
    expect(
      selectInterfaceResolutionPolicy({
        development: false,
        previewRequested: true,
        test: false,
      }),
    ).toBe('development-preview')
    expect(
      selectInterfaceResolutionPolicy({
        development: false,
        previewRequested: false,
        test: true,
      }),
    ).toBe('development-preview')
  })

  it('serves signed-off Kannada under the strict policy', () => {
    const resolution = resolveInterfaceMessage(
      'nav.home',
      'kn-IN',
      'approved-only',
    )

    expect(resolution).toMatchObject({
      isPreview: false,
      resolvedLocale: 'kn-IN',
      reviewStatus: 'approved',
      text: 'ಮುಖಪುಟ',
      usedFallback: false,
    })
    expect(resolution.fallbackEvent).toBeNull()
  })

  it('falls back from pending Hindi under the strict policy', () => {
    const resolution = resolveInterfaceMessage(
      'nav.home',
      'hi-IN',
      'approved-only',
    )

    expect(resolution).toMatchObject({
      isPreview: false,
      resolvedLocale: 'en-IN',
      text: 'Home',
      usedFallback: true,
    })
    expect(resolution.fallbackEvent).not.toBeNull()
  })

  it('falls back to English for unapproved locales under the strict policy', () => {
    let fallbackEvent = null
    const resolution = resolveMessage('nav.home', {
      governance: pendingHindiGovernance(),
      onFallback: (event) => {
        fallbackEvent = event
      },
      policy: 'approved-only',
      requestedLocale: 'hi-IN',
    })

    expect(resolution).toMatchObject({
      isPreview: false,
      resolvedLocale: 'en-IN',
      text: 'Home',
      usedFallback: true,
    })
    expect(fallbackEvent).toMatchObject({
      messageKey: 'nav.home',
      reason: 'unapproved-translation',
      requestedLocale: 'hi-IN',
      resolvedLocale: 'en-IN',
      type: 'localization.fallback',
    })
  })

  it('reports only the privacy-safe structured fallback event', () => {
    const consoleWarning = vi
      .spyOn(console, 'warn')
      .mockImplementation(() => undefined)
    const resolution = resolveMessage('nav.home', {
      governance: pendingHindiGovernance(),
      policy: 'approved-only',
      requestedLocale: 'hi-IN',
    })
    let fallbackEvent = null
    resolveMessage('nav.home', {
      governance: pendingHindiGovernance(),
      onFallback: (event) => {
        fallbackEvent = event
      },
      policy: 'approved-only',
      requestedLocale: 'hi-IN',
    })

    expect(fallbackEvent).not.toBeNull()
    reportInterfaceFallback(fallbackEvent!)

    expect(consoleWarning).toHaveBeenCalledWith(
      'localization.fallback',
      fallbackEvent,
    )
    expect(JSON.stringify(consoleWarning.mock.calls)).not.toContain(
      resolution.text,
    )

    consoleWarning.mockRestore()
  })
})
