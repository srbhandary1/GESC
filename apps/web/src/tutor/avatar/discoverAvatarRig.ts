import { Bone, Object3D, SkinnedMesh, type Mesh } from 'three'
import { normalizeMorphName } from './arkitVisemeWeights'

export interface AvatarRig {
  readonly morphMeshes: readonly Mesh[]
  readonly skinnedMeshes: readonly SkinnedMesh[]
  readonly rigKind: 'static' | 'skinned'
  readonly spine: Object3D | null
  readonly chest: Object3D | null
  readonly leftShoulder: Object3D | null
  readonly rightShoulder: Object3D | null
  readonly leftArm: Object3D | null
  readonly rightArm: Object3D | null
  readonly leftForeArm: Object3D | null
  readonly rightForeArm: Object3D | null
  readonly leftHand: Object3D | null
  readonly rightHand: Object3D | null
  readonly jaw: Object3D | null
  readonly head: Object3D | null
}

const LEFT_ARM = ['leftarm', 'upperarml', 'arml', 'mixamorigleftarm']
const RIGHT_ARM = ['rightarm', 'upperarmr', 'armr', 'mixamorigrightarm']
const LEFT_SHOULDER = ['leftshoulder', 'claviclel', 'mixamorigleftshoulder']
const RIGHT_SHOULDER = ['rightshoulder', 'clavicler', 'mixamorigrightshoulder']
const SPINE = ['spine', 'mixamorigspine']
const CHEST = ['spine2', 'chest', 'upperchest', 'mixamorigspine2']
const LEFT_FOREARM = ['leftforearm', 'lowerarml', 'forearml', 'mixamorigleftforearm']
const RIGHT_FOREARM = [
  'rightforearm',
  'lowerarmr',
  'forearmr',
  'mixamorigrightforearm',
]
const LEFT_HAND = ['lefthand', 'handl', 'mixamoriglefthand', 'wristl']
const RIGHT_HAND = ['righthand', 'handr', 'mixamorigrighthand', 'wristr']
const JAW = ['jaw', 'jawbone', 'mixamorigjaw', 'ccbasejaw', 'mandible']
const HEAD = ['head', 'mixamorighead', 'ccbasehead']

export function discoverAvatarRig(root: Object3D): AvatarRig {
  const morphMeshes: Mesh[] = []
  const skinnedMeshes: SkinnedMesh[] = []
  const candidates = new Map<string, Object3D>()

  root.traverse((node) => {
    if ((node as Mesh).isMesh === true) {
      const mesh = node as Mesh
      if (
        mesh.morphTargetDictionary !== undefined &&
        mesh.morphTargetInfluences !== undefined
      ) {
        morphMeshes.push(mesh)
      }
    }

    if ((node as Bone).isBone === true || node.type === 'Bone') {
      const key = normalizeMorphName(node.name)
      if (key !== '' && !candidates.has(key)) {
        candidates.set(key, node)
      }
    }

    if ((node as SkinnedMesh).isSkinnedMesh === true) {
      const skinned = node as SkinnedMesh
      skinnedMeshes.push(skinned)
      const skeleton = skinned.skeleton
      for (const bone of skeleton.bones) {
        const key = normalizeMorphName(bone.name)
        if (key !== '' && !candidates.has(key)) {
          candidates.set(key, bone)
        }
      }
    }
  })

  return {
    morphMeshes,
    skinnedMeshes,
    rigKind:
      skinnedMeshes.length > 0 && candidates.size >= 8 ? 'skinned' : 'static',
    spine: pickBone(candidates, SPINE),
    chest: pickBone(candidates, CHEST),
    leftShoulder: pickBone(candidates, LEFT_SHOULDER),
    rightShoulder: pickBone(candidates, RIGHT_SHOULDER),
    leftArm: pickBone(candidates, LEFT_ARM),
    rightArm: pickBone(candidates, RIGHT_ARM),
    leftForeArm: pickBone(candidates, LEFT_FOREARM),
    rightForeArm: pickBone(candidates, RIGHT_FOREARM),
    leftHand: pickBone(candidates, LEFT_HAND),
    rightHand: pickBone(candidates, RIGHT_HAND),
    jaw: pickBone(candidates, JAW),
    head: pickBone(candidates, HEAD),
  }
}

function pickBone(
  candidates: ReadonlyMap<string, Object3D>,
  wanted: readonly string[],
): Object3D | null {
  for (const name of wanted) {
    const bone = candidates.get(name)
    if (bone !== undefined) {
      return bone
    }
  }

  for (const [key, bone] of candidates.entries()) {
    if (wanted.some((name) => key.includes(name))) {
      return bone
    }
  }

  return null
}
