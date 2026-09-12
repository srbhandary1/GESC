import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { ThemeToggle } from './ThemeToggle'

describe('ThemeToggle', () => {
  it('exposes a 48px appearance control and reports the pressed dark state', async () => {
    const user = userEvent.setup()
    const onToggle = vi.fn()

    const { rerender } = render(
      <ThemeToggle
        darkLabel="Dark"
        label="Appearance"
        lightLabel="Light"
        onToggle={onToggle}
        scheme="light"
      />,
    )

    const button = screen.getByRole('button', { name: 'Appearance: Dark' })
    expect(button).toHaveAttribute('aria-pressed', 'false')
    await user.click(button)
    expect(onToggle).toHaveBeenCalledOnce()

    rerender(
      <ThemeToggle
        darkLabel="Dark"
        label="Appearance"
        lightLabel="Light"
        onToggle={onToggle}
        scheme="dark"
      />,
    )
    expect(
      screen.getByRole('button', { name: 'Appearance: Light' }),
    ).toHaveAttribute('aria-pressed', 'true')
  })
})
