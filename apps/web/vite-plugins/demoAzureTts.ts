import { readFileSync } from 'node:fs'
import type { Connect, Plugin } from 'vite'

/**
 * Demo-only Azure TTS middleware for local presentation (`make demo`).
 * Secrets stay in process env / secret files — never exposed to the browser.
 * Does not amend the PR-009 ordinary app Azure binding or benchmark allowlist.
 */

const DEMO_TTS_PATH = '/demo/tts'
const DEMO_TTS_STATUS_PATH = '/demo/tts/status'
const MAXIMUM_CHARACTERS = 500
const TTS_ROUTE = '/tts/cognitiveservices/v1'
const OUTPUT_FORMAT = 'riff-24khz-16bit-mono-pcm'

export const DEMO_AZURE_VOICES = {
  'en-IN': 'en-IN-NeerjaNeural',
  'hi-IN': 'hi-IN-SwaraNeural',
  'kn-IN': 'kn-IN-SapnaNeural',
  'ml-IN': 'ml-IN-SobhanaNeural',
  'ta-IN': 'ta-IN-PallaviNeural',
  'te-IN': 'te-IN-ShrutiNeural',
} as const

export type DemoAzureLocale = keyof typeof DEMO_AZURE_VOICES

interface DemoTtsCredentials {
  readonly endpoint: string
  readonly key: string
}

function readBody(req: Connect.IncomingMessage): Promise<string> {
  return new Promise((resolve, reject) => {
    const chunks: Buffer[] = []
    req.on('data', (chunk: Buffer) => {
      chunks.push(chunk)
      const total = chunks.reduce((sum, part) => sum + part.length, 0)
      if (total > 16_384) {
        reject(new Error('request_too_large'))
        req.destroy()
      }
    })
    req.on('end', () => resolve(Buffer.concat(chunks).toString('utf8')))
    req.on('error', reject)
  })
}

function resolveCredentials(): DemoTtsCredentials | null {
  const endpoint = (process.env.AZURE_SPEECH_ENDPOINT ?? '').trim().replace(/\/$/u, '')
  if (endpoint === '') {
    return null
  }

  const inlineKey = (process.env.AZURE_SPEECH_KEY ?? '').trim()
  if (inlineKey !== '') {
    return { endpoint, key: inlineKey }
  }

  const secretFile = (process.env.AZURE_SPEECH_SECRET_FILE ?? '').trim()
  if (secretFile === '') {
    return null
  }

  try {
    const key = readFileSync(secretFile, 'utf8').trim()
    if (key === '') {
      return null
    }
    return { endpoint, key }
  } catch {
    return null
  }
}

function isDemoLocale(value: string): value is DemoAzureLocale {
  return Object.hasOwn(DEMO_AZURE_VOICES, value)
}

function escapeXml(value: string): string {
  return value
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&apos;')
}

function sendJson(
  res: Connect.ServerResponse,
  status: number,
  body: Record<string, unknown>,
): void {
  res.statusCode = status
  res.setHeader('Content-Type', 'application/json; charset=utf-8')
  res.setHeader('Cache-Control', 'no-store')
  res.end(JSON.stringify(body))
}

async function synthesizeAzure(
  credentials: DemoTtsCredentials,
  locale: DemoAzureLocale,
  text: string,
): Promise<ArrayBuffer> {
  const voice = DEMO_AZURE_VOICES[locale]
  const ssml =
    `<speak version="1.0" xml:lang="${escapeXml(locale)}">` +
    `<voice name="${escapeXml(voice)}">${escapeXml(text)}</voice>` +
    `</speak>`

  const response = await fetch(`${credentials.endpoint}${TTS_ROUTE}`, {
    method: 'POST',
    headers: {
      'Ocp-Apim-Subscription-Key': credentials.key,
      'Content-Type': 'application/ssml+xml',
      'X-Microsoft-OutputFormat': OUTPUT_FORMAT,
      Accept: 'audio/wav',
      'User-Agent': 'global-spoken-english-demo-tts',
    },
    body: ssml,
  })

  if (!response.ok) {
    throw new Error(`azure_http_${response.status}`)
  }

  return response.arrayBuffer()
}

function attachDemoTtsMiddleware(middlewares: Connect.Server): void {
  middlewares.use(async (req, res, next) => {
    const url = req.url?.split('?')[0] ?? ''

    if (url === DEMO_TTS_STATUS_PATH && req.method === 'GET') {
      const credentials = resolveCredentials()
      sendJson(res, 200, {
        configured: credentials !== null,
        region: 'centralindia',
        voices: DEMO_AZURE_VOICES,
      })
      return
    }

    if (url !== DEMO_TTS_PATH || req.method !== 'POST') {
      next()
      return
    }

    const credentials = resolveCredentials()
    if (credentials === null) {
      sendJson(res, 503, {
        error: 'azure_tts_unconfigured',
        message:
          'Set AZURE_SPEECH_ENDPOINT and AZURE_SPEECH_KEY or AZURE_SPEECH_SECRET_FILE for demo TTS.',
      })
      return
    }

    let payload: { locale?: unknown; text?: unknown }
    try {
      payload = JSON.parse(await readBody(req)) as {
        locale?: unknown
        text?: unknown
      }
    } catch {
      sendJson(res, 400, { error: 'invalid_json' })
      return
    }

    const locale =
      typeof payload.locale === 'string' ? payload.locale.trim() : ''
    const text = typeof payload.text === 'string' ? payload.text.trim() : ''

    if (!isDemoLocale(locale)) {
      sendJson(res, 400, { error: 'unsupported_locale' })
      return
    }

    if (
      text === '' ||
      [...text].length > MAXIMUM_CHARACTERS ||
      /[\u0000-\u0008\u000B\u000C\u000E-\u001F\u007F]/u.test(text)
    ) {
      sendJson(res, 400, { error: 'invalid_text' })
      return
    }

    try {
      const audio = await synthesizeAzure(credentials, locale, text)
      // eslint-disable-next-line no-console -- intentional demo spend telemetry
      console.info(
        `[demo-tts] locale=${locale} voice=${DEMO_AZURE_VOICES[locale]} chars=${[...text].length}`,
      )
      res.statusCode = 200
      res.setHeader('Content-Type', 'audio/wav')
      res.setHeader('Cache-Control', 'no-store')
      res.setHeader('X-Demo-Tts-Voice', DEMO_AZURE_VOICES[locale])
      res.end(Buffer.from(audio))
    } catch (error) {
      const detail = error instanceof Error ? error.message : 'azure_failed'
      // eslint-disable-next-line no-console -- intentional demo diagnostics
      console.warn(`[demo-tts] synthesis failed: ${detail}`)
      sendJson(res, 502, { error: 'azure_tts_failed', detail })
    }
  })
}

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
