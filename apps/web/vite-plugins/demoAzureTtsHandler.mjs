import { readFileSync } from 'node:fs'

/**
 * Shared demo Azure TTS handler for Vite middleware and the static
 * demo-client server. Credentials stay in process env / secret files.
 */

export const DEMO_TTS_PATH = '/demo/tts'
export const DEMO_TTS_STATUS_PATH = '/demo/tts/status'
export const DEMO_AZURE_VOICES = {
  'en-IN': 'en-IN-NeerjaNeural',
  'hi-IN': 'hi-IN-SwaraNeural',
  'kn-IN': 'kn-IN-SapnaNeural',
  'ml-IN': 'ml-IN-SobhanaNeural',
  'ta-IN': 'ta-IN-PallaviNeural',
  'te-IN': 'te-IN-ShrutiNeural',
}
const MAXIMUM_CHARACTERS = 500
const TTS_ROUTE = '/tts/cognitiveservices/v1'
const OUTPUT_FORMAT = 'riff-24khz-16bit-mono-pcm'

export function requestPath(url) {
  const raw = url ?? ''
  return raw.split('?')[0] ?? ''
}

export function isDemoTtsPath(pathname) {
  return pathname === DEMO_TTS_PATH || pathname === DEMO_TTS_STATUS_PATH
}

export function resolveDemoTtsCredentials() {
  const endpoint = (process.env.AZURE_SPEECH_ENDPOINT ?? '')
    .trim()
    .replace(/\/$/u, '')
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

function isDemoLocale(value) {
  return Object.hasOwn(DEMO_AZURE_VOICES, value)
}

function escapeXml(value) {
  return value
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&apos;')
}

export function applyDemoTtsCors(res, req) {
  const origin = req.headers?.origin
  res.setHeader(
    'Access-Control-Allow-Origin',
    typeof origin === 'string' && origin !== '' ? origin : '*',
  )
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
  res.setHeader('Access-Control-Allow-Headers', 'Accept, Content-Type')
  res.setHeader('Vary', 'Origin')
}

function sendJson(res, req, status, body) {
  applyDemoTtsCors(res, req)
  res.statusCode = status
  res.setHeader('Content-Type', 'application/json; charset=utf-8')
  res.setHeader('Cache-Control', 'no-store')
  res.end(JSON.stringify(body))
}

function readBody(req) {
  return new Promise((resolve, reject) => {
    const chunks = []
    req.on('data', (chunk) => {
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

async function synthesizeAzure(credentials, locale, text) {
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

/**
 * Handle `/demo/tts` and `/demo/tts/status`. Returns true when the request
 * was consumed so the caller must not write another response.
 */
export async function handleDemoTtsRequest(req, res) {
  const url = requestPath(req.url)
  const method = req.method ?? 'GET'

  if (!isDemoTtsPath(url)) {
    return false
  }

  if (method === 'OPTIONS') {
    applyDemoTtsCors(res, req)
    res.statusCode = 204
    res.setHeader('Cache-Control', 'no-store')
    res.end()
    return true
  }

  if (url === DEMO_TTS_STATUS_PATH && method === 'GET') {
    const credentials = resolveDemoTtsCredentials()
    sendJson(res, req, 200, {
      configured: credentials !== null,
      region: 'centralindia',
      voices: DEMO_AZURE_VOICES,
    })
    return true
  }

  if (url !== DEMO_TTS_PATH || method !== 'POST') {
    applyDemoTtsCors(res, req)
    res.statusCode = 405
    res.setHeader('Allow', 'GET, POST, OPTIONS')
    res.setHeader('Cache-Control', 'no-store')
    res.end()
    return true
  }

  const credentials = resolveDemoTtsCredentials()
  if (credentials === null) {
    sendJson(res, req, 503, {
      error: 'azure_tts_unconfigured',
      message:
        'Set AZURE_SPEECH_ENDPOINT and AZURE_SPEECH_KEY or AZURE_SPEECH_SECRET_FILE for demo TTS.',
    })
    return true
  }

  let payload
  try {
    payload = JSON.parse(await readBody(req))
  } catch {
    sendJson(res, req, 400, { error: 'invalid_json' })
    return true
  }

  const locale = typeof payload.locale === 'string' ? payload.locale.trim() : ''
  const text = typeof payload.text === 'string' ? payload.text.trim() : ''

  if (!isDemoLocale(locale)) {
    sendJson(res, req, 400, { error: 'unsupported_locale' })
    return true
  }

  if (
    text === '' ||
    [...text].length > MAXIMUM_CHARACTERS ||
    /[\u0000-\u0008\u000B\u000C\u000E-\u001F\u007F]/u.test(text)
  ) {
    sendJson(res, req, 400, { error: 'invalid_text' })
    return true
  }

  try {
    const audio = await synthesizeAzure(credentials, locale, text)
    // eslint-disable-next-line no-console -- intentional demo spend telemetry
    console.info(
      `[demo-tts] locale=${locale} voice=${DEMO_AZURE_VOICES[locale]} chars=${[...text].length}`,
    )
    applyDemoTtsCors(res, req)
    res.statusCode = 200
    res.setHeader('Content-Type', 'audio/wav')
    res.setHeader('Cache-Control', 'no-store')
    res.setHeader('X-Demo-Tts-Voice', DEMO_AZURE_VOICES[locale])
    res.end(Buffer.from(audio))
  } catch (error) {
    const detail = error instanceof Error ? error.message : 'azure_failed'
    // eslint-disable-next-line no-console -- intentional demo diagnostics
    console.warn(`[demo-tts] synthesis failed: ${detail}`)
    sendJson(res, req, 502, { error: 'azure_tts_failed', detail })
  }

  return true
}

export function attachDemoTtsMiddleware(middlewares) {
  middlewares.use(async (req, res, next) => {
    if (await handleDemoTtsRequest(req, res)) {
      return
    }
    next()
  })
}
