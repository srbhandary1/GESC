import {
  DEFAULT_LOCALE,
  resolveMessage,
  type MessageKey,
  type ResolutionPolicy,
} from '@global-english/localization'
import { cleanup, render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import type { ReactNode } from 'react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ApplicationErrorBoundary } from './ApplicationErrorBoundary'
import { createRootErrorOptions } from './rootErrorReporting'

const sensitiveFixture = 'learner transcript must remain private'

function BrokenChild(): ReactNode {
  throw new Error(sensitiveFixture)
}

afterEach(() => {
  cleanup()
  vi.restoreAllMocks()
})

beforeEach(() => {
  document.documentElement.lang = DEFAULT_LOCALE
  delete document.documentElement.dataset.requestedLocale
})

describe('ApplicationErrorBoundary', () => {
  it('renders children while the application is healthy', () => {
    render(
      <ApplicationErrorBoundary>
        <p>Application content</p>
      </ApplicationErrorBoundary>,
    )

    expect(screen.getByText('Application content')).toBeInTheDocument()
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })

  it('shows an accessible generic fallback without exposing the error', async () => {
    const consoleError = vi
      .spyOn(console, 'error')
      .mockImplementation(() => undefined)
    const rootErrorReport = vi.fn()
    const rootErrorOptions = createRootErrorOptions(rootErrorReport)
    const onCaught = vi.fn()
    const onReload = vi.fn()
    const user = userEvent.setup()

    render(
      <ApplicationErrorBoundary
        onCaught={onCaught}
        onReload={onReload}
      >
        <BrokenChild />
      </ApplicationErrorBoundary>,
      {
        onCaughtError: rootErrorOptions.onCaughtError,
        onRecoverableError: rootErrorOptions.onRecoverableError,
      },
    )

    const title = screen.getByRole('heading', {
      name: message('error.title'),
    })

    await waitFor(() => expect(title).toHaveFocus())
    expect(screen.getByRole('main')).toHaveAccessibleName(
      message('error.title'),
    )
    expect(screen.getByRole('alert')).toHaveTextContent(
      message('error.message'),
    )
    expect(screen.queryByText(sensitiveFixture)).not.toBeInTheDocument()
    expect(onCaught).toHaveBeenCalledWith({
      category: 'react_render_failure',
    })
    expect(rootErrorReport).toHaveBeenCalledWith({
      category: 'react_caught_error',
    })
    expect(JSON.stringify(rootErrorReport.mock.calls)).not.toContain(
      sensitiveFixture,
    )
    expect(JSON.stringify(consoleError.mock.calls)).not.toContain(
      sensitiveFixture,
    )

    await user.click(
      screen.getByRole('button', { name: message('error.reload') }),
    )

    expect(onReload).toHaveBeenCalledOnce()
  })

  it('uses the approved Kannada catalogue without an English fallback', async () => {
    vi.spyOn(console, 'error').mockImplementation(() => undefined)
    const consoleWarning = vi
      .spyOn(console, 'warn')
      .mockImplementation(() => undefined)

    render(
      <ApplicationErrorBoundary localizationPolicy="approved-only">
        <BrokenChild />
      </ApplicationErrorBoundary>,
    )

    expect(screen.queryByRole('status')).not.toBeInTheDocument()
    expect(await screen.findByRole('alert')).toHaveTextContent(
      message('error.message', 'approved-only'),
    )
    expect(
      screen.getByRole('button', {
        name: message('error.reload', 'approved-only'),
      }),
    ).toBeInTheDocument()
    expect(document.documentElement).toHaveAttribute('lang', 'kn-IN')
    expect(document.documentElement).toHaveAttribute('data-requested-locale', DEFAULT_LOCALE)
    expect(JSON.stringify(consoleWarning.mock.calls)).not.toContain(
      'localization.fallback',
    )
    expect(JSON.stringify(consoleWarning.mock.calls)).not.toContain(
      sensitiveFixture,
    )
  })

  it('uses a visible English fallback when pending Hindi is requested', async () => {
    document.documentElement.dataset.requestedLocale = 'hi-IN'
    vi.spyOn(console, 'error').mockImplementation(() => undefined)
    const consoleWarning = vi
      .spyOn(console, 'warn')
      .mockImplementation(() => undefined)

    render(
      <ApplicationErrorBoundary localizationPolicy="approved-only">
        <BrokenChild />
      </ApplicationErrorBoundary>,
    )

    expect(await screen.findByRole('status')).toHaveTextContent(
      message('localization.fallback_notice', 'approved-only', 'hi-IN'),
    )
    expect(screen.getByRole('alert')).toHaveTextContent(
      message('error.message', 'approved-only', 'hi-IN'),
    )
    await waitFor(() => expect(consoleWarning).toHaveBeenCalled())
    expect(document.documentElement).toHaveAttribute('lang', 'en-IN')
    expect(document.documentElement).toHaveAttribute(
      'data-requested-locale',
      'hi-IN',
    )
  })
})

function message(
  key: MessageKey,
  policy: ResolutionPolicy = 'development-preview',
  requestedLocale: typeof DEFAULT_LOCALE | 'hi-IN' = DEFAULT_LOCALE,
): string {
  return resolveMessage(key, {
    policy,
    requestedLocale,
  }).text
}
