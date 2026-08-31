import { describe, expect, it } from 'vitest'
import { lanHttpsRecordingHref } from './secureRecordingOrigin'

describe('lanHttpsRecordingHref', () => {
  it('returns the LAN HTTPS origin on plain HTTP IP pages', () => {
    expect(
      lanHttpsRecordingHref({
        protocol: 'http:',
        hostname: '192.168.1.4',
        pathname: '/',
        hash: '#learn',
      }),
    ).toBe('https://192.168.1.4:8443/#learn')
  })

  it('returns null on loopback HTTP because the browser already allows capture', () => {
    expect(
      lanHttpsRecordingHref({
        protocol: 'http:',
        hostname: '127.0.0.1',
        pathname: '/',
        hash: '#learn',
      }),
    ).toBeNull()
  })

  it('returns null once the page is already HTTPS', () => {
    expect(
      lanHttpsRecordingHref({
        protocol: 'https:',
        hostname: '192.168.1.4',
        pathname: '/',
        hash: '#learn',
      }),
    ).toBeNull()
  })
})
