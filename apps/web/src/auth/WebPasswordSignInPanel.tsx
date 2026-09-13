import type { MessageKey } from '@global-english/localization'
import { useEffect, useRef, useState, type FormEvent } from 'react'
import type {
  AuthenticationAdapter,
  AuthenticationSnapshot,
  FederatedProvider,
  FederatedProviderAvailability,
  PasswordFormAuthentication,
  WebSessionAuthentication,
} from './AuthenticationAdapter'
import type { InterfaceMessageResolution } from '../localization/interfaceLocalization'
import { LocalizedMessage } from '../localization/LocalizedMessage'
import { WebOtpSignInPanel } from './WebOtpSignInPanel'
import { isPasswordSignInSecureContext } from './passwordSignInSecureContext'

interface WebPasswordSignInPanelProps {
  readonly authentication: AuthenticationAdapter & PasswordFormAuthentication
  readonly embedded?: boolean
  readonly message: (key: MessageKey) => InterfaceMessageResolution
  readonly onAuthenticated: (snapshot: AuthenticationSnapshot) => void
}

const USERNAME_PATTERN = /^[A-Za-z][A-Za-z0-9._-]{3,63}$/
const USERNAME_HTML_PATTERN = '[A-Za-z][A-Za-z0-9._\\-]{3,63}'

function isValidUsername(value: string): boolean {
  return USERNAME_PATTERN.test(value)
}

