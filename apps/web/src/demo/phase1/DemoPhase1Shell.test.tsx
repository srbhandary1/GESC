import { cleanup, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it } from 'vitest'
import { DemoPhase1Shell } from './DemoPhase1Shell'

afterEach(() => {
  cleanup()
  window.history.replaceState(null, '', '/')
})

describe('DemoPhase1Shell', () => {
  it('opens the dashboard with five module entries', () => {
    render(
      <DemoPhase1Shell
        locale="en-IN"
        localizationPolicy="development-preview"
      />,
    )

    expect(
      screen.getByRole('heading', { level: 1, name: 'Dashboard' }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('navigation', { name: 'Phase 1 modules' }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: /Attendance/i }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: /AI Teacher/i }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: /Daily Activity/i }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: /My Levels/i }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: /Notice Board/i }),
    ).toBeInTheDocument()
  })

  it('navigates to attendance, AI teacher, daily quiz, levels and notices', async () => {
    const user = userEvent.setup()

    render(
      <DemoPhase1Shell
        locale="en-IN"
        localizationPolicy="development-preview"
      />,
    )

    await user.click(screen.getByRole('link', { name: 'Attendance' }))
    expect(
      screen.getByRole('heading', { level: 1, name: 'Attendance' }),
    ).toBeInTheDocument()
    expect(screen.getByText('75%')).toBeInTheDocument()

    await user.click(screen.getByRole('link', { name: 'AI Teacher' }))
    expect(
      screen.getByText('Scripted demo replies only. No cloud AI call is made.'),
    ).toBeInTheDocument()

    await user.click(screen.getByRole('link', { name: 'Daily Activity' }))
    expect(screen.getByText("Today's 5-mark check")).toBeInTheDocument()
    expect(screen.getAllByRole('radio').length).toBe(15)

    await user.click(screen.getByRole('link', { name: 'My Levels' }))
    expect(
      screen.getByRole('button', { name: /Pre-Level 1/i }),
    ).toBeInTheDocument()
    expect(screen.getByText('Content Room')).toBeInTheDocument()

    await user.click(screen.getByRole('link', { name: 'Notice Board' }))
    expect(
      screen.getByText('Welcome to the Phase 1 demo pathway'),
    ).toBeInTheDocument()
  })
})
