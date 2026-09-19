import AxeBuilder from '@axe-core/playwright'
import {
  DEFAULT_LOCALE,
  INTERFACE_LOCALES,
  resolveMessage,
  type MessageKey,
  type SupportedLocale,
} from '@global-english/localization'
import { expect, test, type Page } from '@playwright/test'

test.beforeEach(async ({ page }) => {
  await mockAnonymousSession(page)
})

test('opens the dedicated sign-in page instead of the retired LPL-01 review aid', async ({
  page,
}) => {
  await page.emulateMedia({ colorScheme: 'dark' })
  await page.goto('/lpl01-reconciliation.html')
  await expect(
    page.getByRole('heading', {
      name: 'LPL-01 human transcript reconciliation',
    }),
  ).toHaveCount(0)

  await page.goto('/#sign-in')
  await expect(page.locator('#sign-in-title')).toBeVisible()

  const frame = page.locator('.ge-application-frame')
  await expect(frame).toHaveClass(/ge-application-frame--jezweb/u)
  await expect(page.locator('.shell-sign-in-page__card')).toHaveCSS(
    'background-color',
    'rgb(255, 255, 255)',
  )
  await expect(page.locator('#web-password-username')).toHaveCSS(
    'background-color',
    'rgb(255, 255, 255)',
  )
  await expect(page.locator('#web-password-username')).toHaveCSS(
    'color',
    'rgb(26, 26, 26)',
  )

  const showPassword = page.getByRole('checkbox', {
    name: message('auth.password.show'),
  })
  await expect(showPassword).toHaveCSS('width', '24px')
  await expect(showPassword).toHaveCSS('height', '24px')
  const checkboxLabel = showPassword.locator('xpath=ancestor::label')
  expect((await checkboxLabel.boundingBox())?.height).toBeGreaterThanOrEqual(48)

  const accessibility = await new AxeBuilder({ page }).analyze()
  expect(accessibility.violations).toEqual([])
})

test('creates one invited password account without browser persistence', async ({
  page,
}) => {
  let submitted: unknown = null
  await page.route(
    '**/api/v1/auth/password/invitation-enrollments',
    async (route) => {
      submitted = route.request().postDataJSON()
      await route.fulfill({ status: 204 })
    },
  )
  await page.goto('/#sign-in')

  await page
    .getByRole('button', {
      name: message('auth.password.invitation_open'),
    })
    .click()
  await page
    .getByLabel(message('auth.password.invitation_code'))
    .fill('GEI-CLOSED-GROUP-2026')
  await page
    .getByLabel(message('auth.password.username'))
    .fill('closed.tester')
  await page
    .locator('#web-password-password')
    .fill('Closed group learner phrase 2026!')
  await page
    .locator('#web-password-confirmation')
    .fill('Closed group learner phrase 2026!')
  await page
    .getByRole('button', {
      name: message('auth.password.invitation_submit'),
    })
    .click()

  await expect(
    page.getByText(message('auth.password.invitation_complete')),
  ).toBeVisible()
  expect(submitted).toEqual({
    invitation_code: 'GEI-CLOSED-GROUP-2026',
    username: 'closed.tester',
    password: 'Closed group learner phrase 2026!',
  })
  expect(
    await page.evaluate(() => ({
      local: Object.keys(localStorage),
      session: Object.keys(sessionStorage),
    })),
  ).toEqual({ local: [], session: [] })
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
    // exact: the marketing hero CTA contains this label as a substring.
    page.getByRole('link', { exact: true, name: message('session.label') }),
  ).toBeVisible()
  await expect(page.getByRole('main')).toBeVisible()
  await expect(
    page.getByRole('heading', {
      level: 1,
      name: message('demo.phase1.home.title'),
    }),
  ).toBeVisible()
  await expect(page.getByRole('img', { name: message('tutor.name') })).toBeVisible()
  await expect(page.locator('[aria-roledescription="carousel"]')).toHaveCount(0)
  await expect(page.locator('.marketing-hero__slide')).toHaveCount(0)
  await expect(page.locator('.marketing-landing')).toHaveCount(0)
  await expect(
    page.getByRole('link', {
      name: message('marketing.hero.cta_learn'),
    }),
  ).toHaveAttribute('href', '#about')
  await expect(
    page.getByRole('link', { name: message('demo.phase1.home.speak') }),
  ).toHaveAttribute('href', '#sign-in')
  await expect(
    page.getByRole('navigation', {
      name: message('shell.navigation_label'),
    }),
  ).toHaveCount(0)
  await expect(page.getByRole('alert')).toHaveCount(0)

  const accessibility = await new AxeBuilder({ page }).analyze()

  expect(accessibility.violations).toEqual([])

  for (const locale of INTERFACE_LOCALES.filter(
    (candidate) => candidate !== DEFAULT_LOCALE,
  )) {
    await page.locator('#interface-locale').selectOption(locale)
    await expect(page.locator('html')).toHaveAttribute('lang', locale)

    const localeAccessibility = await new AxeBuilder({ page }).analyze()

    expect(localeAccessibility.violations).toEqual([])
  }
})

