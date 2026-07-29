import { describe, expect, it, vi } from 'vitest'
import {
  reportInterfaceFallback,
  resolveInterfaceMessage,
  selectInterfaceResolutionPolicy,
} from './interfaceLocalization'

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

  it('falls back to English under the strict policy with safe evidence', () => {
    const resolution = resolveInterfaceMessage(
      'page.home.title',
      'kn-IN',
      'approved-only',
    )

    expect(resolution).toMatchObject({
      fallbackEvent: {
        messageKey: 'page.home.title',
        reason: 'unapproved-translation',
        requestedLocale: 'kn-IN',
        resolvedLocale: 'en-IN',
        type: 'localization.fallback',
      },
      isPreview: false,
      resolvedLocale: 'en-IN',
      text: 'Home',
      usedFallback: true,
    })
  })

  it('reports only the privacy-safe structured fallback event', () => {
    const consoleWarning = vi
      .spyOn(console, 'warn')
      .mockImplementation(() => undefined)
    const resolution = resolveInterfaceMessage(
      'page.home.title',
      'kn-IN',
      'approved-only',
    )

    expect(resolution.fallbackEvent).not.toBeNull()
    reportInterfaceFallback(resolution.fallbackEvent!)

    expect(consoleWarning).toHaveBeenCalledWith(
      'localization.fallback',
      resolution.fallbackEvent,
    )
    expect(JSON.stringify(consoleWarning.mock.calls)).not.toContain(
      resolution.text,
    )

    consoleWarning.mockRestore()
  })
})
