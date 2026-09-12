import type { IncomingMessage, ServerResponse } from 'node:http'
import type { Connect } from 'vite'

export const DEMO_TTS_PATH: '/demo/tts'
export const DEMO_TTS_STATUS_PATH: '/demo/tts/status'
export const DEMO_AZURE_VOICES: {
  readonly 'en-IN': 'en-IN-NeerjaNeural'
  readonly 'hi-IN': 'hi-IN-SwaraNeural'
  readonly 'kn-IN': 'kn-IN-SapnaNeural'
  readonly 'ml-IN': 'ml-IN-SobhanaNeural'
  readonly 'ta-IN': 'ta-IN-PallaviNeural'
  readonly 'te-IN': 'te-IN-ShrutiNeural'
}

export function requestPath(url: string | undefined): string
export function isDemoTtsPath(pathname: string): boolean
export function resolveDemoTtsCredentials(): {
  readonly endpoint: string
  readonly key: string
} | null
export function applyDemoTtsCors(
  res: ServerResponse,
  req: IncomingMessage,
): void
export function handleDemoTtsRequest(
  req: IncomingMessage,
  res: ServerResponse,
): Promise<boolean>
export function attachDemoTtsMiddleware(middlewares: Connect.Server): void
