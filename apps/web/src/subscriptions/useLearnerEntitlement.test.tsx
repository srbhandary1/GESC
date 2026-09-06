import { act, renderHook, waitFor } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import type {
  LearnerEntitlement,
  LearnerSubscriptionApi,
} from './LearnerSubscriptionApi'
import { useLearnerEntitlement } from './useLearnerEntitlement'

const premium: LearnerEntitlement = {
  allows_roleplay: true,
  applies: true,
  entitlement_id: 'entitlement-premium',
  expires_at: '2026-10-05T00:00:00+00:00',
  gstin_is_placeholder: false,
  is_demo: false,
  live_ai_cap_seconds: 5400,
  live_ai_seconds_remaining: 4920,
  max_level_number: null,
  plan_code: 'monthly_modules_roleplay',
  plan_name: 'Premium',
  status: 'active',
}

describe('useLearnerEntitlement', () => {
  it('models every state and preserves paid access after refresh failure', async () => {
    let resolveInitial: (value: LearnerEntitlement) => void = () => undefined
    const entitlement = vi.fn(
      () =>
        new Promise<LearnerEntitlement>((resolve) => {
          resolveInitial = resolve
        }),
    )
    const api = subscriptionApi(entitlement)
    const { rerender, result } = renderHook(
      ({ scope }: { scope: string | null }) =>
        useLearnerEntitlement(scope, api),
      { initialProps: { scope: null as string | null } },
    )

    expect(result.current.status).toBe('absent')
    expect(result.current.entitlement).toBeNull()

    rerender({ scope: 'membership-one' })
    expect(result.current.status).toBe('loading')

    act(() => resolveInitial(premium))
    await waitFor(() => expect(result.current.status).toBe('ready'))
    expect(result.current.entitlement).toEqual(premium)

    entitlement.mockRejectedValueOnce(new Error('temporary outage'))
    await act(async () => result.current.refreshEntitlement())
    expect(result.current.status).toBe('unavailable')
    expect(result.current.entitlement).toEqual(premium)

    act(() => {
      result.current.applyEntitlement({ ...premium, applies: false })
    })
    expect(result.current.status).toBe('absent')

    act(() => {
      result.current.applyEntitlement({
        ...premium,
        live_ai_seconds_remaining: 10,
      })
    })
    expect(result.current.status).toBe('exhausted')
  })
})

function subscriptionApi(
  entitlement: LearnerSubscriptionApi['entitlement'],
): LearnerSubscriptionApi {
  return {
    checkout: vi.fn(),
    entitlement,
    plans: vi.fn(),
  }
}
