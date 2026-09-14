import { expect, test } from '@playwright/test'

const origin = process.env.GE_STAFF_API_ORIGIN ?? 'http://117.254.108.143:8080'
const username = process.env.GE_STAFF_USERNAME ?? 'northstar'
const password = process.env.GE_STAFF_PASSWORD ?? ''

test.skip(password === '', 'Set GE_STAFF_PASSWORD to run the live staff UI walk.')

test.use({ baseURL: origin })

test('staff learner can sign in and walk primary destinations', async ({
  page,
}) => {
  const consoleErrors: string[] = []
  page.on('pageerror', (error) => {
    consoleErrors.push(error.message)
  })

  await page.setViewportSize({ width: 390, height: 844 })
  await page.goto('/#home')

  const signIn = page.locator('a[href="#sign-in"]').first()
  await expect(signIn).toBeVisible()
  await signIn.click()
  await expect(page.locator('#sign-in-title')).toBeVisible()

  const card = page.locator('.shell-sign-in-page__card')
  await expect(card).toBeVisible()
  const cardColor = await card.evaluate((node) => {
    const styles = getComputedStyle(node)
    return { color: styles.color, background: styles.backgroundColor }
  })
  expect(cardColor.color).not.toBe(cardColor.background)

  await page.locator('#web-password-username').fill(username)
  await page.locator('#web-password-password').fill(password)
  await page.locator('.web-otp-sign-in__form button[type="submit"]').click()

  await expect(page.locator('#web-password-error')).toHaveCount(0)
  await expect(page).toHaveURL(/#home/)
  await expect(page.locator('.ge-application-frame--jezweb')).toBeVisible()

  const nav = page.getByRole('navigation').first()
  await expect(nav).toBeVisible()

  for (const hash of ['#learn', '#speak', '#progress', '#tuition', '#home']) {
    await page.locator(`a[href="${hash}"]`).first().click()
    await expect(page).toHaveURL(new RegExp(`${hash}$`))
    await page.waitForTimeout(250)
  }

  const glyphs = page.locator('.ge-primary-navigation__link .ge-nav-glyph')
  const glyphCount = await glyphs.count()
  const linkCount = await page.locator('.ge-primary-navigation__link').count()
  expect(glyphCount).toBeLessThanOrEqual(linkCount)

  await page.locator('a[href="#learn"]').first().click()
  await expect(page).toHaveURL(/#learn/)
  const levelOne = page.getByRole('button', { name: /ಮಟ್ಟ 1|Level 1/ }).first()
  await expect(levelOne).toBeVisible()
  await levelOne.click()
  const introduction = page.getByRole('button', { name: /ಪರಿಚಯ|Introduction/ }).first()
  if (await introduction.count()) {
    await introduction.click()
  }

  const audio = page.locator('.model-audio-player')
  await expect(audio.first()).toBeVisible({ timeout: 20_000 })
  const toolbar = page.locator('.model-audio-player__toolbar').first()
  await expect(toolbar).toBeVisible()
  const toolbarButtons = toolbar.locator('button')
  expect(await toolbarButtons.count()).toBeGreaterThanOrEqual(1)
  const boxes = await toolbarButtons.all()
  const firstBox = await boxes[0]?.boundingBox()
  const lastBox = await boxes[boxes.length - 1]?.boundingBox()
  if (firstBox !== null && firstBox !== undefined && lastBox !== null && lastBox !== undefined) {
    expect(Math.abs(firstBox.y - lastBox.y)).toBeLessThan(16)
  }

  const hold = page.getByRole('button', { name: /hold|hold to talk|ದಬಾಯಿಸಿ/i })
  if (await hold.count()) {
    await expect(hold.first()).toBeVisible()
  }

  await page.locator('a[href="#speak"]').first().click()
  await expect(page).toHaveURL(/#speak/)
  await page.locator('a[href="#progress"]').first().click()
  await expect(page).toHaveURL(/#progress/)
  await page.locator('a[href="#tuition"]').first().click()
  await expect(page).toHaveURL(/#tuition/)

  expect(consoleErrors, consoleErrors.join('\n')).toEqual([])
})
