import { listAvailableTutorAvatars } from './tutorAvatarCatalog'

/** @deprecated Use `/tutor/avatars/swathi-1.glb` via the avatar catalog. */
export const LICENSED_SWATHI_GLB_PATH = '/tutor/swathi.glb'
/** @deprecated Use `/tutor/LICENSE-tutor-avatars.txt` via the avatar catalog. */
export const LICENSED_SWATHI_LICENSE_PATH = '/tutor/LICENSE-swathi-glb.txt'

export async function licensedSwathiGlbIsPresent(
  fetchImpl: typeof fetch = fetch,
): Promise<boolean> {
  const options = await listAvailableTutorAvatars(fetchImpl)
  return options.some((option) => option.path !== null)
}

export function isUsableAssetResponse(response: Pick<Response, 'ok' | 'status' | 'headers'>): boolean {
  if (!response.ok && response.status !== 206) {
    return false
  }

  const type = contentType(response)
  // Soft-launch Caddy uses try_files → /index.html, so missing GLB/license
  // still return HTTP 200 text/html. That must not enable the GLB loader.
  return type !== '' && !type.includes('text/html')
}

function contentType(response: Pick<Response, 'headers'>): string {
  return (response.headers?.get('content-type') ?? '').toLowerCase()
}
