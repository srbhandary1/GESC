import AxeBuilder from '@axe-core/playwright'
import {
  DEFAULT_LOCALE,
  resolveMessage,
  type MessageKey,
} from '@global-english/localization'
import { expect, test, type Page, type Route } from '@playwright/test'

const ids = {
  asset: '01J10000000000000000000014',
  attempt: '01J10000000000000000000001',
  best: '01J10000000000000000000002',
  enrollment: '01J10000000000000000000004',
  first: '01J10000000000000000000005',
  latest: '01J10000000000000000000006',
  membership: '01J10000000000000000000007',
  mission: '01J10000000000000000000008',
  objective: '01J10000000000000000000009',
  organization: '01J10000000000000000000010',
  publication: '01J10000000000000000000011',
  user: '01J10000000000000000000012',
} as const

test('completes the contract-stubbed online no-AI learner journey', async ({
  page,
}) => {
  const networkUrls: string[] = []
  page.on('request', (request) => networkUrls.push(request.url()))
  await installWorkingMicrophone(page)
  const api = await mockLearnerApi(page, false)

  await page.goto('/#learn')

  await expect(
    page.getByRole('heading', {
      name: message('learner.onboarding.title'),
    }),
  ).toBeFocused()
  await page
    .getByRole('checkbox', {
      name: message('learner.onboarding.adult_confirmation'),
    })
    .check()
  await page
    .getByRole('radio', {
      name: message('learner.onboarding.goal_workplace'),
    })
    .check()
  await page
    .getByRole('button', {
      name: message('learner.onboarding.continue'),
    })
    .click()

  await page
    .getByRole('button', {
      name: message('learner.home.start_mission'),
    })
    .click()
  await expect(
    page.getByText('Introduce yourself', { exact: true }).first(),
  ).toHaveAttribute('lang', 'en-IN')

  const modelAudio = page.getByTestId('model-audio-element')
  await expect(modelAudio).not.toHaveAttribute('autoplay', /.*/)
  await expect
    .poll(() => modelAudio.evaluate((audio) => (audio as HTMLAudioElement).paused))
    .toBe(true)
  await page
    .getByRole('button', { name: message('learner.audio.play') })
    .click()

  await expect(
    page.getByText('Hello, my name is Ravi.', { exact: true }).first(),
  ).toHaveAttribute('lang', 'en-IN')
  await expect(
    page.getByText('ನಿಮ್ಮ ಹೆಸರನ್ನು ಹೇಳಿ.', { exact: true }),
  ).toHaveAttribute('lang', 'kn-IN')
  await page
    .getByRole('button', {
      name: message('learner.mission.continue_to_speak'),
    })
    .click()

  await page
    .getByRole('button', {
      name: message('learner.recording.start'),
    })
    .click()
  await page.waitForTimeout(300)
  await page
    .getByRole('button', {
      name: message('learner.recording.stop'),
    })
    .click()
  await expect(
    page.getByLabel(message('learner.recording.playback_label')),
  ).toBeVisible()
  await page
    .getByRole('button', {
      name: message('learner.recording.retry'),
    })
    .click()

  await page
    .getByRole('button', {
      name: message('learner.recording.start'),
    })
    .click()
  await page.waitForTimeout(300)
  await page
    .getByRole('button', {
      name: message('learner.recording.stop'),
    })
    .click()
  await page
    .getByRole('button', {
      name: message('learner.mission.continue_to_check'),
    })
    .click()
  await expect(
    page.getByText('Choose the correct introduction.'),
  ).toHaveAttribute('lang', 'en-IN')
  await page
    .getByRole('radio', { name: 'Hello, my name is Ravi.' })
    .check()
  await page
    .getByRole('button', {
      name: message('learner.mission.submit'),
    })
    .click()

  await expect(
    page.getByRole('heading', {
      name: message('learner.result.title'),
    }),
  ).toBeFocused()
  await expect(
    page.getByText(message('learner.result.knowledge_mastered')),
  ).toBeVisible()
  await expect(
    page.getByText(message('learner.result.spoken_unassessed')).first(),
  ).toBeVisible()
  await expect(
    page.getByText(message('learner.result.local_capture_completed')).first(),
  ).toBeVisible()
  await expect(page.getByText('ಪ್ರಯತ್ನ 1')).toBeVisible()
  await expect(page.getByText('ಪ್ರಯತ್ನ 2').first()).toBeVisible()
  await expect(page.getByText(ids.first)).toHaveCount(0)

  expect(api.lastResponse?.local_recording).toMatchObject({
    captured: true,
    client_capture_id: expect.stringMatching(
      /^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i,
    ),
    mime_type: 'audio/webm',
  })
  expect(api.lastResponse!.local_recording!.byte_size).toBeGreaterThanOrEqual(
    128,
  )
  expect(api.lastResponse!.local_recording!.byte_size).toBeLessThanOrEqual(
    10_000_000,
  )
  expect(api.lastResponse!.local_recording!.duration_ms).toBeGreaterThanOrEqual(
    250,
  )
  expect(api.lastResponse!.local_recording!.duration_ms).toBeLessThanOrEqual(
    120_000,
  )
  expect(JSON.stringify(api.lastResponse)).not.toContain('sha256')
  expect(JSON.stringify(api.lastResponse)).not.toContain('blob:')
  expect(api.requestBodies.join('')).not.toContain(
    'deterministic-local-audio',
  )
  expect(api.modelAudioRequests.length).toBeGreaterThan(0)
  expect(
    api.modelAudioRequests.every(
      (request) =>
        request.method === 'GET' &&
        request.postData === null &&
        request.url ===
          `http://127.0.0.1:4173/api/v1/media-assets/${ids.asset}/content?enrollment_id=${ids.enrollment}&mission_id=${ids.mission}`,
    ),
  ).toBe(true)
  expect(
    networkUrls.every((url) => new URL(url).hostname === '127.0.0.1'),
  ).toBe(true)
  expect(api.commandIds.every((commandId) => commandId !== null)).toBe(true)
  await expect
    .poll(() =>
      page.evaluate(() => {
        const state = window as unknown as {
          __recordingTestState: {
            revokedUrls: number
            stoppedTracks: number
          }
        }
        return state.__recordingTestState
      }),
    )
    .toMatchObject({ revokedUrls: 2, stoppedTracks: 2 })
  await expect
    .poll(() => page.evaluate(() => window.localStorage.length))
    .toBe(0)
  await expect
    .poll(() =>
      page.evaluate(async () => (await indexedDB.databases()).length),
    )
    .toBe(0)
  await expect
    .poll(() =>
      page.evaluate(async () => ({
        cacheKeys:
          'caches' in window ? await window.caches.keys() : [],
        serviceWorkerControlled:
          navigator.serviceWorker?.controller !== null &&
          navigator.serviceWorker?.controller !== undefined,
        serviceWorkerRegistrations:
          navigator.serviceWorker === undefined
            ? 0
            : (await navigator.serviceWorker.getRegistrations()).length,
      })),
    )
    .toEqual({
      cacheKeys: [],
      serviceWorkerControlled: false,
      serviceWorkerRegistrations: 0,
    })

  const accessibility = await new AxeBuilder({ page }).analyze()
  expect(accessibility.violations).toEqual([])
})

