import type { ConversationSpeechResult, ConversationTurnEvent, SynthesizeConversationSpeechRequest } from '@global-english/contracts'
import type {
  CreateLearnerAiMemoryFactRequest,
  GrantVoiceConsentRequest,
  LearnerAiMemoryFact,
  LearnerAiMemoryListing,
  ReviewVoiceTranscriptRequest,
  SupportedLocale,
  UpdateLearnerAiMemoryFactRequest,
  VoiceConsentEvent,
  VoiceConsentListing,
  VoiceConsentPurpose,
  VoiceDeletion,
  VoiceRecording,
  WithdrawVoiceConsentRequest,
} from '@global-english/contracts'
import {
  ConversationJourneyUnavailableError,
  type AdvanceRoleplayStageInput,
  type ConversationJourneyApi,
  type CreateFriendTurnInput,
  type FriendSession,
  type FriendTurn,
  type StartFriendSessionInput,
} from './ConversationJourneyApi'
import {
  defaultAuthenticationRuntime,
  type ApiCsrfProtection,
} from '../auth/defaultAuthentication'

export interface WebConversationJourneyApiOptions {
  readonly baseUrl?: string
  readonly csrfProtection?: ApiCsrfProtection
  readonly fetch?: typeof fetch
  readonly readCookie?: () => string
}

export class WebConversationJourneyApi
  implements ConversationJourneyApi
{
  readonly #baseUrl: string
  readonly #csrfProtection: ApiCsrfProtection
  readonly #fetch: typeof fetch
  readonly #readCookie: () => string

  constructor(options: WebConversationJourneyApiOptions = {}) {
    this.#baseUrl = options.baseUrl ?? '/api/v1'
    this.#csrfProtection =
      options.csrfProtection ?? 'sanctum-cookie'
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

  advanceStage(
    sessionId: string,
    input: AdvanceRoleplayStageInput,
    commandId: string,
    signal?: AbortSignal,
  ): Promise<FriendSession> {
    return this.#mutation<FriendSession>(
      `/conversation-sessions/${encodeURIComponent(sessionId)}/stages`,
      {
        body: JSON.stringify(input),
        headers: { 'Idempotency-Key': commandId },
        method: 'POST',
        signal,
      },
    )
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

  synthesizeSpeech(
    sessionId: string,
    input: SynthesizeConversationSpeechRequest,
    commandId: string,
    signal?: AbortSignal,
  ): Promise<ConversationSpeechResult> {
    return this.#mutation<ConversationSpeechResult>(
      `/conversation-sessions/${encodeURIComponent(sessionId)}/speech`,
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

  voiceConsents(
    locale: SupportedLocale,
    signal?: AbortSignal,
  ): Promise<VoiceConsentListing> {
    return this.#json<VoiceConsentListing>(
      `/voice-consents?locale=${encodeURIComponent(locale)}`,
      { method: 'GET', signal },
    )
  }

  grantVoiceConsent(
    input: GrantVoiceConsentRequest,
    commandId: string,
    signal?: AbortSignal,
  ): Promise<VoiceConsentEvent> {
    return this.#mutation<VoiceConsentEvent>('/voice-consents', {
      body: JSON.stringify(input),
      headers: { 'Idempotency-Key': commandId },
      method: 'POST',
      signal,
    })
  }

  withdrawVoiceConsent(
    purpose: VoiceConsentPurpose,
    input: WithdrawVoiceConsentRequest,
    commandId: string,
    signal?: AbortSignal,
  ): Promise<VoiceConsentEvent> {
    return this.#mutation<VoiceConsentEvent>(
      `/voice-consents/${encodeURIComponent(purpose)}`,
      {
        body: JSON.stringify(input),
        headers: { 'Idempotency-Key': commandId },
        method: 'DELETE',
        signal,
      },
    )
  }

  uploadVoiceRecording(
    sessionId: string,
    audio: Blob,
    commandId: string,
    signal?: AbortSignal,
  ): Promise<VoiceRecording> {
    const file =
      audio instanceof File && audio.name.trim() !== ''
        ? audio
        : new File([audio], 'voice-practice.wav', {
            type: audio.type.trim() === '' ? 'audio/wav' : audio.type,
          })
    const body = new FormData()
    body.append('audio', file, file.name)

    return this.#mutation<VoiceRecording>(
      `/conversation-sessions/${encodeURIComponent(sessionId)}/recordings`,
      {
        body,
        headers: { 'Idempotency-Key': commandId },
        method: 'POST',
        signal,
      },
    )
  }

  reviewVoiceTranscript(
    recordingId: string,
    input: ReviewVoiceTranscriptRequest,
    commandId: string,
    signal?: AbortSignal,
  ): Promise<VoiceRecording> {
    return this.#mutation<VoiceRecording>(
      `/voice-recordings/${encodeURIComponent(recordingId)}/transcript-review`,
      {
        body: JSON.stringify(input),
        headers: { 'Idempotency-Key': commandId },
        method: 'POST',
        signal,
      },
    )
  }

  deleteVoiceRecording(
    recordingId: string,
    signal?: AbortSignal,
  ): Promise<VoiceDeletion> {
    return this.#mutation<VoiceDeletion>(
      `/voice-recordings/${encodeURIComponent(recordingId)}`,
      { method: 'DELETE', signal },
    )
  }

  listAiMemory(signal?: AbortSignal): Promise<LearnerAiMemoryListing> {
    return this.#json<LearnerAiMemoryListing>('/me/ai-memory', {
      method: 'GET',
      signal,
    })
  }

  createAiMemoryFact(
    input: CreateLearnerAiMemoryFactRequest,
    commandId: string,
    signal?: AbortSignal,
  ): Promise<LearnerAiMemoryFact> {
    return this.#mutation<LearnerAiMemoryFact>('/me/ai-memory', {
      body: JSON.stringify(input),
      headers: { 'Idempotency-Key': commandId },
      method: 'POST',
      signal,
    })
  }

  updateAiMemoryFact(
    factId: string,
    input: UpdateLearnerAiMemoryFactRequest,
    commandId: string,
    signal?: AbortSignal,
  ): Promise<LearnerAiMemoryFact> {
    return this.#mutation<LearnerAiMemoryFact>(
      `/me/ai-memory/${encodeURIComponent(factId)}`,
      {
        body: JSON.stringify(input),
        headers: { 'Idempotency-Key': commandId },
        method: 'PATCH',
        signal,
      },
    )
  }

  deleteAiMemoryFact(
    factId: string,
    commandId: string,
    signal?: AbortSignal,
  ): Promise<LearnerAiMemoryFact> {
    return this.#mutation<LearnerAiMemoryFact>(
      `/me/ai-memory/${encodeURIComponent(factId)}`,
      {
        headers: { 'Idempotency-Key': commandId },
        method: 'DELETE',
        signal,
      },
    )
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
            || init.body instanceof FormData
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
      if (/^https?:\/\//u.test(this.#baseUrl)) {
        return `${new URL(this.#baseUrl).origin}${eventsUrl}`
      }

      return eventsUrl
    }

    return `${this.#baseUrl}/${eventsUrl.replace(/^\/+/u, '')}`
  }
}

export const defaultConversationJourneyApi =
  new WebConversationJourneyApi({
    baseUrl: defaultAuthenticationRuntime.apiBaseUrl,
    csrfProtection: defaultAuthenticationRuntime.csrfProtection,
    fetch: defaultAuthenticationRuntime.apiFetch,
  })

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
