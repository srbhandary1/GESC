import {
  defaultAuthenticationRuntime,
  type ApiCsrfProtection,
} from '../auth/defaultAuthentication'

export type OperatorLearnerPanelKey =
  | 'learner_directory'
  | 'learner_enrollments'
  | 'learner_invitations'

export type OperatorLearnerAction =
  | 'learner.goal_correct'
  | 'learner.suspend'
  | 'learner.reactivate'
  | 'learner_enrollment.create'
  | 'learner_enrollment.change_cohort'
  | 'learner_enrollment.activate_curriculum'
  | 'learner_enrollment.withdraw'
  | 'learner_invitation.issue'
  | 'learner_invitation.revoke'

export interface OperatorLearnerChoice {
  readonly id: string
  readonly key: string
  readonly name: string
  readonly version: number
}

export interface OperatorLearnerProfile {
  readonly id: string
  readonly adult_confirmed: boolean
  readonly onboarding_completed: boolean
  readonly current_goal_key: OperatorLearnerGoalKey | null
  readonly current_goal_version: number | null
}

export type OperatorLearnerGoalKey =
  | 'everyday-conversation'
  | 'workplace-confidence'
  | 'travel-confidence'

export interface OperatorLearner {
  readonly membership_id: string
  readonly display_name: string | null
  readonly status: 'active' | 'suspended' | 'ended'
  readonly joined_at: string
  readonly ended_at: string | null
  readonly version: number
  readonly profile: OperatorLearnerProfile | null
  readonly attempt_count: number
  readonly latest_attempt_at: string | null
  readonly allowed_actions: readonly OperatorLearnerAction[]
}

export interface OperatorLearnerCollection {
  readonly data: readonly OperatorLearner[]
  readonly next_cursor: string | null
  readonly allowed_actions: readonly OperatorLearnerAction[]
  readonly choices: Readonly<{
    cohorts: readonly OperatorLearnerChoice[]
  }> | null
}

export interface OperatorLearnerCohort {
  readonly id: string
  readonly key: string | null
  readonly name: string | null
  readonly status: 'active' | 'archived' | null
  readonly version: number | null
}

export interface OperatorLearnerTrainer {
  readonly membership_id: string
  readonly display_name: string | null
}

export interface OperatorLearnerCurriculumPin {
  readonly owner_organization_id: string | null
  readonly version_id: string | null
  readonly publication_id: string
  readonly share_grant_id: string | null
}

export interface OperatorLearnerEnrollment {
  readonly id: string
  readonly learner_membership_id: string
  readonly status: 'pending_curriculum' | 'active' | 'completed' | 'withdrawn'
  readonly version: number
  readonly enrolled_at: string
  readonly activated_at: string | null
  readonly ended_at: string | null
  readonly cohort: OperatorLearnerCohort | null
  readonly curriculum_pin: OperatorLearnerCurriculumPin | null
  readonly trainers: readonly OperatorLearnerTrainer[] | null
  readonly allowed_actions: readonly OperatorLearnerAction[]
}

export interface OperatorLearnerEnrollmentCollection {
  readonly data: readonly OperatorLearnerEnrollment[]
  readonly next_cursor: string | null
  readonly allowed_actions: readonly OperatorLearnerAction[]
}

export interface OperatorLearnerInvitation {
  readonly id: string
  readonly status: 'accepted' | 'expired' | 'pending' | 'revoked'
  readonly version: number
  readonly role: (OperatorLearnerChoice & Readonly<{ status: string | null }>) | null
  readonly cohort: (OperatorLearnerChoice & Readonly<{ status: string | null }>) | null
  readonly expires_at: string
  readonly accepted_at: string | null
  readonly revoked_at: string | null
  readonly created_at: string
  readonly updated_at: string
  readonly allowed_actions: readonly OperatorLearnerAction[]
}

export interface OperatorLearnerInvitationCollection {
  readonly data: readonly OperatorLearnerInvitation[]
  readonly next_cursor: string | null
  readonly allowed_actions: readonly OperatorLearnerAction[]
  readonly choices: Readonly<{
    roles: readonly OperatorLearnerChoice[]
    cohorts: readonly OperatorLearnerChoice[]
  }>
}

export interface OperatorLearnerCommandResponse<T> {
  readonly data: T
  readonly replayed: boolean
}

export interface OperatorLearnerInvitationIssuance
  extends OperatorLearnerCommandResponse<OperatorLearnerInvitation> {
  readonly activation_code: string | null
}

