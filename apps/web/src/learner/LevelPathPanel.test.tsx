import { cleanup, render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import type {
  OfflineLearningStore,
  OfflineProfileBinding,
  PendingLevelCheckpointCommand,
} from '../offline/OfflineLearningStore'
import { LevelPathPanel } from './LevelPathPanel'
import type {
  LevelCheckpointDetail,
  LevelCheckpointSubmissionResult,
  LevelSessionsApi,
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

beforeEach(() => {
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
    expect(
      screen.getByRole('heading', { name: 'Can — ability and permission' }),
    ).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Open level' })).toBeInTheDocument()
    expect(screen.getByText(/Coming next/)).toBeInTheDocument()
    expect(screen.getByText('Level 2')).toBeInTheDocument()
    expect(
      screen.queryByText(/Listen to the lesson, answer your teacher/),
    ).not.toBeInTheDocument()
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
    expect(screen.getByRole('heading', { name: 'Do this' })).toBeInTheDocument()
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

    await user.click(await screen.findByRole('button', { name: /ಮಟ್ಟ 1/ }))
    await waitFor(() => {
      expect(document.querySelector('.level-session__kannada')).toHaveTextContent(
        'ಕನ್ನಡ ವಿವರಣೆ',
      )
    })
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
      listSessions: vi.fn(),
      sessionDetail: vi.fn(),
      completeSession: vi.fn(),
      submitCheck: vi.fn(),
      startConversation: vi.fn(),
    }

    render(<LevelPathPanel api={api} locale="en-IN" />)

    expect(await screen.findByText('Review due first')).toBeInTheDocument()
    expect(
      screen.getByText(
        'Complete your due review on Home before taking the level checkpoint.',
      ),
    ).toBeInTheDocument()
    expect(screen.queryByText('Checkpoint ready')).not.toBeInTheDocument()
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
    expect(api.startConversation).toHaveBeenCalled()
    expect(
      await screen.findByText('Listen. Say: I can drink water. Now you try.'),
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
    await user.click(await screen.findByRole('button', { name: 'Ask AI Tutor' }))

    expect(screen.getByText('I can + base verb.')).toBeInTheDocument()
    expect(
      screen.getByText('Listen. Say: I can drink water. Now you try.'),
    ).toBeInTheDocument()
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
    expect(screen.getByText(nextModel)).toBeInTheDocument()
    expect(
      screen.queryByText(/Do not start a free conversation/u),
    ).not.toBeInTheDocument()
    expect(
      screen.queryByText(
        'A clearer sentence is: "I enjoy learning English after work."',
      ),
    ).not.toBeInTheDocument()
  })

  it('queues checkpoint answers offline and syncs authoritative results on reconnect', async () => {
    const user = userEvent.setup()
    const ids = {
      command: '018f4f82-6400-7a12-8f4b-20b12bf6b6f4',
      idempotency: '018f4f82-6400-7a12-8f4b-20b12bf6b6f5',
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
      saveLevelBundle: vi.fn(async () => undefined),
      markCheckpointNeedsAttention: vi.fn(async () => undefined),
      markNeedsAttention: vi.fn(async () => undefined),
      pendingCheckpointCommands: vi.fn(async () => queued),
      pendingCommands: vi.fn(async () => []),
      prepareLogout: vi.fn(async () => undefined),
      purge: vi.fn(async () => undefined),
      purgeAll: vi.fn(async () => undefined),
      queueCheckpointSubmission: vi.fn(async (_binding, command) => {
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
          return createIdCall === 1 ? ids.command : ids.idempotency
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
    globalThis.dispatchEvent(new Event('offline'))

    await user.click(screen.getByRole('radio', { name: 'I can swim.' }))
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
        answers: { 'can-cp-01': 0 },
        blueprintContentHash: checkpointDetail.blueprint_content_hash,
        bundleId: storedLevelBundle.bundle.manifest.bundle_id,
        commandId: ids.command,
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
    globalThis.dispatchEvent(new Event('online'))

    await waitFor(() => {
      expect(submitOfflineLevelCheckpoint).toHaveBeenCalledWith(
        storedLevelBundle.bundle.manifest.bundle_id,
        storedLevelBundle.bundle.lease,
        1,
        { 'can-cp-01': 0 },
        ids.idempotency,
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
  })
})
