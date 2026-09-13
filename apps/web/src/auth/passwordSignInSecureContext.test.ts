import { describe, expect, it } from 'vitest'
import { isPasswordSignInSecureContext } from './passwordSignInSecureContext'

describe('password sign-in secure context', () => {
  it('allows HTTPS and loopback HTTP', () => {
    expect(
      isPasswordSignInSecureContext({
        hostname: 'app.example.com',
        protocol: 'https:',
      }),
    ).toBe(true)
    expect(
      isPasswordSignInSecureContext({
        hostname: '127.0.0.1',
        protocol: 'http:',
      }),
    ).toBe(true)
  })

  it('allows trusted private and Tailscale HTTP hosts', () => {
    expect(
      isPasswordSignInSecureContext({
        hostname: '100.89.51.18',
        protocol: 'http:',
      }),
    ).toBe(true)
    expect(
      isPasswordSignInSecureContext({
        hostname: 'real-studioz.taila69adf.ts.net',
        protocol: 'http:',
      }),
    ).toBe(true)
    expect(
      isPasswordSignInSecureContext({
        hostname: '192.168.1.4',
        protocol: 'http:',
      }),
    ).toBe(true)
  })

  it('blocks plain HTTP on public hosts', () => {
    expect(
      isPasswordSignInSecureContext({
        hostname: '117.254.108.143',
        protocol: 'http:',
      }),
    ).toBe(false)
    expect(
      isPasswordSignInSecureContext({
        hostname: 'example.com',
        protocol: 'http:',
      }),
    ).toBe(false)
  })
})
