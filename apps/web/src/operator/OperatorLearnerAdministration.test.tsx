import { cleanup, render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { OperatorLearnerAdministration } from './OperatorLearnerAdministration'
import {
  OperatorLearnerApiError,
  type OperatorLearner,
  type OperatorLearnerApi,
  type OperatorLearnerEnrollment,
  type OperatorLearnerInvitation,
} from './OperatorLearnerApi'

afterEach(() => {
  cleanup()
  vi.restoreAllMocks()
})

describe('operator learner administration', () => {
  it('loads and retries authorized panels independently', async () => {
    const listLearners = vi
      .fn()
      .mockRejectedValueOnce(new Error('temporary'))
      .mockResolvedValueOnce(learnerCollection([learner()]))
    const listInvitations = vi.fn(async () => invitationCollection([]))
    const listEnrollments = vi.fn()
    const api = learnerApi({ listEnrollments, listInvitations, listLearners })
    const user = userEvent.setup()

    renderAdministration(api, ['learner_directory', 'learner_invitations'])

    const invitations = await screen.findByRole('article', {
      name: 'Learner invitations',
    })
    expect(
      within(invitations).getByRole('button', { name: 'Issue invitation' }),
    ).toBeInTheDocument()
    const directory = screen.getByRole('article', { name: 'Learner directory' })
    expect(within(directory).getByRole('alert')).toHaveTextContent(
      'This panel could not be loaded.',
    )
    expect(listEnrollments).not.toHaveBeenCalled()

    await user.click(within(directory).getByRole('button', { name: 'Try again' }))

    expect(await within(directory).findByText('Asha Learner')).toBeInTheDocument()
    expect(within(invitations).getByRole('button', { name: 'Issue invitation' }))
      .toBeInTheDocument()
    expect(listLearners).toHaveBeenCalledTimes(2)
  })

  it('loads bounded detail and enrollment history only after deliberate learner selection', async () => {
    const currentLearner = learner({
      allowed_actions: ['learner.goal_correct', 'learner_enrollment.create'],
    })
    const getLearner = vi.fn(async () => currentLearner)
    const listEnrollments = vi.fn(async () => enrollmentCollection([enrollment()]))
    const correctGoal = vi.fn(async () => ({
      data: {
        ...currentLearner,
        profile: {
          ...currentLearner.profile!,
          current_goal_key: 'workplace-confidence' as const,
          current_goal_version: 2,
        },
      },
      replayed: false,
    }))
    const api = learnerApi({
      correctGoal,
      getLearner,
      listEnrollments,
      listLearners: vi.fn(async () => learnerCollection([currentLearner])),
    })
    const user = userEvent.setup()

    renderAdministration(api, ['learner_directory', 'learner_enrollments'])

    expect(listEnrollments).not.toHaveBeenCalled()
    await user.click(await screen.findByRole('button', { name: 'Open learner' }))

    expect(await screen.findByRole('heading', { name: 'Selected learner detail' }))
      .toBeInTheDocument()
    expect(await screen.findByText('Learner enrollment')).toBeInTheDocument()
    expect(getLearner).toHaveBeenCalledWith(
      currentLearner.membership_id,
      expect.any(AbortSignal),
    )
    expect(listEnrollments).toHaveBeenCalledWith(
      currentLearner.membership_id,
      undefined,
      expect.any(AbortSignal),
    )
    expect(screen.queryByText(/user_id/u)).not.toBeInTheDocument()

    const detail = screen.getByRole('region', { name: 'Selected learner detail' })
    await user.selectOptions(
      within(detail).getByLabelText('Current goal', { selector: 'select' }),
      'workplace-confidence',
    )
    await user.click(
      within(detail).getByRole('button', { name: 'Correct current goal' }),
    )

    expect(correctGoal).toHaveBeenCalledWith(
      currentLearner.membership_id,
      { expected_version: 1, goal_key: 'workplace-confidence' },
      '11111111-2222-4333-8444-555555555555',
    )
  })

  it('shows a first invitation code only in memory and copies it only on explicit request', async () => {
    const user = userEvent.setup()
    const writeText = vi.fn(async () => undefined)
    Object.defineProperty(globalThis.navigator, 'clipboard', {
      configurable: true,
      value: { writeText },
    })
    const localStorageWrite = vi.spyOn(Storage.prototype, 'setItem')
    const issueInvitation = vi.fn(async () => ({
      activation_code: 'GE-LEARNER-ONLY-ONCE',
      data: invitation(),
      replayed: false,
    }))
    const api = learnerApi({
      issueInvitation,
      listInvitations: vi.fn(async () => invitationCollection([])),
    })

    renderAdministration(api, ['learner_invitations'])

    await user.click(await screen.findByRole('button', { name: 'Issue invitation' }))

    const code = await screen.findByText('GE-LEARNER-ONLY-ONCE')
    expect(code).toHaveAttribute('dir', 'ltr')
    expect(code.closest('[aria-live]')).toBeNull()
    expect(writeText).not.toHaveBeenCalled()
    expect(localStorageWrite).not.toHaveBeenCalled()
    expect(issueInvitation).toHaveBeenCalledWith(
      {
        cohort_id: null,
        expected_cohort_version: null,
        expected_role_version: 3,
        expected_version: 0,
        expires_in_hours: 24,
        role_id: '01J00000000000000000000041',
      },
      '11111111-2222-4333-8444-555555555555',
    )

    await user.click(screen.getByRole('button', { name: 'Copy code' }))
    expect(writeText).toHaveBeenCalledWith('GE-LEARNER-ONLY-ONCE')
    expect(await screen.findByText('The activation code was copied.')).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'I saved the code' }))
    expect(screen.queryByText('GE-LEARNER-ONLY-ONCE')).not.toBeInTheDocument()
  })

  it('restores focus after lifecycle cancellation and offers a stale-state reload', async () => {
    const user = userEvent.setup()
    const currentLearner = learner({ allowed_actions: ['learner.suspend'] })
    const listLearners = vi.fn(async () => learnerCollection([currentLearner]))
    const suspendLearner = vi.fn(async () => {
      throw new OperatorLearnerApiError('stale_state')
    })
    const api = learnerApi({
      getLearner: vi.fn(async () => currentLearner),
      listLearners,
      suspendLearner,
    })

    renderAdministration(api, ['learner_directory'])
    await user.click(await screen.findByRole('button', { name: 'Open learner' }))

    const suspend = await screen.findByRole('button', { name: 'Suspend' })
    await user.click(suspend)
    await user.click(screen.getByRole('button', { name: 'Cancel' }))
    await waitFor(() => expect(suspend).toHaveFocus())

    await user.click(suspend)
    await user.click(screen.getByRole('button', { name: 'Confirm' }))
    expect(await screen.findByText(/record changed after it was loaded/u))
      .toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Reload current data' }))
    expect(listLearners).toHaveBeenCalledTimes(2)
  })

  it('appends cursor pages without replacing learner records already shown', async () => {
    const user = userEvent.setup()
    const listLearners = vi
      .fn()
      .mockResolvedValueOnce({
        ...learnerCollection([learner()]),
        next_cursor: 'tenant-bound-next',
      })
      .mockResolvedValueOnce(
        learnerCollection([
          learner({
            display_name: 'Bhavya Learner',
            membership_id: '01J00000000000000000000012',
          }),
        ]),
      )

    renderAdministration(learnerApi({ listLearners }), ['learner_directory'])
    expect(await screen.findByText('Asha Learner')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Load more' }))

    expect(await screen.findByText('Bhavya Learner')).toBeInTheDocument()
    expect(screen.getByText('Asha Learner')).toBeInTheDocument()
    expect(listLearners).toHaveBeenLastCalledWith(
      {},
      'tenant-bound-next',
      expect.any(AbortSignal),
    )
  })
})

