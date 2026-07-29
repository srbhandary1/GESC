import { afterEach, describe, expect, it, vi } from 'vitest'
import { createRootErrorOptions } from './rootErrorReporting'

const sensitiveFixture = 'learner transcript must remain private'

afterEach(() => {
  vi.restoreAllMocks()
})

describe('React root error reporting', () => {
  it('replaces every raw React error with an allowlisted category', () => {
    const report = vi.fn()
    const options = createRootErrorOptions(report)
    const error = new Error(sensitiveFixture)

    options.onCaughtError?.(error, {})
    options.onRecoverableError?.(error, {})
    options.onUncaughtError?.(error, {})

    expect(report.mock.calls).toEqual([
      [{ category: 'react_caught_error' }],
      [{ category: 'react_recoverable_error' }],
      [{ category: 'react_uncaught_error' }],
    ])
    expect(JSON.stringify(report.mock.calls)).not.toContain(sensitiveFixture)
  })

  it('keeps the default console event free of raw errors', () => {
    const consoleError = vi
      .spyOn(console, 'error')
      .mockImplementation(() => undefined)
    const options = createRootErrorOptions()

    options.onCaughtError?.(new Error(sensitiveFixture), {})

    expect(consoleError).toHaveBeenCalledWith({
      category: 'react_caught_error',
    })
    expect(JSON.stringify(consoleError.mock.calls)).not.toContain(
      sensitiveFixture,
    )
  })
})
