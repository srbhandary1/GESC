import { act, cleanup, render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import type {
  LevelQuizDraft,
  OfflineLearningStore,
  OfflineProfileBinding,
  PendingLevelCheckpointCommand,
} from '../offline/OfflineLearningStore'
import { LEVEL_QUIZ_DRAFT_TTL_MILLISECONDS } from '../offline/OfflineLearningStore'
import { LevelPathPanel } from './LevelPathPanel'
import type {
  LevelCheckpointDetail,
  LevelCheckpointSubmissionResult,
  LevelSessionsApi,
  SessionPartId,
} from './LevelSessionsApi'

const offlineBinding: OfflineProfileBinding = {
  membershipId: '01J00000000000000000000041',
  organizationId: '01J00000000000000000000042',
  userId: '01J00000000000000000000043',
}

const checkpointDetail: LevelCheckpointDetail = {
  level_number: 1,
  concept_key: 'can',
  blueprint_id: '01TESTBLUEPRINT000000000001',
  blueprint_content_hash: 'a'.repeat(64),
  rule_key: 'level_checkpoint_v1',
  rule_hash: 'b'.repeat(64),
  pass_threshold: 0.8,
  max_attempts: 3,
  attempts_remaining: 3,
  items: [
    {
      id: 'can-cp-01',
      weight: 1,
      prompt: 'Which sentence uses can correctly?',
      options: ['I can swim.', 'I cans swim.', 'Can I swam?'],
    },
    {
      id: 'can-cp-02',
      weight: 1,
      prompt: 'Which sentence asks permission correctly?',
      options: ['Can I sit here?', 'I can to sit here?', 'Can I sat here?'],
    },
  ],
}

const completedSessionsList = {
  level_number: 1,
  concept_key: 'can',
  blueprint_id: '01TESTBLUEPRINT000000000001',
  blueprint_content_hash: 'a'.repeat(64),
  sessions: [
    {
      part_id: 'introduction' as const,
      part_order: 1,
      media_asset_id: '01TESTMEDIA0000000000000001',
      completed: true,
    },
    {
      part_id: 'practice' as const,
      part_order: 2,
      media_asset_id: '01TESTMEDIA0000000000000002',
      completed: true,
    },
    {
      part_id: 'conversation' as const,
      part_order: 3,
      media_asset_id: '01TESTMEDIA0000000000000003',
      completed: true,
    },
    {
      part_id: 'interaction' as const,
      part_order: 4,
      media_asset_id: '01TESTMEDIA0000000000000004',
      completed: true,
    },
  ],
}

const quickCheckItems = [
  {
    id: 'can-check-01',
    weight: 1,
    prompt: 'Choose the sentence that uses can correctly.',
    options: ['I cans swim.', 'I can swim.'],
  },
  {
    id: 'can-check-02',
    weight: 1,
    prompt: 'Choose the correct permission question.',
    options: ['Can I sit here?', 'Can I sat here?'],
  },
] as const

function apiWithQuickCheck(
  submitCheck: LevelSessionsApi['submitCheck'],
): LevelSessionsApi {
  return {
    listLevels: vi.fn(async () => ({
      levels: [
        {
          level_number: 1,
          concept_key: 'can',
          availability: 'available' as const,
          blueprint_id: '01TESTBLUEPRINT000000000001',
          session_part_ids: [
            'introduction',
            'practice',
            'conversation',
            'interaction',
          ],
        },
      ],
    })),
    listSessions: vi.fn(async () => ({
      ...completedSessionsList,
      sessions: completedSessionsList.sessions.map((session) => ({
        ...session,
        completed:
          session.part_id === 'introduction' ||
          session.part_id === 'practice',
      })),
    })),
    sessionDetail: vi.fn(async (_levelNumber: number, partId: SessionPartId) => ({
      level_number: 1,
      part_id: partId,
      part_order: partId === 'practice' ? 2 : 1,
      completed: partId === 'introduction',
      media_asset_id: '01TESTMEDIA0000000000000001',
      audio_stream_path: '/api/v1/me/levels/1/sessions/practice/audio',
      objective: 'Use can and cannot.',
      target_patterns: ['I can + base verb.'],
      kannada_guidance: { summary: 'ಕನ್ನಡ ವಿವರಣೆ' },
      talk: null,
      apply: null,
      check:
        partId === 'practice'
          ? {
              pass_threshold: 0.8,
              items: quickCheckItems.map((item) => ({
                ...item,
                options: [...item.options],
              })),
            }
          : null,
      activities: [],
      conversation_binding: null,
    })),
    completeSession: vi.fn(),
    submitCheck,
    startConversation: vi.fn(),
  }
}

beforeEach(() => {
  window.history.replaceState(null, '', '/#learn')
  Object.defineProperty(globalThis.navigator, 'onLine', {
    configurable: true,
    value: true,
  })
})

afterEach(() => {
  cleanup()
  vi.restoreAllMocks()
})

describe('LevelPathPanel', () => {
  it('opens plans from every entitlement-locked level with completed-level context', async () => {
    const user = userEvent.setup()
    const onOpenPlans = vi.fn()
    const api: LevelSessionsApi = {
      listLevels: vi.fn(async () => ({
        levels: [
          {
            level_number: 2,
            concept_key: 'should',
            availability: 'available' as const,
            entitlement_access: 'allowed' as const,
            checkpoint_status: 'passed' as const,
            blueprint_id: '01TESTBLUEPRINT000000000002',
            session_part_ids: ['introduction', 'practice'],
          },
          {
            level_number: 3,
            concept_key: 'do_does',
            availability: 'available' as const,
            entitlement_access: 'upgrade_required' as const,
            checkpoint_status: 'not_ready' as const,
            blueprint_id: '01TESTBLUEPRINT000000000003',
            session_part_ids: ['introduction', 'practice'],
          },
          {
            level_number: 4,
            concept_key: 'is_are_ing',
            availability: 'available' as const,
            entitlement_access: 'upgrade_required' as const,
            checkpoint_status: 'not_ready' as const,
            blueprint_id: '01TESTBLUEPRINT000000000004',
            session_part_ids: ['introduction', 'practice'],
          },
        ],
      })),
      listSessions: vi.fn(),
      sessionDetail: vi.fn(),
      completeSession: vi.fn(),
      submitCheck: vi.fn(),
      startConversation: vi.fn(),
    }

    render(
      <LevelPathPanel
        api={api}
        locale="en-IN"
        onOpenPlans={onOpenPlans}
      />,
    )

    await user.click(
      await screen.findByRole('button', { name: 'View plans for Level 3' }),
    )
    expect(onOpenPlans).toHaveBeenLastCalledWith({
      completedLevelNumber: 2,
      lockedLevelNumber: 3,
      returnTargetId: 'level-upgrade-3',
    })

    await user.click(
      screen.getByRole('button', { name: 'View plans for Level 4' }),
    )
    expect(onOpenPlans).toHaveBeenLastCalledWith({
      completedLevelNumber: 2,
      lockedLevelNumber: 4,
      returnTargetId: 'level-upgrade-4',
    })
  })

  it('lists ready levels separately and opens an available level session', async () => {
    const user = userEvent.setup()
    const api: LevelSessionsApi = {
      listLevels: vi.fn(async () => ({
        levels: [
          {
            level_number: 1,
            concept_key: 'can',
            availability: 'available' as const,
            blueprint_id: '01TESTBLUEPRINT000000000001',
            session_part_ids: [
              'introduction',
              'practice',
              'conversation',
              'interaction',
            ],
          },
          {
            level_number: 2,
            concept_key: null,
            availability: 'commissioning_pending' as const,
            blueprint_id: null,
            session_part_ids: [
              'introduction',
              'practice',
              'conversation',
              'interaction',
            ],
          },
        ],
      })),
      listSessions: vi.fn(async () => ({
        level_number: 1,
        concept_key: 'can',
        blueprint_id: '01TESTBLUEPRINT000000000001',
        blueprint_content_hash: 'a'.repeat(64),
        sessions: [
          {
            part_id: 'introduction' as const,
            part_order: 1,
            media_asset_id: '01TESTMEDIA0000000000000001',
            completed: false,
          },
          {
            part_id: 'practice' as const,
            part_order: 2,
            media_asset_id: '01TESTMEDIA0000000000000002',
            completed: false,
          },
          {
            part_id: 'conversation' as const,
            part_order: 3,
            media_asset_id: '01TESTMEDIA0000000000000003',
            completed: false,
          },
          {
            part_id: 'interaction' as const,
            part_order: 4,
            media_asset_id: '01TESTMEDIA0000000000000004',
            completed: false,
          },
        ],
      })),
      sessionDetail: vi.fn(async () => ({
        level_number: 1,
        part_id: 'introduction' as const,
        part_order: 1,
        completed: false,
        media_asset_id: '01TESTMEDIA0000000000000001',
        audio_stream_path: '/api/v1/me/levels/1/sessions/introduction/audio',
        objective: 'Use can and cannot.',
        target_patterns: ['I can + base verb.'],
        kannada_guidance: { summary: 'ಕನ್ನಡ ವಿವರಣೆ' },
        talk: {
          scenario: 'Ask for permission.',
          elicitation_goals: [],
          fallback_turns: [],
          free_speech: true,
        },
        apply: null,
        check: null,
        activities: [
          { id: 'listen', prompt: 'Listen to the Introduction audio once for meaning.' },
        ],
        conversation_binding: {
          prototype: 'skill_focus',
          coaching_mode: 'tutor',
        },
      })),
      completeSession: vi.fn(),
      submitCheck: vi.fn(),
      startConversation: vi.fn(),
    }

    const { unmount } = render(<LevelPathPanel api={api} locale="en-IN" />)

    await waitFor(() => {
      expect(screen.getByText('Level 1')).toBeInTheDocument()
    })
    expect(screen.getByText(/Coming next/)).toBeInTheDocument()
    expect(screen.getByText('Level 2')).toBeInTheDocument()
    expect(screen.queryByText('Commissioning pending')).not.toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: /Level 1/i }))
    await waitFor(() => {
      expect(
        screen.getByRole('heading', { name: 'Introduction' }),
      ).toBeInTheDocument()
    })
    expect(screen.getByText('Use can and cannot.')).toBeInTheDocument()
    expect(screen.getByText('I can + base verb.')).toBeInTheDocument()
    expect(document.querySelector('.level-session__kannada')).toBeNull()
    expect(
      screen.getByText('Kannada explanation'),
    ).toBeInTheDocument()
    expect(
      screen.getByText(
        'After you listen, your teacher will ask you questions. Speak your answer.',
      ),
    ).toBeInTheDocument()
    expect(
      screen.getAllByRole('button', { name: 'Ask AI Tutor' }),
    ).toHaveLength(1)
    expect(
      screen.queryByText('This session could not be loaded.'),
    ).not.toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Listen' })).toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: 'Play model audio' }),
    ).toBeEnabled()
    expect(screen.queryByText('I can drink water.')).not.toBeInTheDocument()
    expect(screen.getByText('Do this')).toBeInTheDocument()
    unmount()
  })

  it('shows Kannada lesson guidance when the interface locale is Kannada', async () => {
    const user = userEvent.setup()
    const api: LevelSessionsApi = {
      listLevels: vi.fn(async () => ({
        levels: [
          {
            level_number: 1,
            concept_key: 'can',
            availability: 'available' as const,
            blueprint_id: '01TESTBLUEPRINT000000000001',
            session_part_ids: [
              'introduction',
              'practice',
              'conversation',
              'interaction',
            ],
          },
        ],
      })),
      listSessions: vi.fn(async () => ({
        level_number: 1,
        concept_key: 'can',
        blueprint_id: '01TESTBLUEPRINT000000000001',
        blueprint_content_hash: 'a'.repeat(64),
        sessions: [
          {
            part_id: 'introduction' as const,
            part_order: 1,
            media_asset_id: '01TESTMEDIA0000000000000001',
            completed: false,
          },
        ],
      })),
      sessionDetail: vi.fn(async () => ({
        level_number: 1,
        part_id: 'introduction' as const,
        part_order: 1,
        completed: false,
        media_asset_id: '01TESTMEDIA0000000000000001',
        audio_stream_path: '/api/v1/me/levels/1/sessions/introduction/audio',
        objective: 'Use can and cannot.',
        target_patterns: ['I can + base verb.'],
        kannada_guidance: { summary: 'ಕನ್ನಡ ವಿವರಣೆ' },
        talk: {
          scenario: 'Ask for permission.',
          elicitation_goals: [],
          fallback_turns: [],
          free_speech: true,
        },
        apply: null,
        check: null,
        activities: [
          { id: 'listen', prompt: 'Listen to the Introduction audio once for meaning.' },
        ],
        conversation_binding: {
          prototype: 'skill_focus',
          coaching_mode: 'tutor',
        },
      })),
      completeSession: vi.fn(),
      submitCheck: vi.fn(),
      startConversation: vi.fn(),
    }

    const { unmount } = render(
      <LevelPathPanel
        api={api}
        locale="kn-IN"
        localizationPolicy="development-preview"
      />,
    )

    expect(await screen.findByText('Can — ಸಾಮರ್ಥ್ಯ ಮತ್ತು ಅನುಮತಿ')).toBeInTheDocument()
    expect(screen.queryByText('Can — ability and permission')).not.toBeInTheDocument()
    await user.click(await screen.findByRole('button', { name: /ಮಟ್ಟ 1/ }))
    await waitFor(() => {
      expect(document.querySelector('.level-session__objective')).toHaveTextContent(
        'ಕನ್ನಡ ವಿವರಣೆ',
      )
      expect(document.querySelector('.level-session__objective')).toHaveAttribute('lang', 'kn-IN')
      expect(screen.queryByText('Use can and cannot.')).not.toBeInTheDocument()
    })
    expect(screen.getByText('ಅರ್ಥ ತಿಳಿಯಲು ಪರಿಚಯದ ಆಡಿಯೋವನ್ನು ಒಮ್ಮೆ ಕೇಳಿ.')).toHaveAttribute('lang', 'kn-IN')
    unmount()
  })

  it('lists progress-locked levels separately from ready levels', async () => {
    const api: LevelSessionsApi = {
      listLevels: vi.fn(async () => ({
        levels: [
          {
            level_number: 1,
            concept_key: 'can',
            availability: 'available' as const,
            blueprint_id: '01TESTBLUEPRINT000000000001',
            session_part_ids: [
              'introduction',
              'practice',
              'conversation',
              'interaction',
            ],
            progress_access: 'allowed' as const,
          },
          {
            level_number: 2,
            concept_key: 'should',
            availability: 'available' as const,
            blueprint_id: '01TESTBLUEPRINT000000000002',
            session_part_ids: [
              'introduction',
              'practice',
              'conversation',
              'interaction',
            ],
            progress_access: 'locked' as const,
            unlock_after_level_number: 1,
          },
        ],
      })),
      listSessions: vi.fn(),
      sessionDetail: vi.fn(),
      completeSession: vi.fn(),
      submitCheck: vi.fn(),
      startConversation: vi.fn(),
    }

    render(<LevelPathPanel api={api} locale="en-IN" />)

    expect(await screen.findByRole('button', { name: /Level 1/i })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Level 2/i })).not.toBeInTheDocument()
    expect(
      screen.getByText('Complete your current level first'),
    ).toBeInTheDocument()
    expect(
      screen.getByText(
        'Finish all four sessions and pass the Level 1 checkpoint to open the next level.',
      ),
    ).toBeInTheDocument()
    expect(screen.getByText('Level 2')).toBeInTheDocument()
  })

  it('shows review-due blocking before checkpoint-ready levels', async () => {
    const user = userEvent.setup()
    const api: LevelSessionsApi = {
      listLevels: vi.fn(async () => ({
        levels: [
          {
            level_number: 1,
            concept_key: 'can',
            availability: 'available' as const,
            blueprint_id: '01TESTBLUEPRINT000000000001',
            session_part_ids: [
              'introduction',
              'practice',
              'conversation',
              'interaction',
            ],
            progress_access: 'allowed' as const,
            checkpoint_status: 'review_due_blocked' as const,
            review_due_blocking: true,
          },
        ],
      })),
      listSessions: vi.fn(async () => completedSessionsList),
      sessionDetail: vi.fn(async (_levelNumber, partId) => ({
        level_number: 1,
        part_id: partId,
        part_order: 1,
        completed: true,
        media_asset_id: '01TESTMEDIA0000000000000001',
        audio_stream_path: '/api/v1/me/levels/1/sessions/introduction/audio',
        objective: 'Use can and cannot.',
        target_patterns: ['I can + base verb.'],
        kannada_guidance: { summary: 'ಕನ್ನಡ ವಿವರಣೆ' },
        talk: null,
        apply: null,
        check: null,
        activities: [],
        conversation_binding: null,
      })),
      completeSession: vi.fn(),
      submitCheck: vi.fn(),
      startConversation: vi.fn(),
    }

    render(<LevelPathPanel api={api} locale="en-IN" />)

    expect(await screen.findByText('Review due first')).toBeInTheDocument()
    expect(
      screen.getByText(
        'A review is ready or overdue. Review it from Home before taking the level checkpoint.',
      ),
    ).toBeInTheDocument()
    const reviewAction = screen.getByRole('link', { name: 'Go to review' })
    expect(reviewAction).toHaveAttribute('href', '#home')
    await user.click(reviewAction)
    expect(globalThis.location.hash).toBe('#home')
    window.history.replaceState(null, '', '/')
    expect(screen.queryByText('Checkpoint ready')).not.toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: /Level 1/i }))
    const inLevelReviewAction = await screen.findByRole('link', {
      name: 'Go to review',
    })
    expect(inLevelReviewAction).toHaveAttribute('href', '#home')
  })

  it('keeps Conversation and Interaction locked until earlier sessions are complete', async () => {
    const user = userEvent.setup()
    const sessionDetail = vi.fn(async () => ({
      level_number: 1,
      part_id: 'introduction' as const,
      part_order: 1,
      completed: false,
      media_asset_id: '01TESTMEDIA0000000000000001',
      audio_stream_path: '/api/v1/me/levels/1/sessions/introduction/audio',
      objective: 'Use can and cannot.',
      target_patterns: ['I can + base verb.'],
      kannada_guidance: { summary: 'ಕನ್ನಡ ವಿವರಣೆ' },
      talk: {
        scenario: 'Ask for permission.',
        elicitation_goals: [],
        fallback_turns: [],
        free_speech: true,
      },
      apply: null,
      check: null,
      activities: [],
      conversation_binding: {
        prototype: 'skill_focus',
        coaching_mode: 'tutor',
      },
    }))
    const api: LevelSessionsApi = {
      listLevels: vi.fn(async () => ({
        levels: [
          {
            level_number: 1,
            concept_key: 'can',
            availability: 'available' as const,
            blueprint_id: '01TESTBLUEPRINT000000000001',
            session_part_ids: [
              'introduction',
              'practice',
              'conversation',
              'interaction',
            ],
          },
        ],
      })),
      listSessions: vi.fn(async () => ({
        level_number: 1,
        concept_key: 'can',
        blueprint_id: '01TESTBLUEPRINT000000000001',
        blueprint_content_hash: 'a'.repeat(64),
        sessions: [
          {
            part_id: 'introduction' as const,
            part_order: 1,
            media_asset_id: '01TESTMEDIA0000000000000001',
            completed: false,
          },
          {
            part_id: 'practice' as const,
            part_order: 2,
            media_asset_id: '01TESTMEDIA0000000000000002',
            completed: false,
          },
          {
            part_id: 'conversation' as const,
            part_order: 3,
            media_asset_id: '01TESTMEDIA0000000000000003',
            completed: false,
          },
          {
            part_id: 'interaction' as const,
            part_order: 4,
            media_asset_id: '01TESTMEDIA0000000000000004',
            completed: false,
          },
        ],
      })),
      sessionDetail,
      completeSession: vi.fn(),
      submitCheck: vi.fn(),
      startConversation: vi.fn(),
    }

    render(<LevelPathPanel api={api} locale="en-IN" />)
    await user.click(await screen.findByRole('button', { name: /Level 1/i }))
    await screen.findByRole('heading', { name: 'Introduction' })

    const callsBeforeConversation = sessionDetail.mock.calls.length
    await user.click(screen.getByRole('button', { name: 'Conversation' }))
    expect(
      screen.getByText('Complete the previous session before opening this one.'),
    ).toBeInTheDocument()
    expect(sessionDetail.mock.calls).toHaveLength(callsBeforeConversation)
  })

  it('keeps Conversation and Interaction on the paid plan even after earlier sessions', async () => {
    const user = userEvent.setup()
    const api: LevelSessionsApi = {
      listLevels: vi.fn(async () => ({
        levels: [
          {
            level_number: 1,
            concept_key: 'can',
            availability: 'available' as const,
            blueprint_id: '01TESTBLUEPRINT000000000001',
            session_part_ids: [
              'introduction',
              'practice',
              'conversation',
              'interaction',
            ],
          },
        ],
      })),
      listSessions: vi.fn(async () => ({
        level_number: 1,
        concept_key: 'can',
        blueprint_id: '01TESTBLUEPRINT000000000001',
        blueprint_content_hash: 'a'.repeat(64),
        sessions: [
          {
            part_id: 'introduction' as const,
            part_order: 1,
            media_asset_id: '01TESTMEDIA0000000000000001',
            completed: true,
          },
          {
            part_id: 'practice' as const,
            part_order: 2,
            media_asset_id: '01TESTMEDIA0000000000000002',
            completed: true,
          },
          {
            part_id: 'conversation' as const,
            part_order: 3,
            media_asset_id: '01TESTMEDIA0000000000000003',
            completed: false,
          },
          {
            part_id: 'interaction' as const,
            part_order: 4,
            media_asset_id: '01TESTMEDIA0000000000000004',
            completed: false,
          },
        ],
      })),
      sessionDetail: vi.fn(async () => ({
        level_number: 1,
        part_id: 'introduction' as const,
        part_order: 1,
        completed: true,
        media_asset_id: '01TESTMEDIA0000000000000001',
        audio_stream_path: '/api/v1/me/levels/1/sessions/introduction/audio',
        objective: 'Use can and cannot.',
        target_patterns: ['I can + base verb.'],
        kannada_guidance: { summary: 'ಕನ್ನಡ ವಿವರಣೆ' },
        talk: {
          scenario: 'Ask for permission.',
          elicitation_goals: [],
          fallback_turns: [],
          free_speech: false,
        },
        apply: null,
        check: null,
        activities: [],
        conversation_binding: {
          prototype: 'skill_focus',
          coaching_mode: 'tutor',
        },
      })),
      completeSession: vi.fn(),
      submitCheck: vi.fn(),
      startConversation: vi.fn(),
    }

    render(
      <LevelPathPanel
        allowedSessionParts={['introduction', 'practice']}
        api={api}
        locale="en-IN"
      />,
    )
    await user.click(await screen.findByRole('button', { name: /Level 1/i }))
    await screen.findByRole('heading', { name: 'Introduction' })
    await user.click(screen.getByRole('button', { name: 'Interaction' }))
    expect(
      screen.getByText('This session needs a paid monthly plan.'),
    ).toBeInTheDocument()
  })

  it('completes saved Practice without an in-memory Check and opens Conversation', async () => {
    const user = userEvent.setup()
    const api = apiWithQuickCheck(vi.fn())
    vi.mocked(api.listSessions).mockResolvedValue({ ...completedSessionsList,
      sessions: completedSessionsList.sessions.map((session) => ({ ...session, completed: session.part_id === 'introduction' })) })
    render(<LevelPathPanel api={api} locale="en-IN" />)
    await user.click(await screen.findByRole('button', { name: /Level 1/i }))
    await user.click(await screen.findByRole('button', { name: /^Practice(?:, Completed)?$/i }))
    await screen.findByRole('heading', { name: 'Practice' })
    expect(screen.getByRole('button', { name: 'Conversation' })).toHaveAttribute('aria-disabled', 'true')
    await user.click(screen.getByRole('button', { name: 'Mark session complete' }))
    expect(api.completeSession).toHaveBeenCalledWith(1, 'practice', expect.any(String))
    await user.click(await screen.findByRole('button', { name: 'Continue · Conversation' }))
    expect(await screen.findByRole('heading', { name: 'Conversation' })).toBeVisible()
  })

  it('does not unlock Conversation when the server rejects Practice completion', async () => {
    const user = userEvent.setup()
    const api = apiWithQuickCheck(vi.fn())
    vi.mocked(api.listSessions).mockResolvedValue({ ...completedSessionsList,
      sessions: completedSessionsList.sessions.map((session) => ({ ...session, completed: session.part_id === 'introduction' })) })
    vi.mocked(api.completeSession).mockRejectedValue(new Error('level_sessions_http_404'))
    render(<LevelPathPanel api={api} locale="en-IN" />)
    await user.click(await screen.findByRole('button', { name: /Level 1/i }))
    await user.click(await screen.findByRole('button', { name: 'Practice' }))
    await screen.findByRole('heading', { name: 'Practice' })
    await user.click(screen.getByRole('button', { name: 'Mark session complete' }))
    await vi.waitFor(() => expect(api.completeSession).toHaveBeenCalledOnce())
    expect(screen.getByRole('button', { name: 'Conversation' })).toHaveAttribute('aria-disabled', 'true')
    expect(screen.queryByRole('button', { name: 'Continue · Conversation' })).not.toBeInTheDocument()
  })

  it('opens Conversation on the lesson scenario, not the Introduction drill', async () => {
    const user = userEvent.setup()
    const session = {
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
    }
    const introductionDetail = {
      level_number: 1,
      part_id: 'introduction' as const,
      part_order: 1,
      completed: true,
      media_asset_id: '01TESTMEDIA0000000000000001',
      audio_stream_path: '/api/v1/me/levels/1/sessions/introduction/audio',
      objective: 'Use can and cannot.',
      target_patterns: ['I can + base verb.', 'I cannot + base verb.'],
      kannada_guidance: { summary: 'ಕನ್ನಡ ವಿವರಣೆ' },
      talk: {
        scenario: 'Ask a coworker if you can use the printer.',
        elicitation_goals: ['Ask one Can I...? question.'],
        fallback_turns: ['Can I use your pen?'],
        free_speech: false,
      },
      apply: null,
      check: null,
      activities: [],
      conversation_binding: {
        prototype: 'skill_focus' as const,
        coaching_mode: 'tutor' as const,
      },
    }
    const conversationDetail = {
      ...introductionDetail,
      part_id: 'conversation' as const,
      part_order: 3,
      completed: false,
      media_asset_id: '01TESTMEDIA0000000000000003',
      audio_stream_path: '/api/v1/me/levels/1/sessions/conversation/audio',
      conversation_binding: {
        prototype: 'friend' as const,
        coaching_mode: 'gentle' as const,
      },
    }
    const api: LevelSessionsApi = {
      listLevels: vi.fn(async () => ({
        levels: [
          {
            level_number: 1,
            concept_key: 'can',
            availability: 'available' as const,
            blueprint_id: '01TESTBLUEPRINT000000000001',
            session_part_ids: [
              'introduction',
              'practice',
              'conversation',
              'interaction',
            ],
          },
        ],
      })),
      listSessions: vi.fn(async () => ({
        level_number: 1,
        concept_key: 'can',
        blueprint_id: '01TESTBLUEPRINT000000000001',
        blueprint_content_hash: 'a'.repeat(64),
        sessions: [
          {
            part_id: 'introduction' as const,
            part_order: 1,
            media_asset_id: '01TESTMEDIA0000000000000001',
            completed: true,
          },
          {
            part_id: 'practice' as const,
            part_order: 2,
            media_asset_id: '01TESTMEDIA0000000000000002',
            completed: true,
          },
          {
            part_id: 'conversation' as const,
            part_order: 3,
            media_asset_id: '01TESTMEDIA0000000000000003',
            completed: false,
          },
          {
            part_id: 'interaction' as const,
            part_order: 4,
            media_asset_id: '01TESTMEDIA0000000000000004',
            completed: false,
          },
        ],
      })),
      sessionDetail: vi.fn(async (_level, part) =>
        part === 'conversation' ? conversationDetail : introductionDetail,
      ),
      completeSession: vi.fn(),
      submitCheck: vi.fn(),
      startConversation: vi.fn(async () => session),
    }

    render(
      <LevelPathPanel
        api={api}
        conversationApi={{
          startSession: vi.fn(),
          createTurn: vi.fn(),
          events: vi.fn(),
        }}
        locale="en-IN"
      />,
    )
    await user.click(await screen.findByRole('button', { name: /Level 1/i }))
    await waitFor(() => {
      expect(
        screen.getByRole('button', { name: 'Conversation' }),
      ).not.toHaveClass('level-session__tab--locked')
    })
    await user.click(screen.getByRole('button', { name: 'Conversation' }))
    await user.click(
      await screen.findByRole('button', { name: 'Open free-speech room' }),
    )

    expect(document.querySelector('.level-session__scenario')).toHaveTextContent(
      'Ask a coworker if you can use the printer.',
    )
    expect(
      document.querySelector('[data-lesson-room="conversation"]'),
    ).not.toBeNull()
    expect(
      screen.queryByText('Listen. Say: I can drink water. Now you try.'),
    ).not.toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: 'Skip this turn' }),
    ).toBeInTheDocument()
  })

  it('loads private lesson audio through the authenticated downloader', async () => {
    const user = userEvent.setup()
    vi.spyOn(HTMLMediaElement.prototype, 'load').mockImplementation(
      () => undefined,
    )
    vi.spyOn(HTMLMediaElement.prototype, 'pause').mockImplementation(
      () => undefined,
    )
    const createObjectUrl = vi
      .spyOn(URL, 'createObjectURL')
      .mockReturnValue('blob:authenticated-lesson-audio')
    const downloadAudio = vi.fn(
      async () => new Blob(['lesson-audio'], { type: 'audio/mpeg' }),
    )
    const api: LevelSessionsApi = {
      listLevels: vi.fn(async () => ({
        levels: [
          {
            level_number: 1,
            concept_key: 'can',
            availability: 'available' as const,
            blueprint_id: '01TESTBLUEPRINT000000000001',
            session_part_ids: [
              'introduction',
              'practice',
              'conversation',
              'interaction',
            ],
          },
        ],
      })),
      listSessions: vi.fn(async () => ({
        level_number: 1,
        concept_key: 'can',
        blueprint_id: '01TESTBLUEPRINT000000000001',
        blueprint_content_hash: 'a'.repeat(64),
        sessions: [
          {
            part_id: 'introduction' as const,
            part_order: 1,
            media_asset_id: '01TESTMEDIA0000000000000001',
            completed: false,
          },
        ],
      })),
      sessionDetail: vi.fn(async () => ({
        level_number: 1,
        part_id: 'introduction' as const,
        part_order: 1,
        completed: false,
        media_asset_id: '01TESTMEDIA0000000000000001',
        audio_stream_path: '/api/v1/me/levels/1/sessions/introduction/audio',
        objective: 'Use can and cannot.',
        target_patterns: [],
        kannada_guidance: { summary: 'ಕನ್ನಡ ವಿವರಣೆ' },
        talk: null,
        apply: null,
        check: null,
        activities: [],
        conversation_binding: null,
      })),
      completeSession: vi.fn(),
      submitCheck: vi.fn(),
      startConversation: vi.fn(),
      downloadAudio,
    }

    const { unmount } = render(<LevelPathPanel api={api} locale="en-IN" />)
    await user.click(await screen.findByRole('button', { name: /Level 1/i }))
    await waitFor(() => {
      expect(downloadAudio).toHaveBeenCalledWith(
        '/api/v1/me/levels/1/sessions/introduction/audio',
        expect.any(AbortSignal),
      )
    })
    await waitFor(() => {
      expect(
        screen.getByTestId('model-audio-element').querySelector('source'),
      ).toHaveAttribute('src', 'blob:authenticated-lesson-audio')
    })
    expect(createObjectUrl).toHaveBeenCalledTimes(1)
    expect(
      screen.getByRole('button', { name: 'Play model audio' }),
    ).toBeEnabled()
    unmount()
  })

  it('keeps the live teacher in the same level session', async () => {
    const user = userEvent.setup()
    const session = {
      coaching_mode: 'tutor' as const,
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
        key: 'past-tense-skill-focus-prototype' as const,
        review_status: 'synthetic_unreviewed' as const,
        topic_code: 'everyday-conversation' as const,
        version: 1,
        version_id: '01J00000000000000000000005',
      },
      status: 'active' as const,
      ui_locale: 'en-IN' as const,
    }
    const api: LevelSessionsApi = {
      listLevels: vi.fn(async () => ({
        levels: [
          {
            level_number: 1,
            concept_key: 'can',
            availability: 'available' as const,
            blueprint_id: '01TESTBLUEPRINT000000000001',
            session_part_ids: [
              'introduction',
              'practice',
              'conversation',
              'interaction',
            ],
          },
        ],
      })),
      listSessions: vi.fn(async () => ({
        level_number: 1,
        concept_key: 'can',
        blueprint_id: '01TESTBLUEPRINT000000000001',
        blueprint_content_hash: 'a'.repeat(64),
        sessions: [],
      })),
      sessionDetail: vi.fn(async () => ({
        level_number: 1,
        part_id: 'introduction' as const,
        part_order: 1,
        completed: false,
        media_asset_id: '01TESTMEDIA0000000000000001',
        audio_stream_path: '/api/v1/me/levels/1/sessions/introduction/audio',
        objective: 'Use can and cannot.',
        target_patterns: [
          'I can + base verb.',
          'I cannot + base verb.',
          'Can I + base verb?',
          'Yes, you can. / No, you cannot.',
        ],
        kannada_guidance: { summary: 'ಕನ್ನಡ ವಿವರಣೆ' },
        talk: {
          scenario: 'Ask for permission.',
          elicitation_goals: ['Ask one Can I...? question.'],
          fallback_turns: ['Can I use your pen?'],
          free_speech: false,
        },
        apply: null,
        check: null,
        activities: [],
        conversation_binding: {
          prototype: 'skill_focus',
          coaching_mode: 'tutor',
        },
      })),
      completeSession: vi.fn(),
      submitCheck: vi.fn(),
      startConversation: vi.fn(async () => session),
      startTutorHelp: vi.fn(async () => session),
    }

    render(
      <LevelPathPanel
        api={api}
        conversationApi={{
          startSession: vi.fn(),
          createTurn: vi.fn(),
          events: vi.fn(),
        }}
        locale="en-IN"
      />,
    )
    await user.click(await screen.findByRole('button', { name: /Level 1/i }))
    await user.click(await screen.findByRole('button', { name: 'Ask AI Tutor' }))
    expect(await screen.findByRole('heading', { name: 'Ask Swathi' })).toBeInTheDocument()
    expect(api.startConversation).not.toHaveBeenCalled()
    await user.click(screen.getByRole('button', { name: 'Back' }))
    await user.click(await screen.findByRole('button', { name: 'Start Skill Focus' }))
    expect(api.startConversation).toHaveBeenCalled()
    expect(
      await screen.findByText('I can drink water.'),
    ).toBeInTheDocument()
    expect(
      document.querySelector('[data-lesson-room="introduction"]'),
    ).not.toBeNull()
    expect(document.querySelectorAll('.tutor-presence')).toHaveLength(1)
    expect(
      screen.queryByText(/Do not start a free conversation/u),
    ).not.toBeInTheDocument()
  })

  it('advances Introduction to the next model sentence after one try', async () => {
    const user = userEvent.setup()
    const nextModel =
      'Good. Now listen. Say: I cannot drink water. Your turn.'
    const session = {
      coaching_mode: 'tutor' as const,
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
        key: 'past-tense-skill-focus-prototype' as const,
        review_status: 'synthetic_unreviewed' as const,
        topic_code: 'everyday-conversation' as const,
        version: 1,
        version_id: '01J00000000000000000000005',
      },
      status: 'active' as const,
      ui_locale: 'en-IN' as const,
    }
    const drillTurn = {
      assistant_reply: nextModel,
      conversation_session_id: session.id,
      events_url:
        '/api/v1/conversation-turns/01J00000000000000000000006/events',
      id: '01J00000000000000000000006',
      priority_correction: {
        correction_en:
          'A clearer sentence is: "I enjoy learning English after work."',
        error_category: 'grammar.article',
        explanation_l1:
          'ಸ್ಪಷ್ಟವಾಗಿ ಹೇಳುವುದಾದರೆ: "I enjoy learning English after work."',
        explanation_locale: 'kn-IN' as const,
        explanation_kn:
          'ಸ್ಪಷ್ಟವಾಗಿ ಹೇಳುವುದಾದರೆ: "I enjoy learning English after work."',
        needed: false,
        retry_recommended: false,
      },
      replayed: false,
      safety_state: 'ok' as const,
      speech: {
        playback_available: false,
        rendering_mode: 'deterministic-fake-text-only' as const,
      },
      status: 'completed' as const,
      transcript: 'I can use a pen.',
      turn_number: 1,
      understood_intent: 'prototype_utterance_received',
      usage: {
        estimated_cost_microunits: 0,
        provider_attempts: 3,
        total_latency_ms: 14,
      },
    }
    const api: LevelSessionsApi = {
      listLevels: vi.fn(async () => ({
        levels: [
          {
            level_number: 1,
            concept_key: 'can',
            availability: 'available' as const,
            blueprint_id: '01TESTBLUEPRINT000000000001',
            session_part_ids: [
              'introduction',
              'practice',
              'conversation',
              'interaction',
            ],
          },
        ],
      })),
      listSessions: vi.fn(async () => ({
        level_number: 1,
        concept_key: 'can',
        blueprint_id: '01TESTBLUEPRINT000000000001',
        blueprint_content_hash: 'a'.repeat(64),
        sessions: [],
      })),
      sessionDetail: vi.fn(async () => ({
        level_number: 1,
        part_id: 'introduction' as const,
        part_order: 1,
        completed: false,
        media_asset_id: '01TESTMEDIA0000000000000001',
        audio_stream_path: '/api/v1/me/levels/1/sessions/introduction/audio',
        objective: 'Use can and cannot.',
        target_patterns: [
          'I can + base verb.',
          'I cannot + base verb.',
          'Can I + base verb?',
          'Yes, you can. / No, you cannot.',
        ],
        kannada_guidance: { summary: 'ಕನ್ನಡ ವಿವರಣೆ' },
        talk: {
          scenario: 'Ask for permission.',
          elicitation_goals: ['Ask one Can I...? question.'],
          fallback_turns: ['Can I use your pen?'],
          free_speech: false,
        },
        apply: null,
        check: null,
        activities: [],
        conversation_binding: {
          prototype: 'skill_focus',
          coaching_mode: 'tutor',
        },
      })),
      completeSession: vi.fn(),
      submitCheck: vi.fn(),
      startConversation: vi.fn(async () => session),
    }
    const createTurn = vi.fn(async () => drillTurn)

    render(
      <LevelPathPanel
        api={api}
        conversationApi={{
          startSession: vi.fn(),
          createTurn,
          events: vi.fn(async () => []),
        }}
        locale="en-IN"
      />,
    )
    await user.click(await screen.findByRole('button', { name: /Level 1/i }))
    await user.click(await screen.findByRole('button', { name: 'Start Skill Focus' }))

    expect(screen.getByText('I can + base verb.')).toBeInTheDocument()
    expect(screen.getByText('I can drink water.')).toBeInTheDocument()
    expect(screen.queryByText('Stage')).not.toBeInTheDocument()
    expect(
      screen.queryByRole('button', { name: 'Skip this turn' }),
    ).not.toBeInTheDocument()
    expect(
      screen.queryByRole('button', { name: 'Hint in my language' }),
    ).not.toBeInTheDocument()
    expect(
      document.querySelector('[data-lesson-room="introduction"]'),
    ).not.toBeNull()

    await user.click(screen.getByRole('button', { name: 'Type instead' }))
    await user.type(
      screen.getByRole('textbox', {
        name: 'Type one English practice turn',
      }),
      'I can use a pen.',
    )
    await user.click(screen.getByRole('button', { name: 'Send turn' }))

    expect(createTurn).toHaveBeenCalledWith(
      session.id,
      { prototype_utterance: 'I can use a pen.' },
      expect.any(String),
      expect.any(AbortSignal),
    )
    expect(await screen.findByText('I can use a pen.')).toBeInTheDocument()
    expect(screen.getByText('I cannot drink water.')).toBeInTheDocument()
    expect(
      screen.queryByText(/Do not start a free conversation/u),
    ).not.toBeInTheDocument()
    expect(
      screen.queryByText(
        'A clearer sentence is: "I enjoy learning English after work."',
      ),
    ).not.toBeInTheDocument()
  })

  it('renders Kannada quiz directions and feedback while submitting unchanged English option identities', async () => {
    const user = userEvent.setup()
    const submit = vi.fn().mockResolvedValue({ replayed: false, score: 1, threshold: 0.8, passed: true,
      item_results: [{ id: 'can-01', selected_option: 1, correct: true, rationale: 'Can is followed by the base verb speak.' }] })
    const api = apiWithQuickCheck(submit)
    const detail = api.sessionDetail
    api.sessionDetail = vi.fn(async (...args: Parameters<LevelSessionsApi['sessionDetail']>) => {
      const value = await detail(...args)
      return { ...value, check: value.check ? { ...value.check, items: [{ id: 'can-01', weight: 1,
        prompt: 'Choose the correct sentence.', options: ['I can speaking English.', 'I can speak English.'] }] } : null }
    })
    render(<LevelPathPanel api={api} locale="kn-IN" localizationPolicy="development-preview" />)
    await user.click(await screen.findByRole('button', { name: /ಮಟ್ಟ 1/ }))
    await user.click(document.querySelector('#level-1-part-practice')!)
    expect(await screen.findByText('ಸರಿಯಾದ ವಾಕ್ಯವನ್ನು ಆಯ್ಕೆಮಾಡಿ.')).toHaveAttribute('lang', 'kn-IN')
    await user.click(screen.getByRole('radio', { name: 'I can speak English.' }))
    const form = document.querySelector('.level-session__assessment')!
    await user.click(form.querySelector('button[type="submit"]')!)
    await waitFor(() => expect(submit).toHaveBeenCalled())
    expect(submit.mock.calls[0]?.[2]).toEqual({ 'can-01': 1 })
    expect(await screen.findByText(/Can ನಂತರ speak ಎಂಬ ಮೂಲ ಕ್ರಿಯಾಪದ ಬರುತ್ತದೆ/)).toHaveAttribute('lang', 'kn-IN')
  })

  it('shows one quick-check question at a time, item feedback, and a focused retry', async () => {
    const user = userEvent.setup()
    const firstResult = {
      replayed: false,
      score: 0.5,
      threshold: 0.8,
      passed: false,
      item_results: [
        {
          id: 'can-check-01',
          selected_option: 0,
          correct: false,
          rationale: 'Use can without adding s.',
        },
        {
          id: 'can-check-02',
          selected_option: 0,
          correct: true,
          rationale: 'Use can + subject + base verb.',
        },
      ],
    }
    const secondResult = {
      ...firstResult,
      score: 1,
      passed: true,
      item_results: [
        {
          id: 'can-check-01',
          selected_option: 1,
          correct: true,
          rationale: 'Use can without adding s.',
        },
        firstResult.item_results[1]!,
      ],
    }
    const submitCheck = vi
      .fn()
      .mockResolvedValueOnce(firstResult)
      .mockResolvedValueOnce(secondResult)
    const attemptIds = ['quick-attempt-1', 'quick-attempt-2']

    render(
      <LevelPathPanel
        api={apiWithQuickCheck(submitCheck)}
        createClientId={() => attemptIds.shift() ?? 'unused-attempt'}
        locale="en-IN"
      />,
    )

    await user.click(await screen.findByRole('button', { name: /Level 1/i }))
    await user.click(
      await screen.findByRole('button', { name: /^Practice(?:, Completed)?$/i }),
    )

    expect(await screen.findByText('Question 1 of 2')).toBeInTheDocument()
    expect(
      screen.getByText('Choose the sentence that uses can correctly.'),
    ).toBeInTheDocument()
    expect(
      screen.queryByText('Choose the correct permission question.'),
    ).not.toBeInTheDocument()

    await user.click(screen.getByRole('radio', { name: 'I cans swim.' }))
    expect(
      screen.getByRole('radio', { name: 'I cans swim.' }).closest('label'),
    ).toHaveClass('is-selected')
    expect(
      document.querySelector('.level-session--question-phase'),
    ).not.toBeNull()
    await user.click(screen.getByRole('button', { name: 'Continue' }))

    expect(screen.getByText('Question 2 of 2')).toBeInTheDocument()
    expect(
      screen.queryByText('Choose the sentence that uses can correctly.'),
    ).not.toBeInTheDocument()
    await user.click(
      screen.getByRole('button', { name: 'Previous question' }),
    )
    expect(screen.getByText('Question 1 of 2')).toBeInTheDocument()
    expect(
      screen.getByRole('radio', { name: 'I cans swim.' }),
    ).toBeChecked()
    await user.click(screen.getByRole('button', { name: 'Continue' }))
    await user.click(screen.getByRole('radio', { name: 'Can I sit here?' }))
    await user.click(screen.getByRole('button', { name: 'Submit check' }))

    expect(await screen.findByText('Not yet')).toBeInTheDocument()
    expect(screen.queryByText(/^Score /)).not.toBeInTheDocument()
    expect(screen.getByText('1 of 4 sessions complete')).toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: 'Introduction, Completed' }),
    ).toBeVisible()
    expect(screen.getByText('Why: Use can without adding s.')).toBeInTheDocument()
    expect(screen.getByText('Your answer: I cans swim.')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Next result' }))
    expect(screen.getByText('Correct')).toBeInTheDocument()
    expect(screen.queryByText(/^Score /)).not.toBeInTheDocument()
    expect(
      screen.getByText(
        'Reviewed. This answer needs more practice, so one short retry is ready now. Your review attempt was recorded; mastery has not changed.',
      ),
    ).toBeVisible()
    await user.click(
      screen.getByRole('button', { name: 'Retry incorrect answers' }),
    )

    expect(screen.getByText('Question 1 of 1')).toBeInTheDocument()
    expect(
      screen.queryByText('Choose the correct permission question.'),
    ).not.toBeInTheDocument()
    await user.click(
      await screen.findByRole('radio', { name: 'I can swim.' }),
    )
    await user.click(screen.getByRole('button', { name: 'Submit check' }))

    expect(await screen.findByText('Correct')).toBeInTheDocument()
    expect(screen.queryByText(/^Score /)).not.toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Next result' }))
    expect(screen.queryByText(/^Score /)).not.toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Done' }))
    expect(screen.getByText('Score 1% — passed')).toBeVisible()
    expect(screen.getByRole('button', { name: 'Conversation' })).not.toHaveAttribute('aria-disabled', 'true')
    await user.click(screen.getByRole('button', { name: 'Continue · Conversation' }))
    expect(await screen.findByRole('heading', { name: 'Conversation' })).toBeVisible()

    expect(submitCheck).toHaveBeenNthCalledWith(
      1,
      1,
      'practice',
      { 'can-check-01': 0, 'can-check-02': 0 },
      'quick-attempt-1',
    )
    expect(submitCheck).toHaveBeenNthCalledWith(
      2,
      1,
      'practice',
      { 'can-check-01': 1, 'can-check-02': 0 },
      'quick-attempt-2',
    )
  })

  it('restores a tenant-bound option-only draft after recreation and replays an uncertain submission with the same attempt id', async () => {
    const user = userEvent.setup()
    let storedDraft: LevelQuizDraft | null = null
    const saveLevelQuizDraft = vi.fn(async (
      _binding: OfflineProfileBinding,
      draft: LevelQuizDraft,
    ) => {
      storedDraft = structuredClone(draft)
    })
    const store: OfflineLearningStore = {
      acceptReceipt: vi.fn(async () => undefined),
      available: () => true,
      deleteLevelQuizDraft: vi.fn(async () => undefined),
      enablePersonalDevice: vi.fn(async () => undefined),
      hasProfile: vi.fn(async () => true),
      loadBundle: vi.fn(async () => null),
      loadLevelQuizDraft: vi.fn(async () => storedDraft),
      markNeedsAttention: vi.fn(async () => undefined),
      pendingCommands: vi.fn(async () => []),
      prepareLogout: vi.fn(async () => undefined),
      purge: vi.fn(async () => undefined),
      purgeAll: vi.fn(async () => undefined),
      queueCompletion: vi.fn(async () => undefined),
      resumePersonalDevice: vi.fn(async () => null),
      saveBundle: vi.fn(async () => undefined),
      saveLevelQuizDraft,
    }
    const result = {
      replayed: true,
      score: 1,
      threshold: 0.8,
      passed: true,
      item_results: quickCheckItems.map((item, index) => ({
        id: item.id,
        selected_option: index === 0 ? 1 : 0,
        correct: true,
        rationale: 'Deterministic rationale.',
      })),
    }
    const submitCheck = vi
      .fn()
      .mockRejectedValueOnce(new Error('response_lost'))
      .mockResolvedValueOnce(result)
    const firstRender = render(
      <LevelPathPanel
        api={apiWithQuickCheck(submitCheck)}
        createClientId={() => 'stable-quick-attempt'}
        locale="en-IN"
        offlineBinding={offlineBinding}
        offlineStore={store}
      />,
    )

    await user.click(await screen.findByRole('button', { name: /Level 1/i }))
    await user.click(await screen.findByRole('button', { name: /^Practice(?:, Completed)?$/i }))
    await user.click(
      await screen.findByRole('radio', { name: 'I can swim.' }),
    )
    await user.click(screen.getByRole('button', { name: 'Continue' }))
    await waitFor(() => {
      expect(storedDraft?.currentItemIndex).toBe(1)
    })
    await user.click(
      screen.getByRole('button', { name: 'Introduction, Completed' }),
    )
    await user.click(await screen.findByRole('button', { name: 'Practice' }))
    expect(await screen.findByText('Question 2 of 2')).toBeInTheDocument()
    expect(JSON.stringify(storedDraft)).not.toContain('I can swim.')
    expect(JSON.stringify(storedDraft)).not.toContain('prompt')
    expect(saveLevelQuizDraft).toHaveBeenLastCalledWith(
      offlineBinding,
      expect.objectContaining({
        attemptId: 'stable-quick-attempt',
        blueprintContentHash: 'a'.repeat(64),
        levelNumber: 1,
        partId: 'practice',
        selectedOptionIds: { 'can-check-01': 1 },
      }),
    )
    expect(
      new Date(storedDraft!.expiresAt).getTime() -
        new Date(storedDraft!.createdAt).getTime(),
    ).toBe(LEVEL_QUIZ_DRAFT_TTL_MILLISECONDS)

    firstRender.unmount()
    render(
      <LevelPathPanel
        api={apiWithQuickCheck(submitCheck)}
        createClientId={() => 'must-not-replace-restored-attempt'}
        locale="en-IN"
        offlineBinding={offlineBinding}
        offlineStore={store}
      />,
    )
    expect(
      await screen.findByRole('heading', { name: 'Practice' }),
    ).toBeInTheDocument()
    expect(await screen.findByText('Question 2 of 2')).toBeInTheDocument()
    expect(store.loadLevelQuizDraft).toHaveBeenLastCalledWith(
      offlineBinding,
      1,
      'practice',
    )
    await user.click(screen.getByRole('radio', { name: 'Can I sit here?' }))
    await user.click(screen.getByRole('button', { name: 'Submit check' }))
    expect(
      await screen.findByText('That action could not be saved. Try again.'),
    ).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Submit check' }))

    expect(submitCheck).toHaveBeenCalledTimes(2)
    expect(submitCheck.mock.calls[0]?.[3]).toBe('stable-quick-attempt')
    expect(submitCheck.mock.calls[1]?.[3]).toBe('stable-quick-attempt')
    expect(submitCheck.mock.calls[1]?.[2]).toEqual({
      'can-check-01': 1,
      'can-check-02': 0,
    })
  })

  it('discards an expired draft instead of restoring stale answer state', async () => {
    const user = userEvent.setup()
    const expiredDraft: LevelQuizDraft = {
      activeItemIds: quickCheckItems.map(({ id }) => id),
      attemptId: 'expired-attempt',
      blueprintContentHash: 'a'.repeat(64),
      createdAt: '2000-01-01T00:00:00.000Z',
      currentItemIndex: 1,
      expiresAt: '2000-01-02T00:00:00.000Z',
      itemIds: quickCheckItems.map(({ id }) => id),
      levelNumber: 1,
      partId: 'practice',
      selectedOptionIds: { 'can-check-01': 1 },
      updatedAt: '2000-01-01T00:00:00.000Z',
    }
    const saveLevelQuizDraft = vi.fn(async () => undefined)
    const store: OfflineLearningStore = {
      acceptReceipt: vi.fn(async () => undefined),
      available: () => true,
      enablePersonalDevice: vi.fn(async () => undefined),
      hasProfile: vi.fn(async () => true),
      loadBundle: vi.fn(async () => null),
      loadLevelQuizDraft: vi.fn(async () => expiredDraft),
      markNeedsAttention: vi.fn(async () => undefined),
      pendingCommands: vi.fn(async () => []),
      prepareLogout: vi.fn(async () => undefined),
      purge: vi.fn(async () => undefined),
      purgeAll: vi.fn(async () => undefined),
      queueCompletion: vi.fn(async () => undefined),
      resumePersonalDevice: vi.fn(async () => null),
      saveBundle: vi.fn(async () => undefined),
      saveLevelQuizDraft,
    }

    render(
      <LevelPathPanel
        api={apiWithQuickCheck(vi.fn())}
        createClientId={() => 'fresh-attempt'}
        locale="en-IN"
        offlineBinding={offlineBinding}
        offlineStore={store}
      />,
    )
    await user.click(await screen.findByRole('button', { name: /Level 1/i }))
    await user.click(await screen.findByRole('button', { name: /^Practice(?:, Completed)?$/i }))

    expect(await screen.findByText('Question 1 of 2')).toBeInTheDocument()
    expect(
      screen.getByRole('radio', { name: 'I can swim.' }),
    ).not.toBeChecked()
    await waitFor(() => {
      expect(saveLevelQuizDraft).toHaveBeenCalledWith(
        offlineBinding,
        expect.objectContaining({ attemptId: 'fresh-attempt' }),
      )
    })
  })

  it('queues checkpoint answers offline and syncs authoritative results on reconnect', async () => {
    const user = userEvent.setup()
    const ids = {
      attempt: '018f4f82-6400-7a12-8f4b-20b12bf6b6f4',
      command: '018f4f82-6400-7a12-8f4b-20b12bf6b6f5',
    }
    let queued: PendingLevelCheckpointCommand[] = []
    const checkpointResult: LevelCheckpointSubmissionResult = {
      attempt_id: '01J00000000000000000000051',
      attempt_number: 1,
      attempts_remaining: 2,
      item_results: [
        {
          correct: true,
          id: 'can-cp-01',
          rationale: 'Use can + base verb.',
          selected_option: 0,
        },
        {
          correct: true,
          id: 'can-cp-02',
          rationale: 'Use can + subject + base verb for permission.',
          selected_option: 0,
        },
      ],
      passed: true,
      pass_threshold: 0.8,
      replayed: false,
      score: 1,
      spoken_evidence: {
        evidence_state: 'not_recorded',
        spoken_progress_claim: false,
        spoken_state: 'unassessed',
      },
      level_unlock: {
        level_number: 2,
        replayed: false,
        unlock_decision_id: '01J00000000000000000000052',
      },
    }
    const submitCheckpoint = vi.fn(async () => checkpointResult)
    const submitOfflineLevelCheckpoint = vi.fn(async () => checkpointResult)
    const markCheckpointNeedsAttention = vi.fn(async () => undefined)
    let savedDraft: LevelQuizDraft | null = null
    let lastQueued: PendingLevelCheckpointCommand | null = null
    const storedLevelBundle = {
      bundle: {
        lease: 'signed-lease-token',
        manifest: {
          schema: 'global-english.offline-level-bundle.v1' as const,
          bundle_id: '01J00000000000000000000070',
          bundle_hash: 'abc123',
          issued_at: '2026-09-13T00:00:00Z',
          expires_at: '2026-09-14T00:00:00Z',
          binding: offlineBinding,
          level: {
            level_number: 1,
            concept_key: 'can',
            blueprint_id: checkpointDetail.blueprint_id,
            blueprint_content_hash: checkpointDetail.blueprint_content_hash,
            checkpoint_rule_key: checkpointDetail.rule_key,
            checkpoint_rule_hash: checkpointDetail.rule_hash,
          },
          checkpoint: checkpointDetail,
        },
      },
    }
    const api: LevelSessionsApi = {
      listLevels: vi.fn(async () => ({
        levels: [
          {
            level_number: 1,
            concept_key: 'can',
            availability: 'available' as const,
            blueprint_id: '01TESTBLUEPRINT000000000001',
            session_part_ids: [
              'introduction',
              'practice',
              'conversation',
              'interaction',
            ],
            checkpoint_status: 'ready' as const,
          },
        ],
      })),
      listSessions: vi.fn(async () => completedSessionsList),
      sessionDetail: vi.fn(async () => ({
        level_number: 1,
        part_id: 'introduction' as const,
        part_order: 1,
        completed: true,
        media_asset_id: '01TESTMEDIA0000000000000001',
        audio_stream_path: '/api/v1/me/levels/1/sessions/introduction/audio',
        objective: 'Use can and cannot.',
        target_patterns: ['I can + base verb.'],
        kannada_guidance: { summary: 'ಕನ್ನಡ ವಿವರಣೆ' },
        talk: {
          scenario: 'Ask for permission.',
          elicitation_goals: [],
          fallback_turns: [],
          free_speech: true,
        },
        apply: null,
        check: null,
        activities: [],
        conversation_binding: {
          prototype: 'skill_focus',
          coaching_mode: 'tutor',
        },
      })),
      completeSession: vi.fn(),
      submitCheck: vi.fn(),
      startConversation: vi.fn(),
      checkpointDetail: vi.fn(async () => checkpointDetail),
      offlineLevelBundle: vi.fn(async () => storedLevelBundle.bundle),
      submitOfflineLevelCheckpoint,
      submitCheckpoint,
    }
    const store: OfflineLearningStore = {
      acceptCheckpointReceipt: vi.fn(async (_binding, commandId) => {
        queued = queued.filter((command) => command.commandId !== commandId)
      }),
      acceptReceipt: vi.fn(async () => undefined),
      available: () => true,
      enablePersonalDevice: vi.fn(async () => undefined),
      hasProfile: vi.fn(async () => true),
      loadBundle: vi.fn(async () => null),
      loadCheckpointDetail: vi.fn(async () => checkpointDetail),
      loadLevelBundle: vi.fn(async () => storedLevelBundle),
      loadLevelQuizDraft: vi.fn(async () => savedDraft),
      saveLevelBundle: vi.fn(async () => undefined),
      saveLevelQuizDraft: vi.fn(async (_binding, draft) => {
        savedDraft = draft
      }),
      deleteLevelQuizDraft: vi.fn(async (_binding, _level, _part, attemptId) => {
        if (savedDraft?.attemptId === attemptId) {
          savedDraft = null
        }
      }),
      markCheckpointNeedsAttention,
      markNeedsAttention: vi.fn(async () => undefined),
      pendingCheckpointCommands: vi.fn(async () => queued),
      pendingCommands: vi.fn(async () => []),
      prepareLogout: vi.fn(async () => undefined),
      purge: vi.fn(async () => undefined),
      purgeAll: vi.fn(async () => undefined),
      queueCheckpointSubmission: vi.fn(async (_binding, command) => {
        lastQueued = command
        queued = [command]
      }),
      queueCompletion: vi.fn(async () => undefined),
      resumePersonalDevice: vi.fn(async () => null),
      saveBundle: vi.fn(async () => undefined),
      saveCheckpointDetail: vi.fn(async () => undefined),
    }

    let createIdCall = 0

    render(
      <LevelPathPanel
        api={api}
        createClientId={() => {
          createIdCall += 1
          return createIdCall === 1 ? ids.attempt : ids.command
        }}
        locale="en-IN"
        offlineBinding={offlineBinding}
        offlineStore={store}
      />,
    )

    await user.click(
      await screen.findByRole('button', { name: 'Level 1 checkpoint' }),
    )
    await waitFor(() => {
      expect(
        screen.getByRole('heading', { name: 'Level checkpoint' }),
      ).toBeInTheDocument()
    })

    Object.defineProperty(globalThis.navigator, 'onLine', {
      configurable: true,
      value: false,
    })
    await act(async () => {
      globalThis.dispatchEvent(new Event('offline'))
      await Promise.resolve()
    })

    await user.click(
      await screen.findByRole('radio', { name: 'I can swim.' }),
    )
    expect(
      screen.queryByText('Which sentence asks permission correctly?'),
    ).not.toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Continue' }))
    expect(
      screen.getByText('Which sentence asks permission correctly?'),
    ).toBeInTheDocument()
    expect(
      screen.queryByText('Which sentence uses can correctly?'),
    ).not.toBeInTheDocument()
    await user.click(screen.getByRole('radio', { name: 'Can I sit here?' }))
    await user.click(screen.getByRole('button', { name: 'Submit checkpoint' }))

    expect(
      await screen.findByRole('heading', {
        name: 'Mission saved securely on this device.',
      }),
    ).toBeInTheDocument()
    expect(
      screen.getByText(
        'Saved on this device. Your result is not authoritative until the server verifies it.',
      ),
    ).toBeInTheDocument()
    expect(submitCheckpoint).not.toHaveBeenCalled()
    expect(store.queueCheckpointSubmission).toHaveBeenCalledWith(
      offlineBinding,
      expect.objectContaining({
        answers: { 'can-cp-01': 0, 'can-cp-02': 0 },
        blueprintContentHash: checkpointDetail.blueprint_content_hash,
        bundleId: storedLevelBundle.bundle.manifest.bundle_id,
        commandId: ids.command,
        idempotencyKey: ids.attempt,
        lease: storedLevelBundle.bundle.lease,
        levelNumber: 1,
        state: 'pending',
      }),
    )
    expect(screen.queryByText('Level 2 is now open.')).not.toBeInTheDocument()

    Object.defineProperty(globalThis.navigator, 'onLine', {
      configurable: true,
      value: true,
    })
    await act(async () => {
      globalThis.dispatchEvent(new Event('online'))
      await Promise.resolve()
    })

    await waitFor(() => {
      expect(submitOfflineLevelCheckpoint).toHaveBeenCalledWith(
        storedLevelBundle.bundle.manifest.bundle_id,
        storedLevelBundle.bundle.lease,
        1,
        { 'can-cp-01': 0, 'can-cp-02': 0 },
        ids.attempt,
      )
    })
    expect(submitCheckpoint).not.toHaveBeenCalled()
    expect(store.acceptCheckpointReceipt).toHaveBeenCalledWith(
      offlineBinding,
      ids.command,
    )
    await waitFor(() => {
      expect(screen.getByText('Level 2 is now open.')).toBeInTheDocument()
    })
    expect(
      screen.getByText('The server verified your saved completion.'),
    ).toBeInTheDocument()

    expect(lastQueued).not.toBeNull()
    queued = [{
      ...lastQueued!,
      commandId: '018f4f82-6400-7a12-8f4b-20b12bf6b6f6',
    }]
    submitOfflineLevelCheckpoint.mockRejectedValueOnce(
      new Error('level_sessions_http_409'),
    )
    Object.defineProperty(globalThis.navigator, 'onLine', {
      configurable: true,
      value: false,
    })
    await act(async () => {
      globalThis.dispatchEvent(new Event('offline'))
      await Promise.resolve()
    })
    Object.defineProperty(globalThis.navigator, 'onLine', {
      configurable: true,
      value: true,
    })
    await act(async () => {
      globalThis.dispatchEvent(new Event('online'))
      await Promise.resolve()
    })

    await waitFor(() => {
      expect(markCheckpointNeedsAttention).toHaveBeenCalledWith(
        offlineBinding,
        '018f4f82-6400-7a12-8f4b-20b12bf6b6f6',
      )
    })
    expect(
      screen.getByText(
        'The saved completion could not be verified yet. It remains on this device for another safe retry.',
      ),
    ).toBeInTheDocument()
  })
})
