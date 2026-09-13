import {
  cleanup,
  render,
  screen,
  waitFor,
} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
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
  window.history.replaceState(null, '', '/')
})

function openOperatorSection(hash: string) {
  window.history.replaceState(null, '', hash.startsWith('#') ? hash : `#${hash}`)
}

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

    openOperatorSection('#operator-learning')

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

    openOperatorSection('#operator-learning')

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
    await waitFor(() => {
      expect(listWorkspaces).toHaveBeenCalledOnce()
      expect(listReviewQueue).toHaveBeenCalledOnce()
      expect(listPublications).toHaveBeenCalledOnce()
    })
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

    openOperatorSection('#operator-learners')

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

    openOperatorSection('#operator-entitlements')

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
      screen.getAllByRole('heading', { name: 'Subscriptions' }).length,
    ).toBeGreaterThan(0)
    await waitFor(() => expect(workspace).toHaveBeenCalledOnce())
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

    openOperatorSection('#operator-people')

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
          kind: 'stuck_in_progress' as const,
          subject_type: 'learner_attempt' as const,
          subject_id: '01J00000000000000000000006',
          status: 'in_progress',
          observed_at: '2026-08-09T09:00:00+00:00',
        },
      ],
    }))
    const api = operatorApi({
      getOverview: vi.fn(async () => ({
        generated_at: '2026-08-09T10:00:00+00:00',
        navigation: ['overview', 'operations'] as const,
        organization: organization(),
        panels: ['support_diagnostics'] as const,
        search_available: true,
      })),
      getPilotAnalytics,
      listAuditEvents,
      listDrafts,
      listPublications,
      listSupportDiagnostics,
    })

    renderConsole(api)

    expect(
      await screen.findByRole('heading', {
        name: 'Organization signals',
      }),
    ).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Overview' })).toBeInTheDocument()
    expect(
      screen.getByRole('link', { name: 'Operations' }),
    ).toBeInTheDocument()
    expect(
      screen.queryByRole('link', { name: 'Learning' }),
    ).not.toBeInTheDocument()
    expect(
      screen.queryByRole('link', { name: 'Safety' }),
    ).not.toBeInTheDocument()

    openOperatorSection('#operator-operations')
    window.dispatchEvent(new HashChangeEvent('hashchange'))

    expect(
      await screen.findByRole('heading', { name: 'Support diagnostics' }),
    ).toBeInTheDocument()
    expect(await screen.findByText('stuck_in_progress')).toBeInTheDocument()
    expect(listSupportDiagnostics).toHaveBeenCalledOnce()
    expect(listDrafts).not.toHaveBeenCalled()
    expect(listPublications).not.toHaveBeenCalled()
    expect(listAuditEvents).not.toHaveBeenCalled()
    expect(getPilotAnalytics).not.toHaveBeenCalled()
  })

  it('renders Speech Economics only when the overview panel is authorized', async () => {
    const getSpeechEconomics = vi.fn(async () => speechEconomics())
    const exportSpeechEconomics = vi.fn(async () => undefined)
    const getSpeechEconomicsCommercial = vi.fn(async () => null)
    const api = operatorApi({
      getOverview: vi.fn(async () => ({
        generated_at: '2026-09-09T10:00:00+00:00',
        navigation: ['overview', 'operations'] as const,
        organization: organization(),
        panels: ['speech_economics'] as const,
        search_available: false,
      })),
      exportSpeechEconomics,
      getSpeechEconomics,
      getSpeechEconomicsCommercial,
    })

    renderConsole(api)
    openOperatorSection('#operator-operations')
    window.dispatchEvent(new HashChangeEvent('hashchange'))

    expect(
      await screen.findByRole('heading', { name: 'Speech Economics' }),
    ).toBeInTheDocument()
    expect(await screen.findByText('azure-speech')).toBeInTheDocument()
    expect(
      screen.getByText(
        'Some rows are hidden because their sample is too small to display safely.',
      ),
    ).toBeInTheDocument()
    expect(
      screen.getByText(
        'Allocated cost is not yet reconciled to a provider statement.',
      ),
    ).toBeInTheDocument()
    expect(getSpeechEconomics).toHaveBeenCalledOnce()
    expect(getSpeechEconomicsCommercial).toHaveBeenCalledOnce()
    expect(
      screen.queryByRole('heading', { name: 'Commercial terms' }),
    ).not.toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: 'Export CSV' }))
    expect(exportSpeechEconomics).toHaveBeenCalledOnce()
  })

  it('keeps successful panels available while one panel fails and retries', async () => {
    const draft: OperatorContentDraft = {
      curriculum_version_id: '01J00000000000000000000002',
      head_manifest_hash: null,
      head_publication_id: null,
      head_publication_number: null,
      head_version: 0,
      latest_english_decision: null,
      latest_kannada_decision: null,
      program_id: '01J00000000000000000000001',
      program_internal_name: 'Synthetic Foundation Preview',
      program_key: 'synthetic-foundation-preview',
      publication_status: 'unpublished',
      version_key: 'draft-1',
    }
    const listDrafts = vi
      .fn()
      .mockRejectedValueOnce(new Error('temporary'))
      .mockResolvedValue([draft])
    const api = operatorApi({
      getOverview: vi.fn(async () => ({
        generated_at: '2026-08-09T10:00:00+00:00',
        navigation: [
          'overview',
          'learning',
          'operations',
          'safety',
        ] as const,
        organization: organization(),
        panels: [
          'pilot_analytics',
          'review_queue',
          'publish_status',
          'support_diagnostics',
          'audit',
        ] as const,
        search_available: true,
      })),
      listDrafts,
      listPublications: vi.fn(async () => [
        {
          created_at: '2026-08-09T10:00:00+00:00',
          curriculum_version_id: draft.curriculum_version_id,
          head_version: 1,
          is_head: true,
          manifest_hash: 'a'.repeat(64),
          program_id: draft.program_id,
          program_key: draft.program_key,
          publication_id: '01J00000000000000000000003',
          publication_number: 1,
          version_key: draft.version_key,
        },
      ]),
    })

    openOperatorSection('#operator-learning')

    renderConsole(api)

    expect(
      await screen.findByRole('heading', { name: 'Publish status' }),
    ).toBeInTheDocument()
    expect(await screen.findByText('#1 · head')).toBeInTheDocument()
    expect(
      await screen.findByText(
        'This panel could not be loaded. Other operator tasks remain available.',
      ),
    ).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: 'Try again' }))

    expect(
      await screen.findByText('Synthetic Foundation Preview'),
    ).toBeInTheDocument()
    expect(listDrafts).toHaveBeenCalledTimes(2)
    expect(screen.getByText('#1 · head')).toBeInTheDocument()
  })

  it('debounces bounded search, ignores short input, and appends a cursor page', async () => {
    const search = vi
      .fn()
      .mockResolvedValueOnce({
        generated_at: '2026-08-09T10:00:00+00:00',
        items: [
          {
            id: '01J00000000000000000000002',
            kind: 'curriculum_version' as const,
            occurred_at: null,
            primary_text: 'Synthetic Foundation Preview',
            secondary_text: 'synthetic-foundation-preview / draft-1',
            section: 'learning' as const,
          },
        ],
        next_cursor: 'encrypted-next',
      })
      .mockResolvedValueOnce({
        generated_at: '2026-08-09T10:00:01+00:00',
        items: [
          {
            id: '01J00000000000000000000003',
            kind: 'curriculum_publication' as const,
            occurred_at: '2026-08-09T10:00:00+00:00',
            primary_text: 'Synthetic Foundation Preview',
            secondary_text: 'synthetic-foundation-preview / draft-1 · #1',
            section: 'learning' as const,
          },
        ],
        next_cursor: null,
      })
    const api = operatorApi({ search })
    const user = userEvent.setup()

    renderConsole(api)
    const input = await screen.findByRole('searchbox', {
      name: 'Search this organization',
    })

    await user.type(input, 'x')
    await new Promise((resolve) => globalThis.setTimeout(resolve, 350))
    expect(search).not.toHaveBeenCalled()

    await user.type(input, 'y')
    await user.click(screen.getByRole('button', { name: 'Search' }))

    expect(
      await screen.findByText('synthetic-foundation-preview / draft-1'),
    ).toBeInTheDocument()
    expect(search).toHaveBeenCalledWith(
      'xy',
      undefined,
      expect.any(AbortSignal),
    )
    await new Promise((resolve) => globalThis.setTimeout(resolve, 350))
    expect(search).toHaveBeenCalledOnce()

    await user.click(screen.getByRole('button', { name: 'Load more' }))

    expect(
      await screen.findByText(
        'synthetic-foundation-preview / draft-1 · #1',
      ),
    ).toBeInTheDocument()
    expect(search).toHaveBeenLastCalledWith(
      'xy',
      'encrypted-next',
      expect.any(AbortSignal),
    )
    expect(screen.getByText('Results shown').closest('p')).toHaveTextContent(
      'Results shown: 2',
    )
  })

  it('opens safe audit detail and appends cursor-paged events', async () => {
    const listAuditEvents = vi
      .fn()
      .mockResolvedValueOnce({
        items: [auditEvent('01J00000000000000000000004', 'activated')],
        next_cursor: 'audit-next',
      })
      .mockResolvedValueOnce({
        items: [auditEvent('01J00000000000000000000007', 'reviewed')],
        next_cursor: null,
      })
    const api = operatorApi({
      getOverview: vi.fn(async () => ({
        generated_at: '2026-08-09T10:00:00+00:00',
        navigation: ['overview', 'safety'] as const,
        organization: organization(),
        panels: ['audit'] as const,
        search_available: true,
      })),
      listAuditEvents,
    })
    const user = userEvent.setup()

    openOperatorSection('#operator-safety')

    renderConsole(api)

    await user.click(
      await screen.findByRole('button', {
        name: /curriculum\.publication\.activated/u,
      }),
    )

    expect(screen.getByText('Audit event detail')).toBeInTheDocument()
    expect(
      screen.getByText('11111111-1111-4111-8111-111111111111'),
    ).toBeInTheDocument()
    expect(
      screen.getByText('01J00000000000000000000005'),
    ).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Load more' }))

    expect(
      await screen.findByRole('button', {
        name: /curriculum\.content_review\.recorded/u,
      }),
    ).toBeInTheDocument()
    expect(listAuditEvents).toHaveBeenLastCalledWith(
      'audit-next',
      expect.any(AbortSignal),
    )
  })

  it('retries the hidden-denial overview without starting panel reads', async () => {
    const getOverview = vi
      .fn()
      .mockRejectedValueOnce(new Error('not available'))
      .mockResolvedValueOnce({
        generated_at: '2026-08-09T10:00:00+00:00',
        navigation: ['overview'] as const,
        organization: organization(),
        panels: [] as const,
        search_available: false,
      })
    const listDrafts = vi.fn(async () => [])
    const api = operatorApi({ getOverview, listDrafts })

    renderConsole(api)

    expect(
      await screen.findByText(
        'The operator content workflow is unavailable. Try again.',
      ),
    ).toBeInTheDocument()
    expect(listDrafts).not.toHaveBeenCalled()

    await userEvent.click(screen.getByRole('button', { name: 'Try again' }))

    expect(
      await screen.findByText('Hithaishi Learning Hub'),
    ).toBeInTheDocument()
    expect(getOverview).toHaveBeenCalledTimes(2)
    expect(listDrafts).not.toHaveBeenCalled()
  })
})