test('keeps MarketingLanding on the explicit about hash', async ({
  page,
}) => {
  await page.goto('/#about')

  await expect(
    page.getByRole('heading', {
      level: 1,
      name: message('marketing.hero.title'),
    }),
  ).toBeVisible()
  await expect(page.locator('.marketing-landing')).toHaveCount(1)
  await expect(page.locator('[data-guest-mira-home]')).toHaveCount(0)
})

test('supports skip-link and sign-in from the keyboard', async ({
  page,
}) => {
  await page.goto('/')

  const skipLink = page.getByRole('link', {
    name: message('shell.skip_to_content'),
  })
  await expect(skipLink).toBeAttached()
  await page.keyboard.press('Tab')

  await expect(skipLink).toBeFocused()
  await expect(skipLink).toBeVisible()
  await page.keyboard.press('Enter')
  await expect(page.getByRole('main')).toBeFocused()

  await page
    .getByRole('link', { exact: true, name: message('session.label') })
    .click()
  await expect(page.locator('#sign-in-title')).toBeVisible()
  await expect(page.locator('#web-password-username')).toBeFocused()
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
      name: 'Today',
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
    await expect(
      page.getByRole('link', { exact: true, name: message('session.label') }),
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
    .locator('.shell-header-authentication, #interface-locale')
    .all()) {
    const box = await target.boundingBox()

    expect(box).not.toBeNull()
    expect(box!.height).toBeGreaterThanOrEqual(44)
    expect(box!.width).toBeGreaterThanOrEqual(44)
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
  await page.context().addCookies([
    {
      name: 'XSRF-TOKEN',
      url: 'http://127.0.0.1:4173',
      value: 'bootstrap-e2e-csrf-token',
    },
  ])
  await page.route('**/sanctum/csrf-cookie', async (route) => {
    await route.fulfill({ status: 204 })
  })
  await page.route('**/api/v1/auth/password/mode', async (route) => {
    await route.fulfill({
      contentType: 'application/json',
      json: { mode: 'password_only' },
      status: 200,
    })
  })
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
      page.evaluate(() => {
        const viewportWidth = document.documentElement.clientWidth
        if (document.documentElement.scrollWidth <= viewportWidth) {
          return []
        }

        return Array.from(document.querySelectorAll<HTMLElement>('body *'))
          .filter((element) => {
            const bounds = element.getBoundingClientRect()

            return bounds.left < 0 || bounds.right > viewportWidth
          })
          .slice(0, 12)
          .map((element) => {
            const ancestry: string[] = []
            let ancestor: HTMLElement | null = element
            while (ancestor !== null && ancestry.length < 5) {
              const bounds = ancestor.getBoundingClientRect()
              ancestry.push(
                `${ancestor.tagName}.${ancestor.className || '-'}[${Math.round(bounds.left)},${Math.round(bounds.right)}]`,
              )
              ancestor = ancestor.parentElement
            }

            return {
              ancestry,
              className: element.className,
              documentWidth: document.documentElement.scrollWidth,
              left: Math.round(element.getBoundingClientRect().left),
              right: Math.round(element.getBoundingClientRect().right),
              tagName: element.tagName,
              text: element.textContent?.trim().slice(0, 80) ?? '',
            }
          })
      }),
    )
    .toEqual([])
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
