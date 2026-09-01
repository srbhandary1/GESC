export interface LearnerEntitlement {
  readonly applies: boolean
  readonly entitlement_id: string | null
  readonly plan_code: string | null
  readonly plan_name: string | null
  readonly status: string
  readonly allows_roleplay: boolean
  readonly max_level_number: number | null
  readonly is_demo: boolean
  readonly expires_at: string | null
  readonly live_ai_seconds_remaining: number | null
  readonly live_ai_cap_seconds: number | null
  readonly gstin_is_placeholder: boolean
}

export interface SubscriptionPlanOffer {
  readonly id: string
  readonly code: string
  readonly name: string
  readonly amount_ex_gst_paise: number
  readonly gst_paise: number
  readonly total_paise: number
  readonly allows_roleplay: boolean
  readonly is_demo: boolean
  readonly checkout_required: boolean
  readonly recommended: boolean
}

export interface LearnerSubscriptionApi {
  entitlement(): Promise<LearnerEntitlement>
  plans(): Promise<{
    gstin_is_placeholder: boolean
    plans: readonly SubscriptionPlanOffer[]
  }>
  checkout(planId: string, idempotencyKey: string): Promise<{ status: string }>
}

function csrfToken(): string {
  const match = /XSRF-TOKEN=([^;]+)/.exec(document.cookie)
  return match?.[1] ? decodeURIComponent(match[1]) : ''
}

async function parse(response: Response): Promise<unknown> {
  if (!response.ok) {
    throw new Error('subscription_unavailable')
  }
  return response.json()
}

export const defaultLearnerSubscriptionApi: LearnerSubscriptionApi = {
  async entitlement() {
    const response = await fetch('/api/v1/me/entitlement', {
      credentials: 'include',
      headers: { Accept: 'application/json' },
    })
    return (await parse(response)) as LearnerEntitlement
  },
  async plans() {
    const response = await fetch('/api/v1/me/subscription-plans', {
      credentials: 'include',
      headers: { Accept: 'application/json' },
    })
    return (await parse(response)) as {
      gstin_is_placeholder: boolean
      plans: readonly SubscriptionPlanOffer[]
    }
  },
  async checkout(planId, idempotencyKey) {
    const response = await fetch('/api/v1/me/subscription-checkouts', {
      method: 'POST',
      credentials: 'include',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'Idempotency-Key': idempotencyKey,
        'X-XSRF-TOKEN': csrfToken(),
      },
      body: JSON.stringify({ plan_id: planId }),
    })
    return (await parse(response)) as { status: string }
  },
}
