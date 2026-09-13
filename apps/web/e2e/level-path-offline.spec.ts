import {
  DEFAULT_LOCALE,
  resolveMessage,
  type MessageKey,
} from '@global-english/localization'
import { expect, test, type Page, type Route } from '@playwright/test'
import { createHash } from 'node:crypto'

const ids = {
  asset: '01J10000000000000000000014',
  attempt: '01J10000000000000000000001',
  blueprint: '01TESTBLUEPRINT000000000001',
  bundle: '01J10000000000000000000015',
  enrollment: '01J10000000000000000000004',
  levelBundle: '01J00000000000000000000070',
  membership: '01J10000000000000000000007',
  mission: '01J10000000000000000000008',
  objective: '01J10000000000000000000009',
  organization: '01J10000000000000000000010',
  publication: '01J10000000000000000000011',
  unlock: '01J00000000000000000000052',
  user: '01J10000000000000000000012',
} as const

const blueprintContentHash = 'a'.repeat(64)
const checkpointRuleHash = 'b'.repeat(64)

test('queues a level checkpoint offline and syncs through the signed submission route on reconnect', async ({
  context,
  page,
}) => {
  const api = await mockLevelPathLearnerApi(page, true)

  await page.goto('/#home')
  await page
    .getByRole('checkbox', {
      name: message('learner.offline.personal_confirmation'),
    })
    .check()
  await page
    .getByRole('button', {
      name: message('learner.offline.download'),
    })
    .click()
  await expect(
    page.getByText(message('learner.offline.available')),
  ).toBeVisible()

  await page.goto('/#learn')
  await expect(
    page.getByRole('heading', {
      name: message('level.path.title'),
    }),
  ).toBeVisible()

  await page
    .getByRole('button', {
      name: message('level.path.checkpoint_open', { number: '1' }),
    })
    .click()

  await expect(
    page.getByRole('heading', {
      name: message('level.path.checkpoint_heading'),
    }),
  ).toBeVisible()

  await expect
    .poll(() => api.offlineLevelBundleRequests)
    .toBe(1)

  await context.setOffline(true)

  await page.getByRole('radio', { name: 'I can swim.' }).check()
  await page
    .getByRole('button', {
      name: message('level.path.checkpoint_submit'),
    })
    .click()

  await expect(
    page.getByRole('heading', {
      name: message('learner.offline.saved'),
    }),
  ).toBeVisible()
  await expect(
    page.getByText(message('learner.offline.pending_authority')),
  ).toBeVisible()
  expect(api.offlineCheckpointBodies).toHaveLength(0)
  expect(api.onlineCheckpointBodies).toHaveLength(0)

  await context.setOffline(false)

  await expect(
    page.getByText(
      message('level.path.checkpoint_unlocked', { number: '2' }),
    ),
  ).toBeVisible()
  expect(api.offlineCheckpointBodies).toHaveLength(1)
  expect(api.offlineCheckpointBodies[0]).toMatchObject({
    answers: { 'can-cp-01': 0 },
    bundle_id: ids.levelBundle,
    lease: 'signed-level-offline-lease',
    level_number: 1,
  })
  expect(api.onlineCheckpointBodies).toHaveLength(0)
  expect(JSON.stringify(api.offlineCheckpointBodies[0])).not.toContain('audio')
})

interface MockLevelPathApiState {
  offlineCheckpointBodies: unknown[]
  offlineLevelBundleRequests: number
  onlineCheckpointBodies: unknown[]
}

