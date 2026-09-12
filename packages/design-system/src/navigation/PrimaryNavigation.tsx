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
                {destination === 'practice' ? (
                  <span aria-hidden="true" className="ge-speak-glyph">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                      <path
                        d="M4 14c2-3 4-3 6 0s4 3 6 0 4-3 6 0"
                        fill="none"
                        stroke="currentColor"
                        strokeLinecap="round"
                        strokeWidth="2.2"
                      />
                      <path
                        d="M7 9c1.4-2 2.8-2 4.2 0M13 9c1.4-2 2.8-2 4.2 0"
                        fill="none"
                        stroke="currentColor"
                        strokeLinecap="round"
                        strokeWidth="2"
                      />
                    </svg>
                  </span>
                ) : null}
                {link.label}
              </a>
            </li>
          )
        })}
      </ul>
    </nav>
  )
}
