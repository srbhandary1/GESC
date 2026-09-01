import { cleanup, render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { SubscriptionPanel } from './SubscriptionPanel'
import type {
  LearnerEntitlement,
  LearnerSubscriptionApi,
  SubscriptionPlanOffer,
} from './LearnerSubscriptionApi'

afterEach(() => {
  cleanup()
  vi.restoreAllMocks()
})

function entitlement(
  overrides: Partial<LearnerEntitlement> = {},
): LearnerEntitlement {
  return {
    applies: true,
    entitlement_id: '01JENTITLEMENT000000000001',
    plan_code: 'demo',
    plan_name: 'Two-day demo',
    status: 'active',
    allows_roleplay: true,
    max_level_number: 2,
    is_demo: true,
    expires_at: '2026-09-03T00:00:00+00:00',
    live_ai_seconds_remaining: 240,
    live_ai_cap_seconds: 300,
    gstin_is_placeholder: true,
    ...overrides,
  }
}

function plan(overrides: Partial<SubscriptionPlanOffer> = {}): SubscriptionPlanOffer {
  return {
    id: '01JPLAN00000000000000000001',
    code: 'monthly_modules',
    name: 'Monthly modules',
    amount_ex_gst_paise: 35000,
    gst_paise: 6300,
    total_paise: 41300,
    allows_roleplay: false,
    is_demo: false,
    checkout_required: true,
    recommended: false,
    ...overrides,
  }
}

describe('subscription panel', () => {
  it('shows remaining demo seconds and completes dummy checkout', async () => {
    const checkout = vi.fn(async () => ({ status: 'completed' }))
    const api: LearnerSubscriptionApi = {
      entitlement: vi.fn(async () => entitlement()),
      plans: vi.fn(async () => ({
        gstin_is_placeholder: true,
        plans: [plan(), plan({
          id: '01JPLAN00000000000000000002',
          code: 'demo',
          name: 'Two-day demo',
          amount_ex_gst_paise: 0,
          gst_paise: 0,
          total_paise: 0,
          allows_roleplay: true,
          is_demo: true,
          checkout_required: false,
          recommended: false,
        })],
      })),
      checkout,
    }
    const user = userEvent.setup()

    render(
      <SubscriptionPanel
        api={api}
        locale="en-IN"
        localizationPolicy="development-preview"
      />,
    )

    expect(await screen.findByText(/240s/)).toBeInTheDocument()
    expect(screen.getByText(/Prices exclude GST/)).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Pay now' }))
    expect(checkout).toHaveBeenCalledOnce()
  })

  it('highlights Premium checkout', async () => {
    const checkout = vi.fn(async () => ({ status: 'completed' }))
    const api: LearnerSubscriptionApi = {
      entitlement: vi.fn(async () => entitlement()),
      plans: vi.fn(async () => ({
        gstin_is_placeholder: true,
        plans: [
          plan({
            id: '01JPLAN00000000000000000003',
            code: 'monthly_modules_roleplay',
            name: 'Premium',
            amount_ex_gst_paise: 50000,
            gst_paise: 9000,
            total_paise: 59000,
            allows_roleplay: true,
            recommended: true,
          }),
        ],
      })),
      checkout,
    }
    const user = userEvent.setup()

    render(
      <SubscriptionPanel
        api={api}
        locale="en-IN"
        localizationPolicy="development-preview"
      />,
    )

    expect(await screen.findByText(/Recommended/)).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Start Premium' }))
    expect(checkout).toHaveBeenCalledOnce()
  })

  it('hides itself for institutional learners', async () => {
    const api: LearnerSubscriptionApi = {
      entitlement: vi.fn(async () =>
        entitlement({
          applies: false,
          is_demo: false,
          live_ai_seconds_remaining: null,
        }),
      ),
      plans: vi.fn(async () => ({ gstin_is_placeholder: false, plans: [] })),
      checkout: vi.fn(),
    }

    const { container } = render(
      <SubscriptionPanel
        api={api}
        locale="en-IN"
        localizationPolicy="development-preview"
      />,
    )

    await waitFor(() => {
      expect(container).toBeEmptyDOMElement()
    })
  })
})
