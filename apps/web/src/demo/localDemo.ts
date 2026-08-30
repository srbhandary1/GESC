import type { AuthenticationAdapter } from '../auth/AuthenticationAdapter'
import type {
  ConversationJourneyApi,
  CreateFriendTurnInput,
  FriendSession,
  FriendTurn,
  FriendTurnEvent,
  StartFriendSessionInput,
} from '../conversation/ConversationJourneyApi'
import type {
  AttemptResponseInput,
  GovernedContent,
  LearnerAttempt,
  LearnerHome,
  LearnerJourneyApi,
  LearnerLevelVideoCatalog,
  LearnerMission,
  LearnerProgress,
  MissionResult,
  OnboardingInput,
} from '../learner/LearnerJourneyApi'
import type {
  CheckSubmissionResult,
  LevelSessionDetail,
  LevelSessionsApi,
  LevelSessionsList,
  SessionPartId,
} from '../learner/LevelSessionsApi'
import { encodeDemoSpeechUrl } from './browserSpeech'
import { requestDemoChatReply } from './demoChat'

const ids = {
  asset: '01J30000000000000000000001',
  attempt: '01J30000000000000000000002',
  best: '01J30000000000000000000003',
  enrollment: '01J30000000000000000000004',
  first: '01J30000000000000000000005',
  latest: '01J30000000000000000000006',
  membership: '01J30000000000000000000007',
  mission: '01J30000000000000000000008',
  objective: '01J30000000000000000000009',
  organization: '01J30000000000000000000010',
  persona: '01J30000000000000000000011',
  personaVersion: '01J30000000000000000000012',
  publication: '01J30000000000000000000013',
  scenario: '01J30000000000000000000014',
  scenarioVersion: '01J30000000000000000000015',
  session: '01J30000000000000000000016',
  turn: '01J30000000000000000000017',
  user: '01J30000000000000000000018',
  greetingObjective: '01J30000000000000000000020',
} as const

export interface LocalDemoEnvironment {
  readonly development: boolean
  /**
   * True only for the explicit `vite build --mode demo` distribution
   * artifact. The ordinary production build mode never sets this.
   */
  readonly distribution: boolean
  readonly requested: boolean
}

export function isLocalDemoEnabled(
  environment: LocalDemoEnvironment,
): boolean {
  return (
    (environment.development || environment.distribution) &&
    environment.requested
  )
}

export function createLocalDemoRuntime() {
  return {
    authentication: demoAuthentication,
    conversationApi: new LocalDemoConversationApi(),
    learnerJourneyApi: new LocalDemoLearnerApi(),
    levelSessionsApi: new LocalDemoLevelSessionsApi(),
  } as const
}

const demoAuthentication: AuthenticationAdapter = {
  transport: 'sanctum-cookie',
  current: async () => ({
    status: 'authenticated',
    identity: {
      current_membership: {
        id: ids.membership,
        membership_type: 'learner',
        organization: {
          id: ids.organization,
          kind: 'platform_direct',
          name: 'Global English Local Demo',
        },
        role_keys: ['local-demo-learner'],
        status: 'active',
      },
      memberships: [],
      user: { id: ids.user },
    },
  }),
  logout: async () => undefined,
}

class LocalDemoLearnerApi implements LearnerJourneyApi {
  #attemptNumber = 0
  #hasCaptureReceipt = false
  #selectedOptionId: string | null = null

  async home(signal?: AbortSignal): Promise<LearnerHome> {
    abortIfNeeded(signal)
    return demoHome()
  }

  async progressDashboard(signal?: AbortSignal): Promise<import('../learner/LearnerJourneyApi').LearnerProgressDashboard> {
    abortIfNeeded(signal)
    return {
      enrollment: { id: ids.enrollment, status: 'active' },
      objectives: [],
      speaking_activity: {
        meaningful_speaking_days_count: 5,
        completed_turn_count: 10,
        speaking_duration_ms: 120000,
        speaking_minutes: 2,
        timezone: 'Asia/Kolkata',
      },
      attempt_improvement: {
        missions_attempted: 2,
        missions_passed_on_first_attempt: 1,
        missions_improved_on_retry: 1,
        missions_still_not_mastered: 0,
      },
      delayed_review: {
        scheduled_count: 0,
        due_count: 0,
        pending_count: 0,
        completed_count: 0,
        completed_on_time_count: 0,
        completed_late_count: 0,
      },
      recurring_errors: { patterns: [] },
    }
  }

