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
    page.getByText('Scripted demo replies only. No cloud AI call is made.'),
  ).toBeVisible()
  await page.getByRole('button', { name: 'English' }).click()
  await expect(
    page.getByText('Welcome. You are on Level 1.', { exact: false }),
  ).toBeVisible()

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
  await expect(page.getByText('Pre-Level 1')).toBeVisible()
  await expect(page.getByText('Level 17')).toBeVisible()
  await expect(page.getByText('Conversation Room')).toBeVisible()

  await page.getByRole('link', { name: 'Notice Board' }).click()
  await expect(
    page.getByText('Welcome to the Phase 1 demo pathway'),
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
