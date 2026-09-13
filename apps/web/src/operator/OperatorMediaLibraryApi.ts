import {
  defaultAuthenticationRuntime,
  type ApiCsrfProtection,
} from '../auth/defaultAuthentication'
import type {
  AppendOperatorMediaKannadaRequest,
  AppendOperatorMediaMetadataRequest,
  AttachOperatorMediaRequest,
  DecideOperatorMediaRightsRequest,
  OperatorMediaAction,
  OperatorMediaAttachableUsageKey,
  OperatorMediaAssetKind,
  OperatorMediaAssetState,
  OperatorMediaUploadState,
  ReviewOperatorMediaMetadataRequest,
  StartOperatorMediaUploadRequest,
} from '@global-english/contracts'

export type {
  AppendOperatorMediaKannadaRequest,
  AppendOperatorMediaMetadataRequest,
  AttachOperatorMediaRequest,
  DecideOperatorMediaRightsRequest,
  OperatorMediaAction,
  OperatorMediaAttachableUsageKey,
  OperatorMediaAssetKind,
  OperatorMediaAssetState,
  OperatorMediaUploadState,
  ReviewOperatorMediaMetadataRequest,
  StartOperatorMediaUploadRequest,
}

export interface OperatorMediaManifest {
  readonly generated_at: string
  readonly organization_id: string
  readonly allowed_actions: readonly OperatorMediaAction[]
  readonly limits: {
    readonly part_size_bytes: number
    readonly model_audio_maximum_bytes: number
    readonly lesson_audio_maximum_bytes: number
    readonly lesson_video_maximum_bytes: number
    readonly upload_expiry_hours: number
    readonly clean_review_expiry_days: number
  }
  readonly supported_kinds: readonly OperatorMediaAssetKind[]
  readonly rights_attestation: {
    readonly approval_reference: string
    readonly evidence_sha256: string
    readonly creates_asset_approval: false
  }
}

export interface OperatorMediaAssetSummary {
  readonly media_asset_id: string
  readonly asset_kind: OperatorMediaAssetKind
  readonly asset_hash: string
  readonly mime_type: string
  readonly byte_size: number
  readonly state: OperatorMediaAssetState
  readonly lifecycle_version: number
  readonly selected_scan_id: string
  readonly predecessor_media_asset_id: string | null
  readonly operator_label: string | null
  readonly metadata_revision_id: string | null
  readonly metadata_version: number | null
  readonly metadata_content_hash: string | null
  readonly uploaded_at: string
  readonly updated_at: string
}

export interface OperatorMediaUploadSummary {
  readonly id: string
  readonly candidate_asset_id: string
  readonly asset_kind: OperatorMediaAssetKind
  readonly safe_label: string
  readonly state: OperatorMediaUploadState
  readonly version: number
  readonly total_bytes: number
  readonly whole_object_hash: string
  readonly part_size_bytes: number
  readonly expected_part_count: number
  readonly received_part_count: number
  readonly selected_scan_id: string | null
  readonly accepted_asset_id: string | null
  readonly expires_at: string
  readonly review_expires_at: string | null
  readonly decision_reason_key: string | null
  readonly decided_at: string | null
  readonly updated_at: string
}

export interface OperatorMediaMetadataRevision {
  readonly id: string
  readonly revision_number: number
  readonly operator_label: string
  readonly transcript: string
  readonly text_alternative: string | null
  readonly content_hash: string
  readonly authored_at: string
  readonly metadata_version: number
}

export interface OperatorMediaMetadataVariant {
  readonly id: string
  readonly revision_number: number
  readonly locale: 'kn-IN'
  readonly source_content_hash: string
  readonly transcript: string
  readonly text_alternative: string | null
  readonly content_hash: string
  readonly translated_at: string
  readonly variant_version: number
}

export interface OperatorMediaMetadataReview {
  readonly id: string
  readonly program_id: string
  readonly metadata_revision_id: string
  readonly review_kind: 'academic_en' | 'kannada'
  readonly metadata_variant_id: string | null
  readonly decision: 'approved' | 'rejected' | 'withdrawn'
  readonly rationale_key: string | null
  readonly reviewer_membership_id: string
  readonly authority_appointment_id: string
  readonly reviewed_at: string
}

