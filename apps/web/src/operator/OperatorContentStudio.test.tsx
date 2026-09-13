import { cleanup, render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import type {
  OperatorContentReviewQueueListing,
  OperatorContentWorkspaceDetail,
} from '@global-english/contracts'
import { OperatorContentStudio } from './OperatorContentStudio'
import {
  OperatorContentApiError,
  type OperatorContentStudioApi,
} from './OperatorContentStudioApi'

afterEach(() => {
  cleanup()
  vi.restoreAllMocks()
})

describe('operator Content Studio', () => {
  it('keeps workspace, preview and review resources usable when publications fail', async () => {
    const api = contentApi({
      listPublications: vi.fn(async () => {
        throw new Error('publication read unavailable')
      }),
    })

    renderStudio(api)

    expect(await screen.findByRole('heading', { name: 'Content Studio' }))
      .toBeInTheDocument()
    expect(await screen.findByText('Synthetic Foundation')).toBeInTheDocument()
    expect((await screen.findAllByText('Hello, learner.')).length).toBeGreaterThan(0)
    expect(await screen.findByRole('heading', { name: 'Independent review queues' }))
      .toBeInTheDocument()
    expect(await screen.findByText(
      'This Content Studio resource is unavailable. Other resources remain usable.',
    )).toBeInTheDocument()
  })

  it('authors a typed graph through structured controls without exposing a JSON editor', async () => {
    const saveGraph = vi.fn(async () => ({
      replayed: false,
      result_id: ids.draft,
      result_type: 'curriculum_draft_graph_revision' as const,
    }))
    const api = contentApi({ saveGraph })
    const user = userEvent.setup()

    renderStudio(api)
    await screen.findByText('Synthetic Foundation')
    await user.click(await screen.findByText('Structured curriculum graph'))
    const levels = screen.getByRole('group', { name: 'Levels' })
    await user.click(within(levels).getByRole('button', { name: 'Add row' }))
    await user.type(within(levels).getByLabelText('Stable key'), 'a1')
    expect(screen.queryByLabelText(/json/iu)).not.toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Save graph revision' }))

    expect(saveGraph).toHaveBeenCalledWith(
      ids.version,
      expect.objectContaining({
        expected_workspace_version: 1,
        graph: expect.objectContaining({
          levels: [{ key: 'a1', sequence: 1 }],
          schema: 'global-english.curriculum-draft-graph.v2',
        }),
      }),
      '11111111-2222-4333-8444-555555555555',
    )
    expect(await screen.findByText('The governed content change was recorded.'))
      .toBeInTheDocument()
  })

  it('reloads stale authority without silently discarding unsaved graph values', async () => {
    const saveGraph = vi.fn(async () => {
      throw new OperatorContentApiError('stale_version')
    })
    const getWorkspace = vi.fn(async () => workspace())
    const api = contentApi({ getWorkspace, saveGraph })
    const user = userEvent.setup()

    renderStudio(api)
    await screen.findByText('Synthetic Foundation')
    await user.click(await screen.findByText('Structured curriculum graph'))
    const levels = screen.getByRole('group', { name: 'Levels' })
    await user.click(within(levels).getByRole('button', { name: 'Add row' }))
    const key = within(levels).getByLabelText('Stable key')
    await user.type(key, 'unsaved-a1')
    await user.click(screen.getByRole('button', { name: 'Save graph revision' }))

    expect(await screen.findByText(/authoritative state changed/iu)).toBeInTheDocument()
    expect(key).toHaveValue('unsaved-a1')
    expect(getWorkspace).toHaveBeenCalledTimes(2)
  })

  it('exposes daily speaking missions as governed fields instead of raw JSON', async () => {
    const api = contentApi({
      getWorkspace: vi.fn(async () => ({
        ...workspace(),
        allowed_actions: ['create_document' as const],
      })),
    })
    const user = userEvent.setup()

    renderStudio(api)
    const creators = await screen.findAllByText('Create governed document')
    await user.click(creators[0]!)
    await user.selectOptions(
      screen.getByLabelText('Governed payload type'),
      'global-english.daily-speaking-mission.v1',
    )

    expect(screen.getByRole('group', { name: 'Daily speaking mission fields' }))
      .toBeInTheDocument()
    expect(screen.getByLabelText('Learner goal')).toBeInTheDocument()
    expect(screen.getByLabelText('Model English')).toBeInTheDocument()
    expect(screen.getByRole('group', { name: 'Multiple-choice check options' }))
      .toBeInTheDocument()
    expect(screen.queryByLabelText(/json/iu)).not.toBeInTheDocument()
  })

  it('schedules the exact reviewed workspace head after confirmation', async () => {
    const schedulePublication = vi.fn(async () => ({
      head_version: 8,
      replayed: false,
    }))
    const api = contentApi({
      getWorkspace: vi.fn(async () => ({
        ...workspace(),
        allowed_actions: ['schedule' as const],
        publication_head_version: 7,
      })),
      schedulePublication,
    })
    const user = userEvent.setup()

    renderStudio(api)
    await user.click(await screen.findByText('Schedule publication'))
    const activation = '2026-08-10T12:30'
    await user.type(screen.getByLabelText('Activation date and time'), activation)
    await user.click(screen.getByRole('button', { name: 'Confirm publication schedule' }))

    expect(schedulePublication).toHaveBeenCalledWith({
      activation_at: new Date(activation).toISOString(),
      curriculum_version_id: ids.version,
      expected_head_version: 7,
    }, '11111111-2222-4333-8444-555555555555')
  })

  it('records an exact structure approval using a server-provided appointment', async () => {
    const reviewStructure = vi.fn(async () => ({
      curriculum_graph_review_id: ids.review,
    }))
    const api = contentApi({
      listReviewQueue: vi.fn(async () => ({
        allowed_actions: [],
        data: [{
          allowed_actions: ['review_structure'],
          appointment_choices: [{
            authority_kind: 'english_academic_reviewer',
            id: ids.appointment,
          }],
          curriculum_version_id: ids.version,
          graph_hash: 'b'.repeat(64),
          graph_revision_id: ids.graph,
          graph_revision_number: 1,
          latest_decision: null,
          program_id: ids.program,
          program_key: 'synthetic-foundation',
          version_key: 'draft-1',
        }],
        next_cursor: null,
      }) as OperatorContentReviewQueueListing),
      reviewStructure,
    })
    const user = userEvent.setup()

    renderStudio(api)
    await user.click(await screen.findByRole('button', { name: 'Approve exact head' }))
    await user.click(screen.getByRole('button', { name: 'Confirm review decision' }))

    expect(reviewStructure).toHaveBeenCalledWith({
      authority_appointment_id: ids.appointment,
      curriculum_graph_revision_id: ids.graph,
      decision: 'approved',
      graph_hash: 'b'.repeat(64),
      rationale_key: 'meets_standard',
    }, '11111111-2222-4333-8444-555555555555')
  })
})

const ids = {
  appointment: '01J00000000000000000000007',
  document: '01J00000000000000000000003',
  draft: '01J00000000000000000000006',
  graph: '01J00000000000000000000008',
  membership: '01J00000000000000000000004',
  program: '01J00000000000000000000001',
  review: '01J00000000000000000000009',
  revision: '01J00000000000000000000005',
  version: '01J00000000000000000000002',
} as const

function workspace(): OperatorContentWorkspaceDetail {
  return {
    active_publication_id: null,
    allowed_actions: ['edit_graph', 'create_document', 'translate'],
    appointment_choices: { english: [], kannada: [] },
    current_draft_revision_id: ids.draft,
    curriculum_version_id: ids.version,
    descriptor_version: 1,
    document_count: 1,
    documents: [{
      content_document_id: ids.document,
      content_type: 'instruction' as const,
      document_key: 'greeting-model',
      learner_visible: true,
      localization_requirement: 'kn_required' as const,
      payload_schema: 'global-english.governed-text.v1',
      source_content_hash: 'a'.repeat(64),
      source_revision_id: ids.revision,
      source_revision_number: 1,
    }],
    documents_truncated: false,
    graph: {
      authored_at: '2026-08-09T10:00:00Z',
      authored_by_membership_id: ids.membership,
      blocker_codes: ['workspace_incomplete'],
      graph_hash: 'b'.repeat(64),
      is_complete: false,
      revision_id: ids.draft,
      revision_number: 1,
      value: {
        curriculum_version_id: ids.version,
        delivery_bundles: [],
        items: [],
        lessons: [],
        levels: [],
        modules: [],
        objectives: [],
        organization_id: '01J00000000000000000000000',
        schema: 'global-english.curriculum-draft-graph.v2' as const,
      },
    },
    program_id: ids.program,
    program_internal_name: 'Synthetic Foundation',
    program_key: 'synthetic-foundation',
    publication_head_version: 0,
    publication_suspended: false,
    readiness: {
      blocker_codes: ['workspace_incomplete'],
      ready: false,
    },
    source_curriculum_publication_id: null,
    source_curriculum_version_id: null,
    state: 'draft' as const,
    submitted_graph_revision_id: null,
    version_key: 'draft-1',
    workspace_version: 1,
  }
}

function contentApi(
  overrides: Partial<OperatorContentStudioApi> = {},
): OperatorContentStudioApi {
  return {
    getWorkspace: vi.fn(async () => workspace()),
    listPublications: vi.fn(async () => ({
      allowed_actions: [],
      items: [],
      next_cursor: null,
    })),
    listHistory: vi.fn(async () => ({
      data: [],
      next_cursor: null,
    })),
    listReviewQueue: vi.fn(async () => ({
      allowed_actions: [],
      data: [],
      next_cursor: null,
    })),
    listWorkspaces: vi.fn(async () => ({
      allowed_actions: ['create_program'],
      data: [{
        active_publication_id: null,
        allowed_actions: ['edit_graph'],
        blocker_codes: ['workspace_incomplete'],
        current_draft_revision_id: ids.draft,
        curriculum_version_id: ids.version,
        descriptor_version: 1,
        graph_hash: 'b'.repeat(64),
        is_complete: false,
        program_id: ids.program,
        program_internal_name: 'Synthetic Foundation',
        program_key: 'synthetic-foundation',
        publication_head_version: 0,
        publication_suspended: false,
        source_curriculum_publication_id: null,
        source_curriculum_version_id: null,
        state: 'draft',
        submitted_graph_revision_id: null,
        version_key: 'draft-1',
        workspace_version: 1,
      }],
      next_cursor: null,
    })),
    previewDocument: vi.fn(async () => ({
      content_document_id: ids.document,
      content_type: 'instruction',
      document_key: 'greeting-model',
      english: {
        content_hash: 'a'.repeat(64),
        locale: 'en-IN',
        payload: {
          schema: 'global-english.governed-text.v1',
          text: 'Hello, learner.',
        },
        revision_id: ids.revision,
        revision_number: 1,
      },
      kannada: null,
      localization_requirement: 'kn_required',
      media: [],
      read_only: false,
    })),
    saveGraph: vi.fn(),
    ...overrides,
  } as unknown as OperatorContentStudioApi
}

function renderStudio(api: OperatorContentStudioApi) {
  return render(
    <OperatorContentStudio
      api={api}
      createCommandId={() => '11111111-2222-4333-8444-555555555555'}
      locale="en-IN"
      localizationPolicy="development-preview"
    />,
  )
}
