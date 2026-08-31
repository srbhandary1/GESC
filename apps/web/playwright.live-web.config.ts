import { defineConfig, devices } from '@playwright/test'

const baseURL = process.env.LIVE_WEB_BASE_URL ?? 'http://127.0.0.1:8080'

export default defineConfig({
  testDir: './e2e',
  outputDir: './.playwright-output-live-web',
  fullyParallel: false,
  forbidOnly: Boolean(process.env.CI),
  retries: 0,
  workers: 1,
  reporter: 'list',
  timeout: 180_000,
  expect: { timeout: 30_000 },
  use: {
    baseURL,
    ignoreHTTPSErrors: true,
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
      name: 'live-web-setup',
      testMatch: 'live-web-auth.setup.ts',
    },
    {
      name: 'live-web',
      testMatch: 'live-web-complete.spec.ts',
      dependencies: ['live-web-setup'],
      use: {
        ...devices['Desktop Chrome'],
        storageState: '.playwright-output-live-web/northstar-session.json',
      },
    },
  ],
})
