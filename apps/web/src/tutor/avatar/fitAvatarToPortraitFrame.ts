import { Box3, Object3D, Vector3 } from 'three'

export interface PortraitFrameFitOptions {
  readonly viewportAspect: number
  readonly fill?: number
  readonly waistHeightRatio?: number
  readonly frameBottomY?: number
}

const DEFAULT_FILL = 0.94
const DEFAULT_WAIST_HEIGHT_RATIO = 0.56
const DEFAULT_FRAME_BOTTOM_Y = -0.4

export function fitAvatarToPortraitFrame(
  root: Object3D,
  options: PortraitFrameFitOptions,
): void {
  const fill = options.fill ?? DEFAULT_FILL
  const waistHeightRatio = options.waistHeightRatio ?? DEFAULT_WAIST_HEIGHT_RATIO
  const frameBottomY = options.frameBottomY ?? DEFAULT_FRAME_BOTTOM_Y
  const viewportAspect = Math.max(options.viewportAspect, 0.45)

  root.scale.set(1, 1, 1)
  root.position.set(0, 0, 0)
  root.updateMatrixWorld(true)

  const box = new Box3().setFromObject(root)
  const size = box.getSize(new Vector3())
  if (size.y <= 0.001 || size.x <= 0.001) {
    return
  }

  const waistLine = box.min.y + size.y * waistHeightRatio
  const upperHeight = Math.max(box.max.y - waistLine, size.y * 0.001)
  const frameHeight = 1
  const frameWidth = frameHeight * viewportAspect
  const scaleForHeight = (frameHeight * fill) / upperHeight
  const scaleForWidth = (frameWidth * fill) / size.x
  const scale = Math.min(scaleForHeight, scaleForWidth)

  root.scale.setScalar(scale)
  root.updateMatrixWorld(true)

  const fitted = new Box3().setFromObject(root)
  const fittedSize = fitted.getSize(new Vector3())
  const waistLineScaled = fitted.min.y + fittedSize.y * waistHeightRatio
  const centerX = (fitted.min.x + fitted.max.x) * 0.5
  const centerZ = (fitted.min.z + fitted.max.z) * 0.5

  root.position.set(-centerX, frameBottomY - waistLineScaled, -centerZ)
}
