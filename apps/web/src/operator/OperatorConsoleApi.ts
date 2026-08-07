export interface OperatorContentDraft {
  readonly program_id: string
  readonly program_key: string
  readonly program_internal_name: string
  readonly curriculum_version_id: string
  readonly version_key: string
  readonly publication_status:
    | 'unpublished'
    | 'published_head'
    | 'superseded_or_other_head'
  readonly head_version: number
  readonly head_publication_id: string | null
  readonly head_manifest_hash: string | null
  readonly head_publication_number: number | null
  readonly latest_english_decision:
    | 'approved'
    | 'rejected'
    | 'withdrawn'
    | null
  readonly latest_kannada_decision:
    | 'approved'
    | 'rejected'
    | 'withdrawn'
    | null
}

export interface OperatorPublicationStatus {
  readonly publication_id: string
  readonly program_id: string
  readonly program_key: string
  readonly curriculum_version_id: string
  readonly version_key: string
  readonly publication_number: number
  readonly manifest_hash: string
  readonly is_head: boolean
  readonly head_version: number | null
  readonly created_at: string
}

export interface OperatorAuditEvent {
  readonly id: string
  readonly action: string
  readonly subject_type: string
  readonly subject_id: string
  readonly actor_membership_id: string | null
  readonly correlation_id: string | null
  readonly occurred_at: string
}

export interface OperatorConsoleApi {
  listDrafts(signal?: AbortSignal): Promise<readonly OperatorContentDraft[]>
  listPublications(
    signal?: AbortSignal,
  ): Promise<readonly OperatorPublicationStatus[]>
  listAuditEvents(
    signal?: AbortSignal,
  ): Promise<readonly OperatorAuditEvent[]>
}

export class OperatorConsoleUnavailableError extends Error {
  constructor(message = 'Operator console unavailable.') {
    super(message)
    this.name = 'OperatorConsoleUnavailableError'
  }
}

export interface WebOperatorConsoleApiOptions {
  readonly baseUrl?: string
  readonly fetch?: typeof fetch
  readonly readCookie?: () => string
}

export class WebOperatorConsoleApi implements OperatorConsoleApi {
  readonly #baseUrl: string
  readonly #fetch: typeof fetch
  readonly #readCookie: () => string

  constructor(options: WebOperatorConsoleApiOptions = {}) {
    this.#baseUrl = options.baseUrl ?? '/api/v1'
    this.#fetch = options.fetch ?? globalThis.fetch.bind(globalThis)
    this.#readCookie =
      options.readCookie ??
      (() => (typeof document === 'undefined' ? '' : document.cookie))
  }

  async listDrafts(
    signal?: AbortSignal,
  ): Promise<readonly OperatorContentDraft[]> {
    const payload = await this.#get<{ items: OperatorContentDraft[] }>(
      '/operator/content/drafts',
      signal,
    )
    return payload.items
  }

  async listPublications(
    signal?: AbortSignal,
  ): Promise<readonly OperatorPublicationStatus[]> {
    const payload = await this.#get<{ items: OperatorPublicationStatus[] }>(
      '/operator/content/publications',
      signal,
    )
    return payload.items
  }

  async listAuditEvents(
    signal?: AbortSignal,
  ): Promise<readonly OperatorAuditEvent[]> {
    const payload = await this.#get<{ items: OperatorAuditEvent[] }>(
      '/operator/audit-events?limit=50',
      signal,
    )
    return payload.items
  }

  async #get<T>(path: string, signal?: AbortSignal): Promise<T> {
    const response = await this.#fetch(`${this.#baseUrl}${path}`, {
      credentials: 'include',
      headers: {
        Accept: 'application/json',
        'X-XSRF-TOKEN': this.#xsrfToken(),
      },
      method: 'GET',
      signal,
    })

    if (!response.ok) {
      throw new OperatorConsoleUnavailableError()
    }

    return (await response.json()) as T
  }

  #xsrfToken(): string {
    const match = /(?:^|;\s*)XSRF-TOKEN=([^;]+)/.exec(this.#readCookie())
    return match === null ? '' : decodeURIComponent(match[1] ?? '')
  }
}

export const defaultOperatorConsoleApi = new WebOperatorConsoleApi()
