import { cleanup, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { AiMemoryPanel } from './AiMemoryPanel'
import type { ConversationJourneyApi } from './ConversationJourneyApi'

afterEach(() => {
  cleanup()
  vi.restoreAllMocks()
})

describe('AI memory panel', () => {
  it('lists creates and deletes learner-visible facts', async () => {
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
    const api: ConversationJourneyApi = {
      startSession: vi.fn(),
      createTurn: vi.fn(),
      events: vi.fn(),
      listAiMemory: vi.fn(async () => ({ facts })),
      createAiMemoryFact: vi.fn(async () => ({
        ...facts[0],
        deleted_at: null,
        replayed: false,
      })),
      updateAiMemoryFact: vi.fn(),
      deleteAiMemoryFact: vi.fn(async () => ({
        ...facts[0],
        status: 'deleted' as const,
        deleted_at: '2026-08-06T01:05:00Z',
        replayed: false,
      })),
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
          purpose: 'conversation_context',
          source: 'learner_explicit',
          status: 'active',
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

    api.listAiMemory = vi.fn(async () => ({ facts: [] }))
    await user.click(
      screen.getAllByRole('button', {
        name: 'conversation.memory.delete',
      })[0]!,
    )
    expect(api.deleteAiMemoryFact).toHaveBeenCalled()
  })
})
