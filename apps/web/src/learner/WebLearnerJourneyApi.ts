import {
  LearnerJourneyUnavailableError,
  type AttemptResponseInput,
  type LearnerAttempt,
  type LearnerHome,
  type LearnerJourneyApi,
  type LearnerLevelVideoCatalog,
  type LearnerMission,
  type LearnerProgress,
  type MissionResult,
  type OfflineBundle,
  type OfflineCompletionInput,
  type OnboardingInput,
} from './LearnerJourneyApi'
import {
  defaultAuthenticationRuntime,
  type ApiCsrfProtection,
} from '../auth/defaultAuthentication'

export interface WebLearnerJourneyApiOptions {
  readonly authenticatedPrivateMedia?: boolean
  readonly baseUrl?: string
  readonly csrfProtection?: ApiCsrfProtection
  readonly fetch?: typeof fetch
  readonly readCookie?: () => string
}

const LESSON_VIDEO_MAXIMUM_BYTES = 20_000_000

export class WebLearnerJourneyApi implements LearnerJourneyApi {
  readonly #baseUrl: string
  readonly #csrfProtection: ApiCsrfProtection
  readonly #fetch: typeof fetch
  readonly #readCookie: () => string
  readonly downloadModelAudio?: (
    url: string,
    signal?: AbortSignal,
  ) => Promise<Blob>
  readonly downloadLessonVideo?: (
    url: string,
    expectedBytes: number,
    signal?: AbortSignal,
  ) => Promise<Blob>

  constructor(options: WebLearnerJourneyApiOptions = {}) {
    this.#baseUrl = options.baseUrl ?? '/api/v1'
    this.#csrfProtection =
      options.csrfProtection ?? 'sanctum-cookie'
    this.#fetch = options.fetch ?? globalThis.fetch.bind(globalThis)
    this.#readCookie =
      options.readCookie ??
      (() => (typeof document === 'undefined' ? '' : document.cookie))

    if (options.authenticatedPrivateMedia === true) {
      this.downloadModelAudio = (url, signal) =>
        this.#downloadMediaBlob(url, 'audio/*', signal)
      this.downloadLessonVideo = (url, expectedBytes, signal) =>
        this.#downloadLessonVideo(url, expectedBytes, signal)
    }
  }

  home(signal?: AbortSignal): Promise<LearnerHome> {
    return this.#json<LearnerHome>('/home', { signal })
  }

  progressDashboard(signal?: AbortSignal): Promise<import('./LearnerJourneyApi').LearnerProgressDashboard> {
    return this.#json<import('./LearnerJourneyApi').LearnerProgressDashboard>('/me/progress-dashboard', { signal })
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

  listLevelVideos(
    enrollmentId: string,
    signal?: AbortSignal,
  ): Promise<LearnerLevelVideoCatalog> {
    const query = new URLSearchParams({ enrollment_id: enrollmentId })

    return this.#json<LearnerLevelVideoCatalog>(
      `/me/level-videos?${query.toString()}`,
      { signal },
    )
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

  offlineBundle(
    missionId: string,
    enrollmentId: string,
    signal?: AbortSignal,
  ): Promise<OfflineBundle> {
    const query = new URLSearchParams({ enrollment_id: enrollmentId })

    return this.#json<OfflineBundle>(
      `/missions/${encodeURIComponent(missionId)}/offline-bundle?${query.toString()}`,
      { signal },
    )
  }

