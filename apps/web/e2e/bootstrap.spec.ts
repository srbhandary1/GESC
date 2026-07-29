import AxeBuilder from '@axe-core/playwright'
import {
  DEFAULT_LOCALE,
  resolveMessage,
  type MessageKey,
  type SupportedLocale,
} from '@global-english/localization'
import { expect, test, type Page } from '@playwright/test'

test.beforeEach(async ({ page }) => {
  await mockAnonymousSession(page)
})

test('renders an axe-clean Kannada-first semantic shell', async ({
  page,
}) => {
  await page.goto('/')

  await expect(page.locator('html')).toHaveAttribute(
    'lang',
    DEFAULT_LOCALE,
  )
  await expect(page.getByRole('banner')).toBeVisible()
  await expect(
    page.getByRole('navigation', {
      name: message('shell.navigation_label'),
    }),
  ).toBeVisible()
  await expect(page.getByRole('main')).toBeVisible()
  await expect(
    page.getByRole('heading', {
      level: 1,
      name: message('page.home.title'),
    }),
  ).toBeVisible()
  await expect(
    page.getByRole('link', { name: message('nav.home') }),
  ).toHaveAttribute('aria-current', 'page')
  await expect(page.getByRole('alert')).toHaveCount(0)

  const accessibility = await new AxeBuilder({ page }).analyze()

  expect(accessibility.violations).toEqual([])

  await page
    .getByRole('combobox', { name: message('shell.locale_label') })
    .selectOption('en-IN')
  await expect(page.locator('html')).toHaveAttribute('lang', 'en-IN')

  const englishAccessibility = await new AxeBuilder({ page }).analyze()

  expect(englishAccessibility.violations).toEqual([])
})

test('supports skip-link and destination navigation from the keyboard', async ({
  page,
}) => {
  await page.goto('/')

  await page.keyboard.press('Tab')
  const skipLink = page.getByRole('link', {
    name: message('shell.skip_to_content'),
  })

  await expect(skipLink).toBeFocused()
  await expect(skipLink).toBeVisible()
  await page.keyboard.press('Enter')
  await expect(page.getByRole('main')).toBeFocused()

  await page.reload()
  await page.keyboard.press('Tab')
  await page.keyboard.press('Tab')
  await page.keyboard.press('Tab')
  await page.keyboard.press('Tab')

  const learnLink = page.locator('a[href="#learn"]')
  await expect(learnLink).toBeFocused()
  await page.keyboard.press('Enter')

  const learnTitle = page.getByRole('heading', {
    level: 1,
    name: message('page.learn.title'),
  })

  await expect(learnLink).toHaveAttribute('aria-current', 'page')
  await expect(learnTitle).toBeFocused()

  await page.goto('/#progress')

  await expect(
    page.getByRole('heading', {
      level: 1,
      name: message('page.progress.title'),
    }),
  ).toBeVisible()
  await expect(page.locator('a[href="#progress"]')).toHaveAttribute(
    'aria-current',
    'page',
  )

  await page.goto('/#learn')
  await page.keyboard.press('Tab')
  await page.keyboard.press('Enter')

  await expect(page.getByRole('main')).toBeFocused()
  expect(new URL(page.url()).hash).toBe('#learn')

  await page.reload()
  await expect(
    page.getByRole('heading', {
      level: 1,
      name: message('page.learn.title'),
    }),
  ).toBeVisible()
})

test('keeps the locale selection ephemeral and stores no credentials', async ({
  page,
}) => {
  await page.goto('/')

  await page
    .getByRole('combobox', { name: message('shell.locale_label') })
    .selectOption('en-IN')

  await expect(page.locator('html')).toHaveAttribute('lang', 'en-IN')
  await expect(
    page.getByRole('heading', {
      level: 1,
      name: message('page.home.title', 'en-IN'),
    }),
  ).toBeVisible()
  await expect
    .poll(() => page.evaluate(() => window.localStorage.length))
    .toBe(0)
  await expect
    .poll(() =>
      page.evaluate(async () => {
        const databases = await window.indexedDB.databases()

        return databases.length
      }),
    )
    .toBe(0)

  await page.reload()

  await expect(page.locator('html')).toHaveAttribute(
    'lang',
    DEFAULT_LOCALE,
  )
})

test('reflows at target widths and enlarged-text stress proxies', async ({
  page,
}) => {
  for (const width of [320, 360, 412, 768, 1280]) {
    await page.setViewportSize({ height: 900, width })
    await page.goto('/')

    await expectNoHorizontalOverflow(page)
    await expect(page.getByRole('main')).toBeVisible()
    await expect(page.locator('.ge-primary-navigation')).toBeVisible()
  }

  await page.setViewportSize({ height: 900, width: 320 })

  for (const textScale of [200, 400]) {
    await page.evaluate((scale) => {
      document.documentElement.style.fontSize = `${scale}%`
    }, textScale)

    await expectNoHorizontalOverflow(page)
  }

  await page.evaluate(() => {
    document.documentElement.style.removeProperty('font-size')
  })

  for (const target of await page
    .locator('.ge-primary-navigation__link, #interface-locale')
    .all()) {
    const box = await target.boundingBox()

    expect(box).not.toBeNull()
    expect(box!.height).toBeGreaterThanOrEqual(44)
    expect(box!.width).toBeGreaterThanOrEqual(44)
  }
})

test('loads the Kannada font stack and honors reduced motion', async ({
  page,
}) => {
  await page.emulateMedia({ reducedMotion: 'reduce' })
  await page.goto('/')

  const fontEvidence = await page.evaluate(async () => {
    await document.fonts.ready
    const kannadaFaces = await document.fonts.load(
      '16px "Noto Sans Kannada Variable"',
      'ಕನ್ನಡ',
    )

    return {
      family: getComputedStyle(document.documentElement).fontFamily,
      loaded: document.fonts.check(
        '16px "Noto Sans Kannada Variable"',
        'ಕನ್ನಡ',
      ),
      loadedFaceCount: kannadaFaces.length,
      resources: performance
        .getEntriesByType('resource')
        .map(({ name }) => name)
        .filter(
          (name) =>
            name.includes('noto-sans-kannada-kannada-wght-normal') &&
            name.endsWith('.woff2'),
        ),
      status: document.fonts.status,
      scrollBehavior: getComputedStyle(
        document.documentElement,
      ).scrollBehavior,
    }
  })

  expect(fontEvidence.family).toContain('Noto Sans Kannada Variable')
  expect(fontEvidence.loaded).toBe(true)
  expect(fontEvidence.loadedFaceCount).toBeGreaterThan(0)
  expect(fontEvidence.resources).toHaveLength(1)
  expect(fontEvidence.status).toBe('loaded')
  expect(fontEvidence.scrollBehavior).toBe('auto')
})

async function mockAnonymousSession(page: Page): Promise<void> {
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
}

async function expectNoHorizontalOverflow(page: Page): Promise<void> {
  await expect
    .poll(() =>
      page.evaluate(
        () =>
          document.documentElement.scrollWidth <=
          document.documentElement.clientWidth,
      ),
    )
    .toBe(true)
}

function message(
  key: MessageKey,
  locale: SupportedLocale = DEFAULT_LOCALE,
): string {
  return resolveMessage(key, {
    policy: 'development-preview',
    requestedLocale: locale,
  }).text
}
