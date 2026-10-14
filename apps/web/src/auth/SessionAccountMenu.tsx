import type { KeyboardEvent, ReactNode } from 'react'
import { useEffect, useLayoutEffect, useRef, useState } from 'react'

export const ACCOUNT_MENU_TRIGGER_ID = 'shell-account-menu-trigger'

interface SessionAccountMenuProps {
  readonly accountLabel: string
  readonly accountSettingsLabel: string
  readonly deleteAccountHref?: string
  readonly deleteAccountLabel?: string
  readonly signOutLabel: string
  readonly onOpenAccount: () => void
  readonly onSignOut: () => void
  readonly accountIcon?: ReactNode
}

export function SessionAccountMenu({
  accountLabel,
  accountSettingsLabel,
  deleteAccountHref,
  deleteAccountLabel,
  signOutLabel,
  onOpenAccount,
  onSignOut,
  accountIcon,
}: SessionAccountMenuProps) {
  const [open, setOpen] = useState(false)
  const container = useRef<HTMLDivElement>(null)
  const trigger = useRef<HTMLButtonElement>(null)
  const menu = useRef<HTMLDivElement>(null)

  useEffect(() => {
    if (!open) return

    const onPointerDown = (event: PointerEvent) => {
      const target = event.target
      if (
        target instanceof Node &&
        container.current !== null &&
        !container.current.contains(target)
      ) {
        setOpen(false)
      }
    }
    const onAndroidBack = (event: Event) => {
      event.preventDefault()
      setOpen(false)
      trigger.current?.focus()
    }

    document.addEventListener('pointerdown', onPointerDown)
    globalThis.addEventListener('ge:android-back-request', onAndroidBack)
    return () => {
      document.removeEventListener('pointerdown', onPointerDown)
      globalThis.removeEventListener('ge:android-back-request', onAndroidBack)
    }
  }, [open])

  useLayoutEffect(() => {
    if (!open) return
    const position = () => {
      if (!trigger.current || !menu.current) return
      const anchor = trigger.current.getBoundingClientRect()
      const width = menu.current.getBoundingClientRect().width
      menu.current.style.left = `${Math.max(8, Math.min(anchor.right - width, window.innerWidth - width - 8))}px`
      menu.current.style.top = `${anchor.bottom + 6}px`
      menu.current.style.maxHeight = `${Math.max(44, window.innerHeight - anchor.bottom - 14)}px`
    }
    position()
    window.addEventListener('resize', position)
    window.addEventListener('scroll', position, true)
    return () => {
      window.removeEventListener('resize', position)
      window.removeEventListener('scroll', position, true)
    }
  }, [open])

  const handleKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
    if (event.key !== 'Escape') {
      return
    }

    event.preventDefault()
    setOpen(false)
    trigger.current?.focus()
  }

  return (
    <div
      className="shell-account-dropdown"
      ref={container}
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
        ref={trigger}
        onClick={() => setOpen((current) => !current)}
        type="button"
      >
        <span aria-hidden="true" className="shell-toolbar-icon">
          {accountIcon ?? '●'}
        </span>
        <span className="shell-toolbar-caption">{accountLabel}</span>
        <span aria-hidden="true" className="shell-toolbar-label">
          ▾
        </span>
      </button>
      {open ? (
        <div
          aria-label={accountLabel}
          className="shell-account-dropdown__menu"
          role="menu"
          ref={menu}
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
          {deleteAccountHref !== undefined && deleteAccountLabel !== undefined ? (
            <a href={deleteAccountHref} role="menuitem">
              {deleteAccountLabel}
            </a>
          ) : null}
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