async function mockLevelPathLearnerApi(
  page: Page,
  initiallyOnboarded: boolean,
): Promise<MockLevelPathApiState> {
  let onboarded = initiallyOnboarded
  const state: MockLevelPathApiState = {
    offlineCheckpointBodies: [],
    offlineLevelBundleRequests: 0,
    onlineCheckpointBodies: [],
  }

  await page.context().addCookies([
    {
      name: 'XSRF-TOKEN',
      url: 'http://127.0.0.1:4173',
      value: 'e2e-csrf-token',
    },
  ])

  await page.route('**/sanctum/csrf-cookie', async (route) => {
    await route.fulfill({ status: 204 })
  })

  await page.route('**/api/v1/**', async (route) => {
    const request = route.request()
    const url = new URL(request.url())
    const path = url.pathname
    const postData = request.postData()

    if (await fulfillPasswordSecurity(route, path)) {
      return
    }

    if (path === '/api/v1/me') {
      await json(route, {
        current_membership: {
          id: ids.membership,
          organization: {
            id: ids.organization,
            kind: 'platform_direct',
            name: 'Global English Direct',
          },
          membership_type: 'learner',
          role_keys: ['institution-learner'],
          status: 'active',
        },
        memberships: [],
        user: { id: ids.user },
      })
      return
    }

    if (path === '/api/v1/home') {
      await json(route, home(onboarded))
      return
    }

    if (path === '/api/v1/me/onboarding') {
      onboarded = true
      await json(route, home(true))
      return
    }

    if (
      path === `/api/v1/missions/${ids.mission}/offline-bundle` &&
      request.method() === 'GET'
    ) {
      await json(route, missionOfflineBundle())
      return
    }

    if (
      path === '/api/v1/offline-completions' &&
      request.method() === 'POST'
    ) {
      await json(route, missionResult(false))
      return
    }

    if (path === `/api/v1/media-assets/${ids.asset}/content`) {
      await route.fulfill({
        body: waveFile(),
        contentType: 'audio/wav',
        headers: {
          'Cache-Control': 'private, no-store',
        },
        status: 200,
      })
      return
    }

    if (path === '/api/v1/me/levels' && request.method() === 'GET') {
      await json(route, { levels: levelSummaries() })
      return
    }

    if (
      path === '/api/v1/me/levels/1/sessions' &&
      request.method() === 'GET'
    ) {
      await json(route, completedSessionsList())
      return
    }

    if (
      path.startsWith('/api/v1/me/levels/1/sessions/') &&
      request.method() === 'GET' &&
      !path.endsWith('/audio')
    ) {
      await json(route, sessionDetail(path))
      return
    }

    if (
      path === '/api/v1/me/levels/1/checkpoint' &&
      request.method() === 'GET'
    ) {
      await json(route, checkpointDetail())
      return
    }

    if (
      path === '/api/v1/me/levels/1/offline-bundle' &&
      request.method() === 'GET'
    ) {
      state.offlineLevelBundleRequests += 1
      await json(route, levelOfflineBundle())
      return
    }

    if (
      path === '/api/v1/offline-level-checkpoint-submissions' &&
      request.method() === 'POST'
    ) {
      state.offlineCheckpointBodies.push(
        JSON.parse(postData ?? 'null') as unknown,
      )
      await json(route, checkpointSubmissionResult())
      return
    }

    if (
      path === '/api/v1/me/levels/1/checkpoint-submissions' &&
      request.method() === 'POST'
    ) {
      state.onlineCheckpointBodies.push(
        JSON.parse(postData ?? 'null') as unknown,
      )
      await json(route, checkpointSubmissionResult())
      return
    }

    if (path === '/api/v1/progress') {
      await json(route, {
        enrollment_id: ids.enrollment,
        objectives: [],
      })
      return
    }

    await route.abort('failed')
  })

  return state
}

async function fulfillPasswordSecurity(
  route: Route,
  path: string,
): Promise<boolean> {
  if (path === '/api/v1/auth/password/status') {
    await json(route, {
      credential_ready: true,
      mode: 'password_only',
      password_confirmed_at: null,
      username: 'learner.one',
    })
    return true
  }
  if (path === '/api/v1/auth/sessions') {
    await json(route, { data: [] })
    return true
  }
  return false
}

function home(onboarded: boolean) {
  return {
    due_review: null,
    upcoming_reviews: [],
    enrollment: onboarded
      ? { id: ids.enrollment, status: 'active' }
      : null,
    next_mission: onboarded
      ? {
          estimated_minutes: 5,
          id: ids.mission,
          title: content('en-IN', 'Introduce yourself'),
        }
      : null,
    onboarding: {
      adult_confirmed: onboarded,
      completed: onboarded,
      goal_key: onboarded ? 'workplace-confidence' : null,
    },
    streak: {
      as_of_date: '2026-08-30',
      count: 0,
      timezone: 'Asia/Kolkata',
    },
  }
}

