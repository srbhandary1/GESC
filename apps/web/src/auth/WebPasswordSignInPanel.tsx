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
import type { DirectRegistrationPlan } from './directRegistrationPlan'
import { WebOtpSignInPanel } from './WebOtpSignInPanel'
import { isPasswordSignInSecureContext } from './passwordSignInSecureContext'

interface WebPasswordSignInPanelProps {
  readonly authentication: AuthenticationAdapter & PasswordFormAuthentication
  readonly embedded?: boolean
  readonly message: (key: MessageKey, parameters?: Record<string, string>) => InterfaceMessageResolution
  readonly onAuthenticated: (snapshot: AuthenticationSnapshot) => void
  readonly onLeavePaidRegistration?: () => void
  readonly onStartPaidRegistration?: () => void
  readonly paidPlanCode?: string | null
}

const LETTER_USERNAME_PATTERN = /^[A-Za-z][A-Za-z0-9._-]{3,63}$/
const LETTER_USERNAME_HTML_PATTERN = '[A-Za-z][A-Za-z0-9._\\-]{3,63}'
const IDENTIFIER_PATTERN = /^([A-Za-z][A-Za-z0-9._-]{3,63}|\+?[0-9]{7,20})$/
const IDENTIFIER_HTML_PATTERN =
  '([A-Za-z][A-Za-z0-9._\\-]{3,63}|\\+?[0-9]{7,20})'

function isValidUsername(value: string, allowPhone: boolean): boolean {
  return (allowPhone ? IDENTIFIER_PATTERN : LETTER_USERNAME_PATTERN).test(
    value,
  )
}

function inr(paise: number): string {
  return new Intl.NumberFormat('en-IN', {
    currency: 'INR',
    minimumFractionDigits: 2,
    style: 'currency',
  }).format(paise / 100)
}

function planCopyKey(code: string): MessageKey | null {
  if (code === 'demo') return 'marketing.plan.demo.name'
  if (code === 'monthly_modules') return 'marketing.plan.modules.name'
  if (code === 'monthly_modules_roleplay') return 'marketing.plan.premium.name'
  return null
}

function planLeadKey(code: string): MessageKey | null {
  if (code === 'demo') return 'marketing.plan.demo.lead'
  if (code === 'monthly_modules') return 'marketing.plan.modules.lead'
  if (code === 'monthly_modules_roleplay') return 'marketing.plan.premium.lead'
  return null
}

