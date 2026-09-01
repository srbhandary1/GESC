import { cleanup, render, screen } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { OperatorConsole } from './OperatorConsole'
import type {
  OperatorConsoleApi,
  OperatorContentDraft,
} from './OperatorConsoleApi'
import type { OperatorOrganizationApi } from './OperatorOrganizationApi'
import type { OperatorLearnerApi } from './OperatorLearnerApi'
import type { OperatorEntitlementApi } from './OperatorEntitlementApi'
import type { OperatorContentStudioApi } from './OperatorContentStudioApi'
import type { OperatorMediaLibraryApi } from './OperatorMediaLibraryApi'

afterEach(() => {
  cleanup()
  vi.restoreAllMocks()
})

describe('operator console', () => {
  it('mounts the Media Library only from the server panel manifest', async () => {
    const getManifest = vi.fn(async () => ({
      allowed_actions: [],
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
    }))
    const listAssets = vi.fn(async () => ({
      allowed_actions: [], data: [], next_cursor: null,
    }))
    const listUploads = vi.fn(async () => ({
      allowed_actions: [], data: [], next_cursor: null,
    }))
    const api = operatorApi({
      getOverview: vi.fn(async () => ({
        generated_at: '2026-08-10T00:00:00+00:00',
        navigation: ['overview', 'learning'] as const,
        organization: organization(),
        panels: ['media_library'] as const,
        search_available: false,
      })),
    })

    render(
      <OperatorConsole
        api={api}
        locale="en-IN"
        localizationPolicy="development-preview"
        mediaApi={{
          getManifest,
          listAssets,
          listUploads,
        } as unknown as OperatorMediaLibraryApi}
      />,
    )

    expect(await screen.findByRole('heading', { name: 'Media Library' }))
      .toBeInTheDocument()
    expect(await screen.findByText('No accepted assets match these filters.'))
      .toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'Content Studio' }))
      .not.toBeInTheDocument()
    expect(getManifest).toHaveBeenCalledOnce()
    expect(listAssets).toHaveBeenCalledOnce()
    expect(listUploads).toHaveBeenCalledOnce()
  })

  it('mounts Content Studio only from the server panel manifest', async () => {
    const listWorkspaces = vi.fn(async () => ({
      allowed_actions: [],
      data: [],
      next_cursor: null,
    }))
    const listReviewQueue = vi.fn(async () => ({
      allowed_actions: [],
      data: [],
      next_cursor: null,
    }))
    const listPublications = vi.fn(async () => ({
      allowed_actions: [],
      items: [],
      next_cursor: null,
    }))
    const api = operatorApi({
      getOverview: vi.fn(async () => ({
        generated_at: '2026-08-09T10:00:00+00:00',
        navigation: ['overview', 'learning'] as const,
        organization: organization(),
        panels: ['content_studio'] as const,
        search_available: false,
      })),
    })

    render(
      <OperatorConsole
        api={api}
        contentApi={{
          listPublications,
          listReviewQueue,
          listWorkspaces,
        } as unknown as OperatorContentStudioApi}
        locale="en-IN"
        localizationPolicy="development-preview"
      />,
    )

    expect(await screen.findByRole('heading', { name: 'Content Studio' }))
      .toBeInTheDocument()
    expect(listWorkspaces).toHaveBeenCalledOnce()
    expect(listReviewQueue).toHaveBeenCalledOnce()
    expect(listPublications).toHaveBeenCalledOnce()
    expect(screen.queryByRole('heading', { name: 'Publish status' }))
      .not.toBeInTheDocument()
  })

  it('renders only learner panels granted by the server manifest', async () => {
    const listLearners = vi.fn(async () => ({
      allowed_actions: [],
      choices: null,
      data: [],
      next_cursor: null,
    }))
    const listInvitations = vi.fn()
    const api = operatorApi({
      getOverview: vi.fn(async () => ({
        generated_at: '2026-08-09T10:00:00+00:00',
        navigation: ['overview', 'learners'] as const,
        organization: organization(),
        panels: ['learner_directory'] as const,
        search_available: false,
      })),
    })

    render(
      <OperatorConsole
        api={api}
        learnerApi={{
          listInvitations,
          listLearners,
          listTuitionRequests: vi.fn(async () => ({ requests: [] })),
        } as unknown as OperatorLearnerApi}
        locale="en-IN"
        localizationPolicy="development-preview"
      />,
    )

    expect(await screen.findByRole('link', { name: 'Learners' })).toHaveAttribute(
      'href',
      '#operator-learners',
    )
    expect(await screen.findByRole('heading', { name: 'Learner directory' }))
      .toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'Learner invitations' }))
      .not.toBeInTheDocument()
    expect(listLearners).toHaveBeenCalledOnce()
    expect(listInvitations).not.toHaveBeenCalled()
  })

  it('renders the Subscriptions workspace from the overview manifest', async () => {
    const workspace = vi.fn(async () => ({
      tax: {
        gst_registered: true,
        gstin: '29AAAAA0000A1Z5',
        gst_rate_bps: 1800,
        gstin_is_placeholder: true,
        version: 1,
      },
      plans: [],
      courtesy_premium: { granted_memberships: 0 },
    }))
    const api = operatorApi({
      getOverview: vi.fn(async () => ({
        generated_at: '2026-08-09T10:00:00+00:00',
        navigation: ['overview', 'entitlements'] as const,
        organization: organization(),
        panels: ['entitlement_plans'] as const,
        search_available: false,
      })),
    })

    render(
      <OperatorConsole
        api={api}
        entitlementApi={{ workspace } as unknown as OperatorEntitlementApi}
        locale="en-IN"
        localizationPolicy="development-preview"
      />,
    )

    expect(
      await screen.findByRole('link', { name: 'Subscriptions' }),
    ).toHaveAttribute('href', '#operator-entitlements')
    expect(
      await screen.findByRole('heading', { name: 'Subscriptions' }),
    ).toBeInTheDocument()
    expect(workspace).toHaveBeenCalledOnce()
  })

  it('renders the manifest People workspace and suppresses unavailable global search', async () => {
    const search = vi.fn()
    const api = operatorApi({
      getOverview: vi.fn(async () => ({
        generated_at: '2026-08-09T10:00:00+00:00',
        navigation: ['overview', 'people'] as const,
        organization: organization(),
        panels: ['branches'] as const,
        search_available: false,
      })),
      search,
    })
    const listBranches = vi.fn(async () => ({
      allowed_actions: [],
      data: [],
      next_cursor: null,
    }))

    render(
      <OperatorConsole
        api={api}
        currentMembershipId="01J00000000000000000000009"
        locale="en-IN"
        localizationPolicy="development-preview"
        organizationApi={{ listBranches } as unknown as OperatorOrganizationApi}
      />,
    )

    expect(await screen.findByRole('link', { name: 'People' })).toHaveAttribute(
      'href',
      '#operator-people',
    )
    expect(
      await screen.findByRole('heading', {
        name: 'Organization and staff administration',
      }),
    ).toBeInTheDocument()
    expect(await screen.findByRole('heading', { name: 'Branches' }))
      .toBeInTheDocument()
    expect(screen.queryByRole('search')).not.toBeInTheDocument()
    expect(search).not.toHaveBeenCalled()
    expect(listBranches).toHaveBeenCalledOnce()
  })

  it('renders only the task navigation and panels authorized by overview', async () => {
    const listDrafts = vi.fn(async () => [])
    const listPublications = vi.fn(async () => [])
    const listAuditEvents = vi.fn(async () => ({
      items: [],
      next_cursor: null,
    }))
    const getPilotAnalytics = vi.fn(async () => pilotAnalytics())
    const listSupportDiagnostics = vi.fn(async () => ({
      generated_at: '2026-08-09T10:00:00+00:00',
      items: [
        {
          program_id: '01J00000000000000000000001',
          program_key: 'synthetic-foundation-preview',
          program_internal_name: 'Synthetic Foundation Preview',
          curriculum_version_id: '01J00000000000000000000002',
          version_key: 'draft-1',
          publication_status: 'unpublished' as const,
          head_version: 0,
          head_publication_id: null,
          head_manifest_hash: null,
          head_publication_number: null,
          latest_english_decision: null,
          latest_kannada_decision: null,
        },
      ]),
      listPublications: vi.fn(async () => [
        {
          publication_id: '01J00000000000000000000003',
          program_id: '01J00000000000000000000001',
          program_key: 'synthetic-foundation-preview',
          curriculum_version_id: '01J00000000000000000000002',
          version_key: 'draft-1',
          publication_number: 1,
          manifest_hash: 'a'.repeat(64),
          is_head: true,
          head_version: 1,
          created_at: '2026-08-07T10:00:00+00:00',
        },
      ]),
      listAuditEvents: vi.fn(async () => [
        {
          id: '01J00000000000000000000004',
          action: 'curriculum.publication.activated',
          subject_type: 'curriculum_publication',
          subject_id: '01J00000000000000000000003',
          actor_membership_id: '01J00000000000000000000005',
          correlation_id: '11111111-1111-4111-8111-111111111111',
          occurred_at: '2026-08-07T10:00:00+00:00',
        },
      ]),
      getPilotAnalytics: vi.fn(async () => ({
        generated_at: '2026-08-07T10:00:00+00:00',
        usage: {
          learner_membership_count: 2,
          conversation_session_count: 3,
          conversation_sessions_by_status: { active: 1, ended: 2 },
          formative_attempt_count: 4,
          formative_attempts_by_status: { submitted: 4 },
        },
        demonstrated_learning: {
          mastery_state_count: 5,
          mastery_by_knowledge_state: { mastered: 2, emerging: 3 },
          review_schedule_count: 1,
        },
        distinction_note:
          'usage_counts_are_not_demonstrated_learning' as const,
      })),
    }

    render(
      <OperatorConsole
        api={api}
        locale="en-IN"
        localizationPolicy="development-preview"
      />,
    )

    expect(
      await screen.findByRole('heading', {
        name: 'Operator content workflow',
      }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('heading', { name: 'Pilot analytics' }),
    ).toBeInTheDocument()
    expect(screen.getByText(/learners=2/)).toBeInTheDocument()
    expect(
      screen.getByRole('heading', { name: 'Review queue' }),
    ).toBeInTheDocument()
    expect(screen.getByText(/unpublished/)).toBeInTheDocument()
    expect(screen.getByText(/EN pending/)).toBeInTheDocument()
    expect(
      screen.getByRole('heading', { name: 'Publish status' }),
    ).toBeInTheDocument()
    expect(screen.getByText(/#1 · head/)).toBeInTheDocument()
    expect(
      screen.getByRole('heading', { name: 'Audit trail' }),
    ).toBeInTheDocument()
    expect(
      screen.getByText('curriculum.publication.activated'),
    ).toBeInTheDocument()
    expect(api.listDrafts).toHaveBeenCalledTimes(1)
    expect(api.listPublications).toHaveBeenCalledTimes(1)
    expect(api.listAuditEvents).toHaveBeenCalledTimes(1)
    expect(api.getPilotAnalytics).toHaveBeenCalledTimes(1)
  })
})
