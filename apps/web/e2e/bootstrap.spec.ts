import { expect, test } from '@playwright/test'

test('renders the Global English secure identity shell', async ({ page }) => {
  await page.route('**/api/v1/me', async (route) => {
    await route.fulfill({
      contentType: 'application/problem+json',
      json: {
        type: 'about:blank',
        title: 'Unauthenticated',
        status: 401,
        detail: 'Authentication is required.',
        instance: '/api/v1/me',
        message_key: 'auth.required',
        correlation_id: '3a8f71f5-aade-4a6e-8b40-48f8912fe63f',
      },
      status: 401,
    })
  })

  await page.goto('/')

  await expect(page.getByRole('main')).toBeVisible()
  await expect(
    page.getByRole('heading', { name: 'Global English' }),
  ).toBeVisible()
  await expect(page.getByRole('status')).toContainText(
    'PR-003 tenancy and secure sign-in foundation is ready.',
  )
  await expect(page.getByRole('alert')).toHaveCount(0)
  await expect
    .poll(() => page.evaluate(() => window.localStorage.length))
    .toBe(0)
})
