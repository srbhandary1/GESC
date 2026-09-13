import { describe, expect, it, vi } from 'vitest'
import {
  isCompletedTutorAvatarLicense,
  listAvailableTutorAvatars,
  TUTOR_AVATAR_MANIFEST_PATH,
} from './tutorAvatarCatalog'
import { isUsableAssetResponse } from './licensedSwathiGlb'

describe('tutor avatar catalog', () => {
  it('rejects the unfilled license template as a grant', () => {
    expect(
      isCompletedTutorAvatarLicense(
        'Fill this file after purchase.\nVendor / store:\nProduct names / SKUs:\nLicense type (commercial app / redistribution / modification):\nLicensed to: Global Spoken English App',
      ),
    ).toBe(false)
    expect(isCompletedTutorAvatarLicense(completedLicense())).toBe(true)
  })

  it('treats SPA index.html fallbacks as missing assets', () => {
    expect(
      isUsableAssetResponse({
        ok: true,
        status: 200,
        headers: new Headers({ 'content-type': 'text/html; charset=utf-8' }),
      }),
    ).toBe(false)
  })

  it('returns portrait only when the license grant is missing', async () => {
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

    const options = await listAvailableTutorAvatars(fetchImpl)
    expect(options).toEqual([
      { id: 'portrait', label: 'Portrait', path: null },
    ])
  })

  it('lists licensed GLB avatars when manifest, license, and files exist', async () => {
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
            {
              id: 'swathi-2',
              label: 'Two',
              path: '/tutor/avatars/swathi-2.glb',
            },
          ],
        })
      }
      if (
        path.endsWith('LICENSE-tutor-avatars.txt')
      ) {
        return textResponse(completedLicense())
      }
      if (path.endsWith('swathi-1.glb')) {
        return typedResponse('model/gltf-binary')
      }
      return { ok: false, status: 404, headers: new Headers() } as Response
    }) as typeof fetch

    const options = await listAvailableTutorAvatars(fetchImpl)
    expect(options).toEqual([
      { id: 'portrait', label: 'Portrait', path: null },
      {
        id: 'swathi-1',
        label: 'One',
        path: '/tutor/avatars/swathi-1.glb',
      },
    ])
    expect(TUTOR_AVATAR_MANIFEST_PATH).toBe('/tutor/avatars/manifest.json')
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

function completedLicense(): string {
  return [
    'Vendor / store: Example vendor',
    'Product names / SKUs: Swathi model',
    'License type (commercial app / redistribution / modification): Commercial app',
    'Licensed to: Global Spoken English App',
  ].join('\n')
}
