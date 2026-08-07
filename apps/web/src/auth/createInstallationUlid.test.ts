import { describe, expect, it } from 'vitest'
import { createInstallationUlid } from './createInstallationUlid'

describe('createInstallationUlid', () => {
  it('returns a 26-character Crockford ULID', () => {
    const ulid = createInstallationUlid(
      () => new Uint8Array(10).fill(7),
      () => Date.UTC(2026, 7, 7, 12, 0, 0),
    )

    expect(ulid).toHaveLength(26)
    expect(ulid).toMatch(/^[0-9A-HJKMNP-TV-Z]{26}$/u)
  })
})
