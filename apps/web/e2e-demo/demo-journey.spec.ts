import AxeBuilder from '@axe-core/playwright'
import { expect, test } from '@playwright/test'

test('runs the disclosed Phase 1 dashboard demo without API calls', async ({
  page,
}) => {
  const apiRequests: string[] = []
  page.on('request', (request) => {
    if (new URL(request.url()).pathname.startsWith('/api/')) {
      apiRequests.push(request.url())
    }
  })

  await page.goto('/')
  await page.getByRole('combobox').selectOption('en-IN')

  await expect(
    page.getByRole('complementary', { name: 'Synthetic local demo' }),
  ).toContainText('does not use learner data')
  await expect(page.locator('.demo-experience')).toBeVisible()
  await expect(
    page.getByRole('heading', { level: 1, name: 'Dashboard' }),
  ).toBeVisible()

  await page.getByRole('link', { name: 'Attendance' }).click()
  await expect(
    page.getByRole('heading', { level: 1, name: 'Attendance' }),
  ).toBeVisible()
  await expect(page.getByText('75%')).toBeVisible()

  await page.getByRole('link', { name: 'AI Teacher' }).click()
  await expect(
    page.getByText(
      'Scripted demo replies. Voice uses Azure neural TTS when configured; no generative AI call.',
    ),
  ).toBeVisible()
  await page.getByRole('button', { name: 'English' }).first().click()
  await expect(
    page.getByText('Welcome. You are on Level 1.', { exact: false }),
  ).toBeVisible()
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

  await page.getByRole('link', { name: 'Daily Activity' }).click()
  await expect(page.getByText("Today's 5-mark check")).toBeVisible()
  await page.getByRole('button', { name: 'Play model audio (demo)' }).click()
  const radios = page.getByRole('radio')
  for (let index = 0; index < 5; index += 1) {
    await radios.nth(index * 3).check()
  }
  await page.getByRole('button', { name: 'Submit daily quiz' }).click()
  await expect(page.getByText("Today's score")).toBeVisible()
  await expect(page.getByText('5/5')).toBeVisible()

  await page.getByRole('link', { name: 'My Levels' }).click()
  await expect(page.getByText('Can — ability and permission')).toBeVisible()
  await expect(page.getByText('Used to — past habits')).toBeVisible()
  await expect(page.getByText('All levels')).toBeVisible()

  await page.getByRole('link', { name: 'Notice Board' }).click()
  await expect(
    page.getByText('Welcome to the Phase 1 demo pathway'),
  ).toBeVisible()

  // Primary shell destinations also available to a student in the demo.
  await page.getByRole('link', { name: 'Learn' }).click()
  await page.getByRole('link', { name: 'Speak' }).click()
  await page.getByRole('link', { name: 'Progress' }).click()
  await page.getByRole('link', { name: '1:1' }).click()
  await page.getByRole('link', { name: 'Home' }).click()
  await expect(
    page.getByRole('heading', { level: 1, name: 'Dashboard' }),
  ).toBeVisible()

  expect(apiRequests).toEqual([])
  expect(await page.evaluate(() => window.localStorage.length)).toBe(0)
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
