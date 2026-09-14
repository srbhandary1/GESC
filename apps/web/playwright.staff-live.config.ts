import { defineConfig, devices } from '@playwright/test'

export default defineConfig({
  testDir: './e2e',
  testMatch: 'staff-closed-beta-live.spec.ts',
  fullyParallel: false,
  retries: 0,
  workers: 1,
  reporter: 'list',
  timeout: 90_000,
  use: {
    baseURL: process.env.GE_STAFF_API_ORIGIN ?? 'http://117.254.108.143:8080',
    trace: 'off',
    viewport: { width: 390, height: 844 },
  },
  projects: [
    {
      name: 'chromium',
      use: devices['Pixel 7'],
    },
  ],
})
