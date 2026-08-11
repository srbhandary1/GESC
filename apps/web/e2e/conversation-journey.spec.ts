import AxeBuilder from '@axe-core/playwright'
import {
  resolveMessage,
  type MessageKey,
} from '@global-english/localization'
import { expect, test, type Page, type Route } from '@playwright/test'

const ids = {
  membership: '01J20000000000000000000001',
  organization: '01J20000000000000000000002',
  persona: '01J20000000000000000000003',
  personaVersion: '01J20000000000000000000004',
  scenario: '01J20000000000000000000005',
  scenarioVersion: '01J20000000000000000000006',
  session: '01J20000000000000000000007',
  turn: '01J20000000000000000000008',
  user: '01J20000000000000000000009',
} as const

test('completes the disclosed text-first AI conversation journey', async ({
  page,
}) => {
  const requests: Array<{
    contentType: string | undefined
    method: string
    path: string
    postData: string | null
    url: string
  }> = []
  await mockConversationApi(page, requests)

  await page.goto('/#practice')
  await page
    .getByRole('combobox', { name: message('shell.locale_label') })
    .selectOption('en-IN')

  await expect(
    page.getByText(
      'This is an AI practice partner, not a human. Feedback is advisory and never changes marks, mastery, or progress.',
    ),
  ).toBeVisible()
  await expect(
    page.getByText(
      'Typed or approved transcript text is used for the current turn; only content-free digests are retained. Voice recording and transcript retention follow your consent choices.',
    ),
  ).toBeVisible()

  await page
    .getByRole('button', { name: 'Start Confidence Conversation' })
    .click()
  await expect(
    page.getByText(
      'I am an AI practice partner, not a human. My feedback is advisory.',
    ),
  ).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Warm-up' })).toBeVisible()
  await page.getByRole('button', { name: 'Continue to listen' }).click()
  await expect(page.getByRole('heading', { name: 'Listen' })).toBeVisible()
  await page.getByRole('button', { name: 'Continue to talk' }).click()

  await page
    .getByRole('textbox', { name: 'Type one English practice turn' })
    .fill('I practise English after work.')
  await page.getByRole('button', { name: 'Send turn' }).click()

  await expect(
    page.getByText(
      'Thank you for sharing that. What would you like to talk about next?',
    ),
  ).toBeVisible()
  await expect(
    page.getByText('The English text above is enough. Playback is optional.'),
  ).toBeVisible()
  await expect(page.getByText('Turn completed')).toBeVisible()

  expect(
    requests.every(({ url }) => new URL(url).hostname === '127.0.0.1'),
  ).toBe(true)
  expect(
    requests.every(
      ({ contentType, method }) =>
        method === 'GET' || contentType === 'application/json',
    ),
  ).toBe(true)
  expect(
    requests.some(({ path }) => /audio|speech|upload/u.test(path)),
  ).toBe(false)
  expect(
    requests
      .filter(({ method }) => method === 'POST')
      .every(({ postData }) => postData !== null),
  ).toBe(true)
  await expect
    .poll(() => page.evaluate(() => window.localStorage.length))
    .toBe(0)

  const accessibility = await new AxeBuilder({ page }).analyze()
  expect(accessibility.violations).toEqual([])
})

test('shows separate optional voice purposes before microphone capture', async ({
  page,
}) => {
  const requests: Array<{
    contentType: string | undefined
    method: string
    path: string
    postData: string | null
    url: string
  }> = []
  await mockConversationApi(page, requests)
  await page.goto('/#practice')
  await page
    .getByRole('combobox', { name: message('shell.locale_label') })
    .selectOption('en-IN')
  await page
    .getByRole('button', { name: 'Start Confidence Conversation' })
    .click()
  await page.getByRole('button', { name: 'Continue to listen' }).click()
  await page.getByRole('button', { name: 'Continue to talk' }).click()
  await page
    .getByRole('button', { name: 'Set up optional voice practice' })
    .click()

  await expect(
    page.getByText(
      'Implementation draft: audio is uploaded privately and deleted after transcription.',
    ),
  ).toBeVisible()
  await expect(
    page.getByRole('button', {
      name: 'Allow private upload and transcription',
    }),
  ).toBeVisible()
  await expect(
    page.getByRole('button', {
      name: 'Allow automated conversation processing',
    }),
  ).toBeVisible()
  expect(
    requests.some(
      ({ method, path }) =>
        method === 'GET' && path === '/api/v1/voice-consents',
    ),
  ).toBe(true)

  const accessibility = await new AxeBuilder({ page }).analyze()
  expect(accessibility.violations).toEqual([])
})

