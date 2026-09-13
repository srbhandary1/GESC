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
    billing_period: 'calendar_month',
    allows_roleplay: false,
    live_ai_cap_seconds: 2400,
    is_demo: false,
    is_talk_pack: false,
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
          billing_period: 'hours',
          allows_roleplay: true,
          live_ai_cap_seconds: 300,
          is_demo: true,
          is_talk_pack: false,
          checkout_required: false,
          recommended: false,
        }), plan({
          id: '01JPLAN00000000000000000004',
          code: 'talk_pack_30',
          name: 'Talk 30',
          amount_ex_gst_paise: 19900,
          gst_paise: 3582,
          total_paise: 23482,
          billing_period: 'talk_pack',
          live_ai_cap_seconds: 1800,
          is_talk_pack: true,
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
    expect(screen.getByRole('button', { name: 'Buy talk time' })).toBeDisabled()
    expect(
      screen.getByText(/Subscribe to Modules or Premium before buying talk time/),
    ).toBeInTheDocument()
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

  it('sells talk packs only on an active paid month', async () => {
    const checkout = vi.fn(async () => ({ status: 'completed' }))
    const talk = plan({
      id: '01JPLAN00000000000000000005',
      code: 'talk_pack_60',
      name: 'Talk 60',
      amount_ex_gst_paise: 34900,
      gst_paise: 6282,
      total_paise: 41182,
      billing_period: 'talk_pack',
      live_ai_cap_seconds: 3600,
      is_talk_pack: true,
    })
    const api: LearnerSubscriptionApi = {
      entitlement: vi.fn(async () =>
        entitlement({
          plan_code: 'monthly_modules',
          plan_name: 'Modules',
          is_demo: false,
          live_ai_seconds_remaining: 1200,
          live_ai_cap_seconds: 2400,
        }),
      ),
      plans: vi.fn(async () => ({
        gstin_is_placeholder: false,
        plans: [plan(), talk],
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

    expect(await screen.findByText(/Live-AI minutes left/)).toBeInTheDocument()
    expect(screen.getByText(/20/)).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Buy talk time' }))
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
