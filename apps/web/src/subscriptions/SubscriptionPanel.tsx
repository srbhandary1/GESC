import type {
  MessageKey,
  ResolutionPolicy,
  SupportedLocale,
} from '@global-english/localization'
import { useCallback, useEffect, useState } from 'react'
import { LocalizedMessage } from '../localization/LocalizedMessage'
import { resolveInterfaceMessage } from '../localization/interfaceLocalization'
import { createClientId } from '../shared/createClientId'
import {
  defaultLearnerSubscriptionApi,
  LearnerSubscriptionHttpError,
  type LearnerEntitlement,
  type LearnerSubscriptionApi,
  type SubscriptionPlanOffer,
} from './LearnerSubscriptionApi'
import {
  openRazorpayCheckout,
  type RazorpayCheckoutRequest,
  type RazorpayCheckoutSuccess,
} from './RazorpayCheckout'

export interface SubscriptionEntryContext {
  readonly completedLevelNumber: number | null
  readonly lockedLevelNumber: number
  readonly returnTargetId: string
}

export interface SubscriptionPanelProps {
  readonly locale: SupportedLocale
  readonly localizationPolicy: ResolutionPolicy
  readonly api?: LearnerSubscriptionApi
  readonly context?: SubscriptionEntryContext | null
  readonly onChange?: (entitlement: LearnerEntitlement) => void
  readonly onClose?: () => void
  readonly paymentCheckout?: (
    request: RazorpayCheckoutRequest,
  ) => Promise<RazorpayCheckoutSuccess | null>
}

type LoadState = 'loading' | 'ready' | 'unavailable'
type CheckoutState =
  | 'idle'
  | 'submitting'
  | 'pending'
  | 'ambiguous'
  | 'failed'
  | 'reconciled'

function currency(locale: SupportedLocale, paise: number): string {
  return new Intl.NumberFormat(locale, {
    currency: 'INR',
    minimumFractionDigits: 2,
    style: 'currency',
  }).format(paise / 100)
}

function minutes(seconds: number | null): string | null {
  return seconds === null ? null : String(Math.floor(seconds / 60))
}

function checkoutIsReflected(
  current: LearnerEntitlement,
  plan: SubscriptionPlanOffer,
  previousSeconds: number | null,
): boolean {
  if (current.applies === false || current.status !== 'active') {
    return false
  }

  if (!plan.is_talk_pack) {
    return current.plan_code === plan.code
  }

  return (
    current.is_demo === false &&
    current.live_ai_seconds_remaining !== null &&
    (previousSeconds === null ||
      current.live_ai_seconds_remaining > previousSeconds)
  )
}

