import type { LearnerProviderUsage } from '@global-english/contracts'
export type OperatorEntitlementPanelKey =
  | 'entitlement_plans'
  | 'entitlement_directory'

export interface OperatorTaxSettings {
  readonly gst_registered: boolean
  readonly gstin: string
  readonly gst_rate_bps: number
  readonly gstin_is_placeholder: boolean
  readonly version: number
}

export interface OperatorEntitlementPlan {
  readonly id: string
  readonly code: string
  readonly name: string
  readonly amount_ex_gst_paise: number
  readonly duration_value: number
  readonly allows_roleplay: boolean
  readonly max_level_number: number | null
  readonly live_ai_cap_seconds: number | null
  readonly is_demo: boolean
  readonly status: string
  readonly version: number
}

export interface OperatorLearnerEntitlement {
  readonly id: string
  readonly plan_code: string
  readonly status: string
  readonly live_ai_seconds_used: number
  readonly source?: string
  readonly starts_at?: string
  readonly expires_at?: string | null
}

export interface OperatorEntitlementAuditEvent {
  readonly id: string
  readonly entitlement_id: string
  readonly event_type: string
  readonly actor_membership_id?: string | null
  readonly occurred_at: string
  readonly plan_code?: string | null
  readonly source?: string | null
  readonly reason?: string | null
  readonly payment_reference?: string | null
}

export interface OperatorEntitlementApi {
  workspace(): Promise<{
    tax: OperatorTaxSettings
    plans: readonly OperatorEntitlementPlan[]
    courtesy_premium: { granted_memberships: number }
  }>
  learner(learnerId: string): Promise<{
    learner_membership_id?: string
    entitlements: readonly OperatorLearnerEntitlement[]
    provider_usage?: LearnerProviderUsage
    events?: readonly OperatorEntitlementAuditEvent[]
  }>
  updateTax(
    body: {
      gst_registered: boolean
      gstin: string
      gst_rate_bps: number
      expected_version: number
    },
    idempotencyKey: string,
  ): Promise<void>
  grant(
    learnerId: string,
    planId: string,
    idempotencyKey: string,
    metadata?: {
      readonly reason?: string
      readonly payment_reference?: string
    },
  ): Promise<void>
  grantCourtesyPremium(idempotencyKey: string): Promise<void>
  updatePlan(
    planId: string,
    body: {
      name: string
      amount_ex_gst_paise: number
      duration_value: number
      allows_roleplay: boolean
      max_level_number: number | null
      live_ai_cap_seconds: number | null
      status: string
      expected_version: number
    },
    idempotencyKey: string,
  ): Promise<void>
  restartClock(entitlementId: string, idempotencyKey: string): Promise<void>
  restartMeter(entitlementId: string, idempotencyKey: string): Promise<void>
  revoke(entitlementId: string, idempotencyKey: string): Promise<void>
  clearThrottle(learnerId: string, idempotencyKey: string): Promise<void>
}

function csrfToken(): string {
  const match = /XSRF-TOKEN=([^;]+)/.exec(document.cookie)
  return match?.[1] ? decodeURIComponent(match[1]) : ''
}

async function read(path: string): Promise<unknown> {
  const response = await fetch(path, {
    credentials: 'include',
    headers: { Accept: 'application/json' },
  })
  if (!response.ok) {
    throw new Error('operator_entitlements_unavailable')
  }
  return response.json()
}

async function command(path: string, body: object, idempotencyKey: string): Promise<void> {
  const response = await fetch(path, {
    method: 'POST',
    credentials: 'include',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'Idempotency-Key': idempotencyKey,
      'X-XSRF-TOKEN': csrfToken(),
    },
    body: JSON.stringify(body),
  })
  if (!response.ok) {
    throw new Error('operator_entitlements_unavailable')
  }
}

export const defaultOperatorEntitlementApi: OperatorEntitlementApi = {
  workspace: () =>
    read('/api/v1/operator/entitlement-workspace') as Promise<{
      tax: OperatorTaxSettings
      plans: readonly OperatorEntitlementPlan[]
      courtesy_premium: { granted_memberships: number }
    }>,
  learner: (learnerId) =>
    read(`/api/v1/operator/learners/${encodeURIComponent(learnerId)}/entitlements`) as Promise<{
      learner_membership_id?: string
      entitlements: readonly OperatorLearnerEntitlement[]
      provider_usage?: LearnerProviderUsage
      events?: readonly OperatorEntitlementAuditEvent[]
    }>,
  updateTax: (body, key) =>
    command('/api/v1/operator/tax-settings/updates', body, key),
  grant: (learnerId, planId, key, metadata) =>
    command(
      `/api/v1/operator/learners/${encodeURIComponent(learnerId)}/entitlement-grants`,
      {
        plan_id: planId,
        ...(metadata?.reason ? { reason: metadata.reason } : {}),
        ...(metadata?.payment_reference
          ? { payment_reference: metadata.payment_reference }
          : {}),
      },
      key,
    ),
  grantCourtesyPremium: (key) =>
    command('/api/v1/operator/courtesy-premium-grants', {}, key),
  updatePlan: (planId, body, key) =>
    command(`/api/v1/operator/entitlement-plans/${planId}/updates`, body, key),
  restartClock: (id, key) =>
    command(`/api/v1/operator/entitlements/${id}/demo-clock-restarts`, {}, key),
  restartMeter: (id, key) =>
    command(`/api/v1/operator/entitlements/${id}/demo-meter-restarts`, {}, key),
  revoke: (id, key) =>
    command(`/api/v1/operator/entitlements/${id}/revocations`, {}, key),
  clearThrottle: (learnerId, key) =>
    command(
      `/api/v1/operator/learners/${learnerId}/signin-throttle-clears`,
      {},
      key,
    ),
}
