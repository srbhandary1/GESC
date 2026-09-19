import { cleanup, render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { SubscriptionPanel } from './SubscriptionPanel'
import {
  LearnerSubscriptionHttpError,
  type LearnerEntitlement,
  type LearnerSubscriptionApi,
  type SubscriptionPlanOffer,
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
    max_level_number: 17,
    live_ai_cap_seconds: 7200,
    is_demo: false,
    is_talk_pack: false,
    checkout_required: true,
    recommended: false,
    ...overrides,
  }
}

function membershipPlans(): readonly SubscriptionPlanOffer[] {
  return [
    plan(),
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
  ]
}

describe('subscription panel', () => {
  it('shows the 5-day start expiry and completes dummy checkout', async () => {
    const checkout = vi.fn(async () => ({ status: 'completed' }))
    const api: LearnerSubscriptionApi = {
      entitlement: vi.fn(async () => entitlement({
        live_ai_seconds_remaining: 240,
        live_ai_cap_seconds: 300,
      })),
      plans: vi.fn(async () => ({
        gstin_is_placeholder: true,
        plans: [plan(), plan({
          id: '01JPLAN00000000000000000003',
          code: 'monthly_modules_roleplay',
          name: 'Premium with Role-play',
          amount_ex_gst_paise: 50000,
          gst_paise: 9000,
          total_paise: 59000,
          allows_roleplay: true,
          recommended: true,
        }), plan({
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

    expect(await screen.findByText('5-day start')).toBeInTheDocument()
    expect(screen.getByText('4 live-AI minutes available')).toBeInTheDocument()
    expect(screen.getByText('Current entitlement')).toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'Talk time' })).not.toBeInTheDocument()
    expect(screen.getByText(/₹354\.00 payable/)).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Choose Premium features' }))
    expect(checkout).toHaveBeenCalledOnce()
  })

  it('highlights Premium checkout', async () => {
    const checkout = vi.fn(async () => ({ status: 'completed' }))
    const api: LearnerSubscriptionApi = {
      entitlement: vi.fn(async () => entitlement()),
      plans: vi.fn(async () => ({
        gstin_is_placeholder: true,
        plans: [
          plan(),
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

    expect(await screen.findByText('Premium membership')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Choose Premium with Role-play' })).not.toHaveAttribute('aria-pressed', 'true')
    await user.click(screen.getByRole('button', { name: 'Choose Premium with Role-play' }))
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
        plans: [plan(), plan({
          id: '01JPLAN00000000000000000003',
          code: 'monthly_modules_roleplay',
          name: 'Premium with Role-play',
          amount_ex_gst_paise: 50000,
          gst_paise: 9000,
          total_paise: 59000,
          allows_roleplay: true,
          recommended: true,
        }), talk],
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

    expect(await screen.findByText('60 live-AI minutes available')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Buy talk time' }))
    expect(checkout).toHaveBeenCalledOnce()
  })

  it('distinguishes loading, unavailable and disabled-provider failure states', async () => {
    let rejectEntitlement: ((reason?: unknown) => void) | undefined
    const entitlementRequest = new Promise<LearnerEntitlement>((_resolve, reject) => {
      rejectEntitlement = reject
    })
    const api: LearnerSubscriptionApi = {
      entitlement: vi.fn(() => entitlementRequest),
      plans: vi.fn(async () => ({ gstin_is_placeholder: false, plans: membershipPlans() })),
      checkout: vi.fn(),
    }
    const { rerender } = render(
      <SubscriptionPanel api={api} locale="en-IN" localizationPolicy="development-preview" />,
    )

    expect(screen.getByText(/Loading current entitlement/)).toBeInTheDocument()
    rejectEntitlement?.(new Error('offline'))
    expect(await screen.findByText(/Plan details are unavailable/)).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Choose/ })).not.toBeInTheDocument()

    const disabledApi: LearnerSubscriptionApi = {
      entitlement: vi.fn(async () => entitlement({ status: 'expired' })),
      plans: vi.fn(async () => ({ gstin_is_placeholder: false, plans: membershipPlans() })),
      checkout: vi.fn(async () => {
        throw new LearnerSubscriptionHttpError(409)
      }),
    }
    rerender(
      <SubscriptionPanel api={disabledApi} locale="en-IN" localizationPolicy="development-preview" />,
    )
    const user = userEvent.setup()
    await user.click(await screen.findByRole('button', { name: 'Choose Premium features' }))
    expect(await screen.findByText(/Checkout was not accepted/)).toBeInTheDocument()
    expect(screen.queryByText(/Access confirmed/)).not.toBeInTheDocument()
  })

  it('keeps checkout pending, marks unknown outcomes ambiguous, and reconciles only server-confirmed access', async () => {
    let resolveCheckout: ((value: { status: string }) => void) | undefined
    const checkoutRequest = new Promise<{ status: string }>((resolve) => {
      resolveCheckout = resolve
    })
    const current = entitlement({ status: 'expired', plan_code: null, plan_name: null })
    const entitlementApi = vi.fn(async () => current)
    const api: LearnerSubscriptionApi = {
      entitlement: entitlementApi,
      plans: vi.fn(async () => ({ gstin_is_placeholder: false, plans: membershipPlans() })),
      checkout: vi.fn(() => checkoutRequest),
    }
    const user = userEvent.setup()
    const { unmount } = render(
      <SubscriptionPanel api={api} locale="en-IN" localizationPolicy="development-preview" />,
    )
    await user.click(await screen.findByRole('button', { name: 'Choose Premium features' }))
    expect(screen.getByText(/Checkout request pending/)).toBeInTheDocument()
    resolveCheckout?.({ status: 'unknown' })
    expect(await screen.findByText(/could not confirm whether access changed/)).toBeInTheDocument()
    unmount()

    const reconciledEntitlement = vi
      .fn()
      .mockResolvedValueOnce(current)
      .mockResolvedValueOnce(entitlement({
        is_demo: false,
        plan_code: 'monthly_modules',
        plan_name: 'Premium features',
        status: 'active',
      }))
    const reconciledApi: LearnerSubscriptionApi = {
      entitlement: reconciledEntitlement,
      plans: vi.fn(async () => ({ gstin_is_placeholder: false, plans: membershipPlans() })),
      checkout: vi.fn(async () => ({ status: 'completed' })),
    }
    render(
      <SubscriptionPanel api={reconciledApi} locale="en-IN" localizationPolicy="development-preview" />,
    )
    await user.click(await screen.findByRole('button', { name: 'Choose Premium features' }))
    expect(await screen.findByText(/Access confirmed from the authoritative/)).toBeInTheDocument()
    expect(screen.getByText('Active access')).toBeInTheDocument()
  })

  it('offers Check access after the provider returns pending', async () => {
    const current = entitlement({
      plan_code: null,
      plan_name: null,
      status: 'expired',
    })
    const entitlementApi = vi.fn(async () => current)
    const api: LearnerSubscriptionApi = {
      entitlement: entitlementApi,
      plans: vi.fn(async () => ({
        gstin_is_placeholder: false,
        plans: membershipPlans(),
      })),
      checkout: vi.fn(async () => ({ status: 'pending' })),
    }
    const user = userEvent.setup()

    render(
      <SubscriptionPanel
        api={api}
        locale="en-IN"
        localizationPolicy="development-preview"
      />,
    )

    await user.click(
      await screen.findByRole('button', { name: 'Choose Premium features' }),
    )
    expect(await screen.findByText(/Checkout request pending/)).toBeInTheDocument()
    const checkAccess = screen.getByRole('button', { name: 'Check access' })
    await user.click(checkAccess)

    expect(entitlementApi).toHaveBeenCalledTimes(2)
    expect(
      await screen.findByText(/could not confirm whether access changed/),
    ).toBeInTheDocument()
  })

  it('opens Razorpay for a server-created order and confirms before reporting access', async () => {
    const current = entitlement({
      plan_code: null,
      plan_name: null,
      status: 'expired',
    })
    const entitlementApi = vi
      .fn()
      .mockResolvedValueOnce(current)
      .mockResolvedValueOnce(entitlement({
        is_demo: false,
        plan_code: 'monthly_modules',
        plan_name: 'Premium features',
        status: 'active',
      }))
    const confirmCheckout = vi.fn(async () => ({
      id: '01M2CHECKOUT000000000000001',
      status: 'completed',
    }))
    const paymentCheckout = vi.fn(async () => ({
      orderId: 'order_test12345678',
      paymentId: 'pay_test12345678',
      signature: 'a'.repeat(64),
    }))
    const api: LearnerSubscriptionApi = {
      entitlement: entitlementApi,
      plans: vi.fn(async () => ({
        gstin_is_placeholder: false,
        plans: membershipPlans(),
      })),
      checkout: vi.fn(async () => ({
        currency: 'INR' as const,
        id: '01M2CHECKOUT000000000000001',
        provider: 'razorpay',
        provider_order_id: 'order_test12345678',
        provider_public_key: 'rzp_test_public123456',
        status: 'pending' as const,
        total_paise: 35400,
      })),
      confirmCheckout,
    }
    const user = userEvent.setup()

    render(
      <SubscriptionPanel
        api={api}
        locale="en-IN"
        localizationPolicy="development-preview"
        paymentCheckout={paymentCheckout}
      />,
    )

    await user.click(
      await screen.findByRole('button', { name: 'Choose Premium features' }),
    )
    await waitFor(() => expect(paymentCheckout).toHaveBeenCalledWith({
      amount: 35400,
      currency: 'INR',
      description: 'Premium features',
      key: 'rzp_test_public123456',
      name: 'Global Spoken English App',
      orderId: 'order_test12345678',
    }))
    expect(confirmCheckout).toHaveBeenCalledWith(
      '01M2CHECKOUT000000000000001',
      'pay_test12345678',
      'a'.repeat(64),
    )
    expect(await screen.findByText(/Access confirmed from the authoritative/)).toBeInTheDocument()
  })

  it('preserves the locked-level accomplishment and provides a recoverable close action', async () => {
    const onClose = vi.fn()
    const api: LearnerSubscriptionApi = {
      entitlement: vi.fn(async () => entitlement()),
      plans: vi.fn(async () => ({ gstin_is_placeholder: false, plans: membershipPlans() })),
      checkout: vi.fn(),
    }
    const user = userEvent.setup()
    render(
      <SubscriptionPanel
        api={api}
        context={{ completedLevelNumber: 2, lockedLevelNumber: 3, returnTargetId: 'level-upgrade-3' }}
        locale="en-IN"
        localizationPolicy="development-preview"
        onClose={onClose}
      />,
    )

    expect(await screen.findByText(/You completed Level 2/)).toHaveTextContent('Level 3')
    await user.click(screen.getByRole('button', { name: 'Close plans' }))
    expect(onClose).toHaveBeenCalledOnce()
    const continueFree = screen.getByRole('link', { name: 'Continue free / review prior lessons' })
    expect(continueFree).toHaveAttribute('href', '#learn')
    await user.click(continueFree)
    expect(onClose).toHaveBeenCalledTimes(2)
    expect(window.location.hash).not.toBe('#learn')
  })

  it('hides itself for institutional learners', async () => {
    const plans = vi.fn(async () => ({ gstin_is_placeholder: false, plans: [] }))
    const api: LearnerSubscriptionApi = {
      entitlement: vi.fn(async () =>
        entitlement({
          applies: false,
          is_demo: false,
          live_ai_seconds_remaining: null,
        }),
      ),
      plans,
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
    expect(plans).not.toHaveBeenCalled()
    expect(api.checkout).not.toHaveBeenCalled()
  })
})
