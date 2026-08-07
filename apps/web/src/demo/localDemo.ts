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
  LearnerMission,
  LearnerProgress,
  MissionResult,
  OnboardingInput,
} from '../learner/LearnerJourneyApi'
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

  async updateOnboarding(
    _input: OnboardingInput,
    _commandId: string,
    signal?: AbortSignal,
  ): Promise<LearnerHome> {
    abortIfNeeded(signal)
    return demoHome()
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

    return {
      coaching_mode: input.coaching_mode ?? 'gentle',
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
      ui_locale: input.ui_locale,
    }
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
