import { cleanup, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import type { MessageKey } from '@global-english/localization'
import { resolveInterfaceMessage } from '../localization/interfaceLocalization'
import { PreSignInSplash } from './PreSignInSplash'

afterEach(() => {
  cleanup()
})

const message = (key: MessageKey) =>
  resolveInterfaceMessage(key, 'en-IN', 'development-preview')

describe('PreSignInSplash', () => {
  it('shows a single sign-in action without a session essay', () => {
    render(
      <PreSignInSplash
        canStartAuthorization
        message={message}
        onRetry={vi.fn()}
        onSignIn={vi.fn()}
        status="anonymous"
      />,
    )

    expect(
      screen.getByRole('heading', {
        level: 1,
        name: 'Speak English with confidence.',
      }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: 'Sign in' }),
    ).toBeInTheDocument()
    expect(
      screen.queryByRole('heading', { name: 'Secure session' }),
    ).not.toBeInTheDocument()
    expect(screen.getByText('Sign in to continue learning.')).toBeInTheDocument()
  })

  it('shows a calm checking skeleton without sign-in or session chrome', () => {
    render(
      <PreSignInSplash
        canStartAuthorization={false}
        message={message}
        onRetry={vi.fn()}
        onSignIn={vi.fn()}
        status="checking"
      />,
    )

    expect(
      screen.getByRole('heading', {
        level: 1,
        name: 'Speak English with confidence.',
      }),
    ).toBeInTheDocument()
    expect(
      screen.queryByRole('button', { name: 'Sign in' }),
    ).not.toBeInTheDocument()
    expect(screen.getByRole('status')).toBeInTheDocument()
    expect(screen.queryByText('Session')).not.toBeInTheDocument()
  })

  it('opens session details from Sign in when PKCE is unavailable', async () => {
    const onSignIn = vi.fn()
    const user = userEvent.setup()

    render(
      <PreSignInSplash
        canStartAuthorization={false}
        message={message}
        onRetry={vi.fn()}
        onSignIn={onSignIn}
        status="anonymous"
      />,
    )

    const details = screen.getByText('Session').closest('details')
    expect(details).not.toHaveAttribute('open')
    await user.click(screen.getByRole('button', { name: 'Sign in' }))
    expect(onSignIn).not.toHaveBeenCalled()
    expect(details).toHaveAttribute('open')
  })

  it('starts PKCE authorization from the primary action', async () => {
    const onSignIn = vi.fn()
    const user = userEvent.setup()

    render(
      <PreSignInSplash
        canStartAuthorization
        message={message}
        onRetry={vi.fn()}
        onSignIn={onSignIn}
        status="anonymous"
      />,
    )

    await user.click(screen.getByRole('button', { name: 'Sign in' }))
    expect(onSignIn).toHaveBeenCalledOnce()
  })

  it('offers retry when sign-in is unavailable', async () => {
    const onRetry = vi.fn()
    const user = userEvent.setup()

    render(
      <PreSignInSplash
        canStartAuthorization={false}
        message={message}
        onRetry={onRetry}
        onSignIn={vi.fn()}
        status="unavailable"
      />,
    )

    await user.click(screen.getByRole('button', { name: 'Try again' }))
    expect(onRetry).toHaveBeenCalledOnce()
  })
})
