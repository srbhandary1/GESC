import { ContactShadows, Outlines } from '@react-three/drei'
import { useFrame } from '@react-three/fiber'
import { useRef, type RefObject } from 'react'
import type { Group, Mesh } from 'three'
import type { TutorMood } from '../TutorPresence'
import { speakingMotion, SWATHI_LOOK } from './swathiMotion'
import type { VisemeMouthShape } from './types'

export const SWATHI_AVATAR_MODE = 'original-sculpt-v4' as const
export { SWATHI_LOOK }

const SKIN = '#b56b42'
const SKIN_SHADOW = '#9a5734'
const HAIR = '#8b3a1f'
const HAIR_DARK = '#5c2414'
const SHIRT = '#2a8f7a'
const SHIRT_SHADOW = '#1f6d5c'
const JEANS = '#3d5a73'
const HEADBAND = '#f2d6c4'
const GLASS = '#c4a35a'
const LENS = '#8eb8c9'
const LIP = '#a84850'
const EYE_WHITE = '#f7f1ea'
const IRIS = '#3d2918'
const TEETH = '#f4efe6'
const MOUTH_IN = '#4a1820'
const LINE = '#1c140f'

export interface SwathiAvatarSceneProps {
  readonly mood: TutorMood
  readonly mouth: VisemeMouthShape
}

