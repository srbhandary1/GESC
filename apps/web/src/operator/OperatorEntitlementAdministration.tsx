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
  const [entitlements, setEntitlements] = useState<
    readonly OperatorLearnerEntitlement[]
  >([])
  const [error, setError] = useState(false)

  const applyWorkspace = useCallback(
    (workspace: {
      tax: OperatorTaxSettings
      plans: readonly OperatorEntitlementPlan[]
      courtesy_premium?: { granted_memberships: number }
    }) => {
      setTax(workspace.tax)
      setPlans(workspace.plans)
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
          const paid = plans.find((plan) => plan.code === 'monthly_modules_roleplay')
          if (!paid || learnerId.length !== 26) {
            return
          }
          void api
            .grant(learnerId, paid.id, createCommandId())
            .then(async () => {
              const listed = await api.learner(learnerId)
              setEntitlements(listed.entitlements)
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
            onChange={(event) => setLearnerId(event.target.value)}
          />
        </label>
        <button type="submit">
          <LocalizedMessage resolution={message('operator.entitlements.grant')} />
        </button>
        <button
          type="button"
          onClick={() => {
            void api
              .clearThrottle(learnerId, createCommandId())
              .catch(() => setError(true))
          }}
        >
          <LocalizedMessage
            resolution={message('operator.entitlements.clear_lockout')}
          />
        </button>
      </form>
      <ul>
        {entitlements.map((row) => (
          <li key={row.id}>
            {row.plan_code} {row.status} {String(row.live_ai_seconds_used)}s
            <button
              type="button"
              onClick={() => {
                void api
                  .restartClock(row.id, createCommandId())
                  .then(async () => {
                    const listed = await api.learner(learnerId)
                    setEntitlements(listed.entitlements)
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
                    const listed = await api.learner(learnerId)
                    setEntitlements(listed.entitlements)
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
                  const listed = await api.learner(learnerId)
                  setEntitlements(listed.entitlements)
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
    </section>
  )
}
