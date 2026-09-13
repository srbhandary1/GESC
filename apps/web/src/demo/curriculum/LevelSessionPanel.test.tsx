import { cleanup, render, screen, waitFor } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import type { ConversationJourneyApi } from '../../conversation/ConversationJourneyApi'
import type { LevelSessionsApi } from '../../learner/LevelSessionsApi'
import { resolveInterfaceMessage } from '../../localization/interfaceLocalization'
import { curriculumByLevelId } from './levelCurriculum'
import { LevelSessionPanel } from './LevelSessionPanel'

afterEach(() => {
  cleanup()
})

const roomTitle = resolveInterfaceMessage(
  'demo.phase1.daily.audio_title',
  'en-IN',
  'development-preview',
)

function conversationApi(): ConversationJourneyApi {
  return {
    startSession: vi.fn(async () => ({
      coaching_mode: 'gentle' as const,
      id: '01J00000000000000000000001',
      mode: 'friend' as const,
      stage: null,
      persona: {
        definition_hash: 'a'.repeat(64),
        disclosure: 'I am a deterministic AI prototype, not a human.',
        display_name: 'Patient Friend Prototype',
        id: '01J00000000000000000000002',
        key: 'patient-friend-prototype' as const,
        review_status: 'synthetic_unreviewed' as const,
        version: 1,
        version_id: '01J00000000000000000000003',
      },
      replayed: false,
      scenario: {
        definition_hash: 'b'.repeat(64),
        id: '01J00000000000000000000004',
        key: 'everyday-friend-prototype' as const,
        review_status: 'synthetic_unreviewed' as const,
        topic_code: 'everyday-conversation' as const,
        version: 1,
        version_id: '01J00000000000000000000005',
      },
      status: 'active' as const,
      ui_locale: 'en-IN' as const,
    })),
    createTurn: vi.fn(),
    events: vi.fn(async () => []),
  }
}

describe('LevelSessionPanel', () => {
  it('plays authored Pre-Level Be without the archive-gap notice', () => {
    render(
      <LevelSessionPanel
        locale="en-IN"
        localizationPolicy="development-preview"
        roomTitle={roomTitle}
        session={curriculumByLevelId('pre-1')!}
      />,
    )

    expect(screen.getByRole('heading', { name: 'Be' })).toBeVisible()
    expect(
      screen.getByText('I am a student. This is my book.'),
    ).toBeVisible()
    expect(
      screen.queryByText(/No authored listen-learn session/i),
    ).not.toBeInTheDocument()
  })

  it('falls back to demo conversation when the level API is down', async () => {
    const api: LevelSessionsApi = {
      listLevels: vi.fn(async () => {
        throw new Error('down')
      }),
      listSessions: vi.fn(async () => {
        throw new Error('down')
      }),
      sessionDetail: vi.fn(async () => {
        throw new Error('down')
      }),
      completeSession: vi.fn(async () => {
        throw new Error('down')
      }),
      submitCheck: vi.fn(async () => {
        throw new Error('down')
      }),
      startConversation: vi.fn(async () => {
        throw new Error('down')
      }),
    }
    const chat = conversationApi()

    render(
      <LevelSessionPanel
        conversationApi={chat}
        demoMode
        levelSessionsApi={api}
        locale="en-IN"
        localizationPolicy="development-preview"
        mode="ai-teacher"
        roomTitle={roomTitle}
        session={curriculumByLevelId('level-1')!}
      />,
    )

    expect(screen.getByRole('heading', { name: 'Can' })).toBeVisible()
    await waitFor(() => {
      expect(chat.startSession).toHaveBeenCalled()
    })
    expect(api.startConversation).toHaveBeenCalled()
  })
})
