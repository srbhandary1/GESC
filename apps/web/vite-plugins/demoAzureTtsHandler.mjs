import {
  adminTokenMatches,
  anyNeuralConfigured,
  DEMO_TTS_SETTINGS_PATH,
  defaultProviderOrder,
  maskedSettingsView,
  mergeSettingsUpdate,
  neuralProvidersConfigured,
  readAdminTokenFromRequest,
  readDemoTtsSettingsFile,
  resolveAdminToken,
  resolveDemoTtsBundle,
  resolveProviderOrder,
  writeDemoTtsSettingsFile,
} from './demoTtsSettings.mjs'

/**
 * Demo TTS for Vite middleware and the static demo-client server.
 * Azure, Sarvam and ElevenLabs stay in process env / the host settings
 * file — never in the browser bundle.
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
  return (
    pathname === DEMO_TTS_PATH ||
    pathname === DEMO_TTS_STATUS_PATH ||
    pathname === DEMO_TTS_SETTINGS_PATH
  )
}

export function resolveDemoTtsCredentials() {
  return resolveDemoTtsBundle().azure
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
  res.setHeader(
    'Access-Control-Allow-Headers',
    'Accept, Content-Type, Authorization, X-Demo-Tts-Admin',
  )
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

  return {
    audio: await response.arrayBuffer(),
    contentType: 'audio/wav',
    voice,
  }
}

async function synthesizeSarvam(credentials, locale, text) {
  const response = await fetch(`${credentials.baseUrl}/text-to-speech`, {
    method: 'POST',
    headers: {
      'api-subscription-key': credentials.key,
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'User-Agent': 'global-spoken-english-demo-tts',
    },
    body: JSON.stringify({
      text,
      target_language_code: locale,
      speaker: credentials.speaker,
      model: credentials.model,
    }),
  })

  if (!response.ok) {
    throw new Error(`sarvam_http_${response.status}`)
  }

  const body = (await response.json())
  const encoded = Array.isArray(body.audios) ? body.audios[0] : null
  if (typeof encoded !== 'string' || encoded === '') {
    throw new Error('sarvam_empty')
  }

  return {
    audio: Buffer.from(encoded, 'base64'),
    contentType: 'audio/mpeg',
    voice: credentials.speaker,
  }
}

async function synthesizeElevenLabs(credentials, text) {
  const url =
    `${credentials.baseUrl}/v1/text-to-speech/${encodeURIComponent(credentials.voiceId)}` +
    '?output_format=mp3_44100_128'
  const response = await fetch(url, {
    method: 'POST',
    headers: {
      'xi-api-key': credentials.key,
      'Content-Type': 'application/json',
      Accept: 'audio/mpeg',
      'User-Agent': 'global-spoken-english-demo-tts',
    },
    body: JSON.stringify({
      text,
      model_id: credentials.modelId,
    }),
  })

  if (!response.ok) {
    throw new Error(`elevenlabs_http_${response.status}`)
  }

  const audio = await response.arrayBuffer()
  if (audio.byteLength === 0) {
    throw new Error('elevenlabs_empty')
  }

  return {
    audio,
    contentType: 'audio/mpeg',
    voice: credentials.voiceId,
  }
}

export async function synthesizeWithFailover(locale, text, bundle) {
  const order = resolveProviderOrder(locale, bundle.providerOrder)
  const failures = []

  for (const provider of order) {
    if (provider === 'sarvam' && bundle.sarvam !== null) {
      try {
        const result = await synthesizeSarvam(bundle.sarvam, locale, text)
        return { ...result, provider }
      } catch (error) {
        failures.push(
          error instanceof Error ? error.message : 'sarvam_failed',
        )
      }
    }

    if (provider === 'elevenlabs' && bundle.elevenlabs !== null) {
      try {
        const result = await synthesizeElevenLabs(bundle.elevenlabs, text)
        return { ...result, provider }
      } catch (error) {
        failures.push(
          error instanceof Error ? error.message : 'elevenlabs_failed',
        )
      }
    }

    if (provider === 'azure' && bundle.azure !== null) {
      try {
        const result = await synthesizeAzure(bundle.azure, locale, text)
        return { ...result, provider }
      } catch (error) {
        failures.push(error instanceof Error ? error.message : 'azure_failed')
      }
    }
  }

  const error = new Error('all_tts_providers_failed')
  error.failures = failures
  throw error
}

function statusPayload() {
  const bundle = resolveDemoTtsBundle()
  const providers = neuralProvidersConfigured(bundle)

  return {
    configured: anyNeuralConfigured(bundle),
    providers,
    order: bundle.providerOrder === ''
      ? { mode: 'locale-aware', 'en-IN': defaultProviderOrder('en-IN'), indic: defaultProviderOrder('hi-IN') }
      : { mode: 'explicit', value: resolveProviderOrder('en-IN', bundle.providerOrder) },
    region: 'centralindia',
    voices: DEMO_AZURE_VOICES,
  }
}

async function handleSettings(req, res, method) {
  if (resolveAdminToken() === '') {
    sendJson(res, req, 503, {
      error: 'admin_unconfigured',
      message:
        'Set DEMO_TTS_ADMIN_TOKEN or DEMO_TTS_ADMIN_TOKEN_FILE on the demo host before using Admin voice settings.',
    })
    return
  }

  if (method === 'GET') {
    if (!adminTokenMatches(readAdminTokenFromRequest(req))) {
      sendJson(res, req, 401, { error: 'admin_unauthorized' })
      return
    }

    sendJson(res, req, 200, maskedSettingsView())
    return
  }

  if (method !== 'POST') {
    applyDemoTtsCors(res, req)
    res.statusCode = 405
    res.setHeader('Allow', 'GET, POST, OPTIONS')
    res.end()
    return
  }

  let payload
  try {
    payload = JSON.parse(await readBody(req))
  } catch {
    sendJson(res, req, 400, { error: 'invalid_json' })
    return
  }

  if (!adminTokenMatches(readAdminTokenFromRequest(req, payload))) {
    sendJson(res, req, 401, { error: 'admin_unauthorized' })
    return
  }

  const next = mergeSettingsUpdate(readDemoTtsSettingsFile(), payload)
  writeDemoTtsSettingsFile(next)
  sendJson(res, req, 200, {
    saved: true,
    settings: maskedSettingsView(),
  })
}

/**
 * Handle `/demo/tts`, `/demo/tts/status` and `/demo/tts/settings`.
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

  if (url === DEMO_TTS_SETTINGS_PATH) {
    await handleSettings(req, res, method)
    return true
  }

  if (url === DEMO_TTS_STATUS_PATH && method === 'GET') {
    sendJson(res, req, 200, statusPayload())
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

  const bundle = resolveDemoTtsBundle()
  if (!anyNeuralConfigured(bundle)) {
    sendJson(res, req, 503, {
      error: 'tts_unconfigured',
      message:
        'Configure Sarvam, ElevenLabs or Azure on the demo host (env or Admin voice settings).',
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
    // eslint-disable-next-line no-control-regex -- reject C0 controls in TTS text
    /[\u0000-\u0008\u000B\u000C\u000E-\u001F\u007F]/u.test(text)
  ) {
    sendJson(res, req, 400, { error: 'invalid_text' })
    return true
  }

  try {
    const result = await synthesizeWithFailover(locale, text, bundle)
    console.info(
      `[demo-tts] provider=${result.provider} locale=${locale} voice=${result.voice} chars=${[...text].length}`,
    )
    applyDemoTtsCors(res, req)
    res.statusCode = 200
    res.setHeader('Content-Type', result.contentType)
    res.setHeader('Cache-Control', 'no-store')
    res.setHeader('X-Demo-Tts-Provider', result.provider)
    res.setHeader('X-Demo-Tts-Voice', result.voice)
    res.end(Buffer.from(result.audio))
  } catch (error) {
    const detail = error instanceof Error ? error.message : 'tts_failed'
    const failures = error instanceof Error ? error.failures : undefined
    console.warn(`[demo-tts] synthesis failed: ${detail}`)
    sendJson(res, req, 502, {
      error: 'tts_failed',
      detail,
      failures: Array.isArray(failures) ? failures : [],
    })
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
