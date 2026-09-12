import { afterEach, describe, expect, it, vi } from 'vitest'
import { EventEmitter } from 'node:events'
import {
  handleDemoTtsRequest,
  resolveDemoTtsCredentials,
} from './demoAzureTtsHandler.mjs'

function createResponse() {
  const headers = new Map<string, string>()
  let body = ''
  return {
    statusCode: 0,
    headers,
    body: () => body,
    setHeader(name: string, value: string) {
      headers.set(name.toLowerCase(), value)
    },
    end(chunk?: string | Buffer) {
      body = chunk === undefined ? '' : chunk.toString()
    },
  }
}

afterEach(() => {
  vi.unstubAllEnvs()
})

describe('demo Azure TTS handler', () => {
  it('reports unconfigured status without exposing secrets', async () => {
    vi.stubEnv('AZURE_SPEECH_ENDPOINT', '')
    vi.stubEnv('AZURE_SPEECH_KEY', '')
    vi.stubEnv('AZURE_SPEECH_SECRET_FILE', '')

    const req = { method: 'GET', url: '/demo/tts/status', headers: {} }
    const res = createResponse()

    expect(await handleDemoTtsRequest(req as never, res as never)).toBe(true)
    expect(res.statusCode).toBe(200)
    expect(JSON.parse(res.body())).toEqual({
      configured: false,
      region: 'centralindia',
      voices: {
        'en-IN': 'en-IN-NeerjaNeural',
        'hi-IN': 'hi-IN-SwaraNeural',
        'kn-IN': 'kn-IN-SapnaNeural',
        'ml-IN': 'ml-IN-SobhanaNeural',
        'ta-IN': 'ta-IN-PallaviNeural',
        'te-IN': 'te-IN-ShrutiNeural',
      },
    })
    expect(res.headers.get('cache-control')).toBe('no-store')
    expect(resolveDemoTtsCredentials()).toBeNull()
  })

  it('rejects synthesis when Azure credentials are missing', async () => {
    vi.stubEnv('AZURE_SPEECH_ENDPOINT', '')
    vi.stubEnv('AZURE_SPEECH_KEY', '')
    const req = new EventEmitter() as EventEmitter & {
      method: string
      url: string
      headers: Record<string, string>
    }
    req.method = 'POST'
    req.url = '/demo/tts'
    req.headers = {}
    const res = createResponse()

    const handled = handleDemoTtsRequest(req as never, res as never)
    queueMicrotask(() => {
      req.emit('data', Buffer.from('{"locale":"en-IN","text":"Hello"}'))
      req.emit('end')
    })

    expect(await handled).toBe(true)
    expect(res.statusCode).toBe(503)
    expect(JSON.parse(res.body()).error).toBe('azure_tts_unconfigured')
  })

  it('ignores unrelated routes', async () => {
    const req = { method: 'GET', url: '/healthz', headers: {} }
    const res = createResponse()
    expect(await handleDemoTtsRequest(req as never, res as never)).toBe(false)
    expect(res.statusCode).toBe(0)
  })
})
