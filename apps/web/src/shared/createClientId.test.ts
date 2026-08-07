import { afterEach, describe, expect, it, vi } from 'vitest'
import { createClientId } from './createClientId'

afterEach(() => {
  vi.unstubAllGlobals()
  vi.restoreAllMocks()
})

describe('createClientId', () => {
  it('uses crypto.randomUUID when available', () => {
    vi.stubGlobal('crypto', {
      randomUUID: () => '11111111-2222-4333-8444-555555555555',
      getRandomValues: (bytes: Uint8Array) => bytes,
    })

    expect(createClientId()).toBe('11111111-2222-4333-8444-555555555555')
  })

  it('falls back when randomUUID is unavailable (insecure HTTP context)', () => {
    vi.stubGlobal('crypto', {
      getRandomValues: (bytes: Uint8Array) => {
        for (let index = 0; index < bytes.length; index += 1) {
          bytes[index] = index
        }

        return bytes
      },
    })

    const id = createClientId()

    expect(id).toMatch(
      /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/u,
    )
  })
})