  async updateOnboarding(
    _input: OnboardingInput,
    _commandId: string,
    signal?: AbortSignal,
  ): Promise<LearnerHome> {
    abortIfNeeded(signal)
    return demoHome()
  }

  async listLevelVideos(
    enrollmentId: string,
    signal?: AbortSignal,
  ): Promise<LearnerLevelVideoCatalog> {
    abortIfNeeded(signal)
    return { enrollment_id: enrollmentId, videos: [] }
  }

  async mission(
    _missionId: string,
    _enrollmentId: string,
    signal?: AbortSignal,
  ): Promise<LearnerMission> {
    abortIfNeeded(signal)
    return demoMission()
  }

  async startAttempt(
    _missionId: string,
    _enrollmentId: string,
    _commandId: string,
    signal?: AbortSignal,
  ): Promise<LearnerAttempt> {
    abortIfNeeded(signal)
    this.#attemptNumber += 1
    this.#hasCaptureReceipt = false
    this.#selectedOptionId = null
    return { id: ids.attempt, status: 'in_progress' }
  }

  async recordResponse(
    _attemptId: string,
    input: AttemptResponseInput,
    _commandId: string,
    signal?: AbortSignal,
  ): Promise<void> {
    abortIfNeeded(signal)
    this.#selectedOptionId = input.selected_option_id
    this.#hasCaptureReceipt = input.local_recording !== null
  }

  async submitAttempt(
    _attemptId: string,
    _commandId: string,
    signal?: AbortSignal,
  ): Promise<MissionResult> {
    abortIfNeeded(signal)
    return demoResult(
      this.#attemptNumber,
      this.#selectedOptionId === 'correct-option',
      this.#hasCaptureReceipt,
    )
  }

  async progress(
    _enrollmentId: string,
    signal?: AbortSignal,
  ): Promise<LearnerProgress> {
    abortIfNeeded(signal)
    const result = demoResult(
      Math.max(this.#attemptNumber, 1),
      this.#selectedOptionId === 'correct-option',
      this.#hasCaptureReceipt,
    )

    return {
      enrollment_id: ids.enrollment,
      objectives: [
        {
          evidence: result.evidence,
          knowledge_state: result.knowledge_state,
          label: content('en-IN', 'Introduce myself'),
          local_capture_receipt_present:
            result.local_capture_receipt_present,
          objective_id: ids.objective,
          review_due_at: result.review.due_at,
          review_timezone: result.review.timezone,
          spoken_state: 'unassessed',
        },
        {
          evidence: {
            best_attempt_id: ids.best,
            best_attempt_number: 1,
            first_attempt_id: ids.first,
            first_attempt_number: 1,
            latest_attempt_id: ids.latest,
            latest_attempt_number: 1,
          },
          knowledge_state: 'developing',
          label: content('en-IN', 'Greet a colleague at work'),
          local_capture_receipt_present: false,
          objective_id: ids.greetingObjective,
          review_due_at: '2026-08-02T09:00:00Z',
          review_timezone: 'Asia/Kolkata',
          spoken_state: 'unassessed',
        },
      ],
    }
  }
}

class LocalDemoLevelSessionsApi implements LevelSessionsApi {
  #completions = new Set<SessionPartId>()

  async listLevels(signal?: AbortSignal) {
    abortIfNeeded(signal)
    const levels = Array.from({ length: 17 }, (_, index) => {
      const levelNumber = index + 1
      if (levelNumber === 1) {
        return {
          level_number: 1,
          concept_key: 'can',
          availability: 'available' as const,
          blueprint_id: '01J30000000000000000000030',
          session_part_ids: [
            'introduction',
            'practice',
            'conversation',
            'interaction',
          ],
        }
      }
      return {
        level_number: levelNumber,
        concept_key: null,
        availability: 'commissioning_pending' as const,
        blueprint_id: null,
        session_part_ids: [
          'introduction',
          'practice',
          'conversation',
          'interaction',
        ],
      }
    })
    return { levels }
  }

