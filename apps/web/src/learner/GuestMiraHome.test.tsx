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
      screen.queryByRole('link', { name: 'Speak now' }),
    ).not.toBeInTheDocument()
    expect(
      screen.getByRole('link', { name: 'Sign in to start' }),
    ).toHaveAttribute('href', '#sign-in')
    expect(screen.getAllByRole('link', { name: 'Sign in to start' })).toHaveLength(1)
    expect(
      screen.getByText('Sign in to open your lessons and practice.'),
    ).toBeInTheDocument()
    expect(screen.queryByText('Continue today’s lesson')).not.toBeInTheDocument()
    expect(
      screen.getByRole('link', { name: 'See the four sessions' }),
    ).toHaveAttribute('href', '#about')
    expect(document.querySelector('[data-guest-layout="compact"]')).not.toBeNull()
    expect(document.querySelector('.marketing-landing')).toBeNull()
  })
})
