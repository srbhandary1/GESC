import type { ConversationTurnEvent } from '@global-english/contracts'
import {
  ConversationJourneyUnavailableError,
  type ConversationJourneyApi,
  type CreateFriendTurnInput,
  type FriendSession,
  type FriendTurn,
  type StartFriendSessionInput,
} from './ConversationJourneyApi'

export interface WebConversationJourneyApiOptions {
  readonly baseUrl?: string
  readonly fetch?: typeof fetch
  readonly readCookie?: () => string
}

export class WebConversationJourneyApi
  implements ConversationJourneyApi
{
  readonly #baseUrl: string
  readonly #fetch: typeof fetch
  readonly #readCookie: () => string

  constructor(options: WebConversationJourneyApiOptions = {}) {
    this.#baseUrl = options.baseUrl ?? '/api/v1'
    this.#fetch = options.fetch ?? globalThis.fetch.bind(globalThis)
    this.#readCookie =
      options.readCookie ??
      (() => (typeof document === 'undefined' ? '' : document.cookie))
  }

  startSession(
    input: StartFriendSessionInput,
    commandId: string,
    signal?: AbortSignal,
  ): Promise<FriendSession> {
    return this.#mutation<FriendSession>('/conversation-sessions', {
      body: JSON.stringify(input),
      headers: { 'Idempotency-Key': commandId },
      method: 'POST',
      signal,
    })
  }

  createTurn(
    sessionId: string,
    input: CreateFriendTurnInput,
    commandId: string,
    signal?: AbortSignal,
  ): Promise<FriendTurn> {
    return this.#mutation<FriendTurn>(
      `/conversation-sessions/${encodeURIComponent(sessionId)}/turns`,
      {
        body: JSON.stringify(input),
        headers: { 'Idempotency-Key': commandId },
        method: 'POST',
        signal,
      },
    )
  }

  async events(
    eventsUrl: string,
    signal?: AbortSignal,
  ): Promise<readonly ConversationTurnEvent[]> {
    let response: Response

    try {
      response = await this.#fetch(this.#eventUrl(eventsUrl), {
        credentials: 'include',
        headers: { Accept: 'text/event-stream' },
        signal,
      })
    } catch {
      throw new ConversationJourneyUnavailableError()
    }

    if (!response.ok) {
      throw new ConversationJourneyUnavailableError(response.status)
    }

    return parseEventStream(await response.text())
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
      throw new ConversationJourneyUnavailableError()
    }

    if (!response.ok) {
      throw new ConversationJourneyUnavailableError(response.status)
    }

    try {
      return (await response.json()) as T
    } catch {
      throw new ConversationJourneyUnavailableError()
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
      throw new ConversationJourneyUnavailableError()
    }

    const token = readCookieValue(this.#readCookie(), 'XSRF-TOKEN')

    if (!response.ok || token === undefined) {
      throw new ConversationJourneyUnavailableError()
    }

    return { 'X-XSRF-TOKEN': token }
  }

  #csrfCookieUrl(): string {
    if (/^https?:\/\//u.test(this.#baseUrl)) {
      return new URL('/sanctum/csrf-cookie', this.#baseUrl).toString()
    }

    return '/sanctum/csrf-cookie'
  }

  #eventUrl(eventsUrl: string): string {
    if (/^https?:\/\//u.test(eventsUrl)) {
      throw new ConversationJourneyUnavailableError()
    }

    if (eventsUrl.startsWith('/api/v1/')) {
      return eventsUrl
    }

    return `${this.#baseUrl}/${eventsUrl.replace(/^\/+/u, '')}`
  }
}

export const defaultConversationJourneyApi =
  new WebConversationJourneyApi()

function parseEventStream(
  stream: string,
): readonly ConversationTurnEvent[] {
  const events: ConversationTurnEvent[] = []

  for (const frame of stream.split(/\r?\n\r?\n/u)) {
    const data = frame
      .split(/\r?\n/u)
      .find((line) => line.startsWith('data: '))
      ?.slice(6)

    if (data === undefined) {
      continue
    }

    let value: unknown

    try {
      value = JSON.parse(data) as unknown
    } catch {
      throw new ConversationJourneyUnavailableError()
    }

    if (!isConversationTurnEvent(value)) {
      throw new ConversationJourneyUnavailableError()
    }

    events.push(value)
  }

  return events
}

function isConversationTurnEvent(
  value: unknown,
): value is ConversationTurnEvent {
  if (typeof value !== 'object' || value === null) {
    return false
  }

  const event = value as Record<string, unknown>

  return (
    typeof event.sequence === 'number' &&
    typeof event.state === 'string' &&
    typeof event.event_code === 'string' &&
    typeof event.recorded_at === 'string' &&
    (event.provider_stage === null ||
      typeof event.provider_stage === 'string') &&
    (event.provider_attempt === null ||
      typeof event.provider_attempt === 'number')
  )
}

function readCookieValue(
  cookieHeader: string,
  name: string,
): string | undefined {
  const encodedName = `${encodeURIComponent(name)}=`
  const pair = cookieHeader
    .split(';')
    .map((entry) => entry.trim())
    .find((entry) => entry.startsWith(encodedName))

  if (pair === undefined) {
    return undefined
  }

  try {
    return decodeURIComponent(pair.slice(encodedName.length))
  } catch {
    return undefined
  }
}
