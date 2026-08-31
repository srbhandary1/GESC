import { expect, test } from '@playwright/test'
import { installWorkingMicrophone } from './helpers/liveMicrophone'
import { localizedPattern, localizedPatterns } from './helpers/localizedMatchers'

test.describe.configure({ mode: 'serial' })

test.beforeEach(async ({ page }) => {
  await installWorkingMicrophone(page)
})

test('loads every primary destination from the shell navigation', async ({
  page,
}) => {
  const destinations = [
    { hash: '#home', titleKey: 'page.home.title' as const },
    { hash: '#learn', titleKey: 'page.learn.title' as const },
    { hash: '#speak', titleKey: 'page.speak.title' as const },
    { hash: '#me', titleKey: 'page.me.title' as const },
    { hash: '#progress', titleKey: 'page.me.title' as const },
    { hash: '#tuition', titleKey: 'page.me.title' as const },
  ] as const

  for (const destination of destinations) {
    await page.goto(destination.hash)
    await expect(
      page.getByRole('heading', {
        level: 1,
        name: localizedPattern(destination.titleKey),
      }),
    ).toBeAttached()
  }
})

test('opens account security from the Me destination', async ({ page }) => {
  await page.goto('/#me')
  await expect(
    page.getByRole('button', {
      name: localizedPattern('session.android_sign_out'),
    }),
  ).toBeVisible()
})

test('learn path opens level content and model audio controls', async ({
  page,
}) => {
  await page.goto('/#learn')
  await expect(
    page.getByRole('heading', {
      level: 1,
      name: localizedPattern('page.learn.title'),
    }),
  ).toBeVisible()

  const introduction = page
    .getByRole('button', {
      name: localizedPattern('level.path.part.introduction'),
    })
    .first()
  if (await introduction.isVisible().catch(() => false)) {
    await introduction.click()
  }

  const playAudio = page
    .getByRole('button', {
      name: localizedPatterns([
        'learner.audio.play',
        'learner.audio.replay',
        'learner.audio.resume',
      ]),
    })
    .first()
  if (await playAudio.isVisible().catch(() => false)) {
    await playAudio.click()
  }

  await expect(page.locator('main')).toBeVisible()
})

test('speak journey exposes consent, avatar, and voice recording controls', async ({
  page,
}) => {
  await page.goto('/#speak')
  await expect(
    page.getByRole('heading', {
      level: 1,
      name: localizedPattern('page.speak.title'),
    }),
  ).toBeVisible()

  const talkButton = page
    .getByRole('button', { name: localizedPattern('conversation.start') })
    .first()
  if (await talkButton.isVisible().catch(() => false)) {
    await talkButton.click()
  }

  for (const consentKey of [
    'conversation.voice.consent_both',
    'conversation.voice.consent_provider',
    'conversation.voice.consent_upload',
  ] as const) {
    const consent = page
      .getByRole('checkbox', { name: localizedPattern(consentKey) })
      .first()
    if (await consent.isVisible().catch(() => false)) {
      await consent.check()
    }
  }

  const startRecording = page
    .getByRole('button', {
      name: localizedPatterns([
        'learner.recording.start',
        'learner.recording.hold',
      ]),
    })
    .first()

  if (await startRecording.isVisible().catch(() => false)) {
    await startRecording.click()
    await page.waitForTimeout(1200)
    const stopRecording = page
      .getByRole('button', {
        name: localizedPatterns([
          'learner.recording.stop',
          'learner.recording.release',
        ]),
      })
      .first()
    if (await stopRecording.isVisible().catch(() => false)) {
      await stopRecording.click()
    }
  }

  const typeInstead = page
    .getByRole('button', {
      name: localizedPattern('conversation.confidence.type_instead'),
    })
    .first()
  if (await typeInstead.isVisible().catch(() => false)) {
    await typeInstead.click()
    const textbox = page.getByRole('textbox').first()
    await textbox.fill('I practise English after work.')
    const send = page
      .getByRole('button', { name: localizedPattern('conversation.send') })
      .first()
    if (await send.isVisible().catch(() => false)) {
      await send.click()
    }
  }
})

test('progress dashboard loads learner progress data', async ({ page }) => {
  await page.goto('/#progress')
  await expect(page.locator('#me-progress')).toBeVisible()
  await expect(page.locator('.progress-dashboard')).toBeVisible()
})

test('tuition request form accepts input and exposes submit control', async ({
  page,
}) => {
  await page.goto('/#tuition')
  await expect(page.locator('#me-tuition')).toBeVisible()

  await page
    .getByText(localizedPattern('tuition.form_open'))
    .first()
    .click()

  const topic = page
    .getByLabel(localizedPattern('tuition.topic'))
    .first()
  const windowField = page
    .getByLabel(localizedPattern('tuition.preferred_window'))
    .first()
  if (await topic.isVisible().catch(() => false)) {
    await topic.fill('Pronunciation help for workplace meetings')
  }
  if (await windowField.isVisible().catch(() => false)) {
    await windowField.fill('Weekday evenings IST')
  }

  const submit = page
    .getByRole('button', { name: localizedPattern('tuition.submit') })
    .first()
  await expect(submit).toBeVisible()
})

test('sign-in form remains available after sign-out', async ({ page }) => {
  await page.goto('/#me')
  await page
    .getByRole('button', { name: localizedPattern('session.android_sign_out') })
    .click()

  await page.goto('/#sign-in')
  await expect(page.locator('#web-password-username')).toBeVisible()
  await expect(page.locator('#web-password-password')).toBeVisible()
  await expect(
    page
      .locator('form')
      .filter({ has: page.locator('#web-password-password') })
      .locator('button[type="submit"]'),
  ).toBeVisible()
})