function levelSummaries() {
  return [
    {
      availability: 'available',
      blueprint_id: ids.blueprint,
      checkpoint_attempts_remaining: 3,
      checkpoint_status: 'ready',
      concept_key: 'can',
      level_number: 1,
      progress_access: 'allowed',
      session_part_ids: [
        'introduction',
        'practice',
        'conversation',
        'interaction',
      ],
      spoken_evidence: {
        evidence_state: 'not_recorded',
        spoken_progress_claim: false,
        spoken_state: 'unassessed',
      },
    },
    {
      availability: 'available',
      blueprint_id: '01TESTBLUEPRINT000000000002',
      checkpoint_status: 'not_ready',
      concept_key: 'could',
      level_number: 2,
      progress_access: 'locked',
      session_part_ids: [
        'introduction',
        'practice',
        'conversation',
        'interaction',
      ],
      unlock_after_level_number: 1,
    },
  ]
}

function completedSessionsList() {
  return {
    blueprint_content_hash: blueprintContentHash,
    blueprint_id: ids.blueprint,
    concept_key: 'can',
    level_number: 1,
    sessions: [
      {
        completed: true,
        media_asset_id: '01TESTMEDIA0000000000000001',
        part_id: 'introduction',
        part_order: 1,
      },
      {
        completed: true,
        media_asset_id: '01TESTMEDIA0000000000000002',
        part_id: 'practice',
        part_order: 2,
      },
      {
        completed: true,
        media_asset_id: '01TESTMEDIA0000000000000003',
        part_id: 'conversation',
        part_order: 3,
      },
      {
        completed: true,
        media_asset_id: '01TESTMEDIA0000000000000004',
        part_id: 'interaction',
        part_order: 4,
      },
    ],
  }
}

function sessionDetail(path: string) {
  const partId = path.split('/').at(-1) ?? 'introduction'

  return {
    activities: [],
    apply: null,
    audio_stream_path: `/api/v1/me/levels/1/sessions/${partId}/audio`,
    check: null,
    completed: true,
    conversation_binding: {
      coaching_mode: 'tutor',
      prototype: 'skill_focus',
    },
    kannada_guidance: { summary: 'ಕನ್ನಡ ವಿವರಣೆ' },
    level_number: 1,
    media_asset_id: '01TESTMEDIA0000000000000001',
    objective: 'Use can and cannot.',
    part_id: partId,
    part_order: 1,
    talk: {
      elicitation_goals: [],
      fallback_turns: [],
      free_speech: true,
      scenario: 'Ask for permission.',
    },
    target_patterns: ['I can + base verb.'],
  }
}

function checkpointDetail() {
  return {
    attempts_remaining: 3,
    blueprint_content_hash: blueprintContentHash,
    blueprint_id: ids.blueprint,
    concept_key: 'can',
    items: [
      {
        id: 'can-cp-01',
        options: ['I can swim.', 'I cans swim.', 'Can I swam?'],
        prompt: 'Which sentence uses can correctly?',
        weight: 1,
      },
    ],
    level_number: 1,
    max_attempts: 3,
    pass_threshold: 0.8,
    rule_hash: checkpointRuleHash,
    rule_key: 'level_checkpoint_v1',
  }
}

function levelOfflineBundle() {
  return {
    lease: 'signed-level-offline-lease',
    manifest: {
      binding: {
        membership_id: ids.membership,
        organization_id: ids.organization,
        user_id: ids.user,
      },
      blueprint_content_hash: blueprintContentHash,
      blueprint_id: ids.blueprint,
      bundle_hash: 'c'.repeat(64),
      bundle_id: ids.levelBundle,
      checkpoint: checkpointDetail(),
      expires_at: '2099-08-01T10:00:00Z',
      issued_at: '2026-09-13T00:00:00Z',
      level: {
        blueprint_content_hash: blueprintContentHash,
        blueprint_id: ids.blueprint,
        checkpoint_rule_hash: checkpointRuleHash,
        checkpoint_rule_key: 'level_checkpoint_v1',
        concept_key: 'can',
        level_number: 1,
      },
      schema: 'global-english.offline-level-bundle.v1',
    },
  }
}

