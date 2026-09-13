import type { Plugin } from 'vite'
import {
  attachDemoTtsMiddleware,
  DEMO_AZURE_VOICES,
} from './demoAzureTtsHandler.mjs'

/**
 * Demo-only multi-provider TTS middleware for local presentation
 * (`make demo`) and the same handler used by the static demo-client
 * server. Secrets stay in process env / the host settings file — never
 * exposed to the browser or a VITE_* bundle. Does not amend the PR-009
 * ordinary app Azure binding or benchmark allowlist.
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