  async downloadMedia(
    url: string,
    signal?: AbortSignal,
  ): Promise<Uint8Array<ArrayBuffer>> {
    let response: Response

    try {
      response = await this.#fetch(url, {
        credentials: 'include',
        headers: { Accept: 'audio/*' },
        signal,
      })
    } catch {
      throw new LearnerJourneyUnavailableError()
    }

    if (!response.ok) {
      throw new LearnerJourneyUnavailableError(response.status)
    }

    return new Uint8Array(await response.arrayBuffer())
  }

  async #downloadMediaBlob(
    url: string,
    accept: string,
    signal?: AbortSignal,
  ): Promise<Blob> {
    let response: Response

    try {
      response = await this.#fetch(url, {
        credentials: 'include',
        headers: { Accept: accept },
        signal,
      })
    } catch {
      throw new LearnerJourneyUnavailableError()
    }

    if (!response.ok) {
      throw new LearnerJourneyUnavailableError(response.status)
    }

    return response.blob()
  }

  async #downloadLessonVideo(
    url: string,
    expectedBytes: number,
    signal?: AbortSignal,
  ): Promise<Blob> {
    if (
      !Number.isSafeInteger(expectedBytes) ||
      expectedBytes < 1 ||
      expectedBytes > LESSON_VIDEO_MAXIMUM_BYTES
    ) {
      throw new LearnerJourneyUnavailableError()
    }

    let response: Response

    try {
      response = await this.#fetch(url, {
        credentials: 'include',
        headers: { Accept: 'video/mp4' },
        signal,
      })
    } catch {
      throw new LearnerJourneyUnavailableError()
    }

    if (!response.ok) {
      throw new LearnerJourneyUnavailableError(response.status)
    }

    const contentType = response.headers
      .get('Content-Type')
      ?.split(';', 1)[0]
      ?.trim()
      .toLowerCase()
    const contentLength = strictPositiveInteger(
      response.headers.get('Content-Length'),
    )

    if (
      contentType !== 'video/mp4' ||
      contentLength !== expectedBytes
    ) {
      throw new LearnerJourneyUnavailableError(response.status)
    }

    try {
      return await boundedResponseBlob(
        response,
        expectedBytes,
        contentType,
      )
    } catch {
      throw new LearnerJourneyUnavailableError(response.status)
    }
  }

  completeOffline(
    input: OfflineCompletionInput,
    commandId: string,
    signal?: AbortSignal,
  ): Promise<MissionResult> {
    return this.#mutation<MissionResult>('/offline-completions', {
      body: JSON.stringify(input),
      headers: {
        'Idempotency-Key': commandId,
      },
      method: 'POST',
      signal,
    })
  }

  async #mutation<T>(
    path: string,
    init: RequestInit,
  ): Promise<T> {
    if (this.#csrfProtection === 'none') {
      return this.#json<T>(path, init)
    }

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
      throw new LearnerJourneyUnavailableError(response.status)
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

function strictPositiveInteger(value: string | null): number | undefined {
  if (value === null || !/^[1-9]\d*$/u.test(value)) {
    return undefined
  }

  const parsed = Number(value)

  return Number.isSafeInteger(parsed) ? parsed : undefined
}

async function boundedResponseBlob(
  response: Response,
  expectedBytes: number,
  contentType: string,
): Promise<Blob> {
  if (response.body === null) {
    const blob = await response.blob()

    if (blob.size !== expectedBytes) {
      throw new Error('Unexpected lesson-video response length.')
    }

    return new Blob([blob], { type: contentType })
  }

  const reader = response.body.getReader()
  const chunks: BlobPart[] = []
  let receivedBytes = 0

  try {
    while (true) {
      const { done, value } = await reader.read()

      if (done) {
        break
      }

      receivedBytes += value.byteLength

      if (receivedBytes > expectedBytes) {
        await reader.cancel()
        throw new Error('Lesson-video response exceeded its declared length.')
      }

      const copy = new Uint8Array(value.byteLength)
      copy.set(value)
      chunks.push(copy)
    }
  } finally {
    reader.releaseLock()
  }

  if (receivedBytes !== expectedBytes) {
    throw new Error('Lesson-video response ended before its declared length.')
  }

  return new Blob(chunks, { type: contentType })
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

export const defaultLearnerJourneyApi = new WebLearnerJourneyApi({
  authenticatedPrivateMedia:
    defaultAuthenticationRuntime.privateMediaRequiresFetch,
  baseUrl: defaultAuthenticationRuntime.apiBaseUrl,
  csrfProtection: defaultAuthenticationRuntime.csrfProtection,
  fetch: defaultAuthenticationRuntime.apiFetch,
})
