import AxeBuilder from '@axe-core/playwright'
import { expect, test, type Page } from '@playwright/test'

/**
 * Full student click-through of the synthetic demo shell:
 * every Phase 1 module, Learn mission, Practice Friend + Role-play,
 * Progress, and primary shell destinations.
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
  await page.addInitScript(() => {
    window.localStorage.setItem('ge-ui-grove-welcome-dismissed', '1')
  })

  await page.goto('/')
  // Default UI locale is kn-IN; use the combobox role (not English name).
  await page.getByRole('combobox').selectOption('en-IN')
  await expect(
    page.getByRole('complementary', { name: 'Synthetic local demo' }),
  ).toHaveCount(0)

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
    page.getByRole('heading', { level: 1, name: 'Today' }),
  ).toBeVisible()
  await expect(
    page.getByRole('button', { name: /Continue today’s lesson/ }),
  ).toBeVisible()

  // Secondary student links from Home.
  for (const item of ['My Levels', 'AI Teacher', 'Profile']) {
    await page.getByRole('button', { name: item }).click()
    await expect(
      page.getByRole('heading', { level: 1, name: item }),
    ).toBeVisible()
    await page.getByRole('button', { name: 'Back' }).click()
  }

  // Profile holds attendance and notices.
  await page.getByRole('link', { name: 'Profile' }).click()
  await expect(page.getByRole('heading', { name: 'Demo status' })).toBeVisible()
  await expect(
    page.getByRole('complementary', { name: 'Synthetic local demo' }),
  ).toContainText('does not use learner data')
  await expect(page.getByText('About this school')).toBeVisible()
  await page.getByRole('button', { name: 'Attendance report' }).click()
  await expect(page.getByText('75%').or(page.getByText('75 %'))).toBeVisible()
  await page.getByRole('button', { name: 'Back' }).click()
  await page.getByRole('button', { name: 'Profile' }).click()
  await page.getByRole('button', { name: 'Notices' }).click()
  await expect(
    page.getByText('Welcome to the Phase 1 demo pathway'),
  ).toBeVisible()
  await page.getByRole('button', { name: 'Back' }).click()

  // AI Teacher — language toggles, focused prompts, Listen.
  await page.getByRole('button', { name: 'AI Teacher' }).click()
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
  for (const prompt of ["Today’s task", 'My level', 'Next step']) {
    await page.getByRole('button', { name: prompt }).click()
  }
  await page.getByRole('button', { name: 'Tap to Play' }).click()

  // Daily Activity — audio + full quiz from the Home CTA.
  await page.getByRole('button', { name: 'Back' }).click()
  await page.getByRole('button', { name: /Continue today’s lesson/ }).click()
  await page.getByRole('button', { name: 'Play model audio (demo)' }).click()
  const radios = page.getByRole('radio')
  for (let index = 0; index < 5; index += 1) {
    await radios.nth(index * 3).check()
  }
  await page.getByRole('button', { name: 'Submit daily quiz' }).click()
  await expect(page.getByText("Today's score")).toBeVisible()
  await expect(page.getByText('5/5')).toBeVisible()

  // My Levels — unlocked pickers.
  await page.getByRole('button', { name: 'Back' }).click()
  await page.getByRole('button', { name: 'My Levels' }).click()
  await page.getByRole('button', { name: /Pre-Level 1/i }).click()
  await page.getByRole('button', { name: /Pre-Level 2/i }).click()
  await page.getByRole('button', { name: /^Level 1\b/i }).click()
  await expect(page.getByText('Conversation Room')).toBeVisible()
  await expect(page.getByRole('button', { name: /Level 17/i })).toBeDisabled()

  // Learn mission (demo skips onboarding).
  await page.getByRole('link', { name: 'Learn' }).click()
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

  // Practice — coaching modes, Confidence Conversation, talk controls.
  await page.getByRole('link', { name: 'Speak' }).click()
  await expect(
    page.getByRole('dialog', { name: 'About this practice Friend' }),
  ).toBeVisible()
  await page.getByRole('button', { name: 'Got it' }).click()
  const coaching = page.getByLabel('Coaching mode')
  if (await coaching.count()) {
    await coaching.selectOption({ label: 'Tutor' })
    await coaching.selectOption({ label: 'Fluency' })
    await coaching.selectOption({ label: 'Gentle' })
  }
  await page.getByRole('button', { name: 'Start speaking practice' }).click()
  await expect(page.getByRole('heading', { name: 'Warm-up' })).toBeVisible()
  await page.getByRole('button', { name: 'Continue to listen' }).click()
  await expect(page.getByRole('heading', { name: 'Listen' })).toBeVisible()
  await page.getByRole('button', { name: 'Play model English' }).click()
  await page.getByRole('button', { name: 'Continue to talk' }).click()

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
      page.getByRole('heading', { name: 'Friend reply' }),
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

  // Fresh Practice-play entry after Confidence Conversation.
  await page.goto('/#practice')
  await page.getByLabel('Interface language').selectOption('en-IN')
  await page.getByRole('button', { name: 'Role-play (synthetic)' }).click()
  await page.getByRole('button', { name: 'Start Role-play' }).click()
  for (const stage of ['Observe', 'Rehearse', 'Perform', 'Reflect']) {
    await expect(page.getByRole('heading', { name: stage })).toBeVisible({
      timeout: 10_000,
    })
    if (stage !== 'Reflect') {
      await page.getByRole('button', { name: 'Continue' }).click()
    }
  }
  await expect(
    page.getByText('Advisory feedback', { exact: true }),
  ).toBeVisible()
  await page
    .getByRole('button', { name: 'Back to Confidence Conversation' })
    .click()

  // Progress (read-only).
  await page.getByRole('link', { name: 'Progress' }).click()
  await expect(
    page.getByRole('heading', { name: 'Your progress' }),
  ).toBeVisible()
  await expect(page.getByText('Introduce myself')).toBeVisible()

  // Return home.
  await page.getByRole('link', { name: 'Home' }).click()
  await expect(
    page.getByRole('heading', { level: 1, name: 'Today' }),
  ).toBeVisible()

  expect(apiRequests).toEqual([])
  expect(
    await page.evaluate(() =>
      Object.keys(window.localStorage).every((key) => key.startsWith('ge-ui-')),
    ),
  ).toBe(true)
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
