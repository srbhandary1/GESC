import AxeBuilder from '@axe-core/playwright'
import { expect, test } from '@playwright/test'

test('walks Grove first-run one question at a time before Home', async ({
  page,
}) => {
  await page.goto('/')
  await expect(
    page.getByRole('heading', { name: 'Interface language' }).or(
      page.getByRole('heading', { name: 'ಬಳಕೆಪರದೆಯ ಭಾಷೆ' }),
    ),
  ).toBeVisible()
  await expect(
    page.getByRole('heading', { level: 1, name: 'Today' }),
  ).toHaveCount(0)
  await expect(
    page.getByRole('link', { name: 'Download Android demo' }),
  ).toHaveAttribute('href', '/downloads/global-english-demo.apk')

  await page.getByRole('radio', { name: 'English' }).check()
  await page.getByRole('button', { name: 'Continue' }).click()
  await page
    .getByRole('radio', { name: 'Speak with confidence at work' })
    .check()
  await page.getByRole('button', { name: 'Continue' }).click()
  await page.getByRole('radio', { name: /I am just starting/ }).check()
  await page.getByRole('button', { name: 'Continue' }).click()

  await expect(
    page.getByRole('heading', { level: 1, name: 'Today' }),
  ).toBeVisible()
  await expect(page.getByRole('img', { name: 'AI Teacher' })).toBeVisible()
  await expect(
    page.getByRole('button', { name: /Continue today’s lesson/ }),
  ).toBeVisible()
  await expect(
    page.getByRole('link', { name: 'Download Android demo' }),
  ).toHaveAttribute('href', '/downloads/global-english-demo.apk')
})

test('runs the disclosed Phase 1 dashboard demo without API calls', async ({
  page,
}) => {
  const apiRequests: string[] = []
  page.on('request', (request) => {
    if (new URL(request.url()).pathname.startsWith('/api/')) {
      apiRequests.push(request.url())
    }
  })

  await page.addInitScript(() => {
    window.localStorage.setItem('ge-ui-grove-welcome-dismissed', '1')
  })
  await page.goto('/')
  await page.getByRole('combobox').selectOption('en-IN')

  await expect(page.locator('.demo-experience')).toBeVisible()
  await expect(
    page.getByRole('complementary', { name: 'Synthetic local demo' }),
  ).toHaveCount(0)
  await expect(
    page.getByRole('heading', { level: 1, name: 'Today' }),
  ).toBeVisible()
  await expect(
    page.getByRole('button', { name: /Continue today’s lesson/ }),
  ).toBeVisible()
  await expect(
    page.getByRole('link', { name: 'Download Android demo' }),
  ).toHaveAttribute('href', '/downloads/global-english-demo.apk')
  await expect(page.getByRole('button', { name: '16 lessons' })).toBeVisible()
  await page.getByRole('button', { name: '16 lessons' }).click()
  await expect(
    page.getByRole('heading', { name: '16 speaking lessons' }),
  ).toBeVisible()
  await expect(
    page.getByRole('button', { name: 'Tap to Play lesson 16' }),
  ).toBeVisible()
  await page.getByRole('link', { name: 'Home' }).click()

  await page.getByRole('button', { name: 'Profile' }).click()
  await expect(
    page.getByRole('heading', { level: 1, name: 'Profile' }),
  ).toBeVisible()
  await expect(
    page.getByRole('heading', { name: 'Demo status' }),
  ).toBeVisible()
  await expect(
    page.getByRole('complementary', { name: 'Synthetic local demo' }),
  ).toContainText('does not use learner data')
  await page.getByRole('button', { name: 'Attendance report' }).click()
  await expect(
    page.getByRole('heading', { level: 1, name: 'Attendance' }),
  ).toBeVisible()
  await expect(page.getByText('75%')).toBeVisible()

  await page.getByRole('button', { name: 'Back' }).click()
  await page.getByRole('button', { name: 'AI Teacher' }).click()
  await expect(
    page.getByText(
      'Scripted demo replies. Voice uses Azure neural TTS when configured; no generative AI call.',
    ),
  ).toBeVisible()
  await page.getByRole('button', { name: 'English' }).first().click()
  await expect(
    page.getByText('Welcome. You are on Level 1.', { exact: false }),
  ).toBeVisible()
  for (const prompt of ["Today’s task", 'My level', 'Next step']) {
    await page.getByRole('button', { name: prompt }).click()
  }
  await page.getByRole('button', { name: 'Tap to Play' }).click()

  await page.getByRole('button', { name: 'Back' }).click()
  await page.getByRole('button', { name: /Continue today’s lesson/ }).click()
  await expect(page.getByText("Today's 5-mark check")).toBeVisible()
  await page.getByRole('button', { name: 'Play model audio (demo)' }).click()
  const radios = page.getByRole('radio')
  for (let index = 0; index < 5; index += 1) {
    await radios.nth(index * 3).check()
  }
  await page.getByRole('button', { name: 'Submit daily quiz' }).click()
  await expect(page.getByText("Today's score")).toBeVisible()
  await expect(page.getByText('5/5')).toBeVisible()

  await page.getByRole('button', { name: 'Back' }).click()
  await page.getByRole('button', { name: 'My Levels' }).click()
  await expect(page.getByText('Pre-Level 1')).toBeVisible()
  await expect(page.getByText('Level 17')).toBeVisible()
  await expect(page.getByText('Conversation Room')).toBeVisible()

  await page.getByRole('button', { name: 'Back' }).click()
  await page.getByRole('button', { name: 'Profile' }).click()
  await page.getByRole('button', { name: 'Notices' }).click()
  await expect(
    page.getByText('Welcome to the Phase 1 demo pathway'),
  ).toBeVisible()

  // Primary shell destinations also available to a student in the demo.
  await page.getByRole('link', { name: 'Learn' }).click()
  await page.getByRole('link', { name: 'Speak' }).click()
  await expect(
    page.getByRole('dialog', { name: 'About this practice Friend' }),
  ).toBeVisible()
  await page.getByRole('button', { name: 'Got it' }).click()
  await page.getByRole('link', { name: 'Progress' }).click()
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
  expect(
    await page.evaluate(async () => {
      const registrations =
        (await navigator.serviceWorker?.getRegistrations()) ?? []

      return registrations.length
    }),
  ).toBe(0)
  const accessibility = await new AxeBuilder({ page }).analyze()
  expect(accessibility.violations).toEqual([])
})
