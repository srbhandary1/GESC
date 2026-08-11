import type {
  MessageKey,
  ResolutionPolicy,
  SupportedLocale,
} from '@global-english/localization'
import { useEffect, useState, type FormEvent } from 'react'
import type { WebSessionAuthentication } from '../auth/AuthenticationAdapter'
import {
  resolveInterfaceMessage,
  type InterfaceMessageResolution,
} from '../localization/interfaceLocalization'
import { LocalizedMessage } from '../localization/LocalizedMessage'

export interface PrivilegedMfaPanelProps {
  authentication: WebSessionAuthentication
  locale: SupportedLocale
  localizationPolicy: ResolutionPolicy
  onCredentialsRevoked: () => void
}

type MfaStatus = Awaited<
  ReturnType<WebSessionAuthentication['privilegedMfaStatus']>
>
type Enrollment = Awaited<
  ReturnType<WebSessionAuthentication['beginPrivilegedMfaEnrollment']>
>
type Challenge = Awaited<
  ReturnType<WebSessionAuthentication['createPrivilegedMfaChallenge']>
>

export function PrivilegedMfaPanel({
  authentication,
  locale,
  localizationPolicy,
  onCredentialsRevoked,
}: PrivilegedMfaPanelProps) {
  const message = (key: MessageKey): InterfaceMessageResolution =>
    resolveInterfaceMessage(key, locale, localizationPolicy)
  const [status, setStatus] = useState<MfaStatus | null>(null)
  const [enrollment, setEnrollment] = useState<Enrollment | null>(null)
  const [challenge, setChallenge] = useState<Challenge | null>(null)
  const [method, setMethod] = useState<'totp' | 'recovery_code'>('totp')
  const [code, setCode] = useState('')
  const [recoveryCodes, setRecoveryCodes] = useState<readonly string[]>([])
  const [credentialsRevoked, setCredentialsRevoked] = useState(false)
  const [revokeArmed, setRevokeArmed] = useState(false)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState(false)

  useEffect(() => {
    let active = true

    authentication
      .privilegedMfaStatus()
      .then((nextStatus) => {
        if (active) {
          setStatus(nextStatus)
          setError(false)
        }
      })
      .catch(() => {
        if (active) {
          setError(true)
        }
      })

    return () => {
      active = false
    }
  }, [authentication])

  const refreshStatus = async () => {
    setStatus(await authentication.privilegedMfaStatus())
  }

  const run = async (operation: () => Promise<void>) => {
    setBusy(true)
    setError(false)

    try {
      await operation()
    } catch {
      setError(true)
    } finally {
      setCode('')
      setBusy(false)
    }
  }

  const beginEnrollment = () =>
    run(async () => {
      setEnrollment(await authentication.beginPrivilegedMfaEnrollment())
      setChallenge(null)
      setRecoveryCodes([])
    })

  const confirmEnrollment = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()

    if (enrollment === null) {
      return
    }

    void run(async () => {
      const result = await authentication.confirmPrivilegedMfaEnrollment(
        enrollment.factor_id,
        enrollment.challenge_id,
        code,
      )
      setEnrollment(null)
      setRecoveryCodes(result.recovery_codes)
      setCredentialsRevoked(result.sessions_revoked)

      if (!result.sessions_revoked) {
        await refreshStatus()
      }
    })
  }

  const createChallenge = () =>
    run(async () => {
      setChallenge(await authentication.createPrivilegedMfaChallenge())
    })

  const verifyChallenge = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()

    if (challenge === null) {
      return
    }

    void run(async () => {
      await authentication.verifyPrivilegedMfaChallenge(
        challenge.challenge_id,
        method,
        code,
      )
      setChallenge(null)
      await refreshStatus()
    })
  }

  const regenerateRecoveryCodes = () =>
    run(async () => {
      const result =
        await authentication.regeneratePrivilegedMfaRecoveryCodes()
      setRecoveryCodes(result.recovery_codes)
      setCredentialsRevoked(true)
    })

  const revokeFactor = () =>
    run(async () => {
      await authentication.revokePrivilegedMfaFactor()
      onCredentialsRevoked()
    })

  const finishRecoveryCodeDisplay = () => {
    setRecoveryCodes([])

    if (credentialsRevoked) {
      onCredentialsRevoked()
    }
  }

  return (
    <section
      aria-labelledby="operator-mfa-title"
      className="operator-panel operator-mfa"
    >
      <h4 id="operator-mfa-title">
        <LocalizedMessage resolution={message('operator.mfa.title')} />
      </h4>
      <p>
        <LocalizedMessage resolution={message('operator.mfa.lead')} />
      </p>

      {status === null && !error ? (
        <p>
          <LocalizedMessage resolution={message('operator.mfa.loading')} />
        </p>
      ) : null}
      {error ? (
        <p role="alert">
          <LocalizedMessage resolution={message('operator.mfa.error')} />
        </p>
      ) : null}

      {status !== null ? (
        <p className="operator-mfa__status">
          <LocalizedMessage resolution={message('operator.mfa.status')} />
          {': '}
          <strong>{status.factor_status}</strong>
          {status.factor_status === 'active'
            ? ` · ${String(status.recovery_codes_remaining)} recovery codes`
            : ''}
          {status.fresh_assertion_scope === null
            ? ''
            : ` · ${status.fresh_assertion_scope}`}
        </p>
      ) : null}

      {status?.requires_support ? (
        <p role="status">
          <LocalizedMessage
            resolution={message('operator.mfa.support_required')}
          />
        </p>
      ) : null}

      {recoveryCodes.length > 0 ? (
        <div aria-live="polite" className="operator-mfa__recovery">
          <h5>
            <LocalizedMessage
              resolution={message('operator.mfa.recovery_title')}
            />
          </h5>
          <p>
            <LocalizedMessage
              resolution={message('operator.mfa.recovery_notice')}
            />
          </p>
          <ol className="operator-mfa__codes">
            {recoveryCodes.map((recoveryCode) => (
              <li key={recoveryCode}>
                <code>{recoveryCode}</code>
              </li>
            ))}
          </ol>
          {credentialsRevoked ? (
            <p>
              <LocalizedMessage
                resolution={message('operator.mfa.sessions_revoked')}
              />
            </p>
          ) : null}
          <button onClick={finishRecoveryCodeDisplay} type="button">
            <LocalizedMessage
              resolution={message('operator.mfa.recovery_saved')}
            />
          </button>
        </div>
      ) : null}

      {enrollment === null && recoveryCodes.length === 0 ? (
        <div className="operator-mfa__actions">
          {status?.can_enroll ? (
            <button disabled={busy} onClick={beginEnrollment} type="button">
              <LocalizedMessage
                resolution={message(
                  status.factor_status === 'active'
                    ? 'operator.mfa.replace'
                    : 'operator.mfa.enroll',
                )}
              />
            </button>
          ) : null}
          {status?.factor_status === 'active' && challenge === null ? (
            <button disabled={busy} onClick={createChallenge} type="button">
              <LocalizedMessage
                resolution={message('operator.mfa.verify_start')}
              />
            </button>
          ) : null}
        </div>
      ) : null}

      {enrollment !== null ? (
        <form className="operator-mfa__form" onSubmit={confirmEnrollment}>
          <p>
            <LocalizedMessage
              resolution={message('operator.mfa.enrollment_notice')}
            />
          </p>
          <code className="operator-mfa__uri">
            {enrollment.provisioning_uri}
          </code>
          <label htmlFor="operator-mfa-enrollment-code">
            <LocalizedMessage
              resolution={message('operator.mfa.totp_label')}
            />
          </label>
          <input
            autoComplete="one-time-code"
            id="operator-mfa-enrollment-code"
            inputMode="numeric"
            maxLength={6}
            onChange={(event) => setCode(event.currentTarget.value)}
            pattern="[0-9]{6}"
            required
            type="text"
            value={code}
          />
          <button disabled={busy} type="submit">
            <LocalizedMessage
              resolution={message('operator.mfa.confirm')}
            />
          </button>
        </form>
      ) : null}

      {challenge !== null ? (
        <form className="operator-mfa__form" onSubmit={verifyChallenge}>
          <label htmlFor="operator-mfa-method">
            <LocalizedMessage
              resolution={message('operator.mfa.method_label')}
            />
          </label>
          <select
            id="operator-mfa-method"
            onChange={(event) => {
              setMethod(
                event.currentTarget.value === 'recovery_code'
                  ? 'recovery_code'
                  : 'totp',
              )
              setCode('')
            }}
            value={method}
          >
            <option value="totp">
              {message('operator.mfa.method_totp').text}
            </option>
            <option value="recovery_code">
              {message('operator.mfa.method_recovery').text}
            </option>
          </select>
          <label htmlFor="operator-mfa-assertion-code">
            <LocalizedMessage
              resolution={message('operator.mfa.code_label')}
            />
          </label>
          <input
            autoComplete={method === 'totp' ? 'one-time-code' : 'off'}
            id="operator-mfa-assertion-code"
            inputMode={method === 'totp' ? 'numeric' : 'text'}
            maxLength={64}
            onChange={(event) => setCode(event.currentTarget.value)}
            required
            type="text"
            value={code}
          />
          <button disabled={busy} type="submit">
            <LocalizedMessage
              resolution={message('operator.mfa.verify')}
            />
          </button>
        </form>
      ) : null}

      {status?.fresh_assertion_scope === 'privileged_actions' &&
      recoveryCodes.length === 0 ? (
        <div className="operator-mfa__management">
          <button
            disabled={busy}
            onClick={regenerateRecoveryCodes}
            type="button"
          >
            <LocalizedMessage
              resolution={message('operator.mfa.regenerate')}
            />
          </button>
          <label>
            <input
              checked={revokeArmed}
              onChange={(event) => setRevokeArmed(event.currentTarget.checked)}
              type="checkbox"
            />{' '}
            <LocalizedMessage
              resolution={message('operator.mfa.revoke_confirm')}
            />
          </label>
          <button
            disabled={busy || !revokeArmed}
            onClick={revokeFactor}
            type="button"
          >
            <LocalizedMessage
              resolution={message('operator.mfa.revoke')}
            />
          </button>
        </div>
      ) : null}
    </section>
  )
}