test('contract-stubbed microphone denial stays spoken-unassessed', async ({
  page,
}) => {
  await installDeniedMicrophone(page)
  const api = await mockLearnerApi(page, true)

  await page.goto('/#learn')
  await page
    .getByRole('button', {
      name: message('learner.home.start_mission'),
    })
    .click()
  await page
    .getByRole('button', {
      name: message('learner.mission.continue_to_speak'),
    })
    .click()
  await page
    .getByRole('button', {
      name: message('learner.recording.start'),
    })
    .click()
  await expect(
    page.getByText(message('learner.recording.blocked')),
  ).toBeVisible()
  await page
    .getByRole('button', {
      name: message('learner.recording.continue_without'),
    })
    .click()
  await page
    .getByRole('radio', { name: 'Hello, my name is Ravi.' })
    .check()
  await page
    .getByRole('button', {
      name: message('learner.mission.submit'),
    })
    .click()

  await expect(
    page.getByText(message('learner.result.spoken_unassessed')).first(),
  ).toBeVisible()
  await expect(
    page
      .getByText(message('learner.result.local_capture_not_recorded'))
      .first(),
  ).toBeVisible()
  expect(api.lastResponse?.local_recording).toBeNull()
})

interface MockApiState {
  commandIds: Array<string | null>
  modelAudioRequests: Array<{
    method: string
    postData: string | null
    url: string
  }>
  requestBodies: string[]
  lastResponse: {
    local_recording: null | {
      captured: true
      byte_size: number
      client_capture_id: string
      duration_ms: number
      mime_type: string
    }
  } | null
}

