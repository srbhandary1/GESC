import {
  DEFAULT_LOCALE,
  resolveMessage,
  type MessageKey,
} from '@global-english/localization'
import AxeBuilder from '@axe-core/playwright'
import { expect, test, type Page, type Route } from '@playwright/test'
import { createHash } from 'node:crypto'

const ids = {
  asset: '01J10000000000000000000014',
  attempt: '01J10000000000000000000001',
  blueprint: '01TESTBLUEPRINT000000000001',
  bundle: '01J10000000000000000000015',
  enrollment: '01J10000000000000000000004',
  levelBundle: '01J00000000000000000000070',
  membership: '01J10000000000000000000007',
  mission: '01J10000000000000000000008',
  objective: '01J10000000000000000000009',
  organization: '01J10000000000000000000010',
  publication: '01J10000000000000000000011',
  unlock: '01J00000000000000000000052',
  user: '01J10000000000000000000012',
} as const

const blueprintContentHash = 'a'.repeat(64)
const checkpointRuleHash = 'b'.repeat(64)

test('opens contextual plans from a locked level and returns without losing level context', async ({
  page,
}) => {
  await mockLevelPathLearnerApi(page, true)
  await page.goto('/#learn')

  const upgrade = page.getByRole('button', {
    name: message('subscription.view_plans_for_level', { level: '3' }),
  })
  await expect(upgrade).toBeVisible()
  await upgrade.click()

  const panel = page.locator('#subscription-panel')
  await expect(panel.getByRole('heading', {
    name: message('subscription.current_title'),
  })).toBeVisible()
  await expect(panel.getByText(message('subscription.context_locked', {
    completed: '',
    locked: '3',
  }))).toBeVisible()
  await expect(panel.locator('.subscription-panel__plan')).toHaveCount(2)
  await expect(panel.getByText(message('subscription.premium'))).toBeVisible()
  await expect(panel.getByRole('heading', {
    name: message('subscription.talk_packs'),
  })).toHaveCount(0)

  const priceDetails = panel.locator('.subscription-panel__tax').first()
  await priceDetails.locator('summary').click()
  await expect(priceDetails.getByText('₹300.00', { exact: true })).toBeVisible()
  await expect(priceDetails.getByText('₹54.00', { exact: true })).toBeVisible()
  await expect(priceDetails.getByText('₹354.00', { exact: true })).toBeVisible()
  await page.setViewportSize({ height: 800, width: 320 })
  await expect.poll(() => panel.evaluate((element) =>
    element.scrollWidth <= element.clientWidth,
  )).toBe(true)
  const accessibility = await new AxeBuilder({ page })
    .include('#subscription-panel')
    .analyze()
  expect(accessibility.violations).toEqual([])

  await panel.getByRole('button', {
    name: message('subscription.close'),
  }).click()
  await expect(panel).toHaveCount(0)
  await expect(upgrade).toBeFocused()
  await expect(page).toHaveURL(/#learn$/u)

  await upgrade.click()
  await panel.getByRole('link', {
    name: message('subscription.continue_free'),
  }).click()
  await expect(panel).toHaveCount(0)
  await expect(page.getByRole('heading', {
    name: message('level.path.title'),
  })).toBeVisible()
  await expect(upgrade).toBeFocused()
  await expect(page).toHaveURL(/#learn$/u)

  await upgrade.click()
  await expect(panel).toBeVisible()
  await page.evaluate(() => {
    window.dispatchEvent(new CustomEvent('ge:android-back-request', { cancelable: true }))
  })
  await expect(panel).toHaveCount(0)
  await expect(upgrade).toBeFocused()
  await expect(page).toHaveURL(/#learn$/u)
})

test('queues a level checkpoint offline and syncs through the signed submission route on reconnect', async ({
  context,
  page,
}) => {
  const api = await mockLevelPathLearnerApi(page, true)

  await page.goto('/#home')
  await page
    .getByRole('checkbox', {
      name: message('learner.offline.personal_confirmation'),
    })
    .check()
  await page
    .getByRole('button', {
      name: message('learner.offline.download'),
    })
    .click()
  await expect(
    page.getByText(message('learner.offline.available')),
  ).toBeVisible()

  await page.goto('/#learn')
  await expect(
    page.getByRole('heading', {
      name: message('level.path.title'),
    }),
  ).toBeVisible()

  await page
    .getByRole('button', {
      name: message('level.path.checkpoint_open', { number: '1' }),
    })
    .click()

  await expect(
    page.getByRole('heading', {
      name: message('level.path.checkpoint_heading'),
    }),
  ).toBeVisible()

  await expect
    .poll(() => api.offlineLevelBundleRequests)
    .toBe(1)

  await expect(
    page.getByText(
      message('level.path.question_progress', { current: '1', total: '2' }),
      { exact: true },
    ),
  ).toBeVisible()
  await expect(
    page.getByText('Which sentence asks permission correctly?'),
  ).toHaveCount(0)
  const accessibility = await new AxeBuilder({ page })
    .include('.level-session__checkpoint')
    .analyze()
  expect(accessibility.violations).toEqual([])
  await page.emulateMedia({ reducedMotion: 'reduce' })
  await expect
    .poll(() =>
      page
        .locator('.level-session__checkpoint')
        .evaluate((element) => getComputedStyle(element).animationName),
    )
    .toBe('none')
  await page.emulateMedia({ reducedMotion: 'no-preference' })
  await page.setViewportSize({ height: 800, width: 320 })
  await expect
    .poll(() =>
      page.evaluate(
        () => document.documentElement.scrollWidth <= globalThis.innerWidth,
      ),
    )
    .toBe(true)

  const firstAnswer = page.getByRole('radio', { name: 'I can swim.' })
  const answerCard = firstAnswer.locator('xpath=ancestor::label')
  expect((await answerCard.boundingBox())?.height ?? 0).toBeGreaterThanOrEqual(48)
  await firstAnswer.check()
  await expect(answerCard).toContainText(message('level.path.answer_selected'))
  await expect
    .poll(async () =>
      page.evaluate(async () => {
        const database = await new Promise<IDBDatabase>((resolve, reject) => {
          const request = indexedDB.open('global-english-offline-v1')
          request.onsuccess = () => resolve(request.result)
          request.onerror = () => reject(request.error)
        })
        try {
          const transaction = database.transaction('records', 'readonly')
          const records = await new Promise<Array<{ type?: string }>>(
            (resolve, reject) => {
              const request = transaction.objectStore('records').getAll()
              request.onsuccess = () =>
                resolve(request.result as Array<{ type?: string }>)
              request.onerror = () => reject(request.error)
            },
          )
          return records.filter(({ type }) => type === 'level-quiz-draft').length
        } finally {
          database.close()
        }
      }),
    )
    .toBe(1)
  const encryptedDraftMetadata = await page.evaluate(async () => {
    const database = await new Promise<IDBDatabase>((resolve, reject) => {
      const request = indexedDB.open('global-english-offline-v1')
      request.onsuccess = () => resolve(request.result)
      request.onerror = () => reject(request.error)
    })
    try {
      const transaction = database.transaction('records', 'readonly')
      const records = await new Promise<Array<Record<string, unknown>>>(
        (resolve, reject) => {
          const request = transaction.objectStore('records').getAll()
          request.onsuccess = () =>
            resolve(request.result as Array<Record<string, unknown>>)
          request.onerror = () => reject(request.error)
        },
      )
      return JSON.stringify(
        records.filter(({ type }) => type === 'level-quiz-draft'),
      )
    } finally {
      database.close()
    }
  })
  expect(encryptedDraftMetadata).not.toContain(ids.organization)
  expect(encryptedDraftMetadata).not.toContain(ids.user)
  expect(encryptedDraftMetadata).not.toContain('I can swim.')

  await page.reload()
  await expect(page).toHaveURL(
    /#learn\/levels\/1\/introduction\/checkpoint\/question\/0\/2$/u,
  )
  await expect(
    page.getByRole('heading', {
      name: message('level.path.checkpoint_heading'),
    }),
  ).toBeVisible()
  await expect(
    page.getByText(
      message('level.path.question_progress', { current: '1', total: '2' }),
      { exact: true },
    ),
  ).toBeVisible()
  await expect(page.getByRole('radio', { name: 'I can swim.' })).toBeChecked()
  await page
    .getByRole('button', { name: message('level.path.continue_question') })
    .click()
  await expect(
    page.getByText(
      message('level.path.question_progress', { current: '2', total: '2' }),
      { exact: true },
    ),
  ).toBeVisible()
  await expect(
    page.getByText('Which sentence uses can correctly?'),
  ).toHaveCount(0)
  await page.getByRole('radio', { name: 'Can I sit here?' }).check()

  await context.setOffline(true)
  await page
    .getByRole('button', {
      name: message('level.path.checkpoint_submit'),
    })
    .click()

  await expect(
    page.getByRole('heading', {
      name: message('learner.offline.saved'),
    }),
  ).toBeVisible()
  await expect(
    page.getByText(message('learner.offline.pending_authority')),
  ).toBeVisible()
  await expect
    .poll(async () =>
      page.evaluate(async () => {
        const database = await new Promise<IDBDatabase>((resolve, reject) => {
          const request = indexedDB.open('global-english-offline-v1')
          request.onsuccess = () => resolve(request.result)
          request.onerror = () => reject(request.error)
        })
        try {
          const transaction = database.transaction('records', 'readonly')
          const records = await new Promise<Array<{ type?: string }>>(
            (resolve, reject) => {
              const request = transaction.objectStore('records').getAll()
              request.onsuccess = () =>
                resolve(request.result as Array<{ type?: string }>)
              request.onerror = () => reject(request.error)
            },
          )
          return records.filter(({ type }) => type === 'level-quiz-draft').length
        } finally {
          database.close()
        }
      }),
    )
    .toBe(0)
  expect(api.offlineCheckpointBodies).toHaveLength(0)
  expect(api.onlineCheckpointBodies).toHaveLength(0)

  await context.setOffline(false)

  await expect(
    page.getByText(
      message('level.path.checkpoint_unlocked', { number: '2' }),
    ),
  ).toBeVisible()
  await expect(
    page.getByText(message('level.path.answer_correct')),
  ).toBeVisible()
  await expect(
    page.getByText(
      message('level.path.answer_rationale', {
        rationale: 'Use can + base verb.',
      }),
    ),
  ).toBeVisible()
  expect(api.offlineCheckpointBodies).toHaveLength(1)
  expect(api.offlineCheckpointBodies[0]).toMatchObject({
    answers: { 'can-cp-01': 0, 'can-cp-02': 0 },
    bundle_id: ids.levelBundle,
    lease: 'signed-level-offline-lease',
    level_number: 1,
  })
  expect(api.onlineCheckpointBodies).toHaveLength(0)
  expect(JSON.stringify(api.offlineCheckpointBodies[0])).not.toContain('audio')
})

test('keeps Android-style Back, focus, targets, IME inset and Variant C contrast aligned', async ({
  page,
}) => {
  await mockLevelPathLearnerApi(page, true)
  await page.setViewportSize({ height: 800, width: 320 })
  await page.emulateMedia({ colorScheme: 'dark' })
  await page.goto('/#learn')

  const levelControl = page.getByRole('button', {
    name: message('level.path.checkpoint_open', { number: '1' }),
  })
  await levelControl.click()
  const firstAnswer = page.getByRole('radio', { name: 'I can swim.' })
  await firstAnswer.check()
  await expect(page).toHaveURL(/#learn\/levels\/1\/introduction\/checkpoint\/question\/0\/2$/u)
  await page.getByRole('button', {
    name: message('level.path.continue_question'),
  }).click()
  await expect(page).toHaveURL(/\/question\/1\/2$/u)

  await page.goBack()
  await expect(page).toHaveURL(/\/question\/0\/2$/u)
  await expect(page.getByText(message('level.path.question_progress', {
    current: '1',
    total: '2',
  }), { exact: true })).toBeVisible()
  await expect(page.locator('#level-1-checkpoint-question-0-continue')).toBeFocused()

  await page.goBack()
  await expect(page.getByRole('heading', {
    name: message('level.path.checkpoint_heading'),
  })).toBeVisible()
  await page.goBack()
  await expect(page.getByRole('heading', {
    name: message('level.path.title'),
  })).toBeVisible()
  await expect(levelControl).toBeFocused()

  const navigationTargets = await page.locator('.ge-primary-navigation__link').evaluateAll(
    (links) => links.map((link) => {
      const rectangle = link.getBoundingClientRect()
      const style = getComputedStyle(link)
      return {
        fontSize: Number.parseFloat(style.fontSize),
        height: rectangle.height,
        minHeight: style.minHeight,
        transform: style.transform,
        width: rectangle.width,
      }
    }),
  )
  expect(navigationTargets).toHaveLength(5)
  for (const target of navigationTargets) {
    expect(target.height, JSON.stringify(target)).toBeGreaterThanOrEqual(48)
    expect(target.width).toBeGreaterThanOrEqual(48)
    expect(target.fontSize).toBeGreaterThanOrEqual(12)
  }
  const activeNavigation = page.locator('.ge-primary-navigation__link[aria-current=page]')
  await expect.poll(() => activeNavigation.evaluate((element) => {
    const style = getComputedStyle(element)
    return style.backgroundColor !== 'rgba(0, 0, 0, 0)' && style.boxShadow !== 'none'
  })).toBe(true)

  await levelControl.click()
  await firstAnswer.check()
  await page.evaluate(() => {
    document.documentElement.style.setProperty('--ge-native-ime-inset-bottom', '220px')
  })
  const stickyAction = page.locator('.level-session__assessment-actions')
  await stickyAction.scrollIntoViewIfNeeded()
  await expect.poll(async () => {
    const box = await stickyAction.boundingBox()
    return box === null ? 801 : box.y + box.height
  }).toBeLessThanOrEqual(580)

  const variantColors = await page.locator('.ge-application-frame--studio-learner').evaluate(
    (element) => {
      const style = getComputedStyle(element)
      return { background: style.backgroundColor }
    },
  )
  expect(variantColors.background).toBe('rgb(250, 251, 250)')
  await expect(activeNavigation).toHaveCSS('color', 'rgb(0, 107, 63)')
  const accessibility = await new AxeBuilder({ page })
    .include('.level-session__assessment')
    .analyze()
  expect(accessibility.violations).toEqual([])

  await page.emulateMedia({ colorScheme: 'dark', forcedColors: 'active' })
  await expect.poll(() => activeNavigation.evaluate((element) => (
    getComputedStyle(element).borderBlockStartWidth
  ))).toBe('3px')
})

test('reflows authenticated Android chrome and quiz actions at 200% text', async ({
  page,
}) => {
  await mockLevelPathLearnerApi(page, true, 4920)
  await page.setViewportSize({ height: 900, width: 320 })
  await page.goto('/#learn')
  await page.locator('.ge-application-frame').evaluate((frame) => {
    frame.classList.add('ge-application-frame--native-android')
  })
  await page.evaluate(() => {
    document.documentElement.style.fontSize = '200%'
  })

  for (const locale of ['en-IN', 'kn-IN']) {
    await page.locator('#interface-locale').selectOption(locale)

    for (const width of [320, 360, 412]) {
      await page.setViewportSize({ height: 900, width })
      const overflowEvidence = await page.evaluate(() => ({
        documentWidth: document.documentElement.scrollWidth,
        viewportWidth: globalThis.innerWidth,
      }))
      expect(overflowEvidence, JSON.stringify(overflowEvidence)).toMatchObject({
        documentWidth: width,
        viewportWidth: width,
      })

      const chromeMetrics = await page.evaluate(() => {
        const metrics = (selector: string) => [
          ...document.querySelectorAll<HTMLElement>(selector),
        ]
          .filter((element) => element.getClientRects().length > 0)
          .map((element) => ({
            clientHeight: element.clientHeight,
            clientWidth: element.clientWidth,
            height: element.getBoundingClientRect().height,
            scrollHeight: element.scrollHeight,
            scrollWidth: element.scrollWidth,
            width: element.getBoundingClientRect().width,
          }))

        return {
          headerControls: metrics('.shell-header-toolbar > *'),
          headerLabels: [
            document.querySelector<HTMLElement>('.shell-account-dropdown__trigger')?.innerText.trim(),
            document.querySelector<HTMLElement>('.shell-theme-control .shell-toolbar-caption')?.innerText.trim(),
            document.querySelector<HTMLSelectElement>('#interface-theme')?.selectedOptions[0]?.text.trim(),
            document.querySelector<HTMLElement>('.shell-locale-control .shell-toolbar-caption')?.innerText.trim(),
            document.querySelector<HTMLSelectElement>('#interface-locale')?.selectedOptions[0]?.text.trim(),
            document.querySelector<HTMLElement>('.shell-live-ai-chip__compact')?.innerText.trim(),
          ],
          navigation: metrics('.ge-primary-navigation__link'),
        }
      })

      expect(chromeMetrics.headerLabels).toHaveLength(6)
      expect(chromeMetrics.headerLabels.every((label) => Boolean(label))).toBe(true)
      expect(chromeMetrics.navigation).toHaveLength(5)
      for (const target of chromeMetrics.navigation) {
        expect(target.height, JSON.stringify(target)).toBeGreaterThanOrEqual(48)
        expect(target.width, JSON.stringify(target)).toBeGreaterThanOrEqual(48)
        expect(target.scrollHeight, JSON.stringify(target)).toBeLessThanOrEqual(
          target.clientHeight + 1,
        )
        expect(target.scrollWidth, JSON.stringify(target)).toBeLessThanOrEqual(
          target.clientWidth + 1,
        )
      }
      for (const control of chromeMetrics.headerControls) {
        expect(control.height, JSON.stringify(control)).toBeGreaterThanOrEqual(48)
        expect(control.scrollHeight, JSON.stringify(control)).toBeLessThanOrEqual(
          control.clientHeight + 1,
        )
        expect(control.scrollWidth, JSON.stringify(control)).toBeLessThanOrEqual(
          control.clientWidth + 1,
        )
      }
    }
  }

  await page.setViewportSize({ height: 412, width: 800 })
  await expect.poll(() => page.evaluate(() => (
    document.documentElement.scrollWidth <= globalThis.innerWidth
  ))).toBe(true)
  const landscapeMetrics = await page.evaluate(() => {
    const nav = document.querySelector<HTMLElement>('.ge-primary-navigation')
    const header = document.querySelector<HTMLElement>('.ge-application-frame__header')
    const main = document.querySelector<HTMLElement>('.ge-application-frame__main')
    const brand = document.querySelector<HTMLElement>('.shell-studio-brand__name')
    const labels = [...document.querySelectorAll<HTMLElement>('.ge-primary-navigation__label')]

    return {
      brandHeight: brand?.getBoundingClientRect().height ?? 0,
      headerHeight: header?.getBoundingClientRect().height ?? 0,
      labelLastLineWidths: labels.map((label) => {
        const range = document.createRange()
        range.selectNodeContents(label)
        const lastRect = [...range.getClientRects()].at(-1)
        return lastRect?.width ?? 0
      }),
      mainHeight: main?.getBoundingClientRect().height ?? 0,
      navHeight: nav?.getBoundingClientRect().height ?? 0,
      viewportHeight: globalThis.innerHeight,
    }
  })
  expect(landscapeMetrics.navHeight).toBeLessThan(landscapeMetrics.viewportHeight * 0.4)
  expect(landscapeMetrics.mainHeight).toBeGreaterThan(96)
  expect(landscapeMetrics.brandHeight).toBeLessThan(48)
  expect(landscapeMetrics.labelLastLineWidths.every((width) => width > 8)).toBe(true)

  await page.setViewportSize({ height: 320, width: 711 })
  await expect.poll(() => page.evaluate(() => (
    document.documentElement.scrollWidth <= globalThis.innerWidth
  ))).toBe(true)
  const shortLandscape = await page.evaluate(() => {
    const nav = document.querySelector<HTMLElement>('.ge-primary-navigation')
    const main = document.querySelector<HTMLElement>('.ge-application-frame__main')
    return {
      mainHeight: main?.getBoundingClientRect().height ?? 0,
      navHeight: nav?.getBoundingClientRect().height ?? 0,
      viewportHeight: globalThis.innerHeight,
    }
  })
  expect(shortLandscape.navHeight).toBeLessThan(shortLandscape.viewportHeight * 0.4)
  expect(shortLandscape.mainHeight).toBeGreaterThan(80)

  await page.setViewportSize({ height: 412, width: 800 })

  const levelControl = page.getByRole('button', {
    name: message('level.path.checkpoint_open', { number: '1' }),
  })
  await levelControl.click()
  await page.getByRole('radio', { name: 'I can swim.' }).check()

  const stickyAction = page.locator('.level-session__assessment-actions')
  const navigation = page.locator('.ge-primary-navigation')
  await stickyAction.scrollIntoViewIfNeeded()
  const closedKeyboardGeometry = await page.evaluate(() => {
    const action = document.querySelector<HTMLElement>('.level-session__assessment-actions')
    const nav = document.querySelector<HTMLElement>('.ge-primary-navigation')
    const frame = document.querySelector<HTMLElement>('.ge-application-frame')
    const actionBox = action?.getBoundingClientRect()
    const navigationBox = nav?.getBoundingClientRect()

    return {
      actionBottom: actionBox === undefined ? null : actionBox.bottom,
      actionBottomStyle: action === null ? null : getComputedStyle(action).bottom,
      navigationOffset: frame === null
        ? null
        : getComputedStyle(frame).getPropertyValue('--ge-mobile-navigation-offset'),
      navigationTop: navigationBox === undefined ? null : navigationBox.top,
    }
  })
  expect(
    closedKeyboardGeometry.actionBottom,
    JSON.stringify(closedKeyboardGeometry),
  ).not.toBeNull()
  expect(
    closedKeyboardGeometry.navigationTop,
    JSON.stringify(closedKeyboardGeometry),
  ).not.toBeNull()
  expect(
    closedKeyboardGeometry.actionBottom!,
    JSON.stringify(closedKeyboardGeometry),
  ).toBeLessThanOrEqual(closedKeyboardGeometry.navigationTop! + 1)
  await expect.poll(async () => {
    const [actionBox, navigationBox] = await Promise.all([
      stickyAction.boundingBox(),
      navigation.boundingBox(),
    ])
    if (actionBox === null || navigationBox === null) {
      return false
    }

    return actionBox.y + actionBox.height <= navigationBox.y + 1
  }).toBe(true)

  await page.evaluate(() => {
    document.documentElement.style.setProperty('--ge-native-ime-inset-bottom', '220px')
  })
  await stickyAction.scrollIntoViewIfNeeded()
  const openKeyboardGeometry = await stickyAction.evaluate((element) => {
    const box = element.getBoundingClientRect()
    return {
      bottom: box.bottom,
      bottomStyle: getComputedStyle(element).bottom,
      height: box.height,
      top: box.top,
    }
  })
  expect(
    openKeyboardGeometry.bottom,
    JSON.stringify(openKeyboardGeometry),
  ).toBeLessThanOrEqual(192)

  await page.emulateMedia({ forcedColors: 'active', reducedMotion: 'reduce' })
  const contrastMetrics = await page.locator('.ge-primary-navigation__link').evaluateAll(
    (links) => links.map((link) => ({
      color: getComputedStyle(link).color,
      opacity: getComputedStyle(link).opacity,
    })),
  )
  expect(contrastMetrics.every(({ opacity }) => opacity === '1')).toBe(true)
  expect(new Set(contrastMetrics.map(({ color }) => color))).not.toContain('rgba(0, 0, 0, 0)')
})

interface MockLevelPathApiState {
  offlineCheckpointBodies: unknown[]
  offlineLevelBundleRequests: number
  onlineCheckpointBodies: unknown[]
}

async function mockLevelPathLearnerApi(
  page: Page,
  initiallyOnboarded: boolean,
  liveAiSecondsRemaining: number | null = null,
): Promise<MockLevelPathApiState> {
  let onboarded = initiallyOnboarded
  const state: MockLevelPathApiState = {
    offlineCheckpointBodies: [],
    offlineLevelBundleRequests: 0,
    onlineCheckpointBodies: [],
  }

  await page.context().addCookies([
    {
      name: 'XSRF-TOKEN',
      url: 'http://127.0.0.1:4173',
      value: 'e2e-csrf-token',
    },
  ])

  await page.route('**/sanctum/csrf-cookie', async (route) => {
    await route.fulfill({ status: 204 })
  })

  await page.route('**/api/v1/**', async (route) => {
    const request = route.request()
    const url = new URL(request.url())
    const path = url.pathname
    const postData = request.postData()

    if (await fulfillPasswordSecurity(route, path)) {
      return
    }

    if (path === '/api/v1/me') {
      await json(route, {
        current_membership: {
          id: ids.membership,
          organization: {
            id: ids.organization,
            kind: 'platform_direct',
            name: 'Global English Direct',
          },
          membership_type: 'learner',
          role_keys: ['institution-learner'],
          status: 'active',
        },
        memberships: [],
        user: { id: ids.user },
      })
      return
    }

    if (path === '/api/v1/home') {
      await json(route, home(onboarded))
      return
    }

    if (path === '/api/v1/me/entitlement') {
      await json(route, {
        allowed_session_parts: ['introduction', 'practice'],
        allows_free_speech: false,
        allows_roleplay: false,
        applies: true,
        entitlement_id: null,
        expires_at: null,
        gstin_is_placeholder: true,
        is_demo: false,
        live_ai_cap_seconds: null,
        live_ai_seconds_remaining: liveAiSecondsRemaining,
        max_level_number: 2,
        plan_code: null,
        plan_name: null,
        status: 'expired',
      })
      return
    }

    if (path === '/api/v1/me/subscription-plans') {
      await json(route, { gstin_is_placeholder: true, plans: subscriptionPlans() })
      return
    }

    if (path === '/api/v1/me/onboarding') {
      onboarded = true
      await json(route, home(true))
      return
    }

    if (
      path === `/api/v1/missions/${ids.mission}/offline-bundle` &&
      request.method() === 'GET'
    ) {
      await json(route, missionOfflineBundle())
      return
    }

    if (
      path === '/api/v1/offline-completions' &&
      request.method() === 'POST'
    ) {
      await json(route, missionResult(false))
      return
    }

    if (path === `/api/v1/media-assets/${ids.asset}/content`) {
      await route.fulfill({
        body: waveFile(),
        contentType: 'audio/wav',
        headers: {
          'Cache-Control': 'private, no-store',
        },
        status: 200,
      })
      return
    }

    if (path === '/api/v1/me/levels' && request.method() === 'GET') {
      await json(route, { levels: levelSummaries() })
      return
    }

    if (
      path === '/api/v1/me/levels/1/sessions' &&
      request.method() === 'GET'
    ) {
      await json(route, completedSessionsList())
      return
    }

    if (
      path.startsWith('/api/v1/me/levels/1/sessions/') &&
      request.method() === 'GET' &&
      !path.endsWith('/audio')
    ) {
      await json(route, sessionDetail(path))
      return
    }

    if (
      path === '/api/v1/me/levels/1/checkpoint' &&
      request.method() === 'GET'
    ) {
      await json(route, checkpointDetail())
      return
    }

    if (
      path === '/api/v1/me/levels/1/offline-bundle' &&
      request.method() === 'GET'
    ) {
      state.offlineLevelBundleRequests += 1
      await json(route, levelOfflineBundle())
      return
    }

    if (
      path === '/api/v1/offline-level-checkpoint-submissions' &&
      request.method() === 'POST'
    ) {
      state.offlineCheckpointBodies.push(
        JSON.parse(postData ?? 'null') as unknown,
      )
      await json(route, checkpointSubmissionResult())
      return
    }

    if (
      path === '/api/v1/me/levels/1/checkpoint-submissions' &&
      request.method() === 'POST'
    ) {
      state.onlineCheckpointBodies.push(
        JSON.parse(postData ?? 'null') as unknown,
      )
      await json(route, checkpointSubmissionResult())
      return
    }

    if (path === '/api/v1/progress') {
      await json(route, {
        enrollment_id: ids.enrollment,
        objectives: [],
      })
      return
    }

    await route.abort('failed')
  })

  return state
}

async function fulfillPasswordSecurity(
  route: Route,
  path: string,
): Promise<boolean> {
  if (path === '/api/v1/auth/password/status') {
    await json(route, {
      credential_ready: true,
      mode: 'password_only',
      password_confirmed_at: null,
      username: 'learner.one',
    })
    return true
  }
  if (path === '/api/v1/auth/sessions') {
    await json(route, { data: [] })
    return true
  }
  return false
}

function home(onboarded: boolean) {
  return {
    due_review: null,
    upcoming_reviews: [],
    enrollment: onboarded
      ? { id: ids.enrollment, status: 'active' }
      : null,
    next_mission: onboarded
      ? {
          estimated_minutes: 5,
          id: ids.mission,
          title: content('en-IN', 'Introduce yourself'),
        }
      : null,
    onboarding: {
      adult_confirmed: onboarded,
      completed: onboarded,
      goal_key: onboarded ? 'workplace-confidence' : null,
    },
    streak: {
      as_of_date: '2026-08-30',
      count: 0,
      timezone: 'Asia/Kolkata',
    },
  }
}

function levelSummaries() {
  return [
    {
      availability: 'available',
      blueprint_id: ids.blueprint,
      checkpoint_attempts_remaining: 3,
      checkpoint_status: 'ready',
      concept_key: 'can',
      level_number: 1,
      progress_access: 'allowed',
      session_part_ids: [
        'introduction',
        'practice',
        'conversation',
        'interaction',
      ],
      spoken_evidence: {
        evidence_state: 'not_recorded',
        spoken_progress_claim: false,
        spoken_state: 'unassessed',
      },
    },
    {
      availability: 'available',
      blueprint_id: '01TESTBLUEPRINT000000000002',
      checkpoint_status: 'not_ready',
      concept_key: 'could',
      level_number: 2,
      progress_access: 'locked',
      session_part_ids: [
        'introduction',
        'practice',
        'conversation',
        'interaction',
      ],
      unlock_after_level_number: 1,
    },
    {
      availability: 'available',
      blueprint_id: '01TESTBLUEPRINT000000000003',
      checkpoint_status: 'not_ready',
      concept_key: 'do_does',
      entitlement_access: 'upgrade_required',
      level_number: 3,
      progress_access: 'not_applicable',
      session_part_ids: [
        'introduction',
        'practice',
        'conversation',
        'interaction',
      ],
    },
  ]
}

function subscriptionPlans() {
  return [
    {
      allows_roleplay: false,
      amount_ex_gst_paise: 30000,
      billing_period: 'calendar_month',
      checkout_required: true,
      code: 'monthly_modules',
      gst_paise: 5400,
      id: '01JPLAN00000000000000000001',
      is_demo: false,
      is_talk_pack: false,
      live_ai_cap_seconds: 7200,
      max_level_number: 17,
      name: 'Premium features',
      recommended: false,
      total_paise: 35400,
    },
    {
      allows_roleplay: true,
      amount_ex_gst_paise: 50000,
      billing_period: 'calendar_month',
      checkout_required: true,
      code: 'monthly_modules_roleplay',
      gst_paise: 9000,
      id: '01JPLAN00000000000000000002',
      is_demo: false,
      is_talk_pack: false,
      live_ai_cap_seconds: 7200,
      max_level_number: 17,
      name: 'Premium with Role-play',
      recommended: true,
      total_paise: 59000,
    },
  ]
}

function completedSessionsList() {
  return {
    blueprint_content_hash: blueprintContentHash,
    blueprint_id: ids.blueprint,
    concept_key: 'can',
    level_number: 1,
    sessions: [
      {
        completed: true,
        media_asset_id: '01TESTMEDIA0000000000000001',
        part_id: 'introduction',
        part_order: 1,
      },
      {
        completed: true,
        media_asset_id: '01TESTMEDIA0000000000000002',
        part_id: 'practice',
        part_order: 2,
      },
      {
        completed: true,
        media_asset_id: '01TESTMEDIA0000000000000003',
        part_id: 'conversation',
        part_order: 3,
      },
      {
        completed: true,
        media_asset_id: '01TESTMEDIA0000000000000004',
        part_id: 'interaction',
        part_order: 4,
      },
    ],
  }
}

function sessionDetail(path: string) {
  const partId = path.split('/').at(-1) ?? 'introduction'

  return {
    activities: [],
    apply: null,
    audio_stream_path: `/api/v1/me/levels/1/sessions/${partId}/audio`,
    check: null,
    completed: true,
    conversation_binding: {
      coaching_mode: 'tutor',
      prototype: 'skill_focus',
    },
    kannada_guidance: { summary: 'ಕನ್ನಡ ವಿವರಣೆ' },
    level_number: 1,
    media_asset_id: '01TESTMEDIA0000000000000001',
    objective: 'Use can and cannot.',
    part_id: partId,
    part_order: 1,
    talk: {
      elicitation_goals: [],
      fallback_turns: [],
      free_speech: true,
      scenario: 'Ask for permission.',
    },
    target_patterns: ['I can + base verb.'],
  }
}

function checkpointDetail() {
  return {
    attempts_remaining: 3,
    blueprint_content_hash: blueprintContentHash,
    blueprint_id: ids.blueprint,
    concept_key: 'can',
    items: [
      {
        id: 'can-cp-01',
        options: ['I can swim.', 'I cans swim.', 'Can I swam?'],
        prompt: 'Which sentence uses can correctly?',
        weight: 1,
      },
      {
        id: 'can-cp-02',
        options: ['Can I sit here?', 'I can to sit here?', 'Can I sat here?'],
        prompt: 'Which sentence asks permission correctly?',
        weight: 1,
      },
    ],
    level_number: 1,
    max_attempts: 3,
    pass_threshold: 0.8,
    rule_hash: checkpointRuleHash,
    rule_key: 'level_checkpoint_v1',
  }
}

function levelOfflineBundle() {
  return {
    lease: 'signed-level-offline-lease',
    manifest: {
      binding: {
        membership_id: ids.membership,
        organization_id: ids.organization,
        user_id: ids.user,
      },
      blueprint_content_hash: blueprintContentHash,
      blueprint_id: ids.blueprint,
      bundle_hash: 'c'.repeat(64),
      bundle_id: ids.levelBundle,
      checkpoint: checkpointDetail(),
      expires_at: '2099-08-01T10:00:00Z',
      issued_at: '2026-09-13T00:00:00Z',
      level: {
        blueprint_content_hash: blueprintContentHash,
        blueprint_id: ids.blueprint,
        checkpoint_rule_hash: checkpointRuleHash,
        checkpoint_rule_key: 'level_checkpoint_v1',
        concept_key: 'can',
        level_number: 1,
      },
      schema: 'global-english.offline-level-bundle.v1',
    },
  }
}

function checkpointSubmissionResult() {
  return {
    attempt_id: ids.attempt,
    attempt_number: 1,
    attempts_remaining: 2,
    item_results: [
      {
        correct: true,
        id: 'can-cp-01',
        rationale: 'Use can + base verb.',
        selected_option: 0,
      },
      {
        correct: true,
        id: 'can-cp-02',
        rationale: 'Use can + subject + base verb for permission.',
        selected_option: 0,
      },
    ],
    level_unlock: {
      level_number: 2,
      replayed: false,
      unlock_decision_id: ids.unlock,
    },
    pass_threshold: 0.8,
    passed: true,
    replayed: false,
    score: 1,
    spoken_evidence: {
      evidence_state: 'not_recorded',
      spoken_progress_claim: false,
      spoken_state: 'unassessed',
    },
  }
}

function missionOfflineBundle() {
  const ordinaryMission = mission()
  const audio = waveFile()

  return {
    lease: 'signed-purpose-specific-offline-lease',
    manifest: {
      binding: {
        enrollment_id: ids.enrollment,
        membership_id: ids.membership,
        organization_id: ids.organization,
        user_id: ids.user,
      },
      bundle_hash: 'b'.repeat(64),
      bundle_id: ids.bundle,
      expires_at: '2099-08-01T10:00:00Z',
      issued_at: '2026-07-30T10:00:00Z',
      mission: {
        ...ordinaryMission,
        model_audio: {
          ...ordinaryMission.model_audio,
          byte_size: audio.byteLength,
          content_hash: createHash('sha256').update(audio).digest('hex'),
        },
        offline_check: {
          correct_option_id: 'correct-option',
        },
      },
      policy_version: 'daily-speaking-v1',
      publication: {
        curriculum_version_id: '01J10000000000000000000016',
        manifest_hash: 'c'.repeat(64),
        mission_definition_hash: 'd'.repeat(64),
        owner_organization_id: ids.organization,
        publication_id: ids.publication,
      },
      schema: 'global-english.offline-mission-bundle.v1',
    },
  }
}

function mission() {
  return {
    check: {
      id: 'check-one',
      options: [
        {
          id: 'correct-option',
          label: content('en-IN', 'Hello, my name is Ravi.'),
        },
        {
          id: 'incorrect-option',
          label: content('en-IN', 'Goodbye yesterday.'),
        },
      ],
      prompt: content('en-IN', 'Choose the correct introduction.'),
    },
    enrollment_id: ids.enrollment,
    id: ids.mission,
    kannada_guidance: content('kn-IN', 'ನಿಮ್ಮ ಹೆಸರನ್ನು ಹೇಳಿ.'),
    model_audio: {
      asset_id: ids.asset,
      duration_ms: 300,
      mime_type: 'audio/wav',
      stream_url: `/api/v1/media-assets/${ids.asset}/content?enrollment_id=${ids.enrollment}&mission_id=${ids.mission}`,
    },
    model_text: content('en-IN', 'Hello, my name is Ravi.'),
    objective_id: ids.objective,
    publication_id: ids.publication,
    title: content('en-IN', 'Introduce yourself'),
    transcript: content('en-IN', 'Hello, my name is Ravi.'),
  }
}

function missionResult(hasRecording: boolean) {
  return {
    attempt_id: ids.attempt,
    can_do: content('en-IN', 'I can introduce myself.'),
    check_state: 'correct',
    evidence: {
      best_attempt_id: '01J10000000000000000000002',
      best_attempt_number: 2,
      first_attempt_id: '01J10000000000000000000005',
      first_attempt_number: 1,
      latest_attempt_id: '01J10000000000000000000006',
      latest_attempt_number: 2,
    },
    knowledge_state: 'mastered',
    local_capture_receipt_present: hasRecording,
    review: {
      due_at: '2026-08-01T09:00:00Z',
      timezone: 'Asia/Kolkata',
    },
    spoken_state: 'unassessed',
  }
}

function content(locale: 'en-IN' | 'kn-IN', text: string) {
  return {
    content_hash: 'a'.repeat(64),
    locale,
    revision_id: '01J10000000000000000000013',
    text,
  }
}

function waveFile(): Buffer {
  const sampleCount = 2400
  const buffer = Buffer.alloc(44 + sampleCount * 2)
  buffer.write('RIFF', 0)
  buffer.writeUInt32LE(buffer.length - 8, 4)
  buffer.write('WAVEfmt ', 8)
  buffer.writeUInt32LE(16, 16)
  buffer.writeUInt16LE(1, 20)
  buffer.writeUInt16LE(1, 22)
  buffer.writeUInt32LE(8000, 24)
  buffer.writeUInt32LE(16000, 28)
  buffer.writeUInt16LE(2, 32)
  buffer.writeUInt16LE(16, 34)
  buffer.write('data', 36)
  buffer.writeUInt32LE(sampleCount * 2, 40)

  return buffer
}

async function json(
  route: Route,
  body: unknown,
  status = 200,
): Promise<void> {
  await route.fulfill({
    contentType: 'application/json',
    json: body,
    status,
  })
}

function message(key: MessageKey, parameters: Record<string, string> = {}): string {
  return resolveMessage(key, {
    parameters,
    policy: 'development-preview',
    requestedLocale: DEFAULT_LOCALE,
  }).text
}
