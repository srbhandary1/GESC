import type { MessageKey } from '@global-english/localization'
import type { InterfaceMessageResolution } from '../localization/interfaceLocalization'
import { LocalizedMessage } from '../localization/LocalizedMessage'

export interface LegalLinksProps {
  readonly message: (key: MessageKey) => InterfaceMessageResolution
}

export function LegalLinks({ message }: LegalLinksProps) {
  const navLabel = message('legal.nav')

  return (
    <footer className="shell-legal-footer">
      <nav className="shell-legal" aria-label={navLabel.text} lang={navLabel.resolvedLocale}>
        <a href="/privacy.html">
          <LocalizedMessage resolution={message('legal.privacy')} />
        </a>
        <a href="/account-deletion.html">
          <LocalizedMessage resolution={message('legal.account_deletion')} />
        </a>
      </nav>
      <p className="shell-developer-credit" lang="en-IN">
        Developed by Northstar Enterprise Solutions Private Limited – Mangalore
      </p>
    </footer>
  )
}