export interface OperatorMediaRightsRevision {
  readonly id: string
  readonly program_id: string
  readonly revision_number: number
  readonly evidence_status: 'approved' | 'rejected' | 'withdrawn' | 'pending'
  readonly evidence_hash: string
  readonly rights_holder_reference: string
  readonly provenance_reference: string
  readonly rights_basis_key: string
  readonly license_or_contract_reference: string
  readonly permitted_release_channels: readonly string[] | string
  readonly permitted_territories: readonly string[] | string
  readonly learner_delivery_allowed: boolean | 0 | 1
  readonly sharing_allowed: boolean | 0 | 1
  readonly derivative_use_allowed: boolean | 0 | 1
  readonly effective_at: string
  readonly expires_at: string | null
  readonly retention_policy_key: string
  readonly deletion_constraint_key: string
  readonly confirmed_at: string | null
}

export interface OperatorMediaUsage {
  readonly curriculum_version_id: string
  readonly content_revision_id: string
  readonly usage_key: OperatorMediaAttachableUsageKey
  readonly sequence: number
  readonly created_at: string
}

export interface OperatorMediaHistoryEvent {
  readonly id: string
  readonly event_type: string
  readonly lifecycle_version: number
  readonly selected_scan_id: string | null
  readonly related_media_asset_id: string | null
  readonly media_upload_id: string | null
  readonly actor_membership_id: string
  readonly reason_key: string | null
  readonly occurred_at: string
}

export interface OperatorMediaAssetDetail {
  readonly media_asset_id: string
  readonly asset_kind: OperatorMediaAssetKind
  readonly mime_type: string
  readonly byte_size: number
  readonly asset_hash: string
  readonly uploaded_at: string
  readonly lifecycle: {
    readonly state: OperatorMediaAssetState
    readonly version: number
    readonly selected_scan_id: string
    readonly predecessor_media_asset_id: string | null
    readonly managed_at: string
    readonly retired_at: string | null
    readonly retirement_reason_key: string | null
  }
  readonly scan: Readonly<Record<string, unknown>> | null
  readonly metadata: OperatorMediaMetadataRevision | null
  readonly kannada_variant: OperatorMediaMetadataVariant | null
  readonly metadata_reviews: readonly OperatorMediaMetadataReview[]
  readonly rights_revisions: readonly OperatorMediaRightsRevision[]
  readonly usages: readonly OperatorMediaUsage[]
  readonly events: readonly OperatorMediaHistoryEvent[]
}

export interface OperatorMediaUploadDetail extends OperatorMediaUploadSummary {
  readonly parts: readonly Readonly<Record<string, unknown>>[]
  readonly scans: readonly Readonly<Record<string, unknown>>[]
  readonly events: readonly Readonly<Record<string, unknown>>[]
}

export interface OperatorMediaPage<T> {
  readonly data: readonly T[]
  readonly next_cursor: string | null
  readonly allowed_actions: readonly OperatorMediaAction[]
}

export interface OperatorMediaCommandResult {
  readonly result_type: string
  readonly result_id: string
  readonly replayed: boolean
  readonly [key: string]: unknown
}

export interface OperatorMediaPreview {
  readonly blob: Blob
  readonly contentHash: string | null
  readonly contentType: string
}