function renderConsole(api: OperatorConsoleApi) {
  return render(
    <OperatorConsole
      api={api}
      locale="en-IN"
      localizationPolicy="development-preview"
    />,
  )
}

function operatorApi(
  overrides: Partial<OperatorConsoleApi> = {},
): OperatorConsoleApi {
  return {
    getOverview: vi.fn(async () => ({
      generated_at: '2026-08-09T10:00:00+00:00',
      navigation: ['overview', 'learning'] as const,
      organization: organization(),
      panels: ['review_queue', 'publish_status'] as const,
      search_available: true,
    })),
    getPilotAnalytics: vi.fn(async () => pilotAnalytics()),
    listAuditEvents: vi.fn(async () => ({
      items: [],
      next_cursor: null,
    })),
    listDrafts: vi.fn(async () => []),
    listPublications: vi.fn(async () => []),
    listSupportDiagnostics: vi.fn(async () => ({
      generated_at: '2026-08-09T10:00:00+00:00',
      items: [],
    })),
    getSpeechEconomics: vi.fn(async () => speechEconomics()),
    exportSpeechEconomics: vi.fn(async () => undefined),
    getSpeechEconomicsCommercial: vi.fn(async () => null),
    search: vi.fn(async () => ({
      generated_at: '2026-08-09T10:00:00+00:00',
      items: [],
      next_cursor: null,
    })),
    ...overrides,
  }
}

