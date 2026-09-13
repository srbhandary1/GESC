import { Canvas } from '@react-three/fiber'
import { Suspense, useRef } from 'react'
import { SRGBColorSpace } from 'three'
import type { TutorMood } from '../TutorPresence'
import { Avatar3dBoundary } from './Avatar3dBoundary'
import { LicensedSwathiModel } from './LicensedSwathiModel'
import { SwathiAvatarScene } from './SwathiAvatarScene'
import type { VisemeMouthShape } from './types'

export interface SwathiAvatarCanvasProps {
  readonly className?: string
  readonly facingY?: number
  readonly glbPath: string
  readonly mood: TutorMood
  readonly mouth: VisemeMouthShape
  readonly rigType?: 'static' | 'skinned'
  readonly visemeId?: number
}

export function SwathiAvatarCanvas({
  className,
  facingY,
  glbPath,
  mood,
  mouth,
  rigType,
  visemeId = 0,
}: SwathiAvatarCanvasProps) {
  const hostRef = useRef<HTMLDivElement>(null)
  const procedural = <SwathiAvatarScene mood={mood} mouth={mouth} />

  return (
    <div ref={hostRef} className={className} aria-hidden="true">
      <Canvas
        camera={{ position: [0, 0.16, 1.72], fov: 34 }}
        dpr={[1, 1.25]}
        gl={{ alpha: true, antialias: true, powerPreference: 'low-power' }}
        resize={{ debounce: 16, scroll: false }}
        style={{ display: 'block', width: '100%', height: '100%' }}
        onCreated={({ camera, gl }) => {
          camera.lookAt(0, 0.16, 0)
          gl.outputColorSpace = SRGBColorSpace
          gl.setClearColor(0x000000, 0)
        }}
      >
        <color attach="background" args={['#1a2438']} />
        <hemisphereLight args={['#d8e4f0', '#2a241c', 0.7]} />
        <ambientLight intensity={0.7} />
        <directionalLight
          color="#fff6ea"
          intensity={1.25}
          position={[1.6, 2.2, 2.6]}
        />
        <directionalLight
          color="#8fbfc8"
          intensity={0.35}
          position={[-1.6, 0.6, 1.2]}
        />
        <Suspense fallback={null}>
          <Avatar3dBoundary fallback={procedural}>
            <LicensedSwathiModel
              facingY={facingY}
              glbPath={glbPath}
              mood={mood}
              mouth={mouth}
              rigType={rigType}
              visemeId={visemeId}
            />
          </Avatar3dBoundary>
        </Suspense>
      </Canvas>
    </div>
  )
}
