import AxeBuilder from '@axe-core/playwright'
import {
  DEFAULT_LOCALE,
  resolveMessage,
  type MessageKey,
  type SupportedLocale,
} from '@global-english/localization'
import { expect, test, type Page, type Route } from '@playwright/test'
import { createHash } from 'node:crypto'
import { calculateWebBuildFingerprint } from '../buildFingerprint'

const publicShellCacheName = `global-english-public-shell-${calculateWebBuildFingerprint()}`

const ids = {
  asset: '01J10000000000000000000014',
  attempt: '01J10000000000000000000001',
  best: '01J10000000000000000000002',
  bundle: '01J10000000000000000000015',
  enrollment: '01J10000000000000000000004',
  first: '01J10000000000000000000005',
  latest: '01J10000000000000000000006',
  membership: '01J10000000000000000000007',
  mission: '01J10000000000000000000008',
  objective: '01J10000000000000000000009',
  organization: '01J10000000000000000000010',
  publication: '01J10000000000000000000011',
  user: '01J10000000000000000000012',
} as const

test('keeps the Kannada Android Home compact, coach-led and accessible', async ({
  page,
}) => {
  await page.setViewportSize({ height: 800, width: 360 })
  await page.emulateMedia({ reducedMotion: 'reduce' })
  await mockLearnerApi(page, true)

  await page.goto('/#home')

  const locale = page.locator('#interface-locale')
  await locale.selectOption('en-IN')
  await locale.selectOption(DEFAULT_LOCALE)
  await page.locator('.ge-application-frame').evaluate((frame) => {
    frame.classList.add('ge-application-frame--native-android')
  })

  const home = page.locator('.learner-home')
  const action = home.locator('.learner-home__action')
  const continueButton = action.getByRole('button', {
    name: message('learner.home.continue'),
  })

  await expect(
    home.getByRole('heading', {
      name: message('learner.home.title'),
    }),
  ).toBeVisible()
  await expect(locale).toHaveValue(DEFAULT_LOCALE)
  await expect(page.locator('html')).toHaveAttribute('lang', DEFAULT_LOCALE)
  await expect(action.locator('.tutor-presence__face')).toBeVisible()
  await expect(action.getByText(message('tutor.name'))).toBeVisible()
  await expect(home.locator('.ge-button--primary')).toHaveCount(1)
  await expect(home.getByRole('link', {
    name: message('demo.phase1.home.speak'),
  })).toHaveCount(0)
  await expect(page.locator('.tutor-home-hero')).toHaveCount(0)

  const metrics = await page.evaluate(() => {
    const button = document.querySelector<HTMLElement>(
      '.learner-home__continue',
    )
    const title = document.querySelector<HTMLElement>(
      '.learner-home__action-title',
    )
    const portrait = document.querySelector<HTMLElement>(
      '.learner-home__coach .tutor-presence__face',
    )
    const navigationLinks = [
      ...document.querySelectorAll<HTMLElement>(
        '.ge-primary-navigation__link',
      ),
    ]

    return {
      buttonHeight: button?.getBoundingClientRect().height ?? 0,
      hasHorizontalOverflow:
        document.documentElement.scrollWidth >
        document.documentElement.clientWidth,
      navigationTargetHeights: navigationLinks.map(
        (link) => link.getBoundingClientRect().height,
      ),
      portraitWidth: portrait?.getBoundingClientRect().width ?? 0,
      titleFontSize: Number.parseFloat(
        title === null ? '0' : getComputedStyle(title).fontSize,
      ),
    }
  })

  expect(metrics.hasHorizontalOverflow).toBe(false)
  expect(metrics.buttonHeight).toBeGreaterThanOrEqual(44)
  expect(metrics.buttonHeight).toBeLessThanOrEqual(56)
  expect(metrics.titleFontSize).toBeLessThanOrEqual(28)
  expect(metrics.portraitWidth).toBeGreaterThanOrEqual(56)
  expect(metrics.portraitWidth).toBeLessThanOrEqual(80)
  expect(
    metrics.navigationTargetHeights.every((height) => height >= 44),
  ).toBe(true)
  await expect(continueButton).toBeEnabled()

  const accessibility = await new AxeBuilder({ page }).analyze()
  expect(accessibility.violations).toEqual([])
})

