import type {
  MessageKey,
  ResolutionPolicy,
  SupportedLocale,
} from '@global-english/localization'
import { useEffect, useState, type FormEvent } from 'react'
import type {
  PasswordAuthenticationStatus,
  WebSessionAuthentication,
} from '../auth/AuthenticationAdapter'
import { FederatedAccountsPanel } from '../auth/FederatedAccountsPanel'
import {
  resolveInterfaceMessage,
  type InterfaceMessageResolution,
} from '../localization/interfaceLocalization'
import { LocalizedMessage } from '../localization/LocalizedMessage'

interface PasswordAccountSecurityPanelProps {
  readonly authentication: WebSessionAuthentication
  readonly locale: SupportedLocale
  readonly localizationPolicy: ResolutionPolicy
  readonly onCredentialsRevoked: () => void
}

type SecurityError = 'confirmation' | 'generic' | 'setup'

export function PasswordAccountSecurityPanel({
  authentication,
  locale,
  localizationPolicy,
  onCredentialsRevoked,
}: PasswordAccountSecurityPanelProps) {
  const message = (key: MessageKey): InterfaceMessageResolution =>
    resolveInterfaceMessage(key, locale, localizationPolicy)
  const [status, setStatus] = useState<PasswordAuthenticationStatus | null>(
    null,
  )
  const [username, setUsername] = useState('')
  const [password, setPassword] = useState('')
  const [newPassword, setNewPassword] = useState('')
  const [showNewPassword, setShowNewPassword] = useState(false)
  const [sessions, setSessions] = useState<
    Awaited<ReturnType<WebSessionAuthentication['sessions']>>
  >([])
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<SecurityError | null>(null)
  const [confirmed, setConfirmed] = useState(false)
  const currentSession = sessions.find((session) => session.current)
  const isPrivilegedSession = currentSession?.privileged === true

  const refresh = async () => {
    const [nextStatus, nextSessions] = await Promise.all([
      authentication.passwordStatus(),
      authentication.sessions(),
    ])
    setStatus(nextStatus)
    setUsername(nextStatus.username ?? '')
    setSessions(nextSessions)
  }

  useEffect(() => {
    let active = true

    Promise.all([authentication.passwordStatus(), authentication.sessions()])
      .then(([nextStatus, nextSessions]) => {
        if (active) {
          setStatus(nextStatus)
          setUsername(nextStatus.username ?? '')
          setSessions(nextSessions)
        }
      })
      .catch(() => active && setError('generic'))

    return () => {
      active = false
    }
  }, [authentication])

  const run = async (
    operation: () => Promise<void>,
    failure: SecurityError,
  ) => {
    setBusy(true)
    setError(null)
    setConfirmed(false)

    try {
      await operation()
    } catch {
      setError(failure)
    } finally {
      setPassword('')
      setNewPassword('')
      setBusy(false)
    }
  }

  const establish = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    void run(
      async () => {
        await authentication.establishPassword(username, newPassword)
        onCredentialsRevoked()
      },
      'setup',
    )
  }

  const confirm = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    void run(
      async () => {
        await authentication.confirmPassword(password)
        setConfirmed(true)
        await refresh()
      },
      'confirmation',
    )
  }

  const changePassword = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    void run(
      async () => {
        await authentication.changePassword(password, newPassword)
        onCredentialsRevoked()
      },
      'generic',
    )
  }

  const changeUsername = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    void run(
      async () => {
        await authentication.confirmPassword(password)
        await authentication.changeUsername(username)
        onCredentialsRevoked()
      },
      'generic',
    )
  }

  return (
    <section
      aria-labelledby="operator-account-settings-title"
      className="operator-panel operator-mfa"
    >
      <h3 id="operator-account-settings-title">
        <LocalizedMessage resolution={message('operator.password.title')} />
      </h3>
      <p>
        <LocalizedMessage
          resolution={message(
            isPrivilegedSession
              ? 'operator.password.lead'
              : 'operator.password.lead_ordinary',
          )}
        />
      </p>

      {status === null && error === null ? (
        <p>
          <LocalizedMessage
            resolution={message('operator.password.loading')}
          />
        </p>
      ) : null}
      {error !== null ? (
        <p role="alert">
          <LocalizedMessage
            resolution={message(
              error === 'setup'
                ? 'operator.password.setup_error'
                : error === 'confirmation'
                  ? 'operator.password.confirm_error'
                  : 'operator.password.error',
            )}
          />
        </p>
      ) : null}

      {status !== null ? (
        <p>
          <LocalizedMessage resolution={message('operator.password.status')} />
          {': '}
          <strong>
            {status.credential_ready
              ? status.username
              : message('operator.password.not_ready').text}
          </strong>
        </p>
      ) : null}

      {status?.credential_ready === false &&
      status.mode === 'credential_migration' ? (
        <form className="operator-mfa__form" onSubmit={establish}>
          <p className="operator-mfa__hint">
            <LocalizedMessage
              resolution={message('operator.password.setup_hint')}
            />
          </p>
          <label htmlFor="operator-password-establish-username">
            <LocalizedMessage
              resolution={message('auth.password.username')}
            />
          </label>
          <input
            aria-describedby="operator-password-establish-username-hint"
            autoCapitalize="none"
            autoComplete="username"
            id="operator-password-establish-username"
            maxLength={64}
            onChange={(event) => setUsername(event.currentTarget.value)}
            pattern="[a-zA-Z][a-zA-Z0-9._-]{3,63}"
            required
            spellCheck={false}
            value={username}
          />
          <p
            className="operator-mfa__hint"
            id="operator-password-establish-username-hint"
          >
            <LocalizedMessage
              resolution={message('operator.password.username_hint')}
            />
          </p>
          <label htmlFor="operator-password-establish-password">
            <LocalizedMessage
              resolution={message('operator.password.new_password')}
            />
          </label>
          <input
            aria-describedby="operator-password-establish-password-hint"
            autoComplete="new-password"
            id="operator-password-establish-password"
            maxLength={128}
            minLength={15}
            onChange={(event) => setNewPassword(event.currentTarget.value)}
            required
            type={showNewPassword ? 'text' : 'password'}
            value={newPassword}
          />
          <p
            className="operator-mfa__hint"
            id="operator-password-establish-password-hint"
          >
            <LocalizedMessage
              resolution={message('operator.password.password_hint')}
            />
          </p>
          <label className="operator-mfa__checkbox">
            <input
              checked={showNewPassword}
              disabled={busy}
              onChange={(event) =>
                setShowNewPassword(event.currentTarget.checked)
              }
              type="checkbox"
            />{' '}
            <LocalizedMessage resolution={message('auth.password.show')} />
          </label>
          <button disabled={busy} type="submit">
            <LocalizedMessage
              resolution={message('operator.password.establish')}
            />
          </button>
        </form>
      ) : null}

      {status?.credential_ready ? (
        <>
          {isPrivilegedSession ? (
            <form className="operator-mfa__form" onSubmit={confirm}>
              <p className="operator-mfa__hint">
                <LocalizedMessage
                  resolution={message('operator.password.confirm_hint')}
                />
              </p>
              <label htmlFor="operator-password-confirm">
                <LocalizedMessage
                  resolution={message('operator.password.current_password')}
                />
              </label>
              <input
                autoComplete="current-password"
                id="operator-password-confirm"
                onChange={(event) => setPassword(event.currentTarget.value)}
                required
                type="password"
                value={password}
              />
              <button disabled={busy} type="submit">
                <LocalizedMessage
                  resolution={message('operator.password.confirm')}
                />
              </button>
              {confirmed ? (
                <p role="status">
                  <LocalizedMessage
                    resolution={message('operator.password.confirmed')}
                  />
                </p>
              ) : null}
            </form>
          ) : null}

          <form className="operator-mfa__form" onSubmit={changePassword}>
            <label htmlFor="operator-password-current-change">
              <LocalizedMessage
                resolution={message('operator.password.current_password')}
              />
            </label>
            <input
              autoComplete="current-password"
              id="operator-password-current-change"
              onChange={(event) => setPassword(event.currentTarget.value)}
              required
              type="password"
              value={password}
            />
            <label htmlFor="operator-password-new-change">
              <LocalizedMessage
                resolution={message('operator.password.new_password')}
              />
            </label>
            <input
              autoComplete="new-password"
              id="operator-password-new-change"
              minLength={15}
              onChange={(event) => setNewPassword(event.currentTarget.value)}
              required
              type="password"
              value={newPassword}
            />
            <button disabled={busy} type="submit">
              <LocalizedMessage
                resolution={message('operator.password.change_password')}
              />
            </button>
          </form>

          {isPrivilegedSession ? (
            <form className="operator-mfa__form" onSubmit={changeUsername}>
            <label htmlFor="operator-password-username-change">
              <LocalizedMessage
                resolution={message('auth.password.username')}
              />
            </label>
            <input
              autoComplete="username"
              id="operator-password-username-change"
              onChange={(event) => setUsername(event.currentTarget.value)}
              required
              value={username}
            />
            <label htmlFor="operator-password-username-confirm">
              <LocalizedMessage
                resolution={message('operator.password.current_password')}
              />
            </label>
            <input
              autoComplete="current-password"
              id="operator-password-username-confirm"
              onChange={(event) => setPassword(event.currentTarget.value)}
              required
              type="password"
              value={password}
            />
            <button disabled={busy} type="submit">
              <LocalizedMessage
                resolution={message('operator.password.change_username')}
              />
            </button>
            </form>
          ) : null}
        </>
      ) : null}

      <h4>
        <LocalizedMessage resolution={message('operator.password.sessions')} />
      </h4>
      <ul>
        {sessions.map((session) => (
          <li key={session.id}>
            {session.current
              ? message('operator.password.current_session').text
              : session.last_activity_at}
            {!session.current ? (
              <button
                disabled={busy}
                onClick={() =>
                  void run(
                    async () => {
                      await authentication.revokeSession(session.id)
                      await refresh()
                    },
                    'generic',
                  )
                }
                type="button"
              >
                <LocalizedMessage
                  resolution={message('operator.password.revoke_session')}
                />
              </button>
            ) : null}
          </li>
        ))}
      </ul>
      <FederatedAccountsPanel
        authentication={authentication}
        message={message}
      />
    </section>
  )
}
