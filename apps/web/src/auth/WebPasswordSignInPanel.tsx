import type { MessageKey } from '@global-english/localization'
import { useEffect, useState, type FormEvent } from 'react'
import type {
  AuthenticationSnapshot,
  WebSessionAuthentication,
} from './AuthenticationAdapter'
import type { InterfaceMessageResolution } from '../localization/interfaceLocalization'
import { LocalizedMessage } from '../localization/LocalizedMessage'

interface WebPasswordSignInPanelProps {
  readonly authentication: WebSessionAuthentication
  readonly message: (key: MessageKey) => InterfaceMessageResolution
  readonly onAuthenticated: (snapshot: AuthenticationSnapshot) => void
}

const USERNAME_PATTERN = /^[A-Za-z][A-Za-z0-9._-]{3,63}$/

function isValidUsername(value: string): boolean {
  return USERNAME_PATTERN.test(value)
}

export function WebPasswordSignInPanel({
  authentication,
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

  if (mode === 'legacy_otp') {
    return (
      <p className="web-password-sign-in__error" role="status">
        <LocalizedMessage
          resolution={message('auth.password.otp_unavailable')}
        />
      </p>
    )
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
      .then(onAuthenticated)
      .catch(() => setError(true))
      .finally(() => {
        setPassword('')
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
        setPasswordConfirmation('')
      })
      .catch(() => setError(true))
      .finally(() => {
        setPassword('')
        setPasswordConfirmation('')
        setBusy(false)
      })
  }

  return (
    <section
      aria-labelledby="web-password-sign-in-title"
      className="web-password-sign-in web-password-sign-in--embedded"
    >
      <div className="web-password-sign-in__card">
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

        <form
          className="web-password-sign-in__form"
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
            autoCapitalize="none"
            autoComplete="username"
            disabled={busy}
            id="web-password-username"
            maxLength={64}
            minLength={4}
            onChange={(event) => setUsername(event.currentTarget.value)}
            pattern="[A-Za-z][A-Za-z0-9._-]{3,63}"
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
        {error ? (
          <p className="web-password-sign-in__error" role="alert">
            <LocalizedMessage resolution={message('auth.password.error')} />
          </p>
        ) : null}
      </div>
    </section>
  )
}