export function WebPasswordSignInPanel({
  authentication,
  embedded = false,
  message,
  onAuthenticated,
  onLeavePaidRegistration,
  onStartPaidRegistration,
  paidPlanCode = null,
}: WebPasswordSignInPanelProps) {
  const [username, setUsername] = useState('')
  const [password, setPassword] = useState('')
  const [passwordConfirmation, setPasswordConfirmation] = useState('')
  const [invitationCode, setInvitationCode] = useState('')
  const [showPassword, setShowPassword] = useState(false)
  const [recovery, setRecovery] = useState(false)
  const [invitationSetup, setInvitationSetup] = useState(false)
  const [localPaidJoin, setLocalPaidJoin] = useState(false)
  const paidJoin = paidPlanCode !== null || localPaidJoin
  const [plans, setPlans] = useState<readonly DirectRegistrationPlan[]>([])
  const [planId, setPlanId] = useState('')
  const [invitationSetupComplete, setInvitationSetupComplete] = useState(false)
  const [recoverySent, setRecoverySent] = useState(false)
  const [mode, setMode] = useState<
    'legacy_otp' | 'credential_migration' | 'password_only' | null
  >(null)
  const [legacySetup, setLegacySetup] = useState(false)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<false | 'form' | 'mismatch' | 'payment'>(false)
  const [errorMode, setErrorMode] = useState(paidJoin)
  // Clear stale payment errors before rendering a different route, including browser Back.
  if (errorMode !== paidJoin) {
    setErrorMode(paidJoin)
    setError(false)
  }
  const errorRef = useRef<HTMLParagraphElement>(null)
  const [providers, setProviders] = useState<FederatedProviderAvailability>({
    google: true,
    facebook: true,
    instagram: true,
  })
  const [secureContext] = useState(() => isPasswordSignInSecureContext())

  useEffect(() => {
    if (!paidJoin || !('listDirectPlans' in authentication)) {
      return
    }
    const listDirectPlans = authentication.listDirectPlans
    if (typeof listDirectPlans !== 'function') {
      return
    }
    let active = true
    void Promise.resolve(
      listDirectPlans.call(authentication) as Promise<readonly DirectRegistrationPlan[]>,
    )
      .then((nextPlans) => {
        if (!active) {
          return
        }
        setPlans(nextPlans)
        setPlanId((current) => {
          const requested = nextPlans.find((plan) => plan.code === paidPlanCode)
          if (requested !== undefined) {
            return requested.id
          }
          if (current !== '' && nextPlans.some((plan) => plan.id === current)) {
            return current
          }
          return (
            nextPlans.find((plan) => plan.recommended)?.id ??
            nextPlans[0]?.id ??
            ''
          )
        })
      })
      .catch(() => active && setError('payment'))
    return () => {
      active = false
    }
  }, [authentication, paidJoin, paidPlanCode])

  useEffect(() => {
    let active = true

    authentication
      .authenticationMode()
      .then((nextMode) => active && setMode(nextMode))
      .catch(() => active && setError('form'))

    authentication
      .federatedProviders()
      .then((nextProviders) => {
        if (!active) {
          return
        }
        if (
          nextProviders.google ||
          nextProviders.facebook ||
          nextProviders.instagram === true
        ) {
          setProviders({
            google: nextProviders.google,
            facebook: nextProviders.facebook,
            instagram: nextProviders.instagram ?? true,
          })
        }
      })
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
    if (
      !embedded ||
      paidJoin ||
      mode === null ||
      mode === 'legacy_otp' ||
      legacySetup ||
      document
        .querySelector('.ge-application-frame')
        ?.classList.contains('ge-application-frame--native-android')
    ) {
      return
    }

    const active = document.activeElement
    if (
      active instanceof HTMLInputElement ||
      active instanceof HTMLSelectElement ||
      active instanceof HTMLTextAreaElement
    ) {
      return
    }

    document.getElementById('web-password-username')?.focus()
  }, [embedded, mode, legacySetup, paidJoin])

  const selectedPlan = plans.find((plan) => plan.id === planId)
  const selectedNameKey =
    selectedPlan === undefined ? null : planCopyKey(selectedPlan.code)
  const selectedPlanName =
    selectedNameKey === null
      ? (selectedPlan?.name ?? '')
      : message(selectedNameKey).text

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
    if (paidJoin) {
      if (planId === '') {
        setBusy(false)
        setError('payment')
        return
      }
      void authentication
        .beginFederatedSignIn(provider, {
          intent: 'register',
          planCode: selectedPlan?.code,
          planId,
        })
        .catch(() => setError('payment'))
        .finally(() => setBusy(false))
      return
    }
    void authentication
      .beginFederatedSignIn(provider)
      .catch(() => setError('form'))
      .finally(() => setBusy(false))
  }

  const signIn = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    if (!isValidUsername(username.trim(), true)) {
      setError('form')
      return
    }
    setBusy(true)
    setError(false)

    void authentication
      .signIn(username.trim(), password)
      .then(() => authentication.current())
      .then((snapshot) => {
        if (snapshot.status !== 'authenticated') {
          setError('form')
          return
        }

        setPassword('')
        onAuthenticated(snapshot)
      })
      .catch(() => setError('form'))
      .finally(() => {
        setBusy(false)
      })
  }

  const requestRecovery = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    if (!isValidUsername(username.trim(), true)) {
      setError('form')
      return
    }
    setBusy(true)
    setError(false)

    void authentication
      .requestPasswordRecovery(username.trim())
      .then(() => setRecoverySent(true))
      .catch(() => setError('form'))
      .finally(() => setBusy(false))
  }

  const payAndRegister = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    const registerWithPayment =
      'registerWithPayment' in authentication &&
      typeof authentication.registerWithPayment === 'function'
        ? (authentication.registerWithPayment.bind(authentication) as (
            phone: string,
            password: string,
            planId: string,
          ) => Promise<void>)
        : null
    if (
      registerWithPayment === null ||
      !isValidUsername(username.trim(), true) ||
      planId === ''
    ) {
      setError('payment')
      return
    }
    if (password !== passwordConfirmation) {
      setError('mismatch')
      return
    }
    setBusy(true)
    setError(false)
    void registerWithPayment(username.trim(), password, planId)
      .then(() => authentication.current())
      .then((snapshot) => {
        if (snapshot.status !== 'authenticated') {
          setError('payment')
          return
        }
        setPassword('')
        setPasswordConfirmation('')
        onAuthenticated(snapshot)
      })
      .catch(() => setError('payment'))
      .finally(() => setBusy(false))
  }

  const enrollWithInvitation = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setError(false)

    if (!isValidUsername(username.trim(), false)) {
      setError('form')
      return
    }

    if (password !== passwordConfirmation) {
      setError('form')
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
      .catch(() => setError('form'))
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
                  : paidJoin
                    ? 'auth.paid.title'
                    : 'auth.password.title',
              )}
            />
          </h3>
          <p>
            <LocalizedMessage
              resolution={message(
                invitationSetup
                  ? 'auth.password.invitation_lead'
                  : paidJoin
                    ? 'auth.paid.lead'
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
            : paidJoin
              ? payAndRegister
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
        {paidJoin ? (
          <fieldset className="web-paid-plans" disabled={busy}>
            <legend>
              <LocalizedMessage resolution={message('auth.paid.plan')} />
            </legend>
            {plans.map((plan) => {
              const nameKey = planCopyKey(plan.code)
              const leadKey = planLeadKey(plan.code)
              const selected = plan.id === planId
              return (
                <label
                  className={
                    selected
                      ? 'web-paid-plan web-paid-plan--selected'
                      : 'web-paid-plan'
                  }
                  key={plan.id}
                >
                  <input
                    checked={selected}
                    name="paid-plan"
                    onChange={() => setPlanId(plan.id)}
                    required
                    type="radio"
                    value={plan.id}
                  />
                  <span className="web-paid-plan__copy">
                    {plan.recommended ? (
                      <span className="web-paid-plan__flag">
                        <LocalizedMessage resolution={message('subscription.recommended')} />
                      </span>
                    ) : null}
                    <strong>
                      {nameKey === null ? plan.name : (
                        <LocalizedMessage resolution={message(nameKey)} />
                      )}
                    </strong>
                    {leadKey === null ? null : (
                      <span>
                        <LocalizedMessage resolution={message(leadKey)} />
                      </span>
                    )}
                    {!plan.allowsRoleplay && !plan.isDemo ? (
                      <span>
                        <LocalizedMessage resolution={message('subscription.benefit.no_roleplay')} />
                      </span>
                    ) : null}
                    <span className="web-paid-plan__price">
                      <LocalizedMessage resolution={message('subscription.ex_gst')} />{' '}
                      {inr(plan.amountExGstPaise)}
                      {' · '}
                      <LocalizedMessage resolution={message('subscription.gst')} />{' '}
                      {inr(plan.gstPaise)}
                      {' · '}
                      <LocalizedMessage resolution={message('subscription.total')} />{' '}
                      {inr(plan.totalPaise)}
                    </span>
                  </span>
                </label>
              )
            })}
          </fieldset>
        ) : null}
        <label htmlFor="web-password-username">
          <LocalizedMessage
            resolution={message(
              invitationSetup
                ? 'auth.password.username'
                : 'auth.password.identifier',
            )}
          />
        </label>
        <input
          aria-describedby={error ? 'web-password-error' : undefined}
          aria-invalid={error !== false}
          autoCapitalize="none"
          autoComplete="username"
          disabled={busy}
          id="web-password-username"
          maxLength={64}
          minLength={4}
          onChange={(event) => setUsername(event.currentTarget.value)}
          pattern={
            invitationSetup
              ? LETTER_USERNAME_HTML_PATTERN
              : IDENTIFIER_HTML_PATTERN
          }
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
              aria-invalid={error !== false}
              autoComplete={
                invitationSetup || paidJoin ? 'new-password' : 'current-password'
              }
              disabled={busy}
              id="web-password-password"
              maxLength={512}
              onChange={(event) => setPassword(event.currentTarget.value)}
              required
              type={showPassword ? 'text' : 'password'}
              value={password}
            />
            {invitationSetup || paidJoin ? (
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
            <label htmlFor="web-password-show">
              <input
                checked={showPassword}
                disabled={busy}
                id="web-password-show"
                name="show-password"
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
            <LocalizedMessage
              resolution={message(
                error === 'mismatch'
                  ? 'auth.paid.password_mismatch'
                  : error === 'payment'
                    ? 'auth.paid.checkout_failed'
                    : 'auth.password.error',
              )}
            />
          </p>
        ) : null}

        <button
          className="ge-button ge-button--primary"
          disabled={busy || recoverySent || (paidJoin && planId === '')}
          type="submit"
        >
          <LocalizedMessage
            resolution={message(
              invitationSetup
                ? 'auth.password.invitation_submit'
                : paidJoin && selectedPlan !== undefined
                  ? 'auth.paid.choose'
                  : paidJoin
                    ? 'auth.paid.submit'
                    : recovery
                      ? 'auth.password.recovery_submit'
                      : busy
                        ? 'auth.password.signing_in'
                        : 'auth.password.submit',
              paidJoin && selectedPlan !== undefined
                ? { plan: selectedPlanName, total: inr(selectedPlan.totalPaise) }
                : {},
            )}
          />
        </button>
        <button
          className="web-password-sign-in__text-action"
          disabled={busy}
          onClick={() => {
            if (paidJoin && onLeavePaidRegistration !== undefined) {
              onLeavePaidRegistration()
            } else if (invitationSetup || paidJoin) {
              setInvitationSetup(false)
              setLocalPaidJoin(false)
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
              recovery || invitationSetup || paidJoin
                ? 'auth.password.back'
                : 'auth.password.recovery_open',
            )}
          />
        </button>
        {!recovery && !invitationSetup && !paidJoin ? (
          <button
            className="ge-button"
            disabled={busy}
            onClick={() => {
              if (onStartPaidRegistration !== undefined) {
                onStartPaidRegistration()
              } else {
                setLocalPaidJoin(true)
              }
              setError(false)
            }}
            type="button"
          >
            <LocalizedMessage resolution={message('auth.paid.open')} />
          </button>
        ) : null}
        {!recovery && !invitationSetup && !paidJoin ? (
          <button
            className="web-password-sign-in__text-action"
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

      {!recovery &&
      !invitationSetup &&
      (providers.google || providers.facebook || providers.instagram) ? (
        <div className="web-password-sign-in__social">
          <p>
            <LocalizedMessage resolution={message('auth.social.or')} />
          </p>
          {providers.google ? (
            <button
              className="ge-button"
              disabled={busy}
              onClick={() => beginFederated('google')}
              type="button"
            >
              <LocalizedMessage resolution={message('auth.social.google')} />
            </button>
          ) : null}
          {providers.facebook ? (
            <button
              className="ge-button"
              disabled={busy}
              onClick={() => beginFederated('facebook')}
              type="button"
            >
              <LocalizedMessage
                resolution={message('auth.social.facebook')}
              />
            </button>
          ) : null}
          {providers.instagram ? (
            <button
              className="ge-button"
              disabled={busy}
              onClick={() => beginFederated('instagram')}
              type="button"
            >
              <LocalizedMessage
                resolution={{
                  ...message('auth.social.google'),
                  text: message('auth.social.google').text.replace(
                    /Google/gu,
                    'Instagram',
                  ),
                }}
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
