import AxeBuilder from '@axe-core/playwright'
import { expect, test, type Page } from '@playwright/test'

/**
 * Full student click-through of the synthetic demo shell:
 * every Phase 1 module, demo mission (`#mission`), Learn level path,
 * Speak room, 1:1, Progress, and primary shell destinations.
 */
test('student click-through exercises every demo page and control', async ({
  page,
}) => {
  test.setTimeout(180_000)
  const apiRequests: string[] = []
  page.on('request', (request) => {
    if (new URL(request.url()).pathname.startsWith('/api/')) {
      apiRequests.push(request.url())
    }
  })
  await installWorkingMicrophone(page)

  await page.goto('/')
  // Default UI locale is kn-IN; use the combobox role (not English name).
  await page.getByRole('combobox').selectOption('en-IN')
  await expect(
    page.getByRole('complementary', { name: 'Synthetic local demo' }),
  ).toBeVisible()

  // Locale cycle (all Phase 1 interface options), then return to English.
  for (const locale of [
    'kn-IN',
    'ml-IN',
    'ta-IN',
    'te-IN',
    'hi-IN',
    'en-IN',
  ] as const) {
    await page.getByRole('combobox').selectOption(locale)
  }

  await page.getByRole('link', { name: 'Home' }).click()
  await expect(
    page.getByRole('heading', { level: 1, name: 'Dashboard' }),
  ).toBeVisible()

  // Dashboard module cards.
  for (const card of [
    'Attendance',
    'AI Teacher',
    'Daily Activity',
    'My Levels',
    'Notice Board',
  ]) {
    await page.getByRole('button', { name: new RegExp(card) }).click()
    await expect(
      page.getByRole('heading', { level: 1, name: card }),
    ).toBeVisible()
    await page.getByRole('link', { name: 'Dashboard' }).click()
  }

  // Attendance (read-only).
  await page.getByRole('link', { name: 'Attendance' }).click()
  await expect(page.getByText('75%').or(page.getByText('75 %'))).toBeVisible()

  // AI Teacher — language toggles, every prompt, Listen.
  await page.getByRole('link', { name: 'AI Teacher' }).click()
  await expect(
    page.getByText(
      'Scripted demo replies. Voice uses Azure neural TTS when configured; no generative AI call.',
    ),
  ).toBeVisible()
  const englishButtons = page.getByRole('button', { name: 'English' })
  await englishButtons.first().click()
  if ((await englishButtons.count()) > 1) {
    await englishButtons.nth(1).click()
  }
  for (const prompt of [
    'Welcome me',
    'Explain the course',
    'Explain my level',
    'Attendance status',
    'Daily score',
    'Motivate me',
  ]) {
    await page.getByRole('button', { name: prompt }).click()
  }
  await page.getByRole('button', { name: 'Listen' }).click()

  // Daily Activity — audio + full quiz.
  await page.getByRole('link', { name: 'Daily Activity' }).click()
  await page.getByRole('button', { name: 'Play model audio (demo)' }).click()
  const radios = page.getByRole('radio')
  for (let index = 0; index < 5; index += 1) {
    await radios.nth(index * 3).check()
  }
  await page.getByRole('button', { name: 'Submit daily quiz' }).click()
  await expect(page.getByText("Today's score")).toBeVisible()
  await expect(page.getByText('5/5')).toBeVisible()

  // My Levels — unlocked pickers.
  await page.getByRole('link', { name: 'My Levels' }).click()
  await page.getByRole('button', { name: /Pre-Level 1/i }).click()
  await page.getByRole('button', { name: /Pre-Level 2/i }).click()
  await page.getByRole('button', { name: /^Level 1\b/i }).click()
  await expect(page.getByText('Conversation Room')).toBeVisible()
  await expect(page.getByRole('button', { name: /Level 17/i })).toBeDisabled()

  // Notice Board.
  await page.getByRole('link', { name: 'Notice Board' }).click()
  await expect(
    page.getByText('Welcome to the Phase 1 demo pathway'),
  ).toBeVisible()

  // Learn mission (demo skips onboarding).
  await page.goto('/#mission')
  await page.getByRole('button', { name: 'Start mission' }).click()
  await page.getByRole('button', { name: 'Play model audio' }).click()
  await page
    .getByRole('button', { name: 'Continue to speaking practice' })
    .click()
  await page.getByRole('button', { name: 'Start recording' }).click()
  await page.waitForTimeout(300)
  await page.getByRole('button', { name: 'Stop recording' }).click()
  await expect(
    page.getByLabel('Your recording playback'),
  ).toBeVisible()
  await page.getByRole('button', { name: 'Record again' }).click()
  await page.getByRole('button', { name: 'Start recording' }).click()
  await page.waitForTimeout(300)
  await page.getByRole('button', { name: 'Stop recording' }).click()
  await page
    .getByRole('button', { name: 'Continue to the check' })
    .click()
  await page.getByRole('radio', { name: 'Hello, my name is Ravi.' }).check()
  await page.getByRole('button', { name: 'Submit check' }).click()
  await expect(
    page.getByRole('heading', { name: 'Mission result' }),
  ).toBeVisible()
  await page.getByRole('button', { name: 'Try the mission again' }).click()
  // Retry restarts the mission at listen (not the home Start button).
  await expect(
    page.getByRole('button', { name: 'Play model audio' }),
  ).toBeVisible()
  await expect(
    page.getByRole('button', { name: 'Continue to speaking practice' }),
  ).toBeVisible()

  // Learn — four-session level path.
  await page.getByRole('link', { name: 'Learn' }).click()
  await expect(
    page.getByRole('button', { name: /Level 1/i }),
  ).toBeVisible()

  // Speak — coaching modes, everyday talk, talk controls.
  await page.getByRole('link', { name: 'Speak' }).click()
  const coaching = page.getByLabel('Coaching mode')
  if (await coaching.count()) {
    await coaching.selectOption({ label: 'Tutor' })
    await coaching.selectOption({ label: 'Fluency' })
    await coaching.selectOption({ label: 'Gentle' })
  }
  await page.getByRole('button', { name: 'Start speaking practice' }).click()
  await expect(page.getByText('Hear an example')).toBeVisible()
  await page.getByRole('button', { name: 'Type instead' }).click()
  const turnBox = page.getByRole('textbox', {
    name: 'Type one English practice turn',
  })

  const sendPracticeTurn = async (text: string) => {
    await turnBox.click()
    await turnBox.fill(text)
    const send = page.getByRole('button', { name: 'Send turn' })
    await expect(send).toBeEnabled({ timeout: 5_000 })
    await send.click()
    await expect(
      page.getByRole('heading', { name: 'Maya' }),
    ).toBeVisible({ timeout: 15_000 })
  }

  await sendPracticeTurn('I practise English after work.')
  await page.getByRole('button', { name: 'Hint in my language' }).click()
  await expect(
    page.getByText('Answer with one short English sentence', {
      exact: false,
    }),
  ).toBeVisible()
  await page.getByRole('button', { name: 'Say that again' }).click()
  const playReply = page.getByRole('button', { name: 'Play Friend reply' })
  if (await playReply.isVisible().catch(() => false)) {
    await playReply.click()
  }

  // Second completed turn unlocks Finish practice (min turns = 2).
  await sendPracticeTurn('I spoke with my Friend again today.')
  await page.getByRole('button', { name: 'Skip this turn' }).click()
  await page.getByRole('button', { name: 'Finish practice' }).click()
  await expect(page.getByRole('heading', { name: 'Reflect' })).toBeVisible()

  const startAgain = page.getByRole('button', { name: 'Start again' })
  if (await startAgain.isVisible().catch(() => false)) {
    await startAgain.click()
  }

  // Fresh Speak-room situation after everyday talk.
  await page.goto('/#speak')
  await page.getByLabel('Interface language').selectOption('en-IN')
  await page.getByRole('tab', { name: 'A situation' }).click()
  await page.getByRole('button', { name: 'Start this situation' }).click()
  await expect(page.getByRole('heading', { exact: true, name: 'Observe' })).toBeVisible({
    timeout: 10_000,
  })
  await page.getByRole('button', { name: 'Continue' }).click()
  await expect(page.getByRole('heading', { exact: true, name: 'Rehearse' })).toBeVisible()
  await page.getByRole('button', { name: 'Continue' }).click()
  await expect(page.getByRole('heading', { exact: true, name: 'Perform' })).toBeVisible({
    timeout: 10_000,
  })
  await page.getByRole('button', { name: 'Type instead' }).click()
  await page
    .getByRole('textbox', { name: 'Type one English practice turn' })
    .fill('Hello, I have an appointment.')
  await page.getByRole('button', { name: 'Send turn' }).click()
  await page.getByRole('button', { name: 'Continue' }).click()
  await expect(page.getByRole('heading', { name: 'Reflect' })).toBeVisible({
    timeout: 10_000,
  })
  await expect(
    page.getByText('Advisory feedback', { exact: true }),
  ).toBeVisible()
  await page
    .getByRole('button', { name: 'Back to everyday talk' })
    .click()

  // 1:1 waitlist (no in-app call).
  await page.getByRole('link', { name: '1:1' }).click()
  await expect(
    page.getByRole('heading', { name: 'Request 1:1 tuition' }),
  ).toBeVisible()

  // Progress (read-only).
  await page.getByRole('link', { name: 'Progress' }).click()
  await expect(
    page.getByRole('heading', { name: 'Your progress' }),
  ).toBeVisible()
  await expect(page.getByText('Introduce myself')).toBeVisible()

  // Return home.
  await page.getByRole('link', { name: 'Home' }).click()
  await expect(
    page.getByRole('heading', { level: 1, name: 'Dashboard' }),
  ).toBeVisible()

  expect(apiRequests).toEqual([])
  expect(await page.evaluate(() => window.localStorage.length)).toBe(0)
  const accessibility = await new AxeBuilder({ page }).analyze()
  expect(accessibility.violations).toEqual([])
})

async function installWorkingMicrophone(page: Page): Promise<void> {
  await page.addInitScript(() => {
    Object.defineProperty(navigator, 'mediaDevices', {
      configurable: true,
      value: {
        getUserMedia: async () => ({
          getTracks: () => [{ stop: () => undefined }],
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
