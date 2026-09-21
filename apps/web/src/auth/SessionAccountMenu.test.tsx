import { cleanup, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { SessionAccountMenu } from './SessionAccountMenu'

afterEach(() => {
  cleanup()
})

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

  it('exposes account deletion only after the menu is opened', async () => {
    const user = userEvent.setup()

    render(
      <SessionAccountMenu
        accountLabel="Account"
        accountSettingsLabel="Account settings"
        deleteAccountHref="/account-deletion.html"
        deleteAccountLabel="Delete account"
        onOpenAccount={vi.fn()}
        onSignOut={vi.fn()}
        signOutLabel="Sign out"
      />,
    )

    expect(screen.queryByRole('menuitem', { name: 'Delete account' })).not.toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Account' }))
    expect(screen.getByRole('menuitem', { name: 'Delete account' })).toHaveAttribute(
      'href',
      '/account-deletion.html',
    )
  })
})
