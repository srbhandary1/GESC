import { useGLTF } from '@react-three/drei'
import { useFrame, useThree } from '@react-three/fiber'
import { useLayoutEffect, useMemo, useRef } from 'react'
import { type Group, type Mesh, type SkinnedMesh } from 'three'
import { clone as cloneSkinnedScene } from 'three/examples/jsm/utils/SkeletonUtils.js'
import type { TutorMood } from '../TutorPresence'
import {
  applyBoneRotationX,
  applyBoneRotationY,
  applyBoneRotationZ,
  applyStaticSpeakingMotion,
  captureOptionalBone,
  type AnimatedBone,
} from './avatarRigMotion'
import {
  arkitWeightsForViseme,
} from './arkitVisemeWeights'
import { applyMorphWeights } from './applyMorphWeights'
import { discoverAvatarRig } from './discoverAvatarRig'
import { fitAvatarToPortraitFrame } from './fitAvatarToPortraitFrame'
import { resolveAvatarFacingY } from './resolveAvatarFacing'
import type { VisemeMouthShape } from './types'

export interface LicensedSwathiModelProps {
  readonly facingY?: number
  readonly glbPath: string
  readonly mood: TutorMood
  readonly mouth: VisemeMouthShape
  readonly rigType?: 'static' | 'skinned'
  readonly visemeId: number
}

