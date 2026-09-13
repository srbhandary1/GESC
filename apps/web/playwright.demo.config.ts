import { defineConfig, devices } from '@playwright/test'
import { playwrightOutputDir } from './playwrightOutput'

export default defineConfig({
  testDir: './e2e-demo',
  outputDir: playwrightOutputDir('demo'),
  fullyParallel: false,
  forbidOnly: true,
  retries: 0,
  workers: 1,
  reporter: 'list',
  use: {
    baseURL: 'http://127.0.0.1:4174',
    trace: 'retain-on-failure',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
  webServer: {
    command: 'VITE_LOCAL_DEMO=true pnpm dev --host 127.0.0.1 --port 4174',
    reuseExistingServer: false,
    timeout: 120_000,
    url: 'http://127.0.0.1:4174',
  },
})
