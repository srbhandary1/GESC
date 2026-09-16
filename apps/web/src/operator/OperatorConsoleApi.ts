import {
  defaultAuthenticationRuntime,
  type ApiCsrfProtection,
} from '../auth/defaultAuthentication'
import type { OperatorOrganizationPanelKey } from './OperatorOrganizationApi'
import type { OperatorLearnerPanelKey } from './OperatorLearnerApi'

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
  readonly publication_head_version?: number
  readonly lifecycle_status?:
    | 'scheduled'
    | 'cancelled'
    | 'active'
    | 'superseded'
    | 'retired'
    | 'suspended'
  readonly effective_at?: string
  readonly publication_suspended?: boolean
  readonly allowed_actions?: readonly string[]
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

export interface OperatorAuditEventPage {
  readonly items: readonly OperatorAuditEvent[]
  readonly next_cursor: string | null
}

export type OperatorNavigationKey =
  | 'overview'
  | 'learning'
  | 'operations'
  | 'safety'
  | 'people'
  | 'learners'
  | 'entitlements'

export type OperatorPanelKey =
  | 'pilot_analytics'
  | 'content_studio'
  | 'media_library'
  | 'review_queue'
  | 'publish_status'
  | 'support_diagnostics'
  | 'speech_economics'
  | 'audit'
  | OperatorOrganizationPanelKey
  | OperatorLearnerPanelKey
  | 'entitlement_plans'
  | 'entitlement_directory'

export interface OperatorOverview {
  readonly generated_at: string
  readonly organization: {
    readonly id: string
    readonly name: string
    readonly timezone: string
  }
  readonly navigation: readonly OperatorNavigationKey[]
  readonly panels: readonly OperatorPanelKey[]
  readonly search_available: boolean
}

export type OperatorSearchResultKind =
  | 'curriculum_version'
  | 'curriculum_publication'
  | 'support_diagnostic'
  | 'audit_event'

export type OperatorSearchSection = 'learning' | 'operations' | 'safety'

export interface OperatorSearchResult {
  readonly kind: OperatorSearchResultKind
  readonly id: string
  readonly section: OperatorSearchSection
  readonly primary_text: string
  readonly secondary_text: string
  readonly occurred_at: string | null
}

export interface OperatorSearchPage {
  readonly generated_at: string
  readonly items: readonly OperatorSearchResult[]
  readonly next_cursor: string | null
}

export interface OperatorPilotAnalyticsSummary {
  readonly generated_at: string
  readonly usage: {
    readonly learner_membership_count: number
    readonly conversation_session_count: number
    readonly conversation_sessions_by_status: Readonly<Record<string, number>>
    readonly formative_attempt_count: number
    readonly formative_attempts_by_status: Readonly<Record<string, number>>
  }
  readonly demonstrated_learning: {
    readonly mastery_state_count: number
    readonly mastery_by_knowledge_state: Readonly<Record<string, number>>
    readonly review_schedule_count: number
  }
  readonly learning_metrics: {
    readonly stage_completion_count: number
    readonly speaking_turn_count: number
    readonly quiz_retry_count: number
    readonly review_return_due_count: number
    readonly review_return_attempted_count: number
    readonly ai_fallback_count: number
    readonly latency_sample_count: number
    readonly session_cost_microunits_total: number
  }
  readonly distinction_note: 'usage_counts_are_not_demonstrated_learning'
}

export interface OperatorSupportDiagnosticItem {
  readonly kind:
    | 'stuck_in_progress'
    | 'provider_failed_recoverable'
    | 'open_deletion'
  readonly subject_type:
    | 'learner_attempt'
    | 'voice_recording'
    | 'voice_deletion_request'
  readonly subject_id: string
  readonly status: string
  readonly observed_at: string
}

export interface OperatorSupportDiagnostics {
  readonly generated_at: string
  readonly items: readonly OperatorSupportDiagnosticItem[]
}

export interface OperatorSpeechEconomicsFilters {
  readonly from?: string
  readonly to?: string
  readonly environment?: string
  readonly provider_stage?: 'stt' | 'model' | 'tts' | 'end_to_end'
  readonly provider_key?: string
  readonly locale?: string
  readonly conversation_mode?: string
  readonly result_status?:
    | 'succeeded'
    | 'failed'
    | 'cancelled'
    | 'partial'
    | 'started'
    | 'unavailable'
  readonly dispatch_kind?:
    | 'provider_dispatch'
    | 'cache'
    | 'replay'
    | 'local_execution'
    | 'unknown'
    | 'client_sample'
  readonly route_role?: 'primary' | 'fallback' | 'benchmark' | 'client'
}

export interface OperatorSpeechEconomicsTotals {
  readonly attempt_count: number
  readonly dispatch_count: number
  readonly success_count: number
  readonly failure_count: number
  readonly cancelled_count: number
  readonly partial_count: number
  readonly fallback_count: number
  readonly billable_unit_quantity: number
  readonly allocated_inr_microunits: number
}