function organization() {
  return {
    id: '01J00000000000000000000003',
    name: 'Hithaishi Learning Hub',
    timezone: 'Asia/Kolkata',
  }
}

function speechEconomics() {
  return {
    empty: false,
    generated_at: '2026-09-09T10:00:00+00:00',
    organization_id: '01J00000000000000000000003',
    partial: true,
    presentation: 'allocated_usage' as const,
    rows: [
      {
        aggregate_kind: 'provider_attempt',
        allocated_inr_microunits: 12_000,
        attempt_count: 12,
        billable_unit_kind: 'unicode_characters',
        billable_unit_quantity: 1200,
        cancelled_count: 0,
        completion_p95_ms: 1000,
        conversation_mode: 'everyday',
        cost_per_successful_turn_inr_microunits: 1000,
        dispatch_count: 12,
        dispatch_kind: 'provider_dispatch',
        environment: 'soft-launch',
        failure_count: 0,
        fallback_count: 0,
        first_audible_p95_ms: 1000,
        first_result_p50_ms: 1000,
        first_result_p95_ms: 1000,
        locale: 'en-IN',
        model_version: 'test-v1',
        partial_count: 0,
        provider_key: 'azure-speech',
        provider_stage: 'tts',
        reconciliation_status: 'unreconciled',
        result_status: 'succeeded',
        route_role: 'primary',
        small_cell_suppressed: false,
        success_count: 12,
        usage_date: '2026-09-08',
      },
    ],
    stale_rate: false,
    suppressed_row_count: 1,
    totals: {
      allocated_inr_microunits: 12_000,
      attempt_count: 12,
      billable_unit_quantity: 1200,
      cancelled_count: 0,
      dispatch_count: 12,
      failure_count: 0,
      fallback_count: 0,
      partial_count: 0,
      success_count: 12,
    },
    unreconciled: true,
    visible_row_count: 1,
  }
}