export interface OperatorMediaLibraryApi {
  getManifest(signal?: AbortSignal): Promise<OperatorMediaManifest>
  listAssets(
    filters?: Readonly<{
      cursor?: string
      kind?: OperatorMediaAssetKind
      state?: OperatorMediaAssetState
    }>,
    signal?: AbortSignal,
  ): Promise<OperatorMediaPage<OperatorMediaAssetSummary>>
  getAsset(id: string, signal?: AbortSignal): Promise<OperatorMediaAssetDetail>
  previewAsset(id: string, signal?: AbortSignal): Promise<OperatorMediaPreview>
  listUploads(
    filters?: Readonly<{ cursor?: string; state?: OperatorMediaUploadState }>,
    signal?: AbortSignal,
  ): Promise<OperatorMediaPage<OperatorMediaUploadSummary>>
  getUpload(id: string, signal?: AbortSignal): Promise<OperatorMediaUploadDetail>
  previewUpload(id: string, signal?: AbortSignal): Promise<OperatorMediaPreview>
  startUpload(body: StartOperatorMediaUploadRequest, key: string): Promise<OperatorMediaCommandResult>
  uploadPart(
    id: string,
    bytes: Blob,
    partNumber: number,
    partHash: string,
    expectedVersion: number,
    key: string,
  ): Promise<OperatorMediaCommandResult>
  finalizeUpload(id: string, expectedVersion: number, key: string): Promise<OperatorMediaCommandResult>
  acceptUpload(id: string, expectedVersion: number, key: string): Promise<OperatorMediaCommandResult>
  rejectUpload(id: string, expectedVersion: number, reason: string, key: string): Promise<OperatorMediaCommandResult>
  adoptAsset(id: string, hash: string, key: string): Promise<OperatorMediaCommandResult>
  appendMetadata(id: string, body: AppendOperatorMediaMetadataRequest, key: string): Promise<OperatorMediaCommandResult>
  appendKannada(id: string, body: AppendOperatorMediaKannadaRequest, key: string): Promise<OperatorMediaCommandResult>
  reviewMetadata(body: ReviewOperatorMediaMetadataRequest, key: string): Promise<OperatorMediaCommandResult>
  decideRights(body: DecideOperatorMediaRightsRequest, key: string): Promise<OperatorMediaCommandResult>
  attach(id: string, body: AttachOperatorMediaRequest, key: string): Promise<OperatorMediaCommandResult>
  replace(id: string, successorId: string, predecessorVersion: number, successorVersion: number, key: string): Promise<OperatorMediaCommandResult>
  retire(id: string, version: number, reason: string, key: string): Promise<OperatorMediaCommandResult>
}

export type OperatorMediaErrorKind =
  | 'concurrent_change'
  | 'duplicate_use'
  | 'forbidden'
  | 'idempotency_conflict'
  | 'not_found'
  | 'stale_lifecycle'
  | 'stale_metadata'
  | 'stale_upload'
  | 'stale_workspace'
  | 'storage_failure'
  | 'unavailable'
  | 'validation'

export class OperatorMediaApiError extends Error {
  readonly kind: OperatorMediaErrorKind

  constructor(kind: OperatorMediaErrorKind) {
    super('The governed media request could not be completed.')
    this.name = 'OperatorMediaApiError'
    this.kind = kind
  }
}

export interface WebOperatorMediaLibraryApiOptions {
  readonly baseUrl?: string
  readonly csrfProtection?: ApiCsrfProtection
  readonly fetch?: typeof fetch
  readonly readCookie?: () => string
}

export class WebOperatorMediaLibraryApi implements OperatorMediaLibraryApi {
  readonly #baseUrl: string
  readonly #csrfProtection: ApiCsrfProtection
  readonly #fetch: typeof fetch
  readonly #readCookie: () => string

  constructor(options: WebOperatorMediaLibraryApiOptions = {}) {
    this.#baseUrl = options.baseUrl ?? '/api/v1'
    this.#csrfProtection = options.csrfProtection ?? 'sanctum-cookie'
    this.#fetch = options.fetch ?? globalThis.fetch.bind(globalThis)
    this.#readCookie =
      options.readCookie ??
      (() => (typeof document === 'undefined' ? '' : document.cookie))
  }

  getManifest(signal?: AbortSignal) {
    return this.#get<OperatorMediaManifest>('/operator/media/manifest', signal)
  }

