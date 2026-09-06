import { defineConfig, devices } from '@playwright/test'
import { playwrightOutputDir } from './playwrightOutput'

export default defineConfig({
  testDir: './e2e',
  outputDir: playwrightOutputDir('web'),
  fullyParallel: true,
  forbidOnly: Boolean(process.env.CI),
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : undefined,
  reporter: 'list',
  use: {
    baseURL: 'http://127.0.0.1:4173',
    trace: 'on-first-retry',
    // The PCM recorder needs a real MediaStream feeding a real AudioContext.
    // CI runners have no microphone, so recording journeys use Chromium's
    // deterministic fake capture device (the flag is
    // --use-fake-device-for-media-stream; the -capture variant does not exist).
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
      name: 'chromium',
      use: devices['Desktop Chrome'],
    },
  ],
  webServer: {
    command:
      'VITE_UI_TRANSLATION_PREVIEW=true pnpm build && pnpm preview --host 127.0.0.1 --port 4173',
    url: 'http://127.0.0.1:4173',
    reuseExistingServer: !process.env.CI,
  },
})
