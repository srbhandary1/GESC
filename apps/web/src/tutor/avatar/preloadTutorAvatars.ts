import { useGLTF } from '@react-three/drei'
import type { TutorAvatarOption } from './tutorAvatarCatalog'

let canvasModulePromise: Promise<unknown> | null = null

export function preloadTutorAvatarModels(
  avatars: readonly TutorAvatarOption[],
): void {
  for (const avatar of avatars) {
    if (avatar.path === null) {
      continue
    }

    try {
      useGLTF.preload(avatar.path)
    } catch {
      // Ignore preload failures in non-WebGL environments.
    }
  }
}

export function preloadTutorAvatarCanvas(): void {
  if (canvasModulePromise !== null) {
    return
  }

  canvasModulePromise = import('./SwathiAvatarCanvas')
}