  listAssets(
    filters: Readonly<{
      cursor?: string
      kind?: OperatorMediaAssetKind
      state?: OperatorMediaAssetState
    }> = {},
    signal?: AbortSignal,
  ) {
    const parameters = new URLSearchParams({ limit: '25' })
    if (filters.cursor !== undefined) parameters.set('cursor', filters.cursor)
    if (filters.kind !== undefined) parameters.set('kind', filters.kind)
    if (filters.state !== undefined) parameters.set('state', filters.state)
    return this.#get<OperatorMediaPage<OperatorMediaAssetSummary>>(
      `/operator/media/assets?${parameters.toString()}`,
      signal,
    )
  }

  async getAsset(id: string, signal?: AbortSignal) {
    const response = await this.#get<{
      data: OperatorMediaAssetDetail
      allowed_actions: readonly OperatorMediaAction[]
    }>(`/operator/media/assets/${this.#segment(id)}`, signal)
    return response.data
  }

  previewAsset(id: string, signal?: AbortSignal) {
    return this.#preview(`/operator/media/assets/${this.#segment(id)}/preview`, signal)
  }

  listUploads(
    filters: Readonly<{
      cursor?: string
      state?: OperatorMediaUploadState
    }> = {},
    signal?: AbortSignal,
  ) {
    const parameters = new URLSearchParams({ limit: '25' })
    if (filters.cursor !== undefined) parameters.set('cursor', filters.cursor)
    if (filters.state !== undefined) parameters.set('state', filters.state)
    return this.#get<OperatorMediaPage<OperatorMediaUploadSummary>>(
      `/operator/media/uploads?${parameters.toString()}`,
      signal,
    )
  }

  async getUpload(id: string, signal?: AbortSignal) {
    const response = await this.#get<{
      data: OperatorMediaUploadDetail
      allowed_actions: readonly OperatorMediaAction[]
    }>(`/operator/media/uploads/${this.#segment(id)}`, signal)
    return response.data
  }

  previewUpload(id: string, signal?: AbortSignal) {
    return this.#preview(`/operator/media/uploads/${this.#segment(id)}/preview`, signal)
  }

  startUpload(body: StartOperatorMediaUploadRequest, key: string) {
    return this.#post<OperatorMediaCommandResult>('/operator/media/uploads', body, key)
  }

  uploadPart(
    id: string,
    bytes: Blob,
    partNumber: number,
    partHash: string,
    expectedVersion: number,
    key: string,
  ) {
    return this.#request<OperatorMediaCommandResult>(
      `/operator/media/uploads/${this.#segment(id)}/parts`,
      {
        body: bytes,
        headers: {
          'Content-Type': 'application/octet-stream',
          'Idempotency-Key': key,
          'Media-Part-Number': String(partNumber),
          'Media-Part-SHA256': partHash,
          'Media-Upload-Version': String(expectedVersion),
        },
        method: 'POST',
      },
    )
  }

  finalizeUpload(id: string, expectedVersion: number, key: string) {
    return this.#post<OperatorMediaCommandResult>(
      `/operator/media/uploads/${this.#segment(id)}/finalizations`,
      { expected_upload_version: expectedVersion },
      key,
    )
  }

  acceptUpload(id: string, expectedVersion: number, key: string) {
    return this.#post<OperatorMediaCommandResult>(
      `/operator/media/uploads/${this.#segment(id)}/acceptances`,
      { expected_upload_version: expectedVersion },
      key,
    )
  }

  rejectUpload(id: string, expectedVersion: number, reason: string, key: string) {
    return this.#post<OperatorMediaCommandResult>(
      `/operator/media/uploads/${this.#segment(id)}/rejections`,
      { expected_upload_version: expectedVersion, reason_key: reason },
      key,
    )
  }

  adoptAsset(id: string, hash: string, key: string) {
    return this.#post<OperatorMediaCommandResult>(
      `/operator/media/assets/${this.#segment(id)}/adoptions`,
      { selected_asset_hash: hash },
      key,
    )
  }

  appendMetadata(id: string, body: AppendOperatorMediaMetadataRequest, key: string) {
    return this.#post<OperatorMediaCommandResult>(
      `/operator/media/assets/${this.#segment(id)}/metadata-revisions`,
      body,
      key,
    )
  }

  appendKannada(id: string, body: AppendOperatorMediaKannadaRequest, key: string) {
    return this.#post<OperatorMediaCommandResult>(
      `/operator/media/metadata-revisions/${this.#segment(id)}/kannada-variants`,
      body,
      key,
    )
  }

  reviewMetadata(body: ReviewOperatorMediaMetadataRequest, key: string) {
    return this.#post<OperatorMediaCommandResult>(
      '/operator/media/metadata-reviews',
      body,
      key,
    )
  }

  decideRights(body: DecideOperatorMediaRightsRequest, key: string) {
    return this.#post<OperatorMediaCommandResult>(
      '/operator/media/rights-decisions',
      body,
      key,
    )
  }

  attach(id: string, body: AttachOperatorMediaRequest, key: string) {
    return this.#post<OperatorMediaCommandResult>(
      `/operator/media/assets/${this.#segment(id)}/attachments`,
      body,
      key,
    )
  }

  replace(
    id: string,
    successorId: string,
    predecessorVersion: number,
    successorVersion: number,
    key: string,
  ) {
    return this.#post<OperatorMediaCommandResult>(
      `/operator/media/assets/${this.#segment(id)}/replacements`,
      {
        expected_predecessor_lifecycle_version: predecessorVersion,
        expected_successor_lifecycle_version: successorVersion,
        successor_media_asset_id: successorId,
      },
      key,
    )
  }

  retire(id: string, version: number, reason: string, key: string) {
    return this.#post<OperatorMediaCommandResult>(
      `/operator/media/assets/${this.#segment(id)}/retirements`,
      { expected_lifecycle_version: version, reason_key: reason },
      key,
    )
  }

  #post<T>(path: string, body: object, key: string): Promise<T> {
    return this.#request<T>(path, {
      body: JSON.stringify(body),
      headers: {
        'Content-Type': 'application/json',
        'Idempotency-Key': key,
      },
      method: 'POST',
    })
  }

  #get<T>(path: string, signal?: AbortSignal): Promise<T> {
    return this.#request<T>(path, { method: 'GET', signal })
  }

  async #preview(path: string, signal?: AbortSignal): Promise<OperatorMediaPreview> {
    const response = await this.#fetch(`${this.#baseUrl}${path}`, {
      credentials: 'include',
      headers: this.#headers({ Accept: 'audio/*, video/*' }),
      method: 'GET',
      signal,
    })
    if (!response.ok) throw await this.#error(response)
    return {
      blob: await response.blob(),
      contentHash: response.headers.get('X-Content-SHA256'),
      contentType: response.headers.get('Content-Type') ?? 'application/octet-stream',
    }
  }

  async #request<T>(path: string, init: RequestInit): Promise<T> {
    const response = await this.#fetch(`${this.#baseUrl}${path}`, {
      ...init,
      credentials: 'include',
      headers: this.#headers(init.headers),
    })
    if (!response.ok) throw await this.#error(response)
    try {
      return (await response.json()) as T
    } catch {
      throw new OperatorMediaApiError('unavailable')
    }
  }

  #headers(headers?: HeadersInit): Headers {
    const result = new Headers(headers)
    if (!result.has('Accept')) result.set('Accept', 'application/json')
    if (this.#csrfProtection === 'sanctum-cookie') {
      result.set('X-XSRF-TOKEN', this.#xsrfToken())
    }
    return result
  }

  async #error(response: Response): Promise<OperatorMediaApiError> {
    let key = ''
    try {
      const body = (await response.json()) as Readonly<{ message_key?: unknown }>
      if (typeof body.message_key === 'string') key = body.message_key
    } catch {
      // Response bodies are intentionally not reflected to the caller.
    }
    const kinds: Readonly<Record<string, OperatorMediaErrorKind>> = {
      'problem.forbidden': 'forbidden',
      'problem.media_library.concurrent_change': 'concurrent_change',
      'problem.media_library.duplicate_use': 'duplicate_use',
      'problem.media_library.idempotency_conflict': 'idempotency_conflict',
      'problem.media_library.stale_lifecycle': 'stale_lifecycle',
      'problem.media_library.stale_metadata': 'stale_metadata',
      'problem.media_library.stale_upload': 'stale_upload',
      'problem.media_library.stale_workspace': 'stale_workspace',
      'problem.media_library.storage_failure': 'storage_failure',
      'problem.media_library.not_found': 'not_found',
      'problem.not_found': 'not_found',
      'problem.validation_failed': 'validation',
    }
    return new OperatorMediaApiError(kinds[key] ?? 'unavailable')
  }

  #segment(value: string): string {
    if (value === '') throw new OperatorMediaApiError('validation')
    return encodeURIComponent(value)
  }

  #xsrfToken(): string {
    const match = /(?:^|;\s*)XSRF-TOKEN=([^;]+)/.exec(this.#readCookie())
    return match === null ? '' : decodeURIComponent(match[1] ?? '')
  }
}

export const defaultOperatorMediaLibraryApi = new WebOperatorMediaLibraryApi({
  baseUrl: defaultAuthenticationRuntime.apiBaseUrl,
  csrfProtection: defaultAuthenticationRuntime.csrfProtection,
  fetch: defaultAuthenticationRuntime.apiFetch,
})
