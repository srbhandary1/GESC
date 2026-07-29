import type { ReactNode } from 'react'
import type { SupportedLanguage } from '../text/MixedLanguageText'

export const PRIMARY_DESTINATIONS = [
  'home',
  'learn',
  'practice',
  'progress',
] as const

export type PrimaryDestination = (typeof PRIMARY_DESTINATIONS)[number]

export interface PrimaryNavigationLink {
  href: string
  label: ReactNode
}

export type PrimaryNavigationLinks = Readonly<
  Record<PrimaryDestination, PrimaryNavigationLink>
>

export interface PrimaryNavigationProps {
  ariaLabel: string
  ariaLabelLocale: SupportedLanguage
  currentDestination: PrimaryDestination
  links: PrimaryNavigationLinks
}

export function PrimaryNavigation({
  ariaLabel,
  ariaLabelLocale,
  currentDestination,
  links,
}: PrimaryNavigationProps) {
  return (
    <nav
      aria-label={ariaLabel}
      className="ge-primary-navigation"
      lang={ariaLabelLocale}
    >
      <ul className="ge-primary-navigation__list">
        {PRIMARY_DESTINATIONS.map((destination) => {
          const link = links[destination]

          return (
            <li key={destination}>
              <a
                aria-current={
                  currentDestination === destination ? 'page' : undefined
                }
                className="ge-primary-navigation__link"
                href={link.href}
              >
                {link.label}
              </a>
            </li>
          )
        })}
      </ul>
    </nav>
  )
}
