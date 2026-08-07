import { cleanup, render, screen } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { OperatorConsole } from './OperatorConsole'
import type { OperatorConsoleApi } from './OperatorConsoleApi'

afterEach(() => {
  cleanup()
  vi.restoreAllMocks()
})

describe('operator console', () => {
  it('renders review queue, publish status, and audit trail', async () => {
    const api: OperatorConsoleApi = {
      listDrafts: vi.fn(async () => [
        {
          program_id: '01J00000000000000000000001',
          program_key: 'synthetic-foundation-preview',
          program_internal_name: 'Synthetic Foundation Preview',
          curriculum_version_id: '01J00000000000000000000002',
          version_key: 'draft-1',
          publication_status: 'unpublished',
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
  })
})