for (const locale of ['en-IN', 'kn-IN'] as const) {
  test(`completes first-use onboarding in ${locale}`, async ({ page }) => {
    const api = await mockLearnerApi(page, false)
    await page.setViewportSize({ height: 700, width: 320 })
    await page.goto('/#home')

    await page.locator('#interface-locale').selectOption(locale)
    await page.locator('.ge-application-frame').evaluate((frame) => {
      frame.classList.add('ge-application-frame--native-android')
    })
    await page.evaluate(() => {
      document.documentElement.style.fontSize = '200%'
      document.documentElement.style.setProperty('--ge-native-safe-area-top', '24px')
      document.documentElement.style.setProperty('--ge-native-safe-area-right', '8px')
      document.documentElement.style.setProperty('--ge-native-safe-area-bottom', '24px')
      document.documentElement.style.setProperty('--ge-native-safe-area-left', '8px')
      document.documentElement.style.setProperty('--ge-native-ime-inset-bottom', '120px')
    })
    await expect(page.locator('html')).toHaveAttribute('lang', locale)
    await expect(page.getByRole('heading', {
      name: localizedMessage('learner.onboarding.welcome_title', locale),
    })).toBeFocused()
    await expect.poll(() => page.evaluate(() => location.hash)).toBe(
      '#home/first-use/welcome',
    )
    await continueFirstUse(page, locale)
    await expect(page.getByRole('heading', {
      name: localizedMessage('learner.onboarding.adult_title', locale),
    })).toBeFocused()
    await expect.poll(() => page.evaluate(() => location.hash)).toBe(
      '#home/first-use/adult',
    )
    await page.goBack()
    await expect(page.getByRole('heading', {
      name: localizedMessage('learner.onboarding.welcome_title', locale),
    })).toBeFocused()
    await expect.poll(() => page.evaluate(() => (
      document.documentElement.scrollWidth <= globalThis.innerWidth
    ))).toBe(true)
    const continueButton = page.getByRole('button', {
      name: localizedMessage('learner.onboarding.continue', locale),
    })
    await expect(continueButton).toBeVisible()
    await continueButton.scrollIntoViewIfNeeded()
    await expect.poll(async () => {
      const box = await continueButton.boundingBox()
      return box === null ? 701 : box.y + box.height
    }).toBeLessThanOrEqual(700)
    await page.evaluate(() => {
      document.documentElement.style.removeProperty('font-size')
      document.documentElement.style.removeProperty('--ge-native-safe-area-top')
      document.documentElement.style.removeProperty('--ge-native-safe-area-right')
      document.documentElement.style.removeProperty('--ge-native-safe-area-bottom')
      document.documentElement.style.removeProperty('--ge-native-safe-area-left')
      document.documentElement.style.removeProperty('--ge-native-ime-inset-bottom')
      document.querySelector<HTMLElement>('.ge-onboarding-screen')?.scrollTo({
        top: 0,
      })
    })

    await advanceFirstUseToGoal(page, locale)
    await continueFirstUse(page, locale)
    if (locale === 'kn-IN') {
      await expect.poll(() => page.evaluate(() => {
        const resumeKey = Object.keys(localStorage).find((key) =>
          key.startsWith('global-english:first-use-onboarding:v2:'),
        )
        return resumeKey === undefined ? null : localStorage.getItem(resumeKey)
      })).toContain('"step":"placement"')
      await page.reload()
      await expect(page.getByRole('heading', {
        name: localizedMessage('learner.onboarding.placement_title', locale),
      })).toBeFocused()
    }
    await finishFirstUseAfterGoal(page, locale)

    await expect(page.getByRole('heading', {
      name: localizedMessage('learner.home.title', locale),
    })).toBeVisible()
    expect(api.commandIds).toHaveLength(1)
    expect(api.commandIds[0]).not.toBeNull()
  })
}