export function WebPasswordSignInPanel({
  authentication,
  embedded = false,
  message,
  onAuthenticated,
}: WebPasswordSignInPanelProps) {
  const [username, setUsername] = useState('')
  const [password, setPassword] = useState('')
  const [passwordConfirmation, setPasswordConfirmation] = useState('')
  const [invitationCode, setInvitationCode] = useState('')
  const [showPassword, setShowPassword] = useState(false)
  const [recovery, setRecovery] = useState(false)
  const [invitationSetup, setInvitationSetup] = useState(false)
  const [invitationSetupComplete, setInvitationSetupComplete] = useState(false)
  const [recoverySent, setRecoverySent] = useState(false)
  const [mode, setMode] = useState<
    'legacy_otp' | 'credential_migration' | 'password_only' | null
  >(null)
  const [legacySetup, setLegacySetup] = useState(false)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState(false)
  const errorRef = useRef<HTMLParagraphElement>(null)
  const [providers, setProviders] = useState<FederatedProviderAvailability>({
    google: false,
    facebook: false,
  })
  const [secureContext] = useState(() => isPasswordSignInSecureContext())

  useEffect(() => {
    let active = true

    authentication
      .authenticationMode()
      .then((nextMode) => active && setMode(nextMode))
      .catch(() => active && setError(true))

    authentication
      .federatedProviders()
      .then((nextProviders) => active && setProviders(nextProviders))
      .catch(() => undefined)

    return () => {
      active = false
    }
  }, [authentication])

  useEffect(() => {
    if (!error) {
      return
    }

    errorRef.current?.scrollIntoView?.({ block: 'nearest' })
  }, [error])

  useEffect(() => {
    if (!embedded || mode === null || mode === 'legacy_otp' || legacySetup) {
      return
    }

    document.getElementById('web-password-username')?.focus()
  }, [embedded, mode, legacySetup])

  if (mode === 'legacy_otp' || legacySetup) {
    if (authentication.transport !== 'sanctum-cookie') {
      return (
        <p className="web-otp-sign-in__error" role="alert">
          <LocalizedMessage resolution={message('auth.password.error')} />
        </p>
      )
    }

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
          authentication={authentication as WebSessionAuthentication}
          message={message}
          onAuthenticated={onAuthenticated}
        />
      </>
    )
  }

  const beginFederated = (provider: FederatedProvider) => {
    setBusy(true)
    setError(false)
    void authentication
      .beginFederatedSignIn(provider)
      .catch(() => setError(true))
      .finally(() => setBusy(false))
  }

  const signIn = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    if (!isValidUsername(username.trim())) {
      setError(true)
      return
    }
    setBusy(true)
    setError(false)

    void authentication
      .signIn(username.trim(), password)
      .then(() => authentication.current())
      .then((snapshot) => {
        if (snapshot.status !== 'authenticated') {
          setError(true)
          return
        }

        setPassword('')
        onAuthenticated(snapshot)
      })
      .catch(() => setError(true))
      .finally(() => {
        setBusy(false)
      })
  }

  const requestRecovery = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    if (!isValidUsername(username.trim())) {
      setError(true)
      return
    }
    setBusy(true)
    setError(false)

    void authentication
      .requestPasswordRecovery(username.trim())
      .then(() => setRecoverySent(true))
      .catch(() => setError(true))
      .finally(() => setBusy(false))
  }

  const enrollWithInvitation = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setError(false)

    if (!isValidUsername(username.trim())) {
      setError(true)
      return
    }

    if (password !== passwordConfirmation) {
      setError(true)
      return
    }

    setBusy(true)
    void authentication
      .enrollWithInvitation(
        invitationCode.trim(),
        username.trim(),
        password,
      )
      .then(() => {
        setInvitationSetupComplete(true)
        setInvitationSetup(false)
        setInvitationCode('')
        setPassword('')
        setPasswordConfirmation('')
      })
      .catch(() => setError(true))
      .finally(() => {
        setBusy(false)
      })
  }

  return (
    <section
      aria-labelledby={
        embedded ? undefined : 'web-password-sign-in-title'
      }
      className={
        embedded
          ? 'web-password-sign-in web-password-sign-in--embedded'
          : 'web-password-sign-in'
      }
    >
      {embedded ? null : (
        <div aria-hidden="true" className="web-password-sign-in__visual">
          <span className="web-password-sign-in__brand-mark">GSE</span>
          <span className="web-password-sign-in__visual-ring" />
        </div>
      )}
      <div className="web-password-sign-in__card">
      {embedded ? null : (
        <>
          <h3 id="web-password-sign-in-title">
            <LocalizedMessage
              resolution={message(
                invitationSetup
                  ? 'auth.password.invitation_title'
                  : 'auth.password.title',
              )}
            />
          </h3>
          <p>
            <LocalizedMessage
              resolution={message(
                invitationSetup
                  ? 'auth.password.invitation_lead'
                  : 'auth.password.lead',
              )}
            />
          </p>
        </>
      )}
      {!secureContext ? (
        <p className="web-otp-sign-in__error" role="alert">
          <LocalizedMessage
            resolution={message('auth.password.https_required')}
          />
        </p>
      ) : null}

      {mode === 'credential_migration' ? (
        <button onClick={() => setLegacySetup(true)} type="button">
          <LocalizedMessage
            resolution={message('auth.password.existing_setup')}
          />
        </button>
      ) : null}

      <form
        className="web-otp-sign-in__form"
        onSubmit={
          invitationSetup
            ? enrollWithInvitation
            : recovery
              ? requestRecovery
              : signIn
        }
      >
        {invitationSetup ? (
          <>
            <label htmlFor="web-password-invitation-code">
              <LocalizedMessage
                resolution={message('auth.password.invitation_code')}
              />
            </label>
            <input
              autoCapitalize="none"
              autoComplete="off"
              disabled={busy}
              id="web-password-invitation-code"
              maxLength={128}
              minLength={12}
              onChange={(event) =>
                setInvitationCode(event.currentTarget.value)
              }
              required
              spellCheck={false}
              type="text"
              value={invitationCode}
            />
          </>
        ) : null}
        <label htmlFor="web-password-username">
          <LocalizedMessage resolution={message('auth.password.username')} />
        </label>
        <input
          aria-describedby={error ? 'web-password-error' : undefined}
          aria-invalid={error}
          autoCapitalize="none"
          autoComplete="username"
          disabled={busy}
          id="web-password-username"
          maxLength={64}
          minLength={4}
          onChange={(event) => setUsername(event.currentTarget.value)}
          pattern={USERNAME_HTML_PATTERN}
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
              aria-describedby={error ? 'web-password-error' : undefined}
              aria-invalid={error}
              autoComplete={
                invitationSetup ? 'new-password' : 'current-password'
              }
              disabled={busy}
              id="web-password-password"
              maxLength={512}
              onChange={(event) => setPassword(event.currentTarget.value)}
              required
              type={showPassword ? 'text' : 'password'}
              value={password}
            />
            {invitationSetup ? (
              <>
                <label htmlFor="web-password-confirmation">
                  <LocalizedMessage
                    resolution={message(
                      'auth.password.invitation_password_confirmation',
                    )}
                  />
                </label>
                <input
                  autoComplete="new-password"
                  disabled={busy}
                  id="web-password-confirmation"
                  maxLength={512}
                  onChange={(event) =>
                    setPasswordConfirmation(event.currentTarget.value)
                  }
                  required
                  type={showPassword ? 'text' : 'password'}
                  value={passwordConfirmation}
                />
              </>
            ) : null}
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

        {error ? (
          <p
            className="web-otp-sign-in__error"
            id="web-password-error"
            ref={errorRef}
            role="alert"
          >
            <LocalizedMessage resolution={message('auth.password.error')} />
          </p>
        ) : null}

        <button disabled={busy || recoverySent} type="submit">
          <LocalizedMessage
            resolution={message(
              invitationSetup
                ? 'auth.password.invitation_submit'
                : recovery
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
            if (invitationSetup) {
              setInvitationSetup(false)
            } else {
              setRecovery((current) => !current)
            }
            setRecoverySent(false)
            setError(false)
          }}
          type="button"
        >
          <LocalizedMessage
            resolution={message(
              recovery || invitationSetup
                ? 'auth.password.back'
                : 'auth.password.recovery_open',
            )}
          />
        </button>
        {!recovery && !invitationSetup ? (
          <button
            disabled={busy}
            onClick={() => {
              setInvitationSetup(true)
              setInvitationSetupComplete(false)
              setError(false)
            }}
            type="button"
          >
            <LocalizedMessage
              resolution={message('auth.password.invitation_open')}
            />
          </button>
        ) : null}
      </form>

      {!recovery && !invitationSetup && (providers.google || providers.facebook) ? (
        <div className="web-password-sign-in__social">
          <p>
            <LocalizedMessage resolution={message('auth.social.or')} />
          </p>
          {providers.google ? (
            <button
              disabled={busy}
              onClick={() => beginFederated('google')}
              type="button"
            >
              <LocalizedMessage resolution={message('auth.social.google')} />
            </button>
          ) : null}
          {providers.facebook ? (
            <button
              disabled={busy}
              onClick={() => beginFederated('facebook')}
              type="button"
            >
              <LocalizedMessage
                resolution={message('auth.social.facebook')}
              />
            </button>
          ) : null}
        </div>
      ) : null}

      {recoverySent ? (
        <p role="status">
          <LocalizedMessage
            resolution={message('auth.password.recovery_sent')}
          />
        </p>
      ) : null}
      {invitationSetupComplete ? (
        <p role="status">
          <LocalizedMessage
            resolution={message('auth.password.invitation_complete')}
          />
        </p>
      ) : null}
      </div>
    </section>
  )
}
