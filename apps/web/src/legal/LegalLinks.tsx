import type { MessageKey } from '@global-english/localization'
import type { InterfaceMessageResolution } from '../localization/interfaceLocalization'
import { LocalizedMessage } from '../localization/LocalizedMessage'

export interface LegalLinksProps {
  readonly message: (key: MessageKey) => InterfaceMessageResolution
}

export function LegalLinks({ message }: LegalLinksProps) {
  const navLabel = message('legal.nav')

  return (
    <nav className="shell-legal" aria-label={navLabel.text} lang={navLabel.resolvedLocale}>
      <a href="/privacy.html">
        <LocalizedMessage resolution={message('legal.privacy')} />
      </a>
      <a href="/account-deletion.html">
        <LocalizedMessage resolution={message('legal.account_deletion')} />
      </a>
    </nav>
  )
}