export interface OperatorLearnerFilters {
  readonly status?: OperatorLearner['status']
  readonly goal_key?: OperatorLearnerGoalKey
  readonly cohort_id?: string
  readonly q?: string
}

export interface OperatorTuitionRequest {
  readonly id: string
  readonly membership_id: string
  readonly display_name: string | null
  readonly preferred_window: string
  readonly topic: string
  readonly notes: string | null
  readonly status: 'submitted' | 'acknowledged'
  readonly created_at: string | null
  readonly acknowledged_at: string | null
}

export interface OperatorTuitionRequestListing {
  readonly requests: readonly OperatorTuitionRequest[]
}

export interface OperatorLearnerApi {
  listLearners(
    filters?: OperatorLearnerFilters,
    cursor?: string,
    signal?: AbortSignal,
  ): Promise<OperatorLearnerCollection>
  getLearner(
    membershipId: string,
    signal?: AbortSignal,
  ): Promise<OperatorLearner>
  listEnrollments(
    membershipId: string,
    cursor?: string,
    signal?: AbortSignal,
  ): Promise<OperatorLearnerEnrollmentCollection>
  listInvitations(
    status?: OperatorLearnerInvitation['status'],
    cursor?: string,
    signal?: AbortSignal,
  ): Promise<OperatorLearnerInvitationCollection>
  listTuitionRequests(
    signal?: AbortSignal,
  ): Promise<OperatorTuitionRequestListing>
  acknowledgeTuitionRequest(
    requestId: string,
    idempotencyKey: string,
  ): Promise<OperatorTuitionRequest>
  correctGoal(
    membershipId: string,
    body: Readonly<{ goal_key: OperatorLearnerGoalKey; expected_version: number }>,
    idempotencyKey: string,
  ): Promise<OperatorLearnerCommandResponse<OperatorLearner>>
  createEnrollment(
    membershipId: string,
    body: Readonly<{
      expected_membership_version: number
      expected_version: 0
      cohort_id: string | null
      expected_cohort_version: number | null
    }>,
    idempotencyKey: string,
  ): Promise<OperatorLearnerCommandResponse<OperatorLearnerEnrollment>>
  changeEnrollmentCohort(
    enrollmentId: string,
    body: Readonly<{
      expected_version: number
      cohort_id: string | null
      expected_cohort_version: number | null
    }>,
    idempotencyKey: string,
  ): Promise<OperatorLearnerCommandResponse<OperatorLearnerEnrollment>>
  activateEnrollment(
    enrollmentId: string,
    body: Readonly<{
      publication_id: string
      share_grant_id: string | null
      expected_version: number
    }>,
    idempotencyKey: string,
  ): Promise<OperatorLearnerCommandResponse<OperatorLearnerEnrollment>>
  withdrawEnrollment(
    enrollmentId: string,
    body: Readonly<{ expected_version: number }>,
    idempotencyKey: string,
  ): Promise<OperatorLearnerCommandResponse<OperatorLearnerEnrollment>>
  suspendLearner(
    membershipId: string,
    body: Readonly<{ expected_version: number }>,
    idempotencyKey: string,
  ): Promise<OperatorLearnerCommandResponse<OperatorLearner>>
  reactivateLearner(
    membershipId: string,
    body: Readonly<{ expected_version: number }>,
    idempotencyKey: string,
  ): Promise<OperatorLearnerCommandResponse<OperatorLearner>>
  issueInvitation(
    body: Readonly<{
      role_id: string
      expected_role_version: number
      cohort_id: string | null
      expected_cohort_version: number | null
      expires_in_hours: 24 | 72 | 168
      expected_version: 0
    }>,
    idempotencyKey: string,
  ): Promise<OperatorLearnerInvitationIssuance>
  revokeInvitation(
    invitationId: string,
    body: Readonly<{ expected_version: number }>,
    idempotencyKey: string,
  ): Promise<OperatorLearnerCommandResponse<OperatorLearnerInvitation>>
}

export type OperatorLearnerErrorKind =
  | 'active_dependency'
  | 'forbidden'
  | 'idempotency_conflict'
  | 'stale_state'
  | 'unsafe_role'
  | 'unavailable'
  | 'validation'

export class OperatorLearnerApiError extends Error {
  readonly kind: OperatorLearnerErrorKind

