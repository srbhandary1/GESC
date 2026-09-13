import { describe, expect, it, vi } from 'vitest'
import {
  OperatorLearnerApiError,
  WebOperatorLearnerApi,
} from './OperatorLearnerApi'

describe('web operator learner API', () => {
  it('keeps bounded learner filters and cursors in an encoded no-credential URL', async () => {
    let request: Request | null = null
    const api = new WebOperatorLearnerApi({
      baseUrl: 'https://app.global-english.test/api/v1',
      fetch: vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
        request = new Request(input, init)
        return Response.json({
          allowed_actions: [],
          choices: null,
          data: [],
          next_cursor: null,
        })
      }) as typeof fetch,
      readCookie: () => 'other=x; XSRF-TOKEN=csrf%20value',
    })

    await api.listLearners(
      { goal_key: 'workplace-confidence', q: 'Asha Rao', status: 'active' },
      'tenant-bound+/=',
    )

    expect((request as unknown as Request).url).toBe(
      'https://app.global-english.test/api/v1/operator/learners?limit=25&goal_key=workplace-confidence&q=Asha+Rao&status=active&cursor=tenant-bound%2B%2F%3D',
    )
    expect((request as unknown as Request).credentials).toBe('include')
    expect((request as unknown as Request).headers.get('X-XSRF-TOKEN')).toBe(
      'csrf value',
    )
  })

  it('sends invitation authority versions in the body and idempotency only in its header', async () => {
    let request: Request | null = null
    const api = new WebOperatorLearnerApi({
      baseUrl: 'https://app.global-english.test/api/v1',
      fetch: vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
        request = new Request(input, init)
        return Response.json(
          { activation_code: 'GE-ONLY-ONCE', data: invitation(), replayed: false },
          { status: 201 },
        )
      }) as typeof fetch,
      readCookie: () => 'XSRF-TOKEN=csrf-token',
    })

    const response = await api.issueInvitation(
      {
        cohort_id: null,
        expected_cohort_version: null,
        expected_role_version: 4,
        expected_version: 0,
        expires_in_hours: 72,
        role_id: '01J00000000000000000000001',
      },
      '11111111-2222-4333-8444-555555555555',
    )

    expect(response.activation_code).toBe('GE-ONLY-ONCE')
    expect((request as unknown as Request).url).toBe(
      'https://app.global-english.test/api/v1/operator/learner-invitations',
    )
    expect((request as unknown as Request).headers.get('Idempotency-Key')).toBe(
      '11111111-2222-4333-8444-555555555555',
    )
    expect(await (request as unknown as Request).json()).toEqual({
      cohort_id: null,
      expected_cohort_version: null,
      expected_role_version: 4,
      expected_version: 0,
      expires_in_hours: 72,
      role_id: '01J00000000000000000000001',
    })
  })

  it.each([
    [403, 'problem.forbidden', 'forbidden'],
    [409, 'problem.learner_administration.stale_state', 'stale_state'],
    [409, 'problem.learner_administration.idempotency_conflict', 'idempotency_conflict'],
    [409, 'problem.learner_administration.active_dependency', 'active_dependency'],
    [409, 'problem.learner_administration.unsafe_role', 'unsafe_role'],
    [422, 'problem.validation_failed', 'validation'],
  ] as const)(
    'maps status %s and exact problem key %s to %s',
    async (status, messageKey, kind) => {
      const api = new WebOperatorLearnerApi({
        fetch: vi.fn(async () =>
          Response.json(
            { detail: 'sensitive database explanation', message_key: messageKey },
            { status },
          ),
        ) as typeof fetch,
      })

      await expect(
        api.suspendLearner(
          '01J00000000000000000000001',
          { expected_version: 2 },
          '11111111-2222-4333-8444-555555555555',
        ),
      ).rejects.toEqual(new OperatorLearnerApiError(kind))
    },
  )

  it('does not reflect malformed, unknown, or near-match problem detail', async () => {
    const api = new WebOperatorLearnerApi({
      fetch: vi.fn(async () =>
        Response.json(
          {
            detail: 'secret relationship and identity data',
            message_key: 'problem.learner_administration.stale_state.extra',
          },
          { status: 409 },
        ),
      ) as typeof fetch,
    })

    await expect(
      api.suspendLearner(
        '01J00000000000000000000001',
        { expected_version: 2 },
        '11111111-2222-4333-8444-555555555555',
      ),
    ).rejects.toEqual(new OperatorLearnerApiError('unavailable'))
    await expect(
      api.suspendLearner(
        '01J00000000000000000000001',
        { expected_version: 2 },
        '11111111-2222-4333-8444-555555555556',
      ),
    ).rejects.not.toThrow(/database|identity|relationship/u)
  })

  it('accepts exact invitation replay without inventing a recoverable code', async () => {
    const api = new WebOperatorLearnerApi({
      fetch: vi.fn(async () =>
        Response.json({ activation_code: null, data: invitation(), replayed: true }),
      ) as typeof fetch,
    })

    await expect(
      api.issueInvitation(
        {
          cohort_id: null,
          expected_cohort_version: null,
          expected_role_version: 4,
          expected_version: 0,
          expires_in_hours: 24,
          role_id: '01J00000000000000000000001',
        },
        '11111111-2222-4333-8444-555555555555',
      ),
    ).resolves.toMatchObject({ activation_code: null, replayed: true })
  })
})

function invitation() {
  return {
    accepted_at: null,
    allowed_actions: ['learner_invitation.revoke'] as const,
    cohort: null,
    created_at: '2026-08-09T10:00:00Z',
    expires_at: '2026-08-12T10:00:00Z',
    id: '01J00000000000000000000002',
    revoked_at: null,
    role: {
      id: '01J00000000000000000000001',
      key: 'learner',
      name: 'Learner',
      status: 'active',
      version: 4,
    },
    status: 'pending' as const,
    updated_at: '2026-08-09T10:00:00Z',
    version: 1,
  }
}
