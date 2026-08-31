import { expect, test, type Page } from '@playwright/test'

const apiOrigin =
  process.env.MOBILE_E2E_API_ORIGIN ?? 'http://127.0.0.1:8080'
const oauthClientId =
  process.env.MOBILE_E2E_OAUTH_CLIENT_ID ??
  '019fdd3f-4bba-720b-9e4e-a771ecccdd7f'

test.beforeEach(async ({ page }) => {
  await installCapacitorAndroid(page)
})

test('mobile build reaches the live API origin with packaged OAuth settings', async ({
  page,
}) => {
  await page.goto('/#home')

  const healthStatus = await page.evaluate(async (origin) => {
    const response = await fetch(`${origin}/api/v1/health/live`, {
      headers: { Accept: 'application/json' },
    })
    return response.status
  }, apiOrigin)

  expect(healthStatus).toBe(200)

  const scriptPath = await page
    .locator('script[type="module"][src*="/assets/index-"]')
    .first()
    .getAttribute('src')
  expect(scriptPath).toBeTruthy()

  const bundle = await page.request.get(String(scriptPath))
  expect(bundle.ok()).toBeTruthy()
  const source = await bundle.text()
  expect(source).toContain(apiOrigin)
  expect(source).toContain(oauthClientId)
})

test('live OAuth authorize accepts the packaged public client id', async ({
  request,
}) => {
  const verifier = randomBase64Url(64)
  const challenge = await sha256Base64Url(verifier)
  const state = randomBase64Url(32)
  const redirectUri = 'com.globalenglish.app:/oauth/callback'
  const authorizeUrl = new URL(`${apiOrigin}/oauth/authorize`)
  authorizeUrl.searchParams.set('client_id', oauthClientId)
  authorizeUrl.searchParams.set('code_challenge', challenge)
  authorizeUrl.searchParams.set('code_challenge_method', 'S256')
  authorizeUrl.searchParams.set('redirect_uri', redirectUri)
  authorizeUrl.searchParams.set('response_type', 'code')
  authorizeUrl.searchParams.set('scope', 'mobile:api')
  authorizeUrl.searchParams.set('state', state)

  const response = await request.get(authorizeUrl.toString(), {
    maxRedirects: 0,
  })

  expect([302, 303]).toContain(response.status())
  expect(response.headers()['location'] ?? '').toMatch(/password|sign-in|login/i)
})

test('Capacitor WebView recording is supported on the secure localhost origin', async ({
  page,
}) => {
  await installWorkingMicrophone(page)
  await page.goto('/#learn')

  const supported = await page.evaluate(async () => {
    const mediaDevices = navigator.mediaDevices
    const AudioContextCtor =
      globalThis.AudioContext ??
      (
        globalThis as typeof globalThis & {
          webkitAudioContext?: typeof AudioContext
        }
      ).webkitAudioContext

    if (mediaDevices?.getUserMedia === undefined || AudioContextCtor === undefined) {
      return false
    }

    const stream = await mediaDevices.getUserMedia({ audio: true, video: false })
    stream.getTracks().forEach((track) => track.stop())
    return true
  })

  expect(supported).toBe(true)
})

test('marketing homepage advertises the Android APK download', async ({ page }) => {
  await page.goto('/#home')
  await expect(
    page.locator('a[href="/downloads/global-spoken-english.apk"]'),
  ).toBeVisible()
})

async function installCapacitorAndroid(page: Page): Promise<void> {
  await page.addInitScript(() => {
    ;(
      globalThis as typeof globalThis & {
        Capacitor?: {
          isNativePlatform: () => boolean
          getPlatform: () => string
        }
      }
    ).Capacitor = {
      isNativePlatform: () => true,
      getPlatform: () => 'android',
    }
  })
}

async function installWorkingMicrophone(page: Page): Promise<void> {
  await page.addInitScript(() => {
    const original = navigator.mediaDevices?.getUserMedia?.bind(
      navigator.mediaDevices,
    )

    if (original === undefined) {
      return
    }

    navigator.mediaDevices.getUserMedia = async (constraints) => {
      try {
        return await original(constraints ?? { audio: true })
      } catch {
        const context = new AudioContext()
        const destination = context.createMediaStreamDestination()
        const oscillator = context.createOscillator()
        oscillator.connect(destination)
        oscillator.start()
        return destination.stream
      }
    }
  })
}

function randomBase64Url(length: number): string {
  const bytes = new Uint8Array(length)
  crypto.getRandomValues(bytes)
  return btoa(String.fromCharCode(...bytes))
    .replace(/\+/g, '-')
    .replace(/\//g, '_')
    .replace(/=+$/u, '')
}

async function sha256Base64Url(value: string): Promise<string> {
  const digest = await crypto.subtle.digest(
    'SHA-256',
    new TextEncoder().encode(value),
  )
  return btoa(String.fromCharCode(...new Uint8Array(digest)))
    .replace(/\+/g, '-')
    .replace(/\//g, '_')
    .replace(/=+$/u, '')
}