function pilotAnalytics() {
  return {
    demonstrated_learning: {
      mastery_by_knowledge_state: { emerging: 3, mastered: 2 },
      mastery_state_count: 5,
      review_schedule_count: 1,
    },
    learning_metrics: {
      ai_fallback_count: 0,
      latency_sample_count: 0,
      quiz_retry_count: 1,
      review_return_completed_count: 2,
      review_return_due_count: 0,
      session_cost_microunits_total: 0,
      speaking_turn_count: 6,
      stage_completion_count: 4,
    },
    distinction_note: 'usage_counts_are_not_demonstrated_learning' as const,
    generated_at: '2026-08-09T10:00:00+00:00',
    usage: {
      conversation_session_count: 3,
      conversation_sessions_by_status: { active: 1, ended: 2 },
      formative_attempt_count: 4,
      formative_attempts_by_status: { submitted: 4 },
      learner_membership_count: 2,
    },
  }
}

function auditEvent(id: string, kind: 'activated' | 'reviewed') {
  return {
    action:
      kind === 'activated'
        ? 'curriculum.publication.activated'
        : 'curriculum.content_review.recorded',
    actor_membership_id: '01J00000000000000000000005',
    correlation_id: '11111111-1111-4111-8111-111111111111',
    id,
    occurred_at: '2026-08-09T10:00:00+00:00',
    subject_id: '01J00000000000000000000003',
    subject_type:
      kind === 'activated'
        ? 'curriculum_publication'
        : 'content_review',
  }
}
