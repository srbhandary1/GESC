import { describe, expect, it, vi } from 'vitest'
import {
  OperatorOrganizationApiError,
  WebOperatorOrganizationApi,
} from './OperatorOrganizationApi'

describe('web operator organization API', () => {
  it('uses bounded encoded collection reads with cookie credentials', async () => {
    const requests: Request[] = []
    const api = new WebOperatorOrganizationApi({
      baseUrl: 'https://app.global-english.test/api/v1',
      fetch: vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
        requests.push(new Request(input, init))

        return Response.json({
          allowed_actions: [],
          data: [],
          next_cursor: null,
        })
      }) as typeof fetch,
      readCookie: () => 'other=x; XSRF-TOKEN=csrf%20value',
    })

    await api.listBranches('tenant-bound+/=')

    expect(requests[0]?.url).toBe(
      'https://app.global-english.test/api/v1/operator/branches?limit=25&cursor=tenant-bound%2B%2F%3D',
    )
    expect(requests[0]?.credentials).toBe('include')
    expect(requests[0]?.method).toBe('GET')
    expect(requests[0]?.headers.get('X-XSRF-TOKEN')).toBe('csrf value')
  })

  it('sends only the allowlisted invitation body with CSRF and header-only idempotency', async () => {
    let request: Request | null = null
    const api = new WebOperatorOrganizationApi({
      baseUrl: 'https://app.global-english.test/api/v1',
      fetch: vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
        request = new Request(input, init)

        return Response.json(
          {
            activation_code: 'GE-ONLY-ONCE',
            data: invitation(),
            replayed: false,
          },
          { status: 201 },
        )
      }) as typeof fetch,
      readCookie: () => 'XSRF-TOKEN=csrf-token',
    })

    const response = await api.issueInvitation(
      {
        expected_version: 0,
        expires_in_hours: 72,
        membership_type: 'trainer',
      },
      '11111111-2222-4333-8444-555555555555',
    )

    expect(response.activation_code).toBe('GE-ONLY-ONCE')
    expect(request).not.toBeNull()
    expect((request as unknown as Request).url).toBe(
      'https://app.global-english.test/api/v1/operator/institution-invites',
    )
    expect((request as unknown as Request).method).toBe('POST')
    expect(
      (request as unknown as Request).headers.get('Idempotency-Key'),
    ).toBe('11111111-2222-4333-8444-555555555555')
    expect(
      (request as unknown as Request).headers.get('X-XSRF-TOKEN'),
    ).toBe('csrf-token')
    expect(await (request as unknown as Request).json()).toEqual({
      expected_version: 0,
      expires_in_hours: 72,
      membership_type: 'trainer',
    })
  })

  it.each([
    [403, 'problem.fresh_mfa_required', 'fresh_mfa'],
    [403, 'problem.forbidden', 'forbidden'],
    [409, 'problem.organization_administration.stale_state', 'stale_state'],
    [
      409,
      'problem.organization_administration.idempotency_conflict',
      'idempotency_conflict',
    ],
    [
      409,
      'problem.organization_administration.last_manager_required',
      'last_manager_required',
    ],
    [
      409,
      'problem.organization_administration.active_dependency',
      'active_dependency',
    ],
    [
      422,
      'problem.organization_administration.self_target_forbidden',
      'self_target_forbidden',
    ],
    [422, 'problem.validation_failed', 'validation'],
  ] as const)(
    'maps status %s and exact key %s to %s',
    async (status, messageKey, kind) => {
      const api = new WebOperatorOrganizationApi({
        fetch: vi.fn(async () =>
          Response.json(
            {
              detail: 'secret database and identity explanation',
              message_key: messageKey,
            },
            { status },
          ),
        ) as typeof fetch,
      })

      await expect(
        api.archiveBranch(
          '01J00000000000000000000001',
          { expected_version: 1 },
          '11111111-2222-4333-8444-555555555555',
        ),
      ).rejects.toEqual(new OperatorOrganizationApiError(kind))
    },
  )

  it('discards reflected detail and near-match or unknown problem keys', async () => {
    const responses = [
      Response.json(
        {
          detail: 'secret database and identity explanation',
          message_key: 'problem.fresh_mfa_required.extra',
        },
        { status: 403 },
      ),
      Response.json(
        {
          detail: 'changed relationship identifiers',
          message_key: 'problem.organization_administration.unknown',
        },
        { status: 409 },
      ),
    ]
    const api = new WebOperatorOrganizationApi({
      fetch: vi.fn(async () => responses.shift() ?? new Response(null, { status: 500 })) as typeof fetch,
    })

    await expect(
      api.archiveBranch(
        '01J00000000000000000000001',
        { expected_version: 1 },
        '11111111-2222-4333-8444-555555555555',
      ),
    ).rejects.toEqual(new OperatorOrganizationApiError('unavailable'))
    await expect(
      api.archiveBranch(
        '01J00000000000000000000001',
        { expected_version: 1 },
        '11111111-2222-4333-8444-555555555556',
      ),
    ).rejects.toEqual(new OperatorOrganizationApiError('unavailable'))
    await expect(
      api.archiveBranch(
        '01J00000000000000000000001',
        { expected_version: 1 },
        '11111111-2222-4333-8444-555555555557',
      ),
    ).rejects.not.toThrow(/database|identity|relationship/u)
  })

  it('accepts exact invite replay without inventing a replacement secret', async () => {
    const api = new WebOperatorOrganizationApi({
      fetch: vi.fn(async () =>
        Response.json({
          activation_code: null,
          data: invitation(),
          replayed: true,
        }),
      ) as typeof fetch,
    })

    await expect(
      api.issueInvitation(
        {
          expected_version: 0,
          expires_in_hours: 72,
          membership_type: 'trainer',
        },
        '11111111-2222-4333-8444-555555555555',
      ),
    ).resolves.toMatchObject({ activation_code: null, replayed: true })
  })
})

function invitation() {
  return {
    accepted_at: null,
    allowed_actions: ['invitation.revoke'] as const,
    created_at: '2026-08-09T10:00:00Z',
    expires_at: '2026-08-12T10:00:00Z',
    id: '01J00000000000000000000001',
    membership_type: 'trainer' as const,
    status: 'pending' as const,
    updated_at: '2026-08-09T10:00:00Z',
    version: 1,
  }
}
