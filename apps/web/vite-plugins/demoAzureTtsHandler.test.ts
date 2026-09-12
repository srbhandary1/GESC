import { afterEach, describe, expect, it, vi } from 'vitest'
import { EventEmitter } from 'node:events'
import { mkdtempSync, rmSync } from 'node:fs'
import { tmpdir } from 'node:os'
import path from 'node:path'
import {
  handleDemoTtsRequest,
  resolveDemoTtsCredentials,
  synthesizeWithFailover,
} from './demoAzureTtsHandler.mjs'
import { writeDemoTtsSettingsFile } from './demoTtsSettings.mjs'

function createResponse() {
  const headers = new Map<string, string>()
  let body: string | Buffer = ''
  return {
    statusCode: 0,
    headers,
    body: () => (typeof body === 'string' ? body : body.toString()),
    rawBody: () => body,
    setHeader(name: string, value: string) {
      headers.set(name.toLowerCase(), value)
    },
    end(chunk?: string | Buffer) {
      body = chunk === undefined ? '' : chunk
    },
  }
}

function isolateSettings() {
  const directory = mkdtempSync(path.join(tmpdir(), 'ge-demo-tts-'))
  vi.stubEnv('DEMO_TTS_SETTINGS_FILE', path.join(directory, 'settings.json'))
  vi.stubEnv('AZURE_SPEECH_ENDPOINT', '')
  vi.stubEnv('AZURE_SPEECH_KEY', '')
  vi.stubEnv('AZURE_SPEECH_SECRET_FILE', '')
  vi.stubEnv('SARVAM_API_KEY', '')
  vi.stubEnv('SARVAM_API_KEY_FILE', '')
  vi.stubEnv('SARVAM_BASE_URL', '')
  vi.stubEnv('ELEVENLABS_API_KEY', '')
  vi.stubEnv('ELEVENLABS_API_KEY_FILE', '')
  vi.stubEnv('ELEVENLABS_VOICE_ID', '')
  vi.stubEnv('TTS_PROVIDER_ORDER', '')
  vi.stubEnv('DEMO_TTS_ADMIN_TOKEN', '')
  vi.stubEnv('DEMO_TTS_ADMIN_TOKEN_FILE', '')
  return directory
}

function postJson(
  url: string,
  payload: unknown,
  headers: Record<string, string> = {},
) {
  const req = new EventEmitter() as EventEmitter & {
    method: string
    url: string
    headers: Record<string, string>
  }
  req.method = 'POST'
  req.url = url
  req.headers = headers
  const res = createResponse()
  const handled = handleDemoTtsRequest(req as never, res as never)
  queueMicrotask(() => {
    req.emit('data', Buffer.from(JSON.stringify(payload)))
    req.emit('end')
  })
  return { handled, res }
}

afterEach(() => {
  vi.unstubAllEnvs()
  vi.unstubAllGlobals()
  vi.restoreAllMocks()
})