test('completes the disclosed synthetic Role-play stage journey', async ({
  page,
}) => {
  const requests: Array<{
    contentType: string | undefined
    method: string
    path: string
    postData: string | null
    url: string
  }> = []
  await mockConversationApi(page, requests)

  await page.goto('/#practice')
  await page
    .getByRole('combobox', { name: message('shell.locale_label') })
    .selectOption('en-IN')
  await page
    .getByRole('button', { name: 'Role-play (synthetic)' })
    .click()

  await expect(
    page.getByText(
      'This is a deterministic synthetic Role-play for local testing. It is not approved curriculum or a human.',
    ),
  ).toBeVisible()
  await page.getByRole('button', { name: 'Start Role-play' }).click()
  await expect(page.getByRole('heading', { name: 'Observe' })).toBeVisible()
  await page.getByRole('button', { name: 'Continue' }).click()
  await expect(page.getByRole('heading', { name: 'Rehearse' })).toBeVisible()
  await page.getByRole('button', { name: 'Continue' }).click()
  await expect(page.getByRole('heading', { name: 'Perform' })).toBeVisible()
  await page.getByRole('button', { name: 'Continue' }).click()
  await expect(page.getByRole('heading', { name: 'Reflect' })).toBeVisible()
  await expect(
    page.getByText('Advisory feedback', { exact: true }),
  ).toBeVisible()

  expect(
    requests.some(
      ({ method, path }) =>
        method === 'POST' && path === '/api/v1/conversation-sessions',
    ),
  ).toBe(true)
  expect(
    requests.filter(
      ({ method, path }) =>
        method === 'POST' &&
        path === `/api/v1/conversation-sessions/${ids.session}/stages`,
    ),
  ).toHaveLength(3)

  const accessibility = await new AxeBuilder({ page }).analyze()
  expect(accessibility.violations).toEqual([])
})

