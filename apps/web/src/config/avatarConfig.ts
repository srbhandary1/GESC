import type { CoachGender } from '../coach/coachPreferences'
import avatarConfigJson from './avatarConfig.json' with { type: 'json' }

export interface AvatarAssetEntry {
  readonly id: CoachGender
  readonly glb: string
  readonly atlas: string
}

export type TutorRendererMode = 'gltf' | 'svg'

export interface AvatarConfig {
  readonly defaultTeacherId: CoachGender
  /**
   * Which renderer learner-facing tutor surfaces use. The GLB/WebGL stage
   * stays code-complete behind this gate, but ships as `svg` until a bust
   * asset passes visual review: the current procedural GLBs render with a
   * garbled face texture, which fails the product quality bar (D-0249).
   */
  readonly tutorRenderer: TutorRendererMode
  readonly avatars: Readonly<Record<CoachGender, AvatarAssetEntry>>
}

export const avatarConfig = avatarConfigJson as AvatarConfig

export function tutorRendererMode(): TutorRendererMode {
  return avatarConfig.tutorRenderer === 'gltf' ? 'gltf' : 'svg'
}

export function resolvePublicAssetUrl(path: string): string {
  const base = import.meta.env.BASE_URL
  const prefix = base.endsWith('/') ? base : `${base}/`
  return `${prefix}${path.replace(/^\//u, '')}`
}

export function tutorGlbUrl(teacherId: CoachGender): string {
  return resolvePublicAssetUrl(avatarConfig.avatars[teacherId].glb)
}

export function tutorAtlasUrl(teacherId: CoachGender): string {
  return resolvePublicAssetUrl(avatarConfig.avatars[teacherId].atlas)
}
