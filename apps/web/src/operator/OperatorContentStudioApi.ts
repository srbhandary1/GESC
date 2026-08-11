import type {
  AbandonOperatorContentWorkspaceRequest,
  ActivateOperatorContentPublicationRequest,
  AppendOperatorEnglishContentRequest,
  AppendOperatorKannadaContentRequest,
  CancelOperatorContentPublicationScheduleRequest,
  CorrectOperatorContentProgramDescriptorRequest,
  CreateOperatorContentDocumentRequest,
  CreateOperatorContentProgramRequest,
  CreateOperatorContentSuccessorRequest,
  OperatorContentGovernanceResult,
  OperatorContentHistoryListing,
  OperatorContentPreview,
  OperatorContentPreviewResponse,
  OperatorContentPublicationLifecycleResult,
  OperatorContentReviewQueueListing,
  OperatorContentWorkspaceCommandResult,
  OperatorContentWorkspaceDetail,
  OperatorContentWorkspaceListing,
  OperatorContentWorkspaceResponse,
  OperatorPublicationListing,
  OperatorPublicationResult,
  PublishOperatorCurriculumRequest,
  RecordEnglishContentReviewRequest,
  RecordKannadaContentReviewRequest,
  RecordOperatorStructureContentReviewRequest,
  RetireOperatorContentPublicationRequest,
  RollbackOperatorCurriculumRequest,
  SaveOperatorContentGraphRequest,
  ScheduleOperatorContentPublicationRequest,
  VersionedOperatorContentWorkspaceRequest,
  WithdrawOperatorContentReviewRequest,
} from '@global-english/contracts'
import {
  defaultAuthenticationRuntime,
  type ApiCsrfProtection,
} from '../auth/defaultAuthentication'

export type OperatorContentQueue =
  | 'structure'
  | 'english'
  | 'kannada'
  | 'readiness'

export interface OperatorContentStudioApi {
  listWorkspaces(
    cursor?: string,
    signal?: AbortSignal,
  ): Promise<OperatorContentWorkspaceListing>
  getWorkspace(
    curriculumVersionId: string,
    signal?: AbortSignal,
  ): Promise<OperatorContentWorkspaceDetail>
  previewDocument(
    curriculumVersionId: string,
    documentKey: string,
    signal?: AbortSignal,
  ): Promise<OperatorContentPreview>
  listHistory(
    curriculumVersionId: string,
    cursor?: string,
    signal?: AbortSignal,
  ): Promise<OperatorContentHistoryListing>
  listReviewQueue(
    queue: OperatorContentQueue,
    cursor?: string,
    signal?: AbortSignal,
  ): Promise<OperatorContentReviewQueueListing>
  listPublications(
    cursor?: string,
    signal?: AbortSignal,
  ): Promise<OperatorPublicationListing>
  createProgram(
    body: CreateOperatorContentProgramRequest,
    idempotencyKey: string,
  ): Promise<OperatorContentWorkspaceCommandResult>
  correctDescriptor(
    programId: string,
    body: CorrectOperatorContentProgramDescriptorRequest,
    idempotencyKey: string,
  ): Promise<OperatorContentWorkspaceCommandResult>
  createSuccessor(
    curriculumVersionId: string,
    body: CreateOperatorContentSuccessorRequest,
    idempotencyKey: string,
  ): Promise<OperatorContentWorkspaceCommandResult>
  saveGraph(
    curriculumVersionId: string,
    body: SaveOperatorContentGraphRequest,
    idempotencyKey: string,
  ): Promise<OperatorContentWorkspaceCommandResult>
  submitStructure(
    curriculumVersionId: string,
    body: VersionedOperatorContentWorkspaceRequest,
    idempotencyKey: string,
  ): Promise<OperatorContentWorkspaceCommandResult>
  abandonWorkspace(
    curriculumVersionId: string,
    body: AbandonOperatorContentWorkspaceRequest,
    idempotencyKey: string,
  ): Promise<OperatorContentWorkspaceCommandResult>
  createDocument(
    curriculumVersionId: string,
    body: CreateOperatorContentDocumentRequest,
    idempotencyKey: string,
  ): Promise<OperatorContentWorkspaceCommandResult>
  appendEnglish(
    documentId: string,
    body: AppendOperatorEnglishContentRequest,
    idempotencyKey: string,
  ): Promise<OperatorContentWorkspaceCommandResult>
  appendKannada(
    revisionId: string,
    body: AppendOperatorKannadaContentRequest,
    idempotencyKey: string,
  ): Promise<OperatorContentWorkspaceCommandResult>
  reviewStructure(
    body: RecordOperatorStructureContentReviewRequest,
    idempotencyKey: string,
  ): Promise<OperatorContentGovernanceResult>
  reviewEnglish(
    body: RecordEnglishContentReviewRequest,
    idempotencyKey: string,
  ): Promise<Readonly<{ content_review_id: string }>>
  reviewKannada(
    body: RecordKannadaContentReviewRequest,
    idempotencyKey: string,
  ): Promise<Readonly<{ content_review_id: string }>>
  withdrawReview(
    reviewId: string,
    body: WithdrawOperatorContentReviewRequest,
    idempotencyKey: string,
  ): Promise<OperatorContentGovernanceResult>
  publishNow(
    body: PublishOperatorCurriculumRequest,
    idempotencyKey: string,
  ): Promise<OperatorPublicationResult>
  schedulePublication(
    body: ScheduleOperatorContentPublicationRequest,
    idempotencyKey: string,
  ): Promise<OperatorContentPublicationLifecycleResult>
  activatePublication(
    body: ActivateOperatorContentPublicationRequest,
    idempotencyKey: string,
  ): Promise<OperatorContentPublicationLifecycleResult>
  cancelSchedule(
    body: CancelOperatorContentPublicationScheduleRequest,
    idempotencyKey: string,
  ): Promise<OperatorContentPublicationLifecycleResult>
  retirePublication(
    body: RetireOperatorContentPublicationRequest,
    idempotencyKey: string,
  ): Promise<OperatorContentPublicationLifecycleResult>
  rollbackPublication(
    body: RollbackOperatorCurriculumRequest,
    idempotencyKey: string,
  ): Promise<OperatorPublicationResult>
}

