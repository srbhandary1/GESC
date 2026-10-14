import type { MessageKey } from '@global-english/localization'
import type { DirectSignupPlanCode } from '../auth/directRegistrationPlan'

export interface DirectSignupPlanCopy {
  readonly code: DirectSignupPlanCode
  readonly extra: MessageKey
  readonly lead: MessageKey
  readonly name: MessageKey
  readonly price: MessageKey
  readonly recommended?: boolean
}

export const directSignupPlans: readonly DirectSignupPlanCopy[] = [
  {
    code: 'demo',
    extra: 'marketing.plan.demo.extra',
    lead: 'marketing.plan.demo.lead',
    name: 'marketing.plan.demo.name',
    price: 'marketing.plan.demo.price',
  },
  {
    code: 'monthly_modules',
    extra: 'marketing.plan.modules.extra',
    lead: 'marketing.plan.modules.lead',
    name: 'marketing.plan.modules.name',
    price: 'marketing.plan.modules.price',
  },
  {
    code: 'monthly_modules_roleplay',
    extra: 'marketing.plan.premium.extra',
    lead: 'marketing.plan.premium.lead',
    name: 'marketing.plan.premium.name',
    price: 'marketing.plan.premium.price',
    recommended: true,
  },
]

export function directSignupHref(code: DirectSignupPlanCode): string {
  return `#sign-up/${code}`
}