export function LicensedSwathiModel({
  facingY,
  glbPath,
  mood,
  mouth,
  rigType,
  visemeId,
}: LicensedSwathiModelProps) {
  const { scene } = useGLTF(glbPath)
  const viewport = useThree((state) => state.size)
  const rootRef = useRef<Group>(null)
  const mountRef = useRef<Group>(null)
  const motionRef = useRef<Group>(null)
  const morphMeshes = useRef<Mesh[]>([])
  const skinnedMeshes = useRef<SkinnedMesh[]>([])
  const spine = useRef<AnimatedBone | null>(null)
  const chest = useRef<AnimatedBone | null>(null)
  const leftShoulder = useRef<AnimatedBone | null>(null)
  const rightShoulder = useRef<AnimatedBone | null>(null)
  const leftArm = useRef<AnimatedBone | null>(null)
  const rightArm = useRef<AnimatedBone | null>(null)
  const leftForeArm = useRef<AnimatedBone | null>(null)
  const rightForeArm = useRef<AnimatedBone | null>(null)
  const leftHand = useRef<AnimatedBone | null>(null)
  const rightHand = useRef<AnimatedBone | null>(null)
  const jawBone = useRef<AnimatedBone | null>(null)
  const head = useRef<AnimatedBone | null>(null)
  const isStatic = useRef(true)
  const fitted = useRef(false)

  const cloned = useMemo(() => cloneSkinnedScene(scene), [scene])
  const targetWeights = useMemo(
    () => arkitWeightsForViseme(visemeId),
    [visemeId],
  )

  useLayoutEffect(() => {
    fitted.current = false
  }, [cloned, facingY, glbPath, viewport.width, viewport.height])

  useLayoutEffect(() => {
    const root = rootRef.current
    const mount = mountRef.current
    const motion = motionRef.current
    if (root === null || mount === null || motion === null) {
      return
    }

    mount.rotation.set(0, resolveAvatarFacingY(cloned, facingY), 0)
    mount.updateMatrixWorld(true)

    const rig = discoverAvatarRig(motion)
    morphMeshes.current = [...rig.morphMeshes]
    skinnedMeshes.current = [...rig.skinnedMeshes]
    isStatic.current =
      rigType === 'static' ||
      (rigType !== 'skinned' && rig.rigKind === 'static')
    spine.current = captureOptionalBone(rig.spine)
    chest.current = captureOptionalBone(rig.chest)
    leftShoulder.current = captureOptionalBone(rig.leftShoulder)
    rightShoulder.current = captureOptionalBone(rig.rightShoulder)
    leftArm.current = captureOptionalBone(rig.leftArm)
    rightArm.current = captureOptionalBone(rig.rightArm)
    leftForeArm.current = captureOptionalBone(rig.leftForeArm)
    rightForeArm.current = captureOptionalBone(rig.rightForeArm)
    leftHand.current = captureOptionalBone(rig.leftHand)
    rightHand.current = captureOptionalBone(rig.rightHand)
    jawBone.current = captureOptionalBone(rig.jaw)
    head.current = captureOptionalBone(rig.head)

    if (fitted.current) {
      return
    }

    motion.position.set(0, 0, 0)
    motion.rotation.set(0, 0, 0)
    motion.scale.set(1, 1, 1)

    const viewportAspect = viewport.width / Math.max(viewport.height, 1)
    fitAvatarToPortraitFrame(root, { viewportAspect })
    fitted.current = true
  }, [cloned, facingY, glbPath, rigType, viewport.height, viewport.width])

  useFrame((state, delta) => {
    const t = state.clock.elapsedTime
    const lerp = Math.min(1, delta * 14)
    const speaking = mood === 'speaking'
    const speechPulse = speaking ? 0.18 + Math.sin(t * 9.5) * 0.12 : 0
    const jawOpen = Math.max(mouth.jawOpen, speechPulse)
    const motion = motionRef.current

    if (motion !== null && isStatic.current) {
      applyStaticSpeakingMotion(motion, t, speaking, jawOpen)
    } else if (motion !== null) {
      motion.position.y = speaking ? Math.sin(t * 2.1) * 0.01 : 0
      motion.rotation.z = Math.sin(t * (speaking ? 1.6 : 0.9)) *
        (speaking ? 0.012 : 0.004)
      motion.rotation.x = 0
      motion.scale.set(1, 1, 1)
    }

    applyMorphWeights(morphMeshes.current, targetWeights, lerp)

    applyBoneRotationX(jawBone.current, jawOpen * 0.34)

    const bodyBeat = speaking ? Math.sin(t * 2.2) : Math.sin(t * 0.8)
    const armSwing = speaking ? 0.2 : 0.05
    const handSwing = speaking ? 0.28 : 0.06
    applyBoneRotationX(spine.current, bodyBeat * (speaking ? 0.035 : 0.012))
    applyBoneRotationZ(chest.current, bodyBeat * (speaking ? 0.06 : 0.018))
    applyBoneRotationY(head.current, Math.sin(t * 1.7) * (speaking ? 0.08 : 0.025))
    applyBoneRotationZ(
      leftShoulder.current,
      0.04 + Math.sin(t * 2.1) * (speaking ? 0.1 : 0.03),
    )
    applyBoneRotationZ(
      rightShoulder.current,
      -0.08 + Math.sin(t * 2.5) * (speaking ? 0.12 : 0.03),
    )
    applyBoneRotationZ(leftArm.current, 0.12 + Math.sin(t * 1.8) * armSwing)
    applyBoneRotationZ(
      rightArm.current,
      (speaking ? -0.5 : -0.1) + Math.sin(t * 2.4) * armSwing,
    )
    applyBoneRotationX(
      leftForeArm.current,
      0.18 + Math.sin(t * 2.1 + 0.4) * (handSwing + 0.04),
    )
    applyBoneRotationX(
      rightForeArm.current,
      -0.28 + Math.sin(t * 2.8) * (handSwing + 0.04),
    )
    applyBoneRotationZ(leftHand.current, 0.08 + Math.sin(t * 3.4) * handSwing)
    applyBoneRotationZ(
      rightHand.current,
      -0.12 + Math.sin(t * 3.8) * handSwing,
    )

    for (const mesh of skinnedMeshes.current) {
      mesh.skeleton.update()
    }
  })

  return (
    <group ref={rootRef}>
      <group ref={mountRef}>
        <group ref={motionRef}>
          <primitive object={cloned} />
        </group>
      </group>
    </group>
  )
}
