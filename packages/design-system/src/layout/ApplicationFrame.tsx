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
  /**
   * Narrow-viewport wordmark. The full `brandLabel` remains the accessible
   * name and is shown on wider layouts.
   */
  compactBrandLabel?: ReactNode
  children: ReactNode
  currentDestination: PrimaryDestination
  navigationLabel: string
  navigationLabelLocale: SupportedLanguage
  navigationLinks: PrimaryNavigationLinks
  skipToContentLabel: ReactNode
  headerActions?: ReactNode
  /**
   * When true, omits the primary destination navigation. Used for the
   * pre-sign-in splash and by the demo Phase-1 shell, which renders its
   * own module navigation.
   */
  hidePrimaryNavigation?: boolean
  mainId?: string
}

export function ApplicationFrame({
  brandLabel,
  brandLabelLocale,
  compactBrandLabel,
  children,
  currentDestination,
  navigationLabel,
  navigationLabelLocale,
  navigationLinks,
  skipToContentLabel,
  headerActions,
  hidePrimaryNavigation = false,
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
            className={
              compactBrandLabel === undefined
                ? 'ge-application-frame__brand'
                : 'ge-application-frame__brand ge-application-frame__brand--responsive'
            }
            lang={brandLabelLocale}
          >
            <span className="ge-application-frame__brand-full">
              {brandLabel}
            </span>
            {compactBrandLabel === undefined ? null : (
              <span
                aria-hidden="true"
                className="ge-application-frame__brand-short"
              >
                {compactBrandLabel}
              </span>
            )}
          </span>
          {headerActions === undefined ? null : (
            <div className="ge-application-frame__header-actions">
              {headerActions}
            </div>
          )}
        </div>
      </header>
      {hidePrimaryNavigation ? null : (
        <PrimaryNavigation
          ariaLabel={navigationLabel}
          ariaLabelLocale={navigationLabelLocale}
          currentDestination={currentDestination}
          links={navigationLinks}
        />
      )}
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
