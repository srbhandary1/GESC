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
            <li
              className={
                destination === 'practice'
                  ? 'ge-primary-navigation__item ge-primary-navigation__item--speak'
                  : 'ge-primary-navigation__item'
              }
              key={destination}
            >
              <a
                aria-current={
                  currentDestination === destination ? 'page' : undefined
                }
                className={
                  destination === 'practice'
                    ? 'ge-primary-navigation__link ge-primary-navigation__link--speak'
                    : 'ge-primary-navigation__link'
                }
                data-destination={destination}
                href={link.href}
              >
                <span aria-hidden="true" className="ge-nav-glyph">
                  {destination === 'home' ? (
                    <svg fill="none" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" viewBox="0 0 24 24">
                      <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                      <polyline points="9 22 9 12 15 12 15 22" />
                    </svg>
                  ) : null}
                  {destination === 'learn' ? (
                    <svg fill="none" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" viewBox="0 0 24 24">
                      <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z" />
                      <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z" />
                    </svg>
                  ) : null}
                  {destination === 'practice' ? (
                    <svg fill="none" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" viewBox="0 0 24 24">
                      <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z" />
                      <path d="M19 10v2a7 7 0 0 1-14 0v-2" />
                      <line x1="12" y1="19" x2="12" y2="23" />
                      <line x1="8" y1="23" x2="16" y2="23" />
                    </svg>
                  ) : null}
                  {destination === 'progress' ? (
                    <svg fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                      <circle cx="12" cy="12" r="8" />
                      <circle cx="12" cy="12" fill="currentColor" r="3" />
                    </svg>
                  ) : null}
                </span>
                {link.label}
              </a>
            </li>
          )
        })}
      </ul>
    </nav>
  )
}
