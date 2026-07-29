import type {
  AnchorHTMLAttributes,
  MouseEvent,
  ReactNode,
} from 'react'

export interface SkipLinkProps
  extends Omit<AnchorHTMLAttributes<HTMLAnchorElement>, 'children' | 'href'> {
  label: ReactNode
  targetId: string
}

export function SkipLink({
  label,
  targetId,
  className,
  onClick,
  ...anchorProps
}: SkipLinkProps) {
  const classes = ['ge-skip-link', className].filter(Boolean).join(' ')
  const handleClick = (event: MouseEvent<HTMLAnchorElement>) => {
    onClick?.(event)

    if (event.defaultPrevented) {
      return
    }

    const target = document.getElementById(targetId)
    if (target === null) {
      return
    }

    event.preventDefault()
    target.focus()
  }

  return (
    <a
      {...anchorProps}
      className={classes}
      href={`#${targetId}`}
      onClick={handleClick}
    >
      {label}
    </a>
  )
}
