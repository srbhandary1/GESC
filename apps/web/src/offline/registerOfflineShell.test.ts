import { describe, expect, it } from 'vitest'

import { serviceWorkerPath } from './registerOfflineShell.ts'

describe('offline public shell release', () => {
  it('binds a valid build fingerprint to the service-worker script URL', () => {
    const release = 'a'.repeat(64)

    expect(serviceWorkerPath(release)).toBe(
      `/service-worker.js?release=${release}`,
    )
  })

  it('does not put an invalid release value in a URL', () => {
    expect(serviceWorkerPath('not a release?secret=value')).toBe(
      '/service-worker.js',
    )
  })
})
