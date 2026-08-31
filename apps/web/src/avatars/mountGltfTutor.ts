import { AvatarGesture } from '@global-english/contracts'
import type { Bone, Mesh, Object3D, WebGLRenderer } from 'three'
import type { CoachGender } from '../coach/coachPreferences'
import { mouthShapeToVisemeTarget, tutorGltfUrl } from './visemeTargets'

export interface GltfTutorMountOptions {
  readonly canvas: HTMLCanvasElement
  readonly gender: CoachGender
  readonly reducedMotion: boolean
  readonly signal: AbortSignal
  readonly getMouthShape: () => string
  readonly getGesture: () => AvatarGesture | null
  readonly getSpeaking: () => boolean
}

function disposeObject(root: Object3D): void {
  root.traverse((object) => {
    const mesh = object as Mesh
    mesh.geometry?.dispose()
    const material = mesh.material
    if (Array.isArray(material)) {
      for (const item of material) {
        item.dispose()
      }
    } else {
      material?.dispose()
    }
  })
}

function resizeRenderer(
  canvas: HTMLCanvasElement,
  renderer: WebGLRenderer,
  camera: { aspect: number; updateProjectionMatrix: () => void },
): void {
  const width = Math.max(1, canvas.clientWidth)
  const height = Math.max(1, canvas.clientHeight)
  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2))
  renderer.setSize(width, height, false)
  camera.aspect = width / height
  camera.updateProjectionMatrix()
}

export async function mountGltfTutor(options: GltfTutorMountOptions): Promise<void> {
  const three = await import('three')
  const { GLTFLoader } = await import('three/addons/loaders/GLTFLoader.js')
  const { canvas, gender, reducedMotion, signal, getMouthShape, getGesture, getSpeaking } =
    options

  if (signal.aborted) {
    return
  }

  const renderer = new three.WebGLRenderer({
    canvas,
    alpha: true,
    antialias: true,
    powerPreference: 'high-performance',
  })
  renderer.setClearColor(0x000000, 0)
  renderer.outputColorSpace = three.SRGBColorSpace
  renderer.toneMapping = three.ACESFilmicToneMapping
  renderer.toneMappingExposure = 1.05

  const scene = new three.Scene()
  const camera = new three.PerspectiveCamera(26, 1, 0.05, 8)
  camera.position.set(0, 1.545, 0.38)
  camera.lookAt(0, 1.505, 0.02)

  scene.add(new three.HemisphereLight(0xf4e6d4, 0x2a3344, 1.15))
  const key = new three.DirectionalLight(0xfff4e8, 1.35)
  key.position.set(0.35, 1.9, 0.8)
  scene.add(key)
  const fill = new three.DirectionalLight(0xc5d8ff, 0.45)
  fill.position.set(-0.5, 1.4, 0.4)
  scene.add(fill)

  const gltf = await new GLTFLoader().loadAsync(tutorGltfUrl(gender))
  if (signal.aborted) {
    renderer.dispose()
    return
  }

  const root = gltf.scene
  scene.add(root)

  const morphMeshes: Mesh[] = []
  const found = { head: null as Bone | null }
  root.traverse((object) => {
    if (object instanceof three.Mesh && object.morphTargetInfluences && object.morphTargetDictionary) {
      morphMeshes.push(object)
    }
    if (object instanceof three.Bone && object.name === 'Head') {
      found.head = object
    }
  })
  const headBone = found.head

  const fit = (): void => {
    resizeRenderer(canvas, renderer, camera)
  }
  fit()

  const observer = new ResizeObserver(fit)
  observer.observe(canvas)

  let frameId = 0
  const startedAt = performance.now()
  const restHead = {
    x: headBone?.rotation.x ?? 0,
    y: headBone?.rotation.y ?? 0,
    z: headBone?.rotation.z ?? 0,
  }

  const tick = (now: number): void => {
    if (signal.aborted) {
      return
    }

    const target = mouthShapeToVisemeTarget(getMouthShape())
    for (const mesh of morphMeshes) {
      const dictionary = mesh.morphTargetDictionary
      const influences = mesh.morphTargetInfluences
      if (dictionary === undefined || influences === undefined) {
        continue
      }
      for (const [name, index] of Object.entries(dictionary)) {
        const desired = name === target ? 1 : 0
        const current = influences[index] ?? 0
        influences[index] = current + (desired - current) * 0.38
      }
    }

    if (headBone !== null && !reducedMotion) {
      const elapsed = (now - startedAt) / 1000
      const gesture = getGesture()
      let nod = 0
      let lean = 0
      if (gesture === AvatarGesture.ENCOURAGING_NOD) {
        nod = Math.sin(elapsed * 8) * 0.12
      } else if (gesture === AvatarGesture.ATTENTIVE_LEAN) {
        lean = Math.sin(elapsed * 5) * 0.1
      } else if (gesture === AvatarGesture.WARM_SMILE) {
        nod = Math.sin(elapsed * 3) * 0.03
      } else if (!getSpeaking()) {
        nod = Math.sin(elapsed * 1.1) * 0.015
        lean = Math.sin(elapsed * 0.7) * 0.01
      }
      headBone.rotation.x = restHead.x + nod
      headBone.rotation.z = restHead.z + lean
      headBone.rotation.y = restHead.y
    }

    renderer.render(scene, camera)
    frameId = window.requestAnimationFrame(tick)
  }

  frameId = window.requestAnimationFrame(tick)

  const onAbort = (): void => {
    window.cancelAnimationFrame(frameId)
    observer.disconnect()
    disposeObject(root)
    renderer.dispose()
    signal.removeEventListener('abort', onAbort)
  }
  signal.addEventListener('abort', onAbort)
}