async function mockLearnerApi(
  page: Page,
  initiallyOnboarded: boolean,
): Promise<MockApiState> {
  let onboarded = initiallyOnboarded
  const state: MockApiState = {
    commandIds: [],
    lastResponse: null,
    modelAudioRequests: [],
    requestBodies: [],
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
    if (postData !== null) {
      state.requestBodies.push(postData)
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
      state.commandIds.push(request.headers()['idempotency-key'] ?? null)
      onboarded = true
      await json(route, home(true))
      return
    }

    if (
      path === `/api/v1/missions/${ids.mission}` &&
      request.method() === 'GET'
    ) {
      expect(url.searchParams.get('enrollment_id')).toBe(ids.enrollment)
      await json(route, mission())
      return
    }

    if (path === `/api/v1/media-assets/${ids.asset}/content`) {
      expect(url.searchParams.get('enrollment_id')).toBe(ids.enrollment)
      expect(url.searchParams.get('mission_id')).toBe(ids.mission)
      expect(request.headers()['cookie']).toContain(
        'XSRF-TOKEN=e2e-csrf-token',
      )
      state.modelAudioRequests.push({
        method: request.method(),
        postData,
        url: request.url(),
      })
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

    if (
      path === `/api/v1/missions/${ids.mission}/attempts` &&
      request.method() === 'POST'
    ) {
      state.commandIds.push(request.headers()['idempotency-key'] ?? null)
      await json(route, { id: ids.attempt, status: 'in_progress' }, 201)
      return
    }

    if (path === `/api/v1/attempts/${ids.attempt}/responses`) {
      state.commandIds.push(request.headers()['idempotency-key'] ?? null)
      state.lastResponse = request.postDataJSON() as MockApiState['lastResponse']
      await route.fulfill({ status: 204 })
      return
    }

    if (path === `/api/v1/attempts/${ids.attempt}/submit`) {
      state.commandIds.push(request.headers()['idempotency-key'] ?? null)
      expect(postData).toBeNull()
      await json(route, result(state.lastResponse?.local_recording !== null))
      return
    }

    if (path === '/api/v1/progress') {
      await json(route, progress(state.lastResponse?.local_recording !== null))
      return
    }

    await route.abort('failed')
  })

  return state
}

async function installWorkingMicrophone(page: Page): Promise<void> {
  await page.addInitScript(() => {
    const testState = {
      revokedUrls: 0,
      stoppedTracks: 0,
    }
    const nativeRevoke = URL.revokeObjectURL.bind(URL)
    URL.revokeObjectURL = (url: string) => {
      testState.revokedUrls += 1
      nativeRevoke(url)
    }
    Object.assign(window, { __recordingTestState: testState })
    Object.defineProperty(navigator, 'mediaDevices', {
      configurable: true,
      value: {
        getUserMedia: async () => ({
          getTracks: () => [
            {
              stop: () => {
                testState.stoppedTracks += 1
              },
            },
          ],
        }),
      },
    })

    class DeterministicMediaRecorder extends EventTarget {
      static isTypeSupported(): boolean {
        return true
      }

      readonly mimeType = 'audio/webm'
      state: RecordingState = 'inactive'

      start(): void {
        this.state = 'recording'
      }

      pause(): void {
        this.state = 'paused'
      }

      resume(): void {
        this.state = 'recording'
      }

      stop(): void {
        this.state = 'inactive'
        const data = new Blob(['deterministic-local-audio'.padEnd(128, 'x')], {
          type: this.mimeType,
        })
        const event = new Event('dataavailable')
        Object.defineProperty(event, 'data', { value: data })
        queueMicrotask(() => {
          this.dispatchEvent(event)
          this.dispatchEvent(new Event('stop'))
        })
      }
    }

    Object.defineProperty(window, 'MediaRecorder', {
      configurable: true,
      value: DeterministicMediaRecorder,
    })
  })
}

async function installDeniedMicrophone(page: Page): Promise<void> {
  await page.addInitScript(() => {
    Object.defineProperty(navigator, 'mediaDevices', {
      configurable: true,
      value: {
        getUserMedia: async () => {
          throw new DOMException('Denied for deterministic test', 'NotAllowedError')
        },
      },
    })
    class SupportedMediaRecorder {
      static isTypeSupported(): boolean {
        return true
      }
    }
    Object.defineProperty(window, 'MediaRecorder', {
      configurable: true,
      value: SupportedMediaRecorder,
    })
  })
}

function home(onboarded: boolean) {
  return {
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

function result(hasRecording: boolean) {
  return {
    attempt_id: ids.attempt,
    can_do: content('en-IN', 'I can introduce myself.'),
    check_state: 'correct',
    evidence: {
      best_attempt_id: ids.best,
      best_attempt_number: 2,
      first_attempt_id: ids.first,
      first_attempt_number: 1,
      latest_attempt_id: ids.latest,
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

function progress(hasRecording: boolean) {
  const missionResult = result(hasRecording)

  return {
    enrollment_id: ids.enrollment,
    objectives: [
      {
        evidence: missionResult.evidence,
        knowledge_state: 'mastered',
        label: content('en-IN', 'Introduce myself'),
        local_capture_receipt_present:
          missionResult.local_capture_receipt_present,
        objective_id: ids.objective,
        review_due_at: missionResult.review.due_at,
        review_timezone: missionResult.review.timezone,
        spoken_state: missionResult.spoken_state,
      },
    ],
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

function message(key: MessageKey): string {
  return resolveMessage(key, {
    policy: 'development-preview',
    requestedLocale: DEFAULT_LOCALE,
  }).text
}
