import AxeBuilder from '@axe-core/playwright'
import { expect, test } from '@playwright/test'

const challengeId = '01J90000000000000000000091'

test('excluded-account commissioning keeps approval evidence content-free and ephemeral', async ({
  page,
}) => {
  let opened = false
  let openAttempts = 0
  let openBody: unknown = null

  await page.route('**/api/v1/local/excluded-password-recovery/**', async (route) => {
    const request = route.request()

    if (request.method() === 'GET') {
      await route.fulfill({
        contentType: 'application/json',
        body: JSON.stringify({
          target: {
            membership_type: 'organization_admin',
            recovery_hint: '+91******4411',
          },
          case: opened
            ? {
                status: 'pending_approval',
                recovery_kind: 'excluded_credential_establishment',
                not_before_at: '2026-08-12T10:00:00Z',
                expires_at: '2026-08-19T10:00:00Z',
                approval_count: 0,
              }
            : null,
        }),
        status: 200,
      })

      return
    }

    openBody = request.postDataJSON()
    openAttempts += 1

    if (openAttempts === 1) {
      await route.fulfill({
        contentType: 'application/problem+json',
        body: JSON.stringify({
          detail: 'Fresh password confirmation is required.',
          message_key: 'problem.fresh_mfa_required',
        }),
        status: 409,
      })

      return
    }

    opened = true
    await route.fulfill({
      contentType: 'application/json',
      body: JSON.stringify({
        status: 'pending_approval',
        recovery_kind: 'excluded_credential_establishment',
        approval_count: 0,
      }),
      status: 201,
    })
  })

  await page.goto('/password-recovery-commissioning.html')
  await expect(page.getByText('organization_admin')).toBeVisible()
  await expect(page.getByText('+91******4411')).toBeVisible()
  await page.getByRole('button', { name: 'Open recovery case' }).click()
  await expect(
    page.getByText(
      'Confirm the current password under Username and password security, then retry within five minutes.',
    ),
  ).toBeVisible()
  await page.getByRole('button', { name: 'Open recovery case' }).click()
  await expect(page.getByText('pending_approval')).toBeVisible()

  expect(openBody).toEqual({})
  expect(await page.evaluate(() => localStorage.length)).toBe(0)
  expect(await page.evaluate(() => sessionStorage.length)).toBe(0)
  expect(await page.evaluate(async () => (await indexedDB.databases()).length)).toBe(0)
  expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
})

test('target establishment never receives the protected token in the browser', async ({
  page,
}) => {
  const submitted: Array<Record<string, unknown>> = []

  await page.route('**/api/v1/local/password-establishment', async (route) => {
    const request = route.request()

    if (request.method() === 'GET') {
      await route.fulfill({
        contentType: 'application/json',
        body: JSON.stringify({
          delivery: {
            challenge_id: challengeId,
            expires_at: '2026-08-11T10:15:00Z',
            recovery_hint: '+91******4411',
          },
        }),
        status: 200,
      })

      return
    }

    submitted.push(request.postDataJSON() as Record<string, unknown>)
    await route.fulfill({ status: 204 })
  })

  await page.goto('/password-establishment.html')
  await expect(page.getByText('Verified contact: +91******4411')).toBeVisible()
  await page.getByLabel('Username').fill('recovered.operator')
  await page.getByLabel('Password (15 to 128 characters)').fill(
    'Target chooses browser battery 2026!',
  )
  await page.getByRole('button', { name: 'Set username and password' }).click()

  await expect(page.getByText('Username and password established.')).toBeVisible()
  await expect(page.getByLabel('Password (15 to 128 characters)')).toHaveValue('')
  expect(submitted).toEqual([
    {
      challenge_id: challengeId,
      username: 'recovered.operator',
      password: 'Target chooses browser battery 2026!',
    },
  ])
  expect(JSON.stringify(submitted)).not.toContain('token')
  expect(await page.evaluate(() => localStorage.length)).toBe(0)
  expect(await page.evaluate(() => sessionStorage.length)).toBe(0)
  expect(await page.evaluate(async () => (await indexedDB.databases()).length)).toBe(0)

  await page.setViewportSize({ height: 800, width: 320 })
  await expect
    .poll(() =>
      page.evaluate(
        () =>
          document.documentElement.scrollWidth <=
          document.documentElement.clientWidth,
      ),
    )
    .toBe(true)
  expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
})