test('recovers onboarding with same-command retry and new-command edit', async ({
  page,
}) => {
  const api = await mockLearnerApi(page, false, {
    onboardingFailureStatuses: [503, 503],
  })
  await page.goto('/#home')

  await advanceFirstUseToGoal(page, DEFAULT_LOCALE)
  await continueFirstUse(page, DEFAULT_LOCALE)

  await expect(page.getByText(
    message('learner.onboarding.error_server'),
  )).toBeVisible()
  await expect(page.getByRole('radio', {
    name: message('learner.onboarding.goal_travel'),
  })).toBeDisabled()
  await page.getByRole('button', {
    name: message('learner.onboarding.retry_same'),
  }).click()
  await expect(page.getByText(
    message('learner.onboarding.error_server'),
  )).toBeVisible()

  expect(api.commandIds[1]).toBe(api.commandIds[0])

  await page.getByRole('button', {
    name: message('learner.onboarding.edit_choices'),
  }).click()
  const travel = page.getByRole('radio', {
    name: message('learner.onboarding.goal_travel'),
  })
  await expect(travel).toBeEnabled()
  await travel.check()
  await page.getByRole('button', {
    name: message('learner.onboarding.continue'),
  }).click()

  await finishFirstUseAfterGoal(page, DEFAULT_LOCALE)
  await expect(page.getByRole('heading', {
    name: message('learner.home.title'),
  })).toBeVisible()
  expect(api.commandIds).toHaveLength(3)
  expect(api.commandIds[2]).not.toBe(api.commandIds[0])
})

