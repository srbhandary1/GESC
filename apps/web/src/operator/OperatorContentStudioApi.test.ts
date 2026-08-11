import { describe, expect, it, vi } from 'vitest'
import {
  OperatorContentApiError,
  WebOperatorContentStudioApi,
} from './OperatorContentStudioApi'

describe('web operator Content Studio API', () => {
  it('uses bounded encoded tenant-resource URLs with cookie credentials', async () => {
    const requests: Request[] = []
    const api = new WebOperatorContentStudioApi({
      baseUrl: 'https://app.global-english.test/api/v1',
      fetch: vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
        requests.push(new Request(input, init))
        return Response.json({ allowed_actions: [], data: [], next_cursor: null })
      }) as typeof fetch,
      readCookie: () => 'other=x; XSRF-TOKEN=csrf%20value',
    })

    await api.listWorkspaces('tenant-bound+/=')
    await api.listReviewQueue('kannada', 'queue-bound+/=')

    expect(requests[0]?.url).toBe(
      'https://app.global-english.test/api/v1/operator/content/workspaces?limit=25&cursor=tenant-bound%2B%2F%3D',
    )
    expect(requests[1]?.url).toBe(
      'https://app.global-english.test/api/v1/operator/content/review-queues?limit=25&queue=kannada&cursor=queue-bound%2B%2F%3D',
    )
    expect(requests[0]?.credentials).toBe('include')
    expect(requests[0]?.headers.get('X-XSRF-TOKEN')).toBe('csrf value')
  })

  it('keeps preview content keys encoded and out of browser persistence', async () => {
    let request: Request | null = null
    const api = new WebOperatorContentStudioApi({
      baseUrl: 'https://app.global-english.test/api/v1',
      fetch: vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
        request = new Request(input, init)
        return Response.json({ data: preview() })
      }) as typeof fetch,
    })

    await api.previewDocument(
      '01J00000000000000000000001',
      'greeting model/one',
    )

    expect((request as unknown as Request).url).toBe(
      'https://app.global-english.test/api/v1/operator/content/workspaces/01J00000000000000000000001/preview?document_key=greeting+model%2Fone',
    )
    expect((request as unknown as Request).method).toBe('GET')
  })

  it('sends graph authority in the strict body and idempotency only in its header', async () => {
    let request: Request | null = null
    const api = new WebOperatorContentStudioApi({
      baseUrl: 'https://app.global-english.test/api/v1',
      fetch: vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
        request = new Request(input, init)
        return Response.json(
          {
            replayed: false,
            result_id: '01J00000000000000000000002',
            result_type: 'curriculum_draft_graph_revision',
          },
          { status: 201 },
        )
      }) as typeof fetch,
      readCookie: () => 'XSRF-TOKEN=csrf-token',
    })

    await api.saveGraph(
      '01J00000000000000000000001',
      {
        expected_workspace_version: 2,
        graph: {
          delivery_bundles: [],
          items: [],
          lessons: [],
          levels: [{ key: 'a1', sequence: 1 }],
          modules: [],
          objectives: [],
          schema: 'global-english.curriculum-draft-graph.v2',
        },
      },
      '11111111-2222-4333-8444-555555555555',
    )

    expect((request as unknown as Request).headers.get('Idempotency-Key')).toBe(
      '11111111-2222-4333-8444-555555555555',
    )
    expect((request as unknown as Request).headers.get('X-XSRF-TOKEN')).toBe(
      'csrf-token',
    )
    expect(await (request as unknown as Request).json()).toEqual({
      expected_workspace_version: 2,
      graph: {
        delivery_bundles: [],
        items: [],
        lessons: [],
        levels: [{ key: 'a1', sequence: 1 }],
        modules: [],
        objectives: [],
        schema: 'global-english.curriculum-draft-graph.v2',
      },
    })
  })

  it.each([
    [403, 'problem.fresh_mfa_required', 'fresh_mfa'],
    [403, 'problem.forbidden', 'forbidden'],
    [404, 'problem.content_workspace.not_found', 'not_found'],
    [409, 'problem.content_workspace.stale_version', 'stale_version'],
    [409, 'problem.content_workspace.state_conflict', 'state_conflict'],
    [409, 'problem.content_workspace.head_conflict', 'head_conflict'],
    [409, 'problem.content_workspace.concurrent_change', 'concurrent_change'],
    [409, 'problem.content_workspace.idempotency_conflict', 'idempotency_conflict'],
    [422, 'problem.validation_failed', 'validation'],
  ] as const)(
    'maps status %s and exact problem key %s to %s',
    async (status, messageKey, kind) => {
      const api = new WebOperatorContentStudioApi({
        fetch: vi.fn(async () => Response.json(
          { detail: 'sensitive content and database detail', message_key: messageKey },
          { status },
        )) as typeof fetch,
      })

      await expect(api.submitStructure(
        '01J00000000000000000000001',
        { expected_workspace_version: 2 },
        '11111111-2222-4333-8444-555555555555',
      )).rejects.toEqual(new OperatorContentApiError(kind))
    },
  )

  it('does not reflect malformed or near-match problem detail', async () => {
    const api = new WebOperatorContentStudioApi({
      fetch: vi.fn(async () => Response.json({
        detail: 'private source and learner text',
        message_key: 'problem.content_workspace.stale_version.extra',
      }, { status: 409 })) as typeof fetch,
    })

    await expect(api.submitStructure(
      '01J00000000000000000000001',
      { expected_workspace_version: 2 },
      '11111111-2222-4333-8444-555555555555',
    )).rejects.toEqual(new OperatorContentApiError('unavailable'))
    await expect(api.submitStructure(
      '01J00000000000000000000001',
      { expected_workspace_version: 2 },
      '11111111-2222-4333-8444-555555555556',
    )).rejects.not.toThrow(/private|learner|source/u)
  })
})

function preview() {
  return {
    content_document_id: '01J00000000000000000000002',
    content_type: 'instruction' as const,
    document_key: 'greeting-model',
    english: {
      content_hash: 'a'.repeat(64),
      locale: 'en-IN' as const,
      payload: { schema: 'global-english.governed-text.v1', text: 'Hello.' },
      revision_id: '01J00000000000000000000003',
      revision_number: 1,
    },
    kannada: null,
    localization_requirement: 'kn_required' as const,
    media: [],
    read_only: false,
  }
}
