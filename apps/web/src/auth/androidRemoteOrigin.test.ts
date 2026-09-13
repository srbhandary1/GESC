import { describe, expect, it } from 'vitest'
import { resolveAndroidRemoteServer } from './androidRemoteOrigin'

describe('resolveAndroidRemoteServer', () => {
  it('accepts the documented static-IP HTTP review origin', () => {
    expect(
      resolveAndroidRemoteServer('http://117.254.108.143:8080'),
    ).toEqual({
      url: 'http://117.254.108.143:8080',
      cleartext: true,
    })
  })

  it('rejects missing, credential-bearing, or non-8080 remote HTTP origins', () => {
    expect(resolveAndroidRemoteServer(undefined)).toBeUndefined()
    expect(resolveAndroidRemoteServer('')).toBeUndefined()
    expect(resolveAndroidRemoteServer('http://117.254.108.143')).toBeUndefined()
    expect(
      resolveAndroidRemoteServer('http://example.test:8080'),
    ).toBeUndefined()
    expect(
      resolveAndroidRemoteServer('http://user:pass@117.254.108.143:8080'),
    ).toBeUndefined()
    expect(
      resolveAndroidRemoteServer('http://117.254.108.143:8080/prefix'),
    ).toBeUndefined()
  })
})
