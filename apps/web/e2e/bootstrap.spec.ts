import { expect, test } from '@playwright/test'

test('renders the Global English bootstrap shell', async ({ page }) => {
  await page.goto('/')

  await expect(page.getByRole('main')).toBeVisible()
  await expect(
    page.getByRole('heading', { name: 'Global English' }),
  ).toBeVisible()
  await expect(page.getByRole('status')).toContainText(
    'PR-002 contracts and health foundation is ready.',
  )
  await expect(page.getByRole('alert')).toHaveCount(0)
})
