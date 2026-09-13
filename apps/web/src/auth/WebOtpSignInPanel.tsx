import type { MessageKey } from '@global-english/localization'
import { useEffect, useRef, useState, type FormEvent } from 'react'
import type {
  AuthenticationSnapshot,
  WebSessionAuthentication,
} from './AuthenticationAdapter'
import type { InterfaceMessageResolution } from '../localization/interfaceLocalization'
import { LocalizedMessage } from '../localization/LocalizedMessage'

interface WebOtpSignInPanelProps {
  readonly authentication: WebSessionAuthentication
  readonly message: (key: MessageKey) => InterfaceMessageResolution
  readonly onAuthenticated: (snapshot: AuthenticationSnapshot) => void
}

export function WebOtpSignInPanel({
  authentication,
  message,
  onAuthenticated,
}: WebOtpSignInPanelProps) {
  const [mobileNumber, setMobileNumber] = useState('')
  const [invitationCode, setInvitationCode] = useState('')
  const [otpCode, setOtpCode] = useState('')
  const [challengeId, setChallengeId] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState(false)
  const otpInput = useRef<HTMLInputElement>(null)

  useEffect(() => {
    if (challengeId !== null) {
      otpInput.current?.focus()
    }
  }, [challengeId])

  const requestOtp = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setBusy(true)
    setError(false)

    void authentication
      .requestOtp(mobileNumber.trim())
      .then((challenge) => {
        setChallengeId(challenge.challenge_id)
      })
      .catch(() => {
        setError(true)
      })
      .finally(() => {
        setBusy(false)
      })
  }

  const verifyOtp = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()

    if (challengeId === null) {
      return
    }

    setBusy(true)
    setError(false)
    const normalizedInvitationCode = invitationCode.trim()

    void authentication
      .verifyOtp(
        challengeId,
        mobileNumber.trim(),
        otpCode.trim(),
        normalizedInvitationCode === ''
          ? undefined
          : normalizedInvitationCode,
      )
      .then(() => authentication.current())
      .then((snapshot) => {
        setInvitationCode('')
        setOtpCode('')
        onAuthenticated(snapshot)
      })
      .catch(() => {
        setOtpCode('')
        setError(true)
      })
      .finally(() => {
        setBusy(false)
      })
  }

  const editDetails = () => {
    setChallengeId(null)
    setOtpCode('')
    setError(false)
  }

  return (
    <section
      aria-labelledby="web-otp-sign-in-title"
      className="web-otp-sign-in"
    >
      <h3 id="web-otp-sign-in-title">
        <LocalizedMessage resolution={message('auth.otp.title')} />
      </h3>
      <p>
        <LocalizedMessage resolution={message('auth.otp.lead')} />
      </p>

      {challengeId === null ? (
        <form className="web-otp-sign-in__form" onSubmit={requestOtp}>
          <label htmlFor="web-otp-mobile-number">
            <LocalizedMessage
              resolution={message('auth.otp.mobile_label')}
            />
          </label>
          <input
            aria-describedby="web-otp-mobile-hint"
            autoComplete="tel"
            disabled={busy}
            id="web-otp-mobile-number"
            inputMode="tel"
            maxLength={24}
            minLength={8}
            onChange={(event) => setMobileNumber(event.currentTarget.value)}
            required
            type="tel"
            value={mobileNumber}
          />
          <p id="web-otp-mobile-hint">
            <LocalizedMessage
              resolution={message('auth.otp.mobile_hint')}
            />
          </p>

          <label htmlFor="web-otp-invitation-code">
            <LocalizedMessage
              resolution={message('auth.otp.invitation_label')}
            />
          </label>
          <input
            aria-describedby="web-otp-invitation-hint"
            autoCapitalize="none"
            autoComplete="off"
            dir="ltr"
            disabled={busy}
            id="web-otp-invitation-code"
            maxLength={256}
            onChange={(event) => setInvitationCode(event.currentTarget.value)}
            spellCheck={false}
            type="text"
            value={invitationCode}
          />
          <p id="web-otp-invitation-hint">
            <LocalizedMessage
              resolution={message('auth.otp.invitation_hint')}
            />
          </p>

          <button disabled={busy} type="submit">
            <LocalizedMessage
              resolution={message(
                busy ? 'auth.otp.sending' : 'auth.otp.send',
              )}
            />
          </button>
        </form>
      ) : (
        <form className="web-otp-sign-in__form" onSubmit={verifyOtp}>
          <p className="web-otp-sign-in__sent">
            <LocalizedMessage resolution={message('auth.otp.sent')} />
          </p>
          {invitationCode.trim() === '' ? null : (
            <p className="web-otp-sign-in__invite-ready">
              <LocalizedMessage
                resolution={message('auth.otp.invitation_ready')}
              />
            </p>
          )}
          <label htmlFor="web-otp-code">
            <LocalizedMessage resolution={message('auth.otp.code_label')} />
          </label>
          <input
            autoComplete="one-time-code"
            disabled={busy}
            id="web-otp-code"
            inputMode="numeric"
            maxLength={12}
            onChange={(event) => setOtpCode(event.currentTarget.value)}
            ref={otpInput}
            required
            type="text"
            value={otpCode}
          />
          <div className="web-otp-sign-in__actions">
            <button disabled={busy} type="submit">
              <LocalizedMessage
                resolution={message(
                  busy ? 'auth.otp.verifying' : 'auth.otp.verify',
                )}
              />
            </button>
            <button disabled={busy} onClick={editDetails} type="button">
              <LocalizedMessage
                resolution={message('auth.otp.edit_details')}
              />
            </button>
          </div>
        </form>
      )}

      {error ? (
        <p className="web-otp-sign-in__error" role="alert">
          <LocalizedMessage resolution={message('auth.otp.error')} />
        </p>
      ) : null}
    </section>
  )
}
