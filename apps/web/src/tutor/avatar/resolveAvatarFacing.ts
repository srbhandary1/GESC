import { Box3, Object3D, Vector3 } from 'three'

export function resolveAvatarFacingY(
  root: Object3D,
  overrideY: number | undefined,
): number {
  if (overrideY !== undefined) {
    return overrideY
  }

  root.rotation.set(0, 0, 0)
  root.updateMatrixWorld(true)
  const box = new Box3().setFromObject(root)
  const size = box.getSize(new Vector3())

  if (size.x > size.z * 1.12) {
    return Math.PI / 2
  }

  if (size.z > size.x * 1.12) {
    return 0
  }

  return 0
}