  async listSessions(
    levelNumber: number,
    signal?: AbortSignal,
  ): Promise<LevelSessionsList> {
    abortIfNeeded(signal)
    if (levelNumber !== 1) {
      throw new Error('level_unavailable')
    }
    const parts: SessionPartId[] = [
      'introduction',
      'practice',
      'conversation',
      'interaction',
    ]
    return {
      level_number: 1,
      concept_key: 'can',
      blueprint_id: '01J30000000000000000000030',
      blueprint_content_hash: 'd'.repeat(64),
      sessions: parts.map((partId, index) => ({
        part_id: partId,
        part_order: index + 1,
        media_asset_id: ids.asset,
        completed: this.#completions.has(partId),
      })),
    }
  }

  async sessionDetail(
    levelNumber: number,
    partId: SessionPartId,
    signal?: AbortSignal,
  ): Promise<LevelSessionDetail> {
    abortIfNeeded(signal)
    if (levelNumber !== 1) {
      throw new Error('level_unavailable')
    }
    const order = (
      ['introduction', 'practice', 'conversation', 'interaction'] as const
    ).indexOf(partId)
    return {
      level_number: 1,
      part_id: partId,
      part_order: order + 1,
      completed: this.#completions.has(partId),
      media_asset_id: ids.asset,
      audio_stream_path: encodeDemoSpeechUrl(
        'en-IN',
        'I can speak English. Can I help you?',
      ),
      objective:
        'By the end of this lesson, the learner can use can and cannot.',
      target_patterns: ['I can + base verb.', 'Can I + base verb?'],
      kannada_guidance: {
        summary: 'ಈ ಪಾಠದಲ್ಲಿ can ಬಳಕೆಯನ್ನು ಕಲಿಯಿರಿ.',
      },
      talk: {
        scenario: 'Ask for permission at a community centre.',
        elicitation_goals: ['Ask one Can I...? question.'],
        fallback_turns: ['Can I use your pen?'],
        free_speech: partId === 'conversation' || partId === 'interaction',
      },
      apply:
        partId === 'interaction'
          ? {
              task: 'State one ability and ask permission.',
              rubric: ['Uses one accurate I can statement'],
              free_speech: true,
            }
          : null,
      check:
        partId === 'practice'
          ? {
              pass_threshold: 80,
              items: [
                {
                  id: 'can-01',
                  weight: 35,
                  prompt: 'Choose the correct sentence.',
                  options: [
                    'I can speak English.',
                    'I can speaking English.',
                    'I can to speak English.',
                  ],
                },
                {
                  id: 'can-02',
                  weight: 30,
                  prompt: 'Choose the permission question.',
                  options: [
                    'Can I use your pen?',
                    'I can use your pen?',
                    'Can I using your pen?',
                  ],
                },
                {
                  id: 'can-03',
                  weight: 35,
                  prompt: 'Choose the answer.',
                  options: [
                    'Yes, you can.',
                    'Yes, you can to.',
                    'Yes, you can sitting.',
                  ],
                },
              ],
            }
          : null,
      activities: [
        {
          id: 'listen',
          prompt: `Listen to the ${partId} audio once for meaning.`,
        },
        {
          id: 'repeat',
          prompt: 'Repeat one useful line aloud.',
        },
      ],
      conversation_binding: {
        prototype:
          partId === 'introduction' ? 'skill_focus' : 'confidence_friend',
        coaching_mode: partId === 'introduction' ? 'tutor' : 'friend',
      },
    }
  }

  async completeSession(
    _levelNumber: number,
    partId: SessionPartId,
    _idempotencyKey: string,
    signal?: AbortSignal,
  ) {
    abortIfNeeded(signal)
    this.#completions.add(partId)
    return {
      replayed: false,
      completion_id: '01J30000000000000000000031',
      part_id: partId,
    }
  }

  async submitCheck(
    _levelNumber: number,
    _partId: SessionPartId,
    answers: Record<string, number>,
    _idempotencyKey: string,
    signal?: AbortSignal,
  ): Promise<CheckSubmissionResult> {
    abortIfNeeded(signal)
    const keys = ['can-01', 'can-02', 'can-03'] as const
    const itemResults = keys.map((id) => {
      const selected = answers[id] ?? -1
      return {
        id,
        selected_option: selected,
        correct: selected === 0,
        rationale: selected === 0 ? 'Correct.' : 'Try the base-verb form.',
      }
    })
    const score = Math.round(
      (itemResults.filter((item) => item.correct).length / itemResults.length) *
        100,
    )
    return {
      replayed: false,
      score,
      pass_threshold: 80,
      passed: score >= 80,
      item_results: itemResults,
    }
  }

