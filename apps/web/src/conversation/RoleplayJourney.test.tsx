import { cleanup, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import type {
  ConversationJourneyApi,
  FriendSession,
} from './ConversationJourneyApi'
import { RoleplayJourney } from './RoleplayJourney'

afterEach(() => {
  cleanup()
  vi.restoreAllMocks()
})

describe('synthetic Role-play journey', () => {
  it('discloses synthetic_unreviewed and advances Observe→Reflect', async () => {
    const observe = fakeSession('observe')
    const rehearse = fakeSession('rehearse')
    const perform = fakeSession('perform')
    const reflect = fakeSession('reflect', true)
    const advanceStage = vi
      .fn()
      .mockResolvedValueOnce(rehearse)
      .mockResolvedValueOnce(perform)
      .mockResolvedValueOnce(reflect)
    const api: ConversationJourneyApi = {
      startSession: vi.fn(async () => observe),
      advanceStage,
      createTurn: vi.fn(),
      events: vi.fn(),
    }
    const createClientId = vi
      .fn()
      .mockReturnValueOnce('start-command')
      .mockReturnValueOnce('advance-1')
      .mockReturnValueOnce('advance-2')
      .mockReturnValueOnce('advance-3')
    const user = userEvent.setup()

    render(
      <RoleplayJourney
        api={api}
        createClientId={createClientId}
        locale="en-IN"
        localizationPolicy="development-preview"
      />,
    )

    expect(
      screen.getByText(
        'This is a deterministic synthetic Role-play for local testing. It is not approved curriculum or a human.',
      ),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('region', { name: 'Clinic reception Role-play' }),
    ).toHaveAttribute('data-review-status', 'synthetic_unreviewed')

    await user.click(
      screen.getByRole('button', { name: 'Start Role-play' }),
    )
    expect(
      await screen.findByRole('heading', { name: 'Observe' }),
    ).toBeInTheDocument()
    expect(screen.getByText('Greet the receptionist')).toBeInTheDocument()
    expect(api.startSession).toHaveBeenCalledWith(
      {
        coaching_mode: 'gentle',
        persona_key: 'clinic-reception-roleplay-prototype',
        scenario_key: 'clinic-reception-roleplay-scenario',
        ui_locale: 'en-IN',
      },
      'start-command',
    )

    await user.click(screen.getByRole('button', { name: 'Continue' }))
    expect(
      await screen.findByRole('heading', { name: 'Rehearse' }),
    ).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Continue' }))
    expect(
      await screen.findByRole('heading', { name: 'Perform' }),
    ).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Continue' }))
    expect(
      await screen.findByRole('heading', { name: 'Reflect' }),
    ).toBeInTheDocument()
    expect(screen.getByText('Advisory feedback')).toBeInTheDocument()
    expect(screen.getByText('cccccccccccc')).toBeInTheDocument()
    expect(screen.getByText('dddddddddddd')).toBeInTheDocument()
    expect(advanceStage).toHaveBeenNthCalledWith(
      1,
      observe.id,
      { to_stage: 'rehearse' },
      'advance-1',
    )
    expect(advanceStage).toHaveBeenNthCalledWith(
      3,
      perform.id,
      { to_stage: 'reflect' },
      'advance-3',
    )
  })
})

function fakeSession(
  stage: 'observe' | 'rehearse' | 'perform' | 'reflect',
  withFeedback = false,
): FriendSession {
  return {
    coaching_mode: 'gentle',
    id: '01J00000000000000000000011',
    mode: 'roleplay',
    stage,
    persona: {
      definition_hash: 'a'.repeat(64),
      disclosure:
        'I am a deterministic AI role-play prototype, not a human.',
      display_name: 'Clinic Reception Role-play Prototype',
      id: '01J00000000000000000000012',
      key: 'clinic-reception-roleplay-prototype',
      review_status: 'synthetic_unreviewed',
      version: 1,
      version_id: '01J00000000000000000000013',
    },
    replayed: false,
    scenario: {
      definition_hash: 'b'.repeat(64),
      id: '01J00000000000000000000014',
      key: 'clinic-reception-roleplay-scenario',
      review_status: 'synthetic_unreviewed',
      topic_code: 'clinic-reception',
      version: 1,
      version_id: '01J00000000000000000000015',
    },
    objectives: [
      {
        completion_criteria_code: 'synthetic.greet-receptionist.v1',
        id: '01J00000000000000000000016',
        key: 'greet-receptionist',
        title: 'Greet the receptionist',
      },
      {
        completion_criteria_code: 'synthetic.state-appointment-reason.v1',
        id: '01J00000000000000000000017',
        key: 'state-appointment-reason',
        title: 'State the appointment reason',
      },
    ],
    feedback: withFeedback
      ? [
          {
            completion_state: 'met',
            evidence_digest: 'c'.repeat(64),
            id: '01J00000000000000000000018',
            scenario_objective_id: '01J00000000000000000000016',
          },
          {
            completion_state: 'unmet',
            evidence_digest: 'd'.repeat(64),
            id: '01J00000000000000000000019',
            scenario_objective_id: '01J00000000000000000000017',
          },
        ]
      : [],
    status: 'active',
    ui_locale: 'en-IN',
  }
}
