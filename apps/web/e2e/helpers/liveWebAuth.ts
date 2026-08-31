import { expect, type Page } from '@playwright/test'
import { localizedPattern } from './localizedMatchers'

export async function signInWithPassword(
  page: Page,
  username: string,
  password: string,
): Promise<void> {
  await page.goto('/#sign-in')
  await expect(page.locator('#sign-in-title')).toBeVisible({ timeout: 30_000 })
  await expect(page.locator('#web-password-username')).toBeVisible({
    timeout: 30_000,
  })

  await page.locator('#web-password-username').fill(username)
  await page.locator('#web-password-password').fill(password)

  const signInResponse = page.waitForResponse(
    (response) =>
      response.url().includes('/api/v1/auth/password/sign-ins') &&
      response.status() === 200,
  )

  await page
    .locator('form')
    .filter({ has: page.locator('#web-password-password') })
    .locator('button[type="submit"]')
    .click()

  await signInResponse

  await expect(
    page
      .getByRole('alert')
      .filter({ hasText: /could not be completed|ಪೂರ್ಣಗೊಳಿಸಲಾಗಲಿಲ್ಲ/i }),
  ).toHaveCount(0)

  await expect(
    page
      .getByRole('link', { name: localizedPattern('session.account_menu') })
      .first(),
  ).toBeVisible({ timeout: 30_000 })
}

export async function useEnglishLocale(page: Page): Promise<void> {
  await page.goto('/#me')
  const localeControl = page.locator('#interface-locale')
  await expect(localeControl).toBeVisible({ timeout: 15_000 })
  await localeControl.selectOption('en-IN')
  await expect(localeControl).toHaveValue('en-IN')
}
