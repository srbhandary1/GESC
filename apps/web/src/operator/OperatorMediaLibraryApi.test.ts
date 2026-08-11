import { describe, expect, it, vi } from 'vitest'
import {
  OperatorMediaApiError,
  WebOperatorMediaLibraryApi,
} from './OperatorMediaLibraryApi'

describe('web operator Media Library API', () => {
  it('encodes bounded in-memory filters and uses cookie credentials', async () => {
    const requests: Request[] = []
    const api = new WebOperatorMediaLibraryApi({
      baseUrl: 'https://app.global-english.test/api/v1',
      fetch: vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
        requests.push(new Request(input, init))
        return Response.json({ allowed_actions: [], data: [], next_cursor: null })
      }) as typeof fetch,
      readCookie: () => 'other=x; XSRF-TOKEN=media%20csrf',
    })

    await api.listAssets({
      cursor: 'tenant-bound+/=',
      kind: 'lesson_video',
      state: 'active',
    })
    await api.listUploads({ state: 'scan_clean' })

    expect(requests[0]?.url).toBe(
      'https://app.global-english.test/api/v1/operator/media/assets?limit=25&cursor=tenant-bound%2B%2F%3D&kind=lesson_video&state=active',
    )
    expect(requests[1]?.url).toBe(
      'https://app.global-english.test/api/v1/operator/media/uploads?limit=25&state=scan_clean',
    )
    expect(requests[0]?.credentials).toBe('include')
    expect(requests[0]?.headers.get('X-XSRF-TOKEN')).toBe('media csrf')
  })

  it('keeps binary parts out of JSON and binds exact part authority in headers', async () => {
    let request: Request | null = null
    let requestBody: BodyInit | null | undefined
    const api = new WebOperatorMediaLibraryApi({
      baseUrl: 'https://app.global-english.test/api/v1',
      fetch: vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
        request = new Request(input, init)
        requestBody = init?.body
        return Response.json(
          { replayed: false, result_id: 'part', result_type: 'media_upload_part' },
          { status: 201 },
        )
      }) as typeof fetch,
      readCookie: () => 'XSRF-TOKEN=csrf-token',
    })
    const bytes = new Blob(['private media bytes'], { type: 'application/octet-stream' })

    await api.uploadPart(
      '01J00000000000000000000001',
      bytes,
      2,
      'a'.repeat(64),
      4,
      '11111111-2222-4333-8444-555555555555',
    )

    const sent = request as unknown as Request
    expect(sent.url).toBe(
      'https://app.global-english.test/api/v1/operator/media/uploads/01J00000000000000000000001/parts',
    )
    expect(sent.headers.get('Content-Type')).toBe('application/octet-stream')
    expect(sent.headers.get('Media-Part-Number')).toBe('2')
    expect(sent.headers.get('Media-Part-SHA256')).toBe('a'.repeat(64))
    expect(sent.headers.get('Media-Upload-Version')).toBe('4')
    expect(sent.headers.get('Idempotency-Key')).toBe(
      '11111111-2222-4333-8444-555555555555',
    )
    expect(sent.headers.get('X-XSRF-TOKEN')).toBe('csrf-token')
    expect(requestBody).toBe(bytes)
    expect(requestBody).not.toBeTypeOf('string')
  })

  it('returns a private preview blob without inventing a public URL', async () => {
    const api = new WebOperatorMediaLibraryApi({
      fetch: vi.fn(async () => new Response('audio bytes', {
        headers: {
          'Content-Type': 'audio/wav',
          'X-Content-SHA256': 'b'.repeat(64),
        },
      })) as typeof fetch,
    })

    const preview = await api.previewAsset('01J00000000000000000000001')

    expect(preview.contentType).toBe('audio/wav')
    expect(preview.contentHash).toBe('b'.repeat(64))
    expect(await preview.blob.text()).toBe('audio bytes')
  })

  it.each([
    [403, 'problem.fresh_mfa_required', 'fresh_mfa'],
    [403, 'problem.forbidden', 'forbidden'],
    [404, 'problem.media_library.not_found', 'not_found'],
    [409, 'problem.media_library.stale_upload', 'stale_upload'],
    [409, 'problem.media_library.stale_metadata', 'stale_metadata'],
    [409, 'problem.media_library.stale_lifecycle', 'stale_lifecycle'],
    [409, 'problem.media_library.stale_workspace', 'stale_workspace'],
    [409, 'problem.media_library.duplicate_use', 'duplicate_use'],
    [409, 'problem.media_library.storage_failure', 'storage_failure'],
    [409, 'problem.media_library.idempotency_conflict', 'idempotency_conflict'],
    [422, 'problem.validation_failed', 'validation'],
  ] as const)(
    'maps status %s and exact problem key %s to %s',
    async (status, messageKey, kind) => {
      const api = new WebOperatorMediaLibraryApi({
        fetch: vi.fn(async () => Response.json(
          { detail: 'scanner endpoint and private storage detail', message_key: messageKey },
          { status },
        )) as typeof fetch,
      })

      await expect(api.retire(
        '01J00000000000000000000001',
        1,
        'superseded',
        '11111111-2222-4333-8444-555555555555',
      )).rejects.toEqual(new OperatorMediaApiError(kind))
    },
  )

  it('does not reflect malformed problem details', async () => {
    const api = new WebOperatorMediaLibraryApi({
      fetch: vi.fn(async () => Response.json({
        detail: 'private transcript and scanner host',
        message_key: 'problem.media_library.stale_upload.extra',
      }, { status: 409 })) as typeof fetch,
    })

    await expect(api.finalizeUpload(
      '01J00000000000000000000001',
      2,
      '11111111-2222-4333-8444-555555555555',
    )).rejects.toEqual(new OperatorMediaApiError('unavailable'))
    await expect(api.finalizeUpload(
      '01J00000000000000000000001',
      2,
      '11111111-2222-4333-8444-555555555556',
    )).rejects.not.toThrow(/private|transcript|scanner/u)
  })
})