async function mockConversationApi(
  page: Page,
  requests: Array<{
    contentType: string | undefined
    method: string
    path: string
    postData: string | null
    url: string
  }>,
): Promise<void> {
  await page.context().addCookies([
    {
      name: 'XSRF-TOKEN',
      url: 'http://127.0.0.1:4173',
      value: 'conversation-e2e-csrf-token',
    },
  ])
  await page.route('**/sanctum/csrf-cookie', async (route) => {
    await route.fulfill({ status: 204 })
  })
  await page.route('**/api/v1/**', async (route) => {
    const request = route.request()
    const path = new URL(request.url()).pathname
    requests.push({
      contentType: request.headers()['content-type'],
      method: request.method(),
      path,
      postData: request.postData(),
      url: request.url(),
    })

    if (await fulfillPasswordSecurity(route, path)) {
      return
    }

    if (path === '/api/v1/me') {
      await json(route, {
        current_membership: {
          id: ids.membership,
          membership_type: 'learner',
          organization: {
            id: ids.organization,
            kind: 'platform_direct',
            name: 'Global English Direct',
          },
          role_keys: ['institution-learner'],
          status: 'active',
        },
        memberships: [],
        user: { id: ids.user },
      })
      return
    }

    if (
      path === '/api/v1/conversation-sessions' &&
      request.method() === 'POST'
    ) {
      const body = JSON.parse(request.postData() ?? '{}') as {
        persona_key?: string
      }
      await json(
        route,
        body.persona_key === 'clinic-reception-roleplay-prototype'
          ? roleplaySession('observe')
          : session(),
        201,
      )
      return
    }

    if (
      path === `/api/v1/conversation-sessions/${ids.session}/stages` &&
      request.method() === 'POST'
    ) {
      const body = JSON.parse(request.postData() ?? '{}') as {
        to_stage?: string
      }
      const toStage = body.to_stage ?? 'observe'
      await json(
        route,
        roleplaySession(
          toStage as 'observe' | 'rehearse' | 'perform' | 'reflect',
        ),
        200,
      )
      return
    }

    if (
      path === `/api/v1/conversation-sessions/${ids.session}/turns` &&
      request.method() === 'POST'
    ) {
      await json(route, turn(), 201)
      return
    }

    if (
      path === `/api/v1/conversation-turns/${ids.turn}/events` &&
      request.method() === 'GET'
    ) {
      await route.fulfill({
        body: events(),
        contentType: 'text/event-stream',
        headers: {
          'Cache-Control': 'no-cache, private',
        },
        status: 200,
      })
      return
    }

    if (
      path === '/api/v1/voice-consents' &&
      request.method() === 'GET'
    ) {
      await json(route, voiceConsents())
      return
    }

    await route.abort('failed')
  })
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

function roleplaySession(
  stage: 'observe' | 'rehearse' | 'perform' | 'reflect',
) {
  return {
    coaching_mode: 'gentle',
    feedback:
      stage === 'reflect'
        ? [
            {
              completion_state: 'met',
              evidence_digest: 'c'.repeat(64),
              id: '01J2000000000000000000000A',
              scenario_objective_id: '01J2000000000000000000000C',
            },
            {
              completion_state: 'unmet',
              evidence_digest: 'd'.repeat(64),
              id: '01J2000000000000000000000B',
              scenario_objective_id: '01J2000000000000000000000D',
            },
          ]
        : [],
    id: ids.session,
    mode: 'roleplay',
    objectives: [
      {
        completion_criteria_code: 'utterance.contains.hello',
        id: '01J2000000000000000000000A',
        key: 'greet-receptionist',
        title: 'Greet the receptionist',
      },
      {
        completion_criteria_code: 'utterance.contains.appointment',
        id: '01J2000000000000000000000B',
        key: 'state-appointment-reason',
        title: 'State the appointment reason',
      },
    ],
    persona: {
      definition_hash: 'a'.repeat(64),
      disclosure: 'I am a deterministic AI prototype, not a human.',
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
    stage,
    status: 'active',
    ui_locale: 'en-IN',
  }
}

function session() {
  return {
    coaching_mode: 'gentle',
    id: ids.session,
    mode: 'friend',
    stage: null,
    persona: {
      definition_hash: 'a'.repeat(64),
      disclosure: 'I am a deterministic AI prototype, not a human.',
      display_name: 'Patient Friend Prototype',
      id: ids.persona,
      key: 'patient-friend-prototype',
      review_status: 'synthetic_unreviewed',
      version: 1,
      version_id: ids.personaVersion,
    },
    replayed: false,
    scenario: {
      definition_hash: 'b'.repeat(64),
      id: ids.scenario,
      key: 'everyday-friend-prototype',
      review_status: 'synthetic_unreviewed',
      topic_code: 'everyday-conversation',
      version: 1,
      version_id: ids.scenarioVersion,
    },
    status: 'active',
    ui_locale: 'kn-IN',
  }
}

function turn() {
  return {
    assistant_reply:
      'Thank you for sharing that. What would you like to talk about next?',
    conversation_session_id: ids.session,
    events_url: `/api/v1/conversation-turns/${ids.turn}/events`,
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
    transcript: 'I practise English after work.',
    turn_number: 1,
    understood_intent: 'prototype_utterance_received',
    usage: {
      estimated_cost_microunits: 0,
      provider_attempts: 3,
      total_latency_ms: 14,
    },
  }
}

function events(): string {
  const states = [
    ['accepted', 'turn.accepted', null, null],
    ['transcribing', 'turn.transcribing', 'stt', 1],
    ['generating_reply', 'turn.generating_reply', 'model', 1],
    ['synthesizing', 'turn.synthesizing', 'tts', 1],
    ['completed', 'turn.completed', null, null],
  ] as const

  return states
    .map(
      ([state, eventCode, providerStage, providerAttempt], index) =>
        `id: ${index + 1}\nevent: conversation-status\ndata: ${JSON.stringify({
          event_code: eventCode,
          provider_attempt: providerAttempt,
          provider_stage: providerStage,
          recorded_at: '2026-07-30T12:00:00Z',
          sequence: index + 1,
          state,
        })}\n\n`,
    )
    .join('')
}

function voiceConsents() {
  return {
    consents: [
      {
        effective_at: null,
        purpose: 'voice_upload_and_transcription',
        state: 'not_granted',
      },
      {
        effective_at: null,
        purpose: 'conversation_provider_processing',
        state: 'not_granted',
      },
      {
        effective_at: null,
        purpose: 'transcript_history',
        state: 'not_granted',
      },
    ],
    notice: {
      history_optional: true,
      notice:
        'Implementation draft: audio is uploaded privately and deleted after transcription.',
      notice_revision: 'voice-processing-notice-2026-07-31',
      notice_sha256: 'c'.repeat(64),
      policy_version: 'recording-policy-v1',
      purposes: [
        'voice_upload_and_transcription',
        'conversation_provider_processing',
        'transcript_history',
      ],
      requested_locale: 'en-IN',
      resolved_locale: 'en-IN',
      review_status: 'implementation_draft',
    },
  }
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

function message(key: MessageKey): string {
  return resolveMessage(key, {
    policy: 'development-preview',
    requestedLocale: 'kn-IN',
  }).text
}