export interface OperatorSpeechEconomicsRow {
  readonly usage_date: string
  readonly environment: string
  readonly aggregate_kind: string
  readonly provider_stage: string
  readonly provider_key: string
  readonly model_version: string
  readonly route_role: string
  readonly locale: string
  readonly conversation_mode: string
  readonly result_status: string
  readonly dispatch_kind: string
  readonly billable_unit_kind: string
  readonly reconciliation_status: string
  readonly small_cell_suppressed: boolean
  readonly attempt_count: number | null
  readonly dispatch_count: number | null
  readonly success_count: number | null
  readonly failure_count: number | null
  readonly cancelled_count: number | null
  readonly partial_count: number | null
  readonly fallback_count: number | null
  readonly billable_unit_quantity: number | null
  readonly allocated_inr_microunits: number | null
  readonly first_result_p50_ms: number | null
  readonly first_result_p95_ms: number | null
  readonly completion_p95_ms: number | null
  readonly first_audible_p95_ms: number | null
  readonly cost_per_successful_turn_inr_microunits: number | null
}

export interface OperatorSpeechEconomicsSummary {
  readonly generated_at: string
  readonly organization_id: string
  readonly presentation: 'allocated_usage'
  readonly empty: boolean
  readonly partial: boolean
  readonly stale_rate: boolean
  readonly unreconciled: boolean
  readonly suppressed_row_count: number
  readonly visible_row_count: number
  readonly totals: OperatorSpeechEconomicsTotals
  readonly rows: readonly OperatorSpeechEconomicsRow[]
}

export interface OperatorSpeechEconomicsRateCard {
  readonly id: string
  readonly provider_key: string
  readonly provider_stage: string
  readonly model_version: string
  readonly rate_version: string
  readonly billable_unit_kind: string
  readonly billing_currency: string
  readonly gross_price_microunits: number
  readonly tax_basis_points: number
  readonly discount_basis_points: number
  readonly credit_microunits_per_basis: number
  readonly inr_per_native_unit_microunits: number
  readonly conversion_source: string
  readonly approval_evidence: string
  readonly effective_from: string
  readonly effective_until: string | null
}

export interface OperatorSpeechEconomicsStatement {
  readonly id: string
  readonly provider_key: string
  readonly period_start: string
  readonly period_end: string
  readonly billing_currency: string
  readonly net_cost_microunits: number
  readonly net_cost_inr_microunits: number
  readonly approval_evidence: string
}

export interface OperatorSpeechEconomicsCommercial {
  readonly generated_at: string
  readonly presentation: 'commercial_terms'
  readonly empty: boolean
  readonly rate_cards: readonly OperatorSpeechEconomicsRateCard[]
  readonly statements: readonly OperatorSpeechEconomicsStatement[]
}

export interface OperatorConsoleApi {
  getOverview(signal?: AbortSignal): Promise<OperatorOverview>
  listDrafts(signal?: AbortSignal): Promise<readonly OperatorContentDraft[]>
  listPublications(
    signal?: AbortSignal,
  ): Promise<readonly OperatorPublicationStatus[]>
  listAuditEvents(
    cursor?: string,
    signal?: AbortSignal,
  ): Promise<OperatorAuditEventPage>
  getPilotAnalytics(
    signal?: AbortSignal,
  ): Promise<OperatorPilotAnalyticsSummary>
  listSupportDiagnostics(
    signal?: AbortSignal,
  ): Promise<OperatorSupportDiagnostics>
  getSpeechEconomics(
    filters?: OperatorSpeechEconomicsFilters,
    signal?: AbortSignal,
  ): Promise<OperatorSpeechEconomicsSummary>
  exportSpeechEconomics(
    filters?: OperatorSpeechEconomicsFilters,
    signal?: AbortSignal,
  ): Promise<void>
  getSpeechEconomicsCommercial(
    signal?: AbortSignal,
  ): Promise<OperatorSpeechEconomicsCommercial | null>
  search(
    query: string,
    cursor?: string,
    signal?: AbortSignal,
  ): Promise<OperatorSearchPage>
}

export class OperatorConsoleUnavailableError extends Error {
  constructor(message = 'Operator console unavailable.') {
    super(message)
    this.name = 'OperatorConsoleUnavailableError'
  }
}

export interface WebOperatorConsoleApiOptions {
  readonly baseUrl?: string
  readonly csrfProtection?: ApiCsrfProtection
  readonly fetch?: typeof fetch
  readonly readCookie?: () => string
}

export class WebOperatorConsoleApi implements OperatorConsoleApi {
  readonly #baseUrl: string
  readonly #csrfProtection: ApiCsrfProtection
  readonly #fetch: typeof fetch
  readonly #readCookie: () => string

  constructor(options: WebOperatorConsoleApiOptions = {}) {
    this.#baseUrl = options.baseUrl ?? '/api/v1'
    this.#csrfProtection =
      options.csrfProtection ?? 'sanctum-cookie'
    this.#fetch = options.fetch ?? globalThis.fetch.bind(globalThis)
    this.#readCookie =
      options.readCookie ??
      (() => (typeof document === 'undefined' ? '' : document.cookie))
  }