export function SwathiAvatarScene({ mood, mouth }: SwathiAvatarSceneProps) {
  const hipsRef = useRef<Group>(null)
  const spineRef = useRef<Group>(null)
  const chestRef = useRef<Group>(null)
  const neckRef = useRef<Group>(null)
  const headRef = useRef<Group>(null)
  const mouthGroupRef = useRef<Group>(null)
  const lowerLipRef = useRef<Mesh>(null)
  const innerMouthRef = useRef<Mesh>(null)
  const leftClavicleRef = useRef<Group>(null)
  const leftUpperRef = useRef<Group>(null)
  const leftForearmRef = useRef<Group>(null)
  const leftHandRef = useRef<Group>(null)
  const rightClavicleRef = useRef<Group>(null)
  const rightUpperRef = useRef<Group>(null)
  const rightForearmRef = useRef<Group>(null)
  const rightHandRef = useRef<Group>(null)
  const leftLidRef = useRef<Mesh>(null)
  const rightLidRef = useRef<Mesh>(null)

  useFrame((state) => {
    const t = state.clock.elapsedTime
    const motion = speakingMotion(mood, mouth, t)
    const breathe = Math.sin(t * 1.4) * 0.014

    if (hipsRef.current !== null) {
      hipsRef.current.position.y = -0.28 + breathe
    }
    drive(spineRef, { x: 0.03 + Math.sin(t * 0.9) * 0.02 })
    drive(chestRef, { y: 0.08 * Math.sin(t * 0.55) })
    drive(neckRef, { x: 0.04 * Math.sin(t * 1.15) })
    drive(headRef, { y: 0.12 * Math.sin(t * 0.7) })

    if (mouthGroupRef.current !== null) {
      mouthGroupRef.current.scale.x = 0.92 + motion.lipWide * 0.28
      mouthGroupRef.current.scale.z = 0.95 + motion.lipRound * 0.18
    }
    if (innerMouthRef.current !== null) {
      innerMouthRef.current.scale.y = 0.22 + motion.jawOpen * 1.65
    }
    if (lowerLipRef.current !== null) {
      lowerLipRef.current.position.y = -0.016 - motion.jawOpen * 0.062
    }

    const blink = blinkAmount(t)
    if (leftLidRef.current !== null) {
      leftLidRef.current.scale.y = blink
    }
    if (rightLidRef.current !== null) {
      rightLidRef.current.scale.y = blink
    }

    drive(leftUpperRef, { x: motion.leftUpperX, z: motion.leftUpperZ })
    drive(leftForearmRef, { x: motion.leftForearmX })
    drive(leftHandRef, { z: 0.08 + Math.sin(t * 2.4) * 0.12 })
    drive(rightUpperRef, { x: motion.rightUpperX, z: motion.rightUpperZ })
    drive(rightForearmRef, { x: motion.rightForearmX })
    drive(rightHandRef, { z: -0.12 + Math.sin(t * 3.1) * 0.2, y: Math.sin(t * 2.7) * 0.15 })
  })

  return (
    <>
      <group ref={hipsRef} position={[0, -0.28, 0]}>
        <group ref={spineRef}>
          <mesh position={[0, 0.08, 0]} rotation={[0.08, 0, 0]} scale={[1.05, 0.95, 0.9]}>
            <capsuleGeometry args={[0.17, 0.18, 8, 16]} />
            <Toon color={JEANS} />
          </mesh>
          <group ref={chestRef} position={[0, 0.32, 0]}>
            <mesh rotation={[0.2, 0, 0]} scale={[1.22, 0.86, 0.84]}>
              <sphereGeometry args={[0.27, 28, 20]} />
              <Toon color={SHIRT} />
            </mesh>
            <mesh position={[0, -0.16, 0.1]} scale={[0.72, 0.3, 0.58]}>
              <sphereGeometry args={[0.22, 16, 12]} />
              <Toon color={SHIRT_SHADOW} />
            </mesh>
            <mesh position={[-0.08, 0.12, 0.21]} rotation={[0.5, 0.75, 0.08]} scale={[0.12, 0.22, 0.03]}>
              <boxGeometry args={[1, 1, 1]} />
              <Toon color={SHIRT} />
            </mesh>
            <mesh position={[0.08, 0.12, 0.21]} rotation={[0.5, -0.75, -0.08]} scale={[0.12, 0.22, 0.03]}>
              <boxGeometry args={[1, 1, 1]} />
              <Toon color={SHIRT} />
            </mesh>
            <mesh position={[0, -0.2, 0.13]} scale={[1.2, 0.72, 0.9]}>
              <sphereGeometry args={[0.055, 12, 10]} />
              <Toon color={SHIRT} />
            </mesh>
            <mesh position={[-0.045, -0.24, 0.11]} rotation={[0.35, 0, 0.65]} scale={[0.55, 1, 0.4]}>
              <capsuleGeometry args={[0.03, 0.07, 4, 8]} />
              <Toon color={SHIRT_SHADOW} />
            </mesh>
            <mesh position={[0.045, -0.24, 0.11]} rotation={[0.35, 0, -0.65]} scale={[0.55, 1, 0.4]}>
              <capsuleGeometry args={[0.03, 0.07, 4, 8]} />
              <Toon color={SHIRT_SHADOW} />
            </mesh>

            <group ref={neckRef} position={[0, 0.22, 0.02]}>
              <mesh position={[0, 0.07, 0]}>
                <cylinderGeometry args={[0.06, 0.078, 0.16, 16]} />
                <Toon color={SKIN} />
              </mesh>
              <group ref={headRef} position={[0, 0.32, 0.02]} scale={[1.12, 1.12, 1.12]}>
                <Head
                  innerMouthRef={innerMouthRef}
                  leftLidRef={leftLidRef}
                  lowerLipRef={lowerLipRef}
                  mouthGroupRef={mouthGroupRef}
                  rightLidRef={rightLidRef}
                />
              </group>
            </group>

            <group ref={leftClavicleRef} position={[-0.22, 0.12, 0.02]}>
              <group ref={leftUpperRef} position={[-0.17, -0.02, 0]}>
                <Limb color={SHIRT} length={0.26} radius={0.06} />
                <group ref={leftForearmRef} position={[0, -0.28, 0]}>
                  <Limb color={SKIN} length={0.24} radius={0.042} />
                  <group ref={leftHandRef} position={[0, -0.26, 0.02]}>
                    <Hand />
                  </group>
                </group>
              </group>
            </group>

            <group ref={rightClavicleRef} position={[0.22, 0.12, 0.02]}>
              <group ref={rightUpperRef} position={[0.17, -0.02, 0]}>
                <Limb color={SHIRT} length={0.26} radius={0.06} />
                <group ref={rightForearmRef} position={[0, -0.28, 0]}>
                  <Limb color={SKIN} length={0.24} radius={0.042} />
                  <group ref={rightHandRef} position={[0, -0.26, 0.02]}>
                    <Hand />
                  </group>
                </group>
              </group>
            </group>
          </group>
        </group>
      </group>
      <ContactShadows
        blur={2.2}
        far={2}
        opacity={0.35}
        position={[0, -0.95, 0]}
        scale={3}
      />
    </>
  )
}

