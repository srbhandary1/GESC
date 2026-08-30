import { expect, test } from '@playwright/test'

const challengeId = '01J00000000000000000000001'
const token = 's'.repeat(43)

test.beforeEach(async ({ page }) => {
  await page.route('**/sanctum/csrf-cookie', async (route) => {
    await route.fulfill({ status: 204 })
  })
})

test('consumes an account-setup fragment without browser persistence', async ({
  page,
}) => {
  let submitted: unknown = null
  await page.route('**/api/v1/auth/password/setups', async (route) => {
    submitted = route.request().postDataJSON()
    await route.fulfill({ status: 204 })
  })
  await page.goto(
    `/password-establishment.html#challenge_id=${challengeId}&token=${token}`,
  )

  await expect(page).toHaveURL(/password-establishment\.html$/)
  await page.getByLabel('Username').fill('setup.user')
  await page
    .getByLabel('Password (15 to 128 characters)')
    .fill('Setup user correct battery 2026!')
  await page.getByRole('button', { name: 'Set username and password' }).click()

  await expect.poll(() => submitted).not.toBeNull()
  expect(submitted).toEqual({
    challenge_id: challengeId,
    username: 'setup.user',
    token,
    password: 'Setup user correct battery 2026!',
  })
  await expect(page.getByRole('status')).toContainText(
    'Username and password established',
  )
  expect(await page.content()).not.toContain(token)
  expect(
    await page.evaluate(() => ({
      local: Object.keys(localStorage),
      session: Object.keys(sessionStorage),
    })),
  ).toEqual({ local: [], session: [] })
})

test('loads protected-local setup metadata with a same-origin referrer', async ({
  page,
}) => {
  let requestReferrer: string | undefined
  await page.route('**/api/v1/local/password-establishment', async (route) => {
    requestReferrer = route.request().headers().referer
    await route.fulfill({
      contentType: 'application/json',
      status: 200,
      body: JSON.stringify({
        delivery: {
          challenge_id: challengeId,
          expires_at: '2026-08-13T08:48:10+00:00',
          recovery_hint: 'verified contact',
        },
      }),
    })
  })

  await page.goto('/password-establishment.html')

  await expect(
    page.getByRole('button', { name: 'Set username and password' }),
  ).toBeEnabled()
  await expect.poll(() => requestReferrer).toBe(
    `${new URL(page.url()).origin}/password-establishment.html`,
  )
})

test('consumes a password-recovery fragment without browser persistence', async ({
  page,
}) => {
  let submitted: unknown = null
  await page.route('**/api/v1/auth/password/recoveries', async (route) => {
    submitted = route.request().postDataJSON()
    await route.fulfill({ status: 204 })
  })
  await page.goto(
    `/password-recovery.html#challenge_id=${challengeId}&token=${token}`,
  )

  await expect(page).toHaveURL(/password-recovery\.html$/)
  await page.getByLabel('Username').fill('recovery.user')
  await page
    .getByLabel('New password (15 to 128 characters)')
    .fill('Recovery user correct battery 2026!')
  await page
    .getByLabel('Confirm new password')
    .fill('Recovery user correct battery 2026!')
  await page.getByRole('button', { name: 'Set new password' }).click()

  await expect.poll(() => submitted).not.toBeNull()
  expect(submitted).toEqual({
    challenge_id: challengeId,
    username: 'recovery.user',
    token,
    new_password: 'Recovery user correct battery 2026!',
  })
  await expect(page.getByRole('status')).toContainText(
    'Your password has been changed',
  )
  expect(await page.content()).not.toContain(token)
})