export type OperatorContentErrorKind =
  | 'concurrent_change'
  | 'forbidden'
  | 'fresh_mfa'
  | 'head_conflict'
  | 'idempotency_conflict'
  | 'not_found'
  | 'stale_version'
  | 'state_conflict'
  | 'unavailable'
  | 'validation'

export class OperatorContentApiError extends Error {
  readonly kind: OperatorContentErrorKind

  constructor(kind: OperatorContentErrorKind) {
    super('The governed content request could not be completed.')
    this.name = 'OperatorContentApiError'
    this.kind = kind
  }
}

export interface WebOperatorContentStudioApiOptions {
  readonly baseUrl?: string
  readonly csrfProtection?: ApiCsrfProtection
  readonly fetch?: typeof fetch
  readonly readCookie?: () => string
}

export class WebOperatorContentStudioApi implements OperatorContentStudioApi {
  readonly #baseUrl: string
  readonly #csrfProtection: ApiCsrfProtection
  readonly #fetch: typeof fetch
  readonly #readCookie: () => string

  constructor(options: WebOperatorContentStudioApiOptions = {}) {
    this.#baseUrl = options.baseUrl ?? '/api/v1'
    this.#csrfProtection = options.csrfProtection ?? 'sanctum-cookie'
    this.#fetch = options.fetch ?? globalThis.fetch.bind(globalThis)
    this.#readCookie =
      options.readCookie ??
      (() => (typeof document === 'undefined' ? '' : document.cookie))
  }

