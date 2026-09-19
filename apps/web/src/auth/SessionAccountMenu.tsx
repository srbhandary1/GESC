import type { KeyboardEvent, ReactNode } from 'react'
import { useState } from 'react'

export const ACCOUNT_MENU_TRIGGER_ID = 'shell-account-menu-trigger'

interface SessionAccountMenuProps {
  readonly accountLabel: string
  readonly accountSettingsLabel: string
  readonly signOutLabel: string
  readonly onOpenAccount: () => void
  readonly onSignOut: () => void
  readonly accountIcon?: ReactNode
}

export function SessionAccountMenu({
  accountLabel,
  accountSettingsLabel,
  signOutLabel,
  onOpenAccount,
  onSignOut,
  accountIcon,
}: SessionAccountMenuProps) {
  const [open, setOpen] = useState(false)

  const handleKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
    if (event.key !== 'Escape') {
      return
    }

    event.preventDefault()
    setOpen(false)
  }

  return (
    <div
      className="shell-account-dropdown"
      onBlur={(event) => {
        if (!event.currentTarget.contains(event.relatedTarget)) {
          setOpen(false)
        }
      }}
      onKeyDown={handleKeyDown}
    >
      <button
        aria-expanded={open}
        aria-haspopup="menu"
        className="shell-header-authentication shell-account-dropdown__trigger"
        id={ACCOUNT_MENU_TRIGGER_ID}
        onClick={() => setOpen((current) => !current)}
        type="button"
      >
        <span aria-hidden="true" className="shell-toolbar-icon">
          {accountIcon ?? '●'}
        </span>
        <span className="shell-toolbar-label">{accountLabel}</span>
      </button>
      {open ? (
        <div
          aria-label={accountLabel}
          className="shell-account-dropdown__menu"
          role="menu"
        >
          <button
            onClick={() => {
              setOpen(false)
              onOpenAccount()
            }}
            role="menuitem"
            type="button"
          >
            {accountSettingsLabel}
          </button>
          <button
            className="shell-account-dropdown__sign-out"
            onClick={() => {
              setOpen(false)
              onSignOut()
            }}
            role="menuitem"
            type="button"
          >
            {signOutLabel}
          </button>
        </div>
      ) : null}
    </div>
  )
}
