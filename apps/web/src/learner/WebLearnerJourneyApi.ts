import {
  LearnerJourneyUnavailableError,
  type AttemptResponseInput,
  type LearnerAttempt,
  type LearnerHome,
  type LearnerJourneyApi,
  type LearnerMission,
  type LearnerProgress,
  type MissionResult,
  type OnboardingInput,
} from './LearnerJourneyApi'

export interface WebLearnerJourneyApiOptions {
  readonly baseUrl?: string
  readonly fetch?: typeof fetch
  readonly readCookie?: () => string
}

export class WebLearnerJourneyApi implements LearnerJourneyApi {
  readonly #baseUrl: string
  readonly #fetch: typeof fetch
  readonly #readCookie: () => string

  constructor(options: WebLearnerJourneyApiOptions = {}) {
    this.#baseUrl = options.baseUrl ?? '/api/v1'
    this.#fetch = options.fetch ?? globalThis.fetch.bind(globalThis)
    this.#readCookie =
      options.readCookie ??
      (() => (typeof document === 'undefined' ? '' : document.cookie))
  }

  home(signal?: AbortSignal): Promise<LearnerHome> {
    return this.#json<LearnerHome>('/home', { signal })
  }

  updateOnboarding(
    input: OnboardingInput,
    commandId: string,
    signal?: AbortSignal,
  ): Promise<LearnerHome> {
    return this.#mutation<LearnerHome>('/me/onboarding', {
      body: JSON.stringify(input),
      headers: {
        'Idempotency-Key': commandId,
      },
      method: 'PUT',
      signal,
    })
  }

  mission(
    missionId: string,
    enrollmentId: string,
    signal?: AbortSignal,
  ): Promise<LearnerMission> {
    const query = new URLSearchParams({ enrollment_id: enrollmentId })

    return this.#json<LearnerMission>(
      `/missions/${encodeURIComponent(missionId)}?${query.toString()}`,
      { signal },
    )
  }

  startAttempt(
    missionId: string,
    enrollmentId: string,
    commandId: string,
    signal?: AbortSignal,
  ): Promise<LearnerAttempt> {
    return this.#mutation<LearnerAttempt>(
      `/missions/${encodeURIComponent(missionId)}/attempts`,
      {
        body: JSON.stringify({ enrollment_id: enrollmentId }),
        headers: {
          'Idempotency-Key': commandId,
        },
        method: 'POST',
        signal,
      },
    )
  }

  async recordResponse(
    attemptId: string,
    input: AttemptResponseInput,
    commandId: string,
    signal?: AbortSignal,
  ): Promise<void> {
    await this.#mutation<unknown>(
      `/attempts/${encodeURIComponent(attemptId)}/responses`,
      {
        body: JSON.stringify(input),
        headers: {
          'Idempotency-Key': commandId,
        },
        method: 'POST',
        signal,
      },
    )
  }

  submitAttempt(
    attemptId: string,
    commandId: string,
    signal?: AbortSignal,
  ): Promise<MissionResult> {
    return this.#mutation<MissionResult>(
      `/attempts/${encodeURIComponent(attemptId)}/submit`,
      {
        headers: {
          'Idempotency-Key': commandId,
        },
        method: 'POST',
        signal,
      },
    )
  }

  progress(
    enrollmentId: string,
    signal?: AbortSignal,
  ): Promise<LearnerProgress> {
    const query = new URLSearchParams({ enrollment_id: enrollmentId })

    return this.#json<LearnerProgress>(
      `/progress?${query.toString()}`,
      { signal },
    )
  }

  async #mutation<T>(
    path: string,
    init: RequestInit,
  ): Promise<T> {
    const csrfHeaders = await this.#csrfHeaders(init.signal)

    return this.#json<T>(path, {
      ...init,
      headers: {
        ...csrfHeaders,
        ...init.headers,
      },
    })
  }

  async #json<T>(path: string, init: RequestInit): Promise<T> {
    let response: Response

    try {
      response = await this.#fetch(`${this.#baseUrl}${path}`, {
        ...init,
        credentials: 'include',
        headers: {
          Accept: 'application/json',
          ...(init.body === undefined
            ? {}
            : { 'Content-Type': 'application/json' }),
          ...init.headers,
        },
      })
    } catch {
      throw new LearnerJourneyUnavailableError()
    }

    if (!response.ok) {
      throw new LearnerJourneyUnavailableError()
    }

    if (response.status === 204) {
      return undefined as T
    }

    try {
      return (await response.json()) as T
    } catch {
      throw new LearnerJourneyUnavailableError()
    }
  }

  async #csrfHeaders(
    signal: AbortSignal | null | undefined,
  ): Promise<Record<string, string>> {
    let response: Response

    try {
      response = await this.#fetch(this.#csrfCookieUrl(), {
        credentials: 'include',
        headers: { Accept: 'application/json' },
        signal,
      })
    } catch {
      throw new LearnerJourneyUnavailableError()
    }

    const token = readCookieValue(this.#readCookie(), 'XSRF-TOKEN')

    if (!response.ok || token === undefined) {
      throw new LearnerJourneyUnavailableError()
    }

    return { 'X-XSRF-TOKEN': token }
  }

  #csrfCookieUrl(): string {
    if (/^https?:\/\//u.test(this.#baseUrl)) {
      return new URL('/sanctum/csrf-cookie', this.#baseUrl).toString()
    }

    return '/sanctum/csrf-cookie'
  }
}

function readCookieValue(
  cookieHeader: string,
  expectedName: string,
): string | undefined {
  for (const part of cookieHeader.split(';')) {
    const [name, ...valueParts] = part.trim().split('=')

    if (name !== expectedName || valueParts.length === 0) {
      continue
    }

    try {
      return decodeURIComponent(valueParts.join('='))
    } catch {
      return undefined
    }
  }

  return undefined
}

export const defaultLearnerJourneyApi = new WebLearnerJourneyApi()
