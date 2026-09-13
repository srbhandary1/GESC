import { Euler, Object3D } from 'three'

export interface AnimatedBone {
  readonly bone: Object3D
  readonly rest: Euler
}

export function captureOptionalBone(
  bone: Object3D | null,
): AnimatedBone | null {
  if (bone === null) {
    return null
  }

  return {
    bone,
    rest: bone.rotation.clone(),
  }
}

export function captureBoneRestPose(bones: readonly Object3D[]): AnimatedBone[] {
  return bones
    .filter((bone): bone is Object3D => bone !== null)
    .map((bone) => ({
      bone,
      rest: bone.rotation.clone(),
    }))
}

export function applyBoneRotationZ(
  entry: AnimatedBone | null,
  deltaZ: number,
): void {
  if (entry === null) {
    return
  }

  entry.bone.rotation.z = entry.rest.z + deltaZ
}

export function applyBoneRotationX(
  entry: AnimatedBone | null,
  deltaX: number,
): void {
  if (entry === null) {
    return
  }

  entry.bone.rotation.x = entry.rest.x + deltaX
}

export function applyBoneRotationY(
  entry: AnimatedBone | null,
  deltaY: number,
): void {
  if (entry === null) {
    return
  }

  entry.bone.rotation.y = entry.rest.y + deltaY
}

export function applyStaticSpeakingMotion(
  root: Object3D,
  elapsed: number,
  speaking: boolean,
  jawOpen: number,
): void {
  const bob = speaking ? Math.sin(elapsed * 2.1) * 0.012 : Math.sin(elapsed * 1.1) * 0.004
  const sway = speaking ? Math.sin(elapsed * 1.6) * 0.018 : Math.sin(elapsed * 0.9) * 0.006
  const breathe = speaking
    ? 1 + Math.sin(elapsed * 9.2) * 0.012 + jawOpen * 0.02
    : 1 + Math.sin(elapsed * 1.4) * 0.004

  root.position.y = bob
  root.rotation.z = sway
  root.rotation.x = speaking ? jawOpen * 0.05 : 0
  root.scale.set(1, breathe, 1)
}
