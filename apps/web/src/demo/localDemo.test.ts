import { describe, expect, it } from 'vitest'
import {
  createLocalDemoRuntime,
  isLocalDemoEnabled,
} from './localDemo'

describe('local demo boundary', () => {
  it('can be enabled only in a development runtime or the explicit demo distribution build', () => {
    expect(
      isLocalDemoEnabled({
        development: true,
        distribution: false,
        requested: true,
      }),
    ).toBe(true)
    expect(
      isLocalDemoEnabled({
        development: false,
        distribution: true,
        requested: true,
      }),
    ).toBe(true)
    expect(
      isLocalDemoEnabled({
        development: false,
        distribution: false,
        requested: true,
      }),
    ).toBe(false)
    expect(
      isLocalDemoEnabled({
        development: true,
        distribution: false,
        requested: false,
      }),
    ).toBe(false)
    expect(
      isLocalDemoEnabled({
        development: false,
        distribution: true,
        requested: false,
      }),
    ).toBe(false)
  })

  it('provides a complete in-memory learner result without spoken authority', async () => {
    const { authentication, learnerJourneyApi } = createLocalDemoRuntime()
    const session = await authentication.current()
    const home = await learnerJourneyApi.home()
    const mission = await learnerJourneyApi.mission(
      home.next_mission!.id,
      home.enrollment!.id,
    )
    const attempt = await learnerJourneyApi.startAttempt(
      mission.id,
      mission.enrollment_id,
      'demo-start-command',
    )

    await learnerJourneyApi.recordResponse(
      attempt.id,
      {
        check_id: mission.check.id,
        local_recording: null,
        selected_option_id: 'correct-option',
      },
      'demo-response-command',
    )
    const result = await learnerJourneyApi.submitAttempt(
      attempt.id,
      'demo-submit-command',
    )

    expect(session.status).toBe('authenticated')
    expect(home.next_mission?.title.text).toBe('Introduce yourself')
    expect(mission.model_audio.stream_url).toMatch(
      /^data:audio\/wav;base64,/u,
    )
    expect(result).toMatchObject({
      check_state: 'correct',
      knowledge_state: 'mastered',
      local_capture_receipt_present: false,
      spoken_state: 'unassessed',
    })
  })

  it('returns a deterministic text-only conversation with zero cost', async () => {
    const { conversationApi } = createLocalDemoRuntime()
    const session = await conversationApi.startSession(
      {
        coaching_mode: 'gentle',
        persona_key: 'patient-friend-prototype',
        scenario_key: 'everyday-friend-prototype',
      },
      'demo-session-command',
    )
    const turn = await conversationApi.createTurn(
      session.id,
      { prototype_utterance: 'I practise English after work.' },
      'demo-turn-command',
    )
    const events = await conversationApi.events(turn.events_url)

    expect(turn).toMatchObject({
      assistant_reply:
        'That sounds interesting. What happened next?',
      speech: {
        playback_available: false,
        rendering_mode: 'deterministic-fake-text-only',
      },
      transcript: 'I practise English after work.',
      usage: { estimated_cost_microunits: 0 },
    })
    expect(events.map(({ state }) => state)).toEqual([
      'accepted',
      'transcribing',
      'generating_reply',
      'synthesizing',
      'completed',
    ])
    expect('voiceConsents' in conversationApi).toBe(false)
  })

  it('rotates deterministic follow-up replies across ordinary turns', async () => {
    const { conversationApi } = createLocalDemoRuntime()
    const session = await conversationApi.startSession(
      {
        coaching_mode: 'gentle',
        persona_key: 'patient-friend-prototype',
        scenario_key: 'everyday-friend-prototype',
      },
      'demo-session-command',
    )

    const replies: Array<string | null> = []
    for (let index = 0; index < 5; index += 1) {
      const turn = await conversationApi.createTurn(
        session.id,
        { prototype_utterance: `Ordinary practice turn ${index}.` },
        `demo-turn-command-${index}`,
      )
      replies.push(turn.assistant_reply)
    }

    expect(replies).toEqual([
      'That sounds interesting. What happened next?',
      'I see. How did that make you feel?',
      'Nice! Tell me a little more about it.',
      'Thank you for sharing that. What would you like to talk about next?',
      'That sounds interesting. What happened next?',
    ])
  })

  it('answers curated beginner patterns with one gentle deterministic tip', async () => {
    const { conversationApi } = createLocalDemoRuntime()
    const session = await conversationApi.startSession(
      {
        coaching_mode: 'gentle',
        persona_key: 'patient-friend-prototype',
        scenario_key: 'everyday-friend-prototype',
      },
      'demo-session-command',
    )

    const cases: ReadonlyArray<readonly [string, string]> = [
      ['Yesterday I go to the market.', 'use the past form'],
      ['I am agree with my manager.', 'say "I agree"'],
      ["I didn't went to class.", 'keep the base verb'],
      ["He don't like tea.", 'say "doesn\'t"'],
    ]

    for (const [utterance, expected] of cases) {
      const turn = await conversationApi.createTurn(
        session.id,
        { prototype_utterance: utterance },
        `demo-turn-${expected}`,
      )

      expect(turn.assistant_reply).toContain('One gentle tip')
      expect(turn.assistant_reply).toContain(expected)
    }
  })

  it('reports demo progress for the dynamic and static objectives', async () => {
    const { learnerJourneyApi } = createLocalDemoRuntime()
    const home = await learnerJourneyApi.home()
    const progress = await learnerJourneyApi.progress(
      home.enrollment!.id,
    )

    expect(progress.objectives).toHaveLength(2)
    expect(progress.objectives[0]!.label.text).toBe('Introduce myself')
    expect(progress.objectives[1]).toMatchObject({
      knowledge_state: 'developing',
      spoken_state: 'unassessed',
    })
    expect(progress.objectives[1]!.label.text).toBe(
      'Greet a colleague at work',
    )
  })
})
