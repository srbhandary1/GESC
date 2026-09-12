import { cleanup, render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { LevelPathPanel } from './LevelPathPanel'
import type { LevelSessionsApi } from './LevelSessionsApi'

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
        key: 'everyday-friend-prototype' as const,
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
        target_patterns: [],
        kannada_guidance: { summary: 'ಕನ್ನಡ ವಿವರಣೆ' },
        talk: {
          scenario: 'Ask for permission.',
          elicitation_goals: ['Ask one Can I...? question.'],
          fallback_turns: ['Can I use your pen?'],
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
      await screen.findByText(
        'Your teacher is ready here. Speak your answers. Corrections appear in your language.',
      ),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('heading', {
        name: 'Speak with a patient AI Friend',
      }),
    ).toBeInTheDocument()
    expect(
      screen.getAllByRole('heading', { name: 'Listen' }).length,
    ).toBeGreaterThan(0)
  })
})
