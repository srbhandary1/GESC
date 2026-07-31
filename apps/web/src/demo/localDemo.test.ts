import { describe, expect, it } from 'vitest'
import {
  createLocalDemoRuntime,
  isLocalDemoEnabled,
} from './localDemo'

describe('local demo boundary', () => {
  it('can be enabled only in a development runtime', () => {
    expect(
      isLocalDemoEnabled({ development: true, requested: true }),
    ).toBe(true)
    expect(
      isLocalDemoEnabled({ development: false, requested: true }),
    ).toBe(false)
    expect(
      isLocalDemoEnabled({ development: true, requested: false }),
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
        'Thank you for sharing that. What would you like to talk about next?',
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
})
