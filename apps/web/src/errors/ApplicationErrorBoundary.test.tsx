import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import type { ReactNode } from 'react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { ApplicationErrorBoundary } from './ApplicationErrorBoundary'
import { createRootErrorOptions } from './rootErrorReporting'

const sensitiveFixture = 'learner transcript must remain private'

function BrokenChild(): ReactNode {
  throw new Error(sensitiveFixture)
}

afterEach(() => {
  vi.restoreAllMocks()
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
      name: 'Global English could not load',
    })

    await waitFor(() => expect(title).toHaveFocus())
    expect(screen.getByRole('main')).toHaveAccessibleName(
      'Global English could not load',
    )
    expect(screen.getByRole('alert')).toHaveTextContent(
      'Something unexpected happened.',
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
      screen.getByRole('button', { name: 'Reload application' }),
    )

    expect(onReload).toHaveBeenCalledOnce()
  })
})
