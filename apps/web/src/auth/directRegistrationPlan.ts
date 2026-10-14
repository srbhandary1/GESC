export interface DirectRegistrationPlan {
  readonly id: string
  readonly code: string
  readonly name: string
  readonly amountExGstPaise: number
  readonly gstPaise: number
  readonly totalPaise: number
  readonly allowsRoleplay: boolean
  readonly maxLevelNumber: number | null
  readonly liveAiCapSeconds: number | null
  readonly isDemo: boolean
  readonly isTalkPack: boolean
  readonly recommended: boolean
  readonly billingPeriod: string
}

export const DIRECT_SIGNUP_PLAN_CODES = [
  'demo',
  'monthly_modules',
  'monthly_modules_roleplay',
] as const

export type DirectSignupPlanCode = (typeof DIRECT_SIGNUP_PLAN_CODES)[number]

export function paidRegistrationPlanFromHash(hash: string): string | null {
  if (hash === '#sign-up') {
    return ''
  }

  const match = /^#sign-up\/([a-z0-9_]+)$/u.exec(hash)
  if (match === null) {
    return null
  }

  return (DIRECT_SIGNUP_PLAN_CODES as readonly string[]).includes(match[1] ?? '')
    ? (match[1] ?? '')
    : ''
}

export function parseDirectRegistrationPlans(
  plans: unknown,
): readonly DirectRegistrationPlan[] {
  if (!Array.isArray(plans)) {
    return []
  }

  return plans.flatMap((plan) => {
    const parsed = parseDirectRegistrationPlan(plan)
    return parsed === null || parsed.isTalkPack ? [] : [parsed]
  })
}

function parseDirectRegistrationPlan(plan: unknown): DirectRegistrationPlan | null {
  if (plan === null || typeof plan !== 'object') {
    return null
  }

  const record = plan as Record<string, unknown>
  if (
    typeof record.id !== 'string' ||
    typeof record.name !== 'string' ||
    typeof record.total_paise !== 'number' ||
    typeof record.is_talk_pack !== 'boolean'
  ) {
    return null
  }

  return {
    allowsRoleplay: record.allows_roleplay === true,
    amountExGstPaise:
      typeof record.amount_ex_gst_paise === 'number'
        ? record.amount_ex_gst_paise
        : record.total_paise,
    billingPeriod:
      typeof record.billing_period === 'string' ? record.billing_period : '',
    code: typeof record.code === 'string' ? record.code : '',
    gstPaise: typeof record.gst_paise === 'number' ? record.gst_paise : 0,
    id: record.id,
    isDemo: record.is_demo === true,
    isTalkPack: record.is_talk_pack,
    liveAiCapSeconds:
      typeof record.live_ai_cap_seconds === 'number'
        ? record.live_ai_cap_seconds
        : null,
    maxLevelNumber:
      typeof record.max_level_number === 'number'
        ? record.max_level_number
        : null,
    name: record.name,
    recommended: record.recommended === true,
    totalPaise: record.total_paise,
  }
}