test('completes the contract-stubbed online no-AI learner journey', async ({
  page,
}) => {
  const networkUrls: string[] = []
  page.on('request', (request) => networkUrls.push(request.url()))
  await installWorkingMicrophone(page)
  const api = await mockLearnerApi(page, false)

  await page.goto('/#home')

  await expect(
    page.getByRole('heading', {
      name: message('learner.onboarding.welcome_title'),
    }),
  ).toBeFocused()
  await advanceFirstUseToGoal(page, DEFAULT_LOCALE)
  await continueFirstUse(page, DEFAULT_LOCALE)
  await finishFirstUseAfterGoal(page, DEFAULT_LOCALE)

  await page
    .getByRole('button', {
      name: message('learner.home.continue'),
    })
    .click()
  await expect(
    page.getByText('Introduce yourself', { exact: true }).first(),
  ).toHaveAttribute('lang', 'en-IN')

  const modelAudio = page.getByTestId('model-audio-element')
  await expect(modelAudio).not.toHaveAttribute('autoplay', /.*/)
  await expect
    .poll(() => modelAudio.evaluate((audio) => (audio as HTMLAudioElement).paused))
    .toBe(true)
  await page
    .getByRole('button', { name: message('learner.audio.play') })
    .click()

  await expect(
    page.getByText('Hello, my name is Ravi.', { exact: true }).first(),
  ).toHaveAttribute('lang', 'en-IN')
  await expect(
    page.getByText('ನಿಮ್ಮ ಹೆಸರನ್ನು ಹೇಳಿ.', { exact: true }),
  ).toHaveAttribute('lang', 'kn-IN')
  await page
    .getByRole('button', {
      name: message('learner.mission.continue_to_speak'),
    })
    .click()

  await page
    .getByRole('button', {
      name: message('learner.recording.start'),
    })
    .click()
  // The PCM recorder needs real capture time: ~80ms settle plus 4096-frame
  // ScriptProcessor buffers, so a short window yields no samples at all.
  await page.waitForTimeout(1200)
  await page
    .getByRole('button', {
      name: message('learner.recording.stop'),
    })
    .click()
  await expect(
    page.getByLabel(message('learner.recording.playback_label')),
  ).toBeVisible()
  await page
    .getByRole('button', {
      name: message('learner.recording.retry'),
    })
    .click()

  await page
    .getByRole('button', {
      name: message('learner.recording.start'),
    })
    .click()
  // The PCM recorder needs real capture time: ~80ms settle plus 4096-frame
  // ScriptProcessor buffers, so a short window yields no samples at all.
  await page.waitForTimeout(1200)
  await page
    .getByRole('button', {
      name: message('learner.recording.stop'),
    })
    .click()
  await page
    .getByRole('button', {
      name: message('learner.mission.continue_to_check'),
    })
    .click()
  await expect(
    page.getByText('Choose the correct introduction.'),
  ).toHaveAttribute('lang', 'en-IN')
  await page
    .getByRole('radio', { name: 'Hello, my name is Ravi.' })
    .check()
  await page
    .getByRole('button', {
      name: message('learner.mission.submit'),
    })
    .click()

  await expect(
    page.getByRole('heading', {
      name: message('learner.result.title'),
    }),
  ).toBeFocused()
  await expect(
    page.getByText(message('learner.result.knowledge_mastered')),
  ).toBeVisible()
  await expect(
    page.getByText(message('learner.result.spoken_unassessed')).first(),
  ).toBeVisible()
  await expect(
    page.getByText(message('learner.result.local_capture_completed')).first(),
  ).toBeVisible()
  await expect(page.getByText('ಪ್ರಯತ್ನ 1')).toBeVisible()
  await expect(page.getByText('ಪ್ರಯತ್ನ 2').first()).toBeVisible()
  await expect(page.getByText(ids.first)).toHaveCount(0)
  await expect(
    page.getByText(
      localizedMessage('learner.result.capability_progress', DEFAULT_LOCALE, {
        completed: '1',
        total: '1',
      }),
    ),
  ).toBeVisible()
  await expect(
    page.getByRole('button', { name: message('learner.result.continue') }),
  ).toHaveClass(/ge-button--primary/u)
  await expect(
    page.getByRole('button', { name: message('learner.result.retry') }),
  ).not.toHaveClass(/ge-button--primary/u)

  expect(api.lastResponse?.local_recording).toMatchObject({
    captured: true,
    client_capture_id: expect.stringMatching(
      /^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i,
    ),
    // Chromium's real recorder reports the canonical opus-tagged variant.
    mime_type: expect.stringMatching(/^audio\/webm(;codecs=opus)?$/),
  })
  expect(api.lastResponse!.local_recording!.byte_size).toBeGreaterThanOrEqual(
    128,
  )
  expect(api.lastResponse!.local_recording!.byte_size).toBeLessThanOrEqual(
    10_000_000,
  )
  expect(api.lastResponse!.local_recording!.duration_ms).toBeGreaterThanOrEqual(
    250,
  )
  expect(api.lastResponse!.local_recording!.duration_ms).toBeLessThanOrEqual(
    120_000,
  )
  expect(JSON.stringify(api.lastResponse)).not.toContain('sha256')
  expect(JSON.stringify(api.lastResponse)).not.toContain('blob:')
  expect(api.requestBodies.join('')).not.toContain(
    'deterministic-local-audio',
  )
  expect(api.modelAudioRequests.length).toBeGreaterThan(0)
  expect(
    api.modelAudioRequests.every(
      (request) =>
        request.method === 'GET' &&
        request.postData === null &&
        request.url ===
          `http://127.0.0.1:4173/api/v1/media-assets/${ids.asset}/content?enrollment_id=${ids.enrollment}&mission_id=${ids.mission}`,
    ),
  ).toBe(true)
  expect(
    networkUrls.every((url) => new URL(url).hostname === '127.0.0.1'),
  ).toBe(true)
  expect(api.commandIds.every((commandId) => commandId !== null)).toBe(true)
  await expect
    .poll(() =>
      page.evaluate(() => {
        const state = window as unknown as {
          __recordingTestState: {
            revokedUrls: number
            stoppedTracks: number
          }
        }
        return state.__recordingTestState
      }),
    )
    .toMatchObject({ revokedUrls: 2, stoppedTracks: 2 })
  await expect
    .poll(() => page.evaluate(() => window.localStorage.length))
    .toBe(0)
  await expect
    .poll(() =>
      page.evaluate(async () => (await indexedDB.databases()).length),
    )
    .toBe(0)
  await expect
    .poll(() =>
      page.evaluate(async () => {
        const cacheKeys =
          'caches' in window ? await window.caches.keys() : []
        const cachedUrls = (
          await Promise.all(
            cacheKeys.map(async (key) =>
              (await (await caches.open(key)).keys()).map(
                ({ url }) => url,
              ),
            ),
          )
        ).flat()

        return {
          cacheKeys,
          cachedUrls,
          serviceWorkerRegistrations:
            navigator.serviceWorker === undefined
              ? 0
              : (await navigator.serviceWorker.getRegistrations()).length,
        }
      }),
    )
    .toMatchObject({
      cacheKeys: [publicShellCacheName],
      serviceWorkerRegistrations: 1,
    })
  const cachedUrls = await page.evaluate(async () => {
    const keys = await caches.keys()

    return (
      await Promise.all(
        keys.map(async (key) =>
          (await (await caches.open(key)).keys()).map(({ url }) => url),
        ),
      )
    ).flat()
  })
  expect(
    cachedUrls.every(
      (url) =>
        !new URL(url).pathname.startsWith('/api/') &&
        !new URL(url).pathname.startsWith('/sanctum/'),
    ),
  ).toBe(true)

  const accessibility = await new AxeBuilder({ page }).analyze()
  expect(accessibility.violations).toEqual([])
})

