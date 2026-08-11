import { cleanup, fireEvent, render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { OperatorMediaLibrary } from './OperatorMediaLibrary'
import {
  OperatorMediaApiError,
  type OperatorMediaAction,
  type OperatorMediaAssetDetail,
  type OperatorMediaAssetKind,
  type OperatorMediaAssetSummary,
  type OperatorMediaLibraryApi,
  type OperatorMediaManifest,
  type OperatorMediaUploadDetail,
} from './OperatorMediaLibraryApi'

beforeEach(() => {
  Object.defineProperty(URL, 'createObjectURL', {
    configurable: true,
    value: vi.fn(() => 'blob:private-media-preview'),
  })
  Object.defineProperty(URL, 'revokeObjectURL', {
    configurable: true,
    value: vi.fn(),
  })
})

afterEach(() => {
  cleanup()
  vi.restoreAllMocks()
})

describe('operator Media Library', () => {
  it('keeps uploads usable when assets fail and retries only the failed resource', async () => {
    const listAssets = vi.fn()
      .mockRejectedValueOnce(new Error('asset read unavailable'))
      .mockResolvedValueOnce(page([assetSummary()]))
    const api = mediaApi({ listAssets })
    const user = userEvent.setup()

    renderLibrary(api)

    expect(await screen.findByText('Clean candidate')).toBeInTheDocument()
    expect(await screen.findByRole('alert')).toHaveTextContent(
      'This Media Library resource is unavailable.',
    )
    await user.click(screen.getByRole('button', { name: 'Try again' }))

    expect(await screen.findByText('Foundation greeting')).toBeInTheDocument()
    expect(listAssets).toHaveBeenCalledTimes(2)
    expect(api.listUploads).toHaveBeenCalledOnce()
  })

  it('appends a cursor page without losing the first bounded page', async () => {
    const first = assetSummary()
    const second = { ...assetSummary(ids.asset2), operator_label: 'Second asset' }
    const listAssets = vi.fn(async (filters?: Readonly<{ cursor?: string }>) =>
      filters?.cursor === 'tenant-bound-cursor'
        ? page([second])
        : page([first], 'tenant-bound-cursor'))
    const api = mediaApi({ listAssets: listAssets as OperatorMediaLibraryApi['listAssets'] })
    const user = userEvent.setup()

    renderLibrary(api)
    expect(await screen.findByText('Foundation greeting')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Load more' }))

    expect(await screen.findByText('Second asset')).toBeInTheDocument()
    expect(screen.getByText('Foundation greeting')).toBeInTheDocument()
    expect(listAssets).toHaveBeenLastCalledWith(
      { cursor: 'tenant-bound-cursor' },
      expect.any(AbortSignal),
    )
  })

  it('resumes the same private upload after a failed part without restarting it', async () => {
    const bytes = new Uint8Array([1, 2, 3, 4])
    const digest = new Uint8Array(32).fill(10).buffer
    vi.spyOn(Blob.prototype, 'arrayBuffer').mockResolvedValue(bytes.buffer)
    vi.spyOn(globalThis.crypto.subtle, 'digest').mockResolvedValue(digest)
    const startUpload = vi.fn(async () => command({ id: ids.upload, version: 1 }))
    const uploadPart = vi.fn()
      .mockRejectedValueOnce(new OperatorMediaApiError('storage_failure'))
      .mockResolvedValueOnce(command({ upload_version: 2 }))
    const finalizeUpload = vi.fn(async () => command({ upload_version: 3 }))
    const api = mediaApi({
      finalizeUpload,
      getManifest: vi.fn(async () => manifest(['start_upload'])),
      startUpload,
      uploadPart,
    })
    const user = userEvent.setup()

    renderLibrary(api)
    const uploadLabels = await screen.findAllByText('Start private upload')
    await user.click(uploadLabels[0]!)
    await user.type(screen.getByLabelText('Safe operator label'), 'New greeting')
    const fileInput = screen.getByLabelText('Private media file')
    await user.upload(
      fileInput,
      new File([bytes], 'greeting.wav', { type: 'audio/wav' }),
    )
    fireEvent.submit(fileInput.closest('form')!)

    expect(await screen.findByText(/could not be completed/iu)).toBeInTheDocument()
    expect(startUpload).toHaveBeenCalledOnce()
    await user.click(screen.getByRole('button', { name: 'Resume in-memory upload' }))

    expect(await screen.findByText('The governed media change was recorded.'))
      .toBeInTheDocument()
    expect(startUpload).toHaveBeenCalledOnce()
    expect(uploadPart).toHaveBeenCalledTimes(2)
    expect(finalizeUpload).toHaveBeenCalledWith(ids.upload, 2, expect.any(String))
  })

  it('loads a private preview on demand and revokes its object URL on unmount', async () => {
    const api = mediaApi()
    const user = userEvent.setup()
    const rendered = renderLibrary(api)

    await user.click(await screen.findByRole('button', { name: /Foundation greeting/iu }))
    await user.click(await screen.findByRole('button', { name: 'Load private preview' }))

    expect(api.previewAsset).toHaveBeenCalledWith(ids.asset, expect.any(AbortSignal))
    expect(URL.createObjectURL).toHaveBeenCalledOnce()
    expect(rendered.container.querySelector('audio')).toHaveAttribute(
      'src',
      'blob:private-media-preview',
    )
    rendered.unmount()
    expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:private-media-preview')
  })

  it('renders lesson audio as private audio while keeping Content Studio attachment closed', async () => {
    const api = mediaApi({
      getAsset: vi.fn(async () => assetDetail(true, 'lesson_audio')),
      getManifest: vi.fn(async () => manifest(['attach_asset', 'start_upload'])),
    })
    const user = userEvent.setup()

    const rendered = renderLibrary(api)
    expect(await screen.findByRole('option', { name: 'Lesson audio' }))
      .toBeInTheDocument()
    expect(screen.getByText('Lesson audio limit: 20.0 MB')).toBeInTheDocument()

    await user.click(await screen.findByRole('button', { name: /Foundation greeting/iu }))
    await user.click(await screen.findByRole('button', { name: 'Load private preview' }))

    expect(rendered.container.querySelector('audio')).toHaveAttribute(
      'src',
      'blob:private-media-preview',
    )
    expect(screen.queryByText('Attach to Content Studio source')).not.toBeInTheDocument()
  })

  it('submits the exact server attestation and preserves fields after a stale conflict', async () => {
    const decideRights = vi.fn(async () => {
      throw new OperatorMediaApiError('stale_lifecycle')
    })
    const api = mediaApi({
      decideRights,
      getManifest: vi.fn(async () => manifest(['record_rights_decision'])),
    })
    const user = userEvent.setup()

    renderLibrary(api)
    await user.click(await screen.findByRole('button', { name: /Foundation greeting/iu }))
    await user.click(await screen.findByText('Confirm exact program rights'))
    await user.type(screen.getByLabelText('Program ID'), ids.program)
    await user.type(screen.getByLabelText('Authority appointment ID'), ids.appointment)
    await user.click(screen.getByLabelText(/explicitly confirm the shown delivery/iu))
    await user.click(screen.getByRole('button', { name: 'Record appointed rights approval' }))

    expect(decideRights).toHaveBeenCalledWith(
      expect.objectContaining({
        evidence_hash: 'f'.repeat(64),
        license_or_contract_reference: 'APP-0044',
        media_asset_id: ids.asset,
        provenance_reference: 'APP-0044',
        selected_asset_hash: 'a'.repeat(64),
      }),
      expect.any(String),
    )
    expect(await screen.findByText(/authoritative media state changed/iu))
      .toBeInTheDocument()
    expect(screen.getByLabelText('Program ID')).toHaveValue(ids.program)
  })

  it('attaches only the exact selected review, rights and metadata evidence', async () => {
    const attach = vi.fn(async () => command({ media_attachment_event_id: ids.event }))
    const governedAsset = assetDetail(true)
    const api = mediaApi({
      attach,
      getAsset: vi.fn(async () => governedAsset),
      getManifest: vi.fn(async () => manifest(['attach_asset'])),
    })
    const user = userEvent.setup()

    renderLibrary(api)
    await user.click(await screen.findByRole('button', { name: /Foundation greeting/iu }))
    await user.click(await screen.findByText('Attach to Content Studio source'))
    const form = screen.getByText('Attach to Content Studio source')
      .closest('details') as HTMLDetailsElement
    await user.type(within(form).getByLabelText('Program ID'), ids.program)
    await user.type(within(form).getByLabelText('Draft curriculum version ID'), ids.version)
    await user.type(within(form).getByLabelText('Current source revision ID'), ids.source)
    await user.selectOptions(within(form).getByLabelText('Approved English review'), ids.reviewEn)
    await user.selectOptions(within(form).getByLabelText('Approved Kannada review'), ids.reviewKn)
    await user.selectOptions(within(form).getByLabelText('Approved rights revision'), ids.rights)
    await user.click(within(form).getByLabelText(/Attach this exact reviewed asset/iu))
    await user.click(within(form).getByRole('button', { name: 'Record exact attachment' }))

    expect(attach).toHaveBeenCalledWith(
      ids.asset,
      {
        curriculum_version_id: ids.version,
        english_review_id: ids.reviewEn,
        expected_lifecycle_version: 1,
        expected_workspace_version: 1,
        kannada_review_id: ids.reviewKn,
        media_rights_revision_id: ids.rights,
        metadata_content_hash: 'c'.repeat(64),
        metadata_revision_id: ids.metadata,
        metadata_variant_id: ids.variant,
        program_id: ids.program,
        source_revision_id: ids.source,
        usage_key: 'model_audio',
        variant_content_hash: 'd'.repeat(64),
      },
      expect.any(String),
    )
  })
})

const ids = {
  appointment: '01J00000000000000000000007',
  asset: '01J00000000000000000000001',
  asset2: '01J00000000000000000000002',
  event: '01J00000000000000000000003',
  metadata: '01J00000000000000000000004',
  program: '01J00000000000000000000005',
  reviewEn: '01J00000000000000000000006',
  reviewKn: '01J00000000000000000000008',
  rights: '01J00000000000000000000009',
  source: '01J00000000000000000000010',
  upload: '01J00000000000000000000011',
  variant: '01J00000000000000000000012',
  version: '01J00000000000000000000013',
} as const

function renderLibrary(api: OperatorMediaLibraryApi) {
  return render(
    <OperatorMediaLibrary
      api={api}
      createCommandId={() => '11111111-2222-4333-8444-555555555555'}
      locale="en-IN"
      localizationPolicy="development-preview"
    />,
  )
}

function manifest(actions: readonly OperatorMediaAction[] = []): OperatorMediaManifest {
  return {
    allowed_actions: actions,
    generated_at: '2026-08-10T00:00:00+00:00',
    limits: {
      clean_review_expiry_days: 7,
      lesson_audio_maximum_bytes: 20_000_000,
      lesson_video_maximum_bytes: 20_000_000,
      model_audio_maximum_bytes: 10_000_000,
      part_size_bytes: 1_000_000,
      upload_expiry_hours: 24,
    },
    organization_id: '01J00000000000000000000000',
    rights_attestation: {
      approval_reference: 'APP-0044',
      creates_asset_approval: false,
      evidence_sha256: 'f'.repeat(64),
    },
    supported_kinds: ['model_audio', 'lesson_audio', 'lesson_video'],
  }
}

function page<T>(data: readonly T[], nextCursor: string | null = null) {
  return { allowed_actions: [], data, next_cursor: nextCursor }
}

function assetSummary(id: string = ids.asset): OperatorMediaAssetSummary {
  return {
    asset_hash: 'a'.repeat(64),
    asset_kind: 'model_audio',
    byte_size: 4,
    lifecycle_version: 1,
    media_asset_id: id,
    metadata_content_hash: null,
    metadata_revision_id: null,
    metadata_version: null,
    mime_type: 'audio/wav',
    operator_label: 'Foundation greeting',
    predecessor_media_asset_id: null,
    selected_scan_id: '01J00000000000000000000014',
    state: 'active',
    updated_at: '2026-08-10T00:00:00+00:00',
    uploaded_at: '2026-08-10T00:00:00+00:00',
  }
}

function assetDetail(
  governed = false,
  assetKind: OperatorMediaAssetKind = 'model_audio',
): OperatorMediaAssetDetail {
  return {
    asset_hash: 'a'.repeat(64),
    asset_kind: assetKind,
    byte_size: 4,
    events: [],
    kannada_variant: governed ? {
      content_hash: 'd'.repeat(64),
      id: ids.variant,
      locale: 'kn-IN',
      revision_number: 1,
      source_content_hash: 'c'.repeat(64),
      text_alternative: null,
      transcript: 'ನಮಸ್ಕಾರ',
      translated_at: '2026-08-10T00:00:00+00:00',
      variant_version: 1,
    } : null,
    lifecycle: {
      managed_at: '2026-08-10T00:00:00+00:00',
      predecessor_media_asset_id: null,
      retired_at: null,
      retirement_reason_key: null,
      selected_scan_id: '01J00000000000000000000014',
      state: 'active',
      version: 1,
    },
    media_asset_id: ids.asset,
    metadata: governed ? {
      authored_at: '2026-08-10T00:00:00+00:00',
      content_hash: 'c'.repeat(64),
      id: ids.metadata,
      metadata_version: 1,
      operator_label: 'Foundation greeting',
      revision_number: 1,
      text_alternative: null,
      transcript: 'Hello',
    } : null,
    metadata_reviews: governed ? [
      review(ids.reviewEn, 'academic_en'),
      review(ids.reviewKn, 'kannada'),
    ] : [],
    mime_type: 'audio/wav',
    rights_revisions: governed ? [{
      confirmed_at: '2026-08-10T00:00:00+00:00',
      deletion_constraint_key: 'preserve_while_referenced',
      derivative_use_allowed: false,
      effective_at: '2026-08-10T00:00:00+00:00',
      evidence_hash: 'f'.repeat(64),
      evidence_status: 'approved',
      expires_at: null,
      id: ids.rights,
      learner_delivery_allowed: true,
      license_or_contract_reference: 'APP-0044',
      permitted_release_channels: ['web', 'android'],
      permitted_territories: ['IN'],
      program_id: ids.program,
      provenance_reference: 'APP-0044',
      retention_policy_key: 'accepted_media',
      revision_number: 1,
      rights_basis_key: 'owner_attestation',
      rights_holder_reference: 'project-owner',
      sharing_allowed: false,
    }] : [],
    scan: {},
    uploaded_at: '2026-08-10T00:00:00+00:00',
    usages: [],
  }
}

function review(id: string, kind: 'academic_en' | 'kannada') {
  return {
    authority_appointment_id: ids.appointment,
    decision: 'approved' as const,
    id,
    metadata_revision_id: ids.metadata,
    metadata_variant_id: kind === 'kannada' ? ids.variant : null,
    program_id: ids.program,
    rationale_key: null,
    review_kind: kind,
    reviewed_at: '2026-08-10T00:00:00+00:00',
    reviewer_membership_id: '01J00000000000000000000015',
  }
}

function uploadDetail(): OperatorMediaUploadDetail {
  return {
    accepted_asset_id: null,
    asset_kind: 'model_audio',
    candidate_asset_id: '01J00000000000000000000016',
    decided_at: null,
    decision_reason_key: null,
    events: [],
    expected_part_count: 1,
    expires_at: '2026-08-11T00:00:00+00:00',
    id: ids.upload,
    part_size_bytes: 1_000_000,
    parts: [],
    received_part_count: 1,
    review_expires_at: '2026-08-17T00:00:00+00:00',
    safe_label: 'Clean candidate',
    scans: [],
    selected_scan_id: '01J00000000000000000000014',
    state: 'scan_clean',
    total_bytes: 4,
    updated_at: '2026-08-10T00:00:00+00:00',
    version: 2,
    whole_object_hash: 'b'.repeat(64),
  }
}

function command(data: Readonly<Record<string, unknown>>) {
  return {
    ...data,
    replayed: false,
    result_id: ids.event,
    result_type: 'media_command',
  }
}

function mediaApi(
  overrides: Partial<OperatorMediaLibraryApi> = {},
): OperatorMediaLibraryApi {
  const upload = uploadDetail()
  const api: OperatorMediaLibraryApi = {
    acceptUpload: vi.fn(async () => command({ upload_version: 3 })),
    adoptAsset: vi.fn(async () => command({ media_asset_id: ids.asset })),
    appendKannada: vi.fn(async () => command({ metadata_variant_id: ids.variant })),
    appendMetadata: vi.fn(async () => command({ metadata_revision_id: ids.metadata })),
    attach: vi.fn(async () => command({ media_attachment_event_id: ids.event })),
    decideRights: vi.fn(async () => command({ media_rights_revision_id: ids.rights })),
    finalizeUpload: vi.fn(async () => command({ upload_version: 2 })),
    getAsset: vi.fn(async () => assetDetail()),
    getManifest: vi.fn(async () => manifest()),
    getUpload: vi.fn(async () => upload),
    listAssets: vi.fn(async () => page([assetSummary()])),
    listUploads: vi.fn(async () => page([upload])),
    previewAsset: vi.fn(async () => ({
      blob: new Blob(['audio']),
      contentHash: 'a'.repeat(64),
      contentType: 'audio/wav',
    })),
    previewUpload: vi.fn(async () => ({
      blob: new Blob(['audio']),
      contentHash: 'b'.repeat(64),
      contentType: 'audio/wav',
    })),
    rejectUpload: vi.fn(async () => command({ upload_version: 3 })),
    replace: vi.fn(async () => command({ media_asset_replacement_id: ids.event })),
    retire: vi.fn(async () => command({ lifecycle_version: 2 })),
    reviewMetadata: vi.fn(async () => command({ metadata_review_id: ids.reviewEn })),
    startUpload: vi.fn(async () => command({ id: ids.upload, version: 1 })),
    uploadPart: vi.fn(async () => command({ upload_version: 2 })),
  }

  return Object.assign(api, overrides)
}
