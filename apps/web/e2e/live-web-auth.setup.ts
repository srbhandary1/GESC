import { test as setup } from '@playwright/test'
import { installWorkingMicrophone } from './helpers/liveMicrophone'
import { signInWithPassword, useEnglishLocale } from './helpers/liveWebAuth'

const username = process.env.LIVE_WEB_USERNAME ?? process.env.MOBILE_E2E_USERNAME
const password = process.env.LIVE_WEB_PASSWORD ?? process.env.MOBILE_E2E_PASSWORD
const storagePath = '.playwright-output-live-web/northstar-session.json'

setup('authenticate northstar', async ({ page }) => {
  if (username === undefined || password === undefined) {
    setup.skip(true, 'Missing LIVE_WEB_USERNAME/LIVE_WEB_PASSWORD')
  }

  await installWorkingMicrophone(page)
  await signInWithPassword(page, username!, password!)
  await useEnglishLocale(page)
  await page.context().storageState({ path: storagePath })
})
