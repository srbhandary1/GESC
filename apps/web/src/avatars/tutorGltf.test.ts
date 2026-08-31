import { readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'
import { GLB_VISEME_TARGETS } from './visemeTargets'

const AVATAR_DIR = join(dirname(fileURLToPath(import.meta.url)), '../../public/avatars')

const REQUIRED_BONES = [
  'Hips',
  'Spine',
  'Spine1',
  'Neck',
  'Head',
  'LeftShoulder',
  'LeftArm',
  'LeftForeArm',
  'LeftHand',
  'RightShoulder',
  'RightArm',
  'RightForeArm',
  'RightHand',
] as const

interface GltfDocument {
  readonly accessors: readonly { readonly count: number }[]
  readonly images: readonly { readonly mimeType: string; readonly uri?: string }[]
  readonly meshes: readonly {
    readonly extras?: { readonly targetNames?: readonly string[] }
    readonly primitives: readonly {
      readonly indices: number
      readonly targets?: readonly unknown[]
    }[]
  }[]
  readonly nodes: readonly { readonly name?: string }[]
  readonly skins: readonly { readonly joints: readonly number[] }[]
}

function parseGlb(bytes: Buffer): GltfDocument {
  const magic = bytes.subarray(0, 4).toString('ascii')
  expect(magic).toBe('glTF')
  const jsonLength = bytes.readUInt32LE(12)
  const jsonType = bytes.subarray(16, 20).toString('ascii')
  expect(jsonType).toBe('JSON')
  return JSON.parse(bytes.subarray(20, 20 + jsonLength).toString('utf8')) as GltfDocument
}

function pngSize(bytes: Buffer): { width: number; height: number } {
  expect(bytes.subarray(0, 8).equals(Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]))).toBe(
    true,
  )
  return { width: bytes.readUInt32BE(16), height: bytes.readUInt32BE(20) }
}

describe('tutor GLB assets', () => {
  for (const gender of ['female', 'male'] as const) {
    it(`ships a production ${gender} tutor with visemes, humanoid bones, and a 1024 atlas`, () => {
      const gltf = parseGlb(readFileSync(join(AVATAR_DIR, `coach-${gender}.glb`)))
      const mesh = gltf.meshes[0]
      const primitive = mesh?.primitives[0]
      expect(mesh?.extras?.targetNames).toEqual([...GLB_VISEME_TARGETS])
      expect(primitive?.targets).toHaveLength(GLB_VISEME_TARGETS.length)

      const triangleCount = (gltf.accessors[primitive?.indices ?? -1]?.count ?? 0) / 3
      expect(triangleCount).toBeGreaterThan(1000)
      expect(triangleCount).toBeLessThanOrEqual(20_000)

      const jointNames = (gltf.skins[0]?.joints ?? []).map(
        (index) => gltf.nodes[index]?.name ?? '',
      )
      expect(jointNames).toEqual([...REQUIRED_BONES])

      expect(gltf.images[0]?.mimeType).toBe('image/png')
      expect(gltf.images[0]?.uri).toBe(`coach-${gender}.png`)
      const atlas = pngSize(readFileSync(join(AVATAR_DIR, `coach-${gender}.png`)))
      expect(atlas.width).toBe(1024)
      expect(atlas.height).toBe(1024)
      expect(readFileSync(join(AVATAR_DIR, `${gender}_tutor.glb`)).equals(readFileSync(join(AVATAR_DIR, `coach-${gender}.glb`)))).toBe(true)
    })
  }
})
