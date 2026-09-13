import {
  BufferAttribute,
  Float32BufferAttribute,
  SphereGeometry,
  Vector3,
} from 'three'

export const SWATHI_HEAD_MORPHS = ['jawOpen', 'lipWide', 'lipRound'] as const
export const SWATHI_LOOK = 'stylized-yellow-shirt-glasses' as const

const NOSE = { x: 0, y: 0.01, z: 0.18 }
const LEFT_EYE = { x: -0.058, y: 0.038, z: 0.155 }
const RIGHT_EYE = { x: 0.058, y: 0.038, z: 0.155 }
const MOUTH = { x: 0, y: -0.055, z: 0.165 }

export function createSwathiHeadGeometry(): SphereGeometry {
  const geometry = new SphereGeometry(0.2, 72, 56)
  const position = geometry.getAttribute('position')
  const vertex = new Vector3()

  for (let index = 0; index < position.count; index += 1) {
    vertex.fromBufferAttribute(position, index)
    sculptHeadVertex(vertex)
    position.setXYZ(index, vertex.x, vertex.y, vertex.z)
  }

  geometry.morphAttributes.position = [
    displaced(geometry, displaceJawOpen),
    displaced(geometry, displaceLipWide),
    displaced(geometry, displaceLipRound),
  ]
  geometry.computeVertexNormals()
  return geometry
}

export function headMorphInfluences(mouth: {
  readonly jawOpen: number
  readonly lipWide: number
  readonly lipRound: number
}): [number, number, number] {
  return [
    clamp01(mouth.jawOpen),
    clamp01(mouth.lipWide * 0.85),
    clamp01(mouth.lipRound),
  ]
}

function sculptHeadVertex(vertex: Vector3): void {
  vertex.x *= 0.88
  vertex.y *= 1.16
  vertex.z *= 0.98

  const nx = vertex.x / 0.18
  const ny = vertex.y / 0.228
  const nz = vertex.z / 0.192

  if (ny < -0.12) {
    const chin = clamp01((-0.12 - ny) / 0.9)
    vertex.x *= 1 - chin * 0.38
    vertex.z *= 1 - chin * 0.08
    vertex.y -= chin * 0.012
  }

  if (ny > 0.35) {
    const brow = clamp01((ny - 0.35) / 0.65)
    vertex.z *= 1 - brow * 0.06
    vertex.x *= 1 - brow * 0.04
  }

  const cheekY = ny + 0.02
  const cheekZ = nz - 0.35
  const cheek =
    Math.exp(-(cheekY * cheekY) / 0.08) * Math.exp(-(cheekZ * cheekZ) / 0.4)
  vertex.x += Math.sign(vertex.x || 1) * 0.012 * cheek * Math.abs(nx)

  pullToward(vertex, LEFT_EYE, 0.055, -0.016)
  pullToward(vertex, RIGHT_EYE, 0.055, -0.016)
  pullToward(vertex, NOSE, 0.045, 0.028)

  if (nz > 0.2 && Math.abs(nx) < 0.22 && ny > -0.05 && ny < 0.12) {
    vertex.z += 0.01 * (1 - Math.abs(nx) / 0.22)
  }
}

function displaceJawOpen(vertex: Vector3): void {
  const mouth = mouthWeight(vertex)
  if (mouth <= 0) {
    return
  }
  const lower = clamp01((-vertex.y - 0.02) / 0.1)
  vertex.y -= mouth * (0.018 + lower * 0.042)
  vertex.z -= mouth * lower * 0.012
}

function displaceLipWide(vertex: Vector3): void {
  const mouth = mouthWeight(vertex)
  if (mouth <= 0) {
    return
  }
  vertex.x += Math.sign(vertex.x || 1) * mouth * 0.022
  vertex.y += mouth * 0.004
}

function displaceLipRound(vertex: Vector3): void {
  const mouth = mouthWeight(vertex)
  if (mouth <= 0) {
    return
  }
  vertex.x *= 1 - mouth * 0.18
  vertex.z += mouth * 0.018
  vertex.y += mouth * 0.006
}

function mouthWeight(vertex: Vector3): number {
  const dx = vertex.x - MOUTH.x
  const dy = vertex.y - MOUTH.y
  const dz = vertex.z - MOUTH.z
  const radial = Math.sqrt(dx * dx + dy * dy * 1.4 + dz * dz)
  if (radial > 0.08 || vertex.z < 0.08) {
    return 0
  }
  return clamp01(1 - radial / 0.08)
}

function pullToward(
  vertex: Vector3,
  target: { x: number; y: number; z: number },
  radius: number,
  zPush: number,
): void {
  const dx = vertex.x - target.x
  const dy = vertex.y - target.y
  const dz = vertex.z - target.z
  const distance = Math.sqrt(dx * dx + dy * dy + dz * dz)
  if (distance >= radius || distance === 0) {
    return
  }
  const falloff = 1 - distance / radius
  vertex.x += (target.x - vertex.x) * falloff * 0.32
  vertex.y += (target.y - vertex.y) * falloff * 0.32
  vertex.z += zPush * falloff
}

function displaced(
  geometry: SphereGeometry,
  mutate: (vertex: Vector3) => void,
): BufferAttribute {
  const source = geometry.getAttribute('position')
  const values = new Float32Array(source.count * 3)
  const vertex = new Vector3()
  for (let index = 0; index < source.count; index += 1) {
    vertex.fromBufferAttribute(source, index)
    mutate(vertex)
    values[index * 3] = vertex.x
    values[index * 3 + 1] = vertex.y
    values[index * 3 + 2] = vertex.z
  }
  return new Float32BufferAttribute(values, 3)
}

function clamp01(value: number): number {
  return Math.max(0, Math.min(1, value))
}