test('contract-stubbed microphone denial stays spoken-unassessed', async ({
  page,
}) => {
  await installDeniedMicrophone(page)
  const api = await mockLearnerApi(page, true)

  await page.goto('/#home')
  await page
    .getByRole('button', {
      name: message('learner.home.continue'),
    })
    .click()
  await page
    .getByRole('button', {
      name: message('learner.mission.continue_to_speak'),
    })
    .click()
  await page
    .getByRole('button', {
      name: message('learner.recording.start'),
    })
    .click()
  await expect(
    page.getByText(message('learner.recording.blocked')),
  ).toBeVisible()
  await page
    .getByRole('button', {
      name: message('learner.recording.continue_without'),
    })
    .click()
  await page
    .getByRole('radio', { name: 'Hello, my name is Ravi.' })
    .check()
  await page
    .getByRole('button', {
      name: message('learner.mission.submit'),
    })
    .click()

  await expect(
    page.getByText(message('learner.result.spoken_unassessed')).first(),
  ).toBeVisible()
  await expect(
    page
      .getByText(message('learner.result.local_capture_not_recorded'))
      .first(),
  ).toBeVisible()
  await expect
    .poll(() => api.lastResponse)
    .toEqual(expect.objectContaining({ local_recording: null }))
})

test('encrypts a personal-device bundle, completes offline, and syncs once on reconnect', async ({
  context,
  page,
}) => {
  await installDeniedMicrophone(page)
  const api = await mockLearnerApi(page, true)

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

  const storageEvidence = await page.evaluate(async () => {
    const request = indexedDB.open('global-english-offline-v1')
    const database = await new Promise<IDBDatabase>((resolve, reject) => {
      request.onsuccess = () => resolve(request.result)
      request.onerror = () => reject(request.error)
    })
    const readAll = <T>(
      storeName: string,
    ): Promise<T[]> =>
      new Promise((resolve, reject) => {
        const all = database
          .transaction(storeName, 'readonly')
          .objectStore(storeName)
          .getAll()
        all.onsuccess = () => resolve(all.result as T[])
        all.onerror = () => reject(all.error)
      })
    const profiles = await readAll<{
      bindingHash: string
      key: CryptoKey
      partitionId: string
    }>('profiles')
    const records = await readAll<{
      ciphertext: ArrayBuffer
      partitionId: string
      type: string
    }>('records')
    database.close()

    return {
      bindingHash: profiles[0]?.bindingHash,
      keyExtractable: profiles[0]?.key.extractable,
      partitionId: profiles[0]?.partitionId,
      recordCiphertextSizes: records.map(
        ({ ciphertext }) => ciphertext.byteLength,
      ),
      recordTypes: records.map(({ type }) => type).sort(),
      serializedIndexes: JSON.stringify(
        records.map(({ partitionId, type }) => ({
          partitionId,
          type,
        })),
      ),
    }
  })

  expect(storageEvidence.bindingHash).toMatch(/^[0-9a-f]{64}$/)
  expect(storageEvidence.keyExtractable).toBe(false)
  expect(storageEvidence.partitionId).not.toContain(ids.user)
  expect(storageEvidence.recordCiphertextSizes.every((size) => size > 0)).toBe(
    true,
  )
  expect(storageEvidence.recordTypes).toEqual(['audio', 'binding', 'bundle'])
  expect(storageEvidence.serializedIndexes).not.toContain(ids.user)
  expect(storageEvidence.serializedIndexes).not.toContain(ids.organization)

  await context.setOffline(true)
  await page
    .getByRole('button', {
      name: message('learner.home.continue'),
    })
    .click()
  await page
    .getByRole('button', {
      name: message('learner.mission.continue_to_speak'),
    })
    .click()
  await page
    .getByRole('button', {
      name: message('learner.recording.start'),
    })
    .click()
  await page
    .getByRole('button', {
      name: message('learner.recording.continue_without'),
    })
    .click()
  await page
    .getByRole('radio', { name: 'Hello, my name is Ravi.' })
    .check()
  await page
    .getByRole('button', {
      name: message('learner.mission.submit'),
    })
    .click()
  await expect(
    page.getByRole('heading', {
      name: message('learner.offline.saved'),
    }),
  ).toBeVisible()
  expect(api.offlineCompletionBodies).toHaveLength(0)

  await context.setOffline(false)
  await expect(
    page.getByRole('heading', {
      name: message('learner.result.title'),
    }),
  ).toBeVisible()
  expect(api.offlineCompletionBodies).toHaveLength(1)
  expect(api.offlineCompletionBodies[0]).toMatchObject({
    bundle_id: ids.bundle,
    local_recording: null,
    schema: 'global-english.offline-completion.v1',
    selected_option_id: 'correct-option',
  })
  expect(
    JSON.stringify(api.offlineCompletionBodies[0]),
  ).not.toContain('audio')

  await page.evaluate(async () => {
    const request = indexedDB.open('global-english-offline-v1')
    const database = await new Promise<IDBDatabase>((resolve, reject) => {
      request.onsuccess = () => resolve(request.result)
      request.onerror = () => reject(request.error)
    })
    const transaction = database.transaction('records', 'readwrite')
    const store = transaction.objectStore('records')
    const records = await new Promise<
      Array<{ ciphertext: ArrayBuffer; type: string }>
    >((resolve, reject) => {
      const all = store.getAll()
      all.onsuccess = () =>
        resolve(
          all.result as Array<{
            ciphertext: ArrayBuffer
            type: string
          }>,
        )
      all.onerror = () => reject(all.error)
    })
    const bundle = records.find(({ type }) => type === 'bundle')

    if (bundle === undefined) {
      throw new Error('Expected encrypted bundle record.')
    }

    const corrupted = new Uint8Array(bundle.ciphertext.slice(0))
    corrupted[0] = (corrupted[0] ?? 0) ^ 1
    bundle.ciphertext = corrupted.buffer
    store.put(bundle)
    await new Promise<void>((resolve, reject) => {
      transaction.oncomplete = () => resolve()
      transaction.onerror = () => reject(transaction.error)
      transaction.onabort = () => reject(transaction.error)
    })
    database.close()
  })
  await context.setOffline(true)
  await page
    .getByRole('button', {
      name: message('learner.result.retry'),
    })
    .click()
  await expect(
    page.getByRole('heading', {
      name: message('learner.result.title'),
    }),
  ).toBeVisible()
  await expect(
    page.getByRole('heading', {
      name: message('learner.mission.learn_title'),
    }),
  ).toHaveCount(0)
})

