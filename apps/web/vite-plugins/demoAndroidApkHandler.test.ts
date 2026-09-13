import { describe, expect, it } from 'vitest'
import { mkdtempSync } from 'node:fs'
import { tmpdir } from 'node:os'
import path from 'node:path'
import { handleDemoAndroidApkRequest } from './demoAndroidApkHandler.mjs'

function createResponse() {
  const headers = new Map<string, string>()
  return {
    statusCode: 0,
    headers,
    setHeader(name: string, value: string) {
      headers.set(name.toLowerCase(), value)
    },
    end() {
      return undefined
    },
  }
}

describe('demo Android APK download handler', () => {
  it('returns 404 when the debug APK has not been built', () => {
    const webRoot = mkdtempSync(path.join(tmpdir(), 'ge-demo-apk-'))
    const req = { method: 'GET', url: '/downloads/global-english-demo.apk' }
    const res = createResponse()

    expect(handleDemoAndroidApkRequest(req, res as never, webRoot)).toBe(true)
    expect(res.statusCode).toBe(404)
    expect(res.headers.get('cache-control')).toBe('no-store')
  })

  it('ignores unrelated routes', () => {
    const req = { method: 'GET', url: '/healthz' }
    const res = createResponse()
    expect(handleDemoAndroidApkRequest(req, res as never)).toBe(false)
    expect(res.statusCode).toBe(0)
  })
})
