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

  await page.getByRole('link', { name: 'Practice' }).click()
  await page
    .getByRole('button', { name: 'Start AI Friend prototype' })
    .click()
  await page
    .getByRole('textbox', { name: 'Type one English practice turn' })
    .fill('I practise English after work.')
  await page.getByRole('button', { name: 'Send turn' }).click()
  await expect(
    page.getByText(
      'Thank you for sharing that. What would you like to talk about next?',
    ),
  ).toBeVisible()
  await expect(page.getByText('Turn completed')).toBeVisible()

  expect(apiRequests).toEqual([])
  expect(await page.evaluate(() => window.localStorage.length)).toBe(0)
  const accessibility = await new AxeBuilder({ page }).analyze()
  expect(accessibility.violations).toEqual([])
})
