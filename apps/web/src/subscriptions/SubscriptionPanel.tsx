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
  type LearnerEntitlement,
  type LearnerSubscriptionApi,
  type SubscriptionPlanOffer,
} from './LearnerSubscriptionApi'

export interface SubscriptionPanelProps {
  readonly locale: SupportedLocale
  readonly localizationPolicy: ResolutionPolicy
  readonly api?: LearnerSubscriptionApi
  readonly onChange?: (entitlement: LearnerEntitlement) => void
}

function rupees(paise: number): string {
  return `₹${(paise / 100).toFixed(0)}`
}

function minutesRemaining(seconds: number | null): string {
  if (seconds === null) {
    return '0'
  }

  return String(Math.floor(seconds / 60))
}

export function SubscriptionPanel({
  locale,
  localizationPolicy,
  api = defaultLearnerSubscriptionApi,
  onChange,
}: SubscriptionPanelProps) {
  const message = (key: MessageKey) =>
    resolveInterfaceMessage(key, locale, localizationPolicy)
  const [entitlement, setEntitlement] = useState<LearnerEntitlement | null>(
    null,
  )
  const [plans, setPlans] = useState<readonly SubscriptionPlanOffer[]>([])
  const [error, setError] = useState(false)
  const [pending, setPending] = useState(false)

  const apply = useCallback(
    (current: LearnerEntitlement, cataloguePlans: readonly SubscriptionPlanOffer[]) => {
      setEntitlement(current)
      setPlans(cataloguePlans)
      onChange?.(current)
      setError(false)
    },
    [onChange],
  )

  const refresh = useCallback(async () => {
    const [current, catalogue] = await Promise.all([
      api.entitlement(),
      api.plans(),
    ])
    apply(current, catalogue.plans)
  }, [api, apply])

  useEffect(() => {
    const controller = new AbortController()
    void Promise.all([api.entitlement(), api.plans()])
      .then(([current, catalogue]) => {
        if (!controller.signal.aborted) {
          apply(current, catalogue.plans)
        }
      })
      .catch(() => {
        if (!controller.signal.aborted) {
          setError(true)
        }
      })
    return () => {
      controller.abort()
    }
  }, [api, apply])

  if (entitlement !== null && entitlement.applies === false) {
    return null
  }

  const canBuyTalkPack =
    entitlement !== null &&
    entitlement.status === 'active' &&
    entitlement.is_demo === false
  const membershipPlans = plans.filter(
    (plan) => plan.checkout_required && plan.is_talk_pack === false,
  )
  const talkPacks = plans.filter(
    (plan) => plan.checkout_required && plan.is_talk_pack === true,
  )

  const pay = (plan: SubscriptionPlanOffer) => {
    setPending(true)
    void api
      .checkout(plan.id, createClientId())
      .then(() => refresh())
      .catch(() => setError(true))
      .finally(() => setPending(false))
  }

  return (
    <section
      className="subscription-panel"
      aria-labelledby="subscription-title"
      id="subscription-panel"
    >
      <h2 id="subscription-title">
        <LocalizedMessage resolution={message('subscription.title')} />
      </h2>
      {error ? (
        <p>
          <LocalizedMessage resolution={message('subscription.error')} />
        </p>
      ) : null}
      {entitlement?.is_demo ? (
        <p>
          <LocalizedMessage
            resolution={message('subscription.demo_remaining')}
          />{' '}
          {String(entitlement.live_ai_seconds_remaining ?? 0)}s
        </p>
      ) : null}
      {entitlement !== null &&
      entitlement.is_demo === false &&
      entitlement.live_ai_seconds_remaining !== null ? (
        <p>
          <LocalizedMessage resolution={message('subscription.ai_remaining')} />{' '}
          {minutesRemaining(entitlement.live_ai_seconds_remaining)}
        </p>
      ) : null}
      {entitlement?.gstin_is_placeholder ? (
        <p>
          <LocalizedMessage
            resolution={message('subscription.placeholder_gst')}
          />
        </p>
      ) : null}
      <p>
        <LocalizedMessage resolution={message('subscription.gst_exclusive')} />
      </p>
      <ul>
        {membershipPlans.map((plan) => (
          <li key={plan.id}>
            <span>
              {plan.recommended ? (
                <>
                  <LocalizedMessage
                    resolution={message('subscription.recommended')}
                  />{' '}
                </>
              ) : null}
              {plan.name} {rupees(plan.amount_ex_gst_paise)} + GST{' '}
              {rupees(plan.total_paise)}
            </span>
            <button
              type="button"
              disabled={pending}
              onClick={() => {
                pay(plan)
              }}
            >
              <LocalizedMessage
                resolution={message(
                  plan.recommended
                    ? 'subscription.pay_premium'
                    : 'subscription.pay',
                )}
              />
            </button>
          </li>
        ))}
      </ul>
      <h3>
        <LocalizedMessage resolution={message('subscription.talk_packs')} />
      </h3>
      <p>
        <LocalizedMessage resolution={message('subscription.talk_pack_lead')} />
      </p>
      {canBuyTalkPack ? null : (
        <p>
          <LocalizedMessage
            resolution={message('subscription.talk_pack_requires_paid')}
          />
        </p>
      )}
      <ul>
        {talkPacks.map((plan) => (
          <li key={plan.id}>
            <span>
              {plan.name} {rupees(plan.amount_ex_gst_paise)} + GST{' '}
              {rupees(plan.total_paise)}
            </span>
            <button
              type="button"
              disabled={pending || !canBuyTalkPack}
              onClick={() => {
                pay(plan)
              }}
            >
              <LocalizedMessage resolution={message('subscription.pay_talk')} />
            </button>
          </li>
        ))}
      </ul>
    </section>
  )
}