describe('demo multi-provider TTS handler', () => {
  it('reports unconfigured status with provider booleans and no secrets', async () => {
    const directory = isolateSettings()
    const req = { method: 'GET', url: '/demo/tts/status', headers: {} }
    const res = createResponse()

    expect(await handleDemoTtsRequest(req as never, res as never)).toBe(true)
    expect(res.statusCode).toBe(200)
    const body = JSON.parse(res.body())
    expect(body.configured).toBe(false)
    expect(body.providers).toEqual({
      azure: false,
      sarvam: false,
      elevenlabs: false,
    })
    expect(body.order).toEqual({
      mode: 'locale-aware',
      'en-IN': ['sarvam', 'elevenlabs', 'azure'],
      indic: ['sarvam', 'azure', 'elevenlabs'],
    })
    expect(body.region).toBe('centralindia')
    expect(JSON.stringify(body)).not.toMatch(/api[_-]?key|subscription|secret/i)
    expect(res.headers.get('cache-control')).toBe('no-store')
    expect(resolveDemoTtsCredentials()).toBeNull()
    rmSync(directory, { recursive: true, force: true })
  })

  it('rejects synthesis when no neural provider is configured', async () => {
    const directory = isolateSettings()
    const { handled, res } = postJson('/demo/tts', {
      locale: 'en-IN',
      text: 'Hello',
    })

    expect(await handled).toBe(true)
    expect(res.statusCode).toBe(503)
    expect(JSON.parse(res.body()).error).toBe('tts_unconfigured')
    rmSync(directory, { recursive: true, force: true })
  })

  it('fails over from Sarvam to Azure when the first provider errors', async () => {
    isolateSettings()
    const fetchImpl = vi
      .fn()
      .mockResolvedValueOnce({
        ok: false,
        status: 500,
        json: async () => ({}),
      })
      .mockResolvedValueOnce({
        ok: true,
        arrayBuffer: async () => new Uint8Array([1, 2, 3]).buffer,
      })
    vi.stubGlobal('fetch', fetchImpl)

    const result = await synthesizeWithFailover('en-IN', 'Hello learners', {
      azure: {
        endpoint: 'https://example.cognitiveservices.azure.com',
        key: 'azure-test-key',
      },
      sarvam: {
        key: 'sarvam-test-key',
        baseUrl: 'https://api.sarvam.ai',
        speaker: 'shubh',
        model: 'bulbul:v3',
      },
      elevenlabs: null,
      providerOrder: '',
    })

    expect(result.provider).toBe('azure')
    expect(result.voice).toBe('en-IN-NeerjaNeural')
    expect(fetchImpl).toHaveBeenCalledTimes(2)
    expect(String(fetchImpl.mock.calls[0]?.[0])).toContain(
      'https://api.sarvam.ai/text-to-speech',
    )
    expect(String(fetchImpl.mock.calls[1]?.[0])).toContain(
      '/tts/cognitiveservices/v1',
    )
  })

  it('honours an explicit provider order', async () => {
    isolateSettings()
    const fetchImpl = vi.fn().mockResolvedValue({
      ok: true,
      arrayBuffer: async () => new Uint8Array([9]).buffer,
    })
    vi.stubGlobal('fetch', fetchImpl)

    const result = await synthesizeWithFailover('en-IN', 'Hello', {
      azure: {
        endpoint: 'https://example.cognitiveservices.azure.com',
        key: 'azure-test-key',
      },
      sarvam: {
        key: 'sarvam-test-key',
        baseUrl: 'https://api.sarvam.ai',
        speaker: 'shubh',
        model: 'bulbul:v3',
      },
      elevenlabs: {
        key: 'eleven-test-key',
        voiceId: 'voice-fixture',
        modelId: 'eleven_multilingual_v2',
        baseUrl: 'https://api.elevenlabs.io',
      },
      providerOrder: 'azure,elevenlabs,sarvam',
    })

    expect(result.provider).toBe('azure')
    expect(String(fetchImpl.mock.calls[0]?.[0])).toContain(
      'https://example.cognitiveservices.azure.com',
    )
  })

  it('returns 503 for settings when the admin token is unset', async () => {
    const directory = isolateSettings()
    const req = { method: 'GET', url: '/demo/tts/settings', headers: {} }
    const res = createResponse()

    expect(await handleDemoTtsRequest(req as never, res as never)).toBe(true)
    expect(res.statusCode).toBe(503)
    expect(JSON.parse(res.body()).error).toBe('admin_unconfigured')
    rmSync(directory, { recursive: true, force: true })
  })

  it('rejects settings reads without a matching admin token', async () => {
    const directory = isolateSettings()
    vi.stubEnv('DEMO_TTS_ADMIN_TOKEN', 'host-admin-token')
    const req = { method: 'GET', url: '/demo/tts/settings', headers: {} }
    const res = createResponse()

    expect(await handleDemoTtsRequest(req as never, res as never)).toBe(true)
    expect(res.statusCode).toBe(401)
    expect(JSON.parse(res.body()).error).toBe('admin_unauthorized')
    rmSync(directory, { recursive: true, force: true })
  })

  it('masks stored secrets and never echoes the raw key', async () => {
    const directory = isolateSettings()
    vi.stubEnv('DEMO_TTS_ADMIN_TOKEN', 'host-admin-token')
    writeDemoTtsSettingsFile({
      sarvam_api_key: 'sarvam-secret-abcd',
      elevenlabs_api_key: 'eleven-secret-wxyz',
      elevenlabs_voice_id: 'voice-fixture',
    })

    const req = {
      method: 'GET',
      url: '/demo/tts/settings',
      headers: { 'x-demo-tts-admin': 'host-admin-token' },
    }
    const res = createResponse()
    expect(await handleDemoTtsRequest(req as never, res as never)).toBe(true)
    expect(res.statusCode).toBe(200)
    const body = JSON.parse(res.body())
    expect(body.sarvam.api_key).toBe('••••abcd')
    expect(body.elevenlabs.api_key).toBe('••••wxyz')
    expect(JSON.stringify(body)).not.toContain('sarvam-secret-abcd')
    expect(JSON.stringify(body)).not.toContain('eleven-secret-wxyz')
    rmSync(directory, { recursive: true, force: true })
  })

  it('saves settings from an authenticated admin POST', async () => {
    const directory = isolateSettings()
    vi.stubEnv('DEMO_TTS_ADMIN_TOKEN', 'host-admin-token')
    const { handled, res } = postJson(
      '/demo/tts/settings',
      {
        sarvam_api_key: 'sarvam-rotated-key',
        provider_order: 'sarvam,azure,elevenlabs',
      },
      { 'x-demo-tts-admin': 'host-admin-token' },
    )

    expect(await handled).toBe(true)
    expect(res.statusCode).toBe(200)
    const body = JSON.parse(res.body())
    expect(body.saved).toBe(true)
    expect(body.settings.sarvam.configured).toBe(true)
    expect(body.settings.sarvam.api_key).toBe('••••-key')
    expect(JSON.stringify(body)).not.toContain('sarvam-rotated-key')

    const status = createResponse()
    await handleDemoTtsRequest(
      { method: 'GET', url: '/demo/tts/status', headers: {} } as never,
      status as never,
    )
    const statusBody = JSON.parse(status.body())
    expect(statusBody.configured).toBe(true)
    expect(statusBody.providers.sarvam).toBe(true)
    expect(JSON.stringify(statusBody)).not.toContain('sarvam-rotated-key')
    rmSync(directory, { recursive: true, force: true })
  })

  it('ignores unrelated routes', async () => {
    isolateSettings()
    const req = { method: 'GET', url: '/healthz', headers: {} }
    const res = createResponse()
    expect(await handleDemoTtsRequest(req as never, res as never)).toBe(false)
    expect(res.statusCode).toBe(0)
  })
})
