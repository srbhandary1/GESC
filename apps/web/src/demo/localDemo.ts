import type { AuthenticationAdapter } from '../auth/AuthenticationAdapter'
import type {
  ConversationJourneyApi,
  CreateFriendTurnInput,
  FriendSession,
  FriendTurn,
  FriendTurnEvent,
  StartFriendSessionInput,
} from '../conversation/ConversationJourneyApi'
import {
  lessonDrillCurrentModel,
  lessonDrillReply,
  lessonDrillRetryReply,
  lessonDrillUtteranceMatchesModel,
} from '../conversation/lessonDrillStepper'
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
import {
  DEMO_LEVEL_FIXTURES,
  demoLevelFixture,
  type DemoLevelFixture,
} from './demoLevelFixtures'

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

type DemoTalkState = {
  drillPatterns: readonly string[] | null
  drillFallbackTurns: readonly string[] | null
  drillPart: 'introduction' | 'practice' | null
  acceptedPatternCount: number
}

export function createLocalDemoRuntime() {
  const talk: DemoTalkState = {
    drillPatterns: null,
    drillFallbackTurns: null,
    drillPart: null,
    acceptedPatternCount: 0,
  }

  return {
    authentication: demoAuthentication,
    conversationApi: new LocalDemoConversationApi(talk),
    learnerJourneyApi: new LocalDemoLearnerApi(),
    levelSessionsApi: new LocalDemoLevelSessionsApi(talk),
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
      actionable_due_review: null,
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
  readonly #completions = new Map<number, Set<SessionPartId>>()
  readonly #checkpointPassed = new Set<number>()
  readonly #checkpointAttempts = new Map<number, number>()
  readonly #reviewDueBlocking: boolean
  readonly #talk: DemoTalkState

  constructor(talk: DemoTalkState, reviewDueBlocking = false) {
    this.#talk = talk
    this.#reviewDueBlocking = reviewDueBlocking
  }

  #fixture(levelNumber: number): DemoLevelFixture {
    const fixture = demoLevelFixture(levelNumber)
    if (fixture === undefined) {
      throw new Error('level_unavailable')
    }
    return fixture
  }

  #completionSet(levelNumber: number): Set<SessionPartId> {
    const existing = this.#completions.get(levelNumber)
    if (existing !== undefined) {
      return existing
    }
    const created = new Set<SessionPartId>()
    this.#completions.set(levelNumber, created)
    return created
  }

  #isLevelFullyComplete(levelNumber: number): boolean {
    const completions = this.#completionSet(levelNumber)
    return (
      completions.has('introduction') &&
      completions.has('practice') &&
      completions.has('conversation') &&
      completions.has('interaction')
    )
  }

  #isLevelUnlockReady(levelNumber: number): boolean {
    return (
      this.#isLevelFullyComplete(levelNumber) &&
      this.#checkpointPassed.has(levelNumber)
    )
  }

  #checkpointStatus(levelNumber: number) {
    if (this.#checkpointPassed.has(levelNumber)) {
      return 'passed' as const
    }
    if (!this.#isLevelFullyComplete(levelNumber)) {
      return 'not_ready' as const
    }
    if ((this.#checkpointAttempts.get(levelNumber) ?? 0) >= 3) {
      return 'attempts_exhausted' as const
    }
    if (this.#reviewDueBlocking) {
      return 'review_due_blocked' as const
    }
    return 'ready' as const
  }

  #checkpointAttemptsRemaining(levelNumber: number): number | null {
    const status = this.#checkpointStatus(levelNumber)
    if (status === 'ready' || status === 'review_due_blocked') {
      return 3 - (this.#checkpointAttempts.get(levelNumber) ?? 0)
    }
    return null
  }

  async listLevels(signal?: AbortSignal) {
    abortIfNeeded(signal)
    return {
      levels: DEMO_LEVEL_FIXTURES.map((fixture) => {
        const priorLevel = fixture.level_number - 1
        const progressUnlocked =
          fixture.level_number === 1 ||
          this.#isLevelUnlockReady(priorLevel)

        return {
          level_number: fixture.level_number,
          concept_key: fixture.concept_key,
          availability: 'available' as const,
          blueprint_id: `01J300000000000000000000${String(fixture.level_number).padStart(2, '0')}`,
          session_part_ids: [
            'introduction',
            'practice',
            'conversation',
            'interaction',
          ],
          entitlement_access: 'allowed' as const,
          progress_access: progressUnlocked
            ? ('allowed' as const)
            : ('locked' as const),
          unlock_after_level_number: progressUnlocked ? null : priorLevel,
          checkpoint_status: this.#checkpointStatus(fixture.level_number),
          checkpoint_attempts_remaining: this.#checkpointAttemptsRemaining(
            fixture.level_number,
          ),
          review_due_blocking:
            this.#checkpointStatus(fixture.level_number) ===
            'review_due_blocked',
          spoken_evidence: {
            evidence_state: 'not_recorded' as const,
            spoken_state: 'unassessed' as const,
            spoken_progress_claim: false as const,
          },
        }
      }),
    }
  }

  async listSessions(
    levelNumber: number,
    signal?: AbortSignal,
  ): Promise<LevelSessionsList> {
    abortIfNeeded(signal)
    const fixture = this.#fixture(levelNumber)
    const parts: SessionPartId[] = [
      'introduction',
      'practice',
      'conversation',
      'interaction',
    ]
    const completions = this.#completionSet(levelNumber)
    return {
      level_number: levelNumber,
      concept_key: fixture.concept_key,
      blueprint_id: `01J300000000000000000000${String(levelNumber).padStart(2, '0')}`,
      blueprint_content_hash: 'd'.repeat(64),
      sessions: parts.map((partId, index) => ({
        part_id: partId,
        part_order: index + 1,
        media_asset_id: ids.asset,
        completed: completions.has(partId),
      })),
    }
  }

  async sessionDetail(
    levelNumber: number,
    partId: SessionPartId,
    signal?: AbortSignal,
  ): Promise<LevelSessionDetail> {
    abortIfNeeded(signal)
    const fixture = this.#fixture(levelNumber)
    const order = (
      ['introduction', 'practice', 'conversation', 'interaction'] as const
    ).indexOf(partId)
    const spokenLine =
      fixture.talk.fallback_turns[0] ??
      fixture.target_patterns[0] ??
      fixture.objective
    return {
      level_number: levelNumber,
      part_id: partId,
      part_order: order + 1,
      completed: this.#completionSet(levelNumber).has(partId),
      media_asset_id: ids.asset,
      audio_stream_path: encodeDemoSpeechUrl('en-IN', spokenLine),
      objective: fixture.objective,
      target_patterns: [...fixture.target_patterns],
      kannada_guidance: {
        summary: fixture.kannada_summary,
      },
      talk: {
        scenario: fixture.talk.scenario,
        elicitation_goals: [],
        fallback_turns: [...fixture.talk.fallback_turns],
        free_speech: partId === 'conversation' || partId === 'interaction',
      },
      apply:
        partId === 'interaction'
          ? {
              task: fixture.apply.task,
              rubric: ['Uses the level pattern accurately'],
              free_speech: true,
            }
          : null,
      check:
        partId === 'practice'
          ? {
              pass_threshold: 80,
              items: fixture.check.map((item) => ({
                id: item.id,
                weight: item.weight,
                prompt: item.prompt,
                options: [...item.options],
              })),
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
          partId === 'introduction' || partId === 'practice'
            ? 'skill_focus'
            : 'confidence_friend',
        coaching_mode:
          partId === 'introduction' || partId === 'practice' ? 'tutor' : 'gentle',
      },
    }
  }

  async completeSession(
    levelNumber: number,
    partId: SessionPartId,
    _idempotencyKey: string,
    signal?: AbortSignal,
  ) {
    abortIfNeeded(signal)
    this.#completionSet(levelNumber).add(partId)
    return {
      replayed: false,
      completion_id: '01J30000000000000000000031',
      part_id: partId,
    }
  }

  async submitCheck(
    levelNumber: number,
    _partId: SessionPartId,
    answers: Record<string, number>,
    _idempotencyKey: string,
    signal?: AbortSignal,
  ): Promise<CheckSubmissionResult> {
    abortIfNeeded(signal)
    const fixture = this.#fixture(levelNumber)
    const itemResults = fixture.check.map((item) => {
      const selected = answers[item.id] ?? -1
      return {
        id: item.id,
        selected_option: selected,
        correct: selected === item.correct_option,
        rationale: selected === item.correct_option ? 'Correct.' : 'Try again.',
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
    levelNumber: number,
    partId: SessionPartId,
    uiLocale: string,
    _idempotencyKey: string,
    signal?: AbortSignal,
  ) {
    abortIfNeeded(signal)
    const fixture = this.#fixture(levelNumber)
    if (partId === 'introduction' || partId === 'practice') {
      this.#talk.drillPatterns = fixture.target_patterns
      this.#talk.drillFallbackTurns = fixture.talk.fallback_turns
      this.#talk.drillPart = partId
      this.#talk.acceptedPatternCount = 0
      return demoFriendSession(uiLocale, 'tutor')
    }

    this.#talk.drillPatterns = null
    this.#talk.drillFallbackTurns = null
    this.#talk.drillPart = null
    this.#talk.acceptedPatternCount = 0
    return demoFriendSession(uiLocale, 'gentle')
  }

  async checkpointDetail(levelNumber: number, signal?: AbortSignal) {
    abortIfNeeded(signal)
    const status = this.#checkpointStatus(levelNumber)
    if (status === 'review_due_blocked') {
      throw new Error('review_due_blocking')
    }
    if (status !== 'ready') {
      throw new Error('checkpoint_not_ready')
    }
    const fixture = this.#fixture(levelNumber)
    return {
      level_number: levelNumber,
      concept_key: fixture.concept_key,
      blueprint_id: `01J300000000000000000000${String(levelNumber).padStart(2, '0')}`,
      blueprint_content_hash: 'd'.repeat(64),
      rule_key: 'level_checkpoint_v1',
      rule_hash: 'e'.repeat(64),
      pass_threshold: 80,
      max_attempts: 3,
      attempts_remaining: 3,
      items: fixture.check.map((item) => ({
        id: `checkpoint-${item.id}`,
        weight: item.weight,
        prompt: item.prompt,
        options: item.options,
      })),
    }
  }

  async submitCheckpoint(
    levelNumber: number,
    answers: Record<string, number>,
    _idempotencyKey: string,
    signal?: AbortSignal,
  ) {
    abortIfNeeded(signal)
    const fixture = this.#fixture(levelNumber)
    const itemResults = fixture.check.map((item) => {
      const selected = answers[`checkpoint-${item.id}`] ?? -1
      return {
        id: `checkpoint-${item.id}`,
        selected_option: selected,
        correct: selected === item.correct_option,
        rationale: selected === item.correct_option ? 'Correct.' : 'Try again.',
      }
    })
    const score = Math.round(
      (itemResults.filter((item) => item.correct).length / itemResults.length) *
        100,
    )
    const passed = score >= 80
    const attemptNumber = (this.#checkpointAttempts.get(levelNumber) ?? 0) + 1
    this.#checkpointAttempts.set(levelNumber, attemptNumber)
    if (passed) {
      this.#checkpointPassed.add(levelNumber)
    }
    const remaining = passed
      ? null
      : Math.max(0, 3 - attemptNumber)
    return {
      replayed: false,
      attempt_id: '01J30000000000000000000041',
      attempt_number: attemptNumber,
      score,
      pass_threshold: 80,
      passed,
      attempts_remaining: remaining,
      item_results: itemResults,
      spoken_evidence: {
        evidence_state: 'not_recorded' as const,
        spoken_state: 'unassessed' as const,
        spoken_progress_claim: false as const,
      },
      level_unlock: passed
        ? {
            replayed: false,
            unlock_decision_id: '01J30000000000000000000042',
            level_number: levelNumber + 1,
          }
        : null,
    }
  }
}

class LocalDemoConversationApi implements ConversationJourneyApi {
  #turnNumber = 0
  #roleplaySession: FriendSession | null = null
  readonly #talk: DemoTalkState

  constructor(talk: DemoTalkState) {
    this.#talk = talk
  }

  async startSession(
    input: StartFriendSessionInput,
    _commandId: string,
    signal?: AbortSignal,
  ): Promise<FriendSession> {
    abortIfNeeded(signal)

    const isRoleplay =
      input.persona_key === 'clinic-reception-roleplay-prototype' ||
      input.scenario_key === 'clinic-reception-roleplay-scenario'

    this.#talk.drillPatterns = null
    this.#talk.drillFallbackTurns = null
    this.#talk.drillPart = null
    this.#talk.acceptedPatternCount = 0
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

    const drillPatterns = this.#talk.drillPatterns
    let assistantReply: string
    let priorityCorrection: FriendTurn['priority_correction']
    let understoodIntent = 'prototype_utterance_received'
    let openRouter: Awaited<ReturnType<typeof requestDemoChatReply>> = null
    if (drillPatterns !== null && drillPatterns.length > 0) {
      const partId = this.#talk.drillPart ?? 'introduction'
      const fallbacks = this.#talk.drillFallbackTurns ?? undefined
      const currentModel = lessonDrillCurrentModel(
        drillPatterns,
        this.#talk.acceptedPatternCount,
        fallbacks,
      )
      if (
        currentModel !== undefined
        && lessonDrillUtteranceMatchesModel(
          input.prototype_utterance,
          currentModel,
        )
      ) {
        assistantReply = lessonDrillReply(
          partId,
          drillPatterns,
          this.#talk.acceptedPatternCount,
          fallbacks,
        )
        this.#talk.acceptedPatternCount += 1
        understoodIntent = 'lesson_drill_pattern_accepted'
        priorityCorrection = {
          correction_en: 'none',
          error_category: 'none',
          explanation_kn: 'ಚೆನ್ನಾಗಿದೆ. ಅದು ಮಾದರಿ ವಾಕ್ಯಕ್ಕೆ ಹೊಂದಿಕೆಯಾಗುತ್ತದೆ.',
          explanation_l1:
            'ಚೆನ್ನಾಗಿದೆ. ಅದು ಮಾದರಿ ವಾಕ್ಯಕ್ಕೆ ಹೊಂದಿಕೆಯಾಗುತ್ತದೆ.',
          explanation_locale: 'kn-IN',
          needed: false,
          retry_recommended: false,
        }
      } else if (currentModel !== undefined) {
        assistantReply = lessonDrillRetryReply(partId, currentModel)
        understoodIntent = 'lesson_drill_pattern_retry'
        priorityCorrection = {
          correction_en: currentModel,
          error_category: 'lesson.pattern_mismatch',
          explanation_kn: `ಮಾದರಿ ವಾಕ್ಯ ಹೇಳಿ: "${currentModel}"`,
          explanation_l1: `ಮಾದರಿ ವಾಕ್ಯ ಹೇಳಿ: "${currentModel}"`,
          explanation_locale: 'kn-IN',
          needed: true,
          retry_recommended: true,
        }
      } else {
        assistantReply = lessonDrillReply(
          partId,
          drillPatterns,
          Math.max(0, this.#talk.acceptedPatternCount - 1),
          fallbacks,
        )
        understoodIntent = 'lesson_drill_complete'
        priorityCorrection = {
          correction_en: 'none',
          error_category: 'none',
          explanation_kn: 'none',
          explanation_l1: 'none',
          explanation_locale: 'kn-IN',
          needed: false,
          retry_recommended: false,
        }
      }
    } else {
      openRouter = await requestDemoChatReply(input.prototype_utterance)
      abortIfNeeded(signal)
      assistantReply =
        openRouter?.reply ??
        demoFriendReply(input.prototype_utterance, this.#turnNumber)
      priorityCorrection = {
        correction_en: 'A clearer sentence is: "I enjoy learning English after work."',
        error_category: 'grammar.article',
        explanation_l1:
          'ಸ್ಪಷ್ಟವಾಗಿ ಹೇಳುವುದಾದರೆ: "I enjoy learning English after work."',
        explanation_locale: 'kn-IN',
        explanation_kn:
          'ಸ್ಪಷ್ಟವಾಗಿ ಹೇಳುವುದಾದರೆ: "I enjoy learning English after work."',
        needed: true,
        retry_recommended: false,
      }
    }

    return {
      assistant_reply: assistantReply,
      conversation_session_id: ids.session,
      events_url: `local-demo://turn/${this.#turnNumber}`,
      id: ids.turn,
      priority_correction: priorityCorrection,
      replayed: false,
      safety_state: 'ok',
      speech: {
        playback_available: false,
        rendering_mode: 'deterministic-fake-text-only',
      },
      status: 'completed',
      transcript: input.prototype_utterance,
      turn_number: this.#turnNumber,
      understood_intent: understoodIntent,
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
    upcoming_reviews: [],
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
