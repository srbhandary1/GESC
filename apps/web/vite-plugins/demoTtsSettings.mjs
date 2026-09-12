import { createHash, timingSafeEqual } from 'node:crypto'
import {
  chmodSync,
  existsSync,
  mkdirSync,
  readFileSync,
  renameSync,
  writeFileSync,
} from 'node:fs'
import { homedir } from 'node:os'
import path from 'node:path'

export const DEMO_TTS_PROVIDERS = ['sarvam', 'elevenlabs', 'azure']
export const DEMO_TTS_SETTINGS_PATH = '/demo/tts/settings'
export const CLEAR_SECRET = '__clear__'
const SARVAM_ALLOWED_HOSTS = new Set(['api.sarvam.ai'])
const ELEVENLABS_ALLOWED_HOSTS = new Set(['api.elevenlabs.io'])

export function demoTtsSettingsFilePath() {
  const configured = (process.env.DEMO_TTS_SETTINGS_FILE ?? '').trim()
  if (configured !== '') {
    return configured
  }

  return path.join(homedir(), 'ge-demo-tts', 'settings.json')
}

function trimString(value) {
  return typeof value === 'string' ? value.trim() : ''
}

export function readSecretFile(filePath) {
  const resolved = trimString(filePath)
  if (resolved === '') {
    return ''
  }

  try {
    return readFileSync(resolved, 'utf8').trim()
  } catch {
    return ''
  }
}

export function readDemoTtsSettingsFile() {
  const filePath = demoTtsSettingsFilePath()
  if (!existsSync(filePath)) {
    return {}
  }

  try {
    const parsed = JSON.parse(readFileSync(filePath, 'utf8'))
    return parsed !== null && typeof parsed === 'object' && !Array.isArray(parsed)
      ? parsed
      : {}
  } catch {
    return {}
  }
}

export function writeDemoTtsSettingsFile(next) {
  const filePath = demoTtsSettingsFilePath()
  const directory = path.dirname(filePath)
  mkdirSync(directory, { recursive: true, mode: 0o700 })
  chmodSync(directory, 0o700)
  const temporary = `${filePath}.${process.pid}.tmp`
  writeFileSync(temporary, `${JSON.stringify(next, null, 2)}\n`, {
    encoding: 'utf8',
    mode: 0o600,
  })
  chmodSync(temporary, 0o600)
  renameSync(temporary, filePath)
  chmodSync(filePath, 0o600)
}

function firstNonEmpty(...values) {
  for (const value of values) {
    const trimmed = trimString(value)
    if (trimmed !== '') {
      return trimmed
    }
  }

  return ''
}

function allowlistedHttpsUrl(raw, allowedHosts, fallback) {
  for (const candidate of [trimString(raw), fallback]) {
    if (candidate === '') {
      continue
    }

    try {
      const url = new URL(candidate)
      if (url.protocol === 'https:' && allowedHosts.has(url.hostname)) {
        return url.origin
      }
    } catch {
      // Try the next candidate.
    }
  }

  return ''
}

export function maskSecret(value) {
  const trimmed = trimString(value)
  if (trimmed === '') {
    return ''
  }
  if (trimmed.length <= 4) {
    return '••••'
  }

  return `••••${trimmed.slice(-4)}`
}

export function parseProviderOrder(value) {
  const parts = trimString(value)
    .split(/[,\s]+/u)
    .map((part) => part.trim().toLowerCase())
    .filter((part) => DEMO_TTS_PROVIDERS.includes(part))

  return [...new Set(parts)]
}

export function defaultProviderOrder(locale) {
  if (locale === 'en-IN') {
    return ['sarvam', 'elevenlabs', 'azure']
  }

  return ['sarvam', 'azure', 'elevenlabs']
}

export function resolveProviderOrder(locale, explicitOrder) {
  const parsed = parseProviderOrder(explicitOrder)
  return parsed.length > 0 ? parsed : defaultProviderOrder(locale)
}

export function resolveDemoTtsBundle() {
  const stored = readDemoTtsSettingsFile()
  const azureEndpoint = firstNonEmpty(
    process.env.AZURE_SPEECH_ENDPOINT,
    stored.azure_speech_endpoint,
  ).replace(/\/$/u, '')
  const azureKey = firstNonEmpty(
    process.env.AZURE_SPEECH_KEY,
    readSecretFile(process.env.AZURE_SPEECH_SECRET_FILE),
    stored.azure_speech_key,
  )
  const sarvamKey = firstNonEmpty(
    process.env.SARVAM_API_KEY,
    readSecretFile(process.env.SARVAM_API_KEY_FILE),
    stored.sarvam_api_key,
  )
  const sarvamBaseUrl = allowlistedHttpsUrl(
    firstNonEmpty(process.env.SARVAM_BASE_URL, stored.sarvam_base_url),
    SARVAM_ALLOWED_HOSTS,
    'https://api.sarvam.ai',
  )
  const elevenKey = firstNonEmpty(
    process.env.ELEVENLABS_API_KEY,
    readSecretFile(process.env.ELEVENLABS_API_KEY_FILE),
    stored.elevenlabs_api_key,
  )
  const elevenVoiceId = firstNonEmpty(
    process.env.ELEVENLABS_VOICE_ID,
    stored.elevenlabs_voice_id,
  )
  const elevenBaseUrl = allowlistedHttpsUrl(
    firstNonEmpty(process.env.ELEVENLABS_BASE_URL, stored.elevenlabs_base_url),
    ELEVENLABS_ALLOWED_HOSTS,
    'https://api.elevenlabs.io',
  )

  return {
    azure:
      azureEndpoint !== '' && azureKey !== ''
        ? { endpoint: azureEndpoint, key: azureKey }
        : null,
    sarvam:
      sarvamKey !== '' && sarvamBaseUrl !== ''
        ? {
            key: sarvamKey,
            baseUrl: sarvamBaseUrl,
            speaker: firstNonEmpty(
              process.env.SARVAM_SPEAKER,
              stored.sarvam_speaker,
              'shubh',
            ).toLowerCase(),
            model: firstNonEmpty(
              process.env.SARVAM_MODEL,
              stored.sarvam_model,
              'bulbul:v3',
            ),
          }
        : null,
    elevenlabs:
      elevenKey !== '' && elevenVoiceId !== '' && elevenBaseUrl !== ''
        ? {
            key: elevenKey,
            voiceId: elevenVoiceId,
            modelId: firstNonEmpty(
              process.env.ELEVENLABS_MODEL_ID,
              stored.elevenlabs_model_id,
              'eleven_multilingual_v2',
            ),
            baseUrl: elevenBaseUrl,
          }
        : null,
    providerOrder: firstNonEmpty(
      process.env.TTS_PROVIDER_ORDER,
      stored.tts_provider_order,
    ),
  }
}

