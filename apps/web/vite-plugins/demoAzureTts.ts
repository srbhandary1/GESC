import type { Plugin } from 'vite'
import {
  attachDemoTtsMiddleware,
  DEMO_AZURE_VOICES,
} from './demoAzureTtsHandler.mjs'

/**
 * Demo-only Azure TTS middleware for local presentation (`make demo`)
 * and the same handler used by the static demo-client server.
 * Secrets stay in process env / secret files — never exposed to the browser.
 * Does not amend the PR-009 ordinary app Azure binding or benchmark allowlist.
 */

export { DEMO_AZURE_VOICES }

export function demoAzureTtsPlugin(): Plugin {
  return {
    name: 'demo-azure-tts',
    configureServer(server) {
      attachDemoTtsMiddleware(server.middlewares)
    },
    configurePreviewServer(server) {
      attachDemoTtsMiddleware(server.middlewares)
    },
  }
}
