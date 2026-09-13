import { cleanup, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { OperatorEntitlementAdministration } from './OperatorEntitlementAdministration'
import type { OperatorEntitlementApi } from './OperatorEntitlementApi'

afterEach(() => {
  cleanup()
  vi.restoreAllMocks()
})

describe('operator entitlement administration', () => {
  it('loads GST settings and grants a plan', async () => {
    const updateTax = vi.fn(async () => undefined)
    const grant = vi.fn(async () => undefined)
    const learner = vi.fn(async () => ({
      entitlements: [
        {
          id: '01JENTITLEMENT000000000009',
          plan_code: 'monthly_modules_roleplay',
          status: 'active',
          live_ai_seconds_used: 0,
        },
      ],
    }))
    const grantCourtesyPremium = vi.fn(async () => undefined)
    const api: OperatorEntitlementApi = {
      workspace: vi.fn(async () => ({
        tax: {
          gst_registered: true,
          gstin: '29AAAAA0000A1Z5',
          gst_rate_bps: 1800,
          gstin_is_placeholder: true,
          version: 1,
        },
        plans: [
          {
            id: '01JPLANROLEPLAY00000000001',
            code: 'monthly_modules_roleplay',
            name: 'Premium',
            amount_ex_gst_paise: 50000,
            duration_value: 1,
            allows_roleplay: true,
            max_level_number: null,
            live_ai_cap_seconds: null,
            is_demo: false,
            status: 'active',
            version: 1,
          },
        ],
        courtesy_premium: { granted_memberships: 0 },
      })),
      learner,
      updateTax,
      grant,
      grantCourtesyPremium,
      updatePlan: vi.fn(),
      restartClock: vi.fn(),
      restartMeter: vi.fn(),
      revoke: vi.fn(),
      clearThrottle: vi.fn(),
    }
    const user = userEvent.setup()

    render(
      <OperatorEntitlementAdministration
        api={api}
        createCommandId={() => '01JCOMMAND00000000000000001'}
        locale="en-IN"
        localizationPolicy="development-preview"
      />,
    )

    expect(
      await screen.findByText(/Dummy GSTIN is in use/),
    ).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Save GST settings' }))
    expect(updateTax).toHaveBeenCalledOnce()

    await user.type(
      screen.getByRole('textbox', { name: 'Learner membership ID' }),
      '01JLEARNER0000000000000001',
    )
    await user.click(
      screen.getByRole('button', { name: 'Grant 1-month Premium' }),
    )
    expect(grant).toHaveBeenCalledWith(
      '01JLEARNER0000000000000001',
      '01JPLANROLEPLAY00000000001',
      '01JCOMMAND00000000000000001',
    )
    await user.click(
      screen.getByRole('button', {
        name: 'Grant 1-month Premium to all Direct learners',
      }),
    )
    expect(grantCourtesyPremium).toHaveBeenCalledWith(
      '01JCOMMAND00000000000000001',
    )
  })
})
