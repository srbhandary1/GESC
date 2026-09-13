import { cleanup, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { INTERFACE_RESOLUTION_POLICY } from '../localization/interfaceLocalization'
import { LiveAiMinutesChip } from './LiveAiMinutesChip'
import type { LearnerEntitlement } from './LearnerSubscriptionApi'

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
    plan_code: 'monthly_modules_roleplay',
    plan_name: 'Premium with Role-play',
    status: 'active',
    allows_roleplay: true,
    allows_free_speech: true,
    allowed_session_parts: [
      'introduction',
      'practice',
      'conversation',
      'interaction',
    ],
    max_level_number: null,
    is_demo: false,
    expires_at: '2026-10-01T00:00:00+00:00',
    live_ai_seconds_remaining: 5340,
    live_ai_cap_seconds: 7200,
    gstin_is_placeholder: true,
    ...overrides,
  }
}

describe('LiveAiMinutesChip', () => {
  it('shows paid minutes and opens account on click', async () => {
    const user = userEvent.setup()
    const onOpenAccount = vi.fn()

    render(
      <LiveAiMinutesChip
        entitlement={entitlement()}
        locale="en-IN"
        localizationPolicy={INTERFACE_RESOLUTION_POLICY}
        onOpenAccount={onOpenAccount}
      />,
    )

    expect(
      screen.getByRole('button', { name: 'View plan and billing' }),
    ).toHaveTextContent('89 min left')

    await user.click(
      screen.getByRole('button', { name: 'View plan and billing' }),
    )

    expect(onOpenAccount).toHaveBeenCalledTimes(1)
  })

  it('hides the chip for demo plans without a live-AI pool', () => {
    render(
      <LiveAiMinutesChip
        entitlement={entitlement({
          is_demo: true,
          live_ai_seconds_remaining: null,
          live_ai_cap_seconds: null,
        })}
        locale="en-IN"
        localizationPolicy={INTERFACE_RESOLUTION_POLICY}
        onOpenAccount={() => {}}
      />,
    )

    expect(screen.queryByRole('button')).not.toBeInTheDocument()
  })

  it('shows demo seconds and hides when entitlement does not apply', () => {
    const { rerender } = render(
      <LiveAiMinutesChip
        entitlement={entitlement({
          is_demo: true,
          live_ai_seconds_remaining: 240,
        })}
        locale="en-IN"
        localizationPolicy={INTERFACE_RESOLUTION_POLICY}
        onOpenAccount={() => {}}
      />,
    )

    expect(screen.getByRole('button')).toHaveTextContent('240s left')

    rerender(
      <LiveAiMinutesChip
        entitlement={entitlement({
          is_demo: true,
          live_ai_seconds_remaining: 1,
        })}
        locale="en-IN"
        localizationPolicy={INTERFACE_RESOLUTION_POLICY}
        onOpenAccount={() => {}}
      />,
    )

    expect(screen.getByRole('button')).toHaveTextContent('Demo ended')

    rerender(
      <LiveAiMinutesChip
        entitlement={entitlement({ applies: false })}
        locale="en-IN"
        localizationPolicy={INTERFACE_RESOLUTION_POLICY}
        onOpenAccount={() => {}}
      />,
    )

    expect(screen.queryByRole('button')).not.toBeInTheDocument()
  })
})
