import { describe, expect, it, vi } from 'vitest'
import { arkitWeightsForViseme, normalizeMorphName } from './arkitVisemeWeights'
import {
  isUsableAssetResponse,
  licensedSwathiGlbIsPresent,
} from './licensedSwathiGlb'

describe('licensed Swathi GLB helpers', () => {
  it('maps Azure visemes onto ARKit jaw and lip weights', () => {
    expect(arkitWeightsForViseme(0)).toEqual({})
    expect(arkitWeightsForViseme(2).jawOpen).toBeGreaterThan(
      arkitWeightsForViseme(6).jawOpen ?? 0,
    )
    expect(arkitWeightsForViseme(7).mouthPucker).toBeGreaterThan(0.5)
    expect(normalizeMorphName('jaw_Open')).toBe('jawopen')
  })

  it('treats SPA index.html fallbacks as missing assets', () => {
    expect(
      isUsableAssetResponse({
        ok: true,
        status: 200,
        headers: new Headers({ 'content-type': 'text/html; charset=utf-8' }),
      }),
    ).toBe(false)
    expect(
      isUsableAssetResponse({
        ok: true,
        status: 200,
        headers: new Headers({ 'content-type': 'model/gltf-binary' }),
      }),
    ).toBe(true)
  })

  it('requires a licensed catalog GLB to be present', async () => {
    const fetchImpl = vi.fn(async (input: RequestInfo | URL) => {
      const path = String(input)
      if (path.endsWith('manifest.json')) {
        return jsonResponse({
          license_path: '/tutor/LICENSE-tutor-avatars.txt',
          avatars: [
            {
              id: 'swathi-1',
              label: 'One',
              path: '/tutor/avatars/swathi-1.glb',
            },
          ],
        })
      }
      if (path.endsWith('swathi-1.glb')) {
        return typedResponse('model/gltf-binary')
      }
      return { ok: false, status: 404, headers: new Headers() } as Response
    }) as typeof fetch

    await expect(licensedSwathiGlbIsPresent(fetchImpl)).resolves.toBe(false)

    const spa = vi.fn(async () => typedResponse('text/html; charset=utf-8')) as typeof fetch
    await expect(licensedSwathiGlbIsPresent(spa)).resolves.toBe(false)

    const both = vi.fn(async (input: RequestInfo | URL) => {
      const path = String(input)
      if (path.endsWith('manifest.json')) {
        return jsonResponse({
          license_path: '/tutor/LICENSE-tutor-avatars.txt',
          avatars: [
            {
              id: 'swathi-1',
              label: 'One',
              path: '/tutor/avatars/swathi-1.glb',
            },
          ],
        })
      }
      if (
        path.endsWith('LICENSE-tutor-avatars.txt')
      ) {
        return textResponse([
          'Vendor / store: Example vendor',
          'Product names / SKUs: Swathi model',
          'License type (commercial app / redistribution / modification): Commercial app',
          'Licensed to: Global Spoken English App',
        ].join('\n'))
      }
      if (path.endsWith('swathi-1.glb')) {
        return typedResponse('model/gltf-binary')
      }
      return { ok: false, status: 404, headers: new Headers() } as Response
    }) as typeof fetch
    await expect(licensedSwathiGlbIsPresent(both)).resolves.toBe(true)
  })
})

function typedResponse(contentType: string): Response {
  return {
    ok: true,
    status: 200,
    headers: new Headers({ 'content-type': contentType }),
  } as Response
}

function jsonResponse(body: unknown): Response {
  return {
    ok: true,
    status: 200,
    headers: new Headers({ 'content-type': 'application/json' }),
    json: async () => body,
  } as Response
}

function textResponse(body: string): Response {
  return {
    ok: true,
    status: 200,
    headers: new Headers({ 'content-type': 'text/plain' }),
    text: async () => body,
  } as Response
}
