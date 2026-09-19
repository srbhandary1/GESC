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
    ).toHaveAttribute('id', 'shell-live-ai-minutes')
    expect(screen.getByText('AI')).toBeInTheDocument()
    expect(screen.getByText('89 min')).toBeInTheDocument()

    await user.click(
      screen.getByRole('button', { name: 'View plan and billing' }),
    )

    expect(onOpenAccount).toHaveBeenCalledTimes(1)
    expect(onOpenAccount).toHaveBeenCalledWith()
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

  it('hides demo seconds in global chrome and shows them only in Speak context', () => {
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

    expect(screen.queryByRole('button')).not.toBeInTheDocument()

    rerender(
      <LiveAiMinutesChip
        context="speak"
        entitlement={entitlement({
          is_demo: true,
          live_ai_seconds_remaining: 240,
        })}
        locale="en-IN"
        localizationPolicy={INTERFACE_RESOLUTION_POLICY}
        onOpenAccount={() => {}}
      />,
    )

    expect(screen.getByRole('button')).toHaveTextContent(
      'Demo use: 240s available',
    )

    rerender(
      <LiveAiMinutesChip
        context="speak"
        entitlement={entitlement({ applies: false })}
        locale="en-IN"
        localizationPolicy={INTERFACE_RESOLUTION_POLICY}
        onOpenAccount={() => {}}
      />,
    )

    expect(screen.queryByRole('button')).not.toBeInTheDocument()
  })
})
