import {
  defaultAuthenticationRuntime,
  type ApiCsrfProtection,
} from '../auth/defaultAuthentication'

export type TuitionRequestStatus = 'submitted' | 'acknowledged'

export interface TuitionRequest {
  readonly id: string
  readonly preferred_window: string
  readonly topic: string
  readonly notes: string | null
  readonly status: TuitionRequestStatus
  readonly created_at: string | null
}

export interface InstituteContact {
  readonly phone: string | null
  readonly whatsapp: string | null
  readonly email: string | null
}

export interface TuitionRequestListing {
  readonly requests: readonly TuitionRequest[]
  readonly institute_contact: InstituteContact
}

export interface TuitionRequestsApi {
  list(signal?: AbortSignal): Promise<TuitionRequestListing>
  create(
    body: Readonly<{
      preferred_window: string
      topic: string
      notes?: string
    }>,
    idempotencyKey: string,
  ): Promise<TuitionRequest>
}

export class TuitionRequestsApiError extends Error {
  constructor() {
    super('The tuition request could not be completed.')
    this.name = 'TuitionRequestsApiError'
  }
}

export interface WebTuitionRequestsApiOptions {
  readonly baseUrl?: string
  readonly csrfProtection?: ApiCsrfProtection
  readonly fetch?: typeof fetch
  readonly readCookie?: () => string
}

export class WebTuitionRequestsApi implements TuitionRequestsApi {
  readonly #baseUrl: string
  readonly #csrfProtection: ApiCsrfProtection
  readonly #fetch: typeof fetch
  readonly #readCookie: () => string

  constructor(options: WebTuitionRequestsApiOptions = {}) {
    this.#baseUrl = options.baseUrl ?? '/api/v1'
    this.#csrfProtection =
      options.csrfProtection ?? defaultAuthenticationRuntime.csrfProtection
    this.#fetch = options.fetch ?? globalThis.fetch.bind(globalThis)
    this.#readCookie =
      options.readCookie ??
      (() => (typeof document === 'undefined' ? '' : document.cookie))
  }

  list(signal?: AbortSignal) {
    return this.#json<TuitionRequestListing>('/me/tuition-requests', { signal })
  }

  create(
    body: Readonly<{
      preferred_window: string
      topic: string
      notes?: string
    }>,
    idempotencyKey: string,
  ) {
    return this.#json<TuitionRequest>('/me/tuition-requests', {
      body: JSON.stringify(body),
      headers: { 'Idempotency-Key': idempotencyKey },
      method: 'POST',
    })
  }

  async #json<T>(path: string, init: RequestInit): Promise<T> {
    const headers: Record<string, string> = {
      Accept: 'application/json',
      ...(init.body === undefined ? {} : { 'Content-Type': 'application/json' }),
      ...(init.headers as Record<string, string> | undefined),
    }
    if (this.#csrfProtection === 'sanctum-cookie') {
      headers['X-XSRF-TOKEN'] = this.#xsrfToken()
    }
    const response = await this.#fetch(`${this.#baseUrl}${path}`, {
      ...init,
      credentials: 'include',
      headers,
    })
    if (!response.ok) {
      throw new TuitionRequestsApiError()
    }
    try {
      return (await response.json()) as T
    } catch {
      throw new TuitionRequestsApiError()
    }
  }

  #xsrfToken(): string {
    const token = this.#readCookie()
      .split(';')
      .map((part) => part.trim())
      .find((part) => part.startsWith('XSRF-TOKEN='))
      ?.slice('XSRF-TOKEN='.length)
    return token === undefined ? '' : decodeURIComponent(token)
  }
}

export const defaultTuitionRequestsApi = new WebTuitionRequestsApi({
  baseUrl: defaultAuthenticationRuntime.apiBaseUrl,
  csrfProtection: defaultAuthenticationRuntime.csrfProtection,
  fetch: defaultAuthenticationRuntime.apiFetch,
})