export function SubscriptionPanel({
  locale,
  localizationPolicy,
  api = defaultLearnerSubscriptionApi,
  context = null,
  onChange,
  onClose,
  paymentCheckout = openRazorpayCheckout,
}: SubscriptionPanelProps) {
  const message = (key: MessageKey, parameters: Record<string, string> = {}) =>
    resolveInterfaceMessage(key, locale, localizationPolicy, parameters)
  const [entitlement, setEntitlement] = useState<LearnerEntitlement | null>(null)
  const [plans, setPlans] = useState<readonly SubscriptionPlanOffer[]>([])
  const [loadState, setLoadState] = useState<LoadState>('loading')
  const [checkoutState, setCheckoutState] = useState<CheckoutState>('idle')
  const [selectedPlan, setSelectedPlan] = useState<SubscriptionPlanOffer | null>(null)
  const [checkoutBaselineSeconds, setCheckoutBaselineSeconds] = useState<number | null>(null)
  const [checkoutId, setCheckoutId] = useState<string | null>(null)

  const apply = useCallback(
    (current: LearnerEntitlement, cataloguePlans: readonly SubscriptionPlanOffer[]) => {
      setEntitlement(current)
      setPlans(cataloguePlans)
      setLoadState('ready')
      onChange?.(current)
    },
    [onChange],
  )

  const load = useCallback(async () => {
    setLoadState('loading')
    try {
      const current = await api.entitlement()
      if (!current.applies) {
        apply(current, [])
        return
      }
      const catalogue = await api.plans()
      apply(current, catalogue.plans)
    } catch {
      setLoadState('unavailable')
    }
  }, [api, apply])

  useEffect(() => {
    let active = true
    void api.entitlement()
      .then(async (current) => {
        if (!active) return
        if (!current.applies) {
          apply(current, [])
          return
        }
        const catalogue = await api.plans()
        if (active) apply(current, catalogue.plans)
      })
      .catch(() => {
        if (active) setLoadState('unavailable')
      })
    return () => {
      active = false
    }
  }, [api, apply])

  const membershipPlans = plans.filter(
    (plan) => plan.checkout_required && !plan.is_demo && !plan.is_talk_pack,
  )
  const talkPacks = plans.filter(
    (plan) => plan.checkout_required && plan.is_talk_pack,
  )
  const canBuyTalkPack = entitlement?.status === 'active' && entitlement.is_demo === false

  const reconcile = useCallback(async () => {
    if (selectedPlan === null) return
    setCheckoutState('submitting')
    try {
      if (checkoutId !== null && api.reconcileCheckout !== undefined) {
        const checkout = await api.reconcileCheckout(checkoutId)
        if (checkout.status === 'failed') {
          setCheckoutState('failed')
          return
        }
        if (checkout.status === 'pending') {
          setCheckoutState('pending')
          return
        }
      }
      const current = await api.entitlement()
      if (!current.applies) {
        apply(current, [])
        return
      }
      const catalogue = await api.plans()
      apply(current, catalogue.plans)
      setCheckoutState(
        checkoutIsReflected(current, selectedPlan, checkoutBaselineSeconds)
          ? 'reconciled'
          : 'ambiguous',
      )
    } catch {
      setCheckoutState('ambiguous')
    }
  }, [api, apply, checkoutBaselineSeconds, checkoutId, selectedPlan])

  const pay = (plan: SubscriptionPlanOffer) => {
    const baseline = entitlement?.live_ai_seconds_remaining ?? null
    setSelectedPlan(plan)
    setCheckoutId(null)
    setCheckoutBaselineSeconds(baseline)
    setCheckoutState('submitting')
    void api.checkout(plan.id, createClientId())
      .then(async (result) => {
        let finalStatus = result.status
        if (result.id !== undefined) setCheckoutId(result.id)
        if (result.status === 'pending') {
          if (
            result.provider === 'razorpay' &&
            result.id !== undefined &&
            result.provider_order_id !== null &&
            result.provider_order_id !== undefined &&
            result.provider_public_key !== null &&
            result.provider_public_key !== undefined &&
            result.total_paise !== undefined &&
            api.confirmCheckout !== undefined
          ) {
            try {
              const completed = await paymentCheckout({
                amount: result.total_paise,
                currency: 'INR',
                description: plan.name,
                key: result.provider_public_key,
                name: 'Global Spoken English App',
                orderId: result.provider_order_id,
              })
              if (completed === null) {
                setCheckoutState('pending')
                return
              }
              if (completed.orderId !== result.provider_order_id) {
                setCheckoutState('ambiguous')
                return
              }
              const confirmation = await api.confirmCheckout(
                result.id,
                completed.paymentId,
                completed.signature,
              )
              if (confirmation.status !== 'completed') {
                setCheckoutState('pending')
                return
              }
              finalStatus = confirmation.status
            } catch {
              setCheckoutState('ambiguous')
              return
            }
          } else {
            setCheckoutState('pending')
            return
          }
        }
        if (finalStatus === 'failed') {
          setCheckoutState('failed')
          return
        }
        if (finalStatus !== 'completed') {
          setCheckoutState('ambiguous')
          return
        }
        try {
          const current = await api.entitlement()
          if (!current.applies) {
            apply(current, [])
            return
          }
          const catalogue = await api.plans()
          apply(current, catalogue.plans)
          setCheckoutState(
            checkoutIsReflected(current, plan, baseline)
              ? 'reconciled'
              : 'ambiguous',
          )
        } catch {
          setCheckoutState('ambiguous')
        }
      })
      .catch((cause: unknown) => {
        setCheckoutState(
          cause instanceof LearnerSubscriptionHttpError &&
            cause.definitiveFailure
            ? 'failed'
            : 'ambiguous',
        )
      })
  }

  if (entitlement?.applies === false) return null

  const stateKey: MessageKey | null =
    loadState === 'loading'
      ? 'subscription.state.loading'
      : loadState === 'unavailable'
        ? 'subscription.state.unavailable'
        : checkoutState === 'pending' || checkoutState === 'submitting'
          ? 'subscription.state.pending'
          : checkoutState === 'ambiguous'
            ? 'subscription.state.ambiguous'
            : checkoutState === 'failed'
              ? 'subscription.state.failed'
              : checkoutState === 'reconciled'
                ? 'subscription.state.reconciled'
                : null

  return (
    <section className="subscription-panel" aria-labelledby="subscription-title" id="subscription-panel">
      <header className="subscription-panel__header">
        <div>
          <p className="subscription-panel__eyebrow"><LocalizedMessage resolution={message('subscription.eyebrow')} /></p>
          <h2 id="subscription-title">
            {context ? (
              <LocalizedMessage
                resolution={message(
                  context.completedLevelNumber === null
                    ? 'subscription.context_locked'
                    : 'subscription.context_completed',
                  {
                    completed: String(context.completedLevelNumber ?? ''),
                    locked: String(context.lockedLevelNumber),
                  },
                )}
              />
            ) : (
              <LocalizedMessage resolution={message('subscription.title')} />
            )}
          </h2>
        </div>
      </header>

      {stateKey ? (
        <div
          className={`subscription-panel__state subscription-panel__state--${loadState === 'unavailable' ? 'unavailable' : checkoutState}`}
          role={loadState === 'unavailable' || checkoutState === 'failed' ? 'alert' : 'status'}
        >
          <LocalizedMessage resolution={message(stateKey)} />
          {loadState === 'unavailable' ? (
            <button onClick={() => void load()} type="button"><LocalizedMessage resolution={message('subscription.retry_load')} /></button>
          ) : checkoutState === 'ambiguous' || checkoutState === 'pending' ? (
            <button onClick={() => void reconcile()} type="button"><LocalizedMessage resolution={message('subscription.check_access')} /></button>
          ) : null}
        </div>
      ) : null}

      {loadState === 'ready' && entitlement !== null ? (
        <>
          <section className="subscription-panel__current" aria-labelledby="subscription-current-title">
            <h3 id="subscription-current-title"><LocalizedMessage resolution={message('subscription.current_title')} /></h3>
            <strong>{entitlement.plan_name ?? message('subscription.current_free').text}</strong>
            <p><LocalizedMessage resolution={message(entitlement.status === 'active' ? 'subscription.state.active' : 'subscription.state.inactive')} /></p>
            {entitlement.expires_at !== null ? (
              <p><LocalizedMessage resolution={message('subscription.active_until')} />{' '}{new Date(entitlement.expires_at).toLocaleDateString(locale)}</p>
            ) : null}
            {minutes(entitlement.live_ai_seconds_remaining) !== null ? (
              <p><LocalizedMessage resolution={message('subscription.ai_minutes_value', { minutes: minutes(entitlement.live_ai_seconds_remaining)! })} /></p>
            ) : null}
          </section>

          {membershipPlans.length === 2 ? (
            <section aria-labelledby="subscription-memberships-title">
              <h3 id="subscription-memberships-title"><LocalizedMessage resolution={message('subscription.memberships_title')} /></h3>
              <p><LocalizedMessage resolution={message('subscription.memberships_lead')} /></p>
              <ul className="subscription-panel__plans">
                {membershipPlans.map((plan) => {
                  const capMinutes = minutes(plan.live_ai_cap_seconds)
                  return (
                    <li className={`subscription-panel__plan${plan.recommended ? ' subscription-panel__plan--premium' : ''}`} key={plan.id}>
                      {plan.recommended ? <p className="subscription-panel__flag"><LocalizedMessage resolution={message('subscription.premium')} /></p> : null}
                      <h4>{plan.name}</h4>
                      <ul className="subscription-panel__benefits">
                        {plan.max_level_number !== null ? <li><LocalizedMessage resolution={message('subscription.benefit.levels', { level: String(plan.max_level_number) })} /></li> : null}
                        <li><LocalizedMessage resolution={message(plan.allows_roleplay ? 'subscription.benefit.roleplay' : 'subscription.benefit.modules')} /></li>
                        {capMinutes !== null ? <li><LocalizedMessage resolution={message('subscription.benefit.live_ai', { minutes: capMinutes })} /></li> : null}
                      </ul>
                      <details className="subscription-panel__tax">
                        <summary><LocalizedMessage resolution={message('subscription.price_summary', { total: currency(locale, plan.total_paise) })} /></summary>
                        <dl>
                          <div><dt><LocalizedMessage resolution={message('subscription.period')} /></dt><dd>{plan.billing_period === 'calendar_month' ? <LocalizedMessage resolution={message('subscription.period_calendar_month')} /> : plan.billing_period}</dd></div>
                          <div><dt><LocalizedMessage resolution={message('subscription.ex_gst')} /></dt><dd>{currency(locale, plan.amount_ex_gst_paise)}</dd></div>
                          <div><dt><LocalizedMessage resolution={message('subscription.gst')} /></dt><dd>{currency(locale, plan.gst_paise)}</dd></div>
                          <div className="subscription-panel__total"><dt><LocalizedMessage resolution={message('subscription.total')} /></dt><dd>{currency(locale, plan.total_paise)}</dd></div>
                        </dl>
                      </details>
                      <button className="ge-button ge-button--primary" disabled={checkoutState === 'pending' || checkoutState === 'submitting'} onClick={() => pay(plan)} type="button">
                        <LocalizedMessage resolution={message('subscription.choose_plan', { plan: plan.name })} />
                      </button>
                    </li>
                  )
                })}
              </ul>
            </section>
          ) : (
            <p className="subscription-panel__state subscription-panel__state--unavailable" role="alert"><LocalizedMessage resolution={message('subscription.memberships_unavailable')} /></p>
          )}

          {canBuyTalkPack && talkPacks.length > 0 ? (
            <section aria-labelledby="subscription-talk-packs-title">
              <h3 id="subscription-talk-packs-title"><LocalizedMessage resolution={message('subscription.talk_packs')} /></h3>
              <p><LocalizedMessage resolution={message('subscription.talk_pack_lead')} /></p>
              <ul className="subscription-panel__talk-packs">
                {talkPacks.map((plan) => (
                  <li key={plan.id}>
                    <span>{plan.name}</span>
                    <details className="subscription-panel__tax">
                      <summary><LocalizedMessage resolution={message('subscription.price_summary', { total: currency(locale, plan.total_paise) })} /></summary>
                      <dl>
                        <div><dt><LocalizedMessage resolution={message('subscription.period')} /></dt><dd><LocalizedMessage resolution={message('subscription.period_talk_pack')} /></dd></div>
                        <div><dt><LocalizedMessage resolution={message('subscription.ex_gst')} /></dt><dd>{currency(locale, plan.amount_ex_gst_paise)}</dd></div>
                        <div><dt><LocalizedMessage resolution={message('subscription.gst')} /></dt><dd>{currency(locale, plan.gst_paise)}</dd></div>
                        <div className="subscription-panel__total"><dt><LocalizedMessage resolution={message('subscription.total')} /></dt><dd>{currency(locale, plan.total_paise)}</dd></div>
                      </dl>
                    </details>
                    <button disabled={checkoutState === 'pending' || checkoutState === 'submitting'} onClick={() => pay(plan)} type="button"><LocalizedMessage resolution={message('subscription.pay_talk')} /></button>
                  </li>
                ))}
              </ul>
            </section>
          ) : null}

          {entitlement.gstin_is_placeholder ? <p className="subscription-panel__tax-notice"><LocalizedMessage resolution={message('subscription.placeholder_gst')} /></p> : null}
        </>
      ) : null}

      <div className="subscription-panel__dock">
        <a
          className="ge-button ge-button--quiet subscription-panel__continue-free"
          href="#learn"
          onClick={(event) => {
            event.preventDefault()
            onClose?.()
          }}
        >
          <LocalizedMessage resolution={message('subscription.continue_free')} />
        </a>
        {onClose ? (
          <button className="ge-button ge-button--quiet" onClick={onClose} type="button">
            <LocalizedMessage resolution={message('subscription.close')} />
          </button>
        ) : null}
      </div>
    </section>
  )
}
