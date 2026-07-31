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
} as const

export interface LocalDemoEnvironment {
  readonly development: boolean
  readonly requested: boolean
}

export function isLocalDemoEnabled(
  environment: LocalDemoEnvironment,
): boolean {
  return environment.development && environment.requested
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
      ],
    }
  }
}

class LocalDemoConversationApi implements ConversationJourneyApi {
  #turnNumber = 0

  async startSession(
    _input: StartFriendSessionInput,
    _commandId: string,
    signal?: AbortSignal,
  ): Promise<FriendSession> {
    abortIfNeeded(signal)
    return {
      coaching_mode: 'gentle',
      id: ids.session,
      mode: 'friend',
      persona: {
        definition_hash: 'b'.repeat(64),
        disclosure: 'I am a deterministic local demo, not a human.',
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
    }
  }

  async createTurn(
    _sessionId: string,
    input: CreateFriendTurnInput,
    _commandId: string,
    signal?: AbortSignal,
  ): Promise<FriendTurn> {
    abortIfNeeded(signal)
    this.#turnNumber += 1

    return {
      assistant_reply:
        'Thank you for sharing that. What would you like to talk about next?',
      conversation_session_id: ids.session,
      events_url: `local-demo://turn/${this.#turnNumber}`,
      id: ids.turn,
      replayed: false,
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
        provider_attempts: 3,
        total_latency_ms: 14,
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
      duration_ms: 500,
      mime_type: 'audio/wav',
      stream_url: demoWaveDataUrl(),
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

let cachedWaveDataUrl: string | null = null

function demoWaveDataUrl(): string {
  if (cachedWaveDataUrl !== null) {
    return cachedWaveDataUrl
  }

  const sampleRate = 8_000
  const sampleCount = sampleRate / 2
  const buffer = new ArrayBuffer(44 + sampleCount * 2)
  const view = new DataView(buffer)
  writeAscii(view, 0, 'RIFF')
  view.setUint32(4, buffer.byteLength - 8, true)
  writeAscii(view, 8, 'WAVEfmt ')
  view.setUint32(16, 16, true)
  view.setUint16(20, 1, true)
  view.setUint16(22, 1, true)
  view.setUint32(24, sampleRate, true)
  view.setUint32(28, sampleRate * 2, true)
  view.setUint16(32, 2, true)
  view.setUint16(34, 16, true)
  writeAscii(view, 36, 'data')
  view.setUint32(40, sampleCount * 2, true)

  for (let index = 0; index < sampleCount; index += 1) {
    const sample = Math.round(
      Math.sin((2 * Math.PI * 440 * index) / sampleRate) * 3_200,
    )
    view.setInt16(44 + index * 2, sample, true)
  }

  const bytes = new Uint8Array(buffer)
  let binary = ''
  for (const byte of bytes) {
    binary += String.fromCharCode(byte)
  }

  cachedWaveDataUrl = `data:audio/wav;base64,${btoa(binary)}`
  return cachedWaveDataUrl
}

function writeAscii(view: DataView, offset: number, value: string): void {
  for (let index = 0; index < value.length; index += 1) {
    view.setUint8(offset + index, value.charCodeAt(index))
  }
}
