import type { ReactNode } from 'react'
import type { SupportedLanguage } from '../text/MixedLanguageText'
import {
  PrimaryNavigation,
  type PrimaryDestination,
  type PrimaryNavigationLinks,
} from '../navigation/PrimaryNavigation'
import { SkipLink } from '../navigation/SkipLink'

export interface ApplicationFrameProps {
  brandLabel: ReactNode
  brandLabelLocale: SupportedLanguage
  children: ReactNode
  currentDestination: PrimaryDestination
  navigationLabel: string
  navigationLabelLocale: SupportedLanguage
  navigationLinks: PrimaryNavigationLinks
  skipToContentLabel: ReactNode
  headerActions?: ReactNode
  mainId?: string
}

export function ApplicationFrame({
  brandLabel,
  brandLabelLocale,
  children,
  currentDestination,
  navigationLabel,
  navigationLabelLocale,
  navigationLinks,
  skipToContentLabel,
  headerActions,
  mainId = 'main-content',
}: ApplicationFrameProps) {
  return (
    <div className="ge-application-frame">
      <SkipLink
        label={skipToContentLabel}
        targetId={mainId}
      />
      <header className="ge-application-frame__header">
        <div className="ge-application-frame__header-inner">
          <span
            className="ge-application-frame__brand"
            lang={brandLabelLocale}
          >
            {brandLabel}
          </span>
          {headerActions === undefined ? null : (
            <div className="ge-application-frame__header-actions">
              {headerActions}
            </div>
          )}
        </div>
      </header>
      <PrimaryNavigation
        ariaLabel={navigationLabel}
        ariaLabelLocale={navigationLabelLocale}
        currentDestination={currentDestination}
        links={navigationLinks}
      />
      <main
        className="ge-application-frame__main"
        id={mainId}
        tabIndex={-1}
      >
        {children}
      </main>
    </div>
  )
}