  listWorkspaces(cursor?: string, signal?: AbortSignal) {
    return this.#get<OperatorContentWorkspaceListing>(
      this.#pagePath('/operator/content/workspaces', cursor),
      signal,
    )
  }

  async getWorkspace(curriculumVersionId: string, signal?: AbortSignal) {
    return (
      await this.#get<OperatorContentWorkspaceResponse>(
        `/operator/content/workspaces/${this.#segment(curriculumVersionId)}`,
        signal,
      )
    ).data
  }

  async previewDocument(
    curriculumVersionId: string,
    documentKey: string,
    signal?: AbortSignal,
  ) {
    const parameters = new URLSearchParams({ document_key: documentKey })
    return (
      await this.#get<OperatorContentPreviewResponse>(
        `/operator/content/workspaces/${this.#segment(curriculumVersionId)}/preview?${parameters.toString()}`,
        signal,
      )
    ).data
  }

  listHistory(
    curriculumVersionId: string,
    cursor?: string,
    signal?: AbortSignal,
  ) {
    return this.#get<OperatorContentHistoryListing>(
      this.#pagePath(
        `/operator/content/workspaces/${this.#segment(curriculumVersionId)}/history`,
        cursor,
      ),
      signal,
    )
  }

  listReviewQueue(
    queue: OperatorContentQueue,
    cursor?: string,
    signal?: AbortSignal,
  ) {
    const parameters = new URLSearchParams({ limit: '25', queue })
    if (cursor !== undefined) parameters.set('cursor', cursor)
    return this.#get<OperatorContentReviewQueueListing>(
      `/operator/content/review-queues?${parameters.toString()}`,
      signal,
    )
  }

  listPublications(cursor?: string, signal?: AbortSignal) {
    return this.#get<OperatorPublicationListing>(
      this.#pagePath('/operator/content/publications', cursor),
      signal,
    )
  }

  createProgram(
    body: CreateOperatorContentProgramRequest,
    idempotencyKey: string,
  ) {
    return this.#post<OperatorContentWorkspaceCommandResult>(
      '/operator/content/programs',
      body,
      idempotencyKey,
    )
  }

  correctDescriptor(
    programId: string,
    body: CorrectOperatorContentProgramDescriptorRequest,
    idempotencyKey: string,
  ) {
    return this.#post<OperatorContentWorkspaceCommandResult>(
      `/operator/content/programs/${this.#segment(programId)}/descriptor-corrections`,
      body,
      idempotencyKey,
    )
  }

  createSuccessor(
    curriculumVersionId: string,
    body: CreateOperatorContentSuccessorRequest,
    idempotencyKey: string,
  ) {
    return this.#post<OperatorContentWorkspaceCommandResult>(
      `/operator/content/versions/${this.#segment(curriculumVersionId)}/successors`,
      body,
      idempotencyKey,
    )
  }

  saveGraph(
    curriculumVersionId: string,
    body: SaveOperatorContentGraphRequest,
    idempotencyKey: string,
  ) {
    return this.#post<OperatorContentWorkspaceCommandResult>(
      `/operator/content/workspaces/${this.#segment(curriculumVersionId)}/graph-revisions`,
      body,
      idempotencyKey,
    )
  }

  submitStructure(
    curriculumVersionId: string,
    body: VersionedOperatorContentWorkspaceRequest,
    idempotencyKey: string,
  ) {
    return this.#post<OperatorContentWorkspaceCommandResult>(
      `/operator/content/workspaces/${this.#segment(curriculumVersionId)}/review-submissions`,
      body,
      idempotencyKey,
    )
  }

  abandonWorkspace(
    curriculumVersionId: string,
    body: AbandonOperatorContentWorkspaceRequest,
    idempotencyKey: string,
  ) {
    return this.#post<OperatorContentWorkspaceCommandResult>(
      `/operator/content/workspaces/${this.#segment(curriculumVersionId)}/abandonments`,
      body,
      idempotencyKey,
    )
  }

  createDocument(
    curriculumVersionId: string,
    body: CreateOperatorContentDocumentRequest,
    idempotencyKey: string,
  ) {
    return this.#post<OperatorContentWorkspaceCommandResult>(
      `/operator/content/workspaces/${this.#segment(curriculumVersionId)}/documents`,
      body,
      idempotencyKey,
    )
  }

  appendEnglish(
    documentId: string,
    body: AppendOperatorEnglishContentRequest,
    idempotencyKey: string,
  ) {
    return this.#post<OperatorContentWorkspaceCommandResult>(
      `/operator/content/documents/${this.#segment(documentId)}/english-revisions`,
      body,
      idempotencyKey,
    )
  }

  appendKannada(
    revisionId: string,
    body: AppendOperatorKannadaContentRequest,
    idempotencyKey: string,
  ) {
    return this.#post<OperatorContentWorkspaceCommandResult>(
      `/operator/content/revisions/${this.#segment(revisionId)}/kannada-variants`,
      body,
      idempotencyKey,
    )
  }

  reviewStructure(
    body: RecordOperatorStructureContentReviewRequest,
    idempotencyKey: string,
  ) {
    return this.#post<OperatorContentGovernanceResult>(
      '/operator/content/reviews/structure',
      body,
      idempotencyKey,
    )
  }

  reviewEnglish(
    body: RecordEnglishContentReviewRequest,
    idempotencyKey: string,
  ) {
    return this.#post<Readonly<{ content_review_id: string }>>(
      '/operator/content/reviews/english',
      body,
      idempotencyKey,
    )
  }

  reviewKannada(
    body: RecordKannadaContentReviewRequest,
    idempotencyKey: string,
  ) {
    return this.#post<Readonly<{ content_review_id: string }>>(
      '/operator/content/reviews/kannada',
      body,
      idempotencyKey,
    )
  }

  withdrawReview(
    reviewId: string,
    body: WithdrawOperatorContentReviewRequest,
    idempotencyKey: string,
  ) {
    return this.#post<OperatorContentGovernanceResult>(
      `/operator/content/reviews/${this.#segment(reviewId)}/withdrawals`,
      body,
      idempotencyKey,
    )
  }

  publishNow(body: PublishOperatorCurriculumRequest, idempotencyKey: string) {
    return this.#post<OperatorPublicationResult>(
      '/operator/content/publications',
      body,
      idempotencyKey,
    )
  }

  schedulePublication(
    body: ScheduleOperatorContentPublicationRequest,
    idempotencyKey: string,
  ) {
    return this.#post<OperatorContentPublicationLifecycleResult>(
      '/operator/content/publication-schedules',
      body,
      idempotencyKey,
    )
  }

  activatePublication(
    body: ActivateOperatorContentPublicationRequest,
    idempotencyKey: string,
  ) {
    return this.#post<OperatorContentPublicationLifecycleResult>(
      '/operator/content/publication-activations',
      body,
      idempotencyKey,
    )
  }

  cancelSchedule(
    body: CancelOperatorContentPublicationScheduleRequest,
    idempotencyKey: string,
  ) {
    return this.#post<OperatorContentPublicationLifecycleResult>(
      '/operator/content/publication-schedule-cancellations',
      body,
      idempotencyKey,
    )
  }

  retirePublication(
    body: RetireOperatorContentPublicationRequest,
    idempotencyKey: string,
  ) {
    return this.#post<OperatorContentPublicationLifecycleResult>(
      '/operator/content/publication-retirements',
      body,
      idempotencyKey,
    )
  }

  rollbackPublication(
    body: RollbackOperatorCurriculumRequest,
    idempotencyKey: string,
  ) {
    return this.#post<OperatorPublicationResult>(
      '/operator/content/rollbacks',
      body,
      idempotencyKey,
    )
  }

  #pagePath(path: string, cursor?: string): string {
    const parameters = new URLSearchParams({ limit: '25' })
    if (cursor !== undefined) parameters.set('cursor', cursor)
    return `${path}?${parameters.toString()}`
  }

  #segment(value: string): string {
    return encodeURIComponent(value)
  }

  async #get<T>(path: string, signal?: AbortSignal): Promise<T> {
    const response = await this.#fetch(`${this.#baseUrl}${path}`, {
      credentials: 'include',
      headers: this.#headers(),
      method: 'GET',
      signal,
    })
    return this.#response<T>(response)
  }

  async #post<T>(
    path: string,
    body: Readonly<object>,
    idempotencyKey: string,
  ): Promise<T> {
    const response = await this.#fetch(`${this.#baseUrl}${path}`, {
      body: JSON.stringify(body),
      credentials: 'include',
      headers: {
        ...this.#headers(),
        'Content-Type': 'application/json',
        'Idempotency-Key': idempotencyKey,
      },
      method: 'POST',
    })
    return this.#response<T>(response)
  }

  #headers(): Readonly<Record<string, string>> {
    return {
      Accept: 'application/json',
      ...(this.#csrfProtection === 'sanctum-cookie'
        ? { 'X-XSRF-TOKEN': this.#xsrfToken() }
        : {}),
    }
  }

  async #response<T>(response: Response): Promise<T> {
    if (response.ok) {
      try {
        return (await response.json()) as T
      } catch {
        throw new OperatorContentApiError('unavailable')
      }
    }

    let messageKey: string | null = null
    try {
      const problem = (await response.json()) as { message_key?: unknown }
      messageKey =
        typeof problem.message_key === 'string' ? problem.message_key : null
    } catch {
      // Never reflect malformed problem bodies or governed content.
    }

    const mapped = {
      'problem.content_workspace.concurrent_change': 'concurrent_change',
      'problem.content_workspace.head_conflict': 'head_conflict',
      'problem.content_workspace.idempotency_conflict': 'idempotency_conflict',
      'problem.content_workspace.stale_version': 'stale_version',
      'problem.content_workspace.state_conflict': 'state_conflict',
    } as const satisfies Readonly<Record<string, OperatorContentErrorKind>>

    if (response.status === 409 && messageKey !== null && messageKey in mapped) {
      throw new OperatorContentApiError(mapped[messageKey as keyof typeof mapped])
    }
    if (
      response.status === 403
      && (messageKey === 'problem.fresh_mfa_required'
        || messageKey === 'problem.fresh_password_confirmation_required')
    ) {
      throw new OperatorContentApiError('fresh_mfa')
    }
    if (response.status === 403) throw new OperatorContentApiError('forbidden')
    if (response.status === 404) throw new OperatorContentApiError('not_found')
    if (response.status === 422) throw new OperatorContentApiError('validation')

    throw new OperatorContentApiError('unavailable')
  }

  #xsrfToken(): string {
    const match = /(?:^|;\s*)XSRF-TOKEN=([^;]+)/.exec(this.#readCookie())
    return match === null ? '' : decodeURIComponent(match[1] ?? '')
  }
}

export const defaultOperatorContentStudioApi = new WebOperatorContentStudioApi({
  baseUrl: defaultAuthenticationRuntime.apiBaseUrl,
  csrfProtection: defaultAuthenticationRuntime.csrfProtection,
  fetch: defaultAuthenticationRuntime.apiFetch,
})