async function advanceFirstUseToGoal(
  page: Page,
  locale: SupportedLocale,
): Promise<void> {
  await continueFirstUse(page, locale)
  await page.getByRole('checkbox', {
    name: localizedMessage('learner.onboarding.adult_confirmation', locale),
  }).check()
  await continueFirstUse(page, locale)
  await expect(page.getByRole('heading', {
    name: localizedMessage('learner.onboarding.preferences_title', locale),
  })).toBeFocused()
  await continueFirstUse(page, locale)
  await page.getByRole('radio', {
    name: localizedMessage('learner.onboarding.goal_workplace', locale),
  }).check()
}

async function finishFirstUseAfterGoal(
  page: Page,
  locale: SupportedLocale,
): Promise<void> {
  await expect(page.getByRole('heading', {
    name: localizedMessage('learner.onboarding.placement_title', locale),
  })).toBeFocused()
  await page.getByRole('radio', {
    name: new RegExp(
      localizedMessage('learner.onboarding.placement_beginning', locale),
    ),
  }).check()
  await continueFirstUse(page, locale)
  await page.getByRole('button', {
    name: localizedMessage('learner.onboarding.audio_defer', locale),
  }).click()
  await continueFirstUse(page, locale)

  await expect(page.getByRole('heading', {
    name: localizedMessage('learner.onboarding.consent_title', locale),
  })).toBeFocused()
  const recordingConsent = page.getByRole('checkbox', {
    name: new RegExp(
      localizedMessage('learner.onboarding.consent_recording_title', locale),
    ),
  })
  const processingConsent = page.getByRole('checkbox', {
    name: new RegExp(
      localizedMessage('learner.onboarding.consent_ai_title', locale),
    ),
  })
  await expect(recordingConsent).not.toBeChecked()
  await expect(processingConsent).not.toBeChecked()
  await continueFirstUse(page, locale)

  await expect(page.getByRole('heading', {
    name: localizedMessage('learner.onboarding.microphone_title', locale),
  })).toBeFocused()
  await page.getByRole('button', {
    name: localizedMessage('learner.onboarding.microphone_type_instead', locale),
  }).last().click()
  await continueFirstUse(page, locale)
  await page.getByRole('radio', {
    name: localizedMessage('learner.onboarding.practice_typed', locale),
  }).check()
  await page.getByRole('textbox', {
    name: localizedMessage('learner.onboarding.practice_typed_label', locale),
  }).fill('Good morning.')
  await continueFirstUse(page, locale)
  await page.getByRole('button', {
    name: localizedMessage('learner.onboarding.open_home', locale),
  }).click()
}

