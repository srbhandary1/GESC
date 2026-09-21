import { cleanup, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it } from 'vitest'
import { GuestMiraHome } from './GuestMiraHome'

afterEach(() => {
  cleanup()
})

describe('GuestMiraHome web layout', () => {
  it('shows the Impeccable web guest home without loading marketing landing', async () => {
    const user = userEvent.setup()
    render(
      <GuestMiraHome
        layout="web"
        locale="en-IN"
        localizationPolicy="development-preview"
      />,
    )

    expect(
      screen.getByRole('heading', {
        level: 1,
        name: 'Let’s speak English fluently',
      }),
    ).toBeInTheDocument()
    expect(screen.getByRole('img', { name: 'Swathi' })).toBeInTheDocument()
    expect(
      screen.getByRole('link', { name: 'Sign in to start' }),
    ).toHaveAttribute('href', '#sign-in')
    expect(
      screen.getAllByRole('link', { name: 'Sign in to start' }),
    ).toHaveLength(1)
    expect(
      screen.getByRole('link', { name: 'See the four sessions' }),
    ).toHaveAttribute('href', '#guest-sessions')
    expect(
      screen.getAllByText('I would like black tea.').length,
    ).toBeGreaterThan(0)
    expect(
      screen.getByRole('heading', {
        name: 'Four sessions, not one endless feed',
      }),
    ).toBeInTheDocument()
    expect(document.querySelector('.marketing-landing')).toBeNull()
    expect(document.querySelector('[data-guest-layout="web"]')).not.toBeNull()

    await user.click(
      screen.getByRole('button', { name: 'I would like a window seat.' }),
    )
    expect(screen.getByRole('status')).toHaveTextContent(
      'Marks from rules, not AI vibes',
    )
  })
})
