import { isUsableAssetResponse } from './licensedSwathiGlb'

export const TUTOR_AVATAR_MANIFEST_PATH = '/tutor/avatars/manifest.json'
export const TUTOR_AVATAR_PORTRAIT_ID = 'portrait' as const

export interface TutorAvatarManifestEntry {
  readonly id: string
  readonly label: string
  readonly path: string
  readonly facing_y?: number
  readonly rig_type?: 'static' | 'skinned'
}

export interface TutorAvatarManifest {
  readonly license_path: string
  readonly avatars: readonly TutorAvatarManifestEntry[]
}

export interface TutorAvatarOption {
  readonly id: string
  readonly label: string
  readonly path: string | null
  readonly facingY?: number
  readonly rigType?: 'static' | 'skinned'
}

export async function fetchTutorAvatarManifest(
  fetchImpl: typeof fetch = fetch,
): Promise<TutorAvatarManifest | null> {
  try {
    const response = await fetchImpl(TUTOR_AVATAR_MANIFEST_PATH, {
      cache: 'no-store',
    })
    if (!response.ok) {
      return null
    }
    const json = (await response.json()) as TutorAvatarManifest
    if (!Array.isArray(json.avatars) || typeof json.license_path !== 'string') {
      return null
    }
    return json
  } catch {
    return null
  }
}

export async function tutorAvatarLicenseIsPresent(
  manifest: TutorAvatarManifest,
  fetchImpl: typeof fetch = fetch,
): Promise<boolean> {
  try {
    const response = await fetchImpl(manifest.license_path, {
      cache: 'no-store',
    })
    if (!isUsableAssetResponse(response)) {
      return false
    }

    return isCompletedTutorAvatarLicense(await response.text())
  } catch {
    return false
  }
}

export function isCompletedTutorAvatarLicense(text: string): boolean {
  if (text.includes('Fill this file after purchase')) {
    return false
  }

  const requiredFields = [
    'Vendor / store',
    'Product names / SKUs',
    'License type (commercial app / redistribution / modification)',
    'Licensed to',
  ]

  return requiredFields.every((field) => {
    const line = text
      .split(/\r?\n/u)
      .find((candidate) => candidate.trimStart().startsWith(`${field}:`))
    return line !== undefined && line.slice(line.indexOf(':') + 1).trim() !== ''
  })
}

export async function listAvailableTutorAvatars(
  fetchImpl: typeof fetch = fetch,
): Promise<readonly TutorAvatarOption[]> {
  const manifest = await fetchTutorAvatarManifest(fetchImpl)
  if (manifest === null) {
    return [{ id: TUTOR_AVATAR_PORTRAIT_ID, label: 'Portrait', path: null }]
  }

  const licensed = await tutorAvatarLicenseIsPresent(manifest, fetchImpl)
  const options: TutorAvatarOption[] = [
    { id: TUTOR_AVATAR_PORTRAIT_ID, label: 'Portrait', path: null },
  ]

  if (!licensed) {
    return options
  }

  for (const avatar of manifest.avatars) {
    if (await resourceExists(avatar.path, fetchImpl)) {
      options.push({
        id: avatar.id,
        label: avatar.label,
        path: avatar.path,
        facingY: avatar.facing_y,
        rigType: avatar.rig_type,
      })
    }
  }

  return options
}

async function resourceExists(
  path: string,
  fetchImpl: typeof fetch,
): Promise<boolean> {
  try {
    const head = await fetchImpl(path, {
      cache: 'no-store',
      method: 'HEAD',
    })
    if (isUsableAssetResponse(head)) {
      return true
    }

    if (head.status !== 405 && head.status !== 501) {
      return false
    }

    const ranged = await fetchImpl(path, {
      cache: 'no-store',
      headers: { Range: 'bytes=0-0' },
      method: 'GET',
    })
    return isUsableAssetResponse(ranged)
  } catch {
    return false
  }
}