  async startConversation(
    _levelNumber: number,
    _partId: SessionPartId,
    uiLocale: string,
    _idempotencyKey: string,
    signal?: AbortSignal,
  ) {
    abortIfNeeded(signal)
    return demoFriendSession(uiLocale)
  }
}

class LocalDemoConversationApi implements ConversationJourneyApi {
  #turnNumber = 0
  #roleplaySession: FriendSession | null = null

  async startSession(
    input: StartFriendSessionInput,
    _commandId: string,
    signal?: AbortSignal,
  ): Promise<FriendSession> {
    abortIfNeeded(signal)

    const isRoleplay =
      input.persona_key === 'clinic-reception-roleplay-prototype' ||
      input.scenario_key === 'clinic-reception-roleplay-scenario'

    if (isRoleplay) {
      this.#roleplaySession = demoRoleplaySession('observe', input.ui_locale)
      return this.#roleplaySession
    }

    return demoFriendSession(input.ui_locale, input.coaching_mode)
  }

  async advanceStage(
    _sessionId: string,
    input: { readonly to_stage: string },
    _commandId: string,
    signal?: AbortSignal,
  ): Promise<FriendSession> {
    abortIfNeeded(signal)
    const stage = input.to_stage as
      | 'observe'
      | 'rehearse'
      | 'perform'
      | 'reflect'
    this.#roleplaySession = demoRoleplaySession(
      stage,
      this.#roleplaySession?.ui_locale ?? 'en-IN',
      stage === 'reflect',
    )
    return this.#roleplaySession
  }