export function neuralProvidersConfigured(bundle = resolveDemoTtsBundle()) {
  return {
    azure: bundle.azure !== null,
    sarvam: bundle.sarvam !== null,
    elevenlabs: bundle.elevenlabs !== null,
  }
}

export function anyNeuralConfigured(bundle = resolveDemoTtsBundle()) {
  const flags = neuralProvidersConfigured(bundle)
  return flags.azure || flags.sarvam || flags.elevenlabs
}

export function resolveAdminToken() {
  return firstNonEmpty(
    process.env.DEMO_TTS_ADMIN_TOKEN,
    readSecretFile(process.env.DEMO_TTS_ADMIN_TOKEN_FILE),
  )
}

export function adminTokenMatches(provided) {
  const expected = resolveAdminToken()
  const received = trimString(provided)
  if (expected === '' || received === '') {
    return false
  }

  const left = Buffer.from(expected)
  const right = Buffer.from(received)
  if (left.length !== right.length) {
    return false
  }

  return timingSafeEqual(left, right)
}

export function readAdminTokenFromRequest(req, payload = {}) {
  const header =
    req.headers?.['x-demo-tts-admin'] ?? req.headers?.['X-Demo-Tts-Admin']
  if (typeof header === 'string' && header.trim() !== '') {
    return header.trim()
  }

  const authorization = req.headers?.authorization ?? req.headers?.Authorization
  if (typeof authorization === 'string' && authorization.startsWith('Bearer ')) {
    return authorization.slice(7).trim()
  }

  return trimString(payload.admin_token)
}

function applyField(target, key, incoming) {
  if (incoming === undefined) {
    return
  }
  if (incoming === CLEAR_SECRET) {
    delete target[key]
    return
  }
  const trimmed = trimString(incoming)
  if (trimmed === '') {
    return
  }
  target[key] = trimmed
}

export function mergeSettingsUpdate(current, payload) {
  const next = { ...current }
  applyField(next, 'azure_speech_endpoint', payload.azure_endpoint)
  applyField(next, 'azure_speech_key', payload.azure_key)
  applyField(next, 'sarvam_api_key', payload.sarvam_api_key)
  applyField(next, 'sarvam_base_url', payload.sarvam_base_url)
  applyField(next, 'sarvam_speaker', payload.sarvam_speaker)
  applyField(next, 'sarvam_model', payload.sarvam_model)
  applyField(next, 'elevenlabs_api_key', payload.elevenlabs_api_key)
  applyField(next, 'elevenlabs_voice_id', payload.elevenlabs_voice_id)
  applyField(next, 'elevenlabs_model_id', payload.elevenlabs_model_id)
  applyField(next, 'tts_provider_order', payload.provider_order)
  return next
}

export function maskedSettingsView() {
  const stored = readDemoTtsSettingsFile()
  const bundle = resolveDemoTtsBundle()

  return {
    azure: {
      endpoint: bundle.azure?.endpoint ?? '',
      key: maskSecret(bundle.azure?.key ?? ''),
      configured: bundle.azure !== null,
      from_env: trimString(process.env.AZURE_SPEECH_ENDPOINT) !== '',
    },
    sarvam: {
      base_url: bundle.sarvam?.baseUrl ?? '',
      speaker: bundle.sarvam?.speaker ?? '',
      model: bundle.sarvam?.model ?? '',
      api_key: maskSecret(bundle.sarvam?.key ?? ''),
      configured: bundle.sarvam !== null,
    },
    elevenlabs: {
      voice_id: bundle.elevenlabs?.voiceId ?? '',
      model_id: bundle.elevenlabs?.modelId ?? '',
      api_key: maskSecret(bundle.elevenlabs?.key ?? ''),
      configured: bundle.elevenlabs !== null,
    },
    provider_order: bundle.providerOrder,
    stored_fields: Object.keys(stored).sort(),
    settings_fingerprint: createHash('sha256')
      .update(JSON.stringify(Object.keys(stored).sort()))
      .digest('hex')
      .slice(0, 12),
  }
}