function checkpointSubmissionResult() {
  return {
    attempt_id: ids.attempt,
    attempt_number: 1,
    attempts_remaining: 2,
    item_results: [
      {
        correct: true,
        id: 'can-cp-01',
        rationale: null,
        selected_option: 0,
      },
    ],
    level_unlock: {
      level_number: 2,
      replayed: false,
      unlock_decision_id: ids.unlock,
    },
    pass_threshold: 0.8,
    passed: true,
    replayed: false,
    score: 1,
    spoken_evidence: {
      evidence_state: 'not_recorded',
      spoken_progress_claim: false,
      spoken_state: 'unassessed',
    },
  }
}

function missionOfflineBundle() {
  const ordinaryMission = mission()
  const audio = waveFile()

  return {
    lease: 'signed-purpose-specific-offline-lease',
    manifest: {
      binding: {
        enrollment_id: ids.enrollment,
        membership_id: ids.membership,
        organization_id: ids.organization,
        user_id: ids.user,
      },
      bundle_hash: 'b'.repeat(64),
      bundle_id: ids.bundle,
      expires_at: '2099-08-01T10:00:00Z',
      issued_at: '2026-07-30T10:00:00Z',
      mission: {
        ...ordinaryMission,
        model_audio: {
          ...ordinaryMission.model_audio,
          byte_size: audio.byteLength,
          content_hash: createHash('sha256').update(audio).digest('hex'),
        },
        offline_check: {
          correct_option_id: 'correct-option',
        },
      },
      policy_version: 'daily-speaking-v1',
      publication: {
        curriculum_version_id: '01J10000000000000000000016',
        manifest_hash: 'c'.repeat(64),
        mission_definition_hash: 'd'.repeat(64),
        owner_organization_id: ids.organization,
        publication_id: ids.publication,
      },
      schema: 'global-english.offline-mission-bundle.v1',
    },
  }
}

function mission() {
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
      duration_ms: 300,
      mime_type: 'audio/wav',
      stream_url: `/api/v1/media-assets/${ids.asset}/content?enrollment_id=${ids.enrollment}&mission_id=${ids.mission}`,
    },
    model_text: content('en-IN', 'Hello, my name is Ravi.'),
    objective_id: ids.objective,
    publication_id: ids.publication,
    title: content('en-IN', 'Introduce yourself'),
    transcript: content('en-IN', 'Hello, my name is Ravi.'),
  }
}

function missionResult(hasRecording: boolean) {
  return {
    attempt_id: ids.attempt,
    can_do: content('en-IN', 'I can introduce myself.'),
    check_state: 'correct',
    evidence: {
      best_attempt_id: '01J10000000000000000000002',
      best_attempt_number: 2,
      first_attempt_id: '01J10000000000000000000005',
      first_attempt_number: 1,
      latest_attempt_id: '01J10000000000000000000006',
      latest_attempt_number: 2,
    },
    knowledge_state: 'mastered',
    local_capture_receipt_present: hasRecording,
    review: {
      due_at: '2026-08-01T09:00:00Z',
      timezone: 'Asia/Kolkata',
    },
    spoken_state: 'unassessed',
  }
}

function content(locale: 'en-IN' | 'kn-IN', text: string) {
  return {
    content_hash: 'a'.repeat(64),
    locale,
    revision_id: '01J10000000000000000000013',
    text,
  }
}

function waveFile(): Buffer {
  const sampleCount = 2400
  const buffer = Buffer.alloc(44 + sampleCount * 2)
  buffer.write('RIFF', 0)
  buffer.writeUInt32LE(buffer.length - 8, 4)
  buffer.write('WAVEfmt ', 8)
  buffer.writeUInt32LE(16, 16)
  buffer.writeUInt16LE(1, 20)
  buffer.writeUInt16LE(1, 22)
  buffer.writeUInt32LE(8000, 24)
  buffer.writeUInt32LE(16000, 28)
  buffer.writeUInt16LE(2, 32)
  buffer.writeUInt16LE(16, 34)
  buffer.write('data', 36)
  buffer.writeUInt32LE(sampleCount * 2, 40)

  return buffer
}

async function json(
  route: Route,
  body: unknown,
  status = 200,
): Promise<void> {
  await route.fulfill({
    contentType: 'application/json',
    json: body,
    status,
  })
}

function message(key: MessageKey, parameters: Record<string, string> = {}): string {
  return resolveMessage(key, {
    parameters,
    policy: 'development-preview',
    requestedLocale: DEFAULT_LOCALE,
  }).text
}