function Head({
  innerMouthRef,
  leftLidRef,
  lowerLipRef,
  mouthGroupRef,
  rightLidRef,
}: {
  readonly innerMouthRef: RefObject<Mesh | null>
  readonly leftLidRef: RefObject<Mesh | null>
  readonly lowerLipRef: RefObject<Mesh | null>
  readonly mouthGroupRef: RefObject<Group | null>
  readonly rightLidRef: RefObject<Mesh | null>
}) {
  return (
    <group>
      <mesh scale={[0.9, 1.12, 0.96]}>
        <sphereGeometry args={[0.2, 40, 32]} />
        <Toon color={SKIN} />
      </mesh>
      <Hair />
      <Glasses />
      <mesh position={[-0.08, 0.08, 0.15]} rotation={[0.1, 0.15, 0.14]} scale={[0.95, 0.24, 0.35]}>
        <capsuleGeometry args={[0.018, 0.04, 4, 8]} />
        <Toon color={HAIR_DARK} />
      </mesh>
      <mesh position={[0.08, 0.08, 0.15]} rotation={[0.1, -0.15, -0.14]} scale={[0.95, 0.24, 0.35]}>
        <capsuleGeometry args={[0.018, 0.04, 4, 8]} />
        <Toon color={HAIR_DARK} />
      </mesh>
      <mesh position={[-0.185, 0, 0.02]} rotation={[0, 0, -0.3]} scale={[0.28, 0.7, 0.48]}>
        <sphereGeometry args={[0.055, 12, 10]} />
        <Toon color={SKIN} />
      </mesh>
      <mesh position={[0.185, 0, 0.02]} rotation={[0, 0, 0.3]} scale={[0.28, 0.7, 0.48]}>
        <sphereGeometry args={[0.055, 12, 10]} />
        <Toon color={SKIN} />
      </mesh>
      <Eye lidRef={leftLidRef} x={-0.055} />
      <Eye lidRef={rightLidRef} x={0.055} />
      <mesh position={[0, 0.004, 0.185]} rotation={[0.32, 0, 0]} scale={[0.4, 0.7, 0.36]}>
        <sphereGeometry args={[0.036, 14, 10]} />
        <Toon color={SKIN_SHADOW} />
      </mesh>
      <group ref={mouthGroupRef} position={[0, -0.055, 0.188]}>
        <mesh ref={innerMouthRef} rotation={[0.15, 0, 0]}>
          <circleGeometry args={[0.048, 16]} />
          <meshBasicMaterial color={MOUTH_IN} />
        </mesh>
        <mesh position={[0, 0.01, 0.004]} scale={[1, 0.55, 0.4]}>
          <boxGeometry args={[0.07, 0.022, 0.012]} />
          <meshToonMaterial color={TEETH} />
        </mesh>
        <mesh position={[0, 0.02, 0.01]} rotation={[0, 0, Math.PI / 2]} scale={[0.7, 1.15, 0.7]}>
          <capsuleGeometry args={[0.012, 0.05, 4, 10]} />
          <Toon color={LIP} />
        </mesh>
        <mesh
          ref={lowerLipRef}
          position={[0, -0.018, 0.01]}
          rotation={[0, 0, Math.PI / 2]}
          scale={[0.75, 1.2, 0.75]}
        >
          <capsuleGeometry args={[0.012, 0.048, 4, 10]} />
          <Toon color={LIP} />
        </mesh>
      </group>
    </group>
  )
}

function Hair() {
  return (
    <group>
      <mesh position={[0, 0.055, -0.02]} scale={[1.22, 1.16, 1.24]}>
        <sphereGeometry args={[0.2, 32, 24, 0, Math.PI * 2, 0, Math.PI * 0.7]} />
        <Toon color={HAIR} />
      </mesh>
      <mesh position={[0, 0.1, 0.02]} rotation={[0.32, 0, 0]}>
        <torusGeometry args={[0.175, 0.015, 8, 24]} />
        <Toon color={HEADBAND} />
      </mesh>
      <mesh position={[0.16, 0.0, 0.08]} rotation={[0.2, -0.65, -0.35]} scale={[0.38, 1.1, 0.38]}>
        <sphereGeometry args={[0.07, 12, 10]} />
        <Toon color={HAIR} />
      </mesh>
      <group position={[-0.14, -0.02, -0.05]} rotation={[0.9, 0.6, -0.1]}>
        <mesh position={[0, -0.08, 0]}>
          <capsuleGeometry args={[0.055, 0.15, 6, 10]} />
          <Toon color={HAIR} />
        </mesh>
        <mesh position={[-0.02, -0.24, 0.02]} rotation={[0.28, 0, -0.12]}>
          <capsuleGeometry args={[0.048, 0.16, 6, 10]} />
          <Toon color={HAIR_DARK} />
        </mesh>
        <mesh position={[-0.04, -0.4, 0.04]} rotation={[0.38, 0, -0.18]}>
          <capsuleGeometry args={[0.038, 0.14, 5, 10]} />
          <Toon color={HAIR} />
        </mesh>
      </group>
    </group>
  )
}

