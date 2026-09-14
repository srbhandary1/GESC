import { cleanup, render, screen } from '@testing-library/react'
import { afterEach, describe, expect, it } from 'vitest'
import { GuestMiraHome } from './GuestMiraHome'

afterEach(() => {
  cleanup()
})

describe('GuestMiraHome', () => {
  it('renders Mira chrome and sends unsigned actions to sign-in', () => {
    render(
      <GuestMiraHome locale="en-IN" localizationPolicy="development-preview" />,
    )

    expect(
      screen.getByRole('heading', { level: 1, name: 'Today' }),
    ).toBeInTheDocument()
    expect(screen.getByRole('img', { name: 'Swathi' })).toBeInTheDocument()
    expect(
      screen.getByRole('link', { name: 'Speak now' }),
    ).toHaveAttribute('href', '#sign-in')
    expect(
      screen.getByRole('link', { name: 'Sign in to start' }),
    ).toHaveAttribute('href', '#sign-in')
    expect(
      screen.getByRole('link', { name: 'See the four sessions' }),
    ).toHaveAttribute('href', '#about')
    expect(document.querySelector('.marketing-landing')).toBeNull()
  })
})