  async getOverview(signal?: AbortSignal): Promise<OperatorOverview> {
    return this.#get<OperatorOverview>('/operator/overview', signal)
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
    cursor?: string,
    signal?: AbortSignal,
  ): Promise<OperatorAuditEventPage> {
    const parameters = new URLSearchParams({ limit: '25' })

    if (cursor !== undefined) {
      parameters.set('cursor', cursor)
    }

    return this.#get<OperatorAuditEventPage>(
      `/operator/audit-events?${parameters.toString()}`,
      signal,
    )
  }

  async getPilotAnalytics(
    signal?: AbortSignal,
  ): Promise<OperatorPilotAnalyticsSummary> {
    return this.#get<OperatorPilotAnalyticsSummary>(
      '/operator/pilot-analytics',
      signal,
    )
  }

  async listSupportDiagnostics(
    signal?: AbortSignal,
  ): Promise<OperatorSupportDiagnostics> {
    return this.#get<OperatorSupportDiagnostics>(
      '/operator/support/diagnostics?limit=50',
      signal,
    )
  }

  async getSpeechEconomics(
    filters: OperatorSpeechEconomicsFilters = {},
    signal?: AbortSignal,
  ): Promise<OperatorSpeechEconomicsSummary> {
    return this.#get<OperatorSpeechEconomicsSummary>(
      `/operator/speech-economics${speechEconomicsQuery(filters)}`,
      signal,
    )
  }

  async exportSpeechEconomics(
    filters: OperatorSpeechEconomicsFilters = {},
    signal?: AbortSignal,
  ): Promise<void> {
    const response = await this.#fetch(
      `${this.#baseUrl}/operator/speech-economics/export${speechEconomicsQuery(filters)}`,
      {
        credentials: 'include',
        headers: {
          Accept: 'text/csv',
          ...(this.#csrfProtection === 'sanctum-cookie'
            ? { 'X-XSRF-TOKEN': this.#xsrfToken() }
            : {}),
        },
        method: 'GET',
        signal,
      },
    )

    if (!response.ok) {
      throw new OperatorConsoleUnavailableError()
    }

    const blob = await response.blob()
    const filename = exportFilename(response) ?? 'speech-economics.csv'
    const objectUrl = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = objectUrl
    link.download = filename
    link.click()
    URL.revokeObjectURL(objectUrl)
  }

  async getSpeechEconomicsCommercial(
    signal?: AbortSignal,
  ): Promise<OperatorSpeechEconomicsCommercial | null> {
    const response = await this.#fetch(
      `${this.#baseUrl}/operator/speech-economics/commercial`,
      {
        credentials: 'include',
        headers: {
          Accept: 'application/json',
          ...(this.#csrfProtection === 'sanctum-cookie'
            ? { 'X-XSRF-TOKEN': this.#xsrfToken() }
            : {}),
        },
        method: 'GET',
        signal,
      },
    )

    if (response.status === 404) {
      return null
    }

    if (!response.ok) {
      throw new OperatorConsoleUnavailableError()
    }

    return (await response.json()) as OperatorSpeechEconomicsCommercial
  }

  async search(
    query: string,
    cursor?: string,
    signal?: AbortSignal,
  ): Promise<OperatorSearchPage> {
    const parameters = new URLSearchParams({ limit: '20', q: query })

    if (cursor !== undefined) {
      parameters.set('cursor', cursor)
    }

    return this.#get<OperatorSearchPage>(
      `/operator/search?${parameters.toString()}`,
      signal,
    )
  }

  async #get<T>(path: string, signal?: AbortSignal): Promise<T> {
    const response = await this.#fetch(`${this.#baseUrl}${path}`, {
      credentials: 'include',
      headers: {
        Accept: 'application/json',
        ...(this.#csrfProtection === 'sanctum-cookie'
          ? { 'X-XSRF-TOKEN': this.#xsrfToken() }
          : {}),
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

export const defaultOperatorConsoleApi = new WebOperatorConsoleApi({
  baseUrl: defaultAuthenticationRuntime.apiBaseUrl,
  csrfProtection: defaultAuthenticationRuntime.csrfProtection,
  fetch: defaultAuthenticationRuntime.apiFetch,
})

function speechEconomicsQuery(
  filters: OperatorSpeechEconomicsFilters,
): string {
  const parameters = new URLSearchParams()

  for (const [key, value] of Object.entries(filters)) {
    if (typeof value === 'string' && value !== '') {
      parameters.set(key, value)
    }
  }

  const encoded = parameters.toString()

  return encoded === '' ? '' : `?${encoded}`
}

function exportFilename(response: Response): string | null {
  const disposition = response.headers.get('Content-Disposition')

  if (disposition === null) {
    return null
  }

  const utfMatch = /filename\*=UTF-8''([^;]+)/i.exec(disposition)

  if (utfMatch?.[1] !== undefined) {
    return decodeURIComponent(utfMatch[1])
  }

  const quoted = /filename="([^"]+)"/i.exec(disposition)

  return quoted?.[1] ?? null
}
