import { cleanup, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { GuestMiraHome } from './GuestMiraHome'

afterEach(() => {
  cleanup()
  vi.restoreAllMocks()
})

beforeEach(() => {
  Object.defineProperty(HTMLMediaElement.prototype, 'play', {
    configurable: true,
    value: vi.fn().mockResolvedValue(undefined),
  })
  Object.defineProperty(HTMLMediaElement.prototype, 'pause', {
    configurable: true,
    value: vi.fn(),
  })
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

    const audio = screen.getByTestId('guest-sample-audio')
    expect(audio).toHaveAttribute('preload', 'none')
    expect(audio).toHaveAttribute(
      'src',
      '/guest-sample/level-12-i-would-like-black-tea.wav',
    )
    const fetchSpy = vi.spyOn(globalThis, 'fetch')

    await user.click(screen.getByRole('button', { name: 'Listen' }))
    expect(HTMLMediaElement.prototype.play).toHaveBeenCalled()
    expect(fetchSpy).not.toHaveBeenCalled()

    await user.click(
      screen.getByRole('button', { name: 'Would you like some water?' }),
    )
    expect(audio).toHaveAttribute(
      'src',
      '/guest-sample/level-12-would-you-like-some-water.wav',
    )

    await user.click(
      screen.getByRole('button', { name: 'I would like a window seat.' }),
    )
    expect(screen.getByRole('status')).toHaveTextContent(
      'Marks from rules, not AI vibes',
    )
  })
})