function renderAdministration(
  api: OperatorLearnerApi,
  panels: Parameters<typeof OperatorLearnerAdministration>[0]['panels'],
) {
  return render(
    <OperatorLearnerAdministration
      api={api}
      createCommandId={() => '11111111-2222-4333-8444-555555555555'}
      locale="en-IN"
      localizationPolicy="development-preview"
      panels={panels}
    />,
  )
}

function learnerCollection(data: readonly OperatorLearner[]) {
  return {
    allowed_actions: [],
    choices: {
      cohorts: [
        {
          id: '01J00000000000000000000051',
          key: 'conversation-a',
          name: 'Conversation A',
          version: 2,
        },
      ],
    },
    data,
    next_cursor: null,
  }
}

function invitationCollection(data: readonly OperatorLearnerInvitation[]) {
  return {
    allowed_actions: ['learner_invitation.issue'] as const,
    choices: {
      cohorts: [],
      roles: [
        {
          id: '01J00000000000000000000041',
          key: 'learner',
          name: 'Learner',
          version: 3,
        },
      ],
    },
    data,
    next_cursor: null,
  }
}

function enrollmentCollection(data: readonly OperatorLearnerEnrollment[]) {
  return { allowed_actions: [], data, next_cursor: null }
}

function learner(overrides: Partial<OperatorLearner> = {}): OperatorLearner {
  return {
    allowed_actions: [],
    attempt_count: 4,
    display_name: 'Asha Learner',
    ended_at: null,
    joined_at: '2026-08-01T10:00:00Z',
    latest_attempt_at: '2026-08-09T10:00:00Z',
    membership_id: '01J00000000000000000000011',
    profile: {
      adult_confirmed: true,
      current_goal_key: 'everyday-conversation',
      current_goal_version: 1,
      id: '01J00000000000000000000021',
      onboarding_completed: true,
    },
    status: 'active',
    version: 2,
    ...overrides,
  }
}

function enrollment(): OperatorLearnerEnrollment {
  return {
    activated_at: null,
    allowed_actions: [],
    cohort: {
      id: '01J00000000000000000000051',
      key: 'conversation-a',
      name: 'Conversation A',
      status: 'active',
      version: 2,
    },
    curriculum_pin: null,
    ended_at: null,
    enrolled_at: '2026-08-09T10:00:00Z',
    id: '01J00000000000000000000061',
    learner_membership_id: '01J00000000000000000000011',
    status: 'pending_curriculum',
    trainers: [],
    version: 1,
  }
}

function invitation(): OperatorLearnerInvitation {
  return {
    accepted_at: null,
    allowed_actions: ['learner_invitation.revoke'],
    cohort: null,
    created_at: '2026-08-09T10:00:00Z',
    expires_at: '2026-08-10T10:00:00Z',
    id: '01J00000000000000000000071',
    revoked_at: null,
    role: {
      id: '01J00000000000000000000041',
      key: 'learner',
      name: 'Learner',
      status: 'active',
      version: 3,
    },
    status: 'pending',
    updated_at: '2026-08-09T10:00:00Z',
    version: 1,
  }
}

function learnerApi(overrides: Partial<OperatorLearnerApi> = {}): OperatorLearnerApi {
  const unavailable = vi.fn(async () => {
    throw new Error('Unexpected learner API call')
  })
  return new Proxy(overrides as OperatorLearnerApi, {
    get(target, property, receiver) {
      return Reflect.has(target, property)
        ? Reflect.get(target, property, receiver)
        : unavailable
    },
  })
}