async function continueFirstUse(
  page: Page,
  locale: SupportedLocale,
): Promise<void> {
  await page.getByRole('button', {
    name: localizedMessage('learner.onboarding.continue', locale),
  }).click()
}

interface MockApiState {
  commandIds: Array<string | null>
  modelAudioRequests: Array<{
    method: string
    postData: string | null
    url: string
  }>
  requestBodies: string[]
  offlineCompletionBodies: unknown[]
  lastResponse: {
    local_recording: null | {
      captured: true
      byte_size: number
      client_capture_id: string
      duration_ms: number
      mime_type: string
    }
  } | null
}

async function mockLearnerApi(
  page: Page,
  initiallyOnboarded: boolean,
  options: {
    onboardingFailureStatuses?: number[]
  } = {},
): Promise<MockApiState> {
  let onboarded = initiallyOnboarded
  const onboardingFailureStatuses = [
    ...(options.onboardingFailureStatuses ?? []),
  ]
  const state: MockApiState = {
    commandIds: [],
    lastResponse: null,
    modelAudioRequests: [],
    offlineCompletionBodies: [],
    requestBodies: [],
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
    if (postData !== null) {
      state.requestBodies.push(postData)
    }

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

    if (path === '/api/v1/me/onboarding') {
      state.commandIds.push(request.headers()['idempotency-key'] ?? null)
      const failureStatus = onboardingFailureStatuses.shift()
      if (failureStatus !== undefined) {
        await json(route, { message: 'synthetic onboarding failure' }, failureStatus)
        return
      }
      onboarded = true
      await json(route, home(true))
      return
    }

    if (path === '/api/v1/voice-consents' && request.method() === 'GET') {
      await json(route, voiceConsentListing())
      return
    }

    if (
      path === `/api/v1/missions/${ids.mission}/offline-bundle` &&
      request.method() === 'GET'
    ) {
      await json(route, offlineBundle())
      return
    }

    if (
      path === '/api/v1/offline-completions' &&
      request.method() === 'POST'
    ) {
      state.commandIds.push(request.headers()['idempotency-key'] ?? null)
      state.offlineCompletionBodies.push(
        JSON.parse(postData ?? 'null') as unknown,
      )
      await json(route, result(false))
      return
    }

    if (
      path === `/api/v1/missions/${ids.mission}` &&
      request.method() === 'GET'
    ) {
      expect(url.searchParams.get('enrollment_id')).toBe(ids.enrollment)
      await json(route, mission())
      return
    }

    if (path === `/api/v1/media-assets/${ids.asset}/content`) {
      expect(url.searchParams.get('enrollment_id')).toBe(ids.enrollment)
      expect(url.searchParams.get('mission_id')).toBe(ids.mission)
      expect(request.headers()['cookie']).toContain(
        'XSRF-TOKEN=e2e-csrf-token',
      )
      state.modelAudioRequests.push({
        method: request.method(),
        postData,
        url: request.url(),
      })
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

    if (
      path === `/api/v1/missions/${ids.mission}/attempts` &&
      request.method() === 'POST'
    ) {
      state.commandIds.push(request.headers()['idempotency-key'] ?? null)
      await json(route, { id: ids.attempt, status: 'in_progress' }, 201)
      return
    }

    if (path === `/api/v1/attempts/${ids.attempt}/responses`) {
      state.commandIds.push(request.headers()['idempotency-key'] ?? null)
      expect(postData).not.toBeNull()
      state.lastResponse = JSON.parse(
        postData ?? 'null',
      ) as MockApiState['lastResponse']
      await route.fulfill({ status: 204 })
      return
    }

    if (path === `/api/v1/attempts/${ids.attempt}/submit`) {
      state.commandIds.push(request.headers()['idempotency-key'] ?? null)
      expect(postData).toBeNull()
      await json(route, result(state.lastResponse?.local_recording != null))
      return
    }

    if (path === '/api/v1/progress') {
      await json(route, progress(state.lastResponse?.local_recording != null))
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

async function installWorkingMicrophone(page: Page): Promise<void> {
  await page.addInitScript(() => {
    const testState = {
      revokedUrls: 0,
      stoppedTracks: 0,
    }
    const nativeRevoke = URL.revokeObjectURL.bind(URL)
    URL.revokeObjectURL = (url: string) => {
      testState.revokedUrls += 1
      nativeRevoke(url)
    }
    Object.assign(window, { __recordingTestState: testState })

    // The PCM recorder feeds getUserMedia's stream into a real AudioContext,
    // so a scripted stub object cannot work and headless CI has no capture
    // device. Synthesize a genuine MediaStream from an oscillator instead:
    // deterministic, device-free, and type-correct for WebAudio.
    Object.defineProperty(navigator, 'mediaDevices', {
      configurable: true,
      value: {
        getUserMedia: async () => {
          const context = new AudioContext()
          const oscillator = context.createOscillator()
          const destination = context.createMediaStreamDestination()
          oscillator.frequency.value = 440
          oscillator.connect(destination)
          oscillator.start()
          void context.resume()

          const stream = destination.stream
          for (const track of stream.getTracks()) {
            const nativeStop = track.stop.bind(track)
            track.stop = () => {
              testState.stoppedTracks += 1
              oscillator.stop()
              void context.close()
              nativeStop()
            }
          }
          return stream
        },
      },
    })
  })
}

async function installDeniedMicrophone(page: Page): Promise<void> {
  await page.addInitScript(() => {
    Object.defineProperty(navigator, 'mediaDevices', {
      configurable: true,
      value: {
        getUserMedia: async () => {
          throw new DOMException('Denied for deterministic test', 'NotAllowedError')
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
}

function voiceConsentListing() {
  return {
    consents: [
      {
        effective_at: null,
        purpose: 'voice_upload_and_transcription',
        state: 'not_granted',
      },
      {
        effective_at: null,
        purpose: 'conversation_provider_processing',
        state: 'not_granted',
      },
      {
        effective_at: null,
        purpose: 'transcript_history',
        state: 'not_granted',
      },
    ],
    notice: {
      history_optional: true,
      notice:
        'Audio is used only for purposes you select. Refusal preserves typed learning.',
      notice_revision: 'voice-notice-v1',
      notice_sha256: 'a'.repeat(64),
      policy_version: 'voice-policy-v1',
      purposes: [
        'voice_upload_and_transcription',
        'conversation_provider_processing',
        'transcript_history',
      ],
      requested_locale: 'en-IN',
      resolved_locale: 'en-IN',
      review_status: 'implementation_draft',
    },
  }
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

function offlineBundle() {
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
          content_hash: createHash('sha256')
            .update(audio)
            .digest('hex'),
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

function result(hasRecording: boolean) {
  return {
    attempt_id: ids.attempt,
    can_do: content('en-IN', 'I can introduce myself.'),
    check_state: 'correct',
    evidence: {
      best_attempt_id: ids.best,
      best_attempt_number: 2,
      first_attempt_id: ids.first,
      first_attempt_number: 1,
      latest_attempt_id: ids.latest,
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

function progress(hasRecording: boolean) {
  const missionResult = result(hasRecording)

  return {
    enrollment_id: ids.enrollment,
    objectives: [
      {
        evidence: missionResult.evidence,
        knowledge_state: 'mastered',
        label: content('en-IN', 'Introduce myself'),
        local_capture_receipt_present:
          missionResult.local_capture_receipt_present,
        objective_id: ids.objective,
        review_due_at: missionResult.review.due_at,
        review_timezone: missionResult.review.timezone,
        spoken_state: missionResult.spoken_state,
      },
    ],
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

function message(key: MessageKey): string {
  return resolveMessage(key, {
    policy: 'development-preview',
    requestedLocale: DEFAULT_LOCALE,
  }).text
}

function localizedMessage(
  key: MessageKey,
  locale: SupportedLocale,
  parameters?: Record<string, string>,
): string {
  return resolveMessage(key, {
    parameters,
    policy: 'development-preview',
    requestedLocale: locale,
  }).text
}
