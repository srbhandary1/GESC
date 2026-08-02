import AxeBuilder from '@axe-core/playwright'
import { expect, test } from '@playwright/test'

test('runs the disclosed synthetic lesson and Friend demo without API calls', async ({
  page,
}) => {
  const apiRequests: string[] = []
  page.on('request', (request) => {
    if (new URL(request.url()).pathname.startsWith('/api/')) {
      apiRequests.push(request.url())
    }
  })
  await page.addInitScript(() => {
    Object.defineProperty(navigator, 'mediaDevices', {
      configurable: true,
      value: {
        getUserMedia: async () => {
          throw new DOMException('Denied for local demo test', 'NotAllowedError')
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

  await page.goto('/')
  await page.getByRole('combobox').selectOption('en-IN')

  await expect(
    page.getByRole('complementary', { name: 'Synthetic local demo' }),
  ).toContainText('does not use learner data')
  await expect(
    page.getByRole('link', { name: 'Home' }),
  ).toHaveAttribute('aria-current', 'page')
  await expect(page.getByText('Introduce yourself')).toBeVisible()

  await page.getByRole('button', { name: 'Start mission' }).click()
  await expect(page.getByText('Hello, my name is Ravi.').first()).toBeVisible()
  await page
    .getByRole('button', { name: 'Continue to speaking practice' })
    .click()
  await page.getByRole('button', { name: 'Start recording' }).click()
  await page
    .getByRole('button', { name: 'Continue without recording' })
    .click()
  await page
    .getByRole('radio', { name: 'Hello, my name is Ravi.' })
    .check()
  await page.getByRole('button', { name: 'Submit check' }).click()
  await expect(page.getByText('Mastered')).toBeVisible()
  await expect(page.getByText('Unassessed').first()).toBeVisible()

  await page.getByRole('link', { name: 'Progress' }).click()
  await expect(
    page.getByRole('heading', { level: 1, name: 'Progress' }),
  ).toBeVisible()
  await expect(
    page.getByRole('heading', { level: 2, name: 'Objective progress' }),
  ).toBeVisible()
  await expect(
    page.getByRole('heading', { level: 3, name: 'Introduce myself' }),
  ).toBeVisible()
  await expect(
    page.getByRole('heading', {
      level: 3,
      name: 'Greet a colleague at work',
    }),
  ).toBeVisible()

  await page.getByRole('link', { name: 'Practice' }).click()
  await page
    .getByRole('button', { name: 'Start AI Friend prototype' })
    .click()
  const utterance = page.getByRole('textbox', {
    name: 'Type one English practice turn',
  })
  await utterance.fill('I practise English after work.')
  await page.getByRole('button', { name: 'Send turn' }).click()
  await expect(
    page.getByText('That sounds interesting. What happened next?'),
  ).toBeVisible()
  await expect(page.getByText('Turn completed')).toBeVisible()

  await utterance.fill('Yesterday I go to the market.')
  await page.getByRole('button', { name: 'Send turn' }).click()
  await expect(
    page.getByText(
      'One gentle tip: with "yesterday", use the past form',
      { exact: false },
    ),
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
