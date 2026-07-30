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

test('completes the disclosed deterministic fake-provider conversation journey', async ({
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
      'This is a deterministic fake for local testing. It is not a real AI provider or a human.',
    ),
  ).toBeVisible()
  await expect(
    page.getByText(
      'Your typed text is processed only for this request. Conversation text and audio are not persisted.',
    ),
  ).toBeVisible()

  await page
    .getByRole('button', { name: 'Start AI Friend prototype' })
    .click()
  await expect(
    page.getByText('I am a deterministic AI prototype, not a human.'),
  ).toBeVisible()

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
    page.getByText('Fake TTS is text-only. No audio was created or stored.'),
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
      await json(route, session(), 201)
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

    await route.abort('failed')
  })
}

function session() {
  return {
    coaching_mode: 'gentle',
    id: ids.session,
    mode: 'friend',
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
  }
}

function turn() {
  return {
    assistant_reply:
      'Thank you for sharing that. What would you like to talk about next?',
    conversation_session_id: ids.session,
    events_url: `/api/v1/conversation-turns/${ids.turn}/events`,
    id: ids.turn,
    replayed: false,
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
