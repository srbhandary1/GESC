import type { LearnerProviderUsage } from '@global-english/contracts'
import { ProviderUsageSummary } from '../subscriptions/ProviderUsageSummary'
import type {
  MessageKey,
  ResolutionPolicy,
  SupportedLocale,
} from '@global-english/localization'
import { useCallback, useEffect, useState, type FormEvent } from 'react'
import { LocalizedMessage } from '../localization/LocalizedMessage'
import { resolveInterfaceMessage } from '../localization/interfaceLocalization'
import { createClientId } from '../shared/createClientId'
import {
  defaultOperatorEntitlementApi,
  type OperatorEntitlementApi,
  type OperatorEntitlementAuditEvent,
  type OperatorEntitlementPlan,
  type OperatorLearnerEntitlement,
  type OperatorTaxSettings,
} from './OperatorEntitlementApi'

export interface OperatorEntitlementAdministrationProps {
  readonly api?: OperatorEntitlementApi
  readonly locale: SupportedLocale
  readonly localizationPolicy: ResolutionPolicy
  readonly createCommandId?: () => string
}

export function OperatorEntitlementAdministration({
  api = defaultOperatorEntitlementApi,
  locale,
  localizationPolicy,
  createCommandId = createClientId,
}: OperatorEntitlementAdministrationProps) {
  const message = (key: MessageKey) =>
    resolveInterfaceMessage(key, locale, localizationPolicy)
  const [tax, setTax] = useState<OperatorTaxSettings | null>(null)
  const [plans, setPlans] = useState<readonly OperatorEntitlementPlan[]>([])
  const [courtesyCount, setCourtesyCount] = useState(0)
  const [gstin, setGstin] = useState('')
  const [gstRate, setGstRate] = useState('18')
  const [learnerId, setLearnerId] = useState('')
  const [selectedPlanId, setSelectedPlanId] = useState('')
  const [grantReason, setGrantReason] = useState('')
  const [paymentReference, setPaymentReference] = useState('')
  const [entitlements, setEntitlements] = useState<
    readonly OperatorLearnerEntitlement[]
  >([])
  const [auditEvents, setAuditEvents] = useState<
    readonly OperatorEntitlementAuditEvent[]
  >([])
  const [providerUsage, setProviderUsage] = useState<LearnerProviderUsage | undefined>()
  const [error, setError] = useState(false)

  const applyWorkspace = useCallback(
    (workspace: {
      tax: OperatorTaxSettings
      plans: readonly OperatorEntitlementPlan[]
      courtesy_premium?: { granted_memberships: number }
    }) => {
      setTax(workspace.tax)
      setPlans(workspace.plans)
      setSelectedPlanId((current) => {
        if (current !== '' && workspace.plans.some((p) => p.id === current)) {
          return current
        }
        const preferred =
          workspace.plans.find((p) => p.code === 'monthly_modules_roleplay') ??
          workspace.plans[0]
        return preferred?.id ?? ''
      })
      setCourtesyCount(workspace.courtesy_premium?.granted_memberships ?? 0)
      setGstin(workspace.tax.gstin)
      setGstRate((workspace.tax.gst_rate_bps / 100).toFixed(0))
      setError(false)
    },
    [],
  )

  const refresh = useCallback(async () => {
    applyWorkspace(await api.workspace())
  }, [api, applyWorkspace])

  useEffect(() => {
    const controller = new AbortController()
    void api
      .workspace()
      .then((workspace) => {
        if (!controller.signal.aborted) {
          applyWorkspace(workspace)
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
  }, [api, applyWorkspace])

  return (
    <section
      className="operator-task-group"
      id="operator-entitlements"
      aria-labelledby="operator-entitlements-title"
    >
      <h3 id="operator-entitlements-title">
        <LocalizedMessage resolution={message('operator.entitlements.title')} />
      </h3>
      <p>
        <LocalizedMessage resolution={message('operator.entitlements.lead')} />
      </p>
      <p>
        <LocalizedMessage
          resolution={message('operator.entitlements.grant_courtesy_lead')}
        />{' '}
        {String(courtesyCount)}
      </p>
      <button
        type="button"
        onClick={() => {
          void api
            .grantCourtesyPremium(createCommandId())
            .then(() => refresh())
            .catch(() => setError(true))
        }}
      >
        <LocalizedMessage
          resolution={message('operator.entitlements.grant_courtesy')}
        />
      </button>
      {error ? (
        <p>
          <LocalizedMessage resolution={message('operator.entitlements.error')} />
        </p>
      ) : null}
      {tax ? (
        <form
          onSubmit={(event: FormEvent) => {
            event.preventDefault()
            void api
              .updateTax(
                {
                  gst_registered: true,
                  gstin,
                  gst_rate_bps: Math.round(Number(gstRate) * 100),
                  expected_version: tax.version,
                },
                createCommandId(),
              )
              .then(() => refresh())
              .catch(() => setError(true))
          }}
        >
          {tax.gstin_is_placeholder ? (
            <p>
              <LocalizedMessage
                resolution={message('operator.entitlements.placeholder')}
              />
            </p>
          ) : null}
          <label>
            <LocalizedMessage
              resolution={message('operator.entitlements.gstin')}
            />
            <input
              value={gstin}
              maxLength={15}
              onChange={(event) => setGstin(event.target.value.toUpperCase())}
            />
          </label>
          <label>
            <LocalizedMessage
              resolution={message('operator.entitlements.gst_rate')}
            />
            <input
              value={gstRate}
              inputMode="numeric"
              onChange={(event) => setGstRate(event.target.value)}
            />
          </label>
          <button type="submit">
            <LocalizedMessage
              resolution={message('operator.entitlements.save_tax')}
            />
          </button>
        </form>
      ) : null}
      <h4>
        <LocalizedMessage resolution={message('operator.entitlements.plans')} />
      </h4>
      <ul>
        {plans.map((plan) => (
          <li key={plan.id}>
            <form
              onSubmit={(event: FormEvent<HTMLFormElement>) => {
                event.preventDefault()
                const form = event.currentTarget
                const amount = form.elements.namedItem('amount')
                const name = form.elements.namedItem('name')
                if (
                  !(amount instanceof HTMLInputElement) ||
                  !(name instanceof HTMLInputElement)
                ) {
                  return
                }
                void api
                  .updatePlan(
                    plan.id,
                    {
                      name: name.value,
                      amount_ex_gst_paise: Math.round(Number(amount.value) * 100),
                      duration_value: plan.duration_value,
                      allows_roleplay: plan.allows_roleplay,
                      max_level_number: plan.max_level_number,
                      live_ai_cap_seconds: plan.live_ai_cap_seconds,
                      status: plan.status,
                      expected_version: plan.version,
                    },
                    createCommandId(),
                  )
                  .then(() => refresh())
                  .catch(() => setError(true))
              }}
            >
              <input
                name="name"
                defaultValue={plan.name}
                aria-label={plan.code}
              />
              <span>({plan.code})</span>
              <input
                name="amount"
                defaultValue={(plan.amount_ex_gst_paise / 100).toFixed(0)}
                inputMode="numeric"
              />
              <button type="submit">
                <LocalizedMessage
                  resolution={message('operator.entitlements.save_plan')}
                />
              </button>
            </form>
          </li>
        ))}
      </ul>
      <form
        onSubmit={(event: FormEvent) => {
          event.preventDefault()
          const targetLearner = learnerId.trim()
          const paid =
            plans.find((plan) => plan.id === selectedPlanId) ??
            plans.find((plan) => plan.code === 'monthly_modules_roleplay')
          if (!paid || targetLearner.length < 4) {
            return
          }
          const metadata =
            grantReason.trim() !== '' || paymentReference.trim() !== ''
              ? {
                  ...(grantReason.trim() !== ''
                    ? { reason: grantReason.trim() }
                    : {}),
                  ...(paymentReference.trim() !== ''
                    ? { payment_reference: paymentReference.trim() }
                    : {}),
                }
              : undefined
          const grantPromise =
            metadata === undefined
              ? api.grant(targetLearner, paid.id, createCommandId())
              : api.grant(targetLearner, paid.id, createCommandId(), metadata)
          void grantPromise
            .then(async () => {
              const listed = await api.learner(targetLearner)
              setEntitlements(listed.entitlements)
              setAuditEvents(listed.events ?? [])
              setProviderUsage(listed.provider_usage)
              setGrantReason('')
              setPaymentReference('')
            })
            .catch(() => setError(true))
        }}
      >
        <label>
          <LocalizedMessage
            resolution={message('operator.entitlements.learner_id')}
          />
          <input
            value={learnerId}
            placeholder="ULID or phone/username"
            onChange={(event) => setLearnerId(event.target.value)}
          />
        </label>
        {plans.length > 0 ? (
          <label>
            <span>Plan</span>
            <select
              aria-label="Plan to grant"
              value={selectedPlanId}
              onChange={(event) => setSelectedPlanId(event.target.value)}
            >
              {plans.map((plan) => (
                <option key={plan.id} value={plan.id}>
                  {plan.name} ({plan.code})
                </option>
              ))}
            </select>
          </label>
        ) : null}
        <label>
          <span>Audit reason (optional)</span>
          <input
            aria-label="Audit reason"
            maxLength={240}
            placeholder="e.g. Payment captured — manual access recovery"
            value={grantReason}
            onChange={(event) => setGrantReason(event.target.value)}
          />
        </label>
        <label>
          <span>Payment reference (optional)</span>
          <input
            aria-label="Payment reference"
            maxLength={120}
            placeholder="e.g. pay_... or order_..."
            value={paymentReference}
            onChange={(event) => setPaymentReference(event.target.value)}
          />
        </label>
        <button type="submit">
          <LocalizedMessage resolution={message('operator.entitlements.grant')} />
        </button>
        <button
          type="button"
          onClick={() => {
            const targetLearner = learnerId.trim()
            if (targetLearner.length < 4) {
              return
            }
            void api
              .learner(targetLearner)
              .then((listed) => {
                setEntitlements(listed.entitlements)
                setAuditEvents(listed.events ?? [])
                setProviderUsage(listed.provider_usage)
                setError(false)
              })
              .catch(() => setError(true))
          }}
        >
          Load learner access
        </button>
        <button
          type="button"
          onClick={() => {
            void api
              .clearThrottle(learnerId.trim(), createCommandId())
              .catch(() => setError(true))
          }}
        >
          <LocalizedMessage
            resolution={message('operator.entitlements.clear_lockout')}
          />
        </button>
      </form>
      <ul>
        <ProviderUsageSummary usage={providerUsage} locale={locale} localizationPolicy={localizationPolicy} />
        {entitlements.map((row) => (
          <li key={row.id}>
            {row.plan_code} {row.status} {String(row.live_ai_seconds_used)}s
            <button
              type="button"
              onClick={() => {
                void api
                  .restartClock(row.id, createCommandId())
                  .then(async () => {
                    const listed = await api.learner(learnerId.trim())
                    setEntitlements(listed.entitlements)
                    setAuditEvents(listed.events ?? [])
                    setProviderUsage(listed.provider_usage)
                  })
              }}
            >
              <LocalizedMessage
                resolution={message('operator.entitlements.restart_clock')}
              />
            </button>
            <button
              type="button"
              onClick={() => {
                void api
                  .restartMeter(row.id, createCommandId())
                  .then(async () => {
                    const listed = await api.learner(learnerId.trim())
                    setEntitlements(listed.entitlements)
                    setAuditEvents(listed.events ?? [])
                    setProviderUsage(listed.provider_usage)
                  })
              }}
            >
              <LocalizedMessage
                resolution={message('operator.entitlements.restart_meter')}
              />
            </button>
            <button
              type="button"
              onClick={() => {
                void api.revoke(row.id, createCommandId()).then(async () => {
                  const listed = await api.learner(learnerId.trim())
                  setEntitlements(listed.entitlements)
                  setAuditEvents(listed.events ?? [])
                  setProviderUsage(listed.provider_usage)
                })
              }}
            >
              <LocalizedMessage
                resolution={message('operator.entitlements.revoke')}
              />
            </button>
          </li>
        ))}
      </ul>
      {auditEvents.length > 0 ? (
        <section aria-labelledby="operator-entitlement-audit-title">
          <h4 id="operator-entitlement-audit-title">Access audit log</h4>
          <ul>
            {auditEvents.map((event) => (
              <li key={event.id}>
                <strong>{event.event_type}</strong>
                {event.plan_code ? ` · ${event.plan_code}` : ''}
                {event.source ? ` (${event.source})` : ''}
                {event.actor_membership_id
                  ? ` · actor ${event.actor_membership_id}`
                  : ''}
                {event.payment_reference
                  ? ` · ref ${event.payment_reference}`
                  : ''}
                {event.reason ? ` · ${event.reason}` : ''}
                {` · ${event.occurred_at}`}
              </li>
            ))}
          </ul>
        </section>
      ) : null}
    </section>
  )
}
