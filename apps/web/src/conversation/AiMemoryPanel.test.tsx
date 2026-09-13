import { cleanup, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { AiMemoryPanel } from './AiMemoryPanel'
import type { ConversationJourneyApi } from './ConversationJourneyApi'
import { WebConversationJourneyApi } from './WebConversationJourneyApi'

afterEach(() => {
  cleanup()
  vi.restoreAllMocks()
})

describe('AI memory panel', () => {
  it('preserves the web API receiver while loading facts', async () => {
    const fetch = vi
      .fn<typeof globalThis.fetch>()
      .mockResolvedValue(Response.json({ facts: [] }))
    const api = new WebConversationJourneyApi({ fetch })

    render(
      <AiMemoryPanel
        api={api}
        createClientId={() => 'memory-command'}
        locale="en-IN"
        localizationPolicy="development-preview"
        text={(key) => key}
      />,
    )

    expect(
      await screen.findByText('No saved AI memory facts yet.'),
    ).toBeInTheDocument()
    expect(fetch).toHaveBeenCalledWith(
      '/api/v1/me/ai-memory',
      expect.objectContaining({
        credentials: 'include',
        method: 'GET',
      }),
    )
  })

  it('lists creates edits and deletes learner-visible facts', async () => {
    const facts = [
      {
        created_at: '2026-08-06T01:00:00Z',
        fact_text: 'I practise English after work.',
        id: '01J00000000000000000000021',
        purpose: 'conversation_context' as const,
        source: 'learner_explicit' as const,
        status: 'active' as const,
        updated_at: '2026-08-06T01:00:00Z',
      },
    ]
    const createdFact = {
      ...facts[0]!,
      deleted_at: null as null,
      replayed: false,
    }
    const updatedFact = {
      ...facts[0]!,
      fact_text: 'I practise English before meetings.',
      updated_at: '2026-08-06T01:04:00Z',
      deleted_at: null as null,
      replayed: false,
    }
    const deletedFact = {
      ...facts[0]!,
      status: 'deleted' as const,
      deleted_at: '2026-08-06T01:05:00Z',
      replayed: false,
    }
    const api: ConversationJourneyApi = {
      startSession: vi.fn(),
      createTurn: vi.fn(),
      events: vi.fn(),
      listAiMemory: vi.fn(async () => ({ facts })),
      createAiMemoryFact: vi.fn(async () => createdFact),
      updateAiMemoryFact: vi.fn(async () => updatedFact),
      deleteAiMemoryFact: vi.fn(async () => deletedFact),
    }
    const user = userEvent.setup()

    render(
      <AiMemoryPanel
        api={api}
        createClientId={() => 'memory-command'}
        locale="en-IN"
        localizationPolicy="development-preview"
        text={(key) => key}
      />,
    )

    expect(
      await screen.findByText('I practise English after work.'),
    ).toBeInTheDocument()

    await user.type(
      screen.getByLabelText('Add a practice note the Friend may use'),
      'I want clearer articles.',
    )
    api.listAiMemory = vi.fn(async () => ({
      facts: [
        ...facts,
        {
          created_at: '2026-08-06T01:02:00Z',
          fact_text: 'I want clearer articles.',
          id: '01J00000000000000000000022',
          purpose: 'conversation_context' as const,
          source: 'learner_explicit' as const,
          status: 'active' as const,
          updated_at: '2026-08-06T01:02:00Z',
        },
      ],
    }))
    await user.click(
      screen.getByRole('button', { name: 'conversation.memory.add' }),
    )
    expect(api.createAiMemoryFact).toHaveBeenCalled()
    expect(
      await screen.findByText('I want clearer articles.'),
    ).toBeInTheDocument()

    await user.click(
      screen.getAllByRole('button', {
        name: 'conversation.memory.edit',
      })[0]!,
    )
    const editField = screen.getByLabelText('Edit this practice note')
    await user.clear(editField)
    await user.type(editField, 'I practise English before meetings.')
    api.listAiMemory = vi.fn(async () => ({
      facts: [
        {
          ...facts[0]!,
          fact_text: 'I practise English before meetings.',
          updated_at: '2026-08-06T01:04:00Z',
        },
        {
          created_at: '2026-08-06T01:02:00Z',
          fact_text: 'I want clearer articles.',
          id: '01J00000000000000000000022',
          purpose: 'conversation_context' as const,
          source: 'learner_explicit' as const,
          status: 'active' as const,
          updated_at: '2026-08-06T01:02:00Z',
        },
      ],
    }))
    await user.click(
      screen.getByRole('button', { name: 'conversation.memory.save' }),
    )
    expect(api.updateAiMemoryFact).toHaveBeenCalledWith(
      '01J00000000000000000000021',
      { fact_text: 'I practise English before meetings.' },
      'memory-command',
    )
    expect(
      await screen.findByText('I practise English before meetings.'),
    ).toBeInTheDocument()

    api.listAiMemory = vi.fn(async () => ({ facts: [] }))
    await user.click(
      screen.getAllByRole('button', {
        name: 'conversation.memory.delete',
      })[0]!,
    )
    expect(api.deleteAiMemoryFact).toHaveBeenCalled()
  })
})