  async createTurn(
    _sessionId: string,
    input: CreateFriendTurnInput,
    _commandId: string,
    signal?: AbortSignal,
  ): Promise<FriendTurn> {
    abortIfNeeded(signal)
    this.#turnNumber += 1

    const openRouter = await requestDemoChatReply(input.prototype_utterance)
    abortIfNeeded(signal)
    const assistantReply =
      openRouter?.reply ??
      demoFriendReply(input.prototype_utterance, this.#turnNumber)

    return {
      assistant_reply: assistantReply,
      conversation_session_id: ids.session,
      events_url: `local-demo://turn/${this.#turnNumber}`,
      id: ids.turn,
      priority_correction: {
        correction_en:
          'A clearer sentence is: "I enjoy learning English after work."',
        error_category: 'grammar.article',
        explanation_l1:
          'ಸ್ಪಷ್ಟವಾಗಿ ಹೇಳುವುದಾದರೆ: "I enjoy learning English after work."',
        explanation_locale: 'kn-IN',
        explanation_kn:
          'ಸ್ಪಷ್ಟವಾಗಿ ಹೇಳುವುದಾದರೆ: "I enjoy learning English after work."',
        needed: true,
        retry_recommended: false,
      },
      replayed: false,
      safety_state: 'ok',
      speech: {
        playback_available: false,
        rendering_mode: 'deterministic-fake-text-only',
      },
      status: 'completed',
      transcript: input.prototype_utterance,
      turn_number: this.#turnNumber,
      understood_intent: 'prototype_utterance_received',
      usage: {
        estimated_cost_microunits: 0,
        provider_attempts: openRouter === null ? 3 : 1,
        total_latency_ms: openRouter === null ? 14 : 0,
      },
    }
  }

  async events(
    _eventsUrl: string,
    signal?: AbortSignal,
  ): Promise<readonly FriendTurnEvent[]> {
    abortIfNeeded(signal)
    const states = [
      ['accepted', 'turn.accepted', null, null],
      ['transcribing', 'turn.transcribing', 'stt', 1],
      ['generating_reply', 'turn.generating_reply', 'model', 1],
      ['synthesizing', 'turn.synthesizing', 'tts', 1],
      ['completed', 'turn.completed', null, null],
    ] as const

    return states.map(
      ([state, eventCode, providerStage, providerAttempt], index) => ({
        event_code: eventCode,
        provider_attempt: providerAttempt,
        provider_stage: providerStage,
        recorded_at: '2026-07-31T12:00:00Z',
        sequence: index + 1,
        state,
      }),
    )
  }
}

function demoFriendSession(
  uiLocale: string,
  coachingMode: FriendSession['coaching_mode'] = 'gentle',
): FriendSession {
  return {
    coaching_mode: coachingMode,
    id: ids.session,
    mode: 'friend',
    stage: null,
    persona: {
      definition_hash: 'b'.repeat(64),
      disclosure:
        'I am a demo practice Friend. When OpenRouter is configured I use a free model; otherwise I use scripted replies. I am not a human.',
      display_name: 'Patient Friend Demo',
      id: ids.persona,
      key: 'patient-friend-prototype',
      review_status: 'synthetic_unreviewed',
      version: 1,
      version_id: ids.personaVersion,
    },
    replayed: false,
    scenario: {
      definition_hash: 'c'.repeat(64),
      id: ids.scenario,
      key: 'everyday-friend-prototype',
      review_status: 'synthetic_unreviewed',
      topic_code: 'everyday-conversation',
      version: 1,
      version_id: ids.scenarioVersion,
    },
    status: 'active',
    ui_locale: uiLocale as FriendSession['ui_locale'],
  }
}

function demoRoleplaySession(
  stage: 'observe' | 'rehearse' | 'perform' | 'reflect',
  uiLocale: FriendSession['ui_locale'],
  withFeedback = false,
): FriendSession {
  return {
    coaching_mode: 'gentle',
    id: ids.session,
    mode: 'roleplay',
    stage,
    persona: {
      definition_hash: 'a'.repeat(64),
      disclosure:
        'I am a deterministic AI role-play prototype, not a human.',
      display_name: 'Clinic Reception Role-play Prototype',
      id: ids.persona,
      key: 'clinic-reception-roleplay-prototype',
      review_status: 'synthetic_unreviewed',
      version: 1,
      version_id: ids.personaVersion,
    },
    replayed: false,
    scenario: {
      definition_hash: 'b'.repeat(64),
      id: ids.scenario,
      key: 'clinic-reception-roleplay-scenario',
      review_status: 'synthetic_unreviewed',
      topic_code: 'clinic-reception',
      version: 1,
      version_id: ids.scenarioVersion,
    },
    objectives: [
      {
        completion_criteria_code: 'synthetic.greet-receptionist.v1',
        id: `${ids.session}-obj-1`,
        key: 'greet-receptionist',
        title: 'Greet the receptionist',
      },
      {
        completion_criteria_code: 'synthetic.state-appointment-reason.v1',
        id: `${ids.session}-obj-2`,
        key: 'state-appointment-reason',
        title: 'State the appointment reason',
      },
    ],
    feedback: withFeedback
      ? [
          {
            completion_state: 'met',
            evidence_digest: 'c'.repeat(64),
            id: `${ids.session}-fb-1`,
            scenario_objective_id: `${ids.session}-obj-1`,
          },
          {
            completion_state: 'unmet',
            evidence_digest: 'd'.repeat(64),
            id: `${ids.session}-fb-2`,
            scenario_objective_id: `${ids.session}-obj-2`,
          },
        ]
      : [],
    status: 'active',
    ui_locale: uiLocale,
  }
}

interface DemoCoachingPattern {
  readonly matcher: RegExp
  readonly reply: string
}

/**
 * A tiny curated set of deterministic pattern replies so a presentation can
 * show the "respond to meaning first, then one gentle correction" behaviour
 * without any provider. This is synthetic demo scripting, not grammar
 * analysis, and it produces no authoritative record.
 */
const demoCoachingPatterns: readonly DemoCoachingPattern[] = [
  {
    matcher: /yesterday\s+i\s+go\b/iu,
    reply:
      'That sounds like a full day. One gentle tip: with "yesterday", use the past form — "Yesterday I went to the market." Would you like to try that once more?',
  },
  {
    matcher: /\bi\s+am\s+agree\b/iu,
    reply:
      'It is nice that we think alike. One gentle tip: say "I agree" instead of "I am agree". What do you agree with the most?',
  },
  {
    matcher: /\bdidn'?t\s+went\b/iu,
    reply:
      'Thank you for telling me. One gentle tip: after "didn\'t", keep the base verb — "I didn\'t go". Where do you usually go instead?',
  },
  {
    matcher: /\b(?:he|she)\s+don'?t\b/iu,
    reply:
      'I understand. One gentle tip: with "he" or "she", say "doesn\'t" — "He doesn\'t like tea." Does that match what you meant?',
  },
]

const demoFriendFollowUps = [
  'That sounds interesting. What happened next?',
  'I see. How did that make you feel?',
  'Nice! Tell me a little more about it.',
  'Thank you for sharing that. What would you like to talk about next?',
] as const

function demoFriendReply(
  utterance: string,
  turnNumber: number,
): string {
  for (const pattern of demoCoachingPatterns) {
    if (pattern.matcher.test(utterance)) {
      return pattern.reply
    }
  }

  return (
    demoFriendFollowUps[
      (turnNumber - 1) % demoFriendFollowUps.length
    ] ?? demoFriendFollowUps[0]
  )
}

function demoHome(): LearnerHome {
  return {
    due_review: null,
    enrollment: { id: ids.enrollment, status: 'active' },
    next_mission: {
      estimated_minutes: 5,
      id: ids.mission,
      title: content('en-IN', 'Introduce yourself'),
    },
    onboarding: {
      adult_confirmed: true,
      completed: true,
      goal_key: 'workplace-confidence',
    },
    streak: {
      as_of_date: '2026-08-30',
      count: 2,
      timezone: 'Asia/Kolkata',
    },
  }
}

function demoMission(): LearnerMission {
  return {
    check: {
      id: 'check-one',
      options: [
        {
          id: 'correct-option',
          label: content('en-IN', 'Hello, my name is Ravi.'),
        },
        {
          id: 'incorrect-option',
          label: content('en-IN', 'Goodbye yesterday.'),
        },
      ],
      prompt: content('en-IN', 'Choose the correct introduction.'),
    },
    enrollment_id: ids.enrollment,
    id: ids.mission,
    kannada_guidance: content('kn-IN', 'ನಿಮ್ಮ ಹೆಸರನ್ನು ಹೇಳಿ.'),
    model_audio: {
      asset_id: ids.asset,
      duration_ms: 2_400,
      // Contract mime remains a media type; playback is browser TTS via the
      // demo-speech: stream URL, not a WAV byte stream.
      mime_type: 'audio/wav',
      stream_url: encodeDemoSpeechUrl(
        'en-IN',
        'Hello, my name is Ravi.',
      ),
    },
    model_text: content('en-IN', 'Hello, my name is Ravi.'),
    objective_id: ids.objective,
    publication_id: ids.publication,
    title: content('en-IN', 'Introduce yourself'),
    transcript: content('en-IN', 'Hello, my name is Ravi.'),
  }
}

function demoResult(
  attemptNumber: number,
  correct: boolean,
  hasCaptureReceipt: boolean,
): MissionResult {
  const boundedAttempt = Math.max(attemptNumber, 1)

  return {
    attempt_id: ids.attempt,
    can_do: content('en-IN', 'I can introduce myself.'),
    check_state: correct ? 'correct' : 'incorrect',
    evidence: {
      best_attempt_id: ids.best,
      best_attempt_number: correct ? boundedAttempt : 1,
      first_attempt_id: ids.first,
      first_attempt_number: 1,
      latest_attempt_id: ids.latest,
      latest_attempt_number: boundedAttempt,
    },
    knowledge_state: correct ? 'mastered' : 'developing',
    local_capture_receipt_present: hasCaptureReceipt,
    review: {
      due_at: '2026-08-01T09:00:00Z',
      timezone: 'Asia/Kolkata',
    },
    spoken_state: 'unassessed',
  }
}

function content(
  locale: GovernedContent['locale'],
  text: string,
): GovernedContent {
  return {
    content_hash: 'a'.repeat(64),
    locale,
    revision_id: '01J30000000000000000000019',
    text,
  }
}

function abortIfNeeded(signal?: AbortSignal): void {
  if (signal?.aborted === true) {
    throw new DOMException('The local demo operation was aborted.', 'AbortError')
  }
}
