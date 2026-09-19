import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { SessionAccountMenu } from './SessionAccountMenu'

describe('SessionAccountMenu', () => {
  it('keeps account actions compact until deliberately opened', async () => {
    const user = userEvent.setup()
    const onOpenAccount = vi.fn()
    const onSignOut = vi.fn()

    render(
      <SessionAccountMenu
        accountLabel="Account"
        accountSettingsLabel="Account settings"
        onOpenAccount={onOpenAccount}
        onSignOut={onSignOut}
        signOutLabel="Sign out"
      />,
    )

    const trigger = screen.getByRole('button', { name: 'Account' })
    expect(trigger).toHaveAttribute('id', 'shell-account-menu-trigger')
    expect(trigger).toHaveAttribute('aria-expanded', 'false')
    expect(screen.queryByRole('menu')).not.toBeInTheDocument()

    await user.click(trigger)
    expect(trigger).toHaveAttribute('aria-expanded', 'true')
    await user.click(screen.getByRole('menuitem', { name: 'Account settings' }))
    expect(onOpenAccount).toHaveBeenCalledOnce()
    expect(screen.queryByRole('menu')).not.toBeInTheDocument()

    await user.click(trigger)
    await user.click(screen.getByRole('menuitem', { name: 'Sign out' }))
    expect(onSignOut).toHaveBeenCalledOnce()
  })
})
