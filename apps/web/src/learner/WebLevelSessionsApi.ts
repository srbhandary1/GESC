import {
  defaultAuthenticationRuntime,
  type ApiCsrfProtection,
} from '../auth/defaultAuthentication'
import type { FriendSession } from '../conversation/ConversationJourneyApi'
import type {
  CheckSubmissionResult,
  LevelSessionsApi,
  LevelSessionsList,
  LevelSessionDetail,
  LevelSummary,
  SessionPartId,
} from './LevelSessionsApi'

export interface WebLevelSessionsApiOptions {
  readonly authenticatedPrivateMedia?: boolean
  readonly baseUrl?: string
  readonly csrfProtection?: ApiCsrfProtection
  readonly fetch?: typeof fetch
  readonly readCookie?: () => string
}

const LEVEL_SESSION_AUDIO_PATH =
  /^\/api\/v1\/me\/levels\/[1-9]\d{0,2}\/sessions\/(?:introduction|practice|conversation|interaction)\/audio$/u

export class WebLevelSessionsApi implements LevelSessionsApi {
  readonly #baseUrl: string
  readonly #csrfProtection: ApiCsrfProtection
  readonly #fetch: typeof fetch
  readonly #readCookie: () => string
  readonly downloadAudio?: (
    url: string,
    signal?: AbortSignal,
  ) => Promise<Blob>

  constructor(options: WebLevelSessionsApiOptions = {}) {
    this.#baseUrl = options.baseUrl ?? '/api/v1'
    this.#csrfProtection =
      options.csrfProtection ?? 'sanctum-cookie'
    this.#fetch = options.fetch ?? globalThis.fetch.bind(globalThis)
    this.#readCookie =
      options.readCookie ??
      (() => (typeof document === 'undefined' ? '' : document.cookie))

    if (options.authenticatedPrivateMedia === true) {
      this.downloadAudio = (url, signal) => this.#downloadAudio(url, signal)
    }
  }

  listLevels(signal?: AbortSignal) {
    return this.#json<{ levels: LevelSummary[] }>('/me/levels', { signal })
  }

  listSessions(levelNumber: number, signal?: AbortSignal) {
    return this.#json<LevelSessionsList>(
      `/me/levels/${levelNumber}/sessions`,
      { signal },
    )
  }

  sessionDetail(
    levelNumber: number,
    partId: SessionPartId,
    signal?: AbortSignal,
  ) {
    return this.#json<LevelSessionDetail>(
      `/me/levels/${levelNumber}/sessions/${partId}`,
      { signal },
    )
  }

  completeSession(
    levelNumber: number,
    partId: SessionPartId,
    idempotencyKey: string,
    signal?: AbortSignal,
  ) {
    return this.#mutation<{
      replayed: boolean
      completion_id: string
      part_id: string
    }>(`/me/levels/${levelNumber}/sessions/${partId}/completions`, {
      body: JSON.stringify({ idempotency_key: idempotencyKey }),
      method: 'POST',
      signal,
    })
  }

  submitCheck(
    levelNumber: number,
    partId: SessionPartId,
    answers: Record<string, number>,
    idempotencyKey: string,
    signal?: AbortSignal,
  ) {
    return this.#mutation<CheckSubmissionResult>(
      `/me/levels/${levelNumber}/sessions/${partId}/check-submissions`,
      {
        body: JSON.stringify({
          answers,
          idempotency_key: idempotencyKey,
        }),
        method: 'POST',
        signal,
      },
    )
  }

  startConversation(
    levelNumber: number,
    partId: SessionPartId,
    uiLocale: string,
    idempotencyKey: string,
    signal?: AbortSignal,
  ) {
    return this.#mutation<FriendSession>(
      `/me/levels/${levelNumber}/sessions/${partId}/conversation-sessions`,
      {
        body: JSON.stringify({
          idempotency_key: idempotencyKey,
          ui_locale: uiLocale,
        }),
        method: 'POST',
        signal,
      },
    )
  }

  async #downloadAudio(url: string, signal?: AbortSignal): Promise<Blob> {
    if (!isLevelSessionAudioUrl(url)) {
      throw new Error('level_sessions_audio_path_rejected')
    }

    let response: Response

    try {
      response = await this.#fetch(url, {
        credentials: 'include',
        headers: { Accept: 'audio/*' },
        signal,
      })
    } catch {
      throw new Error('level_sessions_audio_unavailable')
    }

    if (!response.ok) {
      throw new Error(`level_sessions_audio_http_${response.status}`)
    }

    const contentType = response.headers
      .get('Content-Type')
      ?.split(';', 1)[0]
      ?.trim()
      .toLowerCase()

    if (contentType === undefined || !contentType.startsWith('audio/')) {
      throw new Error('level_sessions_audio_type_rejected')
    }

    return response.blob()
  }

  async #mutation<T>(path: string, init: RequestInit): Promise<T> {
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
    const response = await this.#fetch(`${this.#baseUrl}${path}`, {
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
    if (!response.ok) {
      throw new Error(`level_sessions_http_${response.status}`)
    }
    return (await response.json()) as T
  }

  async #csrfHeaders(
    signal: AbortSignal | null | undefined,
  ): Promise<Record<string, string>> {
    const response = await this.#fetch(this.#csrfCookieUrl(), {
      credentials: 'include',
      headers: { Accept: 'application/json' },
      signal: signal ?? undefined,
    })
    const token = readCookieValue(this.#readCookie(), 'XSRF-TOKEN')
    if (!response.ok || token === undefined) {
      throw new Error('level_sessions_csrf_unavailable')
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

function readCookieValue(cookie: string, name: string): string | undefined {
  const match = cookie
    .split(';')
    .map((part) => part.trim())
    .find((part) => part.startsWith(`${name}=`))
  if (match === undefined) {
    return undefined
  }
  return decodeURIComponent(match.slice(name.length + 1))
}

function isLevelSessionAudioUrl(url: string): boolean {
  if (url.length === 0 || url.length > 200) {
    return false
  }

  try {
    if (/^https?:\/\//iu.test(url)) {
      const parsed = new URL(url)

      return (
        parsed.search === '' &&
        parsed.hash === '' &&
        LEVEL_SESSION_AUDIO_PATH.test(parsed.pathname)
      )
    }

    return LEVEL_SESSION_AUDIO_PATH.test(url)
  } catch {
    return false
  }
}

export const defaultLevelSessionsApi = new WebLevelSessionsApi({
  authenticatedPrivateMedia:
    defaultAuthenticationRuntime.privateMediaRequiresFetch,
  baseUrl: defaultAuthenticationRuntime.apiBaseUrl,
  csrfProtection: defaultAuthenticationRuntime.csrfProtection,
  fetch: defaultAuthenticationRuntime.apiFetch,
})