  constructor(kind: OperatorLearnerErrorKind) {
    super('The learner administration request could not be completed.')
    this.name = 'OperatorLearnerApiError'
    this.kind = kind
  }
}

export interface WebOperatorLearnerApiOptions {
  readonly baseUrl?: string
  readonly csrfProtection?: ApiCsrfProtection
  readonly fetch?: typeof fetch
  readonly readCookie?: () => string
}

export class WebOperatorLearnerApi implements OperatorLearnerApi {
  readonly #baseUrl: string
  readonly #csrfProtection: ApiCsrfProtection
  readonly #fetch: typeof fetch
  readonly #readCookie: () => string

  constructor(options: WebOperatorLearnerApiOptions = {}) {
    this.#baseUrl = options.baseUrl ?? '/api/v1'
    this.#csrfProtection = options.csrfProtection ?? 'sanctum-cookie'
    this.#fetch = options.fetch ?? globalThis.fetch.bind(globalThis)
    this.#readCookie =
      options.readCookie ??
      (() => (typeof document === 'undefined' ? '' : document.cookie))
  }

  listLearners(
    filters: OperatorLearnerFilters = {},
    cursor?: string,
    signal?: AbortSignal,
  ) {
    const parameters = new URLSearchParams({ limit: '25' })

    for (const [key, value] of Object.entries(filters)) {
      if (value !== undefined && value !== '') {
        parameters.set(key, value)
      }
    }

    if (cursor !== undefined) {
      parameters.set('cursor', cursor)
    }

    return this.#get<OperatorLearnerCollection>(
      `/operator/learners?${parameters.toString()}`,
      signal,
    )
  }

  async getLearner(membershipId: string, signal?: AbortSignal) {
    return (
      await this.#get<Readonly<{ data: OperatorLearner }>>(
        `/operator/learners/${this.#segment(membershipId)}`,
        signal,
      )
    ).data
  }

  listEnrollments(
    membershipId: string,
    cursor?: string,
    signal?: AbortSignal,
  ) {
    return this.#get<OperatorLearnerEnrollmentCollection>(
      this.#collectionPath(
        `/operator/learners/${this.#segment(membershipId)}/enrollments`,
        cursor,
      ),
      signal,
    )
  }

  listInvitations(
    status?: OperatorLearnerInvitation['status'],
    cursor?: string,
    signal?: AbortSignal,
  ) {
    const parameters = new URLSearchParams({ limit: '25' })
    if (status !== undefined) parameters.set('status', status)
    if (cursor !== undefined) parameters.set('cursor', cursor)
    return this.#get<OperatorLearnerInvitationCollection>(
      `/operator/learner-invitations?${parameters.toString()}`,
      signal,
    )
  }

  listTuitionRequests(signal?: AbortSignal) {
    return this.#get<OperatorTuitionRequestListing>(
      '/operator/tuition-requests',
      signal,
    )
  }

  acknowledgeTuitionRequest(requestId: string, idempotencyKey: string) {
    return this.#post<OperatorTuitionRequest>(
      `/operator/tuition-requests/${this.#segment(requestId)}/acknowledgements`,
      {},
      idempotencyKey,
    )
  }

  correctGoal(
    membershipId: string,
    body: Readonly<{ goal_key: OperatorLearnerGoalKey; expected_version: number }>,
    idempotencyKey: string,
  ) {
    return this.#post<OperatorLearnerCommandResponse<OperatorLearner>>(
      `/operator/learners/${this.#segment(membershipId)}/goal-corrections`,
      body,
      idempotencyKey,
    )
  }

  createEnrollment(
    membershipId: string,
    body: Readonly<{
      expected_membership_version: number
      expected_version: 0
      cohort_id: string | null
      expected_cohort_version: number | null
    }>,
    idempotencyKey: string,
  ) {
    return this.#post<OperatorLearnerCommandResponse<OperatorLearnerEnrollment>>(
      `/operator/learners/${this.#segment(membershipId)}/enrollments`,
      body,
      idempotencyKey,
    )
  }

  changeEnrollmentCohort(
    enrollmentId: string,
    body: Readonly<{
      expected_version: number
      cohort_id: string | null
      expected_cohort_version: number | null
    }>,
    idempotencyKey: string,
  ) {
    return this.#enrollmentCommand(enrollmentId, 'cohort-changes', body, idempotencyKey)
  }

  activateEnrollment(
    enrollmentId: string,
    body: Readonly<{
      publication_id: string
      share_grant_id: string | null
      expected_version: number
    }>,
    idempotencyKey: string,
  ) {
    return this.#enrollmentCommand(
      enrollmentId,
      'curriculum-activations',
      body,
      idempotencyKey,
    )
  }

  withdrawEnrollment(
    enrollmentId: string,
    body: Readonly<{ expected_version: number }>,
    idempotencyKey: string,
  ) {
    return this.#enrollmentCommand(enrollmentId, 'withdrawals', body, idempotencyKey)
  }

  suspendLearner(
    membershipId: string,
    body: Readonly<{ expected_version: number }>,
    idempotencyKey: string,
  ) {
    return this.#learnerStateCommand(membershipId, 'suspensions', body, idempotencyKey)
  }

  reactivateLearner(
    membershipId: string,
    body: Readonly<{ expected_version: number }>,
    idempotencyKey: string,
  ) {
    return this.#learnerStateCommand(membershipId, 'reactivations', body, idempotencyKey)
  }

  issueInvitation(
    body: Readonly<{
      role_id: string
      expected_role_version: number
      cohort_id: string | null
      expected_cohort_version: number | null
      expires_in_hours: 24 | 72 | 168
      expected_version: 0
    }>,
    idempotencyKey: string,
  ) {
    return this.#post<OperatorLearnerInvitationIssuance>(
      '/operator/learner-invitations',
      body,
      idempotencyKey,
    )
  }

  revokeInvitation(
    invitationId: string,
    body: Readonly<{ expected_version: number }>,
    idempotencyKey: string,
  ) {
    return this.#post<OperatorLearnerCommandResponse<OperatorLearnerInvitation>>(
      `/operator/learner-invitations/${this.#segment(invitationId)}/revocations`,
      body,
      idempotencyKey,
    )
  }

  #learnerStateCommand(
    membershipId: string,
    state: 'reactivations' | 'suspensions',
    body: Readonly<{ expected_version: number }>,
    idempotencyKey: string,
  ) {
    return this.#post<OperatorLearnerCommandResponse<OperatorLearner>>(
      `/operator/learners/${this.#segment(membershipId)}/${state}`,
      body,
      idempotencyKey,
    )
  }

  #enrollmentCommand(
    enrollmentId: string,
    state: 'cohort-changes' | 'curriculum-activations' | 'withdrawals',
    body: Readonly<object>,
    idempotencyKey: string,
  ) {
    return this.#post<OperatorLearnerCommandResponse<OperatorLearnerEnrollment>>(
      `/operator/enrollments/${this.#segment(enrollmentId)}/${state}`,
      body,
      idempotencyKey,
    )
  }

  #collectionPath(path: string, cursor?: string): string {
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

  async #post<T>(path: string, body: Readonly<object>, idempotencyKey: string) {
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
        throw new OperatorLearnerApiError('unavailable')
      }
    }

    let messageKey: string | null = null
    try {
      const problem = (await response.json()) as { message_key?: unknown }
      messageKey = typeof problem.message_key === 'string' ? problem.message_key : null
    } catch {
      // Failure bodies are deliberately not reflected into the interface.
    }

    if (response.status === 403 && messageKey === 'problem.forbidden') {
      throw new OperatorLearnerApiError('forbidden')
    }
    if (response.status === 409) {
      const conflicts = {
        'problem.learner_administration.active_dependency': 'active_dependency',
        'problem.learner_administration.idempotency_conflict': 'idempotency_conflict',
        'problem.learner_administration.stale_state': 'stale_state',
        'problem.learner_administration.unsafe_role': 'unsafe_role',
      } as const satisfies Readonly<Record<string, OperatorLearnerErrorKind>>
      const kind = messageKey === null
        ? undefined
        : conflicts[messageKey as keyof typeof conflicts]
      if (kind !== undefined) throw new OperatorLearnerApiError(kind)
    }
    if (response.status === 422 && messageKey === 'problem.validation_failed') {
      throw new OperatorLearnerApiError('validation')
    }
    throw new OperatorLearnerApiError('unavailable')
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

export const defaultOperatorLearnerApi = new WebOperatorLearnerApi({
  baseUrl: defaultAuthenticationRuntime.apiBaseUrl,
  csrfProtection: defaultAuthenticationRuntime.csrfProtection,
  fetch: defaultAuthenticationRuntime.apiFetch,
})
