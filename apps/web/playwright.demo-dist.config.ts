import { defineConfig, devices } from '@playwright/test'
import { playwrightOutputDir } from './playwrightOutput'

// Runs the same disclosed synthetic demo journey against the built
// `dist-demo` distribution artifact served by `vite preview`, proving the
// static bundle that ships to demo hosting and the Android wrapper works
// without a development server or any API.
export default defineConfig({
  testDir: './e2e-demo',
  outputDir: playwrightOutputDir('demo-dist'),
  fullyParallel: false,
  forbidOnly: true,
  retries: 0,
  workers: 1,
  reporter: 'list',
  use: {
    baseURL: 'http://127.0.0.1:4175',
    trace: 'retain-on-failure',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
  webServer: {
    command: 'pnpm preview:demo',
    reuseExistingServer: false,
    timeout: 120_000,
    url: 'http://127.0.0.1:4175',
  },
})