function Glasses() {
  return (
    <group position={[0, 0.038, 0.19]}>
      <mesh position={[-0.056, 0, 0]}>
        <torusGeometry args={[0.034, 0.005, 8, 16]} />
        <Toon color={GLASS} />
      </mesh>
      <mesh position={[0.056, 0, 0]}>
        <torusGeometry args={[0.034, 0.005, 8, 16]} />
        <Toon color={GLASS} />
      </mesh>
      <mesh position={[-0.056, 0, 0.002]}>
        <circleGeometry args={[0.028, 16]} />
        <meshToonMaterial color={LENS} opacity={0.22} transparent />
      </mesh>
      <mesh position={[0.056, 0, 0.002]}>
        <circleGeometry args={[0.028, 16]} />
        <meshToonMaterial color={LENS} opacity={0.22} transparent />
      </mesh>
      <mesh position={[0, 0.002, 0]} scale={[1, 0.32, 0.32]}>
        <boxGeometry args={[0.03, 0.012, 0.008]} />
        <Toon color={GLASS} />
      </mesh>
      <mesh position={[-0.095, -0.008, -0.07]} rotation={[0.08, 1.05, 0]}>
        <cylinderGeometry args={[0.004, 0.004, 0.12, 8]} />
        <Toon color={GLASS} />
      </mesh>
      <mesh position={[0.095, -0.008, -0.07]} rotation={[0.08, -1.05, 0]}>
        <cylinderGeometry args={[0.004, 0.004, 0.12, 8]} />
        <Toon color={GLASS} />
      </mesh>
    </group>
  )
}

function Eye({
  x,
  lidRef,
}: {
  readonly x: number
  readonly lidRef: RefObject<Mesh | null>
}) {
  return (
    <group position={[x, 0.038, 0.168]}>
      <mesh>
        <sphereGeometry args={[0.03, 14, 12]} />
        <meshToonMaterial color={EYE_WHITE} />
      </mesh>
      <mesh position={[0, 0, 0.016]}>
        <sphereGeometry args={[0.016, 12, 10]} />
        <meshToonMaterial color={IRIS} />
      </mesh>
      <mesh position={[0, 0, 0.026]}>
        <sphereGeometry args={[0.007, 8, 8]} />
        <meshToonMaterial color="#120c08" />
      </mesh>
      <mesh position={[-0.006, 0.006, 0.03]}>
        <sphereGeometry args={[0.004, 8, 8]} />
        <meshBasicMaterial color="#fff" />
      </mesh>
      <mesh ref={lidRef} position={[0, 0.018, 0.008]} scale={[1.05, 1, 0.7]}>
        <sphereGeometry args={[0.03, 10, 8, 0, Math.PI * 2, 0, Math.PI * 0.48]} />
        <Toon color={SKIN} />
      </mesh>
    </group>
  )
}

function Limb({
  color,
  length,
  radius,
}: {
  readonly color: string
  readonly length: number
  readonly radius: number
}) {
  return (
    <mesh position={[0, -length / 2, 0]}>
      <capsuleGeometry args={[radius, length, 5, 12]} />
      <Toon color={color} />
    </mesh>
  )
}

function Hand() {
  return (
    <group>
      <mesh scale={[1.15, 0.78, 0.5]}>
        <boxGeometry args={[0.058, 0.072, 0.04]} />
        <Toon color={SKIN} />
      </mesh>
      <Finger length={0.042} x={-0.022} zRot={-0.24} />
      <Finger length={0.048} x={-0.007} zRot={-0.06} />
      <Finger length={0.046} x={0.008} zRot={0.06} />
      <Finger length={0.038} x={0.022} zRot={0.24} />
      <mesh position={[0.03, -0.006, 0.008]} rotation={[0.4, 0.75, 0.95]}>
        <capsuleGeometry args={[0.009, 0.03, 3, 8]} />
        <Toon color={SKIN} />
      </mesh>
    </group>
  )
}

function Finger({
  x,
  length,
  zRot,
}: {
  readonly x: number
  readonly length: number
  readonly zRot: number
}) {
  return (
    <mesh position={[x, -0.055, 0.01]} rotation={[0.42, 0, zRot]}>
      <capsuleGeometry args={[0.008, length, 3, 8]} />
      <Toon color={SKIN} />
    </mesh>
  )
}

function Toon({ color }: { readonly color: string }) {
  return (
    <>
      <meshToonMaterial color={color} />
      <Outlines color={LINE} thickness={0.02} />
    </>
  )
}

function drive(
  ref: RefObject<Group | null>,
  rotation: { x?: number; y?: number; z?: number },
): void {
  if (ref.current === null) {
    return
  }
  if (rotation.x !== undefined) {
    ref.current.rotation.x = rotation.x
  }
  if (rotation.y !== undefined) {
    ref.current.rotation.y = rotation.y
  }
  if (rotation.z !== undefined) {
    ref.current.rotation.z = rotation.z
  }
}

function blinkAmount(time: number): number {
  const cycle = time % 3.8
  if (cycle > 3.58) {
    return 0.08
  }
  if (cycle > 3.46) {
    return 0.35
  }
  return 1
}
