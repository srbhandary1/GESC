import { describe, expect, it } from 'vitest'
import {
  paidRegistrationPlanFromHash,
  parseDirectRegistrationPlans,
} from './directRegistrationPlan'

describe('direct registration plans', () => {
  it('reads only membership plans from the signup hash', () => {
    expect(paidRegistrationPlanFromHash('#sign-up')).toBe('')
    expect(paidRegistrationPlanFromHash('#sign-up/demo')).toBe('demo')
    expect(paidRegistrationPlanFromHash('#sign-up/monthly_modules')).toBe(
      'monthly_modules',
    )
    expect(
      paidRegistrationPlanFromHash('#sign-up/monthly_modules_roleplay'),
    ).toBe('monthly_modules_roleplay')
    expect(paidRegistrationPlanFromHash('#sign-up/talk_pack_30')).toBe('')
    expect(paidRegistrationPlanFromHash('#sign-in')).toBeNull()
  })

  it('drops talk packs from the public signup catalogue', () => {
    expect(
      parseDirectRegistrationPlans([
        {
          id: '01JPLAN00000000000000000001',
          name: 'Premium features',
          total_paise: 35400,
          is_talk_pack: false,
        },
        {
          id: '01JPLAN00000000000000000004',
          name: 'Talk 30',
          total_paise: 23482,
          is_talk_pack: true,
        },
      ]).map((plan) => plan.name),
    ).toEqual(['Premium features'])
  })
})
