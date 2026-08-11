import type { MessageKey } from '@global-english/localization'
import { useEffect, useState, type FormEvent } from 'react'
import type {
  AuthenticationSnapshot,
  WebSessionAuthentication,
} from './AuthenticationAdapter'
import type { InterfaceMessageResolution } from '../localization/interfaceLocalization'
import { LocalizedMessage } from '../localization/LocalizedMessage'
import { WebOtpSignInPanel } from './WebOtpSignInPanel'

interface WebPasswordSignInPanelProps {
  readonly authentication: WebSessionAuthentication
  readonly message: (key: MessageKey) => InterfaceMessageResolution
  readonly onAuthenticated: (snapshot: AuthenticationSnapshot) => void
}

export function WebPasswordSignInPanel({
  authentication,
  message,
  onAuthenticated,
}: WebPasswordSignInPanelProps) {
  const [username, setUsername] = useState('')
  const [password, setPassword] = useState('')
  const [showPassword, setShowPassword] = useState(false)
  const [recovery, setRecovery] = useState(false)
  const [recoverySent, setRecoverySent] = useState(false)
  const [mode, setMode] = useState<
    'legacy_otp' | 'credential_migration' | 'password_only' | null
  >(null)
  const [legacySetup, setLegacySetup] = useState(false)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState(false)

  useEffect(() => {
    let active = true

    authentication
      .authenticationMode()
      .then((nextMode) => active && setMode(nextMode))
      .catch(() => active && setError(true))

    return () => {
      active = false
    }
  }, [authentication])

  if (mode === 'legacy_otp' || legacySetup) {
    return (
      <>
        {legacySetup ? (
          <button onClick={() => setLegacySetup(false)} type="button">
            <LocalizedMessage
              resolution={message('auth.password.back')}
            />
          </button>
        ) : null}
        <WebOtpSignInPanel
          authentication={authentication}
          message={message}
          onAuthenticated={onAuthenticated}
        />
      </>
    )
  }

  const signIn = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setBusy(true)
    setError(false)

    void authentication
      .signIn(username.trim(), password)
      .then(() => authentication.current())
      .then(onAuthenticated)
      .catch(() => setError(true))
      .finally(() => {
        setPassword('')
        setBusy(false)
      })
  }

  const requestRecovery = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setBusy(true)
    setError(false)

    void authentication
      .requestPasswordRecovery(username.trim())
      .then(() => setRecoverySent(true))
      .catch(() => setError(true))
      .finally(() => setBusy(false))
  }

  return (
    <section
      aria-labelledby="web-password-sign-in-title"
      className="web-otp-sign-in"
    >
      <h3 id="web-password-sign-in-title">
        <LocalizedMessage resolution={message('auth.password.title')} />
      </h3>
      <p>
        <LocalizedMessage resolution={message('auth.password.lead')} />
      </p>

      {mode === 'credential_migration' ? (
        <button onClick={() => setLegacySetup(true)} type="button">
          <LocalizedMessage
            resolution={message('auth.password.existing_setup')}
          />
        </button>
      ) : null}

      <form
        className="web-otp-sign-in__form"
        onSubmit={recovery ? requestRecovery : signIn}
      >
        <label htmlFor="web-password-username">
          <LocalizedMessage resolution={message('auth.password.username')} />
        </label>
        <input
          autoCapitalize="none"
          autoComplete="username"
          disabled={busy}
          id="web-password-username"
          maxLength={64}
          onChange={(event) => setUsername(event.currentTarget.value)}
          pattern="[a-zA-Z][a-zA-Z0-9._-]{3,63}"
          required
          spellCheck={false}
          type="text"
          value={username}
        />

        {recovery ? null : (
          <>
            <label htmlFor="web-password-password">
              <LocalizedMessage
                resolution={message('auth.password.password')}
              />
            </label>
            <input
              autoComplete="current-password"
              disabled={busy}
              id="web-password-password"
              maxLength={512}
              onChange={(event) => setPassword(event.currentTarget.value)}
              required
              type={showPassword ? 'text' : 'password'}
              value={password}
            />
            <label>
              <input
                checked={showPassword}
                disabled={busy}
                onChange={(event) =>
                  setShowPassword(event.currentTarget.checked)
                }
                type="checkbox"
              />{' '}
              <LocalizedMessage resolution={message('auth.password.show')} />
            </label>
          </>
        )}

        <button disabled={busy || recoverySent} type="submit">
          <LocalizedMessage
            resolution={message(
              recovery
                ? 'auth.password.recovery_submit'
                : busy
                  ? 'auth.password.signing_in'
                  : 'auth.password.submit',
            )}
          />
        </button>
        <button
          disabled={busy}
          onClick={() => {
            setRecovery((current) => !current)
            setRecoverySent(false)
            setError(false)
          }}
          type="button"
        >
          <LocalizedMessage
            resolution={message(
              recovery
                ? 'auth.password.back'
                : 'auth.password.recovery_open',
            )}
          />
        </button>
      </form>

      {recoverySent ? (
        <p role="status">
          <LocalizedMessage
            resolution={message('auth.password.recovery_sent')}
          />
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
