import { useEffect, useState } from 'react'
import type { MessageKey } from '@global-english/localization'
import type {
  FederatedAccountAuthentication,
  FederatedProviderAvailability,
} from '../auth/AuthenticationAdapter'
import type { InterfaceMessageResolution } from '../localization/interfaceLocalization'
import { LocalizedMessage } from '../localization/LocalizedMessage'

interface FederatedAccountsPanelProps {
  readonly authentication: FederatedAccountAuthentication & {
    federatedProviders(): Promise<FederatedProviderAvailability>
  }
  readonly message: (key: MessageKey) => InterfaceMessageResolution
}

export function FederatedAccountsPanel({
  authentication,
  message,
}: FederatedAccountsPanelProps) {
  const [providers, setProviders] = useState<FederatedProviderAvailability>({
    google: false,
    facebook: false,
  })
  const [links, setLinks] = useState<FederatedProviderAvailability>({
    google: false,
    facebook: false,
  })
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState(false)

  const refresh = async () => {
    const [nextProviders, nextLinks] = await Promise.all([
      authentication.federatedProviders(),
      authentication.federatedLinks(),
    ])
    setProviders(nextProviders)
    setLinks(nextLinks)
  }

  useEffect(() => {
    let active = true

    Promise.all([
      authentication.federatedProviders(),
      authentication.federatedLinks(),
    ])
      .then(([nextProviders, nextLinks]) => {
        if (!active) {
          return
        }

        setProviders(nextProviders)
        setLinks(nextLinks)
      })
      .catch(() => active && setError(true))

    return () => {
      active = false
    }
  }, [authentication])

  if (!providers.google && !providers.facebook) {
    return null
  }

  const run = (operation: () => Promise<void>) => {
    setBusy(true)
    setError(false)
    void operation()
      .then(() => refresh())
      .catch(() => setError(true))
      .finally(() => setBusy(false))
  }

  return (
    <section className="federated-accounts">
      <h4>
        <LocalizedMessage resolution={message('auth.social.section')} />
      </h4>
      {providers.google ? (
        <p>
          {links.google ? (
            <>
              <LocalizedMessage
                resolution={message('auth.social.linked_google')}
              />{' '}
              <button
                disabled={busy}
                onClick={() =>
                  run(() => authentication.unlinkFederated('google'))
                }
                type="button"
              >
                <LocalizedMessage
                  resolution={message('auth.social.unlink_google')}
                />
              </button>
            </>
          ) : (
            <button
              disabled={busy}
              onClick={() =>
                run(() => authentication.beginFederatedLink('google'))
              }
              type="button"
            >
              <LocalizedMessage
                resolution={message('auth.social.link_google')}
              />
            </button>
          )}
        </p>
      ) : null}
      {providers.facebook ? (
        <p>
          {links.facebook ? (
            <>
              <LocalizedMessage
                resolution={message('auth.social.linked_facebook')}
              />{' '}
              <button
                disabled={busy}
                onClick={() =>
                  run(() => authentication.unlinkFederated('facebook'))
                }
                type="button"
              >
                <LocalizedMessage
                  resolution={message('auth.social.unlink_facebook')}
                />
              </button>
            </>
          ) : (
            <button
              disabled={busy}
              onClick={() =>
                run(() => authentication.beginFederatedLink('facebook'))
              }
              type="button"
            >
              <LocalizedMessage
                resolution={message('auth.social.link_facebook')}
              />
            </button>
          )}
        </p>
      ) : null}
      {error ? (
        <p className="web-otp-sign-in__error" role="alert">
          <LocalizedMessage resolution={message('auth.password.error')} />
        </p>
      ) : null}
    </section>
  )
}
