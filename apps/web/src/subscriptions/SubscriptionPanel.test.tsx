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
    plan_name: '5-day start',
    status: 'active',
    allows_roleplay: false,
    allows_free_speech: false,
    allowed_session_parts: ['introduction', 'practice'],
    max_level_number: 2,
    is_demo: true,
    expires_at: '2026-09-11T00:00:00+00:00',
    live_ai_seconds_remaining: null,
    live_ai_cap_seconds: null,
    gstin_is_placeholder: true,
    ...overrides,
  }
}

function plan(overrides: Partial<SubscriptionPlanOffer> = {}): SubscriptionPlanOffer {
  return {
    id: '01JPLAN00000000000000000001',
    code: 'monthly_modules',
    name: 'Premium features',
    amount_ex_gst_paise: 30000,
    gst_paise: 5400,
    total_paise: 35400,
    billing_period: 'calendar_month',
    allows_roleplay: false,
    live_ai_cap_seconds: 7200,
    is_demo: false,
    is_talk_pack: false,
    checkout_required: true,
    recommended: false,
    ...overrides,
  }
}

describe('subscription panel', () => {
  it('shows the 5-day start expiry and completes dummy checkout', async () => {
    const checkout = vi.fn(async () => ({ status: 'completed' }))
    const api: LearnerSubscriptionApi = {
      entitlement: vi.fn(async () => entitlement()),
      plans: vi.fn(async () => ({
        gstin_is_placeholder: true,
        plans: [plan(), plan({
          id: '01JPLAN00000000000000000002',
          code: 'demo',
          name: '5-day start',
          amount_ex_gst_paise: 300,
          gst_paise: 54,
          total_paise: 354,
          billing_period: 'hours',
          allows_roleplay: false,
          live_ai_cap_seconds: null,
          is_demo: true,
          is_talk_pack: false,
          checkout_required: true,
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

    expect(await screen.findByText(/5-day start active until/)).toBeInTheDocument()
    expect(screen.getByText(/Prices exclude GST/)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Buy talk time' })).toBeDisabled()
    expect(
      screen.getByText(/Subscribe to Premium features or Premium with Role-play before buying talk time/),
    ).toBeInTheDocument()
    await user.click(screen.getAllByRole('button', { name: 'Pay now' })[0]!)
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
            name: 'Premium with Role-play',
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
          plan_name: 'Premium features',
          is_demo: false,
          allows_free_speech: true,
          allowed_session_parts: [
            'introduction',
            'practice',
            'conversation',
            'interaction',
          ],
          live_ai_seconds_remaining: 3600,
          live_ai_cap_seconds: 7200,
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
    expect(screen.getByText(/^60$/)).toBeInTheDocument()
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
