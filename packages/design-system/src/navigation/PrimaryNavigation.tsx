import type { ReactNode } from 'react'
import type { SupportedLanguage } from '../text/MixedLanguageText'

export const PRIMARY_DESTINATIONS = [
  'home',
  'learn',
  'speak',
  'me',
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

const ICON_PATHS: Readonly<Record<PrimaryDestination, ReactNode>> = {
  home: (
    <>
      <path d="M3 10.5 12 3l9 7.5" />
      <path d="M5.5 9.5V21h13V9.5" />
      <path d="M9.5 21v-6h5v6" />
    </>
  ),
  learn: (
    <>
      <path d="M12 4.5c-2-1.3-4.7-1.7-8-1.5v16c3.3-.2 6 .2 8 1.5 2-1.3 4.7-1.7 8-1.5V3c-3.3-.2-6 .2-8 1.5Z" />
      <path d="M12 4.5v16" />
    </>
  ),
  speak: (
    <>
      <rect height="11" rx="3" width="6" x="9" y="2.5" />
      <path d="M5 11.5a7 7 0 0 0 14 0" />
      <path d="M12 18.5v3" />
    </>
  ),
  me: (
    <>
      <circle cx="12" cy="8" r="4" />
      <path d="M4.5 21a7.5 7.5 0 0 1 15 0" />
    </>
  ),
}

function DestinationIcon({
  destination,
}: {
  destination: PrimaryDestination
}) {
  return (
    <svg
      aria-hidden="true"
      className="ge-primary-navigation__icon"
      fill="none"
      focusable="false"
      stroke="currentColor"
      strokeLinecap="round"
      strokeLinejoin="round"
      strokeWidth="1.8"
      viewBox="0 0 24 24"
    >
      {ICON_PATHS[destination]}
    </svg>
  )
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
                data-destination={destination}
                href={link.href}
              >
                <DestinationIcon destination={destination} />
                <span className="ge-primary-navigation__label">
                  {link.label}
                </span>
              </a>
            </li>
          )
        })}
      </ul>
    </nav>
  )
}
