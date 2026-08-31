import { defineConfig, devices } from '@playwright/test'

const apiOrigin =
  process.env.MOBILE_E2E_API_ORIGIN ?? 'http://127.0.0.1:8080'
const oauthClientId =
  process.env.MOBILE_E2E_OAUTH_CLIENT_ID ??
  '019fdd3f-4bba-720b-9e4e-a771ecccdd7f'

export default defineConfig({
  testDir: './e2e',
  testMatch: 'mobile-android-live.spec.ts',
  outputDir: './.playwright-output-mobile',
  fullyParallel: false,
  forbidOnly: Boolean(process.env.CI),
  retries: 0,
  workers: 1,
  reporter: 'list',
  timeout: 120_000,
  use: {
    baseURL: 'http://127.0.0.1:4174',
    trace: 'retain-on-failure',
    launchOptions: {
      args: [
        '--use-fake-ui-for-media-stream',
        '--use-fake-device-for-media-stream',
        '--autoplay-policy=no-user-gesture-required',
      ],
    },
    permissions: ['microphone'],
  },
  projects: [
    {
      name: 'mobile-android-chrome',
      use: {
        ...devices['Pixel 5'],
      },
    },
  ],
  webServer: {
    command: [
      'VITE_UI_TRANSLATION_PREVIEW=true',
      `VITE_MOBILE_API_ORIGIN=${apiOrigin}`,
      `VITE_MOBILE_OAUTH_CLIENT_ID=${oauthClientId}`,
      'pnpm run build && pnpm preview --host 127.0.0.1 --port 4174',
    ].join(' '),
    url: 'http://127.0.0.1:4174',
    reuseExistingServer: !process.env.CI,
    timeout: 180_000,
  },
})
