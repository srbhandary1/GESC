import AxeBuilder from '@axe-core/playwright'
import {
  DEFAULT_LOCALE,
  resolveMessage,
  SUPPORTED_LOCALES,
  type MessageKey,
  type SupportedLocale,
} from '@global-english/localization'
import { expect, test, type Page } from '@playwright/test'

test.beforeEach(async ({ page }) => {
  await mockAnonymousSession(page)
})

test('renders an axe-clean Kannada-first pre-sign-in splash', async ({
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
  ).toHaveCount(0)
  await expect(page.getByRole('main')).toBeVisible()
  await expect(
    page.getByRole('heading', {
      level: 1,
      name: message('shell.splash.tagline'),
    }),
  ).toBeVisible()
  await expect(
    page.getByRole('button', { name: message('session.sign_in') }),
  ).toBeVisible()
  await expect(
    page.getByRole('heading', { name: message('session.label') }),
  ).toHaveCount(0)
  await expect(
    page.getByText(message('shell.eyebrow')),
  ).toHaveCount(0)
  await expect(page.getByRole('alert')).toHaveCount(0)

  const accessibility = await new AxeBuilder({ page }).analyze()

  expect(accessibility.violations).toEqual([])

  for (const locale of SUPPORTED_LOCALES.filter(
    (candidate) => candidate !== DEFAULT_LOCALE,
  )) {
    await page.locator('#interface-locale').selectOption(locale)
    await expect(page.locator('html')).toHaveAttribute('lang', locale)

    const localeAccessibility = await new AxeBuilder({ page }).analyze()

    expect(localeAccessibility.violations).toEqual([])
  }
})

test('supports skip-link focus without exposing learner destinations', async ({
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

  await page.goto('/#learn')
  await expect(
    page.getByRole('heading', {
      level: 1,
      name: message('shell.splash.tagline'),
    }),
  ).toBeVisible()
  await expect(
    page.getByRole('navigation', {
      name: message('shell.navigation_label'),
    }),
  ).toHaveCount(0)
  await expect(
    page.getByRole('heading', { name: message('page.learn.title') }),
  ).toHaveCount(0)

  await page.goto('/#progress')
  await expect(
    page.getByRole('heading', {
      level: 1,
      name: message('shell.splash.tagline'),
    }),
  ).toBeVisible()
  await expect(page.locator('a[href="#progress"]')).toHaveCount(0)

  await page.goto('/#learn')
  await page.keyboard.press('Tab')
  await page.keyboard.press('Enter')

  await expect(page.getByRole('main')).toBeFocused()
  expect(new URL(page.url()).hash).toBe('#learn')
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
      name: message('shell.splash.tagline', 'en-IN'),
    }),
  ).toBeVisible()
  await expect
    .poll(() =>
      page.evaluate(() =>
        Object.keys(window.localStorage).filter(
          (key) => !key.startsWith('ge-ui-'),
        ),
      ),
    )
    .toEqual([])
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
    await expect(page.locator('.ge-primary-navigation')).toHaveCount(0)
    await expect(
      page.getByRole('button', { name: message('session.sign_in') }),
    ).toBeVisible()
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
    .locator(
      '[data-pre-sign-in] .ge-button, .ge-theme-toggle, #interface-locale',
    )
    .all()) {
    const box = await target.boundingBox()

    expect(box).not.toBeNull()
    expect(box!.height).toBeGreaterThanOrEqual(48)
    expect(box!.width).toBeGreaterThanOrEqual(48)
  }
})

test('loads every Phase 1 interface font and honors reduced motion', async ({
  page,
}) => {
  await page.emulateMedia({ reducedMotion: 'reduce' })
  await page.goto('/')

  const fontEvidence = await page.evaluate(async () => {
    const expectedFonts = [
      {
        family: 'Noto Sans Kannada Variable',
        resource: 'noto-sans-kannada-kannada-wght-normal',
        sample: 'ಕನ್ನಡ',
      },
      {
        family: 'Noto Sans Malayalam Variable',
        resource: 'noto-sans-malayalam-malayalam-wght-normal',
        sample: 'മലയാളം',
      },
      {
        family: 'Noto Sans Tamil Variable',
        resource: 'noto-sans-tamil-tamil-wght-normal',
        sample: 'தமிழ்',
      },
      {
        family: 'Noto Sans Telugu Variable',
        resource: 'noto-sans-telugu-telugu-wght-normal',
        sample: 'తెలుగు',
      },
      {
        family: 'Noto Sans Devanagari Variable',
        resource: 'noto-sans-devanagari-devanagari-wght-normal',
        sample: 'हिन्दी',
      },
    ]

    await document.fonts.ready
    const fonts = []

    for (const expected of expectedFonts) {
      const faces = await document.fonts.load(
        `16px "${expected.family}"`,
        expected.sample,
      )

      fonts.push({
        family: expected.family,
        loaded: document.fonts.check(
          `16px "${expected.family}"`,
          expected.sample,
        ),
        loadedFaceCount: faces.length,
        resource: expected.resource,
      })
    }

    await document.fonts.ready

    return {
      family: getComputedStyle(document.documentElement).fontFamily,
      fonts,
      resources: performance
        .getEntriesByType('resource')
        .map(({ name }) => name),
      status: document.fonts.status,
      scrollBehavior: getComputedStyle(
        document.documentElement,
      ).scrollBehavior,
    }
  })

  expect(fontEvidence.family).toContain('Noto Sans Kannada Variable')
  for (const font of fontEvidence.fonts) {
    expect(fontEvidence.family).toContain(font.family)
    expect(font.loaded).toBe(true)
    expect(font.loadedFaceCount).toBeGreaterThan(0)
    expect(
      fontEvidence.resources.some(
        (resource) =>
          resource.includes(font.resource) &&
          resource.endsWith('.woff2'),
      ),
    ).toBe(true)
  }
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
